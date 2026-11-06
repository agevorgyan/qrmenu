<?php

namespace App\Domains\Loyalty\Services;

use App\Domains\Loyalty\DTOs\PointsTransactionDTO;
use App\Domains\Loyalty\Enums\LoyaltyTransactionType;
use App\Domains\Loyalty\Exceptions\ReferralValidationException;
use App\Domains\Loyalty\Models\LoyaltyProgram;
use App\Domains\Loyalty\Models\LoyaltyReferral;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class ReferralService
{
    public function __construct(
        protected PointsLedgerService $ledgerService
    ) {}

    /**
     * Get or generate a customer's referral code.
     */
    public function getCustomerReferralCode(Customer $customer): string
    {
        $existing = LoyaltyReferral::where('vendor_id', $customer->vendor_id)
            ->where('referrer_customer_id', $customer->id)
            ->value('referral_code');

        if ($existing) {
            return $existing;
        }

        // Generate clean referral code
        return 'REF-'.strtoupper(substr(md5($customer->id.'_ref_'.$customer->vendor_id), 0, 6));
    }

    /**
     * Register a referral link when a new customer enrolls using a referral code.
     */
    public function registerReferral(Customer $referee, string $referralCode): LoyaltyReferral
    {
        $code = strtoupper(trim($referralCode));

        // Find referrer by matching their generated code within the same vendor
        $allReferrers = Customer::where('vendor_id', $referee->vendor_id)
            ->where('id', '!=', $referee->id)
            ->get();

        $referrer = $allReferrers->first(fn ($c) => $this->getCustomerReferralCode($c) === $code);

        if (! $referrer) {
            throw new ReferralValidationException('Invalid referral code.');
        }

        if ($referrer->id === $referee->id) {
            throw new ReferralValidationException('You cannot refer yourself.');
        }

        // Check if referee is already referred
        $existing = LoyaltyReferral::where('vendor_id', $referee->vendor_id)
            ->where('referred_customer_id', $referee->id)
            ->first();

        if ($existing) {
            throw new ReferralValidationException('You have already used a referral code.');
        }

        // Cycle check: Ensure referee is not referrer's referrer
        $cycle = LoyaltyReferral::where('vendor_id', $referee->vendor_id)
            ->where('referrer_customer_id', $referee->id)
            ->where('referred_customer_id', $referrer->id)
            ->exists();

        if ($cycle) {
            throw new ReferralValidationException('Circular referral detected.');
        }

        return LoyaltyReferral::create([
            'vendor_id' => $referee->vendor_id,
            'referrer_customer_id' => $referrer->id,
            'referred_customer_id' => $referee->id,
            'referral_code' => $code,
            'status' => 'registered',
        ]);
    }

    /**
     * Process referral reward qualification upon referee's first completed order.
     */
    public function qualifyReferralOnOrder(Order $order, Customer $referee): ?LoyaltyReferral
    {
        $referral = LoyaltyReferral::where('vendor_id', $order->vendor_id)
            ->where('referred_customer_id', $referee->id)
            ->where('status', 'registered')
            ->first();

        if (! $referral) {
            return null;
        }

        $program = LoyaltyProgram::where('vendor_id', $order->vendor_id)->first();
        if (! $program || ! $program->referrals_enabled) {
            return null;
        }

        $settings = $program->settings['referral'] ?? [];
        $minSpend = (float) ($settings['min_order_spend'] ?? 1000.0);
        $referrerPoints = (int) ($settings['referrer_points'] ?? 100);
        $referredPoints = (int) ($settings['referred_points'] ?? 50);

        $orderTotal = (float) ($order->paid_amount ?: $order->total_amount);
        if ($orderTotal < $minSpend) {
            return null;
        }

        return DB::transaction(function () use ($referral, $order, $referrerPoints, $referredPoints) {
            $referral->update([
                'status' => 'qualified',
                'qualifying_order_id' => $order->id,
                'referrer_points_awarded' => $referrerPoints,
                'referred_points_awarded' => $referredPoints,
                'qualified_at' => now(),
            ]);

            // Award points to Referrer
            if ($referrerPoints > 0) {
                $this->ledgerService->creditPoints(new PointsTransactionDTO(
                    vendorId: $referral->vendor_id,
                    customerId: $referral->referrer_customer_id,
                    points: $referrerPoints,
                    type: LoyaltyTransactionType::ReferralBonus,
                    orderId: $order->id,
                    referenceType: 'referral_bonus',
                    referenceId: $referral->id,
                    notes: 'Referral reward for inviting a new member'
                ));
            }

            // Award points to Referee
            if ($referredPoints > 0) {
                $this->ledgerService->creditPoints(new PointsTransactionDTO(
                    vendorId: $referral->vendor_id,
                    customerId: $referral->referred_customer_id,
                    points: $referredPoints,
                    type: LoyaltyTransactionType::ReferralBonus,
                    orderId: $order->id,
                    referenceType: 'referral_welcome_bonus',
                    referenceId: $referral->id,
                    notes: 'Welcome bonus for joining via referral'
                ));
            }

            return $referral;
        });
    }
}
