@extends('layouts.app')

@section('title', __('Create Technical Card') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="max-width: 1100px; margin: 0 auto;" x-data="recipeForm()" x-cloak>
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

    <form method="POST" action="{{ route('admin.inventory.recipes.store') }}" enctype="multipart/form-data">
        @csrf

        <!-- 0. Recipe Target Mode (Menu Dish vs Semi-Finished) -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
            <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.75rem;">
                {{ __('What are you creating this recipe for?') }}
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                <label style="border: 2px solid; border-radius: 12px; padding: 1rem; cursor: pointer; display: flex; align-items: center; gap: 0.75rem; transition: all 0.2s ease;"
                       :style="recipeMode === 'dish' ? 'border-color: #3b82f6; background: rgba(59, 130, 246, 0.05);' : 'border-color: var(--border-color); background: transparent;'">
                    <input type="radio" name="recipe_mode_radio" value="dish" x-model="recipeMode" @change="onModeChanged('dish')" style="accent-color: #3b82f6;">
                    <div>
                        <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem; display: flex; align-items: center; gap: 0.4rem;">
                            <i class="fa-solid fa-utensils" style="color: #3b82f6;"></i>
                            {{ __('Menu Dish / Sales Item') }}
                        </div>
                        <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">
                            {{ __('Connect with an existing dish from your restaurant menu.') }}
                        </div>
                    </div>
                </label>

                <label style="border: 2px solid; border-radius: 12px; padding: 1rem; cursor: pointer; display: flex; align-items: center; gap: 0.75rem; transition: all 0.2s ease;"
                       :style="recipeMode === 'prep' ? 'border-color: #f59e0b; background: rgba(245, 158, 11, 0.05);' : 'border-color: var(--border-color); background: transparent;'">
                    <input type="radio" name="recipe_mode_radio" value="prep" x-model="recipeMode" @change="onModeChanged('prep')" style="accent-color: #f59e0b;">
                    <div>
                        <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem; display: flex; align-items: center; gap: 0.4rem;">
                            <i class="fa-solid fa-blender" style="color: #f59e0b;"></i>
                            {{ __('Semi-Finished / Sauce / Prep Item') }}
                        </div>
                        <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">
                            {{ __('Kitchen prep item stored in stock and used as component in dishes.') }}
                        </div>
                    </div>
                </label>
            </div>
        </div>

        <!-- 1. Menu Product Link & Auto-Fill Section (when in dish mode) -->
        <div x-show="recipeMode === 'dish'" class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
                <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-link" style="color: #10b981;"></i>
                    <span>{{ __('Select Menu Dish to Auto-fill') }}</span>
                </h2>
                <span style="font-size: 0.8rem; color: var(--text-muted);">
                    {{ __('Auto-populates name, category, and selling price') }}
                </span>
            </div>

            <!-- Active Selection Banner -->
            <template x-if="selectedProductId">
                <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.85rem;">
                        <span style="width: 40px; height: 40px; border-radius: 10px; background: #10b981; color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                            <i class="fa-solid fa-check"></i>
                        </span>
                        <div>
                            <div style="font-weight: 800; color: var(--text-main); font-size: 1.05rem;" x-text="getSelectedProductName()"></div>
                            <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.15rem;">
                                {{ __('Category') }}: <strong style="color: var(--text-main);" x-text="getSelectedProductCategory()"></strong> • 
                                {{ __('Selling Price') }}: <strong style="color: #10b981;" x-text="getSelectedProductPrice() + ' ' + currency"></strong>
                            </div>
                        </div>
                    </div>
                    <button type="button" @click="clearSelectedProduct()" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px; font-weight: 600;">
                        <i class="fa-solid fa-xmark"></i> {{ __('Remove Link / Change Dish') }}
                    </button>
                </div>
            </template>

            <!-- Search and Filter Bar -->
            <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Filter by Menu Category') }}
                    </label>
                    <select x-model="selectedCategoryFilter" @change="onCategoryFilterChange()" class="form-select" style="width: 100%; border-radius: 10px;">
                        <option value="">{{ __('All Menu Categories') }} ({{ $products->count() }})</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }} ({{ $products->where('category_id', $cat->id)->count() }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Search Dish by Name') }}
                    </label>
                    <div style="position: relative;">
                        <input type="text" x-model="productSearch" placeholder="{{ __('Type to filter dishes...') }}" class="form-input" style="width: 100%; border-radius: 10px; padding-left: 2.25rem;">
                        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.85rem;"></i>
                    </div>
                </div>
            </div>

            <!-- Dish Selector Dropdown -->
            <div>
                <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                    {{ __('Select Dish') }} <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 400;">({{ __('or choose from filtered list') }})</span>
                </label>
                <select name="product_id" x-model="selectedProductId" @change="onProductSelectChanged()" class="form-select" style="width: 100%; border-radius: 10px;">
                    <option value="">-- {{ __('Select a dish to connect...') }} --</option>
                    <template x-for="prod in filteredProducts" :key="prod.id">
                        <option :value="prod.id" x-text="`${prod.category_name ? '[' + prod.category_name + '] ' : ''}${prod.name} (${prod.price} ${currency})`"></option>
                    </template>
                </select>
                <div x-show="filteredProducts.length === 0" style="font-size: 0.8rem; color: #ef4444; margin-top: 0.35rem;">
                    {{ __('No dishes found matching the current search/category filter.') }}
                </div>
            </div>
        </div>

        <!-- 2. Basic Recipe Information Form -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
            <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin: 0 0 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-info-circle" style="color: #3b82f6;"></i>
                <span>{{ __('Recipe Specifications') }}</span>
            </h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem; margin-bottom: 1.25rem;">
                <!-- Recipe Name -->
                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Recipe / Dish Name') }} <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="name" x-model="recipeName" required placeholder="{{ __('e.g., Beef Stroganoff') }}" class="form-input" style="width: 100%; border-radius: 10px;">
                </div>

                <!-- Recipe Code -->
                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Recipe Code / SKU') }} <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="code" value="{{ old('code', 'REC-' . strtoupper(Str::random(6))) }}" required class="form-input" style="width: 100%; border-radius: 10px; font-family: monospace;">
                </div>

                <!-- Recipe Type -->
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

                <!-- Menu Dish Category -->
                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                        {{ __('Category') }}
                    </label>
                    <select name="category_id" x-model="categoryId" class="form-select" style="width: 100%; border-radius: 10px;">
                        <option value="">{{ __('Select Category') }}</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Produced Semi-finished Ingredient (when semi_finished/sauce/prep) -->
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
                        <input type="number" step="0.01" name="selling_price" x-model="sellingPrice" placeholder="0.00" class="form-input" style="width: 100%; border-radius: 8px;">
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

        <!-- 3. Recipe Bill of Materials (BOM) & Shrinkage -->
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
                <button type="button" @click="addItem()" class="btn btn-primary btn-sm" style="border-radius: 8px; font-weight: 700; display: flex; align-items: center; gap: 0.4rem;">
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
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); width: 105px;" title="{{ __('Gross weight before cleaning') }}">{{ __('Gross Quantity') }} <span style="color: #ef4444;">*</span></th>
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
                                    <input type="number" step="any" min="0.0001" :name="`items[${index}][gross_weight]`" x-model.number="row.gross_weight" @input="recalculateRow(row)" required class="form-input" style="font-size: 0.8rem; padding: 0.35rem; border-radius: 6px; width: 100%;">
                                    <input type="hidden" :name="`items[${index}][quantity]`" :value="row.gross_weight">
                                </td>

                                <td style="padding: 0.6rem 0.5rem;">
                                    <input type="number" step="0.01" :name="`items[${index}][prep_loss_percent]`" x-model.number="row.prep_loss_percent" @input="recalculateRow(row)" class="form-input" style="font-size: 0.8rem; padding: 0.35rem; border-radius: 6px; width: 100%;">
                                </td>

                                <td style="padding: 0.6rem 0.5rem;">
                                    <input type="number" step="any" :name="`items[${index}][net_weight]`" x-model.number="row.net_weight" @input="recalculateFromNet(row)" class="form-input" style="font-size: 0.8rem; padding: 0.35rem; border-radius: 6px; width: 100%;">
                                </td>

                                <td style="padding: 0.6rem 0.5rem;">
                                    <input type="number" step="0.01" :name="`items[${index}][cooking_loss_percent]`" x-model.number="row.cooking_loss_percent" @input="recalculateRow(row)" class="form-input" style="font-size: 0.8rem; padding: 0.35rem; border-radius: 6px; width: 100%;">
                                </td>

                                <td style="padding: 0.6rem 0.5rem;">
                                    <input type="number" step="any" :name="`items[${index}][final_weight]`" x-model.number="row.final_weight" readonly class="form-input" style="font-size: 0.8rem; padding: 0.35rem; border-radius: 6px; width: 100%; background: rgba(0,0,0,0.03);">
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

        <!-- 4. Packaging Materials (Delivery / Takeaway) -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem;">
                <div>
                    <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-box-open" style="color: #ec4899;"></i>
                        <span>{{ __('Packaging Materials (Takeaway / Delivery)') }}</span>
                    </h2>
                    <p style="color: var(--text-muted); font-size: 0.8rem; margin: 0.25rem 0 0;">
                        {{ __('Box, container, bag, or labels included in the recipe cost.') }}
                    </p>
                </div>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <button type="button" @click="openQuickPackagingModal()" class="btn btn-outline-primary btn-sm" style="border-radius: 8px; font-weight: 700;">
                        <i class="fa-solid fa-plus-circle"></i> {{ __('Create New Packaging Material') }}
                    </button>
                    <button type="button" @click="addPackaging()" class="btn btn-secondary btn-sm" style="border-radius: 8px; font-weight: 700;">
                        <i class="fa-solid fa-plus"></i> {{ __('Add Packaging') }}
                    </button>
                </div>
            </div>

            <!-- Help Notice when no packaging items -->
            <template x-if="packagingList.length === 0">
                <div style="background: rgba(236, 72, 153, 0.08); border: 1px dashed rgba(236, 72, 153, 0.35); border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                    <div style="font-size: 0.85rem; color: var(--text-main); display: flex; align-items: center; gap: 0.6rem;">
                        <i class="fa-solid fa-box-open" style="color: #ec4899; font-size: 1.1rem;"></i>
                        <span>{{ __('No packaging items registered yet. Click "Create New Packaging Material" to quickly add containers, boxes, or bags.') }}</span>
                    </div>
                    <button type="button" @click="openQuickPackagingModal()" class="btn btn-sm btn-primary" style="background: #ec4899; border-color: #ec4899; border-radius: 8px; font-weight: 700;">
                        <i class="fa-solid fa-plus"></i> {{ __('Create New Packaging Material') }}
                    </button>
                </div>
            </template>

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
                                        <optgroup :label="__('Packaging & Disposables')">
                                            <template x-for="pItem in packagingList" :key="pItem.id">
                                                <option :value="pItem.id" :data-unit="pItem.unit_id" x-text="`${pItem.name} (${pItem.unit_code})`"></option>
                                            </template>
                                        </optgroup>
                                        <optgroup :label="__('All Consumables & Raw Materials')">
                                            <template x-for="cItem in consumableList" :key="cItem.id">
                                                <option :value="cItem.id" :data-unit="cItem.unit_id" x-text="`${cItem.name} (${cItem.unit_code})`"></option>
                                            </template>
                                        </optgroup>
                                    </select>
                                </td>
                                <td style="padding: 0.5rem;">
                                    <input type="number" step="any" min="0.0001" :name="`packagings[${pIndex}][quantity]`" x-model.number="pkg.quantity" required class="form-input" style="font-size: 0.8rem; border-radius: 6px; width: 100%;">
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

            <div x-show="packagings.length === 0 && packagingList.length > 0" style="padding: 1.5rem; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                {{ __('No packaging added. Click "+ Add Packaging" if this dish requires delivery containers, bags, or boxes.') }}
            </div>
        </div>

        <!-- 5. Preparation Instructions & HACCP CCP -->
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

        <!-- Submit Button Area -->
        <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-bottom: 3rem;">
            <a href="{{ route('admin.inventory.recipes.index') }}" class="btn btn-secondary" style="border-radius: 12px; font-weight: 600; padding: 0.75rem 1.5rem;">
                {{ __('Cancel') }}
            </a>
            <button type="submit" class="btn btn-primary" style="border-radius: 12px; font-weight: 700; padding: 0.75rem 2rem; background: #f59e0b; border-color: #f59e0b;">
                <i class="fa-solid fa-check"></i> {{ __('Save & Calculate Cost') }}
            </button>
        </div>
    </form>

    <!-- Modal: Quick Create Packaging Material -->
    <div x-show="quickPkgModalOpen" x-cloak style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div class="card" style="background: var(--bg-card); border-radius: 16px; max-width: 480px; width: 100%; padding: 1.75rem; box-shadow: var(--shadow-modal);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-box-open" style="color: #ec4899;"></i>
                    <span>{{ __('Create New Packaging Material') }}</span>
                </h3>
                <button type="button" @click="quickPkgModalOpen = false" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">
                    &times;
                </button>
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                    {{ __('Material Name') }} <span style="color: #ef4444;">*</span>
                </label>
                <input type="text" x-model="quickPkgForm.name" placeholder="{{ __('e.g., Takeaway Box 500ml, Pizza Box 32cm') }}" class="form-input" style="width: 100%; border-radius: 8px;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                        {{ __('Unit') }} <span style="color: #ef4444;">*</span>
                    </label>
                    <select x-model="quickPkgForm.unit_id" class="form-select" style="width: 100%; border-radius: 8px;">
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}" {{ $unit->code === 'piece' ? 'selected' : '' }}>{{ $unit->name }} ({{ $unit->code }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                        {{ __('Purchase Price') }} ({{ Auth::user()->vendor->currency }})
                    </label>
                    <input type="number" step="0.01" min="0" x-model="quickPkgForm.purchase_price" placeholder="0.00" class="form-input" style="width: 100%; border-radius: 8px;">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" @click="quickPkgModalOpen = false" class="btn btn-secondary" style="border-radius: 8px;">
                    {{ __('Cancel') }}
                </button>
                <button type="button" @click="submitQuickPackaging()" :disabled="quickPkgLoading" class="btn btn-primary" style="background: #ec4899; border-color: #ec4899; border-radius: 8px; font-weight: 700;">
                    <span x-show="!quickPkgLoading"><i class="fa-solid fa-check"></i> {{ __('Save & Select') }}</span>
                    <span x-show="quickPkgLoading"><i class="fa-solid fa-spinner fa-spin"></i> {{ __('Saving...') }}</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function recipeForm() {
    const rawProducts = @json($productsData ?? []);
    const rawPackaging = @json($packagingData ?? []);
    const rawConsumables = @json($consumablesData ?? []);

    const preselectedId = '{{ old('product_id', $preselectedProduct?->id ?? '') }}';
    const initialMode = '{{ old('type') }}' && ['semi_finished', 'sauce', 'prep', 'bakery'].includes('{{ old('type') }}') ? 'prep' : 'dish';

    return {
        currency: '{{ Auth::user()->vendor->currency }}',
        recipeMode: initialMode,
        recipeType: '{{ old('type', 'dish') }}',

        // Products & Auto-fill
        products: rawProducts,
        filteredProducts: rawProducts,
        selectedProductId: preselectedId,
        selectedCategoryFilter: '',
        productSearch: '',

        // Form Fields
        recipeName: '{{ old('name', $preselectedProduct?->name ?? '') }}',
        categoryId: '{{ old('category_id', $preselectedProduct?->category_id ?? '') }}',
        sellingPrice: '{{ old('selling_price', $preselectedProduct?->price ?? '') }}',

        // BOM items
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

        // Packaging
        packagingList: rawPackaging,
        consumableList: rawConsumables,
        packagings: [],
        quickPkgModalOpen: false,
        quickPkgLoading: false,
        quickPkgForm: {
            name: '',
            unit_id: '{{ $units->firstWhere('code', 'piece')?->id ?? $units->first()?->id }}',
            purchase_price: ''
        },

        init() {
            if (this.selectedProductId) {
                const found = this.products.find(p => String(p.id) === String(this.selectedProductId));
                if (found) {
                    this.applyProduct(found, false);
                }
            }
            this.filterProducts();
        },

        onModeChanged(mode) {
            this.recipeMode = mode;
            if (mode === 'dish') {
                this.recipeType = 'dish';
            } else {
                this.recipeType = 'semi_finished';
                this.selectedProductId = '';
            }
        },

        onCategoryFilterChange() {
            this.filterProducts();
        },

        filterProducts() {
            let list = this.products;
            if (this.selectedCategoryFilter) {
                list = list.filter(p => String(p.category_id) === String(this.selectedCategoryFilter));
            }
            if (this.productSearch.trim()) {
                const q = this.productSearch.toLowerCase().trim();
                list = list.filter(p => p.name.toLowerCase().includes(q) || (p.category_name && p.category_name.toLowerCase().includes(q)));
            }
            this.filteredProducts = list;
        },

        onProductSelectChanged() {
            if (!this.selectedProductId) {
                return;
            }
            const found = this.products.find(p => String(p.id) === String(this.selectedProductId));
            if (found) {
                this.applyProduct(found, true);
            }
        },

        applyProduct(product, overwriteName = true) {
            if (overwriteName || !this.recipeName) {
                this.recipeName = product.name;
            }
            if (product.category_id) {
                this.categoryId = product.category_id;
            }
            if (product.price) {
                this.sellingPrice = product.price;
            }
            this.recipeType = 'dish';
            this.selectedProductId = product.id;
        },

        clearSelectedProduct() {
            this.selectedProductId = '';
        },

        getSelectedProductName() {
            const p = this.products.find(p => String(p.id) === String(this.selectedProductId));
            return p ? p.name : '';
        },

        getSelectedProductCategory() {
            const p = this.products.find(p => String(p.id) === String(this.selectedProductId));
            return p && p.category_name ? p.category_name : '{{ __('Uncategorized') }}';
        },

        getSelectedProductPrice() {
            const p = this.products.find(p => String(p.id) === String(this.selectedProductId));
            return p ? p.price.toFixed(2) : '0.00';
        },

        // BOM item methods
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

        // Packaging methods
        addPackaging() {
            const defaultId = this.packagingList.length > 0 ? this.packagingList[0].id : '';
            const defaultUnit = this.packagingList.length > 0 ? this.packagingList[0].unit_id : '{{ $units->firstWhere('code', 'piece')?->id ?? $units->first()?->id }}';

            this.packagings.push({
                ingredient_id: defaultId,
                quantity: 1.0,
                unit_id: defaultUnit
            });
        },

        removePackaging(index) {
            this.packagings.splice(index, 1);
        },

        onPkgSelected(pkg) {
            const selectEl = event.target;
            const selectedOpt = selectEl.options[selectEl.selectedIndex];
            if (selectedOpt && selectedOpt.dataset.unit) {
                pkg.unit_id = selectedOpt.dataset.unit;
            }
        },

        openQuickPackagingModal() {
            this.quickPkgForm.name = '';
            this.quickPkgForm.purchase_price = '';
            this.quickPkgModalOpen = true;
        },

        async submitQuickPackaging() {
            if (!this.quickPkgForm.name.trim()) {
                alert('{{ __('Please enter a name for the packaging material.') }}');
                return;
            }

            this.quickPkgLoading = true;
            try {
                const response = await fetch('{{ route('admin.inventory.recipes.quick-packaging') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(this.quickPkgForm)
                });

                const data = await response.json();
                if (data.success && data.ingredient) {
                    this.packagingList.unshift({
                        id: String(data.ingredient.id),
                        name: data.ingredient.name,
                        unit_id: String(data.ingredient.unit_id),
                        unit_code: data.ingredient.unit_code
                    });

                    // Automatically add to current recipe rows
                    this.packagings.push({
                        ingredient_id: String(data.ingredient.id),
                        quantity: 1.0,
                        unit_id: String(data.ingredient.unit_id)
                    });

                    this.quickPkgModalOpen = false;
                } else {
                    alert(data.message || '{{ __('Failed to create packaging material.') }}');
                }
            } catch (err) {
                alert('{{ __('Network error. Could not create packaging material.') }}');
            } finally {
                this.quickPkgLoading = false;
            }
        }
    };
}
</script>
@endsection
