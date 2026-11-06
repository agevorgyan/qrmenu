@extends('layouts.app')

@section('title', __('Recipes & Technical Cards') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div style="min-width: 0; flex: 1;">
        <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
            <span style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                <i class="fa-solid fa-utensils"></i>
            </span>
            <span>{{ __('Recipes & Technical Cards') }}</span>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0;">
            {{ __('Manage recipe technical cards, multi-level bills of materials (BOM), shrinkage losses, food costing, and profit margins.') }}
        </p>
    </div>
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        @can('recipes.create')
            <a href="{{ route('admin.inventory.recipes.create') }}" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; border-radius: 12px; font-weight: 700;">
                <i class="fa-solid fa-plus"></i> {{ __('Create Technical Card') }}
            </a>
        @endcan
    </div>
</div>

<!-- KPI Stats Summary -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-card);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(59, 130, 246, 0.12); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="fa-solid fa-book-open"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Total Recipes') }}</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: var(--text-main);">{{ $totalRecipes }}</div>
        </div>
    </div>

    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-card);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(16, 185, 129, 0.12); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Approved Cards') }}</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: var(--text-main);">{{ $approvedRecipes }}</div>
        </div>
    </div>

    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-card);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(245, 158, 11, 0.12); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="fa-solid fa-bowl-food"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Finished Dishes') }}</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: var(--text-main);">{{ $dishCount }}</div>
        </div>
    </div>

    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-card);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(168, 85, 247, 0.12); color: #a855f7; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="fa-solid fa-blender"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Semi-Finished / Prep') }}</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: var(--text-main);">{{ $semiCount }}</div>
        </div>
    </div>
</div>

<!-- Search & Filter Form -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
    <form method="GET" action="{{ route('admin.inventory.recipes.index') }}" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 2; min-width: 220px;">
            <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">{{ __('Search') }}</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by recipe name or code...') }}" class="form-input" style="width: 100%; border-radius: 10px;">
        </div>

        <div style="flex: 1; min-width: 150px;">
            <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">{{ __('Type') }}</label>
            <select name="type" class="form-select" style="width: 100%; border-radius: 10px;">
                <option value="">{{ __('All Types') }}</option>
                <option value="dish" {{ request('type') === 'dish' ? 'selected' : '' }}>{{ __('Dish / Menu Item') }}</option>
                <option value="semi_finished" {{ request('type') === 'semi_finished' ? 'selected' : '' }}>{{ __('Semi-Finished') }}</option>
                <option value="sauce" {{ request('type') === 'sauce' ? 'selected' : '' }}>{{ __('Sauce / Dressing') }}</option>
                <option value="bakery" {{ request('type') === 'bakery' ? 'selected' : '' }}>{{ __('Bakery / Pastry') }}</option>
                <option value="beverage" {{ request('type') === 'beverage' ? 'selected' : '' }}>{{ __('Beverage / Cocktail') }}</option>
                <option value="prep" {{ request('type') === 'prep' ? 'selected' : '' }}>{{ __('Prep Item') }}</option>
            </select>
        </div>

        <div style="flex: 1; min-width: 150px;">
            <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">{{ __('Status') }}</label>
            <select name="status" class="form-select" style="width: 100%; border-radius: 10px;">
                <option value="">{{ __('All Statuses') }}</option>
                <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>{{ __('Draft') }}</option>
                <option value="pending_approval" {{ request('status') === 'pending_approval' ? 'selected' : '' }}>{{ __('Pending Approval') }}</option>
                <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>{{ __('Approved') }}</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
                <option value="archived" {{ request('status') === 'archived' ? 'selected' : '' }}>{{ __('Archived') }}</option>
            </select>
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 600; padding: 0.6rem 1.25rem;">
                <i class="fa-solid fa-filter"></i> {{ __('Filter') }}
            </button>
            @if(request()->hasAny(['search', 'type', 'status', 'category_id']))
                <a href="{{ route('admin.inventory.recipes.index') }}" class="btn btn-secondary" style="border-radius: 10px;">
                    {{ __('Reset') }}
                </a>
            @endif
        </div>
    </form>
</div>

