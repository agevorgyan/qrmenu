<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Admin\Finance\Concerns\AuthorizesFinance;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ImportBankStatementRequest;
use App\Http\Requests\Finance\PerformReconciliationRequest;
use App\Http\Requests\Finance\StoreBankAccountRequest;
use App\Models\BankAccount;
use App\Security\Permission;
use App\Services\BankReconciliationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BankAccountController extends Controller
{
    use AuthorizesFinance;

    public function __construct(
        protected BankReconciliationService $bankService
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::FINANCE_BANK_MANAGE);

        $vendor = $this->getActiveVendor();

        $accounts = BankAccount::where('vendor_id', $vendor->id)
            ->with(['account'])
            ->withCount([
                'transactions',
                'transactions as unreconciled_count' => fn ($q) => $q->where('reconciliation_status', 'unreconciled'),
            ])
            ->orderBy('id', 'asc')
            ->paginate(15);

        return view('admin.finance.bank.index', compact('accounts'));
    }

    public function store(StoreBankAccountRequest $request): RedirectResponse
    {
        $vendor = $this->getActiveVendor();

        $account = $this->bankService->createBankAccount(
            $vendor,
            $request->validated(),
            $request->user()
        );

        return redirect()->route('admin.finance.bank_accounts.show', $account)
            ->with('success', "Bank account '{$account->account_name}' created and linked to Chart of Accounts.");
    }

    public function show(BankAccount $bankAccount, Request $request): View
    {
        $this->authorizePermission(Permission::FINANCE_BANK_MANAGE);

        $vendor = $this->getActiveVendor();
        if ((int) $bankAccount->vendor_id !== (int) $vendor->id) {
            abort(403);
        }

        $query = $bankAccount->transactions()->with(['matchedJournalEntryLine.journalEntry']);

        if ($request->filled('status')) {
            $query->where('reconciliation_status', $request->input('status'));
        }

        $transactions = $query->orderBy('transaction_date', 'desc')->paginate(25);
        $reconciliations = $bankAccount->reconciliations()->with(['reconciledByUser'])->latest()->take(10)->get();

        return view('admin.finance.bank.show', compact('bankAccount', 'transactions', 'reconciliations'));
    }

    public function import(ImportBankStatementRequest $request, BankAccount $bankAccount): RedirectResponse
    {
        $vendor = $this->getActiveVendor();
        if ((int) $bankAccount->vendor_id !== (int) $vendor->id) {
            abort(403);
        }

        $transactions = [];

        if ($request->hasFile('statement_file')) {
            $file = $request->file('statement_file');
            $handle = fopen($file->getRealPath(), 'r');
            $header = fgetcsv($handle); // read header row

            while (($row = fgetcsv($handle)) !== false) {
                if (empty($row) || count($row) < 3) {
                    continue;
                }

                // Expected CSV format: Date, Description, Amount, Type, Reference
                $date = trim($row[0] ?? '');
                $desc = trim($row[1] ?? '');
                $rawAmount = abs((float) preg_replace('/[^0-9.-]/', '', $row[2] ?? 0));
                $type = isset($row[3]) && strtolower(trim($row[3])) === 'debit' ? 'debit' : 'credit';
                $ref = trim($row[4] ?? '');

                if (! empty($date) && $rawAmount > 0) {
                    $transactions[] = [
                        'transaction_date' => Carbon::parse($date)->toDateString(),
                        'description' => $desc,
                        'amount' => $rawAmount,
                        'type' => $type,
                        'reference_number' => $ref ?: null,
                    ];
                }
            }
            fclose($handle);
        }

        if (empty($transactions)) {
            return back()->with('error', 'No valid transactions found in statement file.');
        }

        $result = $this->bankService->importTransactions($bankAccount, $transactions, $request->user());

        return redirect()->route('admin.finance.bank_accounts.show', $bankAccount)
            ->with('success', "Import completed: {$result['imported']} imported, {$result['auto_matched']} auto-matched, {$result['skipped']} duplicates skipped.");
    }

    public function reconcile(PerformReconciliationRequest $request, BankAccount $bankAccount): RedirectResponse
    {
        $vendor = $this->getActiveVendor();
        if ((int) $bankAccount->vendor_id !== (int) $vendor->id) {
            abort(403);
        }

        try {
            $statementDate = Carbon::parse($request->input('statement_date'));
            $statementBalance = (float) $request->input('statement_balance');

            $rec = $this->bankService->performReconciliation(
                $bankAccount,
                $statementDate,
                $statementBalance,
                $request->user(),
                $request->validated()
            );

            return redirect()->route('admin.finance.bank_accounts.show', $bankAccount)
                ->with('success', "Bank reconciliation #{$rec->id} recorded. Difference: {$rec->difference} {$bankAccount->currency}.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
