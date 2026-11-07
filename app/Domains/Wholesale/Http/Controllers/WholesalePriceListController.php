<?php

namespace App\Domains\Wholesale\Http\Controllers;

use App\Domains\Wholesale\Models\WholesalePriceList;
use App\Domains\Wholesale\Models\WholesaleProduct;
use App\Domains\Wholesale\Services\WholesaleCustomerService;
use App\Http\Controllers\Controller;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WholesalePriceListController extends Controller
{
    public function __construct(
        protected WholesaleCustomerService $customerService,
        protected TenantContext $tenantContext
    ) {}

    /**
     * Display a listing of price lists.
     */
    public function index(Request $request): View
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;
        $priceLists = WholesalePriceList::where('vendor_id', $vendor->id)
            ->withCount('items')
            ->orderBy('name')
            ->paginate(15);

        return view('wholesale.pricelists.index', compact('vendor', 'priceLists'));
    }

    /**
     * Show the form for creating a new price list.
     */
    public function create(): View
    {
        return view('wholesale.pricelists.create');
    }

    /**
     * Store a newly created price list.
     */
    public function store(Request $request): RedirectResponse
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('wholesale_price_lists', 'code')->where('vendor_id', $vendor->id),
            ],
            'currency' => ['required', 'string', 'size:3'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'description' => ['nullable', 'string'],
        ]);

        $priceList = $this->customerService->createPriceList($vendor, $validated);

        return redirect()->route('admin.wholesale.pricelists.show', $priceList)
            ->with('success', __('Price list ":name" created.', ['name' => $priceList->name]));
    }

    /**
     * Display the specified price list with items.
     */
    public function show(WholesalePriceList $priceList): View
    {
        $priceList->load(['items.product.unit']);
        $vendor = $this->tenantContext->getTenant();
        $availableProducts = WholesaleProduct::where('vendor_id', $vendor->id)->where('is_active', true)->get();

        return view('wholesale.pricelists.show', compact('priceList', 'vendor', 'availableProducts'));
    }

    /**
     * Add or update an item in the price list.
     */
    public function storeItem(Request $request, WholesalePriceList $priceList): RedirectResponse
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;

        $validated = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('wholesale_products', 'id')->where('vendor_id', $vendor->id)],
            'price' => ['required', 'numeric', 'min:0'],
            'min_quantity' => ['nullable', 'numeric', 'min:0.0001'],
        ]);

        $product = WholesaleProduct::findOrFail($validated['product_id']);
        $this->customerService->setPriceListItem($priceList, $product, (float) $validated['price'], (float) ($validated['min_quantity'] ?? 1.0));

        return redirect()->back()->with('success', __('Price list item saved successfully.'));
    }
}