<!-- Recipes Table Card -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-card);">
    @if($recipes->isEmpty())
        <div style="padding: 4rem 1.5rem; text-align: center;">
            <div style="width: 72px; height: 72px; border-radius: 20px; background: rgba(245, 158, 11, 0.1); color: #f59e0b; display: inline-flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 1rem;">
                <i class="fa-solid fa-utensils"></i>
            </div>
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin: 0 0 0.5rem;">
                {{ __('No recipe technical cards found') }}
            </h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; max-width: 450px; margin: 0 auto 1.5rem;">
                {{ __('Create technical cards to track recipes, calculate dish portion costs, shrinkage losses, and profit margins.') }}
            </p>
            @can('recipes.create')
                <a href="{{ route('admin.inventory.recipes.create') }}" class="btn btn-primary" style="border-radius: 12px; font-weight: 700;">
                    <i class="fa-solid fa-plus"></i> {{ __('Create First Recipe') }}
                </a>
            @endcan
        </div>
    @else
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.02);">
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase;">{{ __('Recipe / Code') }}</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase;">{{ __('Type') }}</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase;">{{ __('Yield / Portion') }}</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase;">{{ __('Portion Cost') }}</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase;">{{ __('Selling Price') }}</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase;">{{ __('Food Cost %') }}</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase;">{{ __('Status') }}</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; text-align: right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recipes as $recipe)
                        @php
                            $cost = $recipe->latestCost;
                            $foodCost = $cost?->food_cost_percent;
                            $portionCost = $cost?->portion_cost ?? 0;
                            $sellingPrice = (float) ($recipe->selling_price ?? $recipe->product?->price ?? 0);
                        @endphp
                        <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.15s ease;">
                            <td style="padding: 1rem 1.25rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    @if($recipe->image)
                                        <img src="{{ $recipe->image }}" alt="{{ $recipe->name }}" style="width: 42px; height: 42px; border-radius: 8px; object-fit: cover; border: 1px solid var(--border-color);">
                                    @else
                                        <div style="width: 42px; height: 42px; border-radius: 8px; background: rgba(245, 158, 11, 0.1); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                                            <i class="fa-solid fa-utensils"></i>
                                        </div>
                                    @endif
                                    <div>
                                        <a href="{{ route('admin.inventory.recipes.show', $recipe) }}" style="font-weight: 700; color: var(--text-main); text-decoration: none; display: block;">
                                            {{ $recipe->name }}
                                        </a>
                                        <div style="font-size: 0.78rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.5rem; margin-top: 0.2rem;">
                                            <span style="font-family: monospace; background: rgba(0,0,0,0.05); padding: 0.15rem 0.4rem; border-radius: 4px;">{{ $recipe->code }}</span>
                                            <span>v{{ $recipe->current_version }}</span>
                                            @if($recipe->product)
                                                <span style="color: #3b82f6;"><i class="fa-solid fa-link"></i> {{ $recipe->product->name }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td style="padding: 1rem 1.25rem;">
                                @php
                                    $typeColors = [
                                        'dish' => ['bg' => 'rgba(16, 185, 129, 0.12)', 'color' => '#10b981', 'label' => __('Dish')],
                                        'semi_finished' => ['bg' => 'rgba(168, 85, 247, 0.12)', 'color' => '#a855f7', 'label' => __('Semi-Finished')],
                                        'sauce' => ['bg' => 'rgba(239, 68, 68, 0.12)', 'color' => '#ef4444', 'label' => __('Sauce')],
                                        'bakery' => ['bg' => 'rgba(245, 158, 11, 0.12)', 'color' => '#f59e0b', 'label' => __('Bakery')],
                                        'beverage' => ['bg' => 'rgba(6, 182, 212, 0.12)', 'color' => '#06b6d4', 'label' => __('Beverage')],
                                        'prep' => ['bg' => 'rgba(107, 114, 128, 0.12)', 'color' => '#6b7280', 'label' => __('Prep')],
                                    ];
                                    $badge = $typeColors[$recipe->type] ?? ['bg' => 'rgba(0,0,0,0.05)', 'color' => 'inherit', 'label' => $recipe->type];
                                @endphp
                                <span style="display: inline-block; padding: 0.25rem 0.65rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; background: {{ $badge['bg'] }}; color: {{ $badge['color'] }};">
                                    {{ $badge['label'] }}
                                </span>
                            </td>

                            <td style="padding: 1rem 1.25rem;">
                                <div style="font-weight: 600; color: var(--text-main);">
                                    {{ (float) $recipe->yield_quantity }} {{ $recipe->yieldUnit?->code }}
                                </div>
                                @if($recipe->portion_size)
                                    <div style="font-size: 0.78rem; color: var(--text-muted);">
                                        {{ __('Portion') }}: {{ (float) $recipe->portion_size }} {{ $recipe->portionUnit?->code ?? $recipe->yieldUnit?->code }}
                                    </div>
                                @endif
                            </td>

                            <td style="padding: 1rem 1.25rem; font-family: monospace; font-weight: 700; color: var(--text-main);">
                                @can('recipes.cost.view')
                                    {{ number_format($portionCost, 2) }} {{ Auth::user()->vendor->currency }}
                                @else
                                    <span style="color: var(--text-muted); font-size: 0.8rem;">***</span>
                                @endcan
                            </td>

                            <td style="padding: 1rem 1.25rem; font-family: monospace; font-weight: 700; color: var(--text-main);">
                                @if($sellingPrice > 0)
                                    {{ number_format($sellingPrice, 2) }} {{ Auth::user()->vendor->currency }}
                                @else
                                    <span style="color: var(--text-muted); font-weight: 400;">—</span>
                                @endif
                            </td>

                            <td style="padding: 1rem 1.25rem;">
                                @can('recipes.cost.view')
                                    @if($foodCost !== null)
                                        @php
                                            $fcColor = $foodCost <= 30 ? '#10b981' : ($foodCost <= 35 ? '#f59e0b' : '#ef4444');
                                            $fcBg = $foodCost <= 30 ? 'rgba(16, 185, 129, 0.12)' : ($foodCost <= 35 ? 'rgba(245, 158, 11, 0.12)' : 'rgba(239, 68, 68, 0.12)');
                                        @endphp
                                        <span style="display: inline-block; padding: 0.25rem 0.65rem; border-radius: 8px; font-size: 0.78rem; font-weight: 800; background: {{ $fcBg }}; color: {{ $fcColor }};">
                                            {{ number_format($foodCost, 1) }}%
                                        </span>
                                    @else
                                        <span style="color: var(--text-muted); font-size: 0.8rem;">—</span>
                                    @endif
                                @else
                                    <span style="color: var(--text-muted); font-size: 0.8rem;">***</span>
                                @endcan
                            </td>

                            <td style="padding: 1rem 1.25rem;">
                                @php
                                    $statusConfigs = [
                                        'approved' => ['bg' => 'rgba(16, 185, 129, 0.12)', 'color' => '#10b981', 'label' => __('Approved')],
                                        'active' => ['bg' => 'rgba(59, 130, 246, 0.12)', 'color' => '#3b82f6', 'label' => __('Active')],
                                        'draft' => ['bg' => 'rgba(107, 114, 128, 0.12)', 'color' => '#6b7280', 'label' => __('Draft')],
                                        'pending_approval' => ['bg' => 'rgba(245, 158, 11, 0.12)', 'color' => '#f59e0b', 'label' => __('Pending Approval')],
                                        'archived' => ['bg' => 'rgba(239, 68, 68, 0.12)', 'color' => '#ef4444', 'label' => __('Archived')],
                                    ];
                                    $st = $statusConfigs[$recipe->status] ?? ['bg' => 'rgba(0,0,0,0.05)', 'color' => 'inherit', 'label' => $recipe->status];
                                @endphp
                                <span style="display: inline-block; padding: 0.25rem 0.65rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; background: {{ $st['bg'] }}; color: {{ $st['color'] }};">
                                    {{ $st['label'] }}
                                </span>
                            </td>

                            <td style="padding: 1rem 1.25rem; text-align: right;">
                                <div style="display: flex; gap: 0.4rem; justify-content: flex-end;">
                                    <a href="{{ route('admin.inventory.recipes.show', $recipe) }}" class="btn btn-secondary btn-sm" title="{{ __('View Technical Card') }}" style="border-radius: 8px; padding: 0.4rem 0.65rem;">
                                        <i class="fa-solid fa-file-invoice"></i>
                                    </a>

                                    @can('recipes.update')
                                        <a href="{{ route('admin.inventory.recipes.edit', $recipe) }}" class="btn btn-secondary btn-sm" title="{{ __('Edit') }}" style="border-radius: 8px; padding: 0.4rem 0.65rem;">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                    @endcan

                                    @can('recipes.delete')
                                        <form method="POST" action="{{ route('admin.inventory.recipes.destroy', $recipe) }}" onsubmit="return confirm('{{ __('Are you sure you want to delete this recipe?') }}');" style="display: inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-secondary btn-sm" title="{{ __('Delete') }}" style="border-radius: 8px; padding: 0.4rem 0.65rem; color: #ef4444;">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($recipes->hasPages())
            <div style="padding: 1.25rem; border-top: 1px solid var(--border-color);">
                {{ $recipes->links() }}
            </div>
        @endif
    @endif
</div>
@endsection

