@extends('layouts.app')

@section('title', __('Create Technical Card') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="max-width: 1100px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <a href="{{ route('admin.inventory.recipes.index') }}" style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--text-muted); font-size: 0.85rem; font-weight: 600; text-decoration: none; margin-bottom: 0.75rem;">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Recipes') }}
        </a>
        <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
            <span style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                <i class="fa-solid fa-file-circle-plus"></i>
            </span>
            <span>{{ __('Create Technical Card') }}</span>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0;">
            {{ __('Define dish or semi-finished bill of materials (BOM), shrinkage losses, cooking instructions, and HACCP CCP points.') }}
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

    <form method="POST" action="{{ route('admin.inventory.recipes.store') }}" enctype="multipart/form-data"
          x-data="recipeForm()" x-cloak>
        @csrf

        <!-- 1. Basic Information -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
            <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin: 0 0 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-info-circle" style="color: #3b82f6;"></i>
                <span>{{ __('Basic Recipe Information') }}</span>
            </h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem; margin-bottom: 1.25rem;">
                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Recipe / Dish Name') }} <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="{{ __('e.g., Beef Stroganoff') }}" class="form-input" style="width: 100%; border-radius: 10px;">
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Recipe Code / SKU') }} <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="code" value="{{ old('code', 'REC-' . strtoupper(Str::random(6))) }}" required class="form-input" style="width: 100%; border-radius: 10px; font-family: monospace;">
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Recipe Type') }} <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="type" x-model="recipeType" class="form-select" style="width: 100%; border-radius: 10px;">
                        <option value="dish">{{ __('Dish / Menu Item') }}</option>
                        <option value="semi_finished">{{ __('Semi-Finished Product') }}</option>
                        <option value="sauce">{{ __('Sauce / Dressing') }}</option>
                        <option value="bakery">{{ __('Bakery / Pastry') }}</option>
                        <option value="beverage">{{ __('Beverage / Cocktail') }}</option>
                        <option value="prep">{{ __('Prep Item') }}</option>
                    </select>
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Category') }}
                    </label>
                    <select name="category_id" class="form-select" style="width: 100%; border-radius: 10px;">
                        <option value="">{{ __('Select Category') }}</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Linked Menu Product (If dish or beverage) -->
                <div x-show="recipeType === 'dish' || recipeType === 'beverage'">
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Link with Menu Product (Sales Item)') }}
                    </label>
                    <select name="product_id" class="form-select" style="width: 100%; border-radius: 10px;">
                        <option value="">{{ __('No linked menu product') }}</option>
                        @foreach($products as $prod)
                            <option value="{{ $prod->id }}" {{ old('product_id') == $prod->id ? 'selected' : '' }}>
                                {{ $prod->name }} ({{ number_format($prod->price, 2) }} {{ Auth::user()->vendor->currency }})
                            </option>
                        @endforeach
                    </select>
                    <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; display: block;">
                        {{ __('Linking connects this recipe to sales orders and automated stock deductions.') }}
                    </span>
                </div>

                <!-- Produced Semi-finished Ingredient (If semi_finished, sauce, or prep) -->
                <div x-show="recipeType === 'semi_finished' || recipeType === 'sauce' || recipeType === 'prep'">
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Produces Inventory Item (Stock In Target)') }}
                    </label>
                    <select name="produced_ingredient_id" class="form-select" style="width: 100%; border-radius: 10px;">
                        <option value="">{{ __('Select Semi-Finished Ingredient') }}</option>
                        @foreach($ingredients->where('type', 'semi_finished') as $ing)
                            <option value="{{ $ing->id }}" {{ old('produced_ingredient_id') == $ing->id ? 'selected' : '' }}>
                                {{ $ing->name }} ({{ $ing->unit?->code }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Yield & Portions -->
            <div style="background: rgba(0,0,0,0.02); border-radius: 12px; padding: 1.25rem; margin-top: 1rem;">
                <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.75rem;">
                    <i class="fa-solid fa-scale-balanced" style="color: #f59e0b;"></i> {{ __('Yield & Portion Specifications') }}
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;">
                    <div>
                        <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">
                            {{ __('Yield Output Quantity') }} <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="number" step="0.0001" name="yield_quantity" value="{{ old('yield_quantity', '1.0000') }}" required class="form-input" style="width: 100%; border-radius: 8px;">
                    </div>

                    <div>
                        <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">
                            {{ __('Yield Unit') }} <span style="color: #ef4444;">*</span>
                        </label>
                        <select name="yield_unit_id" required class="form-select" style="width: 100%; border-radius: 8px;">
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}" {{ old('yield_unit_id', $units->firstWhere('code', 'portion')?->id) == $unit->id ? 'selected' : '' }}>
                                    {{ $unit->name }} ({{ $unit->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">
                            {{ __('Portion Net Weight / Size') }}
                        </label>
                        <input type="number" step="0.0001" name="portion_size" value="{{ old('portion_size') }}" placeholder="{{ __('e.g., 350') }}" class="form-input" style="width: 100%; border-radius: 8px;">
                    </div>

                    <div>
                        <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">
                            {{ __('Portion Unit') }}
                        </label>
                        <select name="portion_unit_id" class="form-select" style="width: 100%; border-radius: 8px;">
                            <option value="">{{ __('Same as yield') }}</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}" {{ old('portion_unit_id') == $unit->id ? 'selected' : '' }}>
                                    {{ $unit->name }} ({{ $unit->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-top: 1rem;">
                    <div>
                        <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">
                            {{ __('Selling Price') }} ({{ Auth::user()->vendor->currency }})
                        </label>
                        <input type="number" step="0.01" name="selling_price" value="{{ old('selling_price') }}" placeholder="0.00" class="form-input" style="width: 100%; border-radius: 8px;">
                    </div>

                    <div>
                        <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">
                            {{ __('Target Food Cost %') }}
                        </label>
                        <input type="number" step="0.01" name="target_food_cost_percent" value="{{ old('target_food_cost_percent', '28.00') }}" placeholder="28.00" class="form-input" style="width: 100%; border-radius: 8px;">
                    </div>

                    <div>
                        <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">
                            {{ __('Prep Time (min)') }}
                        </label>
                        <input type="number" name="prep_time_minutes" value="{{ old('prep_time_minutes') }}" placeholder="15" class="form-input" style="width: 100%; border-radius: 8px;">
                    </div>

                    <div>
                        <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">
                            {{ __('Cook Time (min)') }}
                        </label>
                        <input type="number" name="cook_time_minutes" value="{{ old('cook_time_minutes') }}" placeholder="20" class="form-input" style="width: 100%; border-radius: 8px;">
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Recipe Bill of Materials (BOM) & Shrinkage -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.5rem;">
                <div>
                    <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-layer-group" style="color: #10b981;"></i>
                        <span>{{ __('Ingredients & Sub-Recipes (BOM)') }}</span>
                    </h2>
                    <p style="color: var(--text-muted); font-size: 0.8rem; margin: 0.25rem 0 0;">
                        {{ __('Formula: Net = Gross × (1 - Cold Loss %). Final Weight = Net × (1 - Cook Loss %). Cost is calculated on Gross Weight.') }}
                    </p>
                </div>
                <button type="button" @click="addItem()" class="btn btn-secondary btn-sm" style="border-radius: 8px; font-weight: 700; display: flex; align-items: center; gap: 0.4rem;">
                    <i class="fa-solid fa-plus"></i> {{ __('Add Ingredient / Sub-Recipe') }}
                </button>
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-color); background: rgba(0,0,0,0.02); text-align: left;">
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); width: 110px;">{{ __('Type') }}</th>
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-main); min-width: 220px;">{{ __('Item / Component') }}</th>
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); width: 110px;">{{ __('Unit') }}</th>
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); width: 95px;" title="{{ __('Gross weight before cleaning') }}">{{ __('Gross') }}</th>
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); width: 85px;" title="{{ __('Cold prep / cleaning shrinkage %') }}">{{ __('Cold %') }}</th>
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); width: 95px;" title="{{ __('Net weight after cleaning') }}">{{ __('Net') }}</th>
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); width: 85px;" title="{{ __('Thermal / cooking shrinkage %') }}">{{ __('Cook %') }}</th>
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); width: 95px;" title="{{ __('Final yield weight') }}">{{ __('Final') }}</th>
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); min-width: 120px;">{{ __('Notes') }}</th>
                            <th style="padding: 0.65rem 0.5rem; width: 40px; text-align: center;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(row, index) in items" :key="index">
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 0.6rem 0.5rem;">
                                    <select :name="`items[${index}][type]`" x-model="row.type" class="form-select" style="font-size: 0.8rem; padding: 0.35rem; border-radius: 6px; width: 100%;">
                                        <option value="ingredient">{{ __('Ingredient') }}</option>
                                        <option value="sub_recipe">{{ __('Sub-Recipe') }}</option>
                                    </select>
                                </td>

                                <td style="padding: 0.6rem 0.5rem;">
                                    <template x-if="row.type === 'ingredient'">
                                        <select :name="`items[${index}][ingredient_id]`" x-model="row.ingredient_id" @change="onIngredientSelected(row)" class="form-select" required style="font-size: 0.8rem; padding: 0.35rem; border-radius: 6px; width: 100%;">
                                            <option value="">{{ __('Select Ingredient...') }}</option>
                                            @foreach($ingredients as $ing)
                                                <option value="{{ $ing->id }}" data-unit="{{ $ing->unit_id }}">
                                                    {{ $ing->name }} ({{ $ing->unit?->code }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </template>
                                    <template x-if="row.type === 'sub_recipe'">
                                        <select :name="`items[${index}][sub_recipe_id]`" x-model="row.sub_recipe_id" @change="onSubRecipeSelected(row)" class="form-select" required style="font-size: 0.8rem; padding: 0.35rem; border-radius: 6px; width: 100%;">
                                            <option value="">{{ __('Select Sub-Recipe...') }}</option>
                                            @foreach($semiFinishedRecipes as $sfr)
                                                <option value="{{ $sfr->id }}" data-unit="{{ $sfr->yield_unit_id }}">
                                                    {{ $sfr->name }} ({{ $sfr->yieldUnit?->code }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </template>
                                </td>

                                <td style="padding: 0.6rem 0.5rem;">
                                    <select :name="`items[${index}][unit_id]`" x-model="row.unit_id" class="form-select" required style="font-size: 0.8rem; padding: 0.35rem; border-radius: 6px; width: 100%;">
                                        @foreach($units as $unit)
                                            <option value="{{ $unit->id }}">{{ $unit->code }}</option>
                                        @endforeach
                                    </select>
                                </td>

                                <td style="padding: 0.6rem 0.5rem;">
                                    <input type="number" step="0.0001" :name="`items[${index}][gross_weight]`" x-model.number="row.gross_weight" @input="recalculateRow(row)" required class="form-input" style="font-size: 0.8rem; padding: 0.35rem; border-radius: 6px; width: 100%;">
                                </td>

                                <td style="padding: 0.6rem 0.5rem;">
                                    <input type="number" step="0.01" :name="`items[${index}][prep_loss_percent]`" x-model.number="row.prep_loss_percent" @input="recalculateRow(row)" class="form-input" style="font-size: 0.8rem; padding: 0.35rem; border-radius: 6px; width: 100%;">
                                </td>

                                <td style="padding: 0.6rem 0.5rem;">
                                    <input type="number" step="0.0001" :name="`items[${index}][net_weight]`" x-model.number="row.net_weight" @input="recalculateFromNet(row)" class="form-input" style="font-size: 0.8rem; padding: 0.35rem; border-radius: 6px; width: 100%;">
                                </td>

                                <td style="padding: 0.6rem 0.5rem;">
                                    <input type="number" step="0.01" :name="`items[${index}][cooking_loss_percent]`" x-model.number="row.cooking_loss_percent" @input="recalculateRow(row)" class="form-input" style="font-size: 0.8rem; padding: 0.35rem; border-radius: 6px; width: 100%;">
                                </td>

                                <td style="padding: 0.6rem 0.5rem;">
                                    <input type="number" step="0.0001" :name="`items[${index}][final_weight]`" x-model.number="row.final_weight" readonly class="form-input" style="font-size: 0.8rem; padding: 0.35rem; border-radius: 6px; width: 100%; background: rgba(0,0,0,0.03);">
                                </td>

                                <td style="padding: 0.6rem 0.5rem;">
                                    <input type="text" :name="`items[${index}][notes]`" x-model="row.notes" placeholder="{{ __('Cut / culinary note') }}" class="form-input" style="font-size: 0.8rem; padding: 0.35rem; border-radius: 6px; width: 100%;">
                                </td>

                                <td style="padding: 0.6rem 0.5rem; text-align: center;">
                                    <button type="button" @click="removeItem(index)" class="btn btn-secondary btn-sm" style="color: #ef4444; border-radius: 6px; padding: 0.35rem 0.5rem;">
                                        <i class="fa-solid fa-times"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div x-show="items.length === 0" style="padding: 2rem; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                {{ __('No ingredients added yet. Click "+ Add Ingredient / Sub-Recipe" to add components to this recipe.') }}
            </div>
        </div>

        <!-- 3. Packaging Materials -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
                <div>
                    <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-box-open" style="color: #ec4899;"></i>
                        <span>{{ __('Packaging Materials (Takeaway / Delivery)') }}</span>
                    </h2>
                    <p style="color: var(--text-muted); font-size: 0.8rem; margin: 0.25rem 0 0;">
                        {{ __('Box, container, bag, or labels included in the recipe cost.') }}
                    </p>
                </div>
                <button type="button" @click="addPackaging()" class="btn btn-secondary btn-sm" style="border-radius: 8px; font-weight: 700;">
                    <i class="fa-solid fa-plus"></i> {{ __('Add Packaging') }}
                </button>
            </div>

            <div style="overflow-x: auto;" x-show="packagings.length > 0">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.02); text-align: left;">
                            <th style="padding: 0.5rem;">{{ __('Packaging Item') }}</th>
                            <th style="padding: 0.5rem; width: 140px;">{{ __('Quantity') }}</th>
                            <th style="padding: 0.5rem; width: 140px;">{{ __('Unit') }}</th>
                            <th style="padding: 0.5rem; width: 50px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(pkg, pIndex) in packagings" :key="pIndex">
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 0.5rem;">
                                    <select :name="`packagings[${pIndex}][ingredient_id]`" x-model="pkg.ingredient_id" @change="onPkgSelected(pkg)" class="form-select" required style="font-size: 0.8rem; border-radius: 6px; width: 100%;">
                                        <option value="">{{ __('Select Packaging Item...') }}</option>
                                        @foreach($ingredients->where('type', 'packaging') as $pkgItem)
                                            <option value="{{ $pkgItem->id }}" data-unit="{{ $pkgItem->unit_id }}">
                                                {{ $pkgItem->name }} ({{ $pkgItem->unit?->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td style="padding: 0.5rem;">
                                    <input type="number" step="0.0001" :name="`packagings[${pIndex}][quantity]`" x-model.number="pkg.quantity" required class="form-input" style="font-size: 0.8rem; border-radius: 6px; width: 100%;">
                                </td>
                                <td style="padding: 0.5rem;">
                                    <select :name="`packagings[${pIndex}][unit_id]`" x-model="pkg.unit_id" class="form-select" required style="font-size: 0.8rem; border-radius: 6px; width: 100%;">
                                        @foreach($units as $unit)
                                            <option value="{{ $unit->id }}">{{ $unit->code }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td style="padding: 0.5rem; text-align: center;">
                                    <button type="button" @click="removePackaging(pIndex)" class="btn btn-secondary btn-sm" style="color: #ef4444; border-radius: 6px;">
                                        <i class="fa-solid fa-times"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4. Preparation Instructions & HACCP CCP -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
            <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin: 0 0 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-kitchen-set" style="color: #6366f1;"></i>
                <span>{{ __('Preparation Steps & Food Safety (HACCP CCP)') }}</span>
            </h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.25rem; margin-bottom: 1.25rem;">
                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Culinary Preparation Process / Steps') }}
                    </label>
                    <textarea name="instructions" rows="5" class="form-input" style="width: 100%; border-radius: 10px; font-family: inherit;" placeholder="{{ __('e.g., 1. Cut beef into thin strips, sauté onions, sear over high heat...') }}">{{ old('instructions') }}</textarea>
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Critical Control Points (CCP) / HACCP Requirements') }}
                    </label>
                    <textarea name="critical_control_points" rows="5" class="form-input" style="width: 100%; border-radius: 10px; font-family: inherit;" placeholder="{{ __('e.g., CCP 1: Internal cooking temperature >= 74°C. CCP 2: Rapid chill to < 4°C within 90 minutes.') }}">{{ old('critical_control_points') }}</textarea>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem;">
                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Storage Conditions') }}
                    </label>
                    <input type="text" name="storage_conditions" value="{{ old('storage_conditions') }}" placeholder="{{ __('e.g., +2°C to +4°C in sealed gastronorm') }}" class="form-input" style="width: 100%; border-radius: 10px;">
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Shelf Life (Hours)') }}
                    </label>
                    <input type="number" name="shelf_life_hours" value="{{ old('shelf_life_hours') }}" placeholder="{{ __('e.g., 48') }}" class="form-input" style="width: 100%; border-radius: 10px;">
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Photo / Presentation Image') }}
                    </label>
                    <input type="file" name="image" accept="image/*" class="form-input" style="width: 100%; border-radius: 10px;">
                </div>
            </div>
        </div>

        <!-- Form Submit Bar -->
        <div style="display: flex; justify-content: flex-end; gap: 1rem; align-items: center; margin-bottom: 3rem;">
            <a href="{{ route('admin.inventory.recipes.index') }}" class="btn btn-secondary" style="border-radius: 12px; font-weight: 600;">
                {{ __('Cancel') }}
            </a>
            <button type="submit" class="btn btn-primary" style="border-radius: 12px; font-weight: 700; padding: 0.75rem 2rem;">
                <i class="fa-solid fa-check"></i> {{ __('Save & Calculate Cost') }}
            </button>
        </div>
    </form>
</div>

<script>
function recipeForm() {
    return {
        recipeType: '{{ old('type', 'dish') }}',
        items: [
            {
                type: 'ingredient',
                ingredient_id: '',
                sub_recipe_id: '',
                unit_id: '{{ $units->firstWhere('code', 'kg')?->id ?? $units->first()?->id }}',
                gross_weight: 1.0,
                prep_loss_percent: 0.0,
                net_weight: 1.0,
                cooking_loss_percent: 0.0,
                final_weight: 1.0,
                notes: '',
                is_optional: false
            }
        ],
        packagings: [],

        addItem() {
            this.items.push({
                type: 'ingredient',
                ingredient_id: '',
                sub_recipe_id: '',
                unit_id: '{{ $units->firstWhere('code', 'kg')?->id ?? $units->first()?->id }}',
                gross_weight: 1.0,
                prep_loss_percent: 0.0,
                net_weight: 1.0,
                cooking_loss_percent: 0.0,
                final_weight: 1.0,
                notes: '',
                is_optional: false
            });
        },

        removeItem(index) {
            this.items.splice(index, 1);
        },

        addPackaging() {
            this.packagings.push({
                ingredient_id: '',
                quantity: 1.0,
                unit_id: '{{ $units->firstWhere('code', 'piece')?->id ?? $units->first()?->id }}'
            });
        },

        removePackaging(index) {
            this.packagings.splice(index, 1);
        },

        recalculateRow(row) {
            const gross = parseFloat(row.gross_weight) || 0;
            const prepLoss = parseFloat(row.prep_loss_percent) || 0;
            const cookLoss = parseFloat(row.cooking_loss_percent) || 0;

            row.net_weight = parseFloat((gross * (1 - (prepLoss / 100))).toFixed(4));
            row.final_weight = parseFloat((row.net_weight * (1 - (cookLoss / 100))).toFixed(4));
        },

        recalculateFromNet(row) {
            const net = parseFloat(row.net_weight) || 0;
            const cookLoss = parseFloat(row.cooking_loss_percent) || 0;
            row.final_weight = parseFloat((net * (1 - (cookLoss / 100))).toFixed(4));
        },

        onIngredientSelected(row) {
            const selectEl = event.target;
            const selectedOpt = selectEl.options[selectEl.selectedIndex];
            if (selectedOpt && selectedOpt.dataset.unit) {
                row.unit_id = selectedOpt.dataset.unit;
            }
        },

        onSubRecipeSelected(row) {
            const selectEl = event.target;
            const selectedOpt = selectEl.options[selectEl.selectedIndex];
            if (selectedOpt && selectedOpt.dataset.unit) {
                row.unit_id = selectedOpt.dataset.unit;
            }
        },

        onPkgSelected(pkg) {
            const selectEl = event.target;
            const selectedOpt = selectEl.options[selectEl.selectedIndex];
            if (selectedOpt && selectedOpt.dataset.unit) {
                pkg.unit_id = selectedOpt.dataset.unit;
            }
        }
    };
}
</script>
@endsection

