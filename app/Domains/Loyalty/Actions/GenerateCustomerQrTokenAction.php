<?php

namespace App\Domains\Loyalty\Actions;

use App\Domains\Loyalty\Models\LoyaltyCustomerQrToken;
use App\Models\Customer;
use Illuminate\Support\Str;

class GenerateCustomerQrTokenAction
{
    /**
     * Generate an ephemeral, secure QR token for a customer.
     * The token contains NO customer PII (zero phone, email, or name in the token).
     * Valid for 10 minutes.
     */
    public function execute(Customer $customer, int $validMinutes = 10): LoyaltyCustomerQrToken
    {
        // Expire older unused tokens for this customer
        LoyaltyCustomerQrToken::where('vendor_id', $customer->vendor_id)
            ->where('customer_id', $customer->id)
            ->whereNull('used_at')
            ->where('expires_at', '<', now())
            ->delete();

        $tokenString = 'lq_'.Str::random(32);

        return LoyaltyCustomerQrToken::create([
            'vendor_id' => $customer->vendor_id,
            'customer_id' => $customer->id,
            'token' => $tokenString,
            'expires_at' => now()->addMinutes($validMinutes),
        ]);
    }

    /**
     * Validate and resolve an ephemeral QR token to a Customer model.
     */
    public function resolve(int $vendorId, string $token): ?Customer
    {
        $qrRecord = LoyaltyCustomerQrToken::where('vendor_id', $vendorId)
            ->where('token', $token)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->with('customer')
            ->first();

        return $qrRecord?->customer;
    }
}
