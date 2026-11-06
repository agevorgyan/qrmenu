<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierPaymentRequest;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\Warehouse;
use App\Security\Permission;
use App\Services\ArmenianTaxInvoiceService;
use App\Services\ProcurementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SupplierInvoiceController extends Controller
{
    public function __construct(
        protected ProcurementService $procurementService,
        protected ArmenianTaxInvoiceService $taxInvoiceService
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
        $warehouses = Warehouse::where('vendor_id', $vendorId)->where('is_active', true)->orderBy('name')->get();

        $totalInvoiced = (float) SupplierInvoice::where('vendor_id', $vendorId)->sum('total_amount');
        $totalPaid = (float) SupplierInvoice::where('vendor_id', $vendorId)->sum('paid_amount');
        $totalOutstanding = (float) SupplierInvoice::where('vendor_id', $vendorId)->sum('remaining_balance');
        $vendor = Auth::user()?->vendor;
        $today = $vendor ? $vendor->getLocalTime()->toDateString() : now()->toDateString();

        $overdueCount = SupplierInvoice::where('vendor_id', $vendorId)
            ->where('payment_status', '!=', SupplierInvoice::PAYMENT_PAID)
            ->where('due_date', '<', $today)
            ->count();

        return view('admin.inventory.invoices.index', compact(
            'invoices',
            'suppliers',
            'warehouses',
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
            'items.ingredient.unit',
            'items.unit',
        ]);

        return view('admin.inventory.invoices.show', compact('supplierInvoice'));
    }

    /**
     * Import a received tax invoice directly from Armenian e-invoicing (XML or XLSX).
     */
    public function importTaxInvoice(Request $request): RedirectResponse
    {
        $this->authorizePermission(Permission::PROCUREMENT_CREATE);

        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
        ], [
            'file.required' => __('Please select an invoice file to import.'),
            'warehouse_id.required' => __('Please select a destination warehouse.'),
        ]);

        $vendor = Auth::user()->vendor;
        $warehouse = Warehouse::where('vendor_id', $vendor->id)->findOrFail($request->input('warehouse_id'));

        try {
            $parsed = $this->taxInvoiceService->parseFile($request->file('file'));
            $invoice = $this->taxInvoiceService->importInvoice($vendor, $parsed, $warehouse, Auth::user());

            return redirect()->route('admin.inventory.invoices.show', $invoice)
                ->with('success', __('Armenian Tax Invoice #:number from :supplier successfully imported with :count products. Stock credited to :warehouse.', [
                    'number' => $invoice->invoice_number,
                    'supplier' => $invoice->supplier->name,
                    'count' => $invoice->items->count(),
                    'warehouse' => $warehouse->name,
                ]));
        } catch (\Throwable $e) {
            return back()->with('error', __('Failed to import tax invoice: :msg', ['msg' => $e->getMessage()]));
        }
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

        $currency = Auth::user()?->vendor?->currency ?? 'AMD';

        return back()->with('success', __('Payment #:number of :amount :currency recorded successfully.', [
            'number' => $payment->payment_number,
            'amount' => number_format($payment->amount, 2),
            'currency' => $currency,
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
