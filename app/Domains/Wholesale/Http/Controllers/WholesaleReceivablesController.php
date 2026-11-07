<?php

namespace App\Domains\Wholesale\Http\Controllers;

use App\Domains\Wholesale\Models\WholesaleCustomer;
use App\Domains\Wholesale\Services\WholesaleAccountingService;
use App\Http\Controllers\Controller;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WholesaleReceivablesController extends Controller
{
    public function __construct(
        protected WholesaleAccountingService $accountingService,
        protected TenantContext $tenantContext
    ) {}

    /**
     * Display Accounts Receivable aging analysis and customer credit balances.
     */
    public function index(Request $request): View
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;

        $summary = $this->accountingService->getAccountsReceivableSummary($vendor);
        $debtors = WholesaleCustomer::where('vendor_id', $vendor->id)
            ->where('current_balance', '>', 0)
            ->orderBy('current_balance', 'desc')
            ->paginate(15);

        return view('wholesale.receivables.index', compact('vendor', 'summary', 'debtors'));
    }
}
