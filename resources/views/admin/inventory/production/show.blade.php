@extends('layouts.app')

@section('title', __('Production Order') . ' ' . $productionOrder->order_number . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="max-width: 1200px; margin: 0 auto;" x-data="{ completeModalOpen: false, cancelModalOpen: false }">
    <!-- Top Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <a href="{{ route('admin.inventory.production.index') }}" style="color: var(--text-muted); text-decoration: none; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 0.4rem; margin-bottom: 0.5rem;">
                <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Production Orders') }}
            </a>
            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0;">
                    {{ $productionOrder->order_number }}
                </h1>
                @if($productionOrder->status === 'draft')
                    <span class="badge" style="background: rgba(107, 114, 128, 0.15); color: #6b7280; font-size: 0.85rem;">{{ __('Draft') }}</span>
                @elseif($productionOrder->status === 'in_progress')
                    <span class="badge" style="background: rgba(249, 115, 22, 0.15); color: #f97316; font-size: 0.85rem;">
                        <i class="fa-solid fa-spinner fa-spin" style="margin-right: 0.25rem;"></i> {{ __('In Progress') }}
                    </span>
                @elseif($productionOrder->status === 'completed')
                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-size: 0.85rem;">{{ __('Completed') }}</span>
                @elseif($productionOrder->status === 'cancelled')
                    <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; font-size: 0.85rem;">{{ __('Cancelled') }}</span>
                @endif
            </div>
        </div>

        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()" style="border-radius: 10px; font-weight: 600;">
                <i class="fa-solid fa-print"></i> {{ __('Print Sheet') }}
            </button>

            @if($productionOrder->isDraft())
                <form method="POST" action="{{ route('admin.inventory.production.start', $productionOrder) }}" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-warning" style="border-radius: 10px; font-weight: 700; color: #fff;">
                        <i class="fa-solid fa-play"></i> {{ __('Start Production') }}
                    </button>
                </form>
            @endif

            @if($productionOrder->isInProgress() || $productionOrder->isDraft())
                <button type="button" class="btn btn-success" @click="completeModalOpen = true" style="border-radius: 10px; font-weight: 700; background: #10b981; border-color: #10b981;">
                    <i class="fa-solid fa-check-double"></i> {{ __('Complete Production') }}
                </button>

                <button type="button" class="btn btn-outline-danger" @click="cancelModalOpen = true" style="border-radius: 10px; font-weight: 600;">
                    <i class="fa-solid fa-ban"></i> {{ __('Cancel Order') }}
                </button>
            @endif
        </div>
    </div>

    <!-- Alert Banners -->
    @if(session('success'))
        <div class="alert alert-success" style="border-radius: 12px; margin-bottom: 1.5rem;">
            <i class="fa-solid fa-circle-check" style="margin-right: 0.5rem;"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger" style="border-radius: 12px; margin-bottom: 1.5rem;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 0.5rem;"></i> {{ session('error') }}
        </div>
    @endif

    <!-- 3-Column Info Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
        <!-- Card 1: Recipe & Route -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card);">
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.5rem;">
                <i class="fa-solid fa-utensils" style="color: #f59e0b; margin-right: 0.35rem;"></i> {{ __('Recipe & Routing') }}
            </div>
            <div style="font-size: 1.15rem; font-weight: 700; color: var(--text-main);">
                <a href="{{ route('admin.inventory.recipes.show', $productionOrder->recipe) }}" style="color: inherit; text-decoration: none;">
                    {{ $productionOrder->recipe->name }}
                </a>
            </div>
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('Code') }}: <code>{{ $productionOrder->recipe->code }}</code> | {{ ucfirst($productionOrder->recipe->type) }}
            </div>
            <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px dashed var(--border-color); font-size: 0.85rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.3rem;">
                    <span style="color: var(--text-muted);">{{ __('Source Warehouse') }}:</span>
                    <span style="font-weight: 600;">{{ $productionOrder->sourceWarehouse->name }}</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-muted);">{{ __('Dest Warehouse') }}:</span>
                    <span style="font-weight: 600; color: #10b981;">{{ $productionOrder->destinationWarehouse->name }}</span>
                </div>
            </div>
        </div>

        <!-- Card 2: Quantities & Yield Variance -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card);">
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.5rem;">
                <i class="fa-solid fa-chart-column" style="color: #06b6d4; margin-right: 0.35rem;"></i> {{ __('Yield & Output') }}
            </div>
            <div style="display: flex; justify-content: space-between; align-items: baseline;">
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">{{ __('Planned Yield') }}</div>
                    <div style="font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                        {{ number_format((float)$productionOrder->planned_quantity, 2) }} {{ $productionOrder->unit->code }}
                    </div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-align: right;">{{ __('Actual Yield') }}</div>
                    <div style="font-size: 1.25rem; font-weight: 800; color: #10b981; text-align: right;">
                        {{ $productionOrder->actual_quantity !== null ? number_format((float)$productionOrder->actual_quantity, 2) . ' ' . $productionOrder->unit->code : '-' }}
                    </div>
                </div>
            </div>
            <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px dashed var(--border-color); display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem;">
                <span style="color: var(--text-muted);">{{ __('Yield Variance') }}:</span>
                @if($productionOrder->yield_variance_percent !== null)
                    @php $v = (float)$productionOrder->yield_variance_percent; @endphp
                    <span class="badge" style="background: {{ $v >= 0 ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)' }}; color: {{ $v >= 0 ? '#10b981' : '#ef4444' }}; font-weight: 700; font-size: 0.8rem;">
                        {{ $v >= 0 ? '+' : '' }}{{ $v }}%
                    </span>
                @else
                    <span style="color: var(--text-muted);">-</span>
                @endif
            </div>
        </div>

        <!-- Card 3: Cost Accounting Summary -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card);">
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.5rem;">
                <i class="fa-solid fa-coins" style="color: #10b981; margin-right: 0.35rem;"></i> {{ __('Cost Accounting') }}
            </div>
            <div style="display: flex; justify-content: space-between; align-items: baseline;">
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">{{ __('Total Cost') }}</div>
                    <div style="font-size: 1.35rem; font-weight: 800; color: var(--text-main);">
                        {{ number_format((float)$productionOrder->total_cost, 2) }} {{ Auth::user()->vendor->currency ?? 'AMD' }}
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.75rem; color: var(--text-muted);">{{ __('Unit Cost') }}</div>
                    <div style="font-size: 1.15rem; font-weight: 700; color: #10b981;">
                        {{ number_format((float)$productionOrder->unit_cost, 2) }} / {{ $productionOrder->unit->code }}
                    </div>
                </div>
            </div>
            <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px dashed var(--border-color); font-size: 0.75rem; color: var(--text-muted); display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.25rem;">
                <div>{{ __('Materials') }}: <strong style="color: var(--text-main);">{{ number_format((float)$productionOrder->material_cost, 2) }}</strong></div>
                <div>{{ __('Labor') }}: <strong style="color: var(--text-main);">{{ number_format((float)$productionOrder->labor_cost, 2) }}</strong></div>
                <div>{{ __('Overhead') }}: <strong style="color: var(--text-main);">{{ number_format((float)$productionOrder->overhead_cost, 2) }}</strong></div>
            </div>
        </div>
    </div>

    <!-- Bill of Materials (BOM) Sheet Table -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
        <div style="padding: 1.25rem 1.5rem; background: var(--bg-hover); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <div>
                <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-list-check" style="color: #f97316;"></i> {{ __('Bill of Materials & Component Consumption') }}
                </h3>
            </div>
            <div style="font-size: 0.85rem; color: var(--text-muted);">
                {{ $productionOrder->items->count() }} {{ __('Components') }}
            </div>
        </div>

        <div class="table-responsive">
            <table class="table" style="margin: 0; vertical-align: middle;">
                <thead style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); background: var(--bg-hover);">
                    <tr>
                        <th style="padding: 1rem 1.25rem;">{{ __('Component') }}</th>
                        <th style="padding: 1rem;">{{ __('Type') }}</th>
                        <th style="padding: 1rem; text-align: right;">{{ __('Planned Qty') }}</th>
                        <th style="padding: 1rem; text-align: right;">{{ __('Actual Consumed') }}</th>
                        <th style="padding: 1rem; text-align: right;">{{ __('Waste / Scrap') }}</th>
                        <th style="padding: 1rem; text-align: right;">{{ __('Unit Cost') }}</th>
                        <th style="padding: 1rem; text-align: right;">{{ __('Total Cost') }}</th>
                        <th style="padding: 1rem 1.25rem; text-align: center;">{{ __('Usage Var') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($productionOrder->items as $item)
                        <tr style="border-top: 1px solid var(--border-color);">
                            <td style="padding: 1rem 1.25rem;">
                                <div style="font-weight: 600; color: var(--text-main);">
                                    @if($item->ingredient)
                                        <a href="{{ route('admin.inventory.ingredients.show', $item->ingredient) }}" style="color: inherit; text-decoration: none;">
                                            {{ $item->ingredient->name }}
                                        </a>
                                    @elseif($item->subRecipe)
                                        <a href="{{ route('admin.inventory.recipes.show', $item->subRecipe) }}" style="color: inherit; text-decoration: none;">
                                            {{ $item->subRecipe->name }}
                                        </a>
                                    @endif
                                </div>
                                @if($item->notes)
                                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
                                        {{ $item->notes }}
                                    </div>
                                @endif
                            </td>
                            <td style="padding: 1rem;">
                                <span class="badge" style="background: rgba(107, 114, 128, 0.15); color: var(--text-muted); font-size: 0.7rem; text-transform: uppercase;">
                                    {{ $item->ingredient ? __('Raw Material') : __('Sub-Recipe') }}
                                </span>
                            </td>
                            <td style="padding: 1rem; text-align: right; color: var(--text-muted);">
                                {{ number_format((float)$item->planned_quantity, 4) }} {{ $item->unit->code }}
                            </td>
                            <td style="padding: 1rem; text-align: right; font-weight: 700;">
                                @if($item->actual_quantity !== null)
                                    <span style="color: #10b981;">{{ number_format((float)$item->actual_quantity, 4) }} {{ $item->unit->code }}</span>
                                @else
                                    <span style="color: var(--text-muted);">-</span>
                                @endif
                            </td>
                            <td style="padding: 1rem; text-align: right;">
                                @if((float)$item->waste_quantity > 0)
                                    <span style="color: #ef4444; font-weight: 600;">{{ number_format((float)$item->waste_quantity, 4) }} {{ $item->unit->code }}</span>
                                @else
                                    <span style="color: var(--text-muted);">0.0000</span>
                                @endif
                            </td>
                            <td style="padding: 1rem; text-align: right;">
                                {{ number_format((float)$item->unit_cost, 2) }}
                            </td>
                            <td style="padding: 1rem; text-align: right; font-weight: 700; color: var(--text-main);">
                                {{ number_format((float)$item->total_cost, 2) }}
                            </td>
                            <td style="padding: 1rem 1.25rem; text-align: center;">
                                @php $uVar = $item->calculateUsageVariance(); @endphp
                                @if($uVar !== null)
                                    <span class="badge" style="background: {{ $uVar <= 0 ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)' }}; color: {{ $uVar <= 0 ? '#10b981' : '#ef4444' }}; font-weight: 700;">
                                        {{ $uVar > 0 ? '+' : '' }}{{ $uVar }}%
                                    </span>
                                @else
                                    <span style="color: var(--text-muted);">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Traceability, Batch & Ledger Audit Section -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
        <!-- Batches -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card);">
            <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--text-main); margin: 0 0 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-barcode" style="color: #6366f1;"></i> {{ __('Output Traceability & Batches') }}
            </h4>
            @if($productionOrder->batches->isNotEmpty())
                @foreach($productionOrder->batches as $batch)
                    <div style="background: var(--bg-hover); border-radius: 12px; padding: 0.85rem 1rem; margin-bottom: 0.75rem; border: 1px solid var(--border-color);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                            <span style="font-family: monospace; font-weight: 700; color: #6366f1;">{{ $batch->batch_number }}</span>
                            <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-size: 0.7rem; text-transform: uppercase;">{{ $batch->status }}</span>
                        </div>
                        <div style="font-size: 0.8rem; color: var(--text-muted); display: flex; justify-content: space-between;">
                            <span>{{ __('Produced') }}: {{ $batch->production_date?->format('M d, Y') ?? '-' }}</span>
                            <span>{{ __('Expires') }}: <strong style="color: var(--text-main);">{{ $batch->expiry_date?->format('M d, Y') ?? __('N/A') }}</strong></span>
                        </div>
                    </div>
                @endforeach
            @else
                <div style="color: var(--text-muted); font-size: 0.85rem; text-align: center; padding: 1.5rem 0;">
                    <i class="fa-solid fa-box-open" style="font-size: 1.5rem; opacity: 0.5; margin-bottom: 0.5rem; display: block;"></i>
                    {{ __('No batch generated yet. Batches are created automatically upon completion.') }}
                </div>
            @endif
        </div>

        <!-- Inventory Ledger Transactions -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card);">
            <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--text-main); margin: 0 0 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-book-bookmark" style="color: #10b981;"></i> {{ __('Stock Ledger Audit') }}
            </h4>
            <div style="font-size: 0.85rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0; border-bottom: 1px solid var(--border-color);">
                    <span style="color: var(--text-muted);">{{ __('Raw Materials Issue') }}:</span>
                    @if($productionOrder->issueTransaction)
                        <span style="font-weight: 600; color: #ef4444;">
                            <i class="fa-solid fa-arrow-trend-down"></i> {{ $productionOrder->issueTransaction->transaction_number }}
                        </span>
                    @else
                        <span style="color: var(--text-muted);">{{ __('Pending completion') }}</span>
                    @endif
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0;">
                    <span style="color: var(--text-muted);">{{ __('Output Stock Receipt') }}:</span>
                    @if($productionOrder->receiptTransaction)
                        <span style="font-weight: 600; color: #10b981;">
                            <i class="fa-solid fa-arrow-trend-up"></i> {{ $productionOrder->receiptTransaction->transaction_number }}
                        </span>
                    @else
                        <span style="color: var(--text-muted);">{{ __('Pending completion') }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Complete Modal -->
    <div x-show="completeModalOpen" x-cloak style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div class="card" style="background: var(--bg-card); border-radius: 16px; max-width: 650px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 1.75rem; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-check-double" style="color: #10b981;"></i> {{ __('Complete Production Order') }}
                </h3>
                <button type="button" @click="completeModalOpen = false" class="btn-close" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">
                    &times;
                </button>
            </div>

            <form method="POST" action="{{ route('admin.inventory.production.complete', $productionOrder) }}">
                @csrf

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label class="form-label" style="font-weight: 600;">{{ __('Actual Produced Quantity') }} <span style="color: #ef4444;">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.0001" min="0.0001" name="actual_quantity" class="form-control" value="{{ (float)$productionOrder->planned_quantity }}" required>
                            <span class="input-group-text">{{ $productionOrder->unit->code }}</span>
                        </div>
                    </div>

                    <div>
                        <label class="form-label" style="font-weight: 600;">{{ __('Batch / Lot #') }}</label>
                        <input type="text" name="batch_number" class="form-control" value="{{ 'LOT-' . date('Ymd') . '-' . str_pad($productionOrder->id, 4, '0', STR_PAD_LEFT) }}">
                    </div>

                    <div>
                        <label class="form-label" style="font-weight: 600;">{{ __('Expiry Date') }}</label>
                        <input type="date" name="expiry_date" class="form-control" value="{{ $productionOrder->recipe->shelf_life_hours ? now()->addHours($productionOrder->recipe->shelf_life_hours)->toDateString() : '' }}">
                    </div>

                    <div>
                        <label class="form-label" style="font-weight: 600;">{{ __('Actual Labor Cost') }}</label>
                        <input type="number" step="0.01" min="0" name="labor_cost" class="form-control" value="{{ (float)$productionOrder->labor_cost }}">
                    </div>
                </div>

                <div style="margin-bottom: 1.25rem;">
                    <label class="form-label" style="font-weight: 600;">{{ __('Actual Overhead Cost') }}</label>
                    <input type="number" step="0.01" min="0" name="overhead_cost" class="form-control" value="{{ (float)$productionOrder->overhead_cost }}">
                </div>

                <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-main); margin-bottom: 0.5rem;">
                    {{ __('Actual Component Consumption & Waste') }}
                </div>
                <div style="max-height: 200px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 10px; margin-bottom: 1.5rem;">
                    <table class="table table-sm" style="margin: 0; font-size: 0.85rem;">
                        <thead style="background: var(--bg-hover);">
                            <tr>
                                <th style="padding: 0.5rem 0.75rem;">{{ __('Item') }}</th>
                                <th style="padding: 0.5rem; text-align: right;">{{ __('Actual Used') }}</th>
                                <th style="padding: 0.5rem 0.75rem; text-align: right;">{{ __('Scrap / Waste') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($productionOrder->items as $idx => $item)
                                <tr>
                                    <td style="padding: 0.5rem 0.75rem;">
                                        <input type="hidden" name="items[{{ $idx }}][id]" value="{{ $item->id }}">
                                        <strong>{{ $item->ingredient ? $item->ingredient->name : $item->subRecipe->name }}</strong>
                                        <span style="font-size: 0.75rem; color: var(--text-muted); display: block;">
                                            {{ __('Planned') }}: {{ number_format((float)$item->planned_quantity, 4) }} {{ $item->unit->code }}
                                        </span>
                                    </td>
                                    <td style="padding: 0.5rem; text-align: right;">
                                        <input type="number" step="0.0001" min="0" name="items[{{ $idx }}][actual_quantity]" class="form-control form-control-sm" style="max-width: 110px; display: inline-block;" value="{{ (float)$item->planned_quantity }}">
                                    </td>
                                    <td style="padding: 0.5rem 0.75rem; text-align: right;">
                                        <input type="number" step="0.0001" min="0" name="items[{{ $idx }}][waste_quantity]" class="form-control form-control-sm" style="max-width: 90px; display: inline-block;" value="0">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" @click="completeModalOpen = false" class="btn btn-outline-secondary" style="border-radius: 10px;">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit" class="btn btn-success" style="border-radius: 10px; font-weight: 700; background: #10b981; border-color: #10b981;">
                        <i class="fa-solid fa-check"></i> {{ __('Finalize Production & Book Stock') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cancel Modal -->
    <div x-show="cancelModalOpen" x-cloak style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div class="card" style="background: var(--bg-card); border-radius: 16px; max-width: 480px; width: 100%; padding: 1.75rem;">
            <h3 style="font-size: 1.15rem; font-weight: 700; color: #ef4444; margin: 0 0 1rem;">
                <i class="fa-solid fa-triangle-exclamation"></i> {{ __('Cancel Production Order') }}
            </h3>
            <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                {{ __('Are you sure you want to cancel order') }} <strong>{{ $productionOrder->order_number }}</strong>?
            </p>
            <form method="POST" action="{{ route('admin.inventory.production.cancel', $productionOrder) }}">
                @csrf
                <div style="margin-bottom: 1.25rem;">
                    <label class="form-label" style="font-weight: 600;">{{ __('Cancellation Reason') }}</label>
                    <textarea name="reason" rows="2" class="form-control" style="border-radius: 10px;" placeholder="{{ __('e.g., Equipment breakdown, batch spoiled...') }}"></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" @click="cancelModalOpen = false" class="btn btn-outline-secondary" style="border-radius: 10px;">
                        {{ __('Keep Order') }}
                    </button>
                    <button type="submit" class="btn btn-danger" style="border-radius: 10px; font-weight: 700;">
                        {{ __('Confirm Cancel') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

