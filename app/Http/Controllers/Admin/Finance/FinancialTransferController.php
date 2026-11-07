<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Admin\Finance\Concerns\AuthorizesFinance;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreFinancialTransferRequest;
use App\Models\BankAccount;
use App\Models\CashRegister;
use App\Models\FinancialAuditLog;
use App\Models\FinancialTransfer;
use App\Models\Location;
use App\Security\Permission;
use App\Services\FinancialTransactionService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FinancialTransferController extends Controller
{
    use AuthorizesFinance;

    public function __construct(
        protected FinancialTransactionService $transactionService
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::FINANCE_TRANSFERS_MANAGE);

        $vendor = $this->getActiveVendor();

        $query = FinancialTransfer::where('vendor_id', $vendor->id)
            ->with(['initiatedByUser', 'approvedByUser', 'journalEntry']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $transfers = $query->orderBy('transfer_date', 'desc')->paginate(20);

        $registers = CashRegister::where('vendor_id', $vendor->id)->where('is_active', true)->get();
        $bankAccounts = BankAccount::where('vendor_id', $vendor->id)->where('is_active', true)->get();
        $locations = Location::where('vendor_id', $vendor->id)->get();

        return view('admin.finance.transfers.index', compact('transfers', 'registers', 'bankAccounts', 'locations'));
    }

    public function store(StoreFinancialTransferRequest $request): RedirectResponse
    {
        $vendor = $this->getActiveVendor();

        $data = $request->validated();
        $amount = (float) $data['amount'];
        $transferDate = $data['transfer_date'] ? Carbon::parse($data['transfer_date']) : Carbon::today();

        DB::transaction(function () use ($vendor, $data, $amount, $transferDate, $request) {
            $transferNumber = 'TRF-'.Carbon::now()->year.'-'.str_pad((string) (FinancialTransfer::where('vendor_id', $vendor->id)->count() + 1), 5, '0', STR_PAD_LEFT);

            $transfer = FinancialTransfer::create([
                'vendor_id' => $vendor->id,
                'transfer_number' => $transferNumber,
                'from_type' => $data['source_type'],
                'from_id' => $data['source_id'] ?? null,
                'to_type' => $data['destination_type'],
                'to_id' => $data['destination_id'] ?? null,
                'amount' => $amount,
                'currency' => $vendor->currency ?? 'AMD',
                'transfer_date' => $transferDate->toDateString(),
                'status' => 'completed',
                'initiated_by' => $request->user()->id,
                'approved_by' => $request->user()->id,
                'notes' => $data['notes'] ?? null,
            ]);

            // Deduct from source and credit to destination models if registers or bank
            if ($transfer->from_type === 'cash_register' && $transfer->from_id) {
                $sourceReg = CashRegister::find($transfer->from_id);
                $sourceReg?->decrement('current_balance', $amount);
            } elseif ($transfer->from_type === 'bank_account' && $transfer->from_id) {
                $sourceBank = BankAccount::find($transfer->from_id);
                $sourceBank?->decrement('current_balance', $amount);
            }

            if ($transfer->to_type === 'cash_register' && $transfer->to_id) {
                $destReg = CashRegister::find($transfer->to_id);
                $destReg?->increment('current_balance', $amount);
            } elseif ($transfer->to_type === 'bank_account' && $transfer->to_id) {
                $destBank = BankAccount::find($transfer->to_id);
                $destBank?->increment('current_balance', $amount);
            }

            // Record Asset-to-Asset Journal Entry (Dr Destination Asset, Cr Source Asset)
            $this->transactionService->recordCashTransfer($transfer, $request->user());

            FinancialAuditLog::record(
                vendor: $vendor,
                action: 'transfer_completed',
                auditable: $transfer,
                user: $request->user(),
                newValues: [
                    'transfer_number' => $transferNumber,
                    'amount' => $amount,
                    'from' => "{$transfer->from_type} #{$transfer->from_id}",
                    'to' => "{$transfer->to_type} #{$transfer->to_id}",
                ],
                amount: $amount
            );
        });

        return redirect()->route('admin.finance.transfers.index')
            ->with('success', 'Internal cash/bank transfer completed and posted to ledger.');
    }
}
