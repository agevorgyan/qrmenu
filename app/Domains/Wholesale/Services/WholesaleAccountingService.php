<?php

namespace App\Domains\Wholesale\Services;

use App\Domains\Wholesale\Models\WholesaleCustomer;
use App\Domains\Wholesale\Models\WholesalePayment;
use App\Domains\Wholesale\Models\WholesalePaymentAllocation;
use App\Domains\Wholesale\Models\WholesaleSale;
use App\Domains\Wholesale\Models\WholesaleSeller;
use App\Models\CashRegister;
use App\Models\JournalEntry;
use App\Models\PosCashMovement;
use App\Models\PosShift;
use App\Models\User;
use App\Models\Vendor;
use App\Services\FinancialLedgerService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WholesaleAccountingService
{
    public function __construct(
        protected FinancialLedgerService $financialLedger
    ) {}

    /**
     * List payments for a tenant.
     *
     * @param  array{customer_id?: int, status?: string}  $filters
     */
    public function listPayments(Vendor $vendor, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = WholesalePayment::where('vendor_id', $vendor->id)
            ->with(['customer', 'seller', 'bankAccount', 'cashRegister']);

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Post a commercial sale invoice to the Core General Ledger.
     */
    public function postSaleToLedger(WholesaleSale $sale, ?User $user = null): JournalEntry
    {
        $vendor = $sale->vendor;

        $arAccount = $this->financialLedger->getAccountByCode($vendor, '2211'); // Accounts Receivable
        $revenueAccount = $this->financialLedger->getAccountByCode($vendor, '6111'); // Sales Revenue
        $vatAccount = $this->financialLedger->getAccountByCode($vendor, '5243'); // VAT Payable

        $totalAmount = (float) $sale->total_amount;
        $subtotal = (float) $sale->subtotal;
        $taxTotal = (float) $sale->tax_total;
        $deliveryFee = (float) $sale->delivery_fee;

        $lines = [];

        // Debit: 2211 Accounts Receivable (Full Invoice Total)
        $lines[] = [
            'account_id' => $arAccount->id,
            'type' => 'debit',
            'amount' => $totalAmount,
            'description' => __('Wholesale Invoice :num - :cust', ['num' => $sale->invoice_number, 'cust' => $sale->customer->name]),
        ];

        // Credit: 6111 Sales Revenue (Subtotal)
        $revenueAmount = $subtotal;
        if ($deliveryFee > 0) {
            $serviceAccount = $this->financialLedger->getAccountByCode($vendor, '6114'); // Service Revenue
            $lines[] = [
                'account_id' => $serviceAccount->id,
                'type' => 'credit',
                'amount' => $deliveryFee,
                'description' => __('Wholesale Delivery Fee :num', ['num' => $sale->invoice_number]),
            ];
        }

        $lines[] = [
            'account_id' => $revenueAccount->id,
            'type' => 'credit',
            'amount' => $revenueAmount,
            'description' => __('Wholesale Goods Sales Revenue :num', ['num' => $sale->invoice_number]),
        ];

        // Credit: 5243 VAT Payable (Tax Total)
        if ($taxTotal > 0) {
            $lines[] = [
                'account_id' => $vatAccount->id,
                'type' => 'credit',
                'amount' => $taxTotal,
                'description' => __('Armenia SRC VAT (20%) on Wholesale Invoice :num', ['num' => $sale->invoice_number]),
            ];
        }

        return $this->financialLedger->postJournalEntry(
            vendor: $vendor,
            data: [
                'entry_date' => $sale->sale_date ? $sale->sale_date->toDateString() : now()->toDateString(),
                'source_type' => 'wholesale_sale',
                'source_id' => $sale->id,
                'idempotency_key' => 'wholesale_sale_ledger_'.$sale->id,
                'description' => __('Commercial Wholesale Invoice :num for :cust', ['num' => $sale->invoice_number, 'cust' => $sale->customer->name]),
                'lines' => $lines,
            ],
            user: $user
        );
    }

    /**
     * Record a wholesale payment, allocate to invoices, update AR balance, and post to General Ledger.
     *
     * @param  array{
     *     customer_id: int,
     *     seller_id: int,
     *     payment_date?: string,
     *     payment_method: string,
     *     amount: float,
     *     reference_number?: string|null,
     *     bank_account_id?: int|null,
     *     cash_register_id?: int|null,
     *     sale_id?: int|null,
     *     notes?: string|null
     * }  $data
     */
    public function recordPayment(Vendor $vendor, array $data, ?User $user = null): WholesalePayment
    {
        return DB::transaction(function () use ($vendor, $data, $user) {
            $customer = WholesaleCustomer::where('vendor_id', $vendor->id)->findOrFail($data['customer_id']);
            $seller = WholesaleSeller::where('vendor_id', $vendor->id)->findOrFail($data['seller_id']);

            $amount = (float) $data['amount'];
            $paymentMethod = $data['payment_method'];
            $paymentNumber = $this->generatePaymentNumber($vendor);

            $payment = WholesalePayment::create([
                'vendor_id' => $vendor->id,
                'customer_id' => $customer->id,
                'seller_id' => $seller->id,
                'payment_number' => $paymentNumber,
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'allocated_amount' => 0.0000,
                'unallocated_amount' => $amount,
                'status' => 'COMPLETED',
                'reference_number' => $data['reference_number'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'cash_register_id' => $data['cash_register_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'received_by_user_id' => $user?->id,
            ]);

            // Allocate to sale if passed
            if (! empty($data['sale_id'])) {
                $sale = WholesaleSale::where('vendor_id', $vendor->id)->find($data['sale_id']);
                if ($sale) {
                    $unpaid = max(0, (float) $sale->total_amount - (float) $sale->paid_amount);
                    $allocated = min($amount, $unpaid);

                    WholesalePaymentAllocation::create([
                        'payment_id' => $payment->id,
                        'sale_id' => $sale->id,
                        'amount' => $allocated,
                        'allocated_at' => now(),
                    ]);

                    $sale->increment('paid_amount', $allocated);
                    if ((float) $sale->fresh()->paid_amount >= (float) $sale->total_amount) {
                        $sale->update(['status' => 'PAID']);
                    }

                    $payment->update([
                        'allocated_amount' => $allocated,
                        'unallocated_amount' => $amount - $allocated,
                    ]);
                }
            }

            // Decrement Customer AR Balance
            $customer->decrement('current_balance', $amount);

            // General Ledger Double-Entry Balanced Posting
            $arAccount = $this->financialLedger->getAccountByCode($vendor, '2211'); // Trade Receivables
            $lines = [];

            if ($paymentMethod === 'CASH') {
                $cashAccount = $this->financialLedger->getAccountByCode($vendor, '2511'); // POS Cash Till
                $lines[] = [
                    'account_id' => $cashAccount->id,
                    'type' => 'debit',
                    'amount' => $amount,
                    'description' => __('Cash collection for Wholesale Payment :num', ['num' => $payment->payment_number]),
                ];

                // If associated with a Cash Register, log POS cash movement
                if (! empty($data['cash_register_id'])) {
                    $cashRegister = CashRegister::find($data['cash_register_id']);
                    if ($cashRegister) {
                        $activeShift = PosShift::where('cash_register_id', $cashRegister->id)->where('status', 'open')->first();
                        PosCashMovement::create([
                            'uuid' => (string) Str::uuid(),
                            'vendor_id' => $vendor->id,
                            'pos_shift_id' => $activeShift?->id,
                            'cash_register_id' => $cashRegister->id,
                            'user_id' => $user?->id,
                            'type' => 'cash_in',
                            'amount' => $amount,
                            'reason' => 'WHOLESALE_PAYMENT',
                            'notes' => __('Wholesale Payment :num from :cust', ['num' => $payment->payment_number, 'cust' => $customer->name]),
                        ]);
                    }
                }
            } else {
                $bankAccount = $this->financialLedger->getAccountByCode($vendor, '2521'); // Operating Bank Account
                $lines[] = [
                    'account_id' => $bankAccount->id,
                    'type' => 'debit',
                    'amount' => $amount,
                    'description' => __('Bank transfer for Wholesale Payment :num (Ref: :ref)', [
                        'num' => $payment->payment_number,
                        'ref' => $payment->reference_number ?? 'N/A',
                    ]),
                ];
            }

            // Credit: 2211 Accounts Receivable
            $lines[] = [
                'account_id' => $arAccount->id,
                'type' => 'credit',
                'amount' => $amount,
                'description' => __('AR reduction for Wholesale Payment :num - :cust', ['num' => $payment->payment_number, 'cust' => $customer->name]),
            ];

            $journalEntry = $this->financialLedger->postJournalEntry(
                vendor: $vendor,
                data: [
                    'entry_date' => $payment->payment_date->toDateString(),
                    'source_type' => 'wholesale_payment',
                    'source_id' => $payment->id,
                    'idempotency_key' => 'wholesale_pmt_ledger_'.$payment->id,
                    'description' => __('Wholesale Payment :num received from :cust', ['num' => $payment->payment_number, 'cust' => $customer->name]),
                    'lines' => $lines,
                ],
                user: $user
            );

            $payment->update(['journal_entry_id' => $journalEntry->id]);

            return $payment->fresh(['allocations', 'customer', 'seller']);
        });
    }

    /**
     * Get Accounts Receivable aging analysis and overview report.
     *
     * @return array{
     *     total_receivables: float,
     *     current_bucket: float,
     *     overdue_30: float,
     *     overdue_60: float,
     *     overdue_90_plus: float,
     *     total_credit_limit: float,
     *     active_debtors_count: int
     * }
     */
    public function getAccountsReceivableSummary(Vendor $vendor): array
    {
        $customers = WholesaleCustomer::where('vendor_id', $vendor->id)
            ->where('current_balance', '>', 0)
            ->get();

        $totalReceivables = (float) $customers->sum('current_balance');
        $totalCreditLimit = (float) $customers->sum('credit_limit');

        // Analyze aging by sales due dates
        $unpaidSales = WholesaleSale::where('vendor_id', $vendor->id)
            ->where('status', 'ISSUED')
            ->get();

        $current = 0.0;
        $overdue30 = 0.0;
        $overdue60 = 0.0;
        $overdue90 = 0.0;

        $today = Carbon::today();

        foreach ($unpaidSales as $sale) {
            $unpaid = max(0, (float) $sale->total_amount - (float) $sale->paid_amount);
            if ($unpaid <= 0) {
                continue;
            }

            $dueDate = $sale->due_date ?: $sale->sale_date;
            $daysOverdue = $today->diffInDays($dueDate, false);

            if ($daysOverdue >= 0) {
                $current += $unpaid;
            } elseif ($daysOverdue >= -30) {
                $overdue30 += $unpaid;
            } elseif ($daysOverdue >= -60) {
                $overdue60 += $unpaid;
            } else {
                $overdue90 += $unpaid;
            }
        }

        return [
            'total_receivables' => $totalReceivables,
            'current_bucket' => $current,
            'overdue_30' => $overdue30,
            'overdue_60' => $overdue60,
            'overdue_90_plus' => $overdue90,
            'total_credit_limit' => $totalCreditLimit,
            'active_debtors_count' => $customers->count(),
        ];
    }

    /**
     * Generate sequential payment number: WP-YYYYMMDD-####
     */
    public function generatePaymentNumber(Vendor $vendor): string
    {
        $datePrefix = 'WP-'.now()->format('Ymd');
        $countToday = WholesalePayment::where('vendor_id', $vendor->id)
            ->where('payment_number', 'like', "{$datePrefix}-%")
            ->count();

        return sprintf('%s-%04d', $datePrefix, $countToday + 1);
    }
}
