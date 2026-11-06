@extends('layouts.app')

@section('title', __('New Production Order') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="max-width: 1000px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <a href="{{ route('admin.inventory.production.index') }}" style="color: var(--text-muted); text-decoration: none; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 0.4rem; margin-bottom: 0.5rem;">
                <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Production Orders') }}
            </a>
            <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                <span style="background: rgba(249, 115, 22, 0.15); color: #f97316; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-industry"></i>
                </span>
                <span>{{ __('New Production Order') }}</span>
            </h1>
        </div>
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

    <form method="POST" action="{{ route('admin.inventory.production.store') }}" x-data="productionForm()">
        @csrf

        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.75rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-main); margin: 0 0 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-sliders" style="color: #f97316;"></i> {{ __('Order Configuration') }}
            </h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
                <!-- Recipe Selection -->
                <div>
                    <label class="form-label" style="font-weight: 600;">{{ __('Recipe / Technical Card') }} <span style="color: #ef4444;">*</span></label>
                    <select name="recipe_id" class="form-select" style="border-radius: 10px;" x-model="selectedRecipeId" @change="onRecipeChange()" required>
                        <option value="">{{ __('-- Select Recipe --') }}</option>
                        @foreach($recipes as $recipe)
                            <option value="{{ $recipe->id }}" {{ (old('recipe_id', $preselectedRecipe?->id) == $recipe->id) ? 'selected' : '' }}>
                                {{ $recipe->name }} ({{ strtoupper($recipe->type) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Produced Output Ingredient -->
                <div>
                    <label class="form-label" style="font-weight: 600;">{{ __('Output Catalog Item (Semi-Finished / Finished)') }}</label>
                    <select name="produced_ingredient_id" class="form-select" style="border-radius: 10px;" x-model="producedIngredientId">
                        <option value="">{{ __('-- Auto-detect or None --') }}</option>
                        @foreach($ingredients as $ing)
                            <option value="{{ $ing->id }}" {{ old('produced_ingredient_id') == $ing->id ? 'selected' : '' }}>
                                {{ $ing->name }} [{{ $ing->sku }}]
                            </option>
                        @endforeach
                    </select>
                    <small style="color: var(--text-muted); font-size: 0.75rem;">
                        {{ __('Item stocked into destination warehouse upon completion.') }}
                    </small>
                </div>

                <!-- Source Warehouse -->
                <div>
                    <label class="form-label" style="font-weight: 600;">{{ __('Source Warehouse (Raw Ingredients)') }} <span style="color: #ef4444;">*</span></label>
                    <select name="source_warehouse_id" class="form-select" style="border-radius: 10px;" required>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ (old('source_warehouse_id') == $wh->id || $wh->is_default) ? 'selected' : '' }}>
                                {{ $wh->name }} ({{ ucfirst($wh->type) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Destination Warehouse -->
                <div>
                    <label class="form-label" style="font-weight: 600;">{{ __('Destination Warehouse (Finished Output)') }} <span style="color: #ef4444;">*</span></label>
                    <select name="destination_warehouse_id" class="form-select" style="border-radius: 10px;" required>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ (old('destination_warehouse_id') == $wh->id || $wh->is_default) ? 'selected' : '' }}>
                                {{ $wh->name }} ({{ ucfirst($wh->type) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Planned Quantity -->
                <div>
                    <label class="form-label" style="font-weight: 600;">{{ __('Planned Output Quantity') }} <span style="color: #ef4444;">*</span></label>
                    <div class="input-group">
                        <input type="number" step="0.0001" min="0.0001" name="planned_quantity" class="form-control" style="border-radius: 10px 0 0 10px;" x-model.number="plannedQuantity" required>
                        <select name="unit_id" class="form-select" style="max-width: 110px; border-radius: 0 10px 10px 0;" x-model="selectedUnitId">
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->code }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Planned Start Date -->
                <div>
                    <label class="form-label" style="font-weight: 600;">{{ __('Planned Start Date & Time') }}</label>
                    <input type="datetime-local" name="planned_start_at" class="form-control" style="border-radius: 10px;" value="{{ old('planned_start_at', Auth::user()->vendor->getLocalTime()->format('Y-m-d\TH:i')) }}">
                </div>

                <!-- Labor & Overhead Estimates -->
                <div>
                    <label class="form-label" style="font-weight: 600;">{{ __('Est. Direct Labor Cost') }} ({{ Auth::user()->vendor->currency }})</label>
                    <input type="number" step="0.01" min="0" name="labor_cost" class="form-control" style="border-radius: 10px;" x-model.number="laborCost">
                </div>

                <div>
                    <label class="form-label" style="font-weight: 600;">{{ __('Est. Overhead Cost') }} ({{ Auth::user()->vendor->currency }})</label>
                    <input type="number" step="0.01" min="0" name="overhead_cost" class="form-control" style="border-radius: 10px;" x-model.number="overheadCost">
                </div>
            </div>

            <!-- Notes -->
            <div style="margin-top: 1.25rem;">
                <label class="form-label" style="font-weight: 600;">{{ __('Production Notes & Instructions') }}</label>
                <textarea name="notes" rows="2" class="form-control" style="border-radius: 10px;" placeholder="{{ __('e.g., Target texture, temperature requirements, pre-chill before storage...') }}">{{ old('notes') }}</textarea>
            </div>
        </div>

        <!-- Dynamic Bill of Materials Preview Card -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; margin-bottom: 2rem; box-shadow: var(--shadow-card);" x-show="currentRecipe">
            <div style="padding: 1.25rem 1.75rem; background: var(--bg-hover); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                <div>
                    <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-list-check" style="color: #f97316;"></i> {{ __('Bill of Materials (BOM) Scaling Preview') }}
                    </h3>
                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">
                        {{ __('Quantities automatically scale proportionally based on recipe standard yield.') }}
                    </div>
                </div>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-main);">
                        {{ __('Est. Total Cost') }}: 
                        <span style="color: #10b981;" x-text="formatCurrency(calculateEstimatedTotalCost())"></span>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table" style="margin: 0;">
                    <thead style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); background: var(--bg-hover);">
                        <tr>
                            <th style="padding: 0.75rem 1.25rem;">{{ __('Item Component') }}</th>
                            <th style="padding: 0.75rem;">{{ __('Type') }}</th>
                            <th style="padding: 0.75rem; text-align: right;">{{ __('Standard BOM Gross') }}</th>
                            <th style="padding: 0.75rem; text-align: right;">{{ __('Scaled Order Qty') }}</th>
                            <th style="padding: 0.75rem 1.25rem; text-align: right;">{{ __('Est. Item Cost') }} ({{ Auth::user()->vendor->currency }})</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="item in currentRecipe?.items || []" :key="item.id">
                            <tr style="border-top: 1px solid var(--border-color);">
                                <td style="padding: 0.75rem 1.25rem;">
                                    <div style="font-weight: 600; color: var(--text-main);" x-text="getItemName(item)"></div>
                                </td>
                                <td style="padding: 0.75rem;">
                                    <span class="badge" style="background: rgba(107, 114, 128, 0.15); color: var(--text-muted); font-size: 0.7rem; text-transform: uppercase;" x-text="item.type"></span>
                                </td>
                                <td style="padding: 0.75rem; text-align: right; color: var(--text-muted);" x-text="item.gross_weight + ' ' + (item.unit?.code || '')">
                                </td>
                                <td style="padding: 0.75rem; text-align: right; font-weight: 700; color: #f97316;" x-text="calculateScaledQty(item) + ' ' + (item.unit?.code || '')">
                                </td>
                                <td style="padding: 0.75rem 1.25rem; text-align: right; font-weight: 600;" x-text="formatCurrency(calculateItemCost(item))">
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 1rem;">
            <a href="{{ route('admin.inventory.production.index') }}" class="btn btn-outline-secondary" style="border-radius: 10px; font-weight: 600;">
                {{ __('Cancel') }}
            </a>
            <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 700; background: #f97316; border-color: #f97316; padding: 0.65rem 1.75rem;">
                <i class="fa-solid fa-check"></i> {{ __('Create Production Order') }}
            </button>
        </div>
    </form>
</div>

<script>
function productionForm() {
    const rawRecipes = @json($recipes);
    const initialRecipeId = "{{ old('recipe_id', $preselectedRecipe?->id ?? '') }}";

    return {
        recipes: rawRecipes,
        selectedRecipeId: initialRecipeId,
        currentRecipe: null,
        producedIngredientId: "{{ old('produced_ingredient_id', $preselectedRecipe?->produced_ingredient_id ?? '') }}",
        plannedQuantity: {{ old('planned_quantity', $preselectedRecipe?->yield_quantity ?? 1.0) }},
        selectedUnitId: "{{ old('unit_id', $preselectedRecipe?->yield_unit_id ?? '') }}",
        laborCost: {{ old('labor_cost', 0) }},
        overheadCost: {{ old('overhead_cost', 0) }},

        init() {
            if (this.selectedRecipeId) {
                this.onRecipeChange();
            }
        },

        onRecipeChange() {
            this.currentRecipe = this.recipes.find(r => String(r.id) === String(this.selectedRecipeId)) || null;
            if (this.currentRecipe) {
                this.selectedUnitId = this.currentRecipe.yield_unit_id;
                this.plannedQuantity = parseFloat(this.currentRecipe.yield_quantity) || 1.0;
                if (this.currentRecipe.produced_ingredient_id) {
                    this.producedIngredientId = this.currentRecipe.produced_ingredient_id;
                }
            }
        },

        getItemName(item) {
            if (item.type === 'ingredient' && item.ingredient) {
                return item.ingredient.name;
            } else if (item.type === 'sub_recipe' && item.sub_recipe) {
                return item.sub_recipe.name;
            }
            return 'Component #' + item.id;
        },

        calculateScaledQty(item) {
            if (!this.currentRecipe) return 0;
            const yieldQty = parseFloat(this.currentRecipe.yield_quantity) || 1.0;
            const ratio = (this.plannedQuantity || 1.0) / yieldQty;
            const gross = parseFloat(item.gross_weight) || 0.0;
            return (gross * ratio).toFixed(4);
        },

        calculateItemCost(item) {
            const scaledQty = parseFloat(this.calculateScaledQty(item)) || 0.0;
            let unitCost = 0.0;
            if (item.type === 'ingredient' && item.ingredient) {
                unitCost = parseFloat(item.ingredient.purchase_price) || 0.0;
            } else if (item.type === 'sub_recipe' && item.sub_recipe?.latest_cost) {
                unitCost = parseFloat(item.sub_recipe.latest_cost.portion_cost) || 0.0;
            }
            return (scaledQty * unitCost);
        },

        calculateEstimatedTotalCost() {
            if (!this.currentRecipe) return 0.0;
            let materialCost = 0.0;
            for (const item of (this.currentRecipe.items || [])) {
                materialCost += this.calculateItemCost(item);
            }
            return materialCost + (parseFloat(this.laborCost) || 0) + (parseFloat(this.overheadCost) || 0);
        },

        formatCurrency(num) {
            return (parseFloat(num) || 0).toFixed(2) + ' ' + '{{ Auth::user()->vendor->currency }}';
        }
    };
}
</script>
@endsection

