@extends('layouts.app')

@section('title', __('Menu Builder') . ' - ' . $vendor->name)

@section('styles')
<style>
    .menu-dish-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.15rem 1.25rem;
        background: var(--input-bg);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        gap: 1.25rem;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        min-width: 0;
        box-sizing: border-box;
    }
    .menu-dish-row:hover {
        border-color: rgba(245, 158, 11, 0.35);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        transform: translateY(-1px);
    }
    .dish-thumb {
        width: 76px;
        height: 76px;
        object-fit: cover;
        border-radius: 14px;
        flex-shrink: 0;
        border: 1px solid var(--border-color);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .dish-content {
        flex: 1;
        min-width: 0;
    }
    .dish-pricing-col {
        text-align: right;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.45rem;
        flex-shrink: 0;
    }
    .modal-box-responsive {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        width: 100%;
        max-width: min(660px, 95vw);
        max-height: 90vh;
        overflow-y: auto;
        padding: 1.75rem 2rem;
        margin: auto;
        box-sizing: border-box;
        box-shadow: 0 25px 60px rgba(0,0,0,0.45);
    }
    @media (max-width: 768px) {
        .menu-dish-row {
            flex-direction: column;
            align-items: stretch;
            gap: 1rem;
            padding: 1rem;
        }
        .dish-main-mobile {
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
            width: 100%;
            min-width: 0;
        }
        .dish-pricing-col {
            align-items: stretch;
            text-align: left;
            border-top: 1px solid var(--border-color);
            padding-top: 0.85rem;
            width: 100%;
            gap: 0.75rem;
        }
        .dish-pricing-row-mobile {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .dish-actions-mobile {
            display: flex;
            gap: 0.5rem;
            width: 100%;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        .modal-box-responsive {
            padding: 1.25rem;
        }
    }

    /* Density Switcher Classes */
    .density-compact .menu-dish-row {
        padding: 0.55rem 0.85rem;
        gap: 0.75rem;
        border-radius: 10px;
    }
    .density-compact .dish-thumb {
        width: 44px;
        height: 44px;
        border-radius: 8px;
    }
    .density-compact .table-row-item td {
        padding: 0.35rem 0.6rem !important;
        font-size: 0.8rem !important;
    }
    .density-compact .table-dish-thumb {
        width: 32px;
        height: 32px;
        border-radius: 6px;
    }

    .density-comfortable .menu-dish-row {
        padding: 0.95rem 1.15rem;
        gap: 1rem;
        border-radius: 14px;
    }
    .density-comfortable .dish-thumb {
        width: 64px;
        height: 64px;
        border-radius: 12px;
    }
    .density-comfortable .table-row-item td {
        padding: 0.65rem 0.85rem !important;
        font-size: 0.86rem !important;
    }
    .density-comfortable .table-dish-thumb {
        width: 42px;
        height: 42px;
        border-radius: 8px;
    }

    .density-expanded .menu-dish-row {
        padding: 1.25rem 1.45rem;
        gap: 1.25rem;
        border-radius: 16px;
    }
    .density-expanded .dish-thumb {
        width: 78px;
        height: 78px;
        border-radius: 14px;
    }
    .density-expanded .table-row-item td {
        padding: 0.95rem 1.15rem !important;
        font-size: 0.92rem !important;
    }
    .density-expanded .table-dish-thumb {
        width: 52px;
        height: 52px;
        border-radius: 10px;
    }

    /* Floating Bulk Action Bar */
    .floating-bulk-bar {
        position: fixed;
        bottom: 1.75rem;
        left: 50%;
        transform: translateX(-50%);
        z-index: 1000;
        background: var(--bg-card);
        border: 2px solid var(--primary);
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.45), 0 0 25px var(--primary-glow);
        border-radius: 18px;
        padding: 0.75rem 1.25rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        max-width: min(850px, 94vw);
        flex-wrap: wrap;
        animation: floatUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes floatUp {
        from { opacity: 0; transform: translate(-50%, 20px); }
        to { opacity: 1; transform: translate(-50%, 0); }
    }

    /* High Density Table */
    .modern-menu-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        border-radius: 16px;
        overflow: hidden;
        border: 1px solid var(--border-color);
        background: var(--bg-card);
    }
    .modern-menu-table th {
        background: rgba(14, 20, 36, 0.9);
        color: var(--text-muted);
        font-size: 0.76rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 0.75rem 0.85rem;
        border-bottom: 1px solid var(--border-color);
        text-align: left;
        white-space: nowrap;
    }
    .modern-menu-table td {
        border-bottom: 1px solid var(--table-row-border);
        color: var(--text-main);
        vertical-align: middle;
        transition: background 0.15s ease;
    }
    .modern-menu-table tr:hover td {
        background: rgba(245, 158, 11, 0.04);
    }
    .modern-menu-table tr.selected-row td {
        background: rgba(245, 158, 11, 0.09) !important;
    }

    /* Inline editable click targets */
    .inline-editable-cell {
        cursor: pointer;
        padding: 0.2rem 0.4rem;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        transition: all 0.15s ease;
    }
    .inline-editable-cell:hover {
        background: rgba(255, 255, 255, 0.08);
        outline: 1px dashed rgba(245, 158, 11, 0.4);
    }

    /* Category organizer cards */
    .category-drag-card {
        background: var(--bg-card);
        border: 1.5px solid var(--border-color);
        border-radius: 14px;
        padding: 0.95rem 1.25rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        transition: all 0.2s ease;
        margin-bottom: 0.65rem;
    }
    .category-drag-card:hover {
        border-color: rgba(245, 158, 11, 0.4);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }
    .view-mode-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.45rem 0.85rem;
        border-radius: 10px;
        font-size: 0.84rem;
        font-weight: 700;
        cursor: pointer;
        background: var(--input-bg);
        color: var(--text-muted);
        border: 1px solid var(--border-color);
        transition: all 0.15s ease;
    }
    .view-mode-pill:hover {
        color: var(--text-main);
        border-color: rgba(245, 158, 11, 0.35);
    }
    .view-mode-pill.active {
        background: var(--primary);
        color: #000;
        border-color: var(--primary);
        box-shadow: 0 2px 8px var(--primary-glow);
    }
    .density-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.4rem 0.65rem;
        border-radius: 8px;
        font-size: 0.78rem;
        font-weight: 700;
        cursor: pointer;
        background: var(--input-bg);
        color: var(--text-muted);
        border: 1px solid var(--border-color);
        transition: all 0.15s ease;
    }
    .density-pill:hover {
        color: var(--text-main);
    }
    .density-pill.active {
        background: rgba(245, 158, 11, 0.18);
        color: var(--primary);
        border-color: rgba(245, 158, 11, 0.5);
    }
    .inline-edit-input {
        background: var(--input-bg);
        border: 1.5px solid var(--primary);
        color: var(--text-main);
        border-radius: 6px;
        padding: 0.2rem 0.45rem;
        font-size: 0.82rem;
        font-weight: 700;
        outline: none;
        box-shadow: 0 0 8px var(--primary-glow);
    }
    .menu-toast {
        position: fixed;
        top: 1.5rem;
        right: 1.5rem;
        z-index: 9999;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.85rem 1.25rem;
        border-radius: 12px;
        font-size: 0.88rem;
        font-weight: 700;
        box-shadow: 0 10px 30px rgba(0,0,0,0.4);
        animation: toastSlideIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes toastSlideIn {
        from { opacity: 0; transform: translateX(30px); }
        to { opacity: 1; transform: translateX(0); }
    }
</style>
@endsection

@section('content')
@php
    $supportedLangs = $vendor->getSupportedLanguages();
    $primaryLang = $supportedLangs[0] ?? ['code' => 'en', 'name' => 'English', 'flag' => '🇬🇧'];
    $secondaryLangs = array_slice($supportedLangs, 1);

    $allDishes = $categories->flatMap->products;
    $dishesJson = $allDishes->map(function($p) use ($vendor, $activeLocationId) {
        $avail = $p->isAvailableAtLocation($activeLocationId);
        $prodDailyOffer = $p->dailyOffers ? $p->dailyOffers->where('is_active', true)->first() : null;
        $isDaily = (bool) $prodDailyOffer || ($vendor->featured_dish_enabled && (int)$vendor->featured_product_id === (int)$p->id);

        return [
            'id' => (int) $p->id,
            'category_id' => (int) $p->category_id,
            'category_name' => (string) ($p->category?->name ?? ''),
            'name' => (string) $p->name,
            'price' => (float) $p->price,
            'discount_price' => $p->discount_price !== null ? (float) $p->discount_price : null,
            'is_available' => (bool) $avail,
            'is_featured' => (bool) $p->is_featured,
            'is_daily_offer' => (bool) $isDaily,
            'image' => (string) $p->image,
            'sort_order' => (int) ($p->sort_order ?? 0),
            'available_for_dine_in' => $p->available_for_dine_in !== false && $p->available_for_dine_in !== 0,
            'available_for_takeaway' => $p->available_for_takeaway !== false && $p->available_for_takeaway !== 0,
            'available_for_delivery' => $p->available_for_delivery !== false && $p->available_for_delivery !== 0,
            'calories' => $p->calories,
            'preparation_time_min' => $p->preparation_time_min,
            'variations_count' => (int) ($p->variations ? $p->variations->count() : 0),
            'allergens_count' => (int) ($p->allergens ? $p->allergens->count() : 0),
            'raw_json' => $p,
        ];
    })->values();

    $categoriesJson = $categories->map(function($c) {
        return [
            'id' => (int) $c->id,
            'name' => (string) $c->name,
            'description' => (string) ($c->description ?? ''),
            'sort_order' => (int) ($c->sort_order ?? 0),
            'products_count' => (int) $c->products->count(),
            'name_translations' => $c->name_translations ?? [],
            'raw_json' => $c,
        ];
    })->values();
@endphp

<div x-data="menuManagerApp()" :class="'density-' + density" id="menuManagerRoot">
    <!-- Toast Notifications -->
    <div x-show="toast.show" x-cloak class="menu-toast" :style="toast.type === 'error' ? 'background: #ef4444; color: #fff;' : 'background: #10b981; color: #fff;'">
        <i :class="toast.type === 'error' ? 'fa-solid fa-triangle-exclamation' : 'fa-solid fa-circle-check'"></i>
        <span x-text="toast.message"></span>
    </div>

    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <span style="width: 40px; height: 40px; border-radius: 12px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    <i class="fa-solid fa-utensils"></i>
                </span>
                <h1 style="font-size: 1.75rem; font-weight: 800; margin: 0; color: var(--text-main);">
                    {{ __('Menu Builder') }}
                </h1>
            </div>
            <p style="color: var(--text-muted); font-size: 0.88rem; margin-top: 0.35rem;">
                {{ __('Manage categories, dish offerings, pricing portions, allergens, and happy hour discount schedules.') }}
            </p>
        </div>
        <div style="display: flex; gap: 0.65rem; flex-wrap: wrap;">
            <a href="{{ route('admin.settings.index', ['tab' => 'daily_offers']) }}"
               class="btn btn-secondary"
               style="display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 700; font-size: 0.86rem; border-color: rgba(239, 68, 68, 0.35); background: rgba(239, 68, 68, 0.08); color: #ef4444; text-decoration: none;"
               title="{{ __('Manage daily offers carousel and special dish settings') }}">
                <i class="fa-solid fa-fire-flame-curved"></i>
                <span>{{ __('Daily Offers & Carousel') }}</span>
                <span class="badge" style="background: linear-gradient(135deg, #f59e0b, #ef4444); color: #fff; font-size: 0.7rem; font-weight: 800; padding: 0.15rem 0.5rem; border-radius: 999px;">
                    {{ $vendor->dailyOffers ? $vendor->dailyOffers->where('is_active', true)->count() : 0 }}
                </span>
            </a>
            <button class="btn btn-secondary" onclick="document.getElementById('newCategoryModal').style.display='flex'">
                <i class="fa-solid fa-folder-plus" style="color: var(--primary);"></i> {{ __('New Category') }}
            </button>
            <button class="btn btn-primary" onclick="openNewProductModal()">
                <i class="fa-solid fa-plus"></i> {{ __('Add New Dish') }}
            </button>
        </div>
    </div>

    @if($locations->count() > 0)
        <!-- Branch Menu Scope Bar -->
        <div style="background: var(--bg-card); border: 1.5px solid var(--border-color); border-radius: 16px; padding: 0.95rem 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; box-shadow: 0 4px 16px rgba(0,0,0,0.06);">
            <div style="display: flex; align-items: center; gap: 0.85rem;">
                <span style="width: 38px; height: 38px; border-radius: 10px; background: rgba(99, 102, 241, 0.15); color: #6366f1; display: inline-flex; align-items: center; justify-content: center; font-size: 1.15rem;">
                    <i class="fa-solid fa-store"></i>
                </span>
                <div>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <strong style="font-size: 0.95rem; color: var(--text-main);">{{ __('Branch Menu Availability') }}</strong>
                        <span class="badge badge-indigo" style="font-size: 0.72rem;">{{ $activeLocation?->name ?? __('Default Branch') }}</span>
                    </div>
                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.15rem;">
                        {{ __('The «In Stock / Out of Stock» toggles apply to the selected branch:') }}
                    </div>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-muted); white-space: nowrap;">
                    <i class="fa-solid fa-location-dot" style="color: var(--primary);"></i> {{ __('Branch:') }}
                </label>
                <form method="GET" action="{{ route('admin.menu.index') }}" id="branchScopeFilterForm" style="display: flex; align-items: center; margin: 0;">
                    <select name="location_id" onchange="document.getElementById('branchScopeFilterForm').submit()" class="form-select" style="min-width: 200px; font-weight: 700; padding: 0.45rem 0.75rem; border-radius: 10px; border-color: rgba(99, 102, 241, 0.4); font-size: 0.84rem;">
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" {{ (int)$activeLocationId === (int)$loc->id ? 'selected' : '' }}>
                                📍 {{ $loc->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>
    @endif

    <!-- Advanced Menu Management Toolbar Card -->
    <div class="card" style="margin-bottom: 1.5rem; padding: 1.15rem 1.35rem;">
        <!-- Top row: View Switcher and Density Toggles -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.85rem; margin-bottom: 0.85rem;">
            <!-- View Mode Switcher -->
            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                <span style="font-size: 0.8rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">
                    <i class="fa-solid fa-layer-group" style="color: var(--primary);"></i> {{ __('View:') }}
                </span>
                <button type="button" class="view-mode-pill" :class="{ 'active': viewMode === 'grouped' }" @click="viewMode = 'grouped'">
                    <i class="fa-solid fa-folder-tree"></i> {{ __('Categorized') }}
                </button>
                <button type="button" class="view-mode-pill" :class="{ 'active': viewMode === 'table' }" @click="viewMode = 'table'">
                    <i class="fa-solid fa-table-list"></i> {{ __('Compact Table') }}
                    <span class="badge" style="font-size: 0.68rem; padding: 0.1rem 0.4rem; background: rgba(255,255,255,0.2);" x-text="filteredDishes().length"></span>
                </button>
                <button type="button" class="view-mode-pill" :class="{ 'active': viewMode === 'categories' }" @click="viewMode = 'categories'">
                    <i class="fa-solid fa-folder-open"></i> {{ __('Category Organizer') }}
                    <span class="badge" style="font-size: 0.68rem; padding: 0.1rem 0.4rem; background: rgba(255,255,255,0.2);" x-text="categories.length"></span>
                </button>
            </div>

            <!-- Density Switcher -->
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="font-size: 0.8rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">
                    <i class="fa-solid fa-bars-staggered"></i> {{ __('Density:') }}
                </span>
                <div style="display: flex; gap: 0.25rem; background: var(--input-bg); padding: 0.2rem; border-radius: 10px; border: 1px solid var(--border-color);">
                    <button type="button" class="density-pill" :class="{ 'active': density === 'compact' }" @click="density = 'compact'" title="{{ __('High Density (Compact)') }}">
                        <i class="fa-solid fa-compress"></i> {{ __('Compact') }}
                    </button>
                    <button type="button" class="density-pill" :class="{ 'active': density === 'comfortable' }" @click="density = 'comfortable'" title="{{ __('Standard Density (Comfortable)') }}">
                        <i class="fa-solid fa-table-cells-large"></i> {{ __('Comfortable') }}
                    </button>
                    <button type="button" class="density-pill" :class="{ 'active': density === 'expanded' }" @click="density = 'expanded'" title="{{ __('Spacious Density (Expanded)') }}">
                        <i class="fa-solid fa-expand"></i> {{ __('Expanded') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Bottom row: Live Filters & Search Bar (Visible in Grouped & Table views) -->
        <div x-show="viewMode !== 'categories'" style="display: flex; align-items: center; flex-wrap: wrap; gap: 0.65rem;">
            <!-- Search Input -->
            <div style="flex: 2; min-width: 220px; position: relative;">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.85rem;"></i>
                <input type="text"
                       x-model.debounce.200ms="searchQuery"
                       placeholder="{{ __('Search dishes by name, description, tags...') }}"
                       class="form-input"
                       style="padding-left: 2.25rem; padding-right: 2rem; font-size: 0.85rem;">
                <button type="button" x-show="searchQuery" @click="searchQuery = ''" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 0;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Category Selector Filter -->
            <div style="flex: 1; min-width: 160px;">
                <select x-model="filterCategoryId" class="form-select" style="font-size: 0.85rem;">
                    <option value="">{{ __('All Categories') }}</option>
                    <template x-for="c in categories" :key="c.id">
                        <option :value="c.id" x-text="c.name + ' (' + c.products_count + ')'"></option>
                    </template>
                </select>
            </div>

            <!-- Stock Status Filter -->
            <div style="min-width: 140px;">
                <select x-model="stockFilter" class="form-select" style="font-size: 0.85rem;">
                    <option value="all">{{ __('All Stock') }}</option>
                    <option value="in_stock">{{ __('In Stock Only') }}</option>
                    <option value="out_of_stock">{{ __('Out of Stock Only') }}</option>
                </select>
            </div>

            <!-- Channel Filter -->
            <div style="min-width: 135px;">
                <select x-model="channelFilter" class="form-select" style="font-size: 0.85rem;">
                    <option value="all">{{ __('All Channels') }}</option>
                    <option value="dine_in">🍽️ {{ __('Dine-in') }}</option>
                    <option value="takeaway">🥡 {{ __('Takeaway') }}</option>
                    <option value="delivery">🛵 {{ __('Delivery') }}</option>
                </select>
            </div>

            <!-- Price Range Min / Max -->
            <div style="display: flex; align-items: center; gap: 0.35rem; min-width: 170px;">
                <input type="number" x-model.debounce.300ms="minPrice" placeholder="{{ __('Min Price') }}" class="form-input" style="padding: 0.45rem 0.55rem; font-size: 0.82rem; width: 85px;">
                <span style="color: var(--text-muted);">-</span>
                <input type="number" x-model.debounce.300ms="maxPrice" placeholder="{{ __('Max Price') }}" class="form-input" style="padding: 0.45rem 0.55rem; font-size: 0.82rem; width: 85px;">
            </div>

            <!-- Reset Button -->
            <button type="button"
                    x-show="searchQuery || filterCategoryId || stockFilter !== 'all' || channelFilter !== 'all' || minPrice || maxPrice"
                    @click="resetFilters()"
                    class="btn btn-secondary"
                    style="padding: 0.45rem 0.75rem; font-size: 0.82rem;"
                    title="{{ __('Reset all filters') }}">
                <i class="fa-solid fa-rotate-left"></i> {{ __('Reset') }}
            </button>

            <!-- Showing Counter -->
            <div style="margin-left: auto; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); white-space: nowrap;">
                <span>{{ __('Showing') }}:</span>
                <span style="color: var(--primary);" x-text="filteredDishes().length"></span>
                <span>/</span>
                <span x-text="allDishes.length"></span>
            </div>
        </div>
    </div>

    @if($categories->count() == 0)
        <div class="card" style="text-align: center; padding: 4rem 2rem;">
            <div style="width: 64px; height: 64px; border-radius: 20px; background: rgba(245, 158, 11, 0.15); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 1.25rem;">
                <i class="fa-solid fa-utensils"></i>
            </div>
            <h2 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem; color: var(--text-main);">{{ __('Your menu is empty') }}</h2>
            <p style="color: var(--text-muted); margin-bottom: 1.75rem; max-width: 440px; margin-left: auto; margin-right: auto; line-height: 1.5;">
                {{ __('Start creating categories and dishes, or use our AI Menu Import tool to digitize existing paper/PDF menus in seconds.') }}
            </p>
            <div style="display: flex; justify-content: center; gap: 0.75rem; flex-wrap: wrap;">
                <a href="{{ route('admin.ai.import') }}" class="btn btn-primary">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('AI Menu Import') }}
                </a>
                <button class="btn btn-secondary" onclick="document.getElementById('newCategoryModal').style.display='flex'">
                    <i class="fa-solid fa-plus"></i> {{ __('Create Category') }}
                </button>
            </div>
        </div>
    @else
        <!-- View 1: Categorized View -->
        <div x-show="viewMode === 'grouped'">
    @foreach($categories as $category)
        <div class="card" style="margin-bottom: 2rem;" x-show="!filterCategoryId || filterCategoryId == {{ $category->id }}">
            <!-- Category Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.85rem; flex-wrap: wrap; gap: 0.75rem;">
                <div style="min-width: 0; flex: 1; display: flex; align-items: center; gap: 0.75rem;">
                    <!-- Category Select All Checkbox -->
                    <div style="display: flex; align-items: center;" title="{{ __('Select all dishes in this category') }}">
                        <input type="checkbox"
                               :checked="isCategorySelected({{ $category->id }})"
                               @change="toggleSelectCategory({{ $category->id }})"
                               style="width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer;">
                    </div>
                    <div style="min-width: 0; flex: 1;">
                        <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
                            <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0;">
                                {{ $category->name }}
                            </h2>
                            @if($category->getTranslatedName('hy') || $category->getTranslatedName('ru'))
                                <span style="font-size: 0.78rem; font-weight: 500; color: var(--text-muted); background: var(--input-bg); border: 1px solid var(--border-color); padding: 0.15rem 0.5rem; border-radius: 6px;">
                                    @if($category->getTranslatedName('hy')) HY: {{ $category->getTranslatedName('hy') }} @endif
                                    @if($category->getTranslatedName('hy') && $category->getTranslatedName('ru')) | @endif
                                    @if($category->getTranslatedName('ru')) RU: {{ $category->getTranslatedName('ru') }} @endif
                                </span>
                            @endif
                            <span class="badge badge-indigo" style="font-size: 0.72rem; padding: 0.2rem 0.55rem;">
                                {{ $category->products->count() }} {{ __('dishes') }}
                            </span>
                        </div>
                        @if($category->description)
                            <p style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.35rem;" class="break-word">{{ $category->description }}</p>
                        @endif
                    </div>
                </div>

                <div style="display: flex; gap: 0.45rem; align-items: center; flex-shrink: 0;">
                    <!-- Duplicate Category -->
                    <button type="button"
                            class="btn btn-secondary"
                            style="padding: 0.35rem 0.65rem; font-size: 0.78rem; color: #6366f1; border-color: rgba(99, 102, 241, 0.4);"
                            @click="duplicateCategory({ id: {{ $category->id }}, name: '{{ addslashes($category->name) }}' })"
                            title="{{ __('Duplicate category and all dishes') }}">
                        <i class="fa-solid fa-copy"></i> {{ __('Duplicate') }}
                    </button>
                    <!-- Edit Category -->
                    <button type="button" class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.78rem;" onclick="editCategory({{ json_encode($category) }})">
                        <i class="fa-solid fa-pen"></i> {{ __('Edit') }}
                    </button>
                    <!-- Safe Delete Category -->
                    <button type="button"
                            class="btn btn-danger"
                            style="padding: 0.35rem 0.65rem; font-size: 0.78rem;"
                            @click="openSafeDeleteCategory({ id: {{ $category->id }}, name: '{{ addslashes($category->name) }}', products_count: {{ $category->products->count() }} })"
                            title="{{ __('Delete Category Safely') }}">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </div>

            <!-- Dishes List -->
            <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                @forelse($category->products as $product)
                    <div class="menu-dish-row"
                         x-show="matchesFilter({{ $product->id }})"
                         :class="{ 'selected-row': selectedDishIds.includes({{ $product->id }}) }">
                        <!-- Product Selection Checkbox -->
                        <div style="display: flex; align-items: center; padding-right: 0.25rem;">
                            <input type="checkbox"
                                   :value="{{ $product->id }}"
                                   x-model="selectedDishIds"
                                   style="width: 17px; height: 17px; accent-color: var(--primary); cursor: pointer;">
                        </div>

                        <!-- Left/Desktop Content: Thumbnail + Info -->
                        <div class="dish-main-mobile" style="flex: 1; min-width: 0; display: flex; align-items: flex-start; gap: 1rem;">
                            <img src="{{ $product->image }}" onerror="this.onerror=null;this.src='{{ asset('images/default-dish.png') }}';" class="dish-thumb" alt="{{ $product->name }}">
                            
                            <div class="dish-content">
                                <div style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap;">
                                    <!-- Inline Editable Name -->
                                    <div class="inline-editable-cell"
                                         @click="startInlineEdit({{ $product->id }}, 'name', '{{ addslashes($product->name) }}')"
                                         title="{{ __('Click to edit dish name') }}">
                                        <template x-if="editingDishId === {{ $product->id }} && editingField === 'name'">
                                            <div style="display: inline-flex; align-items: center; gap: 0.25rem;" @click.stop>
                                                <input type="text"
                                                       x-model="editingValue"
                                                       class="inline-edit-input"
                                                       style="width: 180px;"
                                                       @keydown.enter="saveInlineEdit({{ $product->id }})"
                                                       @keydown.escape="cancelInlineEdit()">
                                                <button type="button" class="btn btn-primary" style="padding: 0.15rem 0.35rem; font-size: 0.72rem;" @click="saveInlineEdit({{ $product->id }})"><i class="fa-solid fa-check"></i></button>
                                                <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.35rem; font-size: 0.72rem;" @click="cancelInlineEdit()"><i class="fa-solid fa-xmark"></i></button>
                                            </div>
                                        </template>
                                        <template x-if="!(editingDishId === {{ $product->id }} && editingField === 'name')">
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem;">
                                                <strong style="font-size: 1.05rem; color: var(--text-main); font-family: 'Outfit'; word-break: break-word;"
                                                        x-text="getDishName({{ $product->id }}, '{{ addslashes($product->name) }}')">
                                                    {{ $product->name }}
                                                </strong>
                                                <i class="fa-solid fa-pen" style="font-size: 0.65rem; color: var(--text-muted); opacity: 0.4;"></i>
                                            </span>
                                        </template>
                                    </div>
                                    
                                    @php
                                        $prodDailyOffer = $product->dailyOffers ? $product->dailyOffers->where('is_active', true)->first() : null;
                                        $isProductDailyOffer = (bool) $prodDailyOffer || ($vendor->featured_dish_enabled && $vendor->featured_product_id == $product->id);
                                    @endphp
                                    @if($vendor->featured_dish_enabled && $isProductDailyOffer)
                                        <span class="badge" style="background: linear-gradient(135deg, #f59e0b, #ef4444); color: #fff; font-size: 0.7rem; font-weight: 800; display: inline-flex; align-items: center; gap: 0.35rem;">
                                            <i class="fa-solid fa-fire-flame-curved"></i> {{ ($prodDailyOffer?->badge) ?: ($vendor->featured_dish_badge ?: __('SPECIAL OF THE DAY')) }}
                                        </span>
                                    @endif
                                    
                                    @if($product->is_featured)
                                        <span class="badge badge-amber" style="font-size: 0.7rem; font-weight: 700;">★ {{ __('FEATURED') }}</span>
                                    @endif

                                    @if(!empty($product->dietary_tags))
                                        @foreach($product->dietary_tags as $tag)
                                            <span class="badge badge-emerald" style="font-size: 0.68rem; text-transform: uppercase;">{{ str_replace('_', ' ', $tag) }}</span>
                                        @endforeach
                                    @endif

                                    @if($product->available_start_time || $product->available_end_time || !empty($product->available_days))
                                        <span class="badge badge-amber" style="font-size: 0.68rem;">
                                            <i class="fa-regular fa-clock"></i> {{ $product->getAvailabilityScheduleSummary(app()->getLocale()) }}
                                        </span>
                                    @endif

                                    @if(!$product->available_for_dine_in || !$product->available_for_takeaway || !$product->available_for_delivery)
                                        <span class="badge badge-indigo" style="font-size: 0.68rem;">
                                            @if($product->available_for_dine_in) 🍽️ {{ __('Dine-in') }} @endif
                                            @if($product->available_for_takeaway) 🥡 {{ __('Takeaway') }} @endif
                                            @if($product->available_for_delivery) 🛵 {{ __('Delivery') }} @endif
                                        </span>
                                    @endif

                                    @php
                                        $locUnavailableCount = 0;
                                        foreach($locations as $loc) {
                                            if (!$product->isAvailableAtLocation($loc->id)) {
                                                $locUnavailableCount++;
                                            }
                                        }
                                    @endphp
                                    @if($locUnavailableCount > 0 && $locUnavailableCount < $locations->count())
                                        <span class="badge badge-rose" style="font-size: 0.68rem;">
                                            <i class="fa-solid fa-store-slash"></i> {{ __('Disabled in :count branches', ['count' => $locUnavailableCount]) }}
                                        </span>
                                    @endif
                                </div>

                                @if($product->description)
                                    <p style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.35rem; line-height: 1.4; word-break: break-word;">{{ $product->description }}</p>
                                @endif

                                <!-- Variations & Portions Chips -->
                                @if($product->variations && $product->variations->count() > 0)
                                    @php
                                        $hasCustomVariations = $product->variations->count() > 1 || 
                                            ($product->variations->count() === 1 && !in_array($product->variations->first()->name, ['Standard', 'Standard Portion']));
                                    @endphp
                                    @if($hasCustomVariations)
                                        <div style="margin-top: 0.65rem; padding: 0.5rem 0.75rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; width: 100%; box-sizing: border-box;">
                                            <div style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem; display: flex; align-items: center; gap: 0.35rem;">
                                                <i class="fa-solid fa-sliders" style="color: var(--primary);"></i> {{ __('Portions & Variations') }} ({{ $product->variations->count() }}):
                                            </div>
                                            <div style="display: flex; flex-wrap: wrap; gap: 0.35rem;">
                                                @foreach($product->variations as $var)
                                                    <div style="display: inline-flex; align-items: center; gap: 0.35rem; background: var(--input-bg); border: 1px solid {{ $var->is_default ? 'var(--primary)' : 'var(--border-color)' }}; padding: 0.2rem 0.55rem; border-radius: 8px; font-size: 0.75rem; white-space: nowrap;">
                                                        @if($var->is_default)
                                                            <i class="fa-solid fa-circle-check" style="color: var(--primary); font-size: 0.75rem;" title="{{ __('Default Portion') }}"></i>
                                                        @else
                                                            <i class="fa-regular fa-circle" style="color: var(--text-muted); font-size: 0.7rem;"></i>
                                                        @endif
                                                        <span style="font-weight: 700; color: var(--text-main);">{{ $var->name }}</span>
                                                        @php
                                                            $extraTrans = array_filter([
                                                                $var->name_translations['hy'] ?? null,
                                                                $var->name_translations['ru'] ?? null
                                                            ], fn($t) => !empty($t) && $t !== $var->name);
                                                        @endphp
                                                        @if(!empty($extraTrans))
                                                            <span style="color: var(--text-muted); font-size: 0.7rem;">({{ implode(' • ', $extraTrans) }})</span>
                                                        @endif
                                                        <span style="font-family: 'Outfit'; font-weight: 800; color: var(--primary);">
                                                            {{ number_format($var->price) }} {{ $vendor->currency }}
                                                        </span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                @endif

                                <div style="display: flex; gap: 0.85rem; align-items: center; margin-top: 0.5rem; font-size: 0.75rem; color: var(--text-muted); flex-wrap: wrap;">
                                    @if($product->calories)
                                        <span><i class="fa-solid fa-fire" style="color: var(--primary);"></i> {{ $product->calories }} {{ __('kcal') }}</span>
                                    @endif
                                    @if($product->preparation_time_min)
                                        <span><i class="fa-solid fa-clock"></i> {{ $product->preparation_time_min }} {{ __('mins') }}</span>
                                    @endif
                                    @if($product->allergens->count() > 0)
                                        <span><i class="fa-solid fa-triangle-exclamation" style="color: #ef4444;"></i> {{ __('Allergens:') }} {{ $product->allergens->pluck('icon')->join(' ') }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Right / Bottom Pricing & Actions -->
                        <div class="dish-pricing-col">
                            <div class="dish-pricing-row-mobile">
                                <!-- Inline Editable Price -->
                                <div class="inline-editable-cell"
                                     @click="startInlineEdit({{ $product->id }}, 'price', {{ (float)$product->price }})"
                                     title="{{ __('Click to edit price') }}">
                                    <template x-if="editingDishId === {{ $product->id }} && editingField === 'price'">
                                        <div style="display: inline-flex; align-items: center; gap: 0.25rem;" @click.stop>
                                            <input type="number"
                                                   x-model="editingValue"
                                                   class="inline-edit-input"
                                                   style="width: 90px;"
                                                   @keydown.enter="saveInlineEdit({{ $product->id }})"
                                                   @keydown.escape="cancelInlineEdit()">
                                            <button type="button" class="btn btn-primary" style="padding: 0.15rem 0.35rem; font-size: 0.72rem;" @click="saveInlineEdit({{ $product->id }})"><i class="fa-solid fa-check"></i></button>
                                            <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.35rem; font-size: 0.72rem;" @click="cancelInlineEdit()"><i class="fa-solid fa-xmark"></i></button>
                                        </div>
                                    </template>
                                    <template x-if="!(editingDishId === {{ $product->id }} && editingField === 'price')">
                                        <div style="font-size: 1.25rem; font-weight: 800; color: var(--primary); font-family: 'Outfit'; line-height: 1.2; display: flex; align-items: baseline; gap: 0.35rem;">
                                            @if($product->isDiscountActive())
                                                <div style="display: flex; align-items: baseline; gap: 0.45rem;">
                                                    <span style="color: #ef4444;" x-text="getDishPrice({{ $product->id }}, '{{ number_format($product->discount_price) }} {{ $vendor->currency }}')">
                                                        {{ number_format($product->discount_price) }} {{ $vendor->currency }}
                                                    </span>
                                                    <del style="color: var(--text-muted); font-size: 0.85rem; font-weight: 500;">{{ number_format($product->price) }}</del>
                                                </div>
                                            @elseif($product->discount_price)
                                                <div>
                                                    <span x-text="getDishPrice({{ $product->id }}, '{{ number_format($product->price) }} {{ $vendor->currency }}')">
                                                        {{ number_format($product->price) }} {{ $vendor->currency }}
                                                    </span>
                                                    <div style="font-size: 0.72rem; color: var(--text-muted); font-weight: 600;">
                                                        ({{ __('Discount:') }} {{ number_format($product->discount_price) }} {{ $vendor->currency }})
                                                    </div>
                                                </div>
                                            @elseif($product->variations->count() > 1)
                                                <span x-text="getDishPrice({{ $product->id }}, '{{ number_format($product->variations->min('price')) }} - {{ number_format($product->variations->max('price')) }} {{ $vendor->currency }}')">
                                                    {{ number_format($product->variations->min('price')) }} - {{ number_format($product->variations->max('price')) }} {{ $vendor->currency }}
                                                </span>
                                            @else
                                                <span x-text="getDishPrice({{ $product->id }}, '{{ number_format($product->price) }} {{ $vendor->currency }}')">
                                                    {{ number_format($product->price) }} {{ $vendor->currency }}
                                                </span>
                                            @endif
                                            <i class="fa-solid fa-pen" style="font-size: 0.65rem; color: var(--text-muted); opacity: 0.4;"></i>
                                        </div>
                                    </template>
                                </div>

                                @if($product->isDiscountActive())
                                    <div style="display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap; justify-content: flex-end;">
                                        <span class="badge badge-rose" style="font-size: 0.7rem;">
                                            <i class="fa-solid fa-tag"></i> -{{ $product->getDiscountPercentage() }}% {{ __('Off') }}
                                        </span>
                                        <span class="badge badge-emerald" style="font-size: 0.7rem;">
                                            <i class="fa-regular fa-clock"></i> {{ $product->getDiscountScheduleSummary(app()->getLocale()) }}
                                        </span>
                                    </div>
                                @elseif($product->discount_price)
                                    <div style="font-size: 0.7rem; color: var(--text-muted);">
                                        <i class="fa-regular fa-clock"></i> {{ $product->getDiscountScheduleSummary(app()->getLocale()) }} <span style="opacity: 0.7;">({{ __('off hours') }})</span>
                                    </div>
                                @endif
                            </div>

                            <div class="dish-actions-mobile">
                                <form action="{{ route('admin.menu.products.daily_offer.toggle', $product->id) }}" method="POST" style="margin: 0;">
                                    @csrf
                                    <button type="submit"
                                            class="btn btn-secondary"
                                            style="padding: 0.35rem 0.65rem; font-size: 0.78rem; {{ $isProductDailyOffer ? 'border-color: rgba(239, 68, 68, 0.4); background: rgba(239, 68, 68, 0.08); color: #ef4444; font-weight: 700;' : 'color: var(--text-muted);' }}"
                                            title="{{ $isProductDailyOffer ? __('Remove from Daily Offers') : __('Add to Daily Offers') }}">
                                        <i class="fa-solid fa-fire-flame-curved" style="{{ $isProductDailyOffer ? 'color: #ef4444;' : 'opacity: 0.4;' }}"></i>
                                        <span>{{ $isProductDailyOffer ? __('Daily Offer') : __('+ Daily Offer') }}</span>
                                    </button>
                                </form>

                                <button type="button" class="btn btn-secondary" style="padding: 0.35rem 0.7rem; font-size: 0.78rem;" onclick="editProduct({{ json_encode($product->load(['allergens', 'variations', 'overrides', 'dailyOffers'])) }})">
                                    <i class="fa-solid fa-pen"></i> {{ __('Edit') }}
                                </button>

                                @php
                                    $isInStockCurrentBranch = $product->isAvailableAtLocation($activeLocationId);
                                @endphp
                                <form action="{{ route('admin.menu.products.toggle', $product->id) }}" method="POST" style="margin: 0;">
                                    @csrf
                                    <input type="hidden" name="location_id" value="{{ $activeLocationId }}">
                                    <button type="submit" class="btn btn-secondary" style="padding: 0.35rem 0.7rem; font-size: 0.78rem;" title="{{ __('Toggle stock status for :name', ['name' => $activeLocation?->name]) }}">
                                        @if($isInStockCurrentBranch)
                                            <span style="color: #10b981;"><i class="fa-solid fa-toggle-on"></i> {{ __('In Stock') }}</span>
                                        @else
                                            <span style="color: #ef4444;"><i class="fa-solid fa-toggle-off"></i> {{ __('Out of Stock') }}</span>
                                        @endif
                                        <small style="opacity: 0.7; font-size: 0.7rem; display: block;">({{ Str::limit($activeLocation?->name ?? __('Branch'), 12) }})</small>
                                    </button>
                                </form>

                                <form action="{{ route('admin.menu.products.destroy', $product->id) }}" method="POST" style="margin: 0;" onsubmit="return confirm('{{ __('Delete dish') }} {{ $product->name }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger" style="padding: 0.35rem 0.65rem; font-size: 0.78rem;" title="{{ __('Delete dish') }}">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div style="text-align: center; padding: 2rem; color: var(--text-muted); font-size: 0.9rem;">
                        {{ __('No dishes added to this category yet.') }}
                    </div>
                @endforelse
            </div>
        </div>
    @endforeach
        </div>
        <!-- End View 1: Categorized View -->
@endif

    <!-- View 2: High-Density Compact Table View -->
    <div x-show="viewMode === 'table'" x-cloak class="card" style="padding: 0; overflow: hidden; margin-bottom: 2rem;">
        <div style="overflow-x: auto;">
            <table class="modern-menu-table">
                <thead>
                    <tr>
                        <th style="width: 44px; text-align: center;">
                            <input type="checkbox" :checked="isAllSelected()" @change="toggleSelectAll()" style="width: 17px; height: 17px; accent-color: var(--primary); cursor: pointer;">
                        </th>
                        <th style="width: 55px;">{{ __('Photo') }}</th>
                        <th class="sortable-th" @click="toggleSort('name')" style="cursor: pointer;">
                            {{ __('Dish Name') }}
                            <i class="fa-solid" :class="sortBy === 'name' ? (sortAsc ? 'fa-arrow-up-a-z' : 'fa-arrow-down-z-a') : 'fa-sort'" style="font-size: 0.7rem; margin-left: 0.25rem;"></i>
                        </th>
                        <th class="sortable-th" @click="toggleSort('category_name')" style="cursor: pointer;">
                            {{ __('Category') }}
                            <i class="fa-solid" :class="sortBy === 'category_name' ? (sortAsc ? 'fa-arrow-up' : 'fa-arrow-down') : 'fa-sort'" style="font-size: 0.7rem; margin-left: 0.25rem;"></i>
                        </th>
                        <th class="sortable-th" @click="toggleSort('price')" style="text-align: right; cursor: pointer;">
                            {{ __('Price') }}
                            <i class="fa-solid" :class="sortBy === 'price' ? (sortAsc ? 'fa-arrow-up-1-9' : 'fa-arrow-down-9-1') : 'fa-sort'" style="font-size: 0.7rem; margin-left: 0.25rem;"></i>
                        </th>
                        <th style="text-align: center;">{{ __('Stock') }}</th>
                        <th style="text-align: center;">{{ __('Channels') }}</th>
                        <th style="text-align: center;">{{ __('Daily Offer') }}</th>
                        <th class="sortable-th" @click="toggleSort('sort_order')" style="text-align: center; width: 75px; cursor: pointer;">
                            {{ __('Order') }}
                            <i class="fa-solid fa-sort" style="font-size: 0.7rem;"></i>
                        </th>
                        <th style="text-align: right; width: 110px;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="dish in paginatedDishes()" :key="dish.id">
                        <tr class="table-row-item" :class="{ 'selected-row': selectedDishIds.includes(dish.id) }">
                            <!-- Checkbox -->
                            <td style="text-align: center;">
                                <input type="checkbox" :value="dish.id" x-model="selectedDishIds" style="width: 17px; height: 17px; accent-color: var(--primary); cursor: pointer;">
                            </td>

                            <!-- Thumbnail -->
                            <td>
                                <img :src="dish.image" onerror="this.onerror=null;this.src='/images/default-dish.png';" class="table-dish-thumb" alt="">
                            </td>

                            <!-- Dish Name (Inline Editable) -->
                            <td>
                                <div class="inline-editable-cell" @click="startInlineEdit(dish.id, 'name', dish.name)" title="{{ __('Click to edit dish name') }}">
                                    <template x-if="editingDishId === dish.id && editingField === 'name'">
                                        <div style="display: inline-flex; align-items: center; gap: 0.25rem;" @click.stop>
                                            <input type="text" x-model="editingValue" class="inline-edit-input" style="width: 180px;" @keydown.enter="saveInlineEdit(dish.id)" @keydown.escape="cancelInlineEdit()">
                                            <button type="button" class="btn btn-primary" style="padding: 0.15rem 0.35rem; font-size: 0.72rem;" @click="saveInlineEdit(dish.id)"><i class="fa-solid fa-check"></i></button>
                                            <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.35rem; font-size: 0.72rem;" @click="cancelInlineEdit()"><i class="fa-solid fa-xmark"></i></button>
                                        </div>
                                    </template>
                                    <template x-if="!(editingDishId === dish.id && editingField === 'name')">
                                        <div>
                                            <strong style="color: var(--text-main); font-weight: 700;" x-text="dish.name"></strong>
                                            <span x-show="dish.is_featured" class="badge badge-amber" style="font-size: 0.65rem; margin-left: 0.35rem;">★</span>
                                        </div>
                                    </template>
                                </div>
                            </td>

                            <!-- Category -->
                            <td>
                                <span class="badge badge-indigo" style="font-size: 0.75rem;" x-text="dish.category_name"></span>
                            </td>

                            <!-- Price (Inline Editable) -->
                            <td style="text-align: right;">
                                <div class="inline-editable-cell" @click="startInlineEdit(dish.id, 'price', dish.price)" title="{{ __('Click to edit price') }}">
                                    <template x-if="editingDishId === dish.id && editingField === 'price'">
                                        <div style="display: inline-flex; align-items: center; gap: 0.25rem;" @click.stop>
                                            <input type="number" x-model="editingValue" class="inline-edit-input" style="width: 85px;" @keydown.enter="saveInlineEdit(dish.id)" @keydown.escape="cancelInlineEdit()">
                                            <button type="button" class="btn btn-primary" style="padding: 0.15rem 0.35rem; font-size: 0.72rem;" @click="saveInlineEdit(dish.id)"><i class="fa-solid fa-check"></i></button>
                                            <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.35rem; font-size: 0.72rem;" @click="cancelInlineEdit()"><i class="fa-solid fa-xmark"></i></button>
                                        </div>
                                    </template>
                                    <template x-if="!(editingDishId === dish.id && editingField === 'price')">
                                        <span style="font-family: 'Outfit'; font-weight: 800; color: var(--primary);">
                                            <span x-text="formatNumber(dish.price)"></span> <span x-text="currency"></span>
                                        </span>
                                    </template>
                                </div>
                            </td>

                            <!-- Stock Toggle -->
                            <td style="text-align: center;">
                                <button type="button"
                                        @click="toggleDishStock(dish)"
                                        class="btn btn-secondary"
                                        style="padding: 0.25rem 0.6rem; font-size: 0.74rem; border-radius: 8px;"
                                        :style="dish.is_available ? 'color: #10b981; border-color: rgba(16, 185, 129, 0.4);' : 'color: #ef4444; border-color: rgba(239, 68, 68, 0.4);'">
                                    <i class="fa-solid" :class="dish.is_available ? 'fa-toggle-on' : 'fa-toggle-off'"></i>
                                    <span x-text="dish.is_available ? '{{ __('In Stock') }}' : '{{ __('Out of Stock') }}'"></span>
                                </button>
                            </td>

                            <!-- Channels -->
                            <td style="text-align: center; white-space: nowrap;">
                                <span style="display: inline-flex; gap: 0.25rem; font-size: 0.85rem;">
                                    <span :style="dish.available_for_dine_in ? 'opacity: 1;' : 'opacity: 0.2; filter: grayscale(1);'" title="{{ __('Dine-in') }}">🍽️</span>
                                    <span :style="dish.available_for_takeaway ? 'opacity: 1;' : 'opacity: 0.2; filter: grayscale(1);'" title="{{ __('Takeaway') }}">🥡</span>
                                    <span :style="dish.available_for_delivery ? 'opacity: 1;' : 'opacity: 0.2; filter: grayscale(1);'" title="{{ __('Delivery') }}">🛵</span>
                                </span>
                            </td>

                            <!-- Daily Offer -->
                            <td style="text-align: center;">
                                <span x-show="dish.is_daily_offer" class="badge" style="background: linear-gradient(135deg, #f59e0b, #ef4444); color: #fff; font-size: 0.68rem; padding: 0.15rem 0.45rem;">
                                    <i class="fa-solid fa-fire"></i>
                                </span>
                                <span x-show="!dish.is_daily_offer" style="color: var(--text-muted); opacity: 0.3;">—</span>
                            </td>

                            <!-- Sort Order (Inline Editable) -->
                            <td style="text-align: center;">
                                <div class="inline-editable-cell" @click="startInlineEdit(dish.id, 'sort_order', dish.sort_order)" title="{{ __('Click to edit display order') }}">
                                    <template x-if="editingDishId === dish.id && editingField === 'sort_order'">
                                        <div style="display: inline-flex; align-items: center; gap: 0.25rem;" @click.stop>
                                            <input type="number" x-model="editingValue" class="inline-edit-input" style="width: 50px;" @keydown.enter="saveInlineEdit(dish.id)" @keydown.escape="cancelInlineEdit()">
                                            <button type="button" class="btn btn-primary" style="padding: 0.15rem 0.35rem; font-size: 0.72rem;" @click="saveInlineEdit(dish.id)"><i class="fa-solid fa-check"></i></button>
                                        </div>
                                    </template>
                                    <template x-if="!(editingDishId === dish.id && editingField === 'sort_order')">
                                        <span style="font-size: 0.78rem; color: var(--text-muted); font-weight: 700;" x-text="dish.sort_order"></span>
                                    </template>
                                </div>
                            </td>

                            <!-- Actions -->
                            <td style="text-align: right; white-space: nowrap;">
                                <div style="display: inline-flex; gap: 0.35rem;">
                                    <button type="button" class="btn btn-secondary" style="padding: 0.25rem 0.55rem; font-size: 0.74rem;" @click="editProduct(dish.raw_json)">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button type="button" class="btn btn-danger" style="padding: 0.25rem 0.55rem; font-size: 0.74rem;" @click="deleteSingleProduct(dish)">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="filteredDishes().length === 0">
                        <td colspan="10" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            <i class="fa-solid fa-magnifying-glass" style="font-size: 1.5rem; margin-bottom: 0.5rem; display: block; opacity: 0.5;"></i>
                            {{ __('No dishes matching the active filters.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Table Pagination Footer -->
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.85rem 1.25rem; border-top: 1px solid var(--border-color); flex-wrap: wrap; gap: 0.75rem; background: var(--bg-card);">
            <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.82rem; color: var(--text-muted);">
                <span>{{ __('Rows per page:') }}</span>
                <select x-model.number="perPage" class="form-select" style="padding: 0.2rem 0.5rem; font-size: 0.8rem; width: auto;">
                    <option value="15">15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>

            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary" style="padding: 0.3rem 0.65rem; font-size: 0.78rem;" :disabled="currentPage <= 1" @click="currentPage--">
                    <i class="fa-solid fa-chevron-left"></i> {{ __('Previous') }}
                </button>
                <span style="font-size: 0.82rem; color: var(--text-muted); font-weight: 700;">
                    <span x-text="currentPage"></span> / <span x-text="totalPages()"></span>
                </span>
                <button type="button" class="btn btn-secondary" style="padding: 0.3rem 0.65rem; font-size: 0.78rem;" :disabled="currentPage >= totalPages()" @click="currentPage++">
                    {{ __('Next') }} <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
    <!-- End View 2: High-Density Table View -->

    <!-- View 3: Category Organizer View -->
    <div x-show="viewMode === 'categories'" x-cloak class="card" style="padding: 1.5rem; margin-bottom: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
            <div>
                <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    <i class="fa-solid fa-folder-tree" style="color: var(--primary);"></i> {{ __('Category Display Order & Safeguards') }}
                </h2>
                <p style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.25rem;">
                    {{ __('Drag & drop or use arrow buttons to reorder categories. Duplication clones category dishes and portions.') }}
                </p>
            </div>
            <button type="button" class="btn btn-primary" onclick="document.getElementById('newCategoryModal').style.display='flex'">
                <i class="fa-solid fa-plus"></i> {{ __('New Category') }}
            </button>
        </div>

        <div>
            <template x-for="(cat, index) in categories" :key="cat.id">
                <div class="category-drag-card"
                     draggable="true"
                     @dragstart="onCategoryDragStart(event, index)"
                     @dragover.prevent
                     @drop="onCategoryDrop(event, index)">
                    <!-- Left: Drag handle + Index + Name -->
                    <div style="display: flex; align-items: center; gap: 0.85rem; min-width: 0; flex: 1;">
                        <span class="drag-handle" style="cursor: grab; color: var(--text-muted); padding: 0.25rem;" title="{{ __('Drag to reorder') }}">
                            <i class="fa-solid fa-grip-vertical"></i>
                        </span>
                        <span class="badge" style="background: var(--input-bg); border: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.75rem; font-weight: 800; padding: 0.2rem 0.5rem;">
                            #<span x-text="index + 1"></span>
                        </span>
                        <div style="min-width: 0;">
                            <strong style="font-size: 1.05rem; color: var(--text-main); font-weight: 800; display: block;" x-text="cat.name"></strong>
                            <div x-show="cat.name_translations && (cat.name_translations.hy || cat.name_translations.ru)" style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem;">
                                <span x-show="cat.name_translations?.hy" x-text="'HY: ' + cat.name_translations?.hy"></span>
                                <span x-show="cat.name_translations?.hy && cat.name_translations?.ru"> | </span>
                                <span x-show="cat.name_translations?.ru" x-text="'RU: ' + cat.name_translations?.ru"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Count badge + Move Up/Down + Duplicate + Edit + Safe Delete -->
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0; flex-wrap: wrap;">
                        <span class="badge badge-indigo" style="font-size: 0.75rem; padding: 0.25rem 0.6rem;">
                            <span x-text="cat.products_count"></span> {{ __('dishes') }}
                        </span>

                        <!-- Move Up -->
                        <button type="button" class="btn btn-secondary" style="padding: 0.35rem 0.6rem; font-size: 0.75rem;" :disabled="index === 0" @click="moveCategory(index, -1)" title="{{ __('Move category up') }}">
                            <i class="fa-solid fa-arrow-up"></i>
                        </button>

                        <!-- Move Down -->
                        <button type="button" class="btn btn-secondary" style="padding: 0.35rem 0.6rem; font-size: 0.75rem;" :disabled="index === categories.length - 1" @click="moveCategory(index, 1)" title="{{ __('Move category down') }}">
                            <i class="fa-solid fa-arrow-down"></i>
                        </button>

                        <!-- Duplicate -->
                        <button type="button" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem; color: #6366f1; border-color: rgba(99, 102, 241, 0.4);" @click="duplicateCategory(cat)" title="{{ __('Duplicate category and all dishes') }}">
                            <i class="fa-solid fa-copy"></i> {{ __('Duplicate') }}
                        </button>

                        <!-- Edit -->
                        <button type="button" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" @click="editCategory(cat.raw_json)">
                            <i class="fa-solid fa-pen"></i>
                        </button>

                        <!-- Safe Delete -->
                        <button type="button" class="btn btn-danger" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" @click="openSafeDeleteCategory(cat)" title="{{ __('Delete Category Safely') }}">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
    <!-- End View 3: Category Organizer View -->

    <!-- Floating Bulk Action Bar -->
    <div class="floating-bulk-bar" x-show="selectedDishIds.length > 0" x-cloak>
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span class="badge" style="background: var(--primary); color: #000; font-weight: 800; font-size: 0.85rem; padding: 0.35rem 0.75rem; border-radius: 999px;">
                <i class="fa-solid fa-check"></i> <span x-text="selectedDishIds.length"></span>
            </span>
            <strong style="color: var(--text-main); font-size: 0.9rem;">{{ __('dishes selected') }}</strong>
        </div>

        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
            <!-- Bulk Edit Modal Trigger -->
            <button type="button" class="btn btn-primary" style="padding: 0.45rem 0.85rem; font-size: 0.82rem; font-weight: 700;" @click="openBulkEditModal()">
                <i class="fa-solid fa-pen-to-square"></i> {{ __('Bulk Edit') }}
            </button>

            <!-- Quick In Stock -->
            <button type="button" class="btn btn-secondary" style="padding: 0.45rem 0.75rem; font-size: 0.82rem; color: #10b981; border-color: rgba(16, 185, 129, 0.4);" @click="quickBulkStock(true)">
                <i class="fa-solid fa-circle-check"></i> {{ __('In Stock') }}
            </button>

            <!-- Quick Out of Stock -->
            <button type="button" class="btn btn-secondary" style="padding: 0.45rem 0.75rem; font-size: 0.82rem; color: #ef4444; border-color: rgba(239, 68, 68, 0.4);" @click="quickBulkStock(false)">
                <i class="fa-solid fa-circle-xmark"></i> {{ __('Out of Stock') }}
            </button>

            <!-- Bulk Delete Trigger -->
            <button type="button" class="btn btn-danger" style="padding: 0.45rem 0.75rem; font-size: 0.82rem;" @click="showBulkDeleteModal = true">
                <i class="fa-solid fa-trash"></i> {{ __('Delete') }}
            </button>

            <!-- Clear Selection -->
            <button type="button" class="btn btn-secondary" style="padding: 0.45rem 0.65rem; font-size: 0.82rem;" @click="selectedDishIds = []" title="{{ __('Clear Selection') }}">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </div>

    <!-- Modal: Bulk Edit Dishes -->
    <div class="modern-modal-overlay" x-show="showBulkEditModal" x-cloak style="display: flex;">
        <div class="modal-box-responsive" style="max-width: 620px;" @click.away="showBulkEditModal = false">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <div>
                    <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0;">
                        <i class="fa-solid fa-pen-to-square" style="color: var(--primary);"></i> {{ __('Bulk Edit Dishes') }}
                    </h3>
                    <p style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.25rem;">
                        {{ __('Applying changes to') }} <strong style="color: var(--primary);" x-text="selectedDishIds.length"></strong> {{ __('selected dishes') }}
                    </p>
                </div>
                <button type="button" @click="showBulkEditModal = false" style="background: none; border: none; color: var(--text-muted); font-size: 1.25rem; cursor: pointer;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form @submit.prevent="submitBulkUpdate()">
                <!-- Price Adjustment -->
                <div style="background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem; margin-bottom: 1.15rem;">
                    <label style="font-size: 0.86rem; font-weight: 800; color: var(--text-main); display: block; margin-bottom: 0.65rem;">
                        <i class="fa-solid fa-tag" style="color: var(--primary);"></i> {{ __('Price Adjustment') }}
                    </label>
                    <div class="grid-2" style="gap: 0.75rem; margin-bottom: 0.75rem;">
                        <div>
                            <label class="form-label" style="font-size: 0.75rem;">{{ __('Action') }}</label>
                            <select x-model="bulkAction.price_action" class="form-select" style="font-size: 0.84rem;">
                                <option value="none">{{ __('Leave Unchanged') }}</option>
                                <option value="set">{{ __('Set Specific Price') }}</option>
                                <option value="increase_amount">{{ __('Increase by Fixed Amount') }}</option>
                                <option value="decrease_amount">{{ __('Decrease by Fixed Amount') }}</option>
                                <option value="increase_percent">{{ __('Increase by Percentage (%)') }}</option>
                                <option value="decrease_percent">{{ __('Decrease by Percentage (%)') }}</option>
                            </select>
                        </div>
                        <div x-show="bulkAction.price_action !== 'none'">
                            <label class="form-label" style="font-size: 0.75rem;">
                                <span x-text="bulkAction.price_action.includes('percent') ? '{{ __('Percentage (%)') }}' : '{{ __('Amount (:currency)', ['currency' => $vendor->currency]) }}'"></span>
                            </label>
                            <input type="number" step="any" min="0" x-model="bulkAction.price_value" class="form-input" style="font-size: 0.84rem;" placeholder="0">
                        </div>
                    </div>

                    <div x-show="bulkAction.price_action !== 'none'" style="margin-bottom: 0.75rem;">
                        <label class="form-label" style="font-size: 0.75rem;">{{ __('Rounding Rule') }}</label>
                        <select x-model="bulkAction.price_rounding" class="form-select" style="font-size: 0.84rem;">
                            <option value="none">{{ __('No Rounding (Exact)') }}</option>
                            <option value="nearest_10">{{ __('Round to Nearest 10') }}</option>
                            <option value="nearest_50">{{ __('Round to Nearest 50') }}</option>
                            <option value="nearest_100">{{ __('Round to Nearest 100') }}</option>
                            <option value="integer">{{ __('Round to Integer') }}</option>
                        </select>
                    </div>

                    <!-- Live Price Preview Sample -->
                    <div x-show="bulkAction.price_action !== 'none' && bulkAction.price_value" style="background: rgba(245, 158, 11, 0.1); border: 1px dashed rgba(245, 158, 11, 0.4); border-radius: 10px; padding: 0.65rem 0.85rem; font-size: 0.82rem; color: var(--text-main);">
                        <strong>{{ __('Price Preview Sample:') }}</strong>
                        <div style="margin-top: 0.25rem;" x-html="getBulkPricePreviewHtml()"></div>
                    </div>
                </div>

                <!-- Category Assignment -->
                <div style="background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem; margin-bottom: 1.15rem;">
                    <label style="font-size: 0.86rem; font-weight: 800; color: var(--text-main); display: block; margin-bottom: 0.65rem;">
                        <i class="fa-solid fa-folder" style="color: var(--primary);"></i> {{ __('Move to Category') }}
                    </label>
                    <select x-model="bulkAction.category_id" class="form-select" style="font-size: 0.84rem;">
                        <option value="">{{ __('Leave Unchanged') }}</option>
                        <template x-for="c in categories" :key="c.id">
                            <option :value="c.id" x-text="c.name"></option>
                        </template>
                    </select>
                </div>

                <!-- Stock & Channels -->
                <div style="background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem; margin-bottom: 1.5rem;">
                    <label style="font-size: 0.86rem; font-weight: 800; color: var(--text-main); display: block; margin-bottom: 0.65rem;">
                        <i class="fa-solid fa-toggle-on" style="color: var(--primary);"></i> {{ __('Availability & Channels') }}
                    </label>
                    <div class="grid-2" style="gap: 0.75rem; margin-bottom: 0.75rem;">
                        <div>
                            <label class="form-label" style="font-size: 0.75rem;">{{ __('Stock Availability') }}</label>
                            <select x-model="bulkAction.is_available" class="form-select" style="font-size: 0.84rem;">
                                <option value="">{{ __('Leave Unchanged') }}</option>
                                <option value="1">{{ __('In Stock (Available)') }}</option>
                                <option value="0">{{ __('Out of Stock (Unavailable)') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 0.75rem;">{{ __('Featured Status') }}</label>
                            <select x-model="bulkAction.is_featured" class="form-select" style="font-size: 0.84rem;">
                                <option value="">{{ __('Leave Unchanged') }}</option>
                                <option value="1">★ {{ __('Featured') }}</option>
                                <option value="0">{{ __('Not Featured') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid-3" style="gap: 0.5rem;">
                        <div>
                            <label class="form-label" style="font-size: 0.75rem;">🍽️ {{ __('Dine-in') }}</label>
                            <select x-model="bulkAction.available_for_dine_in" class="form-select" style="font-size: 0.82rem;">
                                <option value="">{{ __('Unchanged') }}</option>
                                <option value="1">{{ __('Enable') }}</option>
                                <option value="0">{{ __('Disable') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 0.75rem;">🥡 {{ __('Takeaway') }}</label>
                            <select x-model="bulkAction.available_for_takeaway" class="form-select" style="font-size: 0.82rem;">
                                <option value="">{{ __('Unchanged') }}</option>
                                <option value="1">{{ __('Enable') }}</option>
                                <option value="0">{{ __('Disable') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 0.75rem;">🛵 {{ __('Delivery') }}</label>
                            <select x-model="bulkAction.available_for_delivery" class="form-select" style="font-size: 0.82rem;">
                                <option value="">{{ __('Unchanged') }}</option>
                                <option value="1">{{ __('Enable') }}</option>
                                <option value="0">{{ __('Disable') }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Footer Buttons -->
                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" class="btn btn-secondary" @click="showBulkEditModal = false">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary" :disabled="bulkSubmitting">
                        <span x-show="!bulkSubmitting"><i class="fa-solid fa-check"></i> {{ __('Apply Changes') }}</span>
                        <span x-show="bulkSubmitting"><i class="fa-solid fa-spinner fa-spin"></i> {{ __('Applying...') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Bulk Delete Confirmation -->
    <div class="modern-modal-overlay" x-show="showBulkDeleteModal" x-cloak style="display: flex;">
        <div class="modal-box-responsive" style="max-width: 480px;" @click.away="showBulkDeleteModal = false">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem;">
                <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(239, 68, 68, 0.15); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div>
                    <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0;">{{ __('Confirm Bulk Deletion') }}</h3>
                    <span style="font-size: 0.8rem; color: #ef4444; font-weight: 600;">{{ __('Irreversible Action') }}</span>
                </div>
            </div>

            <p style="color: var(--text-muted); font-size: 0.88rem; line-height: 1.5; margin-bottom: 1rem;">
                {{ __('You are about to delete') }} <strong style="color: var(--text-main);" x-text="selectedDishIds.length"></strong> {{ __('dishes and all associated portion variations, images, and allergen tags.') }}
            </p>

            <div style="background: rgba(239, 68, 68, 0.06); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 12px; padding: 0.85rem; margin-bottom: 1.25rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.82rem; font-weight: 700; color: var(--text-main); cursor: pointer;">
                    <input type="checkbox" x-model="bulkConfirmDeleteChecked">
                    <span>{{ __('I understand that this action cannot be undone.') }}</span>
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" @click="showBulkDeleteModal = false">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-danger" :disabled="!bulkConfirmDeleteChecked || bulkSubmitting" @click="submitBulkDelete()">
                    <span x-show="!bulkSubmitting"><i class="fa-solid fa-trash"></i> {{ __('Delete Dishes') }}</span>
                    <span x-show="bulkSubmitting"><i class="fa-solid fa-spinner fa-spin"></i> {{ __('Deleting...') }}</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal: Safe Delete Category Safeguard -->
    <div class="modern-modal-overlay" x-show="showSafeDeleteCategoryModal" x-cloak style="display: flex;">
        <div class="modal-box-responsive" style="max-width: 500px;" @click.away="showSafeDeleteCategoryModal = false">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem;">
                <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(239, 68, 68, 0.15); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                    <i class="fa-solid fa-folder-minus"></i>
                </div>
                <div>
                    <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0;">{{ __('Delete Category') }}</h3>
                    <span style="font-size: 0.82rem; color: var(--text-muted);" x-text="deletingCategory?.name"></span>
                </div>
            </div>

            <template x-if="deletingCategory && deletingCategory.products_count > 0">
                <div>
                    <p style="color: var(--text-muted); font-size: 0.86rem; line-height: 1.5; margin-bottom: 1rem;">
                        {{ __('This category currently contains') }} <strong style="color: var(--primary);" x-text="deletingCategory.products_count"></strong> {{ __('dishes. Choose how to handle these dishes:') }}
                    </p>

                    <!-- Choice 1: Reassign -->
                    <div style="background: var(--input-bg); border: 1.5px solid var(--border-color); border-radius: 12px; padding: 0.85rem; margin-bottom: 0.75rem;">
                        <label style="display: flex; align-items: flex-start; gap: 0.6rem; cursor: pointer;">
                            <input type="radio" name="cat_del_choice" value="reassign" :checked="!forceDeleteCategory" @change="forceDeleteCategory = false" style="margin-top: 0.2rem;">
                            <div style="flex: 1;">
                                <strong style="font-size: 0.88rem; color: var(--text-main); display: block;">{{ __('Reassign dishes to another category (Safe)') }}</strong>
                                <span style="font-size: 0.78rem; color: var(--text-muted);">{{ __('Dishes will remain available under the selected category.') }}</span>
                                
                                <div style="margin-top: 0.5rem;" x-show="!forceDeleteCategory">
                                    <select x-model="reassignCategoryId" class="form-select" style="font-size: 0.82rem;">
                                        <option value="">-- {{ __('Select target category') }} --</option>
                                        <template x-for="c in categories.filter(c => c.id !== deletingCategory?.id)" :key="c.id">
                                            <option :value="c.id" x-text="c.name"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>
                        </label>
                    </div>

                    <!-- Choice 2: Force Delete -->
                    <div style="background: rgba(239, 68, 68, 0.05); border: 1.5px solid rgba(239, 68, 68, 0.25); border-radius: 12px; padding: 0.85rem; margin-bottom: 1.25rem;">
                        <label style="display: flex; align-items: flex-start; gap: 0.6rem; cursor: pointer;">
                            <input type="radio" name="cat_del_choice" value="force" :checked="forceDeleteCategory" @change="forceDeleteCategory = true" style="margin-top: 0.2rem;">
                            <div>
                                <strong style="font-size: 0.88rem; color: #ef4444; display: block;">{{ __('Delete category and ALL contained dishes (Forced)') }}</strong>
                                <span style="font-size: 0.78rem; color: var(--text-muted);">{{ __('All dishes inside this category will be permanently removed.') }}</span>
                            </div>
                        </label>
                    </div>
                </div>
            </template>

            <template x-if="deletingCategory && deletingCategory.products_count === 0">
                <p style="color: var(--text-muted); font-size: 0.88rem; line-height: 1.5; margin-bottom: 1.25rem;">
                    {{ __('This category has no dishes and can be safely removed.') }}
                </p>
            </template>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" @click="showSafeDeleteCategoryModal = false">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-danger" :disabled="categoryActionSubmitting || (deletingCategory?.products_count > 0 && !forceDeleteCategory && !reassignCategoryId)" @click="confirmDeleteCategory()">
                    <span x-show="!categoryActionSubmitting"><i class="fa-solid fa-trash"></i> {{ __('Confirm Delete') }}</span>
                    <span x-show="categoryActionSubmitting"><i class="fa-solid fa-spinner fa-spin"></i> {{ __('Deleting...') }}</span>
                </button>
            </div>
        </div>
    </div>
</div>
<!-- End menuManagerRoot -->

<!-- Modal Add Category -->
<div id="newCategoryModal" class="modern-modal-overlay" style="display: none;">
    <div class="modal-box-responsive" style="max-width: 500px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0;">{{ __('Create New Category') }}</h3>
            <button onclick="document.getElementById('newCategoryModal').style.display='none'" style="background: none; border: none; color: var(--text-muted); font-size: 1.25rem; cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form action="{{ route('admin.menu.categories.store') }}" method="POST">
            @csrf
            <div style="margin-bottom: 1rem;">
                <label class="form-label">{{ __('Category Name') }} ({{ $primaryLang['name'] }} {{ $primaryLang['flag'] }}) *</label>
                <input type="text" name="name" required placeholder="{{ __('e.g. Signature Cocktails') }}" class="form-input">
            </div>

            @if(count($secondaryLangs) > 0)
                <div style="margin-bottom: 1rem; padding: 0.85rem; background: var(--bg-body); border: 1px dashed var(--border-color); border-radius: 12px;">
                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.65rem;">
                        <i class="fa-solid fa-language"></i> {{ __('Translations for active languages') }}
                    </div>
                    @foreach($secondaryLangs as $secLang)
                        <div style="margin-bottom: 0.65rem;">
                            <label class="form-label" style="font-size: 0.78rem;">{{ $secLang['name'] }} {{ $secLang['flag'] }}</label>
                            <input type="text" name="name_translations[{{ $secLang['code'] }}]" placeholder="{{ __('Translation in') }} {{ $secLang['name'] }}" class="form-input" style="font-size: 0.85rem;">
                        </div>
                    @endforeach
                </div>
            @endif

            <div style="margin-bottom: 1.5rem;">
                <label class="form-label">{{ __('Description (Optional)') }}</label>
                <textarea name="description" rows="2" placeholder="{{ __('Brief category introduction...') }}" class="form-textarea"></textarea>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('newCategoryModal').style.display='none'">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('Save Category') }}</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Category -->
<div id="editCategoryModal" class="modern-modal-overlay" style="display: none;">
    <div class="modal-box-responsive" style="max-width: 500px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0;">{{ __('Edit Category') }}</h3>
            <button onclick="document.getElementById('editCategoryModal').style.display='none'" style="background: none; border: none; color: var(--text-muted); font-size: 1.25rem; cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form id="editCategoryForm" method="POST">
            @csrf
            <div style="margin-bottom: 1rem;">
                <label class="form-label">{{ __('Category Name') }} ({{ $primaryLang['name'] }} {{ $primaryLang['flag'] }}) *</label>
                <input type="text" id="edit_cat_name" name="name" required class="form-input">
            </div>

            @if(count($secondaryLangs) > 0)
                <div style="margin-bottom: 1rem; padding: 0.85rem; background: var(--bg-body); border: 1px dashed var(--border-color); border-radius: 12px;">
                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.65rem;">
                        <i class="fa-solid fa-language"></i> {{ __('Translations for active languages') }}
                    </div>
                    @foreach($secondaryLangs as $secLang)
                        <div style="margin-bottom: 0.65rem;">
                            <label class="form-label" style="font-size: 0.78rem;">{{ $secLang['name'] }} {{ $secLang['flag'] }}</label>
                            <input type="text" id="edit_cat_trans_{{ $secLang['code'] }}" name="name_translations[{{ $secLang['code'] }}]" placeholder="{{ __('Translation in') }} {{ $secLang['name'] }}" class="form-input" style="font-size: 0.85rem;">
                        </div>
                    @endforeach
                </div>
            @endif

            <div style="margin-bottom: 1.5rem;">
                <label class="form-label">{{ __('Description') }}</label>
                <textarea id="edit_cat_description" name="description" rows="2" class="form-textarea"></textarea>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('editCategoryModal').style.display='none'">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('Update Category') }}</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Add Product -->
<div id="newProductModal" class="modern-modal-overlay" style="display: none;">
    <div class="modal-box-responsive">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0;">{{ __('Add New Menu Dish') }}</h3>
            <button onclick="document.getElementById('newProductModal').style.display='none'" style="background: none; border: none; color: var(--text-muted); font-size: 1.25rem; cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form action="{{ route('admin.menu.products.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div style="margin-bottom: 1rem;">
                <label class="form-label">{{ __('Category') }} *</label>
                <select name="category_id" required class="form-select">
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid-3" style="margin-bottom: 1rem;">
                <div style="grid-column: span 1;">
                    <label class="form-label">{{ __('Dish Name') }} ({{ $primaryLang['name'] }} {{ $primaryLang['flag'] }}) *</label>
                    <input type="text" name="name" required class="form-input">
                </div>
                <div>
                    <label class="form-label">{{ __('Price') }} ({{ $vendor->currency }}) *</label>
                    <input type="number" name="price" step="100" required class="form-input">
                </div>
                <div>
                    <label class="form-label" style="color: #ef4444;">
                        <i class="fa-solid fa-tag"></i> {{ __('Discounted Price') }}
                    </label>
                    <input type="number" name="discount_price" step="100" placeholder="{{ __('e.g. 2500') }}" class="form-input" style="border-color: rgba(239, 68, 68, 0.4);">
                </div>
            </div>

            <!-- Happy Hour & Discount Schedule Section -->
            <div style="margin-bottom: 1.25rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="width: 28px; height: 28px; border-radius: 8px; background: rgba(239, 68, 68, 0.15); color: #ef4444; display: inline-flex; align-items: center; justify-content: center; font-size: 0.85rem;">
                            <i class="fa-solid fa-clock"></i>
                        </span>
                        <div>
                            <strong style="font-size: 0.85rem; color: var(--text-main);">{{ __('Discount Schedule (Happy Hour)') }}</strong>
                            <small style="display: block; color: var(--text-muted); font-size: 0.72rem;">{{ __('Set active days and hours for the discount') }}</small>
                        </div>
                    </div>
                    <label style="font-size: 0.78rem; display: flex; align-items: center; gap: 0.4rem; cursor: pointer; color: var(--text-main); font-weight: 600;">
                        <input type="checkbox" name="is_discount_active" value="1" checked> {{ __('Enable Discount') }}
                    </label>
                </div>

                <!-- Day Selector Chips -->
                <div style="margin-bottom: 0.75rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem; flex-wrap: wrap; gap: 0.35rem;">
                        <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">{{ __('Days of the Week') }}</label>
                        <div style="display: flex; gap: 0.35rem;">
                            <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setDiscountDaysPreset('new', 'all')">{{ __('All') }}</button>
                            <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setDiscountDaysPreset('new', 'weekdays')">{{ __('Mon-Fri') }}</button>
                            <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setDiscountDaysPreset('new', 'weekends')">{{ __('Weekends') }}</button>
                        </div>
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
                        @php
                            $weekDays = [
                                'mon' => __('Mon'),
                                'tue' => __('Tue'),
                                'wed' => __('Wed'),
                                'thu' => __('Thu'),
                                'fri' => __('Fri'),
                                'sat' => __('Sat'),
                                'sun' => __('Sun')
                            ];
                        @endphp
                        @foreach($weekDays as $key => $lbl)
                            <label style="display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.75rem; background: var(--input-bg); border: 1px solid var(--border-color); padding: 0.25rem 0.55rem; border-radius: 8px; cursor: pointer; color: var(--text-main);">
                                <input type="checkbox" class="new-discount-day-checkbox" name="discount_days[]" value="{{ $key }}" checked> {{ $lbl }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- Time Window -->
                <div class="grid-2" style="margin-bottom: 0.35rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.75rem;">{{ __('Start Time') }}</label>
                        <input type="time" name="discount_start_time" class="form-input" style="padding: 0.45rem 0.75rem;">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.75rem;">{{ __('End Time') }}</label>
                        <input type="time" name="discount_end_time" class="form-input" style="padding: 0.45rem 0.75rem;">
                    </div>
                </div>
            </div>

            <!-- Branch Availability (New) -->
            <div style="margin-bottom: 1.25rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="width: 28px; height: 28px; border-radius: 8px; background: rgba(99, 102, 241, 0.15); color: #6366f1; display: inline-flex; align-items: center; justify-content: center; font-size: 0.85rem;">
                            <i class="fa-solid fa-code-branch"></i>
                        </span>
                        <div>
                            <strong style="font-size: 0.85rem; color: var(--text-main);">{{ __('Branch Availability') }}</strong>
                            <small style="display: block; color: var(--text-muted); font-size: 0.72rem;">{{ __('Select which branches offer this dish') }}</small>
                        </div>
                    </div>
                    <div style="display: flex; gap: 0.35rem;">
                        <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setLocationsPreset('new', true)">{{ __('Select All') }}</button>
                        <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setLocationsPreset('new', false)">{{ __('Clear') }}</button>
                    </div>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                    @foreach($locations as $loc)
                        <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.78rem; background: var(--input-bg); border: 1px solid var(--border-color); padding: 0.35rem 0.65rem; border-radius: 8px; cursor: pointer; color: var(--text-main); font-weight: 600;">
                            <input type="checkbox" class="new-location-checkbox" name="locations[]" value="{{ $loc->id }}" checked> 📍 {{ $loc->name }}
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Schedule & Time Availability (Lunch Menu) (New) -->
            <div style="margin-bottom: 1.25rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                    <span style="width: 28px; height: 28px; border-radius: 8px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: inline-flex; align-items: center; justify-content: center; font-size: 0.85rem;">
                        <i class="fa-regular fa-clock"></i>
                    </span>
                    <div>
                        <strong style="font-size: 0.85rem; color: var(--text-main);">{{ __('Operating Hours (Lunch / Hours)') }}</strong>
                        <small style="display: block; color: var(--text-muted); font-size: 0.72rem;">{{ __('e.g., lunch available only 12:00 - 15:00 (leave empty for all day)') }}</small>
                    </div>
                </div>
                <div class="grid-2" style="margin-bottom: 0.75rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.75rem;">{{ __('Available from (Start Time)') }}</label>
                        <input type="time" name="available_start_time" class="form-input" style="padding: 0.45rem 0.75rem;">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.75rem;">{{ __('Until (End Time)') }}</label>
                        <input type="time" name="available_end_time" class="form-input" style="padding: 0.45rem 0.75rem;">
                    </div>
                </div>
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem; flex-wrap: gap: 0.35rem;">
                        <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">{{ __('Available Days') }}</label>
                        <div style="display: flex; gap: 0.35rem;">
                            <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setAvailableDaysPreset('new', 'all')">{{ __('All') }}</button>
                            <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setAvailableDaysPreset('new', 'weekdays')">{{ __('Mon-Fri') }}</button>
                            <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setAvailableDaysPreset('new', 'weekends')">{{ __('Weekends') }}</button>
                        </div>
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
                        @foreach($weekDays as $key => $lbl)
                            <label style="display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.75rem; background: var(--input-bg); border: 1px solid var(--border-color); padding: 0.25rem 0.55rem; border-radius: 8px; cursor: pointer; color: var(--text-main);">
                                <input type="checkbox" class="new-available-day-checkbox" name="available_days[]" value="{{ $key }}" checked> {{ $lbl }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Order Channels / Type Availability (New) -->
            <div style="margin-bottom: 1.25rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                    <span style="width: 28px; height: 28px; border-radius: 8px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: inline-flex; align-items: center; justify-content: center; font-size: 0.85rem;">
                        <i class="fa-solid fa-truck-ramp-box"></i>
                    </span>
                    <div>
                        <strong style="font-size: 0.85rem; color: var(--text-main);">{{ __('Order Channels Availability') }}</strong>
                        <small style="display: block; color: var(--text-muted); font-size: 0.72rem;">{{ __('Select allowed order channels for this dish') }}</small>
                    </div>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 0.65rem;">
                    <label style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.82rem; background: var(--input-bg); border: 1px solid var(--border-color); padding: 0.45rem 0.8rem; border-radius: 10px; cursor: pointer; color: var(--text-main); font-weight: 600;">
                        <input type="hidden" name="available_for_dine_in" value="0">
                        <input type="checkbox" name="available_for_dine_in" value="1" checked> 🍽️ {{ __('Dine-in') }}
                    </label>
                    <label style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.82rem; background: var(--input-bg); border: 1px solid var(--border-color); padding: 0.45rem 0.8rem; border-radius: 10px; cursor: pointer; color: var(--text-main); font-weight: 600;">
                        <input type="hidden" name="available_for_takeaway" value="0">
                        <input type="checkbox" name="available_for_takeaway" value="1" checked> 🥡 {{ __('Takeaway') }}
                    </label>
                    <label style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.82rem; background: var(--input-bg); border: 1px solid var(--border-color); padding: 0.45rem 0.8rem; border-radius: 10px; cursor: pointer; color: var(--text-main); font-weight: 600;">
                        <input type="hidden" name="available_for_delivery" value="0">
                        <input type="checkbox" name="available_for_delivery" value="1" checked> 🛵 {{ __('Delivery') }}
                    </label>
                </div>
            </div>

            @if(count($secondaryLangs) > 0)
                <div style="margin-bottom: 1rem; padding: 0.85rem; background: var(--bg-body); border: 1px dashed var(--border-color); border-radius: 12px;">
                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.65rem;">
                        <i class="fa-solid fa-language"></i> {{ __('Dish Name Translations for active languages') }}
                    </div>
                    <div class="grid-2" style="gap: 0.65rem;">
                        @foreach($secondaryLangs as $secLang)
                            <div>
                                <label class="form-label" style="font-size: 0.78rem;">{{ $secLang['name'] }} {{ $secLang['flag'] }}</label>
                                <input type="text" name="name_translations[{{ $secLang['code'] }}]" placeholder="{{ __('Translation in') }} {{ $secLang['name'] }}" class="form-input" style="font-size: 0.85rem;">
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div style="margin-bottom: 1rem;">
                <label class="form-label">
                    <i class="fa-solid fa-image" style="color: var(--primary);"></i> {{ __('Dish Image (Upload File or Enter URL)') }}
                </label>
                <div class="grid-2" style="align-items: center;">
                    <div>
                        <input type="file" name="image_file" accept="image/*" class="form-input" style="padding: 0.45rem;">
                        <small style="color: var(--text-muted); font-size: 0.7rem; display: block; margin-top: 0.25rem;">📁 {{ __('Upload file from device') }}</small>
                    </div>
                    <div>
                        <input type="text" name="image" placeholder="/storage/... or https://..." class="form-input">
                        <small style="color: var(--text-muted); font-size: 0.7rem; display: block; margin-top: 0.25rem;">🔗 {{ __('Or image link / path') }}</small>
                    </div>
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label class="form-label">{{ __('Description') }} ({{ $primaryLang['name'] }} {{ $primaryLang['flag'] }})</label>
                <textarea name="description" rows="2" class="form-textarea" placeholder="{{ __('Brief dish ingredients or description...') }}"></textarea>
            </div>

            @if(count($secondaryLangs) > 0)
                <div style="margin-bottom: 1rem; padding: 0.85rem; background: var(--bg-body); border: 1px dashed var(--border-color); border-radius: 12px;">
                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.65rem;">
                        <i class="fa-solid fa-language"></i> {{ __('Description Translations for active languages') }}
                    </div>
                    <div class="grid-2" style="gap: 0.65rem;">
                        @foreach($secondaryLangs as $secLang)
                            <div>
                                <label class="form-label" style="font-size: 0.78rem;">{{ $secLang['name'] }} {{ $secLang['flag'] }}</label>
                                <textarea name="description_translations[{{ $secLang['code'] }}]" rows="2" class="form-textarea" style="font-size: 0.85rem;" placeholder="{{ __('Description in') }} {{ $secLang['name'] }}"></textarea>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Portions & Variations Section -->
            <div style="margin-bottom: 1.25rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                    <div>
                        <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-main);">
                            <i class="fa-solid fa-sliders" style="color: var(--primary);"></i> {{ __('Portions & Variations (Optional)') }}
                        </label>
                        <small style="color: var(--text-muted); font-size: 0.72rem;">{{ __('Add sizes or portion options') }}</small>
                    </div>
                    <button type="button" class="btn btn-secondary" style="padding: 0.3rem 0.65rem; font-size: 0.75rem;" onclick="addVariationRow('new')">
                        <i class="fa-solid fa-plus"></i> {{ __('Add Portion') }}
                    </button>
                </div>
                <div id="new_variations_container" style="display: flex; flex-direction: column; gap: 0.5rem;"></div>
            </div>

            <!-- Dietary & Allergens -->
            <div style="margin-bottom: 1rem;">
                <label class="form-label">{{ __('Dietary Tags') }}</label>
                <div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
                    @foreach(['vegan', 'vegetarian', 'gluten_free', 'halal', 'chef_special', 'spicy'] as $dtag)
                        <label style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.78rem; background: var(--input-bg); border: 1px solid var(--border-color); padding: 0.25rem 0.55rem; border-radius: 8px; cursor: pointer; color: var(--text-main);">
                            <input type="checkbox" name="dietary_tags[]" value="{{ $dtag }}"> {{ str_replace('_', ' ', strtoupper($dtag)) }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label class="form-label">{{ __('EU Allergens Tagging') }}</label>
                <div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
                    @foreach($allergens as $allg)
                        <label style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.78rem; background: var(--input-bg); border: 1px solid var(--border-color); padding: 0.25rem 0.55rem; border-radius: 8px; cursor: pointer; color: var(--text-main);">
                            <input type="checkbox" name="allergens[]" value="{{ $allg->id }}"> {{ $allg->icon }} {{ $allg->name }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="grid-3" style="margin-bottom: 1.5rem;">
                <div>
                    <label class="form-label" style="font-size: 0.75rem;">{{ __('Calories (kcal)') }}</label>
                    <input type="number" name="calories" placeholder="450" class="form-input">
                </div>
                <div>
                    <label class="form-label" style="font-size: 0.75rem;">{{ __('Prep Mins') }}</label>
                    <input type="number" name="preparation_time_min" placeholder="15" class="form-input">
                </div>
                <div style="display: flex; align-items: flex-end; padding-bottom: 0.35rem; gap: 1rem; flex-wrap: wrap;">
                    <label style="font-size: 0.82rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: var(--text-main);">
                        <input type="checkbox" name="is_featured" value="1"> ★ {{ __('Featured Dish') }}
                    </label>
                    <label style="font-size: 0.82rem; font-weight: 700; display: flex; align-items: center; gap: 0.45rem; cursor: pointer; color: var(--text-main);">
                        <input type="checkbox" name="is_daily_offer" value="1">
                        <span style="color: #ef4444;"><i class="fa-solid fa-fire-flame-curved"></i></span> {{ __('Daily Offer / Special of the Day') }}
                    </label>
                </div>
            </div>

            <!-- AI Waiter Metadata & Priority Settings -->
            <div style="background: rgba(139, 92, 246, 0.05); border: 1.5px dashed rgba(139, 92, 246, 0.3); border-radius: 14px; padding: 1.1rem; margin-bottom: 1.5rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.85rem;">
                    <div style="font-weight: 700; font-size: 0.88rem; color: #8b5cf6; display: flex; align-items: center; gap: 0.45rem;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('AI Waiter Recommendation Settings') }}
                    </div>
                    <label style="font-size: 0.8rem; font-weight: 700; display: flex; align-items: center; gap: 0.4rem; cursor: pointer; color: var(--text-main);">
                        <input type="checkbox" name="ai_priority" value="1"> ★ {{ __('AI Promoted') }}
                    </label>
                </div>
                <div class="grid-3" style="margin-bottom: 0.75rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.75rem;">{{ __('AI Priority (1-100)') }}</label>
                        <input type="number" name="ai_priority_level" min="1" max="100" value="50" class="form-input" placeholder="50">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.75rem;">{{ __('AI Group') }}</label>
                        <select name="ai_group" class="form-select">
                            <option value="">{{ __('None (Standard)') }}</option>
                            <option value="bestseller">{{ __('AI Bestseller') }}</option>
                            <option value="chef_choice">{{ __('Chef Recommendation') }}</option>
                            <option value="high_margin">{{ __('High Margin') }}</option>
                            <option value="new_arrival">{{ __('New Product') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.75rem;">{{ __('Spiciness Level') }}</label>
                        <select name="ai_spicy_level" class="form-select">
                            <option value="0">{{ __('0 - Not Spicy') }}</option>
                            <option value="1">{{ __('1 - Mild / Light') }}</option>
                            <option value="2">{{ __('2 - Medium Spicy') }}</option>
                            <option value="3">{{ __('3 - Hot / Very Spicy') }}</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="form-label" style="font-size: 0.75rem;">{{ __('AI Keywords / Flavor Tags (comma separated)') }}</label>
                    <input type="text" name="ai_tags" placeholder="{{ __('e.g. meat, savory, juicy, dinner, signature') }}" class="form-input">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('newProductModal').style.display='none'">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('Create Dish') }}</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Product -->
<div id="editProductModal" class="modern-modal-overlay" style="display: none;">
    <div class="modal-box-responsive">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0;">{{ __('Edit Dish') }}</h3>
            <button onclick="document.getElementById('editProductModal').style.display='none'" style="background: none; border: none; color: var(--text-muted); font-size: 1.25rem; cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form id="editProductForm" method="POST" enctype="multipart/form-data">
            @csrf
            <div style="margin-bottom: 1rem;">
                <label class="form-label">{{ __('Category') }} *</label>
                <select id="edit_prod_category_id" name="category_id" required class="form-select">
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid-3" style="margin-bottom: 1rem;">
                <div>
                    <label class="form-label">{{ __('Dish Name') }} ({{ $primaryLang['name'] }} {{ $primaryLang['flag'] }}) *</label>
                    <input type="text" id="edit_prod_name" name="name" required class="form-input">
                </div>
                <div>
                    <label class="form-label">{{ __('Price') }} ({{ $vendor->currency }}) *</label>
                    <input type="number" id="edit_prod_price" name="price" step="100" required class="form-input">
                </div>
                <div>
                    <label class="form-label" style="color: #ef4444;">
                        <i class="fa-solid fa-tag"></i> {{ __('Discount Price') }}
                    </label>
                    <input type="number" id="edit_prod_discount_price" name="discount_price" step="100" placeholder="{{ __('e.g. 2500') }}" class="form-input" style="border-color: rgba(239, 68, 68, 0.4);">
                </div>
            </div>

            <!-- Happy Hour & Discount Schedule Section (Edit) -->
            <div style="margin-bottom: 1.25rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="width: 28px; height: 28px; border-radius: 8px; background: rgba(239, 68, 68, 0.15); color: #ef4444; display: inline-flex; align-items: center; justify-content: center; font-size: 0.85rem;">
                            <i class="fa-solid fa-clock"></i>
                        </span>
                        <div>
                            <strong style="font-size: 0.85rem; color: var(--text-main);">{{ __('Discount Schedule (Happy Hour)') }}</strong>
                            <small style="display: block; color: var(--text-muted); font-size: 0.72rem;">{{ __('Set the days and hours when the discount applies') }}</small>
                        </div>
                    </div>
                    <label style="font-size: 0.78rem; display: flex; align-items: center; gap: 0.4rem; cursor: pointer; color: var(--text-main); font-weight: 600;">
                        <input type="checkbox" id="edit_prod_is_discount_active" name="is_discount_active" value="1"> {{ __('Enable discount') }}
                    </label>
                </div>

                <!-- Day Selector Chips -->
                <div style="margin-bottom: 0.75rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem; flex-wrap: wrap; gap: 0.35rem;">
                        <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">{{ __('Days of Week') }}</label>
                        <div style="display: flex; gap: 0.35rem;">
                            <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setDiscountDaysPreset('edit', 'all')">{{ __('All') }}</button>
                            <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setDiscountDaysPreset('edit', 'weekdays')">{{ __('Mon-Fri') }}</button>
                            <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setDiscountDaysPreset('edit', 'weekends')">{{ __('Weekends') }}</button>
                        </div>
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
                        @foreach($weekDays as $key => $lbl)
                            <label style="display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.75rem; background: var(--input-bg); border: 1px solid var(--border-color); padding: 0.25rem 0.55rem; border-radius: 8px; cursor: pointer; color: var(--text-main);">
                                <input type="checkbox" class="edit-discount-day-checkbox" name="discount_days[]" value="{{ $key }}"> {{ $lbl }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- Time Window -->
                <div class="grid-2" style="margin-bottom: 0.35rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.75rem;">{{ __('Start Time') }}</label>
                        <input type="time" id="edit_prod_discount_start_time" name="discount_start_time" class="form-input" style="padding: 0.45rem 0.75rem;">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.75rem;">{{ __('End Time') }}</label>
                        <input type="time" id="edit_prod_discount_end_time" name="discount_end_time" class="form-input" style="padding: 0.45rem 0.75rem;">
                    </div>
                </div>
            </div>

            <!-- Branch Availability (Edit) -->
            <div style="margin-bottom: 1.25rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="width: 28px; height: 28px; border-radius: 8px; background: rgba(99, 102, 241, 0.15); color: #6366f1; display: inline-flex; align-items: center; justify-content: center; font-size: 0.85rem;">
                            <i class="fa-solid fa-code-branch"></i>
                        </span>
                        <div>
                            <strong style="font-size: 0.85rem; color: var(--text-main);">{{ __('Branch Availability') }}</strong>
                            <small style="display: block; color: var(--text-muted); font-size: 0.72rem;">{{ __('Select which branches this dish is available at') }}</small>
                        </div>
                    </div>
                    <div style="display: flex; gap: 0.35rem;">
                        <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setLocationsPreset('edit', true)">{{ __('All') }}</button>
                        <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setLocationsPreset('edit', false)">{{ __('Clear') }}</button>
                    </div>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                    @foreach($locations as $loc)
                        <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.78rem; background: var(--input-bg); border: 1px solid var(--border-color); padding: 0.35rem 0.65rem; border-radius: 8px; cursor: pointer; color: var(--text-main); font-weight: 600;">
                            <input type="checkbox" class="edit-location-checkbox" name="locations[]" value="{{ $loc->id }}"> 📍 {{ $loc->name }}
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Schedule & Time Availability (Lunch Menu) (Edit) -->
            <div style="margin-bottom: 1.25rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                    <span style="width: 28px; height: 28px; border-radius: 8px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: inline-flex; align-items: center; justify-content: center; font-size: 0.85rem;">
                        <i class="fa-regular fa-clock"></i>
                    </span>
                    <div>
                        <strong style="font-size: 0.85rem; color: var(--text-main);">{{ __('Hours & Daily Availability (Lunch / Hours)') }}</strong>
                        <small style="display: block; color: var(--text-muted); font-size: 0.72rem;">{{ __('e.g. lunch available only 12:00 - 15:00 (leave empty for all day)') }}</small>
                    </div>
                </div>
                <div class="grid-2" style="margin-bottom: 0.75rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.75rem;">{{ __('Start Time') }}</label>
                        <input type="time" id="edit_prod_available_start_time" name="available_start_time" class="form-input" style="padding: 0.45rem 0.75rem;">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.75rem;">{{ __('End Time') }}</label>
                        <input type="time" id="edit_prod_available_end_time" name="available_end_time" class="form-input" style="padding: 0.45rem 0.75rem;">
                    </div>
                </div>
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem; flex-wrap: wrap; gap: 0.35rem;">
                        <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">{{ __('Available Days') }}</label>
                        <div style="display: flex; gap: 0.35rem;">
                            <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setAvailableDaysPreset('edit', 'all')">{{ __('All') }}</button>
                            <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setAvailableDaysPreset('edit', 'weekdays')">{{ __('Mon-Fri') }}</button>
                            <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setAvailableDaysPreset('edit', 'weekends')">{{ __('Weekends') }}</button>
                        </div>
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
                        @foreach($weekDays as $key => $lbl)
                            <label style="display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.75rem; background: var(--input-bg); border: 1px solid var(--border-color); padding: 0.25rem 0.55rem; border-radius: 8px; cursor: pointer; color: var(--text-main);">
                                <input type="checkbox" class="edit-available-day-checkbox" name="available_days[]" value="{{ $key }}"> {{ $lbl }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Order Channels / Type Availability (Edit) -->
            <div style="margin-bottom: 1.25rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                    <span style="width: 28px; height: 28px; border-radius: 8px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: inline-flex; align-items: center; justify-content: center; font-size: 0.85rem;">
                        <i class="fa-solid fa-truck-ramp-box"></i>
                    </span>
                    <div>
                        <strong style="font-size: 0.85rem; color: var(--text-main);">{{ __('Order Channels Availability') }}</strong>
                        <small style="display: block; color: var(--text-muted); font-size: 0.72rem;">{{ __('Select order channels where this dish is available') }}</small>
                    </div>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 0.65rem;">
                    <label style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.82rem; background: var(--input-bg); border: 1px solid var(--border-color); padding: 0.45rem 0.8rem; border-radius: 10px; cursor: pointer; color: var(--text-main); font-weight: 600;">
                        <input type="hidden" name="available_for_dine_in" value="0">
                        <input type="checkbox" id="edit_prod_available_for_dine_in" name="available_for_dine_in" value="1"> 🍽️ {{ __('Dine-in') }}
                    </label>
                    <label style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.82rem; background: var(--input-bg); border: 1px solid var(--border-color); padding: 0.45rem 0.8rem; border-radius: 10px; cursor: pointer; color: var(--text-main); font-weight: 600;">
                        <input type="hidden" name="available_for_takeaway" value="0">
                        <input type="checkbox" id="edit_prod_available_for_takeaway" name="available_for_takeaway" value="1"> 🥡 {{ __('Takeaway') }}
                    </label>
                    <label style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.82rem; background: var(--input-bg); border: 1px solid var(--border-color); padding: 0.45rem 0.8rem; border-radius: 10px; cursor: pointer; color: var(--text-main); font-weight: 600;">
                        <input type="hidden" name="available_for_delivery" value="0">
                        <input type="checkbox" id="edit_prod_available_for_delivery" name="available_for_delivery" value="1"> 🛵 {{ __('Delivery') }}
                    </label>
                </div>
            </div>

            @if(count($secondaryLangs) > 0)
                <div style="margin-bottom: 1rem; padding: 0.85rem; background: var(--bg-body); border: 1px dashed var(--border-color); border-radius: 12px;">
                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.65rem;">
                        <i class="fa-solid fa-language"></i> {{ __('Dish Name Translations for active languages') }}
                    </div>
                    <div class="grid-2" style="gap: 0.65rem;">
                        @foreach($secondaryLangs as $secLang)
                            <div>
                                <label class="form-label" style="font-size: 0.78rem;">{{ $secLang['name'] }} {{ $secLang['flag'] }}</label>
                                <input type="text" id="edit_prod_trans_{{ $secLang['code'] }}" name="name_translations[{{ $secLang['code'] }}]" placeholder="{{ __('Translation in') }} {{ $secLang['name'] }}" class="form-input" style="font-size: 0.85rem;">
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div style="margin-bottom: 1rem;">
                <label class="form-label">
                    <i class="fa-solid fa-image" style="color: var(--primary);"></i> {{ __('Dish Image (Upload New File or Change URL)') }}
                </label>
                <div class="grid-2" style="align-items: center;">
                    <div>
                        <input type="file" name="image_file" accept="image/*" class="form-input" style="padding: 0.45rem;">
                        <small style="color: var(--text-muted); font-size: 0.7rem; display: block; margin-top: 0.25rem;">📁 {{ __('Upload new image file') }}</small>
                    </div>
                    <div>
                        <input type="text" id="edit_prod_image" name="image" placeholder="{{ __('/storage/... or https://...') }}" class="form-input">
                        <small style="color: var(--text-muted); font-size: 0.7rem; display: block; margin-top: 0.25rem;">🔗 {{ __('Or edit image link / path') }}</small>
                    </div>
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label class="form-label">{{ __('Description') }} ({{ $primaryLang['name'] }} {{ $primaryLang['flag'] }})</label>
                <textarea id="edit_prod_description" name="description" rows="2" class="form-textarea" placeholder="{{ __('Brief dish ingredients or description...') }}"></textarea>
            </div>

            @if(count($secondaryLangs) > 0)
                <div style="margin-bottom: 1rem; padding: 0.85rem; background: var(--bg-body); border: 1px dashed var(--border-color); border-radius: 12px;">
                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.65rem;">
                        <i class="fa-solid fa-language"></i> {{ __('Description Translations for active languages') }}
                    </div>
                    <div class="grid-2" style="gap: 0.65rem;">
                        @foreach($secondaryLangs as $secLang)
                            <div>
                                <label class="form-label" style="font-size: 0.78rem;">{{ $secLang['name'] }} {{ $secLang['flag'] }}</label>
                                <textarea id="edit_prod_desc_trans_{{ $secLang['code'] }}" name="description_translations[{{ $secLang['code'] }}]" rows="2" class="form-textarea" style="font-size: 0.85rem;" placeholder="{{ __('Description in') }} {{ $secLang['name'] }}"></textarea>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Portions & Variations Section -->
            <div style="margin-bottom: 1.25rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                    <div>
                        <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-main);">
                            <i class="fa-solid fa-sliders" style="color: var(--primary);"></i> {{ __('Portions & Variations (Optional)') }}
                        </label>
                        <small style="color: var(--text-muted); font-size: 0.72rem;">{{ __('Add or edit portions and options') }}</small>
                    </div>
                    <button type="button" class="btn btn-secondary" style="padding: 0.3rem 0.65rem; font-size: 0.75rem;" onclick="addVariationRow('edit')">
                        <i class="fa-solid fa-plus"></i> {{ __('Add Portion') }}
                    </button>
                </div>
                <div id="edit_variations_container" style="display: flex; flex-direction: column; gap: 0.5rem;"></div>
            </div>

            <!-- Dietary & Allergens -->
            <div style="margin-bottom: 1rem;">
                <label class="form-label">{{ __('Dietary Tags') }}</label>
                <div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
                    @foreach(['vegan', 'vegetarian', 'gluten_free', 'halal', 'chef_special', 'spicy'] as $dtag)
                        <label style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.78rem; background: var(--input-bg); border: 1px solid var(--border-color); padding: 0.25rem 0.55rem; border-radius: 8px; cursor: pointer; color: var(--text-main);">
                            <input type="checkbox" class="edit-dietary-checkbox" name="dietary_tags[]" value="{{ $dtag }}"> {{ str_replace('_', ' ', strtoupper($dtag)) }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label class="form-label">{{ __('EU Allergens Tagging') }}</label>
                <div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
                    @foreach($allergens as $allg)
                        <label style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.78rem; background: var(--input-bg); border: 1px solid var(--border-color); padding: 0.25rem 0.55rem; border-radius: 8px; cursor: pointer; color: var(--text-main);">
                            <input type="checkbox" class="edit-allergen-checkbox" name="allergens[]" value="{{ $allg->id }}"> {{ $allg->icon }} {{ $allg->name }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="grid-3" style="margin-bottom: 1.5rem;">
                <div>
                    <label class="form-label" style="font-size: 0.75rem;">{{ __('Calories (kcal)') }}</label>
                    <input type="number" id="edit_prod_calories" name="calories" placeholder="450" class="form-input">
                </div>
                <div>
                    <label class="form-label" style="font-size: 0.75rem;">{{ __('Prep Mins') }}</label>
                    <input type="number" id="edit_prod_preparation_time_min" name="preparation_time_min" placeholder="15" class="form-input">
                </div>
                <div style="display: flex; align-items: flex-end; padding-bottom: 0.35rem; gap: 1rem; flex-wrap: wrap;">
                    <label style="font-size: 0.82rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: var(--text-main);">
                        <input type="checkbox" id="edit_prod_is_featured" name="is_featured" value="1"> ★ {{ __('Featured Dish') }}
                    </label>
                    <label style="font-size: 0.82rem; font-weight: 700; display: flex; align-items: center; gap: 0.45rem; cursor: pointer; color: var(--text-main);">
                        <input type="checkbox" id="edit_prod_is_daily_offer" name="is_daily_offer" value="1">
                        <span style="color: #ef4444;"><i class="fa-solid fa-fire-flame-curved"></i></span> {{ __('Daily Offer / Special of the Day') }}
                    </label>
                </div>
            </div>

            <!-- AI Waiter Metadata & Priority Settings -->
            <div style="background: rgba(139, 92, 246, 0.05); border: 1.5px dashed rgba(139, 92, 246, 0.3); border-radius: 14px; padding: 1.1rem; margin-bottom: 1.5rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.85rem;">
                    <div style="font-weight: 700; font-size: 0.88rem; color: #8b5cf6; display: flex; align-items: center; gap: 0.45rem;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('AI Waiter Recommendation Settings') }}
                    </div>
                    <label style="font-size: 0.8rem; font-weight: 700; display: flex; align-items: center; gap: 0.4rem; cursor: pointer; color: var(--text-main);">
                        <input type="checkbox" id="edit_prod_ai_priority" name="ai_priority" value="1"> ★ {{ __('AI Promoted') }}
                    </label>
                </div>
                <div class="grid-3" style="margin-bottom: 0.75rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.75rem;">{{ __('AI Priority (1-100)') }}</label>
                        <input type="number" id="edit_prod_ai_priority_level" name="ai_priority_level" min="1" max="100" class="form-input" placeholder="50">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.75rem;">{{ __('AI Group') }}</label>
                        <select id="edit_prod_ai_group" name="ai_group" class="form-select">
                            <option value="">{{ __('None (Standard)') }}</option>
                            <option value="bestseller">{{ __('AI Bestseller') }}</option>
                            <option value="chef_choice">{{ __('Chef Recommendation') }}</option>
                            <option value="high_margin">{{ __('High Margin') }}</option>
                            <option value="new_arrival">{{ __('New Product') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.75rem;">{{ __('Spiciness Level') }}</label>
                        <select id="edit_prod_ai_spicy_level" name="ai_spicy_level" class="form-select">
                            <option value="0">{{ __('0 - Not Spicy') }}</option>
                            <option value="1">{{ __('1 - Mild / Light') }}</option>
                            <option value="2">{{ __('2 - Medium Spicy') }}</option>
                            <option value="3">{{ __('3 - Hot / Very Spicy') }}</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="form-label" style="font-size: 0.75rem;">{{ __('AI Keywords / Flavor Tags (comma separated)') }}</label>
                    <input type="text" id="edit_prod_ai_tags" name="ai_tags" placeholder="{{ __('e.g. meat, savory, juicy, dinner, signature') }}" class="form-input">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('editProductModal').style.display='none'">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('Update Dish') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
    function setDiscountDaysPreset(prefix, type) {
        const checkboxes = document.querySelectorAll(`.${prefix}-discount-day-checkbox`);
        checkboxes.forEach(cb => {
            if (type === 'all') {
                cb.checked = true;
            } else if (type === 'weekdays') {
                cb.checked = ['mon', 'tue', 'wed', 'thu', 'fri'].includes(cb.value);
            } else if (type === 'weekends') {
                cb.checked = ['sat', 'sun'].includes(cb.value);
            }
        });
    }

    function setLocationsPreset(prefix, isChecked) {
        document.querySelectorAll(`.${prefix}-location-checkbox`).forEach(cb => {
            cb.checked = isChecked;
        });
    }

    function setAvailableDaysPreset(prefix, type) {
        const checkboxes = document.querySelectorAll(`.${prefix}-available-day-checkbox`);
        checkboxes.forEach(cb => {
            if (type === 'all') {
                cb.checked = true;
            } else if (type === 'weekdays') {
                cb.checked = ['mon', 'tue', 'wed', 'thu', 'fri'].includes(cb.value);
            } else if (type === 'weekends') {
                cb.checked = ['sat', 'sun'].includes(cb.value);
            }
        });
    }

    const activeSecondaryLangs = @json($secondaryLangs);

    function editCategory(cat) {
        document.getElementById('editCategoryForm').action = "/admin/menu/categories/" + cat.id;
        document.getElementById('edit_cat_name').value = cat.name || '';
        if (activeSecondaryLangs && activeSecondaryLangs.length > 0) {
            activeSecondaryLangs.forEach(lang => {
                const el = document.getElementById('edit_cat_trans_' + lang.code);
                if (el) {
                    el.value = (cat.name_translations && cat.name_translations[lang.code]) ? cat.name_translations[lang.code] : '';
                }
            });
        }
        document.getElementById('edit_cat_description').value = cat.description || '';
        document.getElementById('editCategoryModal').style.display = 'flex';
    }

    function editProduct(prod) {
        document.getElementById('editProductForm').action = "/admin/menu/products/" + prod.id;
        document.getElementById('edit_prod_category_id').value = prod.category_id;
        document.getElementById('edit_prod_name').value = prod.name || '';
        document.getElementById('edit_prod_price').value = prod.price || 0;
        
        if (activeSecondaryLangs && activeSecondaryLangs.length > 0) {
            activeSecondaryLangs.forEach(lang => {
                const nameEl = document.getElementById('edit_prod_trans_' + lang.code);
                if (nameEl) {
                    nameEl.value = (prod.name_translations && prod.name_translations[lang.code]) ? prod.name_translations[lang.code] : '';
                }
                const descEl = document.getElementById('edit_prod_desc_trans_' + lang.code);
                if (descEl) {
                    descEl.value = (prod.description_translations && prod.description_translations[lang.code]) ? prod.description_translations[lang.code] : '';
                }
            });
        }
        
        document.getElementById('edit_prod_image').value = (prod.image && !prod.image.includes('default-dish')) ? prod.image : '';
        document.getElementById('edit_prod_description').value = prod.description || '';
        
        // Dietary tags
        const tags = prod.dietary_tags || [];
        document.querySelectorAll('.edit-dietary-checkbox').forEach(cb => {
            cb.checked = tags.includes(cb.value);
        });
        
        // Allergens
        const allergenIds = prod.allergens ? prod.allergens.map(a => a.id) : [];
        document.querySelectorAll('.edit-allergen-checkbox').forEach(cb => {
            cb.checked = allergenIds.includes(parseInt(cb.value));
        });
        
        document.getElementById('edit_prod_calories').value = prod.calories || '';
        document.getElementById('edit_prod_preparation_time_min').value = prod.preparation_time_min || '';
        document.getElementById('edit_prod_is_featured').checked = !!prod.is_featured;
        const isDailyOfferItem = (prod.daily_offers && prod.daily_offers.some(o => o.is_active)) || ({{ $vendor->featured_product_id ?? 'null' }} == prod.id);
        const editDailyOfferCb = document.getElementById('edit_prod_is_daily_offer');
        if (editDailyOfferCb) {
            editDailyOfferCb.checked = !!isDailyOfferItem;
        }

        // AI Waiter metadata
        document.getElementById('edit_prod_ai_priority').checked = !!prod.ai_priority;
        document.getElementById('edit_prod_ai_priority_level').value = prod.ai_priority_level || 50;
        document.getElementById('edit_prod_ai_group').value = prod.ai_group || '';
        document.getElementById('edit_prod_ai_spicy_level').value = (prod.ai_spicy_level !== undefined && prod.ai_spicy_level !== null) ? prod.ai_spicy_level : 0;
        document.getElementById('edit_prod_ai_tags').value = Array.isArray(prod.ai_tags) ? prod.ai_tags.join(', ') : (prod.ai_tags || '');

        // Branch location availability checkboxes
        const overrides = prod.overrides || [];
        document.querySelectorAll('.edit-location-checkbox').forEach(cb => {
            const locId = parseInt(cb.value);
            const ov = overrides.find(o => parseInt(o.location_id) === locId);
            if (ov && ov.is_available !== undefined && ov.is_available !== null) {
                cb.checked = !!ov.is_available;
            } else {
                cb.checked = (prod.is_available !== false && prod.is_available !== 0);
            }
        });

        // Time schedule (Lunch/Hours)
        document.getElementById('edit_prod_available_start_time').value = prod.available_start_time ? prod.available_start_time.substring(0, 5) : '';
        document.getElementById('edit_prod_available_end_time').value = prod.available_end_time ? prod.available_end_time.substring(0, 5) : '';

        const availDays = prod.available_days || [];
        document.querySelectorAll('.edit-available-day-checkbox').forEach(cb => {
            cb.checked = availDays.length === 0 || availDays.includes(cb.value);
        });

        // Channel availability
        document.getElementById('edit_prod_available_for_dine_in').checked = prod.available_for_dine_in !== false && prod.available_for_dine_in !== 0;
        document.getElementById('edit_prod_available_for_takeaway').checked = prod.available_for_takeaway !== false && prod.available_for_takeaway !== 0;
        document.getElementById('edit_prod_available_for_delivery').checked = prod.available_for_delivery !== false && prod.available_for_delivery !== 0;

        // Discount & Happy Hour fields
        document.getElementById('edit_prod_discount_price').value = prod.discount_price || '';
        document.getElementById('edit_prod_discount_start_time').value = prod.discount_start_time ? prod.discount_start_time.substring(0, 5) : '';
        document.getElementById('edit_prod_discount_end_time').value = prod.discount_end_time ? prod.discount_end_time.substring(0, 5) : '';
        document.getElementById('edit_prod_is_discount_active').checked = prod.is_discount_active !== false && prod.is_discount_active !== 0;

        const discountDays = prod.discount_days || [];
        document.querySelectorAll('.edit-discount-day-checkbox').forEach(cb => {
            cb.checked = discountDays.length === 0 || discountDays.includes(cb.value);
        });
        
        // Populate Variations
        const editContainer = document.getElementById('edit_variations_container');
        if (editContainer) {
            editContainer.innerHTML = '';
            if (prod.variations && prod.variations.length > 0) {
                prod.variations.forEach(v => {
                    addVariationRow('edit', v);
                });
            }
        }

        document.getElementById('editProductModal').style.display = 'flex';
    }

    let variationCounter = 0;

    function addVariationRow(prefix, varData = null) {
        const container = document.getElementById(prefix + '_variations_container');
        if (!container) return;

        const idx = variationCounter++;
        const id = varData ? (varData.id || '') : '';
        const name = varData ? (varData.name || '') : '';
        const price = varData ? (varData.price || '') : '';
        const isDefault = varData ? !!varData.is_default : (container.children.length === 0);

        let secInputsHtml = '';
        if (activeSecondaryLangs && activeSecondaryLangs.length > 0) {
            secInputsHtml += `<div class="grid-2" style="gap: 0.5rem; margin-top: 0.25rem;">`;
            activeSecondaryLangs.forEach(lang => {
                let transVal = '';
                if (varData) {
                    if (varData.name_translations && varData.name_translations[lang.code]) {
                        transVal = varData.name_translations[lang.code];
                    } else if (lang.code === 'hy' && varData.hy_name) {
                        transVal = varData.hy_name;
                    } else if (lang.code === 'ru' && varData.ru_name) {
                        transVal = varData.ru_name;
                    }
                }
                secInputsHtml += `
                    <div>
                        <input type="text" name="variations[${idx}][name_translations][${lang.code}]" value="${transVal}" placeholder="${lang.name} ${lang.flag}" class="form-input" style="padding: 0.4rem 0.65rem; font-size: 0.8rem;">
                    </div>
                `;
            });
            secInputsHtml += `</div>`;
        }

        const row = document.createElement('div');
        row.className = 'variation-row';
        row.style = 'display: flex; flex-direction: column; gap: 0.5rem; padding: 0.75rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; margin-bottom: 0.35rem;';
        row.innerHTML = `
            <input type="hidden" name="variations[${idx}][id]" value="${id}">
            <input type="hidden" name="variations[${idx}][is_default]" class="var-is-default-input" value="${isDefault ? '1' : '0'}">
            
            <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                <input type="text" name="variations[${idx}][name]" value="${name}" placeholder="{{ $primaryLang['name'] }} {{ $primaryLang['flag'] }}" required class="form-input" style="flex: 2; min-width: 140px; padding: 0.45rem 0.65rem; font-size: 0.85rem;">
                <input type="number" name="variations[${idx}][price]" value="${price}" placeholder="{{ __('Price') }}" step="100" required class="form-input" style="flex: 1; min-width: 90px; padding: 0.45rem 0.65rem; font-size: 0.85rem;">
                <label style="display: flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; color: var(--text-muted); cursor: pointer; white-space: nowrap; flex-shrink: 0;" title="{{ __('Default Portion') }}">
                    <input type="radio" name="${prefix}_default_radio" ${isDefault ? 'checked' : ''} onchange="setDefaultVariation(this)">
                    <span>{{ __('Default') }}</span>
                </label>
                <button type="button" class="btn btn-danger" style="padding: 0.4rem 0.6rem; font-size: 0.75rem;" onclick="this.closest('.variation-row').remove()" title="{{ __('Remove portion') }}">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>

            ${secInputsHtml}
        `;
        container.appendChild(row);
    }

    function setDefaultVariation(radioEl) {
        const container = radioEl.closest('#new_variations_container, #edit_variations_container');
        if (!container) return;
        container.querySelectorAll('.variation-row').forEach(row => {
            const rowRadio = row.querySelector('input[type="radio"]');
            const defaultInput = row.querySelector('.var-is-default-input');
            if (rowRadio && defaultInput) {
                defaultInput.value = rowRadio.checked ? '1' : '0';
            }
        });
    }

    function openNewProductModal() {
        const container = document.getElementById('new_variations_container');
        if (container) container.innerHTML = '';
        document.getElementById('newProductModal').style.display = 'flex';
    }

    function menuManagerApp() {
        return {
            viewMode: localStorage.getItem('qrmenu_menu_view_mode') || 'grouped',
            density: localStorage.getItem('qrmenu_menu_density') || 'comfortable',
            currency: '{{ $vendor->currency }}',
            csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',

            // Local datasets
            allDishes: @json($dishesJson),
            categories: @json($categoriesJson),

            // Filters
            searchQuery: '',
            filterCategoryId: '',
            stockFilter: 'all',
            channelFilter: 'all',
            minPrice: '',
            maxPrice: '',

            // Table Pagination & Sorting
            currentPage: 1,
            perPage: 25,
            sortBy: 'sort_order',
            sortAsc: true,

            // Selection
            selectedDishIds: [],

            // Modals
            showBulkEditModal: false,
            showBulkDeleteModal: false,
            showSafeDeleteCategoryModal: false,

            // Bulk edit form
            bulkAction: {
                price_action: 'none',
                price_value: '',
                price_rounding: 'none',
                category_id: '',
                is_available: '',
                is_featured: '',
                available_for_dine_in: '',
                available_for_takeaway: '',
                available_for_delivery: '',
            },
            bulkSubmitting: false,
            bulkConfirmDeleteChecked: false,

            // Category safe delete form
            deletingCategory: null,
            reassignCategoryId: '',
            forceDeleteCategory: false,
            categoryActionSubmitting: false,

            // Drag and drop category
            draggedCategoryIndex: null,

            // Inline editing
            editingDishId: null,
            editingField: null,
            editingValue: '',

            // Toast feedback
            toast: {
                show: false,
                message: '',
                type: 'success',
            },

            init() {
                this.$watch('viewMode', val => localStorage.setItem('qrmenu_menu_view_mode', val));
                this.$watch('density', val => localStorage.setItem('qrmenu_menu_density', val));
                this.$watch('searchQuery', () => this.currentPage = 1);
                this.$watch('filterCategoryId', () => this.currentPage = 1);
                this.$watch('stockFilter', () => this.currentPage = 1);
                this.$watch('channelFilter', () => this.currentPage = 1);
                this.$watch('minPrice', () => this.currentPage = 1);
                this.$watch('maxPrice', () => this.currentPage = 1);
                this.$watch('perPage', () => this.currentPage = 1);
            },

            showNotification(msg, type = 'success') {
                this.toast.message = msg;
                this.toast.type = type;
                this.toast.show = true;
                setTimeout(() => { this.toast.show = false; }, 3500);
            },

            resetFilters() {
                this.searchQuery = '';
                this.filterCategoryId = '';
                this.stockFilter = 'all';
                this.channelFilter = 'all';
                this.minPrice = '';
                this.maxPrice = '';
                this.currentPage = 1;
            },

            matchesFilter(dishId) {
                const dish = this.allDishes.find(d => d.id === dishId);
                if (!dish) return true;

                if (this.filterCategoryId && dish.category_id != this.filterCategoryId) {
                    return false;
                }
                if (this.stockFilter === 'in_stock' && !dish.is_available) {
                    return false;
                }
                if (this.stockFilter === 'out_of_stock' && dish.is_available) {
                    return false;
                }
                if (this.channelFilter === 'dine_in' && !dish.available_for_dine_in) {
                    return false;
                }
                if (this.channelFilter === 'takeaway' && !dish.available_for_takeaway) {
                    return false;
                }
                if (this.channelFilter === 'delivery' && !dish.available_for_delivery) {
                    return false;
                }
                if (this.minPrice !== '' && dish.price < parseFloat(this.minPrice)) {
                    return false;
                }
                if (this.maxPrice !== '' && dish.price > parseFloat(this.maxPrice)) {
                    return false;
                }
                if (this.searchQuery && this.searchQuery.trim() !== '') {
                    const q = this.searchQuery.toLowerCase().trim();
                    const name = (dish.name || '').toLowerCase();
                    const catName = (dish.category_name || '').toLowerCase();
                    if (!name.includes(q) && !catName.includes(q)) {
                        return false;
                    }
                }
                return true;
            },

            filteredDishes() {
                let list = this.allDishes.filter(d => this.matchesFilter(d.id));
                list.sort((a, b) => {
                    let valA = a[this.sortBy];
                    let valB = b[this.sortBy];
                    if (typeof valA === 'string') {
                        valA = valA.toLowerCase();
                        valB = (valB || '').toLowerCase();
                        return this.sortAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
                    }
                    valA = Number(valA || 0);
                    valB = Number(valB || 0);
                    return this.sortAsc ? valA - valB : valB - valA;
                });
                return list;
            },

            paginatedDishes() {
                const filtered = this.filteredDishes();
                const start = (this.currentPage - 1) * this.perPage;
                return filtered.slice(start, start + this.perPage);
            },

            totalPages() {
                return Math.ceil(this.filteredDishes().length / this.perPage) || 1;
            },

            toggleSort(col) {
                if (this.sortBy === col) {
                    this.sortAsc = !this.sortAsc;
                } else {
                    this.sortBy = col;
                    this.sortAsc = true;
                }
            },

            isAllSelected() {
                const visible = this.filteredDishes();
                return visible.length > 0 && visible.every(d => this.selectedDishIds.includes(d.id));
            },

            toggleSelectAll() {
                const visible = this.filteredDishes();
                if (this.isAllSelected()) {
                    const visibleIds = visible.map(d => d.id);
                    this.selectedDishIds = this.selectedDishIds.filter(id => !visibleIds.includes(id));
                } else {
                    const idsToAdd = visible.map(d => d.id).filter(id => !this.selectedDishIds.includes(id));
                    this.selectedDishIds = [...this.selectedDishIds, ...idsToAdd];
                }
            },

            isCategorySelected(catId) {
                const catDishes = this.allDishes.filter(d => d.category_id === catId);
                return catDishes.length > 0 && catDishes.every(d => this.selectedDishIds.includes(d.id));
            },

            toggleSelectCategory(catId) {
                const catDishes = this.allDishes.filter(d => d.category_id === catId);
                const catDishIds = catDishes.map(d => d.id);
                if (this.isCategorySelected(catId)) {
                    this.selectedDishIds = this.selectedDishIds.filter(id => !catDishIds.includes(id));
                } else {
                    const toAdd = catDishIds.filter(id => !this.selectedDishIds.includes(id));
                    this.selectedDishIds = [...this.selectedDishIds, ...toAdd];
                }
            },

            getDishName(dishId, defaultName) {
                const d = this.allDishes.find(x => x.id === dishId);
                return d ? d.name : defaultName;
            },

            getDishPrice(dishId, defaultPriceText) {
                const d = this.allDishes.find(x => x.id === dishId);
                return d ? this.formatNumber(d.price) + ' ' + this.currency : defaultPriceText;
            },

            formatNumber(num) {
                if (num === null || num === undefined) return '0';
                return Number(num).toLocaleString('en-US');
            },

            // Inline Editing
            startInlineEdit(dishId, field, currentValue) {
                this.editingDishId = dishId;
                this.editingField = field;
                this.editingValue = currentValue;
            },

            cancelInlineEdit() {
                this.editingDishId = null;
                this.editingField = null;
                this.editingValue = '';
            },

            async saveInlineEdit(dishId) {
                if (!this.editingDishId || !this.editingField) return;
                const dish = this.allDishes.find(d => d.id === dishId);
                if (!dish) return;

                const originalValue = dish[this.editingField];
                const newValue = this.editingValue;
                const fieldName = this.editingField;

                // Optimistic update
                dish[fieldName] = fieldName === 'price' || fieldName === 'sort_order' ? Number(newValue) : newValue;
                this.cancelInlineEdit();

                try {
                    const res = await fetch(`/admin/menu/products/${dishId}/inline-update`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            field: fieldName,
                            value: newValue,
                        }),
                    });

                    const data = await res.json();
                    if (!res.ok || !data.success) {
                        throw new Error(data.message || 'Error updating product');
                    }
                    this.showNotification(data.message || '{{ __('Saved successfully') }}');
                } catch (e) {
                    dish[fieldName] = originalValue; // Rollback
                    this.showNotification(e.message || '{{ __('Failed to save update') }}', 'error');
                }
            },

            async toggleDishStock(dish) {
                const original = dish.is_available;
                dish.is_available = !dish.is_available; // Optimistic

                try {
                    const res = await fetch(`/admin/menu/products/${dish.id}/inline-update`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            field: 'is_available',
                            value: dish.is_available ? 1 : 0,
                        }),
                    });

                    const data = await res.json();
                    if (!res.ok || !data.success) {
                        throw new Error(data.message || 'Error toggling stock');
                    }
                    this.showNotification(dish.is_available ? '{{ __('Marked as In Stock') }}' : '{{ __('Marked as Out of Stock') }}');
                } catch (e) {
                    dish.is_available = original; // Rollback
                    this.showNotification(e.message || '{{ __('Failed to update stock status') }}', 'error');
                }
            },

            async deleteSingleProduct(dish) {
                if (!confirm(`{{ __('Delete dish') }} "${dish.name}"?`)) return;

                try {
                    const res = await fetch(`/admin/menu/products/${dish.id}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json',
                        },
                    });

                    // Remove dish locally
                    this.allDishes = this.allDishes.filter(d => d.id !== dish.id);
                    this.selectedDishIds = this.selectedDishIds.filter(id => id !== dish.id);
                    // Update category dish count
                    const cat = this.categories.find(c => c.id === dish.category_id);
                    if (cat && cat.products_count > 0) cat.products_count--;

                    this.showNotification('{{ __('Product deleted successfully.') }}');
                } catch (e) {
                    this.showNotification('{{ __('Failed to delete dish') }}', 'error');
                }
            },

            // Bulk Actions
            openBulkEditModal() {
                this.bulkAction = {
                    price_action: 'none',
                    price_value: '',
                    price_rounding: 'none',
                    category_id: '',
                    is_available: '',
                    is_featured: '',
                    available_for_dine_in: '',
                    available_for_takeaway: '',
                    available_for_delivery: '',
                };
                this.showBulkEditModal = true;
            },

            getBulkPricePreviewHtml() {
                const sampleDish = this.allDishes.find(d => this.selectedDishIds.includes(d.id)) || this.allDishes[0];
                const basePrice = sampleDish ? sampleDish.price : 1500;
                const action = this.bulkAction.price_action;
                const val = parseFloat(this.bulkAction.price_value) || 0;
                let newPrice = basePrice;

                if (action === 'set') {
                    newPrice = val;
                } else if (action === 'increase_amount') {
                    newPrice = basePrice + val;
                } else if (action === 'decrease_amount') {
                    newPrice = Math.max(0, basePrice - val);
                } else if (action === 'increase_percent') {
                    newPrice = basePrice * (1 + val / 100);
                } else if (action === 'decrease_percent') {
                    newPrice = Math.max(0, basePrice * (1 - val / 100));
                }

                // Rounding
                const roundRule = this.bulkAction.price_rounding;
                if (roundRule === 'nearest_10') newPrice = Math.round(newPrice / 10) * 10;
                else if (roundRule === 'nearest_50') newPrice = Math.round(newPrice / 50) * 50;
                else if (roundRule === 'nearest_100') newPrice = Math.round(newPrice / 100) * 100;
                else if (roundRule === 'integer') newPrice = Math.round(newPrice);

                return `<span style="opacity: 0.8;">Sample (${sampleDish ? sampleDish.name : 'Dish'}):</span> 
                        <del style="color: var(--text-muted);">${this.formatNumber(basePrice)} ${this.currency}</del> 
                        <i class="fa-solid fa-arrow-right" style="font-size: 0.75rem; margin: 0 0.25rem;"></i> 
                        <strong style="color: var(--primary); font-size: 0.95rem;">${this.formatNumber(newPrice)} ${this.currency}</strong>`;
            },

            async submitBulkUpdate() {
                if (this.selectedDishIds.length === 0) return;
                this.bulkSubmitting = true;

                const payload = {
                    product_ids: this.selectedDishIds,
                    price_action: this.bulkAction.price_action,
                    price_value: this.bulkAction.price_value !== '' ? parseFloat(this.bulkAction.price_value) : null,
                    price_rounding: this.bulkAction.price_rounding,
                    category_id: this.bulkAction.category_id ? parseInt(this.bulkAction.category_id) : null,
                    is_available: this.bulkAction.is_available !== '' ? this.bulkAction.is_available === '1' : null,
                    is_featured: this.bulkAction.is_featured !== '' ? this.bulkAction.is_featured === '1' : null,
                    available_for_dine_in: this.bulkAction.available_for_dine_in !== '' ? this.bulkAction.available_for_dine_in === '1' : null,
                    available_for_takeaway: this.bulkAction.available_for_takeaway !== '' ? this.bulkAction.available_for_takeaway === '1' : null,
                    available_for_delivery: this.bulkAction.available_for_delivery !== '' ? this.bulkAction.available_for_delivery === '1' : null,
                };

                try {
                    const res = await fetch('/admin/menu/products/bulk-update', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(payload),
                    });

                    const data = await res.json();
                    if (!res.ok || !data.success) {
                        throw new Error(data.message || 'Failed to update products');
                    }

                    this.showNotification(data.message);
                    this.showBulkEditModal = false;
                    setTimeout(() => window.location.reload(), 600);
                } catch (e) {
                    this.showNotification(e.message, 'error');
                } finally {
                    this.bulkSubmitting = false;
                }
            },

            async quickBulkStock(isAvailable) {
                if (this.selectedDishIds.length === 0) return;

                try {
                    const res = await fetch('/admin/menu/products/bulk-update', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            product_ids: this.selectedDishIds,
                            is_available: isAvailable,
                        }),
                    });

                    const data = await res.json();
                    if (!res.ok || !data.success) {
                        throw new Error(data.message || 'Error updating stock');
                    }

                    // Update locally
                    this.allDishes.forEach(d => {
                        if (this.selectedDishIds.includes(d.id)) {
                            d.is_available = isAvailable;
                        }
                    });

                    this.showNotification(data.message);
                } catch (e) {
                    this.showNotification(e.message, 'error');
                }
            },

            async submitBulkDelete() {
                if (this.selectedDishIds.length === 0 || !this.bulkConfirmDeleteChecked) return;
                this.bulkSubmitting = true;

                try {
                    const res = await fetch('/admin/menu/products/bulk-delete', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            product_ids: this.selectedDishIds,
                        }),
                    });

                    const data = await res.json();
                    if (!res.ok || !data.success) {
                        throw new Error(data.message || 'Failed to delete products');
                    }

                    this.showNotification(data.message);
                    this.showBulkDeleteModal = false;
                    setTimeout(() => window.location.reload(), 600);
                } catch (e) {
                    this.showNotification(e.message, 'error');
                } finally {
                    this.bulkSubmitting = false;
                }
            },

            // Category Reordering & Safeguards
            async moveCategory(index, direction) {
                const targetIndex = index + direction;
                if (targetIndex < 0 || targetIndex >= this.categories.length) return;

                const temp = this.categories[index];
                this.categories[index] = this.categories[targetIndex];
                this.categories[targetIndex] = temp;

                // Reassign sort orders
                this.categories.forEach((cat, idx) => {
                    cat.sort_order = idx;
                });

                const orderPayload = this.categories.map(c => ({
                    id: c.id,
                    sort_order: c.sort_order,
                }));

                try {
                    const res = await fetch('/admin/menu/categories/reorder', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ order: orderPayload }),
                    });
                    const data = await res.json();
                    if (!res.ok || !data.success) throw new Error(data.message);
                    this.showNotification(data.message);
                } catch (e) {
                    this.showNotification(e.message || '{{ __('Failed to reorder categories') }}', 'error');
                }
            },

            onCategoryDragStart(event, index) {
                this.draggedCategoryIndex = index;
                event.dataTransfer.effectAllowed = 'move';
            },

            onCategoryDrop(event, dropIndex) {
                if (this.draggedCategoryIndex === null || this.draggedCategoryIndex === dropIndex) return;
                const item = this.categories.splice(this.draggedCategoryIndex, 1)[0];
                this.categories.splice(dropIndex, 0, item);
                this.draggedCategoryIndex = null;

                // Persist new ordering
                this.categories.forEach((cat, idx) => {
                    cat.sort_order = idx;
                });

                const orderPayload = this.categories.map(c => ({
                    id: c.id,
                    sort_order: c.sort_order,
                }));

                fetch('/admin/menu/categories/reorder', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ order: orderPayload }),
                })
                .then(r => r.json())
                .then(d => this.showNotification(d.message || '{{ __('Display order updated') }}'))
                .catch(e => this.showNotification('{{ __('Error reordering') }}', 'error'));
            },

            async duplicateCategory(cat) {
                if (!confirm(`{{ __('Duplicate category') }} "${cat.name}" {{ __('and all its dishes?') }}`)) return;

                try {
                    const res = await fetch(`/admin/menu/categories/${cat.id}/duplicate`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json',
                        },
                    });

                    const data = await res.json();
                    if (!res.ok || !data.success) throw new Error(data.message);
                    this.showNotification(data.message);
                    setTimeout(() => window.location.reload(), 600);
                } catch (e) {
                    this.showNotification(e.message || '{{ __('Failed to duplicate category') }}', 'error');
                }
            },

            openSafeDeleteCategory(cat) {
                this.deletingCategory = cat;
                this.reassignCategoryId = '';
                this.forceDeleteCategory = false;
                this.showSafeDeleteCategoryModal = true;
            },

            async confirmDeleteCategory() {
                if (!this.deletingCategory) return;
                this.categoryActionSubmitting = true;

                const params = new URLSearchParams();
                if (this.forceDeleteCategory) {
                    params.append('force_delete', '1');
                } else if (this.reassignCategoryId) {
                    params.append('reassign_to_category_id', this.reassignCategoryId);
                }

                try {
                    const res = await fetch(`/admin/menu/categories/${this.deletingCategory.id}?` + params.toString(), {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json',
                        },
                    });

                    const data = await res.json();
                    if (!res.ok || !data.success) throw new Error(data.message);
                    this.showNotification(data.message);
                    this.showSafeDeleteCategoryModal = false;
                    setTimeout(() => window.location.reload(), 600);
                } catch (e) {
                    this.showNotification(e.message || '{{ __('Failed to delete category') }}', 'error');
                } finally {
                    this.categoryActionSubmitting = false;
                }
            },
        };
    }
</script>
@endsection
