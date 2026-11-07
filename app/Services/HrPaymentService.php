<?php

namespace App\Services;

use App\Models\EmployeeAdvance;
use App\Models\PayrollItem;
use App\Models\SalaryPayment;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class HrPaymentService
{
    /**
     * Record a salary payout transaction against a payroll line item.
     */
    public function recordSalaryPayment(
        PayrollItem $item,
        User $actor,
        float $amount,
        string $method = 'bank_transfer',
        ?string $reference = null,
        ?string $proofPath = null,
        ?string $notes = null
    ): SalaryPayment {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException(__('Payment amount must be greater than zero.'));
        }

        $pending = $item->pending_balance;
        if ($amount > $pending) {
            throw new InvalidArgumentException(__('Payment amount exceeds remaining payable salary balance of :balance', ['balance' => $pending]));
        }

        return DB::transaction(function () use ($item, $actor, $amount, $method, $reference, $proofPath, $notes) {
            $paymentNumber = 'PAY-'.strtoupper(Str::random(10));

            $payment = SalaryPayment::create([
                'vendor_id' => $item->vendor_id,
                'payroll_period_id' => $item->payroll_period_id,
                'payroll_item_id' => $item->id,
                'user_id' => $item->user_id,
                'payment_number' => $paymentNumber,
                'payment_date' => Carbon::now()->toDateString(),
                'amount' => $amount,
                'payment_method' => $method,
                'reference_number' => $reference,
                'proof_document_path' => $proofPath,
                'notes' => $notes,
                'created_by' => $actor->id,
                'status' => 'completed',
            ]);

            // Update item paid amount and status
            $newPaid = round((float) $item->paid_amount + $amount, 2);
            $newStatus = ($newPaid >= (float) $item->net_salary) ? 'paid' : 'partially_paid';

            $item->update([
                'paid_amount' => $newPaid,
                'payment_status' => $newStatus,
            ]);

            // Update period total paid
            $period = $item->period;
            $periodTotalPaid = round((float) $period->payments()->where('status', 'completed')->sum('amount'), 2);
            $periodStatus = ($periodTotalPaid >= (float) $period->total_net) ? 'paid' : $period->status;

            $period->update([
                'total_paid' => $periodTotalPaid,
                'status' => $periodStatus,
            ]);

            try {
                app(FinancialTransactionService::class)->recordSalaryPayout($payment, $actor);
            } catch (\Throwable $e) {
                Log::warning("Could not record GL for SalaryPayment {$payment->id}: {$e->getMessage()}");
            }

            return $payment;
        });
    }

    /**
     * Request a salary advance.
     */
    public function requestAdvance(
        Vendor $vendor,
        User $user,
        float $amount,
        string $reason,
        string $method = 'cash'
    ): EmployeeAdvance {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException(__('Advance amount must be greater than zero.'));
        }

        return EmployeeAdvance::create([
            'vendor_id' => $vendor->id,
            'user_id' => $user->id,
            'amount' => $amount,
            'requested_date' => Carbon::now()->toDateString(),
            'payment_method' => $method,
            'recovered_amount' => 0.00,
            'remaining_amount' => $amount,
            'status' => 'pending',
            'reason' => $reason,
        ]);
    }

    /**
     * Approve and disburse salary advance.
     */
    public function disburseAdvance(
        EmployeeAdvance $advance,
        User $actor,
        bool $approve
    ): EmployeeAdvance {
        if ($advance->status !== 'pending') {
            throw new InvalidArgumentException(__('Only pending advance requests can be processed.'));
        }

        if (! $approve) {
            $advance->update(['status' => 'cancelled']);

            return $advance->fresh();
        }

        $advance->update([
            'status' => 'disbursed',
            'approved_by' => $actor->id,
            'disbursement_date' => Carbon::now()->toDateString(),
        ]);

        try {
            app(FinancialTransactionService::class)->recordSalaryAdvance($advance, $actor);
        } catch (\Throwable $e) {
            Log::warning("Could not record GL for EmployeeAdvance {$advance->id}: {$e->getMessage()}");
        }

        return $advance->fresh();
    }
}
