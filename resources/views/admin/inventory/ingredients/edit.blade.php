@extends('layouts.app')

@section('title', __('Edit Ingredient') . ' - ' . $ingredient->name)

@section('content')
<div style="max-width: 960px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0;">
                {{ __('Edit Ingredient') }}: {{ $ingredient->name }}
            </h1>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0;">
                {{ __('Update specifications, stock thresholds, allergens, and costing parameters.') }}
            </p>
        </div>
        <a href="{{ route('admin.inventory.ingredients.index') }}" class="btn btn-secondary" style="border-radius: 12px; font-weight: 600;">
            &larr; {{ __('Back to Catalog') }}
        </a>
    </div>

    @if($errors->any())
        <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; color: #ef4444;">
            <div style="font-weight: 700; margin-bottom: 0.25rem;">{{ __('Please resolve the following validation errors:') }}</div>
            <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.88rem;">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.inventory.ingredients.update', $ingredient) }}" enctype="multipart/form-data" class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.5rem, 3vw, 2rem); box-shadow: var(--shadow-card);">
        @csrf
        @method('PUT')

        <!-- Section 1: Basic Information -->
        <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0 0 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
            1. {{ __('Basic Information') }}
        </h3>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
            <div>
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.4rem;">
                    {{ __('Ingredient Name') }} <span style="color: #ef4444;">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name', $ingredient->name) }}" required class="form-input" style="width: 100%; border-radius: 10px;">
            </div>

            <div>
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.4rem;">
                    {{ __('Item Type') }} <span style="color: #ef4444;">*</span>
                </label>
                <select name="type" required class="form-select" style="width: 100%; border-radius: 10px;">
                    <option value="raw_material" {{ old('type', $ingredient->type) === 'raw_material' ? 'selected' : '' }}>{{ __('Raw Material') }}</option>
                    <option value="ingredient" {{ old('type', $ingredient->type) === 'ingredient' ? 'selected' : '' }}>{{ __('Ingredient') }}</option>
                    <option value="semi_finished" {{ old('type', $ingredient->type) === 'semi_finished' ? 'selected' : '' }}>{{ __('Semi-Finished Product') }}</option>
                    <option value="finished" {{ old('type', $ingredient->type) === 'finished' ? 'selected' : '' }}>{{ __('Finished Product') }}</option>
                    <option value="packaging" {{ old('type', $ingredient->type) === 'packaging' ? 'selected' : '' }}>{{ __('Packaging Material') }}</option>
                    <option value="consumable" {{ old('type', $ingredient->type) === 'consumable' ? 'selected' : '' }}>{{ __('Consumable / Auxiliary') }}</option>
                </select>
            </div>

            <div>
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.4rem;">
                    {{ __('Category') }}
                </label>
                <select name="category_id" class="form-select" style="width: 100%; border-radius: 10px;">
                    <option value="">-- {{ __('Uncategorized') }} --</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ (string) old('category_id', $ingredient->category_id) === (string) $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.4rem;">
                    {{ __('Primary Unit of Measurement') }} <span style="color: #ef4444;">*</span>
                </label>
                <select name="unit_id" required class="form-select" style="width: 100%; border-radius: 10px;">
                    @foreach($units as $u)
                        <option value="{{ $u->id }}" {{ (string) old('unit_id', $ingredient->unit_id) === (string) $u->id ? 'selected' : '' }}>
                            {{ $u->name }} ({{ $u->code }}) - {{ $u->type }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.4rem;">
                    {{ __('Internal Code / SKU') }}
                </label>
                <input type="text" name="sku" value="{{ old('sku', $ingredient->sku) }}" class="form-input" style="width: 100%; border-radius: 10px;">
            </div>

            <div>
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.4rem;">
                    {{ __('Barcode (EAN/UPC)') }}
                </label>
                <input type="text" name="barcode" value="{{ old('barcode', $ingredient->barcode) }}" class="form-input" style="width: 100%; border-radius: 10px;">
            </div>
        </div>

        <!-- Section 2: Costing & Stock Levels -->
        <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 1.5rem 0 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
            2. {{ __('Costing & Reorder Thresholds') }}
        </h3>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
            <div>
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.4rem;">
                    {{ __('Current Cost per Unit') }}
                </label>
                <input type="number" step="0.0001" name="current_cost" value="{{ old('current_cost', $ingredient->current_cost) }}" class="form-input" style="width: 100%; border-radius: 10px;">
            </div>

            <div>
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.4rem;">
                    {{ __('Min Stock Threshold') }}
                </label>
                <input type="number" step="0.0001" name="min_stock" value="{{ old('min_stock', $ingredient->min_stock) }}" class="form-input" style="width: 100%; border-radius: 10px;">
            </div>

            <div>
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.4rem;">
                    {{ __('Reorder Point') }}
                </label>
                <input type="number" step="0.0001" name="reorder_point" value="{{ old('reorder_point', $ingredient->reorder_point) }}" class="form-input" style="width: 100%; border-radius: 10px;">
            </div>

            <div>
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.4rem;">
                    {{ __('Safety Stock') }}
                </label>
                <input type="number" step="0.0001" name="safety_stock" value="{{ old('safety_stock', $ingredient->safety_stock) }}" class="form-input" style="width: 100%; border-radius: 10px;">
            </div>
        </div>

        <!-- Section 3: Storage & HACCP Parameters -->
        <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 1.5rem 0 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
            3. {{ __('Storage & HACCP Specifications') }}
        </h3>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
            <div>
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.4rem;">
                    {{ __('Storage Conditions') }}
                </label>
                <select name="storage_conditions" class="form-select" style="width: 100%; border-radius: 10px;">
                    <option value="Ambient" {{ old('storage_conditions', $ingredient->storage_conditions) === 'Ambient' ? 'selected' : '' }}>{{ __('Ambient / Dry Room') }}</option>
                    <option value="Refrigerated" {{ old('storage_conditions', $ingredient->storage_conditions) === 'Refrigerated' ? 'selected' : '' }}>{{ __('Refrigerated (0°C to 4°C)') }}</option>
                    <option value="Frozen" {{ old('storage_conditions', $ingredient->storage_conditions) === 'Frozen' ? 'selected' : '' }}>{{ __('Frozen (-18°C or below)') }}</option>
                    <option value="Cool & Dry" {{ old('storage_conditions', $ingredient->storage_conditions) === 'Cool & Dry' ? 'selected' : '' }}>{{ __('Cool & Dry (10°C to 15°C)') }}</option>
                </select>
            </div>

            <div>
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.4rem;">
                    {{ __('Min Temp (°C)') }}
                </label>
                <input type="number" step="0.1" name="storage_temperature_min" value="{{ old('storage_temperature_min', $ingredient->storage_temperature_min) }}" class="form-input" style="width: 100%; border-radius: 10px;">
            </div>

            <div>
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.4rem;">
                    {{ __('Max Temp (°C)') }}
                </label>
                <input type="number" step="0.1" name="storage_temperature_max" value="{{ old('storage_temperature_max', $ingredient->storage_temperature_max) }}" class="form-input" style="width: 100%; border-radius: 10px;">
            </div>

            <div>
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.4rem;">
                    {{ __('Shelf Life (Days)') }}
                </label>
                <input type="number" name="shelf_life_days" value="{{ old('shelf_life_days', $ingredient->shelf_life_days) }}" class="form-input" style="width: 100%; border-radius: 10px;">
            </div>

            <div>
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.4rem;">
                    {{ __('Density (kg / Liter)') }}
                </label>
                <input type="number" step="0.0001" name="density_kg_per_l" value="{{ old('density_kg_per_l', $ingredient->density_kg_per_l) }}" class="form-input" style="width: 100%; border-radius: 10px;">
            </div>
        </div>

        <!-- Section 4: Allergens -->
        <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 1.5rem 0 0.75rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
            4. {{ __('Allergen Declaration') }}
        </h3>

        @php
            $currentAllergenIds = $ingredient->allergens->pluck('id')->all();
        @endphp

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.75rem; margin-bottom: 1.5rem;">
            @foreach($allergens as $allergen)
                <label style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 10px; cursor: pointer; background: var(--bg-main);">
                    <input type="checkbox" name="allergens[]" value="{{ $allergen->id }}" {{ in_array($allergen->id, old('allergens', $currentAllergenIds)) ? 'checked' : '' }}>
                    <span style="font-size: 1.1rem;">{{ $allergen->icon }}</span>
                    <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-main);">{{ $allergen->name }}</span>
                </label>
            @endforeach
        </div>

        <!-- Section 5: Photo & Status -->
        <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 1.5rem 0 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
            5. {{ __('Photo & Options') }}
        </h3>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
            <div>
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.4rem;">
                    {{ __('Ingredient Image') }}
                </label>
                @if($ingredient->image)
                    <div style="margin-bottom: 0.5rem;">
                        <img src="{{ $ingredient->image }}" alt="{{ $ingredient->name }}" style="max-height: 80px; border-radius: 8px;">
                    </div>
                @endif
                <input type="file" name="image" accept="image/*" class="form-input" style="width: 100%; border-radius: 10px;">
            </div>

            <div style="display: flex; align-items: center; gap: 0.5rem; padding-top: 1.5rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.9rem; font-weight: 700; color: var(--text-main);">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $ingredient->is_active) ? 'checked' : '' }}>
                    <span>{{ __('Active in catalog') }}</span>
                </label>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem; border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
            <a href="{{ route('admin.inventory.ingredients.index') }}" class="btn btn-secondary" style="border-radius: 12px; font-weight: 600;">
                {{ __('Cancel') }}
            </a>
            <button type="submit" class="btn btn-primary" style="border-radius: 12px; font-weight: 700; padding: 0.65rem 1.75rem;">
                <i class="fa-solid fa-check"></i> {{ __('Update Ingredient') }}
            </button>
        </div>
    </form>
</div>
@endsection

