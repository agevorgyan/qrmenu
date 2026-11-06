@extends('layouts.app')

@section('title', __('Edit Supplier: ') . $supplier->name . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="max-width: 950px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <a href="{{ route('admin.inventory.suppliers.show', $supplier) }}" style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--text-muted); font-size: 0.85rem; font-weight: 600; text-decoration: none; margin-bottom: 0.75rem;">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Supplier Profile') }}
        </a>
        <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
            <span style="background: rgba(6, 182, 212, 0.15); color: #06b6d4; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                <i class="fa-solid fa-pen-to-square"></i>
            </span>
            <span>{{ __('Edit Supplier: ') }} {{ $supplier->name }}</span>
        </h1>
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

    <form method="POST" action="{{ route('admin.inventory.suppliers.update', $supplier) }}" x-data="supplierEditForm()" x-cloak>
        @csrf
        @method('PUT')

        <!-- 1. General & Contact Information -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
            <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin: 0 0 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-building" style="color: #3b82f6;"></i>
                <span>{{ __('Company Details & Contacts') }}</span>
            </h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; margin-bottom: 1.25rem;">
                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Company / Supplier Name') }} <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="name" value="{{ old('name', $supplier->name) }}" required class="form-input" style="width: 100%; border-radius: 10px;">
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Tax ID / VAT Code (ՀՎՀՀ)') }}
                    </label>
                    <input type="text" name="tax_id" value="{{ old('tax_id', $supplier->tax_id) }}" class="form-input" style="width: 100%; border-radius: 10px; font-family: monospace;">
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('VAT Number') }}
                    </label>
                    <input type="text" name="vat_number" value="{{ old('vat_number', $supplier->vat_number) }}" class="form-input" style="width: 100%; border-radius: 10px; font-family: monospace;">
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Supplier Internal Code') }}
                    </label>
                    <input type="text" name="code" value="{{ old('code', $supplier->code) }}" class="form-input" style="width: 100%; border-radius: 10px; font-family: monospace;">
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Contact Person') }}
                    </label>
                    <input type="text" name="contact_person" value="{{ old('contact_person', $supplier->contact_person) }}" class="form-input" style="width: 100%; border-radius: 10px;">
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Phone Number') }}
                    </label>
                    <input type="text" name="phone" value="{{ old('phone', $supplier->phone) }}" class="form-input" style="width: 100%; border-radius: 10px;">
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Email Address') }}
                    </label>
                    <input type="email" name="email" value="{{ old('email', $supplier->email) }}" class="form-input" style="width: 100%; border-radius: 10px;">
                </div>
            </div>

            <div>
                <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                    {{ __('Warehouse / Office Address') }}
                </label>
                <input type="text" name="address" value="{{ old('address', $supplier->address) }}" class="form-input" style="width: 100%; border-radius: 10px;">
            </div>
        </div>

        <!-- 2. Commercial & Banking Terms -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
            <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin: 0 0 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-credit-card" style="color: #10b981;"></i>
                <span>{{ __('Commercial Terms & Bank Details') }}</span>
            </h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 1.25rem;">
                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Payment Terms') }} <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="payment_terms" class="form-select" style="width: 100%; border-radius: 10px;">
                        <option value="net_30" {{ old('payment_terms', $supplier->payment_terms) === 'net_30' ? 'selected' : '' }}>{{ __('Net 30 Days') }}</option>
                        <option value="net_15" {{ old('payment_terms', $supplier->payment_terms) === 'net_15' ? 'selected' : '' }}>{{ __('Net 15 Days') }}</option>
                        <option value="net_7" {{ old('payment_terms', $supplier->payment_terms) === 'net_7' ? 'selected' : '' }}>{{ __('Net 7 Days') }}</option>
                        <option value="cash" {{ old('payment_terms', $supplier->payment_terms) === 'cash' ? 'selected' : '' }}>{{ __('Cash on Delivery') }}</option>
                        <option value="prepayment" {{ old('payment_terms', $supplier->payment_terms) === 'prepayment' ? 'selected' : '' }}>{{ __('Prepayment') }}</option>
                    </select>
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Minimum Order Amount') }} ({{ Auth::user()->vendor->currency }})
                    </label>
                    <input type="number" step="0.01" name="minimum_order_amount" value="{{ old('minimum_order_amount', $supplier->minimum_order_amount) }}" class="form-input" style="width: 100%; border-radius: 10px;">
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Supplier Default Discount %') }}
                    </label>
                    <input type="number" step="0.01" min="0" max="100" name="discount_percent" value="{{ old('discount_percent', $supplier->discount_percent) }}" class="form-input" style="width: 100%; border-radius: 10px;">
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Bank Name') }}
                    </label>
                    <input type="text" name="bank_name" value="{{ old('bank_name', $supplier->bank_name) }}" class="form-input" style="width: 100%; border-radius: 10px;">
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Bank Account / IBAN') }}
                    </label>
                    <input type="text" name="bank_account" value="{{ old('bank_account', $supplier->bank_account) }}" class="form-input" style="width: 100%; border-radius: 10px; font-family: monospace;">
                </div>
            </div>

            <div>
                <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                    {{ __('Internal Notes & Contract Remarks') }}
                </label>
                <textarea name="notes" rows="3" class="form-input" style="width: 100%; border-radius: 10px;">{{ old('notes', $supplier->notes) }}</textarea>
            </div>
        </div>

        <!-- 3. Contract Price List Catalog -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
                <div>
                    <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-tags" style="color: #f59e0b;"></i>
                        <span>{{ __('Contract Items & Prices') }}</span>
                    </h2>
                </div>
                <button type="button" @click="addItem()" class="btn btn-secondary btn-sm" style="border-radius: 8px; font-weight: 700;">
                    <i class="fa-solid fa-plus"></i> {{ __('Add Contract Item') }}
                </button>
            </div>

            <div style="overflow-x: auto;" x-show="items.length > 0">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.02);">
                            <th style="padding: 0.5rem;">{{ __('Ingredient') }}</th>
                            <th style="padding: 0.5rem; width: 140px;">{{ __('Contract Price') }} ({{ Auth::user()->vendor->currency }})</th>
                            <th style="padding: 0.5rem; width: 110px;">{{ __('Discount %') }}</th>
                            <th style="padding: 0.5rem; width: 130px;">{{ __('Unit') }}</th>
                            <th style="padding: 0.5rem; width: 140px;">{{ __('Supplier SKU') }}</th>
                            <th style="padding: 0.5rem; width: 100px; text-align: center;">{{ __('Primary?') }}</th>
                            <th style="padding: 0.5rem; width: 45px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(row, index) in items" :key="index">
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 0.5rem;">
                                    <select :name="`ingredients[${index}][ingredient_id]`" x-model="row.ingredient_id" @change="onIngredientSelected(row)" class="form-select" required style="font-size: 0.8rem; border-radius: 6px; width: 100%;">
                                        <option value="">{{ __('Select Ingredient...') }}</option>
                                        @foreach($ingredients as $ing)
                                            <option value="{{ $ing->id }}" data-unit="{{ $ing->unit_id }}">
                                                {{ $ing->name }} ({{ $ing->unit?->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td style="padding: 0.5rem;">
                                    <input type="number" step="0.01" :name="`ingredients[${index}][price]`" x-model.number="row.price" required class="form-input" style="font-size: 0.8rem; border-radius: 6px; width: 100%;">
                                </td>
                                <td style="padding: 0.5rem;">
                                    <input type="number" step="0.01" min="0" max="100" placeholder="0" :name="`ingredients[${index}][discount_percent]`" x-model.number="row.discount_percent" class="form-input" style="font-size: 0.8rem; border-radius: 6px; width: 100%;">
                                </td>
                                <td style="padding: 0.5rem;">
                                    <select :name="`ingredients[${index}][unit_id]`" x-model="row.unit_id" class="form-select" required style="font-size: 0.8rem; border-radius: 6px; width: 100%;">
                                        @foreach($units as $unit)
                                            <option value="{{ $unit->id }}">{{ $unit->code }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td style="padding: 0.5rem;">
                                    <input type="text" :name="`ingredients[${index}][supplier_sku]`" x-model="row.supplier_sku" class="form-input" style="font-size: 0.8rem; border-radius: 6px; width: 100%;">
                                </td>
                                <td style="padding: 0.5rem; text-align: center;">
                                    <input type="checkbox" :name="`ingredients[${index}][is_primary_supplier]`" value="1" x-model="row.is_primary_supplier" style="width: 16px; height: 16px; border-radius: 4px;">
                                </td>
                                <td style="padding: 0.5rem; text-align: center;">
                                    <button type="button" @click="removeItem(index)" class="btn btn-secondary btn-sm" style="color: #ef4444; border-radius: 6px;">
                                        <i class="fa-solid fa-times"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Submit Bar -->
        <div style="display: flex; justify-content: flex-end; gap: 1rem; align-items: center; margin-bottom: 3rem;">
            <a href="{{ route('admin.inventory.suppliers.show', $supplier) }}" class="btn btn-secondary" style="border-radius: 12px; font-weight: 600;">
                {{ __('Cancel') }}
            </a>
            <button type="submit" class="btn btn-primary" style="border-radius: 12px; font-weight: 700; padding: 0.75rem 2rem;">
                <i class="fa-solid fa-check"></i> {{ __('Update Supplier') }}
            </button>
        </div>
    </form>
</div>

<script>
function supplierEditForm() {
    return {
        items: @json($supplierIngredientsData ?? []),
        addItem() {
            this.items.push({
                ingredient_id: '',
                price: 0.0,
                discount_percent: 0.0,
                unit_id: '{{ $units->firstWhere('code', 'kg')?->id ?? $units->first()?->id }}',
                supplier_sku: '',
                is_primary_supplier: false
            });
        },
        removeItem(index) {
            this.items.splice(index, 1);
        },
        onIngredientSelected(row) {
            const selectEl = event.target;
            const selectedOpt = selectEl.options[selectEl.selectedIndex];
            if (selectedOpt && selectedOpt.dataset.unit) {
                row.unit_id = selectedOpt.dataset.unit;
            }
        }
    };
}
</script>
@endsection

