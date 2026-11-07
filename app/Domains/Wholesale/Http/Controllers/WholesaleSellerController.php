<?php

namespace App\Domains\Wholesale\Http\Controllers;

use App\Domains\Wholesale\Enums\SellerTaxRegime;
use App\Domains\Wholesale\Models\WholesaleSeller;
use App\Domains\Wholesale\Services\WholesaleSellerService;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Warehouse;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WholesaleSellerController extends Controller
{
    public function __construct(
        protected WholesaleSellerService $sellerService,
        protected TenantContext $tenantContext
    ) {}

    /**
     * Display listing of seller companies for the current tenant.
     */
    public function index(Request $request): View
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;
        $sellers = $this->sellerService->listSellers($vendor);

        return view('wholesale.sellers.index', compact('vendor', 'sellers'));
    }

    /**
     * Show form to create a new seller company.
     */
    public function create(Request $request): View
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;
        $warehouses = Warehouse::where('vendor_id', $vendor->id)->where('is_active', true)->get();
        $coreBankAccounts = BankAccount::where('vendor_id', $vendor->id)->where('is_active', true)->get();
        $regimes = SellerTaxRegime::cases();

        return view('wholesale.sellers.create', compact('vendor', 'warehouses', 'coreBankAccounts', 'regimes'));
    }

    /**
     * Store newly created seller company.
     */
    public function store(Request $request): RedirectResponse
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'legal_name' => ['required', 'string', 'max:255'],
            'tin' => [
                'required',
                'regex:/^\d{8}$/',
                Rule::unique('wholesale_sellers', 'tin')->where('vendor_id', $vendor->id),
            ],
            'vat_number' => ['nullable', 'string', 'max:30'],
            'tax_regime' => ['required', Rule::enum(SellerTaxRegime::class)],
            'address' => ['required', 'string'],
            'default_supply_location' => ['nullable', 'string'],
            'pek_username' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:120'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'iban' => ['nullable', 'string', 'max:50'],
            'currency' => ['nullable', 'string', 'max:10'],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('vendor_id', $vendor->id)],
        ], [
            'tin.regex' => __('Armenian Tax ID (TIN / ՀՎՀՀ) must be exactly 8 digits.'),
        ]);

        $seller = $this->sellerService->createSeller($vendor, $validated);

        return redirect()->route('admin.wholesale.sellers.show', $seller)
            ->with('success', __('Seller company ":name" has been created successfully.', ['name' => $seller->name]));
    }

    /**
     * View detailed seller profile.
     */
    public function show(WholesaleSeller $seller): View
    {
        $seller->load(['taxProfiles', 'locations.warehouse', 'bankAccounts.coreBankAccount']);
        $vendor = $this->tenantContext->getTenant();
        $warehouses = Warehouse::where('vendor_id', $vendor->id)->where('is_active', true)->get();
        $coreBankAccounts = BankAccount::where('vendor_id', $vendor->id)->where('is_active', true)->get();
        $regimes = SellerTaxRegime::cases();

        return view('wholesale.sellers.show', compact('seller', 'vendor', 'warehouses', 'coreBankAccounts', 'regimes'));
    }

    /**
     * Show form to edit seller company.
     */
    public function edit(WholesaleSeller $seller): View
    {
        $vendor = $this->tenantContext->getTenant();
        $warehouses = Warehouse::where('vendor_id', $vendor->id)->where('is_active', true)->get();
        $regimes = SellerTaxRegime::cases();

        return view('wholesale.sellers.edit', compact('seller', 'vendor', 'warehouses', 'regimes'));
    }

    /**
     * Update seller company.
     */
    public function update(Request $request, WholesaleSeller $seller): RedirectResponse
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'legal_name' => ['required', 'string', 'max:255'],
            'tin' => [
                'required',
                'regex:/^\d{8}$/',
                Rule::unique('wholesale_sellers', 'tin')->where('vendor_id', $vendor->id)->ignore($seller->id),
            ],
            'vat_number' => ['nullable', 'string', 'max:30'],
            'tax_regime' => ['required', Rule::enum(SellerTaxRegime::class)],
            'address' => ['required', 'string'],
            'default_supply_location' => ['nullable', 'string'],
            'pek_username' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:120'],
            'is_active' => ['boolean'],
        ]);

        $this->sellerService->updateSeller($seller, $validated);

        return redirect()->route('admin.wholesale.sellers.show', $seller)
            ->with('success', __('Seller company updated successfully.'));
    }

    /**
     * Add a bank account to the seller.
     */
    public function storeBankAccount(Request $request, WholesaleSeller $seller): RedirectResponse
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;

        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:150'],
            'account_number' => ['required', 'string', 'max:50'],
            'iban' => ['nullable', 'string', 'max:50'],
            'currency' => ['required', 'string', 'max:10'],
            'bank_account_id' => ['nullable', 'integer', Rule::exists('bank_accounts', 'id')->where('vendor_id', $vendor->id)],
            'is_default' => ['boolean'],
        ]);

        $this->sellerService->addBankAccount($seller, $validated);

        return redirect()->back()->with('success', __('Bank account added successfully.'));
    }

    /**
     * Add a supply location to the seller.
     */
    public function storeLocation(Request $request, WholesaleSeller $seller): RedirectResponse
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('vendor_id', $vendor->id)],
            'is_default' => ['boolean'],
        ]);

        $this->sellerService->addLocation($seller, $validated);

        return redirect()->back()->with('success', __('Supply location added successfully.'));
    }
}

