@extends('layouts.app')

@section('title', __('Create Purchase Order') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="max-width: 1100px; margin: 0 auto;" x-data="poForm()" x-cloak>
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
            {{ __('Order ingredients and supplies from vendors to replenish inventory.') }}
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
                            @click="addSuggestedItem({{ $sug['ingredient']->id }}, '{{ addslashes($sug['ingredient']->name) }}', {{ $sug['suggested_qty'] }}, {{ $sug['unit_id'] }}, {{ $sug['unit_price'] ?? 0 }}, {{ $sug['supplier']?->id ?? 'null' }})"
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

    <form method="POST" action="{{ route('admin.inventory.procurement.store') }}" @submit="return validateBeforeSubmit($event)">
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
                    <select name="supplier_id" x-model="selectedSupplierId" @change="onSupplierChanged()" required class="form-select" style="width: 100%; border-radius: 10px;">
                        <option value="">{{ __('Select Supplier...') }}</option>
                        @foreach($suppliers as $sup)
                            <option value="{{ $sup->id }}" {{ old('supplier_id', request('supplier_id')) == $sup->id ? 'selected' : '' }}>
                                {{ $sup->name }}
                                @if((float) $sup->discount_percent > 0)
                                    ({{ __('Discount: :percent%', ['percent' => (float)$sup->discount_percent]) }})
                                @endif
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

        <!-- 2. Supplier Catalog Offerings (Auto-suggested when supplier is selected) -->
        <div class="card" x-show="selectedSupplierId && currentSupplierOfferings.length > 0" style="background: rgba(99, 102, 241, 0.04); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 16px; padding: 1.25rem; margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem;">
                <div style="font-weight: 700; color: #6366f1; display: flex; align-items: center; gap: 0.5rem; font-size: 0.95rem;">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    <span>{{ __('Products Offered by Selected Supplier') }} (<span x-text="currentSupplierOfferings.length"></span>)</span>
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted);">
                    {{ __('Click on any product to quickly add it with pre-filled price and unit') }}
                </div>
            </div>

            <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
                <template x-for="offering in currentSupplierOfferings" :key="offering.ingredient_id">
                    <button type="button"
                            @click="addSupplierOffering(offering)"
                            class="btn btn-secondary btn-sm"
                            style="border-radius: 10px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; background: var(--bg-card); border-color: rgba(99, 102, 241, 0.3); padding: 0.45rem 0.75rem;">
                        <i class="fa-solid fa-plus" style="color: #6366f1;"></i>
                        <span style="color: var(--text-main);" x-text="offering.name"></span>
                        <span style="font-family: monospace; font-weight: 700; color: #10b981;">
                            <span x-text="formatNumber(offering.price)"></span> {{ Auth::user()->vendor->currency }}
                            <span style="font-weight: normal; color: var(--text-muted); font-size: 0.75rem;">/<span x-text="offering.unit_code"></span></span>
                        </span>
                        <template x-if="offering.discount_percent > 0">
                            <span style="background: rgba(239, 68, 68, 0.12); color: #ef4444; font-size: 0.72rem; padding: 0.1rem 0.35rem; border-radius: 4px; font-weight: 700;">
                                -<span x-text="offering.discount_percent"></span>%
                            </span>
                        </template>
                    </button>
                </template>
            </div>
        </div>

        <!-- 3. Purchase Order Items Table -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
                <div>
                    <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-list-check" style="color: #10b981;"></i>
                        <span>{{ __('Order Items') }}</span>
                    </h2>
                    <p style="color: var(--text-muted); font-size: 0.8rem; margin: 0.2rem 0 0;">
                        {{ __('Choose ingredients, set quantities, prices and applicable discounts') }}
                    </p>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="button" @click="openPickerForNewRow()" class="btn btn-secondary btn-sm" style="border-radius: 8px; font-weight: 700; color: #6366f1; border-color: rgba(99, 102, 241, 0.3); background: rgba(99, 102, 241, 0.06);">
                        <i class="fa-solid fa-magnifying-glass"></i> {{ __('Search & Pick Item') }}
                    </button>
                    <button type="button" @click="addItem()" class="btn btn-primary btn-sm" style="border-radius: 8px; font-weight: 700;">
                        <i class="fa-solid fa-plus"></i> {{ __('Add Row') }}
                    </button>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-color); background: rgba(0,0,0,0.02);">
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-main); min-width: 250px;">{{ __('Ingredient / Product') }} <span style="color: #ef4444;">*</span></th>
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); width: 110px;">{{ __('Quantity') }} <span style="color: #ef4444;">*</span></th>
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); width: 100px;">{{ __('Unit') }}</th>
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); width: 120px; text-align: right;">{{ __('Price') }} ({{ Auth::user()->vendor->currency }})</th>
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); width: 85px; text-align: right;">{{ __('Discount %') }}</th>
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); width: 80px; text-align: right;">{{ __('Tax %') }}</th>
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-main); width: 140px; text-align: right;">{{ __('Total') }} ({{ Auth::user()->vendor->currency }})</th>
                            <th style="padding: 0.65rem 0.5rem; width: 45px; text-align: center;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(row, index) in items" :key="index">
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <!-- Ingredient Selection -->
                                <td style="padding: 0.6rem 0.5rem;">
                                    <input type="hidden" :name="`items[${index}][ingredient_id]`" :value="row.ingredient_id">

                                    <template x-if="!row.ingredient_id">
                                        <button type="button"
                                                @click="openPickerForRow(index)"
                                                class="btn btn-secondary w-100"
                                                style="text-align: left; justify-content: space-between; border: 1px dashed rgba(99, 102, 241, 0.4); background: rgba(99, 102, 241, 0.04); color: #6366f1; font-weight: 600; padding: 0.45rem 0.75rem; border-radius: 8px; font-size: 0.82rem;">
                                            <span><i class="fa-solid fa-magnifying-glass" style="margin-right: 0.4rem;"></i> {{ __('Select Ingredient / Product...') }}</span>
                                            <i class="fa-solid fa-chevron-right" style="font-size: 0.7rem; opacity: 0.6;"></i>
                                        </button>
                                    </template>

                                    <template x-if="row.ingredient_id">
                                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.35rem 0.6rem; background: var(--bg-body, rgba(0,0,0,0.02)); border: 1px solid var(--border-color); border-radius: 8px;">
                                            <div style="min-width: 0; flex: 1;">
                                                <div style="font-weight: 700; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="row.ingredient_name"></div>
                                                <div style="font-size: 0.72rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.4rem; margin-top: 0.15rem;">
                                                    <span x-show="row.category_name" style="background: rgba(0,0,0,0.06); padding: 0.1rem 0.3rem; border-radius: 4px;" x-text="row.category_name"></span>
                                                    <template x-if="isSupplierItem(row.ingredient_id)">
                                                        <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; padding: 0.1rem 0.35rem; border-radius: 4px; font-weight: 700;">
                                                            <i class="fa-solid fa-check"></i> {{ __('Supplier Item') }}
                                                        </span>
                                                    </template>
                                                </div>
                                            </div>
                                            <button type="button" @click="openPickerForRow(index)" class="btn btn-secondary btn-sm" title="{{ __('Change Ingredient') }}" style="border-radius: 6px; padding: 0.25rem 0.45rem; font-size: 0.75rem; color: var(--text-muted);">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                        </div>
                                    </template>
                                </td>

                                <!-- Quantity -->
                                <td style="padding: 0.6rem 0.5rem;">
                                    <input type="number" step="0.0001" min="0.0001" :name="`items[${index}][quantity]`" x-model.number="row.quantity" required class="form-input" style="font-size: 0.82rem; border-radius: 6px; width: 100%;">
                                </td>

                                <!-- Unit -->
                                <td style="padding: 0.6rem 0.5rem;">
                                    <select :name="`items[${index}][unit_id]`" x-model="row.unit_id" class="form-select" required style="font-size: 0.82rem; border-radius: 6px; width: 100%;">
                                        @foreach($units as $unit)
                                            <option value="{{ $unit->id }}">{{ $unit->code }}</option>
                                        @endforeach
                                    </select>
                                </td>

                                <!-- Unit Price -->
                                <td style="padding: 0.6rem 0.5rem;">
                                    <input type="number" step="0.01" min="0" :name="`items[${index}][unit_price]`" x-model.number="row.unit_price" required class="form-input" style="font-size: 0.82rem; border-radius: 6px; width: 100%; text-align: right; font-family: monospace;">
                                </td>

                                <!-- Discount % -->
                                <td style="padding: 0.6rem 0.5rem;">
                                    <input type="number" step="0.01" min="0" max="100" placeholder="0" :name="`items[${index}][discount_percent]`" x-model.number="row.discount_percent" class="form-input" style="font-size: 0.82rem; border-radius: 6px; width: 100%; text-align: right;">
                                </td>

                                <!-- Tax % -->
                                <td style="padding: 0.6rem 0.5rem;">
                                    <input type="number" step="0.01" min="0" max="100" placeholder="0" :name="`items[${index}][tax_percent]`" x-model.number="row.tax_percent" class="form-input" style="font-size: 0.82rem; border-radius: 6px; width: 100%; text-align: right;">
                                </td>

                                <!-- Row Total -->
                                <td style="padding: 0.6rem 0.5rem; text-align: right; font-family: monospace; font-weight: 700; color: var(--text-main);">
                                    <span x-text="formatNumber(calculateLineTotal(row))"></span>
                                    <span style="font-size: 0.75rem; font-weight: normal; color: var(--text-muted);">{{ Auth::user()->vendor->currency }}</span>
                                </td>

                                <!-- Remove Row -->
                                <td style="padding: 0.6rem 0.5rem; text-align: center;">
                                    <button type="button" @click="removeItem(index)" class="btn btn-secondary btn-sm" style="color: #ef4444; border-radius: 6px; padding: 0.35rem 0.55rem;" title="{{ __('Remove item') }}">
                                        <i class="fa-solid fa-times"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Order Financial Summary -->
            <div style="border-top: 2px solid var(--border-color); margin-top: 1.25rem; padding-top: 1.25rem; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1.5rem;">
                <div style="min-width: 260px; max-width: 400px;">
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Supplier Order Discount %') }}
                    </label>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="number" step="0.01" min="0" max="100" name="discount_percent" x-model.number="orderDiscountPercent" class="form-input" style="width: 120px; border-radius: 8px; text-align: right;" placeholder="0">
                        <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted);">%</span>
                        <template x-if="orderDiscountPercent > 0">
                            <span style="font-size: 0.8rem; color: #10b981; font-weight: 600;">
                                (-<span x-text="formatNumber(calculateOrderDiscountAmount())"></span> {{ Auth::user()->vendor->currency }})
                            </span>
                        </template>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                        {{ __('Applies to the order after individual item discounts') }}
                    </div>
                </div>

                <!-- Totals Breakdown Box -->
                <div style="min-width: 280px; background: rgba(0,0,0,0.02); border: 1px solid var(--border-color); border-radius: 12px; padding: 1rem 1.25rem;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.4rem; color: var(--text-muted);">
                        <span>{{ __('Subtotal (Items):') }}</span>
                        <span style="font-family: monospace; font-weight: 600;"><span x-text="formatNumber(calculateSubtotal())"></span> {{ Auth::user()->vendor->currency }}</span>
                    </div>

                    <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.4rem; color: #10b981;">
                        <span>{{ __('Total Discounts:') }}</span>
                        <span style="font-family: monospace; font-weight: 700;">-<span x-text="formatNumber(calculateTotalDiscount())"></span> {{ Auth::user()->vendor->currency }}</span>
                    </div>

                    <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.5rem; color: var(--text-muted);">
                        <span>{{ __('Total Tax / VAT:') }}</span>
                        <span style="font-family: monospace; font-weight: 600;"><span x-text="formatNumber(calculateTotalTax())"></span> {{ Auth::user()->vendor->currency }}</span>
                    </div>

                    <div style="border-top: 1px solid var(--border-color); padding-top: 0.5rem; display: flex; justify-content: space-between; font-size: 1.1rem; font-weight: 800; color: var(--text-main);">
                        <span>{{ __('Grand Total:') }}</span>
                        <span style="font-family: monospace; color: #10b981;">
                            <span x-text="formatNumber(calculateGrandTotal())"></span> {{ Auth::user()->vendor->currency }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit & Cancel Bar -->
        <div style="display: flex; justify-content: flex-end; gap: 1rem; align-items: center; margin-bottom: 3rem;">
            <a href="{{ route('admin.inventory.procurement.index') }}" class="btn btn-secondary" style="border-radius: 12px; font-weight: 600;">
                {{ __('Cancel') }}
            </a>
            <button type="submit" class="btn btn-primary" style="border-radius: 12px; font-weight: 700; padding: 0.75rem 2rem;">
                <i class="fa-solid fa-check"></i> {{ __('Save Purchase Order') }}
            </button>
        </div>
    </form>

    <!-- Interactive Ingredient Picker Modal -->
    <dialog id="ingredientPickerModal"
            @click.self="closePickerModal()"
            @keydown.escape.window="closePickerModal()"
            style="border: none; border-radius: 20px; padding: 0; background: transparent; max-width: 680px; width: 95%;">
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; box-shadow: var(--shadow-modal); max-height: 85vh; display: flex; flex-direction: column;">
            <!-- Modal Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-boxes-stacked" style="color: #6366f1;"></i>
                    <span>{{ __('Select Ingredient or Supply') }}</span>
                </h3>
                <button type="button" @click="closePickerModal()" class="btn btn-secondary btn-sm" style="border-radius: 8px; width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>

            <!-- Search Input -->
            <div style="margin-bottom: 1rem; position: relative;">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                <input type="text"
                       x-ref="pickerSearchInput"
                       x-model="pickerSearch"
                       placeholder="{{ __('Search by name, SKU or category...') }}"
                       class="form-input"
                       style="width: 100%; border-radius: 12px; padding: 0.7rem 1rem 0.7rem 2.5rem; font-size: 0.95rem;">
            </div>

            <!-- Category / Filter Pills -->
            <div style="display: flex; gap: 0.4rem; overflow-x: auto; padding-bottom: 0.5rem; margin-bottom: 1rem; scrollbar-width: thin;">
                <button type="button"
                        @click="pickerCategoryFilter = 'all'"
                        :class="{'btn-primary': pickerCategoryFilter === 'all', 'btn-secondary': pickerCategoryFilter !== 'all'}"
                        class="btn btn-sm"
                        style="border-radius: 8px; font-size: 0.78rem; font-weight: 700; white-space: nowrap;">
                    {{ __('All Ingredients') }} (<span x-text="allIngredients.length"></span>)
                </button>

                <template x-if="selectedSupplierId && currentSupplierOfferings.length > 0">
                    <button type="button"
                            @click="pickerCategoryFilter = 'supplier'"
                            :class="{'btn-primary': pickerCategoryFilter === 'supplier', 'btn-secondary': pickerCategoryFilter !== 'supplier'}"
                            class="btn btn-sm"
                            style="border-radius: 8px; font-size: 0.78rem; font-weight: 700; white-space: nowrap; border-color: #6366f1;">
                        <i class="fa-solid fa-star" style="color: #eab308; margin-right: 0.3rem;"></i>
                        {{ __('Supplier Catalog') }} (<span x-text="currentSupplierOfferings.length"></span>)
                    </button>
                </template>

                @foreach($categories as $cat)
                    <button type="button"
                            @click="pickerCategoryFilter = '{{ $cat->id }}'"
                            :class="{'btn-primary': pickerCategoryFilter === '{{ $cat->id }}', 'btn-secondary': pickerCategoryFilter !== '{{ $cat->id }}'}"
                            class="btn btn-sm"
                            style="border-radius: 8px; font-size: 0.78rem; font-weight: 600; white-space: nowrap;">
                        {{ $cat->name }}
                    </button>
                @endforeach
            </div>

            <!-- Scrollable Ingredients List -->
            <div style="overflow-y: auto; flex: 1; min-height: 250px; max-height: 400px; padding-right: 0.25rem;">
                <template x-if="filteredIngredients.length === 0">
                    <div style="padding: 3rem 1rem; text-align: center; color: var(--text-muted);">
                        <i class="fa-solid fa-box-open" style="font-size: 2.5rem; opacity: 0.4; margin-bottom: 0.75rem; display: block;"></i>
                        <div style="font-weight: 700; font-size: 1rem;">{{ __('No matching ingredients found') }}</div>
                        <div style="font-size: 0.85rem; margin-top: 0.35rem;">{{ __('Try adjusting your search query or filter') }}</div>
                    </div>
                </template>

                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <template x-for="ing in filteredIngredients" :key="ing.id">
                        <div @click="selectIngredient(ing)"
                             style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: 12px; cursor: pointer; transition: all 0.15s ease; background: var(--bg-card);"
                             onmouseover="this.style.borderColor='#6366f1'; this.style.background='rgba(99, 102, 241, 0.04)';"
                             onmouseout="this.style.borderColor='var(--border-color)'; this.style.background='var(--bg-card)';">
                            <div style="min-width: 0; flex: 1;">
                                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                    <span style="font-weight: 700; color: var(--text-main); font-size: 0.92rem;" x-text="ing.name"></span>
                                    <template x-if="ing.category_name">
                                        <span style="background: rgba(0,0,0,0.06); font-size: 0.72rem; padding: 0.1rem 0.4rem; border-radius: 4px; color: var(--text-muted);" x-text="ing.category_name"></span>
                                    </template>
                                    <template x-if="isSupplierItem(ing.id)">
                                        <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-size: 0.72rem; padding: 0.1rem 0.4rem; border-radius: 4px; font-weight: 700;">
                                            <i class="fa-solid fa-check"></i> {{ __('Offered by Supplier') }}
                                        </span>
                                    </template>
                                </div>
                                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">
                                    <span x-show="ing.sku">{{ __('SKU:') }} <span style="font-family: monospace;" x-text="ing.sku"></span> • </span>
                                    {{ __('Base Unit:') }} <span style="font-weight: 600;" x-text="ing.unit_code"></span>
                                </div>
                            </div>

                            <div style="text-align: right; display: flex; align-items: center; gap: 1rem;">
                                <div>
                                    <div style="font-family: monospace; font-weight: 800; font-size: 0.95rem; color: var(--text-main);">
                                        <span x-text="formatNumber(getIngredientPrice(ing))"></span> {{ Auth::user()->vendor->currency }}
                                    </div>
                                    <template x-if="getIngredientDiscount(ing) > 0">
                                        <div style="font-size: 0.72rem; color: #ef4444; font-weight: 700;">
                                            {{ __('Discount:') }} -<span x-text="getIngredientDiscount(ing)"></span>%
                                        </div>
                                    </template>
                                </div>

                                <button type="button" class="btn btn-primary btn-sm" style="border-radius: 8px; font-weight: 700;">
                                    {{ __('Select') }}
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Modal Footer -->
            <div style="border-top: 1px solid var(--border-color); margin-top: 1rem; padding-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.8rem; color: var(--text-muted);">
                    {{ __('Showing :count items', ['count' => '']) }}<span x-text="filteredIngredients.length"></span>
                </span>
                <button type="button" @click="closePickerModal()" class="btn btn-secondary" style="border-radius: 10px; font-weight: 600;">
                    {{ __('Close') }}
                </button>
            </div>
        </div>
    </dialog>
</div>

<style>
dialog#ingredientPickerModal {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    margin: 0;
    max-width: 680px;
    width: 95%;
    border: none;
    border-radius: 20px;
    padding: 0;
    background: transparent;
    z-index: 1050;
}
dialog#ingredientPickerModal::backdrop {
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(4px);
}
</style>

