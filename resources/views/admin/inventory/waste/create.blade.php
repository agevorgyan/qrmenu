@extends('layouts.app')

@section('title', __('Record Waste Write-Off') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="max-width: 800px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                <span style="background: rgba(239, 68, 68, 0.15); color: #ef4444; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    <i class="fa-solid fa-trash-can"></i>
                </span>
                <span>{{ __('Record Waste Write-Off') }}</span>
            </h1>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0;">
                {{ __('Deduct wasted, expired, or spoiled goods from physical warehouse inventory and record loss.') }}
            </p>
        </div>
        <a href="{{ route('admin.inventory.waste.index') }}" class="btn btn-outline-secondary" style="border-radius: 10px;">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Back') }}
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger" style="border-radius: 12px; margin-bottom: 1.5rem;">
            <ul style="margin: 0; padding-left: 1.25rem;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 2rem; box-shadow: var(--shadow-card);"
         x-data="{
             selectedIngredientId: '{{ old('ingredient_id') }}',
             ingredients: @js($ingredients),
             batches: @js($batches),
             unitCost: '{{ old('unit_cost', 0) }}',
             quantity: '{{ old('quantity', 1) }}',
             get currentIngredient() {
                 return this.ingredients.find(i => i.id == this.selectedIngredientId);
             },
             get availableBatches() {
                 return this.batches.filter(b => b.ingredient_id == this.selectedIngredientId);
             },
             onIngredientChange() {
                 if (this.currentIngredient) {
                     this.unitCost = this.currentIngredient.last_purchase_price || 0;
                 }
             },
             onBatchChange(e) {
                 const b = this.batches.find(b => b.id == e.target.value);
                 if (b && b.unit_cost > 0) {
                     this.unitCost = b.unit_cost;
                 }
             }
         }">
        <form method="POST" action="{{ route('admin.inventory.waste.store') }}">
            @csrf

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
                <!-- Warehouse -->
                <div>
                    <label class="form-label" style="font-weight: 700; font-size: 0.85rem;">{{ __('Warehouse / Location') }} <span style="color: #ef4444;">*</span></label>
                    <select name="warehouse_id" class="form-select" style="border-radius: 10px;" required>
                        <option value="">{{ __('Select Warehouse') }}</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ old('warehouse_id') == $wh->id ? 'selected' : '' }}>
                                {{ $wh->name }} ({{ $wh->code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Ingredient / Raw Material -->
                <div>
                    <label class="form-label" style="font-weight: 700; font-size: 0.85rem;">{{ __('Item / Raw Material') }} <span style="color: #ef4444;">*</span></label>
                    <select name="ingredient_id" class="form-select" style="border-radius: 10px;" x-model="selectedIngredientId" @change="onIngredientChange" required>
                        <option value="">{{ __('Select Ingredient') }}</option>
                        @foreach($ingredients as $ing)
                            <option value="{{ $ing->id }}" {{ old('ingredient_id') == $ing->id ? 'selected' : '' }}>
                                {{ $ing->name }} ({{ $ing->unit?->symbol ?? 'kg' }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Optional Batch Number (FEFO) -->
            <div style="margin-bottom: 1.5rem;" x-show="availableBatches.length > 0">
                <label class="form-label" style="font-weight: 700; font-size: 0.85rem;">
                    {{ __('Specific Batch / Lot (Optional)') }}
                    <span style="font-weight: 400; color: var(--text-muted); font-size: 0.8rem;">— {{ __('Deducts directly from active batch balance') }}</span>
                </label>
                <select name="batch_id" class="form-select" style="border-radius: 10px; font-family: monospace;" @change="onBatchChange">
                    <option value="">{{ __('No specific lot (Warehouse ledger deduction only)') }}</option>
                    <template x-for="b in availableBatches" :key="b.id">
                        <option :value="b.id" x-text="`${b.batch_number} (Rem: ${b.remaining_quantity}, Expiry: ${b.expiry_date || 'N/A'})`"></option>
                    </template>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
                <!-- Quantity -->
                <div>
                    <label class="form-label" style="font-weight: 700; font-size: 0.85rem;">{{ __('Quantity Wasted') }} <span style="color: #ef4444;">*</span></label>
                    <input type="number" step="0.0001" min="0.0001" name="quantity" x-model="quantity" class="form-control" style="border-radius: 10px;" required>
                </div>

                <!-- Unit of Measure -->
                <div>
                    <label class="form-label" style="font-weight: 700; font-size: 0.85rem;">{{ __('Unit') }}</label>
                    <select name="unit_id" class="form-select" style="border-radius: 10px;">
                        @foreach($units as $u)
                            <option value="{{ $u->id }}" {{ old('unit_id') == $u->id ? 'selected' : '' }}>{{ $u->name }} ({{ $u->symbol }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- Unit Cost (AMD) -->
                <div>
                    <label class="form-label" style="font-weight: 700; font-size: 0.85rem;">{{ __('Unit Cost') }} ({{ Auth::user()->vendor->currency }})</label>
                    <input type="number" step="0.01" min="0" name="unit_cost" x-model="unitCost" class="form-control" style="border-radius: 10px;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
                <!-- Reason -->
                <div>
                    <label class="form-label" style="font-weight: 700; font-size: 0.85rem;">{{ __('Waste Reason') }} <span style="color: #ef4444;">*</span></label>
                    <select name="reason" class="form-select" style="border-radius: 10px;" required>
                        <option value="spoilage">{{ __('Spoilage / Rotten') }}</option>
                        <option value="expiration">{{ __('Expired Shelf Life') }}</option>
                        <option value="prep_mistake">{{ __('Kitchen Prep Mistake') }}</option>
                        <option value="burn_spill">{{ __('Overcooked / Burned / Spilled') }}</option>
                        <option value="damage">{{ __('Damaged / Dropped Packaging') }}</option>
                        <option value="storage_failure">{{ __('Cooler / Storage Equipment Malfunction') }}</option>
                        <option value="customer_complaint">{{ __('Customer Return / Complaint') }}</option>
                        <option value="other">{{ __('Other') }}</option>
                    </select>
                </div>

                <!-- Action Taken -->
                <div>
                    <label class="form-label" style="font-weight: 700; font-size: 0.85rem;">{{ __('Action Taken') }}</label>
                    <select name="action_taken" class="form-select" style="border-radius: 10px;">
                        <option value="discarded">{{ __('Discarded in Waste Bin') }}</option>
                        <option value="staff_meal">{{ __('Repurposed for Staff Meal') }}</option>
                        <option value="repurposed">{{ __('Repurposed into Broth / Secondary Dish') }}</option>
                        <option value="returned_to_vendor">{{ __('Returned / Claimed to Supplier') }}</option>
                    </select>
                </div>
            </div>

            <!-- Notes -->
            <div style="margin-bottom: 1.75rem;">
                <label class="form-label" style="font-weight: 700; font-size: 0.85rem;">{{ __('Notes & Circumstances') }}</label>
                <textarea name="notes" rows="3" class="form-control" style="border-radius: 10px;" placeholder="{{ __('Provide details on the cause of spoilage or corrective actions taken...') }}">{{ old('notes') }}</textarea>
            </div>

            <!-- Estimated Financial Loss Preview -->
            <div style="background: rgba(239, 68, 68, 0.08); border: 1px dashed rgba(239, 68, 68, 0.35); border-radius: 12px; padding: 1.25rem; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fa-solid fa-calculator" style="color: #ef4444; font-size: 1.25rem;"></i>
                    <span style="font-weight: 600; color: var(--text-main);">{{ __('Estimated Total Loss') }}:</span>
                </div>
                <div style="font-size: 1.4rem; font-weight: 800; color: #ef4444;">
                    <span x-text="((parseFloat(quantity) || 0) * (parseFloat(unitCost) || 0)).toFixed(2)"></span> {{ Auth::user()->vendor->currency }}
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                <a href="{{ route('admin.inventory.waste.index') }}" class="btn btn-outline-secondary" style="border-radius: 10px; font-weight: 600;">
                    {{ __('Cancel') }}
                </a>
                <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 700; background: #ef4444; border-color: #ef4444; padding: 0.65rem 2rem;">
                    <i class="fa-solid fa-check"></i> {{ __('Confirm & Write-Off Stock') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

