@extends('layouts.app')

@section('title', __('Create Purchase Order') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="max-width: 1050px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <a href="{{ route('admin.inventory.procurement.index') }}" style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--text-muted); font-size: 0.85rem; font-weight: 600; text-decoration: none; margin-bottom: 0.75rem;">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Purchase Orders') }}
        </a>
        <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
            <span style="background: rgba(99, 102, 241, 0.15); color: #6366f1; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                <i class="fa-solid fa-cart-plus"></i>
            </span>
            <span>{{ __('Create Purchase Order') }}</span>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0;">
            {{ __('Order ingredients from suppliers to replenish warehouse stock levels.') }}
        </p>
    </div>

    @if ($errors->any())
        <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; color: #ef4444;">
            <div style="font-weight: 700; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                <i class="fa-solid fa-triangle-exclamation"></i> {{ __('Please correct the following errors:') }}
            </div>
            <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.88rem;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Low Stock Suggestions Banner (if any) -->
    @if($suggestedItems->isNotEmpty())
        <div class="card" style="background: rgba(245, 158, 11, 0.06); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 16px; padding: 1.25rem; margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem;">
                <div style="font-weight: 700; color: #d97706; display: flex; align-items: center; gap: 0.5rem; font-size: 0.95rem;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span>{{ __('Low-Stock Replenishment Recommendations (:count items)', ['count' => $suggestedItems->count()]) }}</span>
                </div>
            </div>
            <p style="font-size: 0.82rem; color: var(--text-muted); margin: 0 0 0.75rem;">
                {{ __('These items are currently at or below minimum threshold. Click "+ Add to PO" to insert them directly into your order.') }}
            </p>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                @foreach($suggestedItems as $sug)
                    <button type="button"
                            onclick="window.addSuggestedItem({{ $sug['ingredient']->id }}, '{{ addslashes($sug['ingredient']->name) }}', {{ $sug['suggested_qty'] }}, {{ $sug['unit_id'] }}, {{ $sug['unit_price'] ?? 0 }}, {{ $sug['supplier']?->id ?? 'null' }});"
                            class="btn btn-secondary btn-sm"
                            style="border-radius: 8px; font-size: 0.78rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; background: var(--bg-card);">
                        <i class="fa-solid fa-plus" style="color: #10b981;"></i>
                        <span>{{ $sug['ingredient']->name }}</span>
                        <span style="font-family: monospace; color: var(--text-muted);">({{ (float) $sug['suggested_qty'] }} {{ $sug['unit_code'] }})</span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.inventory.procurement.store') }}" x-data="poForm()" x-cloak>
        @csrf

        <!-- 1. Header Information -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
            <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin: 0 0 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-info-circle" style="color: #6366f1;"></i>
                <span>{{ __('Order Header & Logistics') }}</span>
            </h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 1.25rem;">
                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Supplier') }} <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="supplier_id" x-model="selectedSupplierId" required class="form-select" style="width: 100%; border-radius: 10px;">
                        <option value="">{{ __('Select Supplier...') }}</option>
                        @foreach($suppliers as $sup)
                            <option value="{{ $sup->id }}" {{ old('supplier_id', request('supplier_id')) == $sup->id ? 'selected' : '' }}>
                                {{ $sup->name }} ({{ ucfirst(str_replace('_', ' ', $sup->payment_terms)) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Delivery Target Warehouse') }} <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="warehouse_id" required class="form-select" style="width: 100%; border-radius: 10px;">
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ old('warehouse_id', $warehouses->firstWhere('is_default', true)?->id) == $wh->id ? 'selected' : '' }}>
                                {{ $wh->name }} {{ $wh->is_default ? '(' . __('Default') . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Order Date') }} <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="date" name="order_date" value="{{ old('order_date', Auth::user()->vendor->getLocalTime()->format('Y-m-d')) }}" required class="form-input" style="width: 100%; border-radius: 10px;">
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Expected Delivery Date') }}
                    </label>
                    <input type="date" name="expected_delivery_date" value="{{ old('expected_delivery_date') }}" class="form-input" style="width: 100%; border-radius: 10px;">
                </div>
            </div>

            <div>
                <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                    {{ __('Order Notes / Instructions for Supplier') }}
                </label>
                <textarea name="notes" rows="2" class="form-input" style="width: 100%; border-radius: 10px;" placeholder="{{ __('Please deliver before 11:00 AM. Require certificate of quality...') }}">{{ old('notes') }}</textarea>
            </div>
        </div>

        <!-- 2. Purchase Order Items -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
                <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-list" style="color: #10b981;"></i>
                    <span>{{ __('Order Items') }}</span>
                </h2>
                <button type="button" @click="addItem()" class="btn btn-secondary btn-sm" style="border-radius: 8px; font-weight: 700;">
                    <i class="fa-solid fa-plus"></i> {{ __('Add Item') }}
                </button>
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-color); background: rgba(0,0,0,0.02);">
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-main);">{{ __('Ingredient') }}</th>
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); width: 130px;">{{ __('Quantity') }}</th>
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); width: 110px;">{{ __('Unit') }}</th>
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); width: 130px; text-align: right;">{{ __('Price') }} ({{ Auth::user()->vendor->currency }})</th>
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); width: 95px; text-align: right;">{{ __('Tax %') }}</th>
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-main); width: 130px; text-align: right;">{{ __('Total') }} ({{ Auth::user()->vendor->currency }})</th>
                            <th style="padding: 0.65rem 0.5rem; width: 45px; text-align: center;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(row, index) in items" :key="index">
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 0.6rem 0.5rem;">
                                    <select :name="`items[${index}][ingredient_id]`" x-model="row.ingredient_id" @change="onIngredientSelected(row)" class="form-select" required style="font-size: 0.8rem; border-radius: 6px; width: 100%;">
                                        <option value="">{{ __('Select Ingredient...') }}</option>
                                        @foreach($ingredients as $ing)
                                            <option value="{{ $ing->id }}" data-unit="{{ $ing->unit_id }}" data-cost="{{ $ing->current_cost > 0 ? $ing->current_cost : $ing->last_purchase_price }}">
                                                {{ $ing->name }} ({{ $ing->unit?->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                </td>

                                <td style="padding: 0.6rem 0.5rem;">
                                    <input type="number" step="0.0001" :name="`items[${index}][quantity]`" x-model.number="row.quantity" required class="form-input" style="font-size: 0.8rem; border-radius: 6px; width: 100%;">
                                </td>

                                <td style="padding: 0.6rem 0.5rem;">
                                    <select :name="`items[${index}][unit_id]`" x-model="row.unit_id" class="form-select" required style="font-size: 0.8rem; border-radius: 6px; width: 100%;">
                                        @foreach($units as $unit)
                                            <option value="{{ $unit->id }}">{{ $unit->code }}</option>
                                        @endforeach
                                    </select>
                                </td>

                                <td style="padding: 0.6rem 0.5rem;">
                                    <input type="number" step="0.01" :name="`items[${index}][unit_price]`" x-model.number="row.unit_price" required class="form-input" style="font-size: 0.8rem; border-radius: 6px; width: 100%; text-align: right;">
                                </td>

                                <td style="padding: 0.6rem 0.5rem;">
                                    <input type="number" step="0.01" :name="`items[${index}][tax_percent]`" x-model.number="row.tax_percent" class="form-input" style="font-size: 0.8rem; border-radius: 6px; width: 100%; text-align: right;">
                                </td>

                                <td style="padding: 0.6rem 0.5rem; text-align: right; font-family: monospace; font-weight: 700; color: var(--text-main);">
                                    $<span x-text="calculateLineTotal(row)"></span>
                                </td>

                                <td style="padding: 0.6rem 0.5rem; text-align: center;">
                                    <button type="button" @click="removeItem(index)" class="btn btn-secondary btn-sm" style="color: #ef4444; border-radius: 6px;">
                                        <i class="fa-solid fa-times"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot>
                        <tr style="border-top: 2px solid var(--border-color); background: rgba(0,0,0,0.03); font-weight: 800;">
                            <td colspan="5" style="padding: 0.75rem 0.5rem; text-align: right;">{{ __('Estimated Total Amount:') }}</td>
                            <td style="padding: 0.75rem 0.5rem; text-align: right; font-family: monospace; color: #10b981; font-size: 1rem;">
                                $<span x-text="calculateGrandTotal()"></span>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Submit Bar -->
        <div style="display: flex; justify-content: flex-end; gap: 1rem; align-items: center; margin-bottom: 3rem;">
            <a href="{{ route('admin.inventory.procurement.index') }}" class="btn btn-secondary" style="border-radius: 12px; font-weight: 600;">
                {{ __('Cancel') }}
            </a>
            <button type="submit" class="btn btn-primary" style="border-radius: 12px; font-weight: 700; padding: 0.75rem 2rem;">
                <i class="fa-solid fa-check"></i> {{ __('Save Purchase Order') }}
            </button>
        </div>
    </form>
</div>

<script>
let globalPoForm = null;

function poForm() {
    return {
        selectedSupplierId: '{{ old('supplier_id', request('supplier_id')) }}',
        items: [
            {
                ingredient_id: '',
                quantity: 1.0,
                unit_id: '{{ $units->firstWhere('code', 'kg')?->id ?? $units->first()?->id }}',
                unit_price: 0.0,
                tax_percent: 0.0
            }
        ],

        init() {
            globalPoForm = this;
        },

        addItem() {
            this.items.push({
                ingredient_id: '',
                quantity: 1.0,
                unit_id: '{{ $units->firstWhere('code', 'kg')?->id ?? $units->first()?->id }}',
                unit_price: 0.0,
                tax_percent: 0.0
            });
        },

        removeItem(index) {
            this.items.splice(index, 1);
        },

        onIngredientSelected(row) {
            const selectEl = event.target;
            const selectedOpt = selectEl.options[selectEl.selectedIndex];
            if (selectedOpt) {
                if (selectedOpt.dataset.unit) {
                    row.unit_id = selectedOpt.dataset.unit;
                }
                if (selectedOpt.dataset.cost) {
                    row.unit_price = parseFloat(selectedOpt.dataset.cost) || 0.0;
                }
            }
        },

        calculateLineTotal(row) {
            const qty = parseFloat(row.quantity) || 0;
            const price = parseFloat(row.unit_price) || 0;
            const tax = parseFloat(row.tax_percent) || 0;
            const base = qty * price;
            return (base + (base * (tax / 100))).toFixed(2);
        },

        calculateGrandTotal() {
            let total = 0;
            for (const row of this.items) {
                const qty = parseFloat(row.quantity) || 0;
                const price = parseFloat(row.unit_price) || 0;
                const tax = parseFloat(row.tax_percent) || 0;
                const base = qty * price;
                total += (base + (base * (tax / 100)));
            }
            return total.toFixed(2);
        }
    };
}

window.addSuggestedItem = function(ingredientId, name, qty, unitId, price, supplierId) {
    if (!globalPoForm) return;

    if (supplierId && !globalPoForm.selectedSupplierId) {
        globalPoForm.selectedSupplierId = supplierId.toString();
    }

    // Replace first empty item if present
    if (globalPoForm.items.length === 1 && !globalPoForm.items[0].ingredient_id) {
        globalPoForm.items[0] = {
            ingredient_id: ingredientId.toString(),
            quantity: qty,
            unit_id: unitId.toString(),
            unit_price: price,
            tax_percent: 0.0
        };
    } else {
        globalPoForm.items.push({
            ingredient_id: ingredientId.toString(),
            quantity: qty,
            unit_id: unitId.toString(),
            unit_price: price,
            tax_percent: 0.0
        });
    }
};
</script>
@endsection

