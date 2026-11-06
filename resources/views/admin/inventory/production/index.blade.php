@extends('layouts.app')

@section('title', __('Production Orders & Processing') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div style="min-width: 0; flex: 1;">
        <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
            <span style="background: rgba(249, 115, 22, 0.15); color: #f97316; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                <i class="fa-solid fa-industry"></i>
            </span>
            <span>{{ __('Production Orders & Processing') }}</span>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0;">
            {{ __('Manage semi-finished and finished kitchen prep orders, bill-of-materials batch tracking, and yield variance.') }}
        </p>
    </div>
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        @can('production.create')
            <a href="{{ route('admin.inventory.production.create') }}" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; border-radius: 12px; font-weight: 700; background: #f97316; border-color: #f97316;">
                <i class="fa-solid fa-plus"></i> {{ __('Create Production Order') }}
            </a>
        @endcan
    </div>
</div>

<!-- KPI Metrics -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-card);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(249, 115, 22, 0.12); color: #f97316; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="fa-solid fa-fire-burner"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('In Progress') }}</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: #f97316;">{{ $metrics['in_progress'] }}</div>
        </div>
    </div>

    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-card);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(16, 185, 129, 0.12); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="fa-solid fa-check-double"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Completed Batches') }}</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: #10b981;">{{ $metrics['completed'] }}</div>
        </div>
    </div>

    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-card);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(99, 102, 241, 0.12); color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="fa-solid fa-coins"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Total Value Produced') }}</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: var(--text-main);">
                {{ number_format($metrics['total_cost_completed'], 2) }} {{ Auth::user()->vendor->currency ?? 'AMD' }}
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
    <form method="GET" action="{{ route('admin.inventory.production.index') }}" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
        <div style="flex: 1; min-width: 220px; position: relative;">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by order #, batch # or recipe...') }}" class="form-control" style="border-radius: 10px; padding-left: 2.25rem;">
            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
        </div>

        <div style="min-width: 160px;">
            <select name="status" class="form-select" style="border-radius: 10px;" onchange="this.form.submit()">
                <option value="">{{ __('All Statuses') }}</option>
                <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>{{ __('Draft') }}</option>
                <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>{{ __('In Progress') }}</option>
                <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>{{ __('Completed') }}</option>
                <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>{{ __('Cancelled') }}</option>
            </select>
        </div>

        <div style="min-width: 180px;">
            <select name="recipe_id" class="form-select" style="border-radius: 10px;" onchange="this.form.submit()">
                <option value="">{{ __('All Recipes') }}</option>
                @foreach($recipes as $recipe)
                    <option value="{{ $recipe->id }}" {{ (string)request('recipe_id') === (string)$recipe->id ? 'selected' : '' }}>
                        {{ $recipe->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="btn btn-secondary" style="border-radius: 10px;">
            <i class="fa-solid fa-filter"></i> {{ __('Filter') }}
        </button>

        @if(request()->hasAny(['search', 'status', 'recipe_id']))
            <a href="{{ route('admin.inventory.production.index') }}" class="btn btn-outline-secondary" style="border-radius: 10px;">
                <i class="fa-solid fa-rotate-left"></i> {{ __('Clear') }}
            </a>
        @endif
    </form>
</div>

<!-- Table -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-card);">
    <div class="table-responsive">
        <table class="table" style="margin: 0; vertical-align: middle;">
            <thead style="background: var(--bg-hover); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted);">
                <tr>
                    <th style="padding: 1rem 1.25rem;">{{ __('Order #') }}</th>
                    <th style="padding: 1rem;">{{ __('Recipe') }}</th>
                    <th style="padding: 1rem;">{{ __('Route (Source → Dest)') }}</th>
                    <th style="padding: 1rem; text-align: right;">{{ __('Planned Qty') }}</th>
                    <th style="padding: 1rem; text-align: right;">{{ __('Actual Qty') }}</th>
                    <th style="padding: 1rem; text-align: right;">{{ __('Cost / Unit') }}</th>
                    <th style="padding: 1rem; text-align: center;">{{ __('Variance') }}</th>
                    <th style="padding: 1rem;">{{ __('Batch #') }}</th>
                    <th style="padding: 1rem; text-align: center;">{{ __('Status') }}</th>
                    <th style="padding: 1rem 1.25rem; text-align: right;">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                    <tr style="border-top: 1px solid var(--border-color);">
                        <td style="padding: 1rem 1.25rem;">
                            <a href="{{ route('admin.inventory.production.show', $order) }}" style="font-weight: 700; color: #f97316; text-decoration: none;">
                                {{ $order->order_number }}
                            </a>
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
                                {{ $order->created_at->toVendorTimezone()->format('M d, Y H:i') }}
                            </div>
                        </td>
                        <td style="padding: 1rem;">
                            <div style="font-weight: 600; color: var(--text-main);">
                                {{ $order->recipe->name }}
                            </div>
                            <span class="badge" style="background: rgba(107, 114, 128, 0.15); color: var(--text-muted); font-size: 0.7rem; text-transform: uppercase;">
                                {{ $order->recipe->type }}
                            </span>
                        </td>
                        <td style="padding: 1rem;">
                            <div style="font-size: 0.85rem; color: var(--text-main); display: flex; align-items: center; gap: 0.4rem;">
                                <span>{{ $order->sourceWarehouse->name }}</span>
                                <i class="fa-solid fa-arrow-right" style="font-size: 0.7rem; color: var(--text-muted);"></i>
                                <span style="font-weight: 600;">{{ $order->destinationWarehouse->name }}</span>
                            </div>
                        </td>
                        <td style="padding: 1rem; text-align: right; font-weight: 600;">
                            {{ number_format((float)$order->planned_quantity, 2) }} {{ $order->unit->code }}
                        </td>
                        <td style="padding: 1rem; text-align: right; font-weight: 700;">
                            @if($order->actual_quantity !== null)
                                <span style="color: #10b981;">{{ number_format((float)$order->actual_quantity, 2) }} {{ $order->unit->code }}</span>
                            @else
                                <span style="color: var(--text-muted);">-</span>
                            @endif
                        </td>
                        <td style="padding: 1rem; text-align: right;">
                            <div style="font-weight: 700; color: var(--text-main);">
                                {{ number_format((float)$order->total_cost, 2) }} {{ Auth::user()->vendor->currency }}
                            </div>
                            @if((float)$order->unit_cost > 0)
                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                    {{ number_format((float)$order->unit_cost, 2) }} {{ Auth::user()->vendor->currency }} / {{ $order->unit->code }}
                                </div>
                            @endif
                        </td>
                        <td style="padding: 1rem; text-align: center;">
                            @if($order->yield_variance_percent !== null)
                                @php $var = (float)$order->yield_variance_percent; @endphp
                                <span class="badge" style="background: {{ $var >= 0 ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)' }}; color: {{ $var >= 0 ? '#10b981' : '#ef4444' }}; font-weight: 700;">
                                    {{ $var >= 0 ? '+' : '' }}{{ $var }}%
                                </span>
                            @else
                                <span style="color: var(--text-muted);">-</span>
                            @endif
                        </td>
                        <td style="padding: 1rem;">
                            @if($order->batch_number)
                                <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: #6366f1; font-family: monospace;">
                                    {{ $order->batch_number }}
                                </span>
                            @else
                                <span style="color: var(--text-muted);">-</span>
                            @endif
                        </td>
                        <td style="padding: 1rem; text-align: center;">
                            @if($order->status === 'draft')
                                <span class="badge" style="background: rgba(107, 114, 128, 0.15); color: #6b7280;">{{ __('Draft') }}</span>
                            @elseif($order->status === 'in_progress')
                                <span class="badge" style="background: rgba(249, 115, 22, 0.15); color: #f97316;">
                                    <i class="fa-solid fa-spinner fa-spin" style="margin-right: 0.25rem;"></i> {{ __('In Progress') }}
                                </span>
                            @elseif($order->status === 'completed')
                                <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">{{ __('Completed') }}</span>
                            @elseif($order->status === 'cancelled')
                                <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">{{ __('Cancelled') }}</span>
                            @endif
                        </td>
                        <td style="padding: 1rem 1.25rem; text-align: right;">
                            <div style="display: inline-flex; gap: 0.4rem;">
                                <a href="{{ route('admin.inventory.production.show', $order) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Production Sheet') }}" style="border-radius: 8px;">
                                    <i class="fa-solid fa-clipboard-list"></i>
                                </a>

                                @if($order->isDraft())
                                    <form method="POST" action="{{ route('admin.inventory.production.start', $order) }}" style="display: inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-warning" title="{{ __('Start Production') }}" style="border-radius: 8px;">
                                            <i class="fa-solid fa-play"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                            <i class="fa-solid fa-industry" style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.4; display: block;"></i>
                            <div style="font-size: 1.1rem; font-weight: 700; color: var(--text-main);">{{ __('No production orders found') }}</div>
                            <p style="margin: 0.5rem 0 1.25rem;">{{ __('Create your first production order from a technical card or recipe.') }}</p>
                            @can('production.create')
                                <a href="{{ route('admin.inventory.production.create') }}" class="btn btn-primary" style="border-radius: 10px; background: #f97316; border-color: #f97316;">
                                    <i class="fa-solid fa-plus"></i> {{ __('Create Production Order') }}
                                </a>
                            @endcan
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($orders->hasPages())
        <div style="padding: 1rem; border-top: 1px solid var(--border-color);">
            {{ $orders->links() }}
        </div>
    @endif
</div>
@endsection

