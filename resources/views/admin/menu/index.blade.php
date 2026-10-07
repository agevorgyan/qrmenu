@extends('layouts.app')

@section('title', 'Menu Builder - ' . $vendor->name)

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
</style>
@endsection

@section('content')
@php
    $supportedLangs = $vendor->getSupportedLanguages();
    $primaryLang = $supportedLangs[0] ?? ['code' => 'en', 'name' => 'English', 'flag' => '🇬🇧'];
    $secondaryLangs = array_slice($supportedLangs, 1);
@endphp
<!-- Page Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.65rem;">
            <span style="width: 40px; height: 40px; border-radius: 12px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                <i class="fa-solid fa-utensils"></i>
            </span>
            <h1 style="font-size: 1.75rem; font-weight: 800; margin: 0; color: var(--text-main);">
                Menu Builder
            </h1>
        </div>
        <p style="color: var(--text-muted); font-size: 0.88rem; margin-top: 0.35rem;">
            Manage categories, dish offerings, pricing portions, allergens, and happy hour discount schedules.
        </p>
    </div>
    <div style="display: flex; gap: 0.65rem; flex-wrap: wrap;">
        <button class="btn btn-secondary" onclick="document.getElementById('newCategoryModal').style.display='flex'">
            <i class="fa-solid fa-folder-plus" style="color: var(--primary);"></i> New Category
        </button>
        <button class="btn btn-primary" onclick="openNewProductModal()">
            <i class="fa-solid fa-plus"></i> Add New Dish
        </button>
    </div>
</div>

@if($locations->count() > 0)
    <!-- Branch Menu Scope Bar -->
    <div style="background: var(--bg-card); border: 1.5px solid var(--border-color); border-radius: 16px; padding: 1rem 1.35rem; margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; box-shadow: 0 4px 16px rgba(0,0,0,0.06);">
        <div style="display: flex; align-items: center; gap: 0.85rem;">
            <span style="width: 42px; height: 42px; border-radius: 12px; background: rgba(99, 102, 241, 0.15); color: #6366f1; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                <i class="fa-solid fa-store"></i>
            </span>
            <div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <strong style="font-size: 1.05rem; color: var(--text-main);">Branch Menu Availability</strong>
                    <span class="badge badge-indigo" style="font-size: 0.72rem;">{{ $activeLocation?->name ?? 'Default Branch' }}</span>
                </div>
                <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.2rem;">
                    The «In Stock / Out of Stock» toggles and stock settings below apply to the selected branch:
                </div>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 0.65rem;">
            <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-muted); white-space: nowrap;">
                <i class="fa-solid fa-location-dot" style="color: var(--primary);"></i> Branch:
            </label>
            <form method="GET" action="{{ route('admin.menu.index') }}" id="branchScopeFilterForm" style="display: flex; align-items: center; margin: 0;">
                <select name="location_id" onchange="document.getElementById('branchScopeFilterForm').submit()" class="form-select" style="min-width: 220px; font-weight: 700; padding: 0.5rem 0.85rem; border-radius: 10px; border-color: rgba(99, 102, 241, 0.4);">
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

@if($categories->count() == 0)
    <div class="card" style="text-align: center; padding: 4rem 2rem;">
        <div style="width: 64px; height: 64px; border-radius: 20px; background: rgba(245, 158, 11, 0.15); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 1.25rem;">
            <i class="fa-solid fa-utensils"></i>
        </div>
        <h2 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem; color: var(--text-main);">Your menu is empty</h2>
        <p style="color: var(--text-muted); margin-bottom: 1.75rem; max-width: 440px; margin-left: auto; margin-right: auto; line-height: 1.5;">
            Start creating categories and dishes, or use our AI Menu Import tool to digitize existing paper/PDF menus in seconds.
        </p>
        <div style="display: flex; justify-content: center; gap: 0.75rem; flex-wrap: wrap;">
            <a href="{{ route('admin.ai.import') }}" class="btn btn-primary">
                <i class="fa-solid fa-wand-magic-sparkles"></i> AI Menu Import
            </a>
            <button class="btn btn-secondary" onclick="document.getElementById('newCategoryModal').style.display='flex'">
                <i class="fa-solid fa-plus"></i> Create Category
            </button>
        </div>
    </div>
