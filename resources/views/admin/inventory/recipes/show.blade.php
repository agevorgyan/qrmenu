@extends('layouts.app')

@section('title', __('Technical Card: ') . $recipe->name . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="max-width: 1200px; margin: 0 auto;">
    <!-- Top Action Navigation -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <a href="{{ route('admin.inventory.recipes.index') }}" style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--text-muted); font-size: 0.85rem; font-weight: 600; text-decoration: none; margin-bottom: 0.5rem;">
                <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Recipes Catalog') }}
            </a>
            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0;">
                    {{ $recipe->name }}
                </h1>
                <span style="font-family: monospace; background: rgba(0,0,0,0.06); padding: 0.2rem 0.5rem; border-radius: 6px; font-weight: 700; font-size: 0.85rem;">
                    {{ $recipe->code }}
                </span>
                <span style="background: rgba(59, 130, 246, 0.12); color: #3b82f6; padding: 0.2rem 0.6rem; border-radius: 6px; font-weight: 700; font-size: 0.78rem;">
                    v{{ $recipe->current_version }}
                </span>
                @php
                    $statusStyles = [
                        'approved' => ['bg' => 'rgba(16, 185, 129, 0.12)', 'color' => '#10b981', 'label' => __('Approved')],
                        'active' => ['bg' => 'rgba(59, 130, 246, 0.12)', 'color' => '#3b82f6', 'label' => __('Active')],
                        'draft' => ['bg' => 'rgba(107, 114, 128, 0.12)', 'color' => '#6b7280', 'label' => __('Draft')],
                        'pending_approval' => ['bg' => 'rgba(245, 158, 11, 0.12)', 'color' => '#f59e0b', 'label' => __('Pending Approval')],
                        'archived' => ['bg' => 'rgba(239, 68, 68, 0.12)', 'color' => '#ef4444', 'label' => __('Archived')],
                    ];
                    $st = $statusStyles[$recipe->status] ?? ['bg' => 'rgba(0,0,0,0.05)', 'color' => 'inherit', 'label' => $recipe->status];
                @endphp
                <span style="background: {{ $st['bg'] }}; color: {{ $st['color'] }}; padding: 0.2rem 0.6rem; border-radius: 6px; font-weight: 700; font-size: 0.78rem;">
                    {{ $st['label'] }}
                </span>
            </div>
        </div>

        <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
            <button onclick="window.print();" class="btn btn-secondary" style="border-radius: 10px; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-print"></i> {{ __('Print Card') }}
            </button>

            @can('recipes.update')
                <button type="button" onclick="document.getElementById('recalculateModal').showModal();" class="btn btn-secondary" style="border-radius: 10px; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-calculator"></i> {{ __('Recalculate Cost') }}
                </button>

                <a href="{{ route('admin.inventory.recipes.edit', $recipe) }}" class="btn btn-secondary" style="border-radius: 10px; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-pen-to-square"></i> {{ __('Edit Card') }}
                </a>
            @endcan

            @can('recipes.approve')
                @if($recipe->status !== \App\Models\Recipe::STATUS_APPROVED)
                    <form method="POST" action="{{ route('admin.inventory.recipes.approve', $recipe) }}" style="display: inline;">
                        @csrf
                        <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-circle-check"></i> {{ __('Approve & Snapshot Version') }}
                        </button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    @php
        $cost = $recipe->latestCost;
        $portionCost = (float) ($cost?->portion_cost ?? 0);
        $sellingPrice = (float) ($recipe->selling_price ?? $recipe->product?->price ?? 0);
        $foodCostPercent = $cost?->food_cost_percent;
        $grossProfit = $cost?->gross_profit;
        $profitMargin = $cost?->profit_margin_percent;
    @endphp

    <!-- Costing & Profitability KPI Cards -->
    @if($canViewCost)
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1.25rem; margin-bottom: 1.75rem;">
            <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card);">
                <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Portion Cost (Cost of Goods)') }}</div>
                <div style="font-size: 1.6rem; font-weight: 800; color: var(--text-main); font-family: monospace; margin: 0.25rem 0;">
                    {{ number_format($portionCost, 2) }} {{ Auth::user()->vendor->currency }}
                </div>
                <div style="font-size: 0.78rem; color: var(--text-muted);">
                    {{ __('Total Batch:') }} {{ number_format($cost?->total_cost ?? 0, 2) }} {{ Auth::user()->vendor->currency }}
                </div>
            </div>

            <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card);">
                <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Selling Price') }}</div>
                <div style="font-size: 1.6rem; font-weight: 800; color: var(--text-main); font-family: monospace; margin: 0.25rem 0;">
                    @if($sellingPrice > 0)
                        {{ number_format($sellingPrice, 2) }} {{ Auth::user()->vendor->currency }}
                    @else
                        <span style="color: var(--text-muted); font-size: 1.1rem;">{{ __('Not set') }}</span>
                    @endif
                </div>
                <div style="font-size: 0.78rem; color: var(--text-muted);">
                    @if($recipe->product)
                        <i class="fa-solid fa-link" style="color: #3b82f6;"></i> {{ __('Menu Product Sync') }}
                    @else
                        {{ __('Standalone recipe') }}
                    @endif
                </div>
            </div>

            <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card);">
                <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Food Cost %') }}</div>
                @if($foodCostPercent !== null)
                    @php
                        $fcColor = $foodCostPercent <= 30 ? '#10b981' : ($foodCostPercent <= 35 ? '#f59e0b' : '#ef4444');
                    @endphp
                    <div style="font-size: 1.6rem; font-weight: 800; color: {{ $fcColor }}; font-family: monospace; margin: 0.25rem 0;">
                        {{ number_format($foodCostPercent, 1) }}%
                    </div>
                    <div style="font-size: 0.78rem; color: var(--text-muted);">
                        {{ __('Target:') }} {{ (float) ($recipe->target_food_cost_percent ?? 28) }}%
                    </div>
                @else
                    <div style="font-size: 1.1rem; color: var(--text-muted); margin: 0.4rem 0;">—</div>
                    <div style="font-size: 0.78rem; color: var(--text-muted);">{{ __('Set selling price to compute') }}</div>
                @endif
            </div>

            <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card);">
                <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Gross Profit / Portion') }}</div>
                <div style="font-size: 1.6rem; font-weight: 800; color: #10b981; font-family: monospace; margin: 0.25rem 0;">
                    @if($grossProfit !== null)
                        {{ number_format($grossProfit, 2) }} {{ Auth::user()->vendor->currency }}
                    @else
                        —
                    @endif
                </div>
                <div style="font-size: 0.78rem; color: var(--text-muted);">
                    @if($profitMargin !== null)
                        {{ __('Margin:') }} {{ number_format($profitMargin, 1) }}%
                    @else
                        {{ __('Margin:') }} —
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Recipe Specification & Technical Parameters -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
        <!-- Specs Details -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; box-shadow: var(--shadow-card);">
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.1rem; font-weight: 700; color: var(--text-main); margin: 0 0 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-sliders" style="color: #3b82f6;"></i>
                <span>{{ __('Technical Card Parameters') }}</span>
            </h3>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.88rem;">
                <div>
                    <div style="color: var(--text-muted); font-size: 0.78rem; font-weight: 600;">{{ __('Yield Output') }}</div>
                    <div style="font-weight: 700; color: var(--text-main); margin-top: 0.2rem;">
                        {{ (float) $recipe->yield_quantity }} {{ $recipe->yieldUnit?->code }}
                    </div>
                </div>

                <div>
                    <div style="color: var(--text-muted); font-size: 0.78rem; font-weight: 600;">{{ __('Portion Size') }}</div>
                    <div style="font-weight: 700; color: var(--text-main); margin-top: 0.2rem;">
                        {{ $recipe->portion_size ? (float) $recipe->portion_size . ' ' . ($recipe->portionUnit?->code ?? $recipe->yieldUnit?->code) : '—' }}
                    </div>
                </div>

                <div>
                    <div style="color: var(--text-muted); font-size: 0.78rem; font-weight: 600;">{{ __('Preparation Time') }}</div>
                    <div style="font-weight: 700; color: var(--text-main); margin-top: 0.2rem;">
                        {{ $recipe->prep_time_minutes ? $recipe->prep_time_minutes . ' ' . __('min') : '—' }}
                    </div>
                </div>

                <div>
                    <div style="color: var(--text-muted); font-size: 0.78rem; font-weight: 600;">{{ __('Cooking Time') }}</div>
                    <div style="font-weight: 700; color: var(--text-main); margin-top: 0.2rem;">
                        {{ $recipe->cook_time_minutes ? $recipe->cook_time_minutes . ' ' . __('min') : '—' }}
                    </div>
                </div>

                <div>
                    <div style="color: var(--text-muted); font-size: 0.78rem; font-weight: 600;">{{ __('Storage Conditions') }}</div>
                    <div style="font-weight: 600; color: var(--text-main); margin-top: 0.2rem;">
                        {{ $recipe->storage_conditions ?? '—' }}
                    </div>
                </div>

                <div>
                    <div style="color: var(--text-muted); font-size: 0.78rem; font-weight: 600;">{{ __('Shelf Life') }}</div>
                    <div style="font-weight: 700; color: var(--text-main); margin-top: 0.2rem;">
                        {{ $recipe->shelf_life_hours ? $recipe->shelf_life_hours . ' ' . __('hours') : '—' }}
                    </div>
                </div>
            </div>

            <!-- Allergens summary -->
            <div style="margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px solid var(--border-color);">
                <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">
                    {{ __('Inherited Allergens') }}
                </div>
                @if($allergens->isNotEmpty())
                    <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                        @foreach($allergens as $allergen)
                            <span style="background: rgba(239, 68, 68, 0.12); color: #ef4444; padding: 0.2rem 0.55rem; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">
                                <i class="fa-solid fa-triangle-exclamation"></i> {{ $allergen->name }}
                            </span>
                        @endforeach
                    </div>
                @else
                    <div style="color: #10b981; font-size: 0.82rem; font-weight: 600;">
                        <i class="fa-solid fa-circle-check"></i> {{ __('No known allergens in recipe ingredients.') }}
                    </div>
                @endif
            </div>
        </div>

        <!-- Recipe Image & Description -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; box-shadow: var(--shadow-card); display: flex; flex-direction: column;">
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.1rem; font-weight: 700; color: var(--text-main); margin: 0 0 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-image" style="color: #a855f7;"></i>
                <span>{{ __('Dish Presentation & Description') }}</span>
            </h3>

            @if($recipe->image)
                <img src="{{ $recipe->image }}" alt="{{ $recipe->name }}" style="width: 100%; height: 180px; border-radius: 12px; object-fit: cover; margin-bottom: 1rem; border: 1px solid var(--border-color);">
            @endif

            <p style="color: var(--text-muted); font-size: 0.9rem; line-height: 1.5; margin: 0; flex: 1;">
                {{ $recipe->description ?: __('No presentation notes or description provided.') }}
            </p>

            <div style="margin-top: 1rem; font-size: 0.78rem; color: var(--text-muted);">
                {{ __('Created by:') }} {{ $recipe->creator?->name ?? 'System' }} • {{ $recipe->created_at->toVendorTimezone()->format('M d, Y') }}
                @if($recipe->approver)
                    <br>{{ __('Approved by:') }} {{ $recipe->approver->name }} • {{ $recipe->approved_at?->toVendorTimezone()->format('M d, Y') }}
                @endif
            </div>
        </div>
    </div>

    <!-- 3. Bill of Materials (BOM) Table -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
            <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-list-check" style="color: #10b981;"></i>
                <span>{{ __('Bill of Materials (BOM) & Shrinkage Table') }}</span>
            </h2>
            <div style="font-size: 0.8rem; color: var(--text-muted);">
                {{ __('Cost Method:') }} <strong style="color: var(--text-main);">{{ ucfirst(str_replace('_', ' ', $cost?->cost_method ?? 'Weighted Average')) }}</strong>
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem; text-align: left;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); background: rgba(0,0,0,0.02);">
                        <th style="padding: 0.75rem 0.6rem; font-weight: 700; color: var(--text-muted); width: 35px;">#</th>
                        <th style="padding: 0.75rem 0.6rem; font-weight: 700; color: var(--text-main);">{{ __('Component / Ingredient') }}</th>
                        <th style="padding: 0.75rem 0.6rem; font-weight: 700; color: var(--text-muted); width: 70px;">{{ __('Unit') }}</th>
                        <th style="padding: 0.75rem 0.6rem; font-weight: 700; color: var(--text-muted); text-align: right;" title="{{ __('Gross weight before cleaning') }}">{{ __('Gross') }}</th>
                        <th style="padding: 0.75rem 0.6rem; font-weight: 700; color: var(--text-muted); text-align: right;" title="{{ __('Cold prep / cleaning shrinkage %') }}">{{ __('Cold %') }}</th>
                        <th style="padding: 0.75rem 0.6rem; font-weight: 700; color: var(--text-muted); text-align: right;" title="{{ __('Net weight after cleaning') }}">{{ __('Net') }}</th>
                        <th style="padding: 0.75rem 0.6rem; font-weight: 700; color: var(--text-muted); text-align: right;" title="{{ __('Cooking shrinkage %') }}">{{ __('Cook %') }}</th>
                        <th style="padding: 0.75rem 0.6rem; font-weight: 700; color: var(--text-muted); text-align: right;" title="{{ __('Final cooked yield') }}">{{ __('Final') }}</th>
                        @if($canViewCost)
                            <th style="padding: 0.75rem 0.6rem; font-weight: 700; color: var(--text-muted); text-align: right;">{{ __('Unit Cost') }}</th>
                            <th style="padding: 0.75rem 0.6rem; font-weight: 700; color: var(--text-main); text-align: right;">{{ __('Total Cost') }}</th>
                        @endif
                        <th style="padding: 0.75rem 0.6rem; font-weight: 700; color: var(--text-muted);">{{ __('Culinary Notes') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $sumGross = 0;
                        $sumNet = 0;
                        $sumFinal = 0;
                        $sumCost = 0;
                    @endphp
                    @forelse($recipe->items as $idx => $item)
                        @php
                            $sumGross += (float) $item->gross_weight;
                            $sumNet += (float) $item->net_weight;
                            $sumFinal += (float) $item->final_weight;
                            $sumCost += (float) $item->total_cost;
                        @endphp
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 0.75rem 0.6rem; color: var(--text-muted); font-size: 0.8rem;">{{ $idx + 1 }}</td>
                            <td style="padding: 0.75rem 0.6rem;">
                                @if($item->type === \App\Models\RecipeItem::TYPE_SUB_RECIPE && $item->subRecipe)
                                    <a href="{{ route('admin.inventory.recipes.show', $item->subRecipe) }}" style="font-weight: 700; color: #a855f7; text-decoration: none;">
                                        <i class="fa-solid fa-layer-group"></i> {{ $item->subRecipe->name }}
                                    </a>
                                    <span style="font-size: 0.72rem; background: rgba(168, 85, 247, 0.12); color: #a855f7; padding: 0.15rem 0.4rem; border-radius: 4px; margin-left: 0.35rem;">
                                        {{ __('Sub-Recipe') }}
                                    </span>
                                @elseif($item->ingredient)
                                    <span style="font-weight: 600; color: var(--text-main);">
                                        {{ $item->ingredient->name }}
                                    </span>
                                    @if($item->is_optional)
                                        <span style="font-size: 0.72rem; color: var(--text-muted); margin-left: 0.35rem;">({{ __('Optional') }})</span>
                                    @endif
                                @else
                                    <span style="color: var(--text-muted);">{{ __('Unknown component') }}</span>
                                @endif
                            </td>
                            <td style="padding: 0.75rem 0.6rem; font-weight: 600; color: var(--text-muted);">{{ $item->unit?->code }}</td>
                            <td style="padding: 0.75rem 0.6rem; text-align: right; font-family: monospace; font-weight: 700;">{{ (float) $item->gross_weight }}</td>
                            <td style="padding: 0.75rem 0.6rem; text-align: right; color: var(--text-muted); font-family: monospace;">{{ (float) $item->prep_loss_percent }}%</td>
                            <td style="padding: 0.75rem 0.6rem; text-align: right; font-family: monospace; font-weight: 700;">{{ (float) $item->net_weight }}</td>
                            <td style="padding: 0.75rem 0.6rem; text-align: right; color: var(--text-muted); font-family: monospace;">{{ (float) $item->cooking_loss_percent }}%</td>
                            <td style="padding: 0.75rem 0.6rem; text-align: right; font-family: monospace; font-weight: 700; color: #10b981;">{{ (float) $item->final_weight }}</td>
                            @if($canViewCost)
                                <td style="padding: 0.75rem 0.6rem; text-align: right; font-family: monospace; color: var(--text-muted);">{{ number_format($item->unit_cost, 2) }} {{ Auth::user()->vendor->currency }}</td>
                                <td style="padding: 0.75rem 0.6rem; text-align: right; font-family: monospace; font-weight: 700; color: var(--text-main);">{{ number_format($item->total_cost, 2) }} {{ Auth::user()->vendor->currency }}</td>
                            @endif
                            <td style="padding: 0.75rem 0.6rem; color: var(--text-muted); font-size: 0.8rem;">{{ $item->notes ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canViewCost ? 11 : 9 }}" style="padding: 2rem; text-align: center; color: var(--text-muted);">
                                {{ __('No ingredients entered in this recipe.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr style="background: rgba(0,0,0,0.03); font-weight: 800; border-top: 2px solid var(--border-color);">
                        <td colspan="3" style="padding: 0.85rem 0.6rem;">{{ __('Total Raw Materials (BOM)') }}</td>
                        <td style="padding: 0.85rem 0.6rem; text-align: right; font-family: monospace;">{{ number_format($sumGross, 2) }}</td>
                        <td style="padding: 0.85rem 0.6rem;"></td>
                        <td style="padding: 0.85rem 0.6rem; text-align: right; font-family: monospace;">{{ number_format($sumNet, 2) }}</td>
                        <td style="padding: 0.85rem 0.6rem;"></td>
                        <td style="padding: 0.85rem 0.6rem; text-align: right; font-family: monospace; color: #10b981;">{{ number_format($sumFinal, 2) }}</td>
                        @if($canViewCost)
                            <td style="padding: 0.85rem 0.6rem;"></td>
                            <td style="padding: 0.85rem 0.6rem; text-align: right; font-family: monospace; color: #10b981;">{{ number_format($sumCost, 2) }} {{ Auth::user()->vendor->currency }}</td>
                        @endif
                        <td style="padding: 0.85rem 0.6rem;"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- 4. Packaging Materials Section (if any) -->
    @if($recipe->packagings->isNotEmpty())
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
            <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin: 0 0 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-box-open" style="color: #ec4899;"></i>
                <span>{{ __('Packaging Materials (Cost Factor)') }}</span>
            </h2>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.02);">
                            <th style="padding: 0.65rem;">{{ __('Item') }}</th>
                            <th style="padding: 0.65rem; text-align: right;">{{ __('Quantity') }}</th>
                            <th style="padding: 0.65rem;">{{ __('Unit') }}</th>
                            @if($canViewCost)
                                <th style="padding: 0.65rem; text-align: right;">{{ __('Unit Cost') }}</th>
                                <th style="padding: 0.65rem; text-align: right;">{{ __('Total Cost') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recipe->packagings as $pkg)
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 0.65rem; font-weight: 600;">{{ $pkg->ingredient?->name }}</td>
                                <td style="padding: 0.65rem; text-align: right; font-family: monospace;">{{ (float) $pkg->quantity }}</td>
                                <td style="padding: 0.65rem; color: var(--text-muted);">{{ $pkg->unit?->code }}</td>
                                @if($canViewCost)
                                    <td style="padding: 0.65rem; text-align: right; font-family: monospace;">{{ number_format($pkg->unit_cost, 2) }} {{ Auth::user()->vendor->currency }}</td>
                                    <td style="padding: 0.65rem; text-align: right; font-family: monospace; font-weight: 700;">{{ number_format($pkg->total_cost, 2) }} {{ Auth::user()->vendor->currency }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- 5. Instructions & HACCP CCP -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; box-shadow: var(--shadow-card);">
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.1rem; font-weight: 700; color: var(--text-main); margin: 0 0 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-fire-burner" style="color: #f59e0b;"></i>
                <span>{{ __('Preparation Steps & Cooking Process') }}</span>
            </h3>
            <div style="color: var(--text-main); font-size: 0.9rem; line-height: 1.6; white-space: pre-line;">
                {{ $recipe->instructions ?: __('No step-by-step preparation instructions recorded.') }}
            </div>
        </div>

        <div class="card" style="background: var(--bg-card); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 16px; padding: 1.5rem; box-shadow: var(--shadow-card);">
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.1rem; font-weight: 700; color: #ef4444; margin: 0 0 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-shield-halved"></i>
                <span>{{ __('HACCP Critical Control Points (CCP)') }}</span>
            </h3>
            <div style="color: var(--text-main); font-size: 0.9rem; line-height: 1.6; white-space: pre-line;">
                {{ $recipe->critical_control_points ?: __('No critical control points (CCP) identified for this recipe.') }}
            </div>
        </div>
    </div>

    <!-- 6. Version History -->
    @if($recipe->versions->isNotEmpty())
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 3rem; box-shadow: var(--shadow-card);">
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.1rem; font-weight: 700; color: var(--text-main); margin: 0 0 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-clock-rotate-left" style="color: #6366f1;"></i>
                <span>{{ __('Version History & Approval Log') }}</span>
            </h3>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.02);">
                            <th style="padding: 0.6rem;">{{ __('Version') }}</th>
                            <th style="padding: 0.6rem;">{{ __('Approved Date') }}</th>
                            <th style="padding: 0.6rem;">{{ __('Approved By') }}</th>
                            <th style="padding: 0.6rem; text-align: right;">{{ __('Portion Cost') }}</th>
                            <th style="padding: 0.6rem; text-align: right;">{{ __('Food Cost %') }}</th>
                            <th style="padding: 0.6rem;">{{ __('Change Summary') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recipe->versions as $v)
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 0.6rem; font-weight: 700; font-family: monospace;">v{{ $v->version_number }}</td>
                                <td style="padding: 0.6rem; color: var(--text-muted);">{{ ($v->approved_at ?? $v->created_at)?->toVendorTimezone()->format('Y-m-d H:i') }}</td>
                                <td style="padding: 0.6rem; font-weight: 600;">{{ $v->approver?->name ?? 'System' }}</td>
                                <td style="padding: 0.6rem; text-align: right; font-family: monospace;">{{ number_format($v->portion_cost, 2) }} {{ Auth::user()->vendor->currency }}</td>
                                <td style="padding: 0.6rem; text-align: right; font-family: monospace;">{{ $v->food_cost_percent !== null ? number_format($v->food_cost_percent, 1) . '%' : '—' }}</td>
                                <td style="padding: 0.6rem; color: var(--text-muted);">{{ $v->change_summary ?? 'Official version release' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

<!-- Recalculate Modal -->
<dialog id="recalculateModal" style="border: none; border-radius: 16px; padding: 0; background: transparent; max-width: 480px; width: 90%;">
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.75rem; box-shadow: var(--shadow-modal);">
        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin: 0 0 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-calculator" style="color: #3b82f6;"></i>
            <span>{{ __('Recalculate Recipe Cost') }}</span>
        </h3>
        <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0 0 1.25rem;">
            {{ __('Update portion costs using current inventory prices. Choose costing method and optional overhead.') }}
        </p>

        <form method="POST" action="{{ route('admin.inventory.recipes.recalculate', $recipe) }}">
            @csrf

            <div style="margin-bottom: 1rem;">
                <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                    {{ __('Costing Valuation Method') }}
                </label>
                <select name="cost_method" class="form-select" style="width: 100%; border-radius: 10px;">
                    <option value="weighted_average" {{ ($cost?->cost_method ?? '') === 'weighted_average' ? 'selected' : '' }}>
                        {{ __('Weighted Average Cost (WAC) - Recommended') }}
                    </option>
                    <option value="last_purchase_price" {{ ($cost?->cost_method ?? '') === 'last_purchase_price' ? 'selected' : '' }}>
                        {{ __('Last Purchase Price') }}
                    </option>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                        {{ __('Direct Labor') }} ({{ Auth::user()->vendor->currency }})
                    </label>
                    <input type="number" step="0.01" name="labor_cost" value="{{ $cost?->labor_cost ?? 0 }}" class="form-input" style="width: 100%; border-radius: 8px;">
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                        {{ __('Overhead') }} ({{ Auth::user()->vendor->currency }})
                    </label>
                    <input type="number" step="0.01" name="overhead_cost" value="{{ $cost?->overhead_cost ?? 0 }}" class="form-input" style="width: 100%; border-radius: 8px;">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="document.getElementById('recalculateModal').close();" class="btn btn-secondary" style="border-radius: 10px;">
                    {{ __('Cancel') }}
                </button>
                <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 700;">
                    <i class="fa-solid fa-arrows-rotate"></i> {{ __('Recalculate Now') }}
                </button>
            </div>
        </form>
    </div>
</dialog>
@endsection

