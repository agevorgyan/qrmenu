<?php

namespace App\Domains\Wholesale\Http\Controllers;

use App\Domains\Wholesale\Models\WholesaleCustomer;
use App\Domains\Wholesale\Models\WholesalePriceList;
use App\Domains\Wholesale\Models\WholesaleProduct;
use App\Domains\Wholesale\Services\WholesaleCustomerService;
use App\Http\Controllers\Controller;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WholesaleCustomerController extends Controller
{
    public function __construct(
        protected WholesaleCustomerService $customerService,
        protected TenantContext $tenantContext
    ) {}

    /**
     * Display a listing of B2B customers.
     */
    public function index(Request $request): View
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;
        $filters = $request->only(['search', 'tax_regime', 'is_active']);

        $customers = $this->customerService->listCustomers($vendor, $filters, 15);

        return view('wholesale.customers.index', compact('vendor', 'customers', 'filters'));
    }

    /**
     * Show the form for creating a new customer.
     */
    public function create(Request $request): View
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;

        return view('wholesale.customers.create', compact('vendor'));
    }

    /**
     * Store a newly created B2B customer.
     */
    public function store(Request $request): RedirectResponse
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tin' => ['nullable', 'string', 'regex:/^[0-9]{8}$/'],
            'tax_regime' => ['required', 'string', 'in:vat_payer,turnover_tax,micro_business,individual'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'payment_term_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'is_credit_allowed' => ['boolean'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address_name' => ['nullable', 'string', 'max:100'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
        ], [
            'tin.regex' => __('Armenian Taxpayer Identification Number (TIN / ՀՎՀՀ) must be exactly 8 numeric digits.'),
        ]);

        $customer = $this->customerService->createCustomer($vendor, $validated);

        return redirect()->route('admin.wholesale.customers.show', $customer)
            ->with('success', __('B2B Customer ":name" created successfully.', ['name' => $customer->name]));
    }

    /**
     * Display the specified customer.
     */
    public function show(WholesaleCustomer $customer): View
    {
        $customer->load(['addresses', 'contacts', 'pricingAgreements.priceList', 'pricingAgreements.product']);
        $vendor = $this->tenantContext->getTenant();
        $availablePriceLists = WholesalePriceList::where('vendor_id', $vendor->id)->where('is_active', true)->get();
        $availableProducts = WholesaleProduct::where('vendor_id', $vendor->id)->where('is_active', true)->get();

        return view('wholesale.customers.show', compact('customer', 'vendor', 'availablePriceLists', 'availableProducts'));
    }

    /**
     * Show the form for editing the customer.
     */
    public function edit(WholesaleCustomer $customer): View
    {
        $vendor = $this->tenantContext->getTenant();

        return view('wholesale.customers.edit', compact('customer', 'vendor'));
    }

    /**
     * Update the customer in storage.
     */
    public function update(Request $request, WholesaleCustomer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tin' => ['nullable', 'string', 'regex:/^[0-9]{8}$/'],
            'tax_regime' => ['required', 'string', 'in:vat_payer,turnover_tax,micro_business,individual'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'payment_term_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'is_credit_allowed' => ['boolean'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ], [
            'tin.regex' => __('Armenian Taxpayer Identification Number (TIN / ՀՎՀՀ) must be exactly 8 numeric digits.'),
        ]);

        $this->customerService->updateCustomer($customer, $validated);

        return redirect()->route('admin.wholesale.customers.show', $customer)
            ->with('success', __('Customer updated successfully.'));
    }

    /**
     * Add a delivery address for the customer.
     */
    public function storeAddress(Request $request, WholesaleCustomer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'contact_name' => ['nullable', 'string', 'max:100'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'is_default' => ['boolean'],
        ]);

        $this->customerService->addAddress($customer, $validated);

        return redirect()->back()->with('success', __('Delivery address added successfully.'));
    }

    /**
     * Assign a price list or contract price to customer.
     */
    public function storePricing(Request $request, WholesaleCustomer $customer): RedirectResponse
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;

        $validated = $request->validate([
            'price_list_id' => ['nullable', 'integer', Rule::exists('wholesale_price_lists', 'id')->where('vendor_id', $vendor->id)],
            'product_id' => ['nullable', 'integer', Rule::exists('wholesale_products', 'id')->where('vendor_id', $vendor->id)],
            'contract_price' => ['nullable', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ]);

        $this->customerService->setCustomerPricing($customer, $validated);

        return redirect()->back()->with('success', __('Pricing agreement assigned to customer.'));
    }
}