@else
    @foreach($categories as $category)
        <div class="card" style="margin-bottom: 2rem;">
            <!-- Category Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.85rem; flex-wrap: wrap; gap: 0.75rem;">
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
                            {{ $category->products->count() }} dishes
                        </span>
                    </div>
                    @if($category->description)
                        <p style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.35rem;" class="break-word">{{ $category->description }}</p>
                    @endif
                </div>

                <div style="display: flex; gap: 0.45rem; align-items: center; flex-shrink: 0;">
                    <button type="button" class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.78rem;" onclick="editCategory({{ json_encode($category) }})">
                        <i class="fa-solid fa-pen"></i> Edit Category
                    </button>
                    <form action="{{ route('admin.menu.categories.destroy', $category->id) }}" method="POST" onsubmit="return confirm('Delete category {{ $category->name }} and all its dishes?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger" style="padding: 0.35rem 0.65rem; font-size: 0.78rem;" title="Delete Category">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Dishes List -->
            <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                @forelse($category->products as $product)
                    <div class="menu-dish-row">
                        <!-- Left/Desktop Content: Thumbnail + Info -->
                        <div class="dish-main-mobile" style="flex: 1; min-width: 0; display: flex; align-items: flex-start; gap: 1rem;">
                            <img src="{{ $product->image }}" onerror="this.onerror=null;this.src='{{ asset('images/default-dish.png') }}';" class="dish-thumb" alt="{{ $product->name }}">
                            
                            <div class="dish-content">
                                <div style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap;">
                                    <strong style="font-size: 1.05rem; color: var(--text-main); font-family: 'Outfit'; word-break: break-word;">{{ $product->name }}</strong>
                                    
                                    @if($vendor->featured_dish_enabled && $vendor->featured_product_id == $product->id)
                                        <span class="badge" style="background: linear-gradient(135deg, #f59e0b, #ef4444); color: #fff; font-size: 0.7rem; font-weight: 800;">
                                            <i class="fa-solid fa-fire-flame-curved"></i> {{ $vendor->featured_dish_badge ?: 'SPECIAL OF THE DAY' }}
                                        </span>
                                    @endif
                                    
                                    @if($product->is_featured)
                                        <span class="badge badge-amber" style="font-size: 0.7rem; font-weight: 700;">★ FEATURED</span>
                                    @endif

                                    @if(!empty($product->dietary_tags))
                                        @foreach($product->dietary_tags as $tag)
                                            <span class="badge badge-emerald" style="font-size: 0.68rem; text-transform: uppercase;">{{ str_replace('_', ' ', $tag) }}</span>
                                        @endforeach
                                    @endif

                                    @if($product->available_start_time || $product->available_end_time || !empty($product->available_days))
                                        <span class="badge badge-amber" style="font-size: 0.68rem;">
                                            <i class="fa-regular fa-clock"></i> {{ $product->getAvailabilityScheduleSummary('en') }}
                                        </span>
                                    @endif

                                    @if(!$product->available_for_dine_in || !$product->available_for_takeaway || !$product->available_for_delivery)
                                        <span class="badge badge-indigo" style="font-size: 0.68rem;">
                                            @if($product->available_for_dine_in) 🍽️ Dine-in @endif
                                            @if($product->available_for_takeaway) 🥡 Takeaway @endif
                                            @if($product->available_for_delivery) 🛵 Delivery @endif
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
                                            <i class="fa-solid fa-store-slash"></i> Disabled in {{ $locUnavailableCount }} branches
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
                                                <i class="fa-solid fa-sliders" style="color: var(--primary);"></i> Portions & Variations ({{ $product->variations->count() }}):
                                            </div>
                                            <div style="display: flex; flex-wrap: wrap; gap: 0.35rem;">
                                                @foreach($product->variations as $var)
                                                    <div style="display: inline-flex; align-items: center; gap: 0.35rem; background: var(--input-bg); border: 1px solid {{ $var->is_default ? 'var(--primary)' : 'var(--border-color)' }}; padding: 0.2rem 0.55rem; border-radius: 8px; font-size: 0.75rem; white-space: nowrap;">
                                                        @if($var->is_default)
                                                            <i class="fa-solid fa-circle-check" style="color: var(--primary); font-size: 0.75rem;" title="Default Portion"></i>
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
                                        <span><i class="fa-solid fa-fire" style="color: var(--primary);"></i> {{ $product->calories }} kcal</span>
                                    @endif
                                    @if($product->preparation_time_min)
                                        <span><i class="fa-solid fa-clock"></i> {{ $product->preparation_time_min }} mins</span>
                                    @endif
                                    @if($product->allergens->count() > 0)
                                        <span><i class="fa-solid fa-triangle-exclamation" style="color: #ef4444;"></i> Allergens: {{ $product->allergens->pluck('icon')->join(' ') }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Right / Bottom Pricing & Actions -->
                        <div class="dish-pricing-col">
                            <div class="dish-pricing-row-mobile">
                                <div style="font-size: 1.25rem; font-weight: 800; color: var(--primary); font-family: 'Outfit'; line-height: 1.2;">
                                    @if($product->isDiscountActive())
                                        <div style="display: flex; align-items: baseline; gap: 0.45rem;">
                                            <span style="color: #ef4444;">{{ number_format($product->discount_price) }} {{ $vendor->currency }}</span>
                                            <del style="color: var(--text-muted); font-size: 0.85rem; font-weight: 500;">{{ number_format($product->price) }}</del>
                                        </div>
                                    @elseif($product->discount_price)
                                        <div>
                                            <span>{{ number_format($product->price) }} {{ $vendor->currency }}</span>
                                            <div style="font-size: 0.72rem; color: var(--text-muted); font-weight: 600;">
                                                (Discount: {{ number_format($product->discount_price) }} {{ $vendor->currency }})
                                            </div>
                                        </div>
                                    @elseif($product->variations->count() > 1)
                                        {{ number_format($product->variations->min('price')) }} - {{ number_format($product->variations->max('price')) }} {{ $vendor->currency }}
                                    @else
                                        {{ number_format($product->price) }} {{ $vendor->currency }}
                                    @endif
                                </div>

                                @if($product->isDiscountActive())
                                    <div style="display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap; justify-content: flex-end;">
                                        <span class="badge badge-rose" style="font-size: 0.7rem;">
                                            <i class="fa-solid fa-tag"></i> -{{ $product->getDiscountPercentage() }}% Off
                                        </span>
                                        <span class="badge badge-emerald" style="font-size: 0.7rem;">
                                            <i class="fa-regular fa-clock"></i> {{ $product->getDiscountScheduleSummary('en') }}
                                        </span>
                                    </div>
                                @elseif($product->discount_price)
                                    <div style="font-size: 0.7rem; color: var(--text-muted);">
                                        <i class="fa-regular fa-clock"></i> {{ $product->getDiscountScheduleSummary('en') }} <span style="opacity: 0.7;">(off hours)</span>
                                    </div>
                                @endif
                            </div>

                            <div class="dish-actions-mobile">
                                <button type="button" class="btn btn-secondary" style="padding: 0.35rem 0.7rem; font-size: 0.78rem;" onclick="editProduct({{ json_encode($product->load(['allergens', 'variations', 'overrides'])) }})">
                                    <i class="fa-solid fa-pen"></i> Edit
                                </button>

                                @php
                                    $isInStockCurrentBranch = $product->isAvailableAtLocation($activeLocationId);
                                @endphp
                                <form action="{{ route('admin.menu.products.toggle', $product->id) }}" method="POST" style="margin: 0;">
                                    @csrf
                                    <input type="hidden" name="location_id" value="{{ $activeLocationId }}">
                                    <button type="submit" class="btn btn-secondary" style="padding: 0.35rem 0.7rem; font-size: 0.78rem;" title="Toggle stock status for {{ $activeLocation?->name }}">
                                        @if($isInStockCurrentBranch)
                                            <span style="color: #10b981;"><i class="fa-solid fa-toggle-on"></i> In Stock</span>
                                        @else
                                            <span style="color: #ef4444;"><i class="fa-solid fa-toggle-off"></i> Out of Stock</span>
                                        @endif
                                        <small style="opacity: 0.7; font-size: 0.7rem; display: block;">({{ Str::limit($activeLocation?->name ?? 'Branch', 12) }})</small>
                                    </button>
                                </form>

                                <form action="{{ route('admin.menu.products.destroy', $product->id) }}" method="POST" style="margin: 0;" onsubmit="return confirm('Delete dish {{ $product->name }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger" style="padding: 0.35rem 0.65rem; font-size: 0.78rem;" title="Delete dish">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div style="text-align: center; padding: 2rem; color: var(--text-muted); font-size: 0.9rem;">
                        No dishes added to this category yet.
                    </div>
                @endforelse
            </div>
        </div>
    @endforeach
@endif

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
            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0;">Add New Menu Dish</h3>
            <button onclick="document.getElementById('newProductModal').style.display='none'" style="background: none; border: none; color: var(--text-muted); font-size: 1.25rem; cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form action="{{ route('admin.menu.products.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div style="margin-bottom: 1rem;">
                <label class="form-label">Category *</label>
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
                    <label class="form-label">Price ({{ $vendor->currency }}) *</label>
                    <input type="number" name="price" step="100" required class="form-input">
                </div>
                <div>
                    <label class="form-label" style="color: #ef4444;">
                        <i class="fa-solid fa-tag"></i> Discounted Price
                    </label>
                    <input type="number" name="discount_price" step="100" placeholder="e.g. 2500" class="form-input" style="border-color: rgba(239, 68, 68, 0.4);">
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
                            <strong style="font-size: 0.85rem; color: var(--text-main);">Discount Schedule (Happy Hour)</strong>
                            <small style="display: block; color: var(--text-muted); font-size: 0.72rem;">Set active days and hours for the discount</small>
                        </div>
                    </div>
                    <label style="font-size: 0.78rem; display: flex; align-items: center; gap: 0.4rem; cursor: pointer; color: var(--text-main); font-weight: 600;">
                        <input type="checkbox" name="is_discount_active" value="1" checked> Enable Discount
                    </label>
                </div>

                <!-- Day Selector Chips -->
                <div style="margin-bottom: 0.75rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem; flex-wrap: wrap; gap: 0.35rem;">
                        <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">Days of the Week</label>
                        <div style="display: flex; gap: 0.35rem;">
                            <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setDiscountDaysPreset('new', 'all')">All</button>
                            <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setDiscountDaysPreset('new', 'weekdays')">Mon-Fri</button>
                            <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setDiscountDaysPreset('new', 'weekends')">Weekends</button>
                        </div>
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
                        @php
                            $weekDays = [
                                'mon' => 'Mon',
                                'tue' => 'Tue',
                                'wed' => 'Wed',
                                'thu' => 'Thu',
                                'fri' => 'Fri',
                                'sat' => 'Sat',
                                'sun' => 'Sun'
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
                        <label class="form-label" style="font-size: 0.75rem;">Start Time</label>
                        <input type="time" name="discount_start_time" class="form-input" style="padding: 0.45rem 0.75rem;">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.75rem;">End Time</label>
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
                            <strong style="font-size: 0.85rem; color: var(--text-main);">Branch Availability</strong>
                            <small style="display: block; color: var(--text-muted); font-size: 0.72rem;">Select which branches offer this dish</small>
                        </div>
                    </div>
                    <div style="display: flex; gap: 0.35rem;">
                        <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setLocationsPreset('new', true)">Select All</button>
                        <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setLocationsPreset('new', false)">Clear</button>
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
                        <strong style="font-size: 0.85rem; color: var(--text-main);">Operating Hours (Lunch / Hours)</strong>
                        <small style="display: block; color: var(--text-muted); font-size: 0.72rem;">e.g., lunch available only 12:00 - 15:00 (leave empty for all day)</small>
                    </div>
                </div>
                <div class="grid-2" style="margin-bottom: 0.75rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.75rem;">Available from (Start Time)</label>
                        <input type="time" name="available_start_time" class="form-input" style="padding: 0.45rem 0.75rem;">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.75rem;">Until (End Time)</label>
                        <input type="time" name="available_end_time" class="form-input" style="padding: 0.45rem 0.75rem;">
                    </div>
                </div>
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem; flex-wrap: wrap; gap: 0.35rem;">
                        <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">Available Days</label>
                        <div style="display: flex; gap: 0.35rem;">
                            <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setAvailableDaysPreset('new', 'all')">All</button>
                            <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setAvailableDaysPreset('new', 'weekdays')">Mon-Fri</button>
                            <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setAvailableDaysPreset('new', 'weekends')">Weekends</button>
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
                        <strong style="font-size: 0.85rem; color: var(--text-main);">Order Channels Availability</strong>
                        <small style="display: block; color: var(--text-muted); font-size: 0.72rem;">Select allowed order channels for this dish</small>
                    </div>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 0.65rem;">
                    <label style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.82rem; background: var(--input-bg); border: 1px solid var(--border-color); padding: 0.45rem 0.8rem; border-radius: 10px; cursor: pointer; color: var(--text-main); font-weight: 600;">
                        <input type="hidden" name="available_for_dine_in" value="0">
                        <input type="checkbox" name="available_for_dine_in" value="1" checked> 🍽️ Dine-in
                    </label>
                    <label style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.82rem; background: var(--input-bg); border: 1px solid var(--border-color); padding: 0.45rem 0.8rem; border-radius: 10px; cursor: pointer; color: var(--text-main); font-weight: 600;">
                        <input type="hidden" name="available_for_takeaway" value="0">
                        <input type="checkbox" name="available_for_takeaway" value="1" checked> 🥡 Takeaway
                    </label>
                    <label style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.82rem; background: var(--input-bg); border: 1px solid var(--border-color); padding: 0.45rem 0.8rem; border-radius: 10px; cursor: pointer; color: var(--text-main); font-weight: 600;">
                        <input type="hidden" name="available_for_delivery" value="0">
                        <input type="checkbox" name="available_for_delivery" value="1" checked> 🛵 Delivery
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
                    <i class="fa-solid fa-image" style="color: var(--primary);"></i> Dish Image (Upload File or Enter URL)
                </label>
                <div class="grid-2" style="align-items: center;">
                    <div>
                        <input type="file" name="image_file" accept="image/*" class="form-input" style="padding: 0.45rem;">
                        <small style="color: var(--text-muted); font-size: 0.7rem; display: block; margin-top: 0.25rem;">📁 Upload file from device</small>
                    </div>
                    <div>
                        <input type="text" name="image" placeholder="/storage/... or https://..." class="form-input">
                        <small style="color: var(--text-muted); font-size: 0.7rem; display: block; margin-top: 0.25rem;">🔗 Or image link / path</small>
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
                            <i class="fa-solid fa-sliders" style="color: var(--primary);"></i> Portions & Variations (Optional)
                        </label>
                        <small style="color: var(--text-muted); font-size: 0.72rem;">{{ __('Add sizes or portion options') }}</small>
                    </div>
                    <button type="button" class="btn btn-secondary" style="padding: 0.3rem 0.65rem; font-size: 0.75rem;" onclick="addVariationRow('new')">
                        <i class="fa-solid fa-plus"></i> Add Portion
                    </button>
                </div>
                <div id="new_variations_container" style="display: flex; flex-direction: column; gap: 0.5rem;"></div>
            </div>

            <!-- Dietary & Allergens -->
            <div style="margin-bottom: 1rem;">
                <label class="form-label">Dietary Tags</label>
                <div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
                    @foreach(['vegan', 'vegetarian', 'gluten_free', 'halal', 'chef_special', 'spicy'] as $dtag)
                        <label style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.78rem; background: var(--input-bg); border: 1px solid var(--border-color); padding: 0.25rem 0.55rem; border-radius: 8px; cursor: pointer; color: var(--text-main);">
                            <input type="checkbox" name="dietary_tags[]" value="{{ $dtag }}"> {{ str_replace('_', ' ', strtoupper($dtag)) }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label class="form-label">EU Allergens Tagging</label>
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
                    <label class="form-label" style="font-size: 0.75rem;">Calories (kcal)</label>
                    <input type="number" name="calories" placeholder="450" class="form-input">
                </div>
                <div>
                    <label class="form-label" style="font-size: 0.75rem;">Prep Mins</label>
                    <input type="number" name="preparation_time_min" placeholder="15" class="form-input">
                </div>
                <div style="display: flex; align-items: flex-end; padding-bottom: 0.35rem;">
                    <label style="font-size: 0.82rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: var(--text-main);">
                        <input type="checkbox" name="is_featured" value="1"> ★ Featured Dish
                    </label>
                </div>
            </div>

            <!-- AI Waiter Metadata & Priority Settings -->
            <div style="background: rgba(139, 92, 246, 0.05); border: 1.5px dashed rgba(139, 92, 246, 0.3); border-radius: 14px; padding: 1.1rem; margin-bottom: 1.5rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.85rem;">
                    <div style="font-weight: 700; font-size: 0.88rem; color: #8b5cf6; display: flex; align-items: center; gap: 0.45rem;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> AI Waiter Recommendation Settings
                    </div>
                    <label style="font-size: 0.8rem; font-weight: 700; display: flex; align-items: center; gap: 0.4rem; cursor: pointer; color: var(--text-main);">
                        <input type="checkbox" name="ai_priority" value="1"> ★ AI Promoted
                    </label>
                </div>
                <div class="grid-3" style="margin-bottom: 0.75rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.75rem;">AI Priority (1-100)</label>
                        <input type="number" name="ai_priority_level" min="1" max="100" value="50" class="form-input" placeholder="50">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.75rem;">AI Group</label>
                        <select name="ai_group" class="form-select">
                            <option value="">None (Standard)</option>
                            <option value="bestseller">AI Bestseller</option>
                            <option value="chef_choice">Chef Recommendation</option>
                            <option value="high_margin">High Margin</option>
                            <option value="new_arrival">New Product</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.75rem;">Spiciness Level</label>
                        <select name="ai_spicy_level" class="form-select">
                            <option value="0">0 - Not Spicy</option>
                            <option value="1">1 - Mild / Light</option>
                            <option value="2">2 - Medium Spicy</option>
                            <option value="3">3 - Hot / Very Spicy</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="form-label" style="font-size: 0.75rem;">AI Keywords / Flavor Tags (comma separated)</label>
                    <input type="text" name="ai_tags" placeholder="e.g. meat, savory, juicy, dinner, signature" class="form-input">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('newProductModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Dish</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Product -->
<div id="editProductModal" class="modern-modal-overlay" style="display: none;">
    <div class="modal-box-responsive">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0;">Edit Dish</h3>
            <button onclick="document.getElementById('editProductModal').style.display='none'" style="background: none; border: none; color: var(--text-muted); font-size: 1.25rem; cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form id="editProductForm" method="POST" enctype="multipart/form-data">
            @csrf
            <div style="margin-bottom: 1rem;">
                <label class="form-label">Category *</label>
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
                    <label class="form-label">Price ({{ $vendor->currency }}) *</label>
                    <input type="number" id="edit_prod_price" name="price" step="100" required class="form-input">
                </div>
                <div>
                    <label class="form-label" style="color: #ef4444;">
                        <i class="fa-solid fa-tag"></i> Discount Price
                    </label>
                    <input type="number" id="edit_prod_discount_price" name="discount_price" step="100" placeholder="e.g. 2500" class="form-input" style="border-color: rgba(239, 68, 68, 0.4);">
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
                            <strong style="font-size: 0.85rem; color: var(--text-main);">Discount Schedule (Happy Hour)</strong>
                            <small style="display: block; color: var(--text-muted); font-size: 0.72rem;">Set the days and hours when the discount applies</small>
                        </div>
                    </div>
                    <label style="font-size: 0.78rem; display: flex; align-items: center; gap: 0.4rem; cursor: pointer; color: var(--text-main); font-weight: 600;">
                        <input type="checkbox" id="edit_prod_is_discount_active" name="is_discount_active" value="1"> Enable discount
                    </label>
                </div>

                <!-- Day Selector Chips -->
                <div style="margin-bottom: 0.75rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem; flex-wrap: wrap; gap: 0.35rem;">
                        <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">Days of Week</label>
                        <div style="display: flex; gap: 0.35rem;">
                            <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setDiscountDaysPreset('edit', 'all')">All</button>
                            <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setDiscountDaysPreset('edit', 'weekdays')">Mon-Fri</button>
                            <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setDiscountDaysPreset('edit', 'weekends')">Weekends</button>
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
                        <label class="form-label" style="font-size: 0.75rem;">Start Time</label>
                        <input type="time" id="edit_prod_discount_start_time" name="discount_start_time" class="form-input" style="padding: 0.45rem 0.75rem;">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.75rem;">End Time</label>
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
                            <strong style="font-size: 0.85rem; color: var(--text-main);">Branch Availability</strong>
                            <small style="display: block; color: var(--text-muted); font-size: 0.72rem;">Select which branches this dish is available at</small>
                        </div>
                    </div>
                    <div style="display: flex; gap: 0.35rem;">
                        <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setLocationsPreset('edit', true)">All</button>
                        <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setLocationsPreset('edit', false)">Clear</button>
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
                        <strong style="font-size: 0.85rem; color: var(--text-main);">Hours & Daily Availability (Lunch / Hours)</strong>
                        <small style="display: block; color: var(--text-muted); font-size: 0.72rem;">e.g. lunch available only 12:00 - 15:00 (leave empty for all day)</small>
                    </div>
                </div>
                <div class="grid-2" style="margin-bottom: 0.75rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.75rem;">Start Time</label>
                        <input type="time" id="edit_prod_available_start_time" name="available_start_time" class="form-input" style="padding: 0.45rem 0.75rem;">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.75rem;">End Time</label>
                        <input type="time" id="edit_prod_available_end_time" name="available_end_time" class="form-input" style="padding: 0.45rem 0.75rem;">
                    </div>
                </div>
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem; flex-wrap: wrap; gap: 0.35rem;">
                        <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">Available Days</label>
                        <div style="display: flex; gap: 0.35rem;">
                            <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setAvailableDaysPreset('edit', 'all')">All</button>
                            <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setAvailableDaysPreset('edit', 'weekdays')">Mon-Fri</button>
                            <button type="button" class="btn btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.68rem;" onclick="setAvailableDaysPreset('edit', 'weekends')">Weekends</button>
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
                        <strong style="font-size: 0.85rem; color: var(--text-main);">Order Channels Availability</strong>
                        <small style="display: block; color: var(--text-muted); font-size: 0.72rem;">Select order channels where this dish is available</small>
                    </div>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 0.65rem;">
                    <label style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.82rem; background: var(--input-bg); border: 1px solid var(--border-color); padding: 0.45rem 0.8rem; border-radius: 10px; cursor: pointer; color: var(--text-main); font-weight: 600;">
                        <input type="hidden" name="available_for_dine_in" value="0">
                        <input type="checkbox" id="edit_prod_available_for_dine_in" name="available_for_dine_in" value="1"> 🍽️ Dine-in
                    </label>
                    <label style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.82rem; background: var(--input-bg); border: 1px solid var(--border-color); padding: 0.45rem 0.8rem; border-radius: 10px; cursor: pointer; color: var(--text-main); font-weight: 600;">
                        <input type="hidden" name="available_for_takeaway" value="0">
                        <input type="checkbox" id="edit_prod_available_for_takeaway" name="available_for_takeaway" value="1"> 🥡 Takeaway
                    </label>
                    <label style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.82rem; background: var(--input-bg); border: 1px solid var(--border-color); padding: 0.45rem 0.8rem; border-radius: 10px; cursor: pointer; color: var(--text-main); font-weight: 600;">
                        <input type="hidden" name="available_for_delivery" value="0">
                        <input type="checkbox" id="edit_prod_available_for_delivery" name="available_for_delivery" value="1"> 🛵 Delivery
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
                    <i class="fa-solid fa-image" style="color: var(--primary);"></i> Dish Image (Upload New File or Change URL)
                </label>
                <div class="grid-2" style="align-items: center;">
                    <div>
                        <input type="file" name="image_file" accept="image/*" class="form-input" style="padding: 0.45rem;">
                        <small style="color: var(--text-muted); font-size: 0.7rem; display: block; margin-top: 0.25rem;">📁 Upload new image file</small>
                    </div>
                    <div>
                        <input type="text" id="edit_prod_image" name="image" placeholder="/storage/... or https://..." class="form-input">
                        <small style="color: var(--text-muted); font-size: 0.7rem; display: block; margin-top: 0.25rem;">🔗 Or edit image link / path</small>
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
                            <i class="fa-solid fa-sliders" style="color: var(--primary);"></i> Portions & Variations (Optional)
                        </label>
                        <small style="color: var(--text-muted); font-size: 0.72rem;">{{ __('Add or edit portions and options') }}</small>
                    </div>
                    <button type="button" class="btn btn-secondary" style="padding: 0.3rem 0.65rem; font-size: 0.75rem;" onclick="addVariationRow('edit')">
                        <i class="fa-solid fa-plus"></i> Add Portion
                    </button>
                </div>
                <div id="edit_variations_container" style="display: flex; flex-direction: column; gap: 0.5rem;"></div>
            </div>

            <!-- Dietary & Allergens -->
            <div style="margin-bottom: 1rem;">
                <label class="form-label">Dietary Tags</label>
                <div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
                    @foreach(['vegan', 'vegetarian', 'gluten_free', 'halal', 'chef_special', 'spicy'] as $dtag)
                        <label style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.78rem; background: var(--input-bg); border: 1px solid var(--border-color); padding: 0.25rem 0.55rem; border-radius: 8px; cursor: pointer; color: var(--text-main);">
                            <input type="checkbox" class="edit-dietary-checkbox" name="dietary_tags[]" value="{{ $dtag }}"> {{ str_replace('_', ' ', strtoupper($dtag)) }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label class="form-label">EU Allergens Tagging</label>
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
                    <label class="form-label" style="font-size: 0.75rem;">Calories (kcal)</label>
                    <input type="number" id="edit_prod_calories" name="calories" placeholder="450" class="form-input">
                </div>
                <div>
                    <label class="form-label" style="font-size: 0.75rem;">Prep Mins</label>
                    <input type="number" id="edit_prod_preparation_time_min" name="preparation_time_min" placeholder="15" class="form-input">
                </div>
                <div style="display: flex; align-items: flex-end; padding-bottom: 0.35rem;">
                    <label style="font-size: 0.82rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: var(--text-main);">
                        <input type="checkbox" id="edit_prod_is_featured" name="is_featured" value="1"> ★ Featured Dish
                    </label>
                </div>
            </div>

            <!-- AI Waiter Metadata & Priority Settings -->
            <div style="background: rgba(139, 92, 246, 0.05); border: 1.5px dashed rgba(139, 92, 246, 0.3); border-radius: 14px; padding: 1.1rem; margin-bottom: 1.5rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.85rem;">
                    <div style="font-weight: 700; font-size: 0.88rem; color: #8b5cf6; display: flex; align-items: center; gap: 0.45rem;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> AI Waiter Recommendation Settings
                    </div>
                    <label style="font-size: 0.8rem; font-weight: 700; display: flex; align-items: center; gap: 0.4rem; cursor: pointer; color: var(--text-main);">
                        <input type="checkbox" id="edit_prod_ai_priority" name="ai_priority" value="1"> ★ AI Promoted
                    </label>
                </div>
                <div class="grid-3" style="margin-bottom: 0.75rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.75rem;">AI Priority (1-100)</label>
                        <input type="number" id="edit_prod_ai_priority_level" name="ai_priority_level" min="1" max="100" class="form-input" placeholder="50">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.75rem;">AI Group</label>
                        <select id="edit_prod_ai_group" name="ai_group" class="form-select">
                            <option value="">None (Standard)</option>
                            <option value="bestseller">AI Bestseller</option>
                            <option value="chef_choice">Chef Recommendation</option>
                            <option value="high_margin">High Margin</option>
                            <option value="new_arrival">New Product</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.75rem;">Spiciness Level</label>
                        <select id="edit_prod_ai_spicy_level" name="ai_spicy_level" class="form-select">
                            <option value="0">0 - Not Spicy</option>
                            <option value="1">1 - Mild / Light</option>
                            <option value="2">2 - Medium Spicy</option>
                            <option value="3">3 - Hot / Very Spicy</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="form-label" style="font-size: 0.75rem;">AI Keywords / Flavor Tags (comma separated)</label>
                    <input type="text" id="edit_prod_ai_tags" name="ai_tags" placeholder="e.g. meat, savory, juicy, dinner, signature" class="form-input">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('editProductModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Dish</button>
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
</script>
@endsection
