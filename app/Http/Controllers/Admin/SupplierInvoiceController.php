<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierPaymentRequest;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Security\Permission;
use App\Services\ProcurementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SupplierInvoiceController extends Controller
{
    public function __construct(
        protected ProcurementService $procurementService
    ) {}

    /**
     * Display Accounts Payable invoices ledger.
     */
    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::PROCUREMENT_VIEW);

        $vendorId = Auth::user()->vendor_id;

        $query = SupplierInvoice::where('vendor_id', $vendorId)
            ->with(['supplier', 'warehouse', 'purchaseOrder']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'ilike', "%{$search}%")
                    ->orWhereHas('supplier', function ($sq) use ($search) {
                        $sq->where('name', 'ilike', "%{$search}%");
                    });
            });
        }

        if ($status = $request->input('status')) {
            $query->where('payment_status', $status);
        }

        if ($supplierId = $request->input('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        $invoices = $query->orderByDesc('invoice_date')->paginate(20)->withQueryString();

        $suppliers = Supplier::where('vendor_id', $vendorId)->where('is_active', true)->orderBy('name')->get();

        $totalInvoiced = (float) SupplierInvoice::where('vendor_id', $vendorId)->sum('total_amount');
        $totalPaid = (float) SupplierInvoice::where('vendor_id', $vendorId)->sum('paid_amount');
        $totalOutstanding = (float) SupplierInvoice::where('vendor_id', $vendorId)->sum('remaining_balance');
        $overdueCount = SupplierInvoice::where('vendor_id', $vendorId)
            ->where('payment_status', '!=', SupplierInvoice::PAYMENT_PAID)
            ->where('due_date', '<', now()->toDateString())
            ->count();

        return view('admin.inventory.invoices.index', compact(
            'invoices',
            'suppliers',
            'totalInvoiced',
            'totalPaid',
            'totalOutstanding',
            'overdueCount'
        ));
    }

    /**
     * Show single supplier invoice details.
     */
    public function show(SupplierInvoice $supplierInvoice): View
    {
        $this->authorizePermission(Permission::PROCUREMENT_VIEW);

        $supplierInvoice->load([
            'supplier',
            'warehouse',
            'purchaseOrder.items.ingredient',
            'payments.creator',
            'receiver',
        ]);

        return view('admin.inventory.invoices.show', compact('supplierInvoice'));
    }

    /**
     * Record payment to a supplier or against an invoice.
     */
    public function storePayment(StoreSupplierPaymentRequest $request): RedirectResponse
    {
        $supplier = Supplier::findOrFail($request->input('supplier_id'));
        $invoice = $request->filled('supplier_invoice_id')
            ? SupplierInvoice::find($request->input('supplier_invoice_id'))
            : null;

        $payment = $this->procurementService->recordPayment(
            supplier: $supplier,
            amount: (float) $request->input('amount'),
            paymentMethod: $request->input('payment_method'),
            invoice: $invoice,
            paymentDate: $request->input('payment_date'),
            referenceNumber: $request->input('reference_number'),
            notes: $request->input('notes'),
            user: Auth::user()
        );

        return back()->with('success', __('Payment #:number of $:amount recorded successfully.', [
            'number' => $payment->payment_number,
            'amount' => number_format($payment->amount, 2),
        ]));
    }

    protected function authorizePermission(string $permission): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission($permission)) {
            abort(403, 'Unauthorized action.');
        }
    }
}
