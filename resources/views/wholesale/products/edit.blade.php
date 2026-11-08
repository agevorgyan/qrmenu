@extends('layouts.app')

@section('title', __('Edit Wholesale Product: :name', ['name' => $product->name]))

@section('content')
<div class="content-wrapper" style="max-width: 900px; margin: 0 auto;">
    <!-- Header -->
    <div style="margin-bottom: 2rem;">
        <a href="{{ route('admin.wholesale.products.show', $product) }}" style="color: #6366f1; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;">
            <i class="fa-solid fa-arrow-left"></i>
            <span>{{ __('Back to Product Details') }}</span>
        </a>
        <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
            {{ __('Edit Wholesale Product') }}
        </h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
            {{ __('Update SKU, ATGAA classification, pricing constraints, and physical stock link.') }}
        </p>
    </div>

    @if($errors->any())
        <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 12px; padding: 1rem 1.25rem; color: #ef4444; margin-bottom: 1.5rem;">
            <div style="font-weight: 700; margin-bottom: 0.5rem;">{{ __('Please correct the following errors:') }}</div>
            <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.88rem;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.wholesale.products.update', $product) }}" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
        @csrf
        @method('PUT')

        <!-- Section 1: Identification -->
        <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0 0 1.25rem 0; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
            1. {{ __('Product Identification & Nomenclature') }}
        </h2>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Wholesale Product Name') }} <span style="color: #ef4444;">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name', $product->name) }}" required
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('SKU') }} <span style="color: #ef4444;">*</span>
                </label>
                <input type="text" name="sku" value="{{ old('sku', $product->sku) }}" required
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem; font-family: monospace; text-transform: uppercase;">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Barcode (EAN-13 / UPC)') }}
                </label>
                <input type="text" name="barcode" value="{{ old('barcode', $product->barcode) }}"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem; font-family: monospace;">
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Category') }}
                </label>
                <select name="category_id" style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
                    <option value="">{{ __('Uncategorized') }}</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ old('category_id', $product->category_id) == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('ATGAA / FEACN Classification') }} <span style="color: #ef4444;">*</span>
                </label>
                <select name="classifier_code" required style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
                    @foreach($atgaaCodes as $atgaa)
                        <option value="{{ $atgaa->code }}" {{ old('classifier_code', $product->classifier_code) === $atgaa->code ? 'selected' : '' }}>
                            {{ $atgaa->code }} — {{ $atgaa->name_hy }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- Section 2: Pricing & Taxation -->
        <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 2rem 0 1.25rem 0; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
            2. {{ __('Wholesale Pricing & Taxes') }}
        </h2>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Base Wholesale Price') }} <span style="color: #ef4444;">*</span>
                </label>
                <input type="number" step="0.01" name="base_wholesale_price" value="{{ old('base_wholesale_price', $product->base_wholesale_price) }}" required
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem; font-weight: 700;">
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Minimum Price Floor') }}
                </label>
                <input type="number" step="0.01" name="min_wholesale_price" value="{{ old('min_wholesale_price', $product->min_wholesale_price) }}"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
                <span style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.25rem; display: block;">
                    {{ __('Hard floor: discounts cannot lower price below this.') }}
                </span>
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Estimated Cost Price (COGS)') }}
                </label>
                <input type="number" step="0.01" name="cost_price" value="{{ old('cost_price', $product->cost_price) }}"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
            <div>
                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.88rem; font-weight: 700; color: var(--text-main); cursor: pointer; margin-top: 1.8rem;">
                    <input type="checkbox" name="is_vat_applicable" value="1" {{ old('is_vat_applicable', $product->is_vat_applicable ? '1' : '0') == '1' ? 'checked' : '' }}>
                    <span>{{ __('Subject to Value Added Tax (20% VAT)') }}</span>
                </label>
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Applicable VAT Rate (%)') }}
                </label>
                <input type="number" step="0.01" name="vat_rate" value="{{ old('vat_rate', $product->vat_rate) }}"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
            </div>
        </div>

        <!-- Section 3: Units & Packaging -->
        <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 2rem 0 1.25rem 0; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
            3. {{ __('Units of Measure & Ordering Rules') }}
        </h2>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Base Unit of Measure') }} <span style="color: #ef4444;">*</span>
                </label>
                <select name="unit_id" required style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}" {{ old('unit_id', $product->unit_id) == $unit->id ? 'selected' : '' }}>
                            {{ $unit->name }} ({{ $unit->code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Minimum Order Qty (MOQ)') }}
                </label>
                <input type="number" step="0.0001" name="min_order_quantity" value="{{ old('min_order_quantity', $product->min_order_quantity) }}"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Order Quantity Multiple') }}
                </label>
                <input type="number" step="0.0001" name="order_quantity_multiple" value="{{ old('order_quantity_multiple', $product->order_quantity_multiple) }}"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
            </div>
        </div>

        <!-- Section 4: Physical Stock Mapping -->
        <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 2rem 0 1.25rem 0; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
            4. {{ __('Explicit Core Physical Stock Mapping (Optional)') }}
        </h2>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Default Fulfillment Warehouse') }}
                </label>
                <select name="default_warehouse_id" style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ old('default_warehouse_id', $product->default_warehouse_id) == $wh->id ? 'selected' : '' }}>
                            {{ $wh->name }} ({{ $wh->code ?: 'WH-'.$wh->id }}) {{ $wh->is_default ? '[' . __('Default') . ']' : '' }}
                        </option>
                    @endforeach
                </select>
                <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                    {{ __('Warehouse from which deliveries will be dispatched by default.') }}
                </span>
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Link to Core Inventory Ingredient / Stock Item') }}
                </label>
                <select name="physical_stock_ingredient_id" style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
                    <option value="">{{ __('No Physical Stock Link (Virtual Wholesale Item)') }}</option>
                    @foreach($physicalIngredients as $ing)
                        <option value="{{ $ing->id }}" {{ old('physical_stock_ingredient_id', $product->physical_stock_ingredient_id) == $ing->id ? 'selected' : '' }}>
                            {{ $ing->name }} [SKU: {{ $ing->sku ?? 'N/A' }}] ({{ $ing->unit?->name ?? 'unit' }}) &bull; {{ __('Stock:') }} {{ number_format($ing->total_stock_on_hand, 2) }}
                        </option>
                    @endforeach
                </select>
                <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                    {{ __('When linked, wholesale order deliveries will atomically deplete available balance from the designated warehouse in the core inventory ledger.') }}
                </span>
            </div>
        </div>

        @if($physicalIngredients->isEmpty())
            <div style="margin-bottom: 1.5rem; padding: 1rem 1.25rem; border-radius: 12px; background: rgba(99, 102, 241, 0.08); border: 1px dashed #6366f1; display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                <div style="font-size: 0.85rem; color: var(--text-main);">
                    <i class="fa-solid fa-boxes-stacked" style="color: #6366f1; margin-right: 0.5rem; font-size: 1.1rem;"></i>
                    <strong>{{ __('No items in core inventory yet.') }}</strong>
                    <div style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.25rem;">
                        {{ __('To enable automatic physical stock deductions upon delivery, add items or raw materials to your inventory catalog.') }}
                    </div>
                </div>
                <a href="{{ route('admin.inventory.ingredients.create') }}" target="_blank"
                   style="padding: 0.5rem 1rem; border-radius: 8px; background: #6366f1; color: #fff; font-size: 0.82rem; font-weight: 700; text-decoration: none; white-space: nowrap; display: inline-flex; align-items: center; gap: 0.4rem; box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);">
                    <i class="fa-solid fa-plus"></i> {{ __('Add Stock Item') }}
                </a>
            </div>
        @endif

        <!-- Section 5: Technological Card / Recipe (Production & BOM) -->
        <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 2rem 0 1.25rem 0; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
            5. {{ __('Տեխնոլոգիական Քարտ (Արտադրություն և Բաղադրատոմս)') }}
        </h2>

        <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem;">
            <div style="display: grid; grid-template-columns: 1fr auto; gap: 1rem; align-items: flex-end;">
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                        {{ __('Կապել Տեխնոլոգիական Քարտի (Recipe / BOM) հետ') }}
                    </label>
                    <select name="recipe_id" style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); font-size: 0.9rem;">
                        <option value="">{{ __('Առանց Տեխնոլոգիական Քարտի (Պատրաստի գնված / Վերավաճառվող ապրանք)') }}</option>
                        @foreach($recipes as $rec)
                            <option value="{{ $rec->id }}" {{ old('recipe_id', $product->recipe_id) == $rec->id ? 'selected' : '' }}>
                                {{ $rec->name }} [{{ $rec->code }}] &bull; {{ __('Ելք:') }} {{ (float)$rec->yield_quantity }} {{ $rec->yieldUnit?->name ?? '' }} 
                                @if($rec->latestCost)
                                    &bull; {{ __('Ինքնարժեք:') }} {{ number_format($rec->latestCost->cost_per_portion ?: $rec->latestCost->total_cost, 2) }} AMD
                                @endif
                            </option>
                        @endforeach
                    </select>
                    <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                        {{ __('Տեխնոլոգիական քարտը կապակցում է մեծածախ ապրանքը հումքերի ծախսի, արտադրության և ինքնարժեքի հետ:') }}
                    </span>
                </div>
                <div>
                    <a href="{{ route('admin.inventory.recipes.create') }}" target="_blank"
                       style="padding: 0.65rem 1rem; border-radius: 10px; background: #6366f1; color: #fff; font-size: 0.82rem; font-weight: 700; text-decoration: none; white-space: nowrap; display: inline-flex; align-items: center; gap: 0.4rem; box-shadow: 0 2px 8px rgba(99, 102, 241, 0.25);">
                        <i class="fa-solid fa-plus"></i> {{ __('Նոր Տեխ․ Քարտ') }}
                    </a>
                </div>
            </div>
        </div>

        <!-- Section 6: Status -->
        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; font-weight: 700; color: var(--text-main); cursor: pointer;">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $product->is_active ? '1' : '0') == '1' ? 'checked' : '' }}>
                <span>{{ __('Product is Active in Wholesale POS & Orders') }}</span>
            </label>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
            <a href="{{ route('admin.wholesale.products.show', $product) }}" 
               style="padding: 0.65rem 1.25rem; border-radius: 10px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-weight: 600; text-decoration: none;">
                {{ __('Cancel') }}
            </a>
            <button type="submit" 
                    style="padding: 0.65rem 1.5rem; border-radius: 10px; background: #6366f1; border: none; color: #fff; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);">
                <i class="fa-solid fa-check"></i>
                <span>{{ __('Update Product') }}</span>
            </button>
        </div>
    </form>
</div>
@endsection