<script>
let globalPoForm = null;

function poForm() {
    const rawSupplierIngredients = @json($supplierIngredientsData);
    const rawAllIngredients = @json($ingredientsData);
    const rawSuppliers = @json($suppliersData);
    const defaultUnitId = '{{ $units->firstWhere('code', 'kg')?->id ?? $units->first()?->id }}';

    return {
        selectedSupplierId: '{{ old('supplier_id', request('supplier_id')) }}',
        orderDiscountPercent: {{ (float) old('discount_percent', 0) }},
        supplierIngredientsData: rawSupplierIngredients,
        allIngredients: rawAllIngredients,
        suppliersData: rawSuppliers,

        items: [
            {
                ingredient_id: '',
                ingredient_name: '',
                category_name: '',
                quantity: 1.0,
                unit_id: defaultUnitId,
                unit_price: 0.0,
                discount_percent: 0.0,
                tax_percent: 0.0
            }
        ],

        // Picker Modal State
        pickerActiveRowIndex: null,
        pickerSearch: '',
        pickerCategoryFilter: 'all',

        init() {
            globalPoForm = this;
            if (this.selectedSupplierId) {
                this.onSupplierChanged(false);
            }
        },

        get currentSupplierOfferings() {
            if (!this.selectedSupplierId || !this.supplierIngredientsData[this.selectedSupplierId]) {
                return [];
            }
            return this.supplierIngredientsData[this.selectedSupplierId];
        },

        isSupplierItem(ingredientId) {
            if (!this.selectedSupplierId || !this.supplierIngredientsData[this.selectedSupplierId]) {
                return false;
            }
            return this.supplierIngredientsData[this.selectedSupplierId].some(
                item => String(item.ingredient_id) === String(ingredientId)
            );
        },

        getSupplierOffering(ingredientId) {
            if (!this.selectedSupplierId || !this.supplierIngredientsData[this.selectedSupplierId]) {
                return null;
            }
            return this.supplierIngredientsData[this.selectedSupplierId].find(
                item => String(item.ingredient_id) === String(ingredientId)
            );
        },

        getIngredientPrice(ing) {
            const offering = this.getSupplierOffering(ing.id);
            if (offering && offering.price > 0) {
                return offering.price;
            }
            return ing.cost || 0.0;
        },

        getIngredientDiscount(ing) {
            const offering = this.getSupplierOffering(ing.id);
            if (offering && offering.discount_percent > 0) {
                return offering.discount_percent;
            }
            return 0.0;
        },

        get filteredIngredients() {
            let list = this.allIngredients;

            if (this.pickerCategoryFilter === 'supplier') {
                const sOfferings = this.currentSupplierOfferings;
                const offeringIds = new Set(sOfferings.map(o => String(o.ingredient_id)));
                list = list.filter(ing => offeringIds.has(String(ing.id)));
            } else if (this.pickerCategoryFilter !== 'all') {
                list = list.filter(ing => String(ing.category_id) === String(this.pickerCategoryFilter));
            }

            if (this.pickerSearch && this.pickerSearch.trim() !== '') {
                const term = this.pickerSearch.toLowerCase().trim();
                list = list.filter(ing =>
                    (ing.name && ing.name.toLowerCase().includes(term)) ||
                    (ing.sku && ing.sku.toLowerCase().includes(term)) ||
                    (ing.category_name && ing.category_name.toLowerCase().includes(term))
                );
            }

            return list;
        },

        onSupplierChanged(autoSetDiscount = true) {
            const sup = this.suppliersData.find(s => String(s.id) === String(this.selectedSupplierId));
            if (sup && autoSetDiscount && sup.discount_percent > 0 && this.orderDiscountPercent === 0) {
                this.orderDiscountPercent = sup.discount_percent;
            }

            // Refresh existing rows prices & discounts if supplier has special agreements
            for (const row of this.items) {
                if (row.ingredient_id) {
                    const offering = this.getSupplierOffering(row.ingredient_id);
                    if (offering) {
                        if (offering.price > 0) row.unit_price = offering.price;
                        if (offering.unit_id) row.unit_id = offering.unit_id;
                        if (offering.discount_percent > 0) row.discount_percent = offering.discount_percent;
                    }
                }
            }
        },

        addItem() {
            this.items.push({
                ingredient_id: '',
                ingredient_name: '',
                category_name: '',
                quantity: 1.0,
                unit_id: defaultUnitId,
                unit_price: 0.0,
                discount_percent: 0.0,
                tax_percent: 0.0
            });
        },

        removeItem(index) {
            if (this.items.length === 1) {
                // Reset first row instead of leaving empty
                this.items[0] = {
                    ingredient_id: '',
                    ingredient_name: '',
                    category_name: '',
                    quantity: 1.0,
                    unit_id: defaultUnitId,
                    unit_price: 0.0,
                    discount_percent: 0.0,
                    tax_percent: 0.0
                };
            } else {
                this.items.splice(index, 1);
            }
        },

        addSupplierOffering(offering) {
            // Check if already in items
            const existing = this.items.find(r => String(r.ingredient_id) === String(offering.ingredient_id));
            if (existing) {
                existing.quantity = Math.round((parseFloat(existing.quantity || 0) + 1.0) * 1000) / 1000;
                return;
            }

            // If first row is empty, fill it
            if (this.items.length === 1 && !this.items[0].ingredient_id) {
                this.items[0] = {
                    ingredient_id: String(offering.ingredient_id),
                    ingredient_name: offering.name,
                    category_name: '',
                    quantity: 1.0,
                    unit_id: String(offering.unit_id || defaultUnitId),
                    unit_price: parseFloat(offering.price) || 0.0,
                    discount_percent: parseFloat(offering.discount_percent) || 0.0,
                    tax_percent: 0.0
                };
            } else {
                this.items.push({
                    ingredient_id: String(offering.ingredient_id),
                    ingredient_name: offering.name,
                    category_name: '',
                    quantity: 1.0,
                    unit_id: String(offering.unit_id || defaultUnitId),
                    unit_price: parseFloat(offering.price) || 0.0,
                    discount_percent: parseFloat(offering.discount_percent) || 0.0,
                    tax_percent: 0.0
                });
            }
        },

        openPickerForRow(index) {
            this.pickerActiveRowIndex = index;
            this.pickerSearch = '';
            this.pickerCategoryFilter = this.selectedSupplierId && this.currentSupplierOfferings.length > 0 ? 'supplier' : 'all';
            const modal = document.getElementById('ingredientPickerModal');
            if (modal) {
                modal.showModal();
                this.$nextTick(() => {
                    if (this.$refs.pickerSearchInput) {
                        this.$refs.pickerSearchInput.focus();
                    }
                });
            }
        },

        openPickerForNewRow() {
            this.addItem();
            this.openPickerForRow(this.items.length - 1);
        },

        closePickerModal() {
            const modal = document.getElementById('ingredientPickerModal');
            if (modal) modal.close();
            this.pickerActiveRowIndex = null;
        },

        selectIngredient(ing) {
            if (this.pickerActiveRowIndex === null || this.pickerActiveRowIndex >= this.items.length) {
                return;
            }

            const row = this.items[this.pickerActiveRowIndex];
            row.ingredient_id = String(ing.id);
            row.ingredient_name = ing.name;
            row.category_name = ing.category_name || '';

            // Check if supplier has a contract price/unit
            const offering = this.getSupplierOffering(ing.id);
            if (offering) {
                row.unit_id = String(offering.unit_id || ing.unit_id || defaultUnitId);
                row.unit_price = parseFloat(offering.price) || 0.0;
                row.discount_percent = parseFloat(offering.discount_percent) || 0.0;
            } else {
                row.unit_id = String(ing.unit_id || defaultUnitId);
                row.unit_price = parseFloat(ing.cost) || 0.0;
                row.discount_percent = 0.0;
            }

            this.closePickerModal();
        },

        calculateLineTotal(row) {
            const qty = parseFloat(row.quantity) || 0;
            const price = parseFloat(row.unit_price) || 0;
            const disc = parseFloat(row.discount_percent) || 0;
            const tax = parseFloat(row.tax_percent) || 0;

            const base = qty * price;
            const discAmt = base * (disc / 100);
            const net = Math.max(0, base - discAmt);
            const lineTax = net * (tax / 100);

            return Math.round((net + lineTax) * 100) / 100;
        },

        calculateSubtotal() {
            let sub = 0;
            for (const row of this.items) {
                const qty = parseFloat(row.quantity) || 0;
                const price = parseFloat(row.unit_price) || 0;
                sub += (qty * price);
            }
            return Math.round(sub * 100) / 100;
        },

        calculateLineDiscountsTotal() {
            let discTotal = 0;
            for (const row of this.items) {
                const qty = parseFloat(row.quantity) || 0;
                const price = parseFloat(row.unit_price) || 0;
                const disc = parseFloat(row.discount_percent) || 0;
                discTotal += ((qty * price) * (disc / 100));
            }
            return Math.round(discTotal * 100) / 100;
        },

        calculateOrderDiscountAmount() {
            const sub = this.calculateSubtotal();
            const lineDisc = this.calculateLineDiscountsTotal();
            const netBeforeOrderDisc = Math.max(0, sub - lineDisc);
            const orderPct = parseFloat(this.orderDiscountPercent) || 0;
            return Math.round((netBeforeOrderDisc * (orderPct / 100)) * 100) / 100;
        },

        calculateTotalDiscount() {
            return Math.round((this.calculateLineDiscountsTotal() + this.calculateOrderDiscountAmount()) * 100) / 100;
        },

        calculateTotalTax() {
            let taxTotal = 0;
            for (const row of this.items) {
                const qty = parseFloat(row.quantity) || 0;
                const price = parseFloat(row.unit_price) || 0;
                const disc = parseFloat(row.discount_percent) || 0;
                const tax = parseFloat(row.tax_percent) || 0;

                const base = qty * price;
                const net = Math.max(0, base - (base * (disc / 100)));
                taxTotal += (net * (tax / 100));
            }
            return Math.round(taxTotal * 100) / 100;
        },

        calculateGrandTotal() {
            const sub = this.calculateSubtotal();
            const totalDisc = this.calculateTotalDiscount();
            const totalTax = this.calculateTotalTax();
            return Math.max(0, Math.round((sub - totalDisc + totalTax) * 100) / 100);
        },

        formatNumber(num) {
            const val = parseFloat(num) || 0;
            return val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        validateBeforeSubmit(e) {
            const validItems = this.items.filter(r => r.ingredient_id && parseFloat(r.quantity) > 0);
            if (validItems.length === 0) {
                alert('{{ __("Please select at least one ingredient with a valid quantity.") }}');
                e.preventDefault();
                return false;
            }
            return true;
        }
    };
}

window.addSuggestedItem = function(ingredientId, name, qty, unitId, price, supplierId) {
    if (!globalPoForm) return;

    if (supplierId && !globalPoForm.selectedSupplierId) {
        globalPoForm.selectedSupplierId = supplierId.toString();
        globalPoForm.onSupplierChanged();
    }

    const ing = globalPoForm.allIngredients.find(i => String(i.id) === String(ingredientId));
    const offering = globalPoForm.getSupplierOffering(ingredientId);

    const itemPrice = offering ? offering.price : (price || (ing ? ing.cost : 0.0));
    const itemDisc = offering ? offering.discount_percent : 0.0;
    const itemUnit = (offering && offering.unit_id) ? offering.unit_id : (unitId || (ing ? ing.unit_id : globalPoForm.items[0]?.unit_id));

    if (globalPoForm.items.length === 1 && !globalPoForm.items[0].ingredient_id) {
        globalPoForm.items[0] = {
            ingredient_id: ingredientId.toString(),
            ingredient_name: name,
            category_name: ing ? ing.category_name : '',
            quantity: qty,
            unit_id: itemUnit.toString(),
            unit_price: itemPrice,
            discount_percent: itemDisc,
            tax_percent: 0.0
        };
    } else {
        globalPoForm.items.push({
            ingredient_id: ingredientId.toString(),
            ingredient_name: name,
            category_name: ing ? ing.category_name : '',
            quantity: qty,
            unit_id: itemUnit.toString(),
            unit_price: itemPrice,
            discount_percent: itemDisc,
            tax_percent: 0.0
        });
    }
};
</script>
@endsection
