<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Admin\Finance\Concerns\AuthorizesFinance;
use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Security\Permission;
use App\Services\FinancialLedgerService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class GeneralLedgerController extends Controller
{
    use AuthorizesFinance;

    public function __construct(
        protected FinancialLedgerService $ledgerService
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::FINANCE_LEDGER_VIEW);

        $vendor = $this->getActiveVendor();

        $query = ChartOfAccount::where('vendor_id', $vendor->id);

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%");
            });
        }

        $accounts = $query->orderBy('code', 'asc')->paginate(30);

        return view('admin.finance.ledger.index', compact('accounts'));
    }

    public function entries(Request $request): View
    {
        $this->authorizePermission(Permission::FINANCE_LEDGER_VIEW);

        $vendor = $this->getActiveVendor();

        $query = JournalEntry::where('vendor_id', $vendor->id)
            ->with(['lines.account', 'createdByUser']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('source_type')) {
            $query->where('source_type', $request->input('source_type'));
        }

        if ($request->filled('start_date')) {
            $query->where('entry_date', '>=', Carbon::parse($request->input('start_date'))->toDateString());
        }

        if ($request->filled('end_date')) {
            $query->where('entry_date', '<=', Carbon::parse($request->input('end_date'))->toDateString());
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('entry_number', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        $entries = $query->orderBy('entry_date', 'desc')->orderBy('id', 'desc')->paginate(25);

        return view('admin.finance.ledger.entries', compact('entries'));
    }

    public function showEntry(JournalEntry $entry): View
    {
        $this->authorizePermission(Permission::FINANCE_LEDGER_VIEW);

        $vendor = $this->getActiveVendor();
        if ((int) $entry->vendor_id !== (int) $vendor->id) {
            abort(403);
        }

        $entry->load(['lines.account', 'createdByUser', 'reversalEntry', 'originalEntry']);

        return view('admin.finance.ledger.show_entry', compact('entry'));
    }

    public function reverse(Request $request, JournalEntry $entry): RedirectResponse
    {
        $this->authorizePermission(Permission::FINANCE_LEDGER_MANAGE);

        $vendor = $this->getActiveVendor();
        if ((int) $entry->vendor_id !== (int) $vendor->id) {
            abort(403);
        }

        $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $reversal = $this->ledgerService->createReversalEntry(
                $entry,
                $request->input('reason'),
                $request->user()
            );

            return redirect()->route('admin.finance.ledger.entries.show', $reversal)
                ->with('success', __('Reversal entry :number posted successfully.', ['number' => $reversal->entry_number]));
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function seedCoa(): RedirectResponse
    {
        $this->authorizePermission(Permission::FINANCE_LEDGER_MANAGE);

        $vendor = $this->getActiveVendor();
        $this->ledgerService->initializeDefaultAccounts($vendor);

        return back()->with('success', __('Chart of Accounts initialized successfully.'));
    }

    public function export(Request $request): Response
    {
        $this->authorizePermission(Permission::FINANCE_REPORTS_EXPORT);

        $vendor = $this->getActiveVendor();

        $query = JournalEntry::where('vendor_id', $vendor->id)
            ->with(['lines.account']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('start_date')) {
            $query->where('entry_date', '>=', Carbon::parse($request->input('start_date'))->toDateString());
        }

        if ($request->filled('end_date')) {
            $query->where('entry_date', '<=', Carbon::parse($request->input('end_date'))->toDateString());
        }

        $entries = $query->orderBy('entry_date', 'desc')->get();

        $csv = "Entry Number,Date,Status,Source Type,Description,Total Debit,Total Credit,Currency\n";
        foreach ($entries as $e) {
            $cleanDesc = str_replace('"', '""', $e->description ?? '');
            $csv .= "{$e->entry_number},{$e->entry_date},{$e->status},{$e->source_type},\"{$cleanDesc}\",{$e->total_debit},{$e->total_credit},{$e->currency}\n";
        }

        $filename = 'journal-entries-'.Carbon::now()->format('Y-m-d').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
