<?php

namespace App\Domains\Wholesale\Http\Controllers;

use App\Domains\Wholesale\Models\WholesaleCustomer;
use App\Domains\Wholesale\Models\WholesalePayment;
use App\Domains\Wholesale\Models\WholesaleSale;
use App\Domains\Wholesale\Models\WholesaleSeller;
use App\Domains\Wholesale\Services\WholesaleAccountingService;
use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WholesalePaymentController extends Controller
{
    public function __construct(
        protected WholesaleAccountingService $accountingService,
        protected TenantContext $tenantContext
    ) {}

    /**
     * Display a listing of payments.
     */
    public function index(Request $request): View
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;
        $filters = $request->only(['customer_id', 'status']);

        $payments = $this->accountingService->listPayments($vendor, $filters, 15);
        $customers = WholesaleCustomer::where('vendor_id', $vendor->id)->orderBy('name')->get();

        return view('wholesale.payments.index', compact('vendor', 'payments', 'customers', 'filters'));
    }

    /**
     * Show form to record a payment.
     */
    public function create(Request $request): View
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;
        $customerId = $request->query('customer_id');
        $saleId = $request->query('sale_id');

        $customers = WholesaleCustomer::where('vendor_id', $vendor->id)->where('is_active', true)->orderBy('name')->get();
        $sellers = WholesaleSeller::where('vendor_id', $vendor->id)->where('is_active', true)->with('bankAccounts')->get();
        $cashRegisters = CashRegister::where('vendor_id', $vendor->id)->where('is_active', true)->get();

        $selectedSale = $saleId ? WholesaleSale::where('vendor_id', $vendor->id)->find($saleId) : null;

        return view('wholesale.payments.create', compact('vendor', 'customers', 'sellers', 'cashRegisters', 'customerId', 'selectedSale'));
    }

    /**
     * Store a payment and post double-entry ledger entry.
     */
    public function store(Request $request): RedirectResponse
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;

        $validated = $request->validate([
            'customer_id' => ['required', 'integer'],
            'seller_id' => ['required', 'integer'],
            'payment_date' => ['nullable', 'date'],
            'payment_method' => ['required', 'string', 'in:CASH,BANK_TRANSFER,CARD,CHECK'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'bank_account_id' => ['nullable', 'integer'],
            'cash_register_id' => ['nullable', 'integer'],
            'sale_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = $this->accountingService->recordPayment($vendor, $validated, $request->user());

        return redirect()->route('admin.wholesale.payments.show', $payment)
            ->with('success', __('Wholesale Payment :num recorded and posted to General Ledger.', ['num' => $payment->payment_number]));
    }

    /**
     * Display payment details and journal entry link.
     */
    public function show(WholesalePayment $payment): View
    {
        $payment->load(['customer', 'seller', 'bankAccount', 'cashRegister', 'journalEntry.lines.account', 'allocations.sale']);
        $vendor = $this->tenantContext->getTenant();

        return view('wholesale.payments.show', compact('payment', 'vendor'));
    }
}

