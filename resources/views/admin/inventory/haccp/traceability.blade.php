@extends('layouts.app')

@section('title', __('HACCP Traceability & Recall Audit') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="max-width: 1200px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-route"></i>
                </span>
                <span>{{ __('HACCP Bidirectional Traceability & Recall Simulation') }}</span>
            </h1>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0;">
                {{ __('Trace upstream raw material supplier origins and downstream customer orders for food safety recall audits.') }}
            </p>
        </div>
        <div>
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()" style="border-radius: 10px; font-weight: 600;">
                <i class="fa-solid fa-print"></i> {{ __('Print Recall Dossier') }}
            </button>
        </div>
    </div>

    <!-- HACCP Sub-Navigation Tabs -->
    <div style="display: flex; gap: 0.5rem; border-bottom: 1px solid var(--border-color); margin-bottom: 1.5rem; overflow-x: auto; padding-bottom: 0.5rem;">
        <a href="{{ route('admin.inventory.haccp.dashboard') }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px; font-weight: 600;">
            <i class="fa-solid fa-gauge-high"></i> {{ __('Dashboard') }}
        </a>
        <a href="{{ route('admin.inventory.haccp.temperatures') }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px; font-weight: 600;">
            <i class="fa-solid fa-temperature-half"></i> {{ __('Temperature Logs') }}
        </a>
        <a href="{{ route('admin.inventory.haccp.checklists') }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px; font-weight: 600;">
            <i class="fa-solid fa-clipboard-check"></i> {{ __('Sanitation Checklists') }}
        </a>
        <a href="{{ route('admin.inventory.haccp.ccps') }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px; font-weight: 600;">
            <i class="fa-solid fa-sliders"></i> {{ __('Critical Control Points (CCPs)') }}
        </a>
        <a href="{{ route('admin.inventory.haccp.batches') }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px; font-weight: 600;">
            <i class="fa-solid fa-boxes-packing"></i> {{ __('Batches & Expiry (FEFO)') }}
        </a>
        <a href="{{ route('admin.inventory.haccp.traceability') }}" class="btn btn-sm btn-primary" style="border-radius: 8px; font-weight: 600;">
            <i class="fa-solid fa-route"></i> {{ __('Traceability & Recall Audit') }}
        </a>
    </div>

    <!-- Search / Barcode Input Card -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
        <form method="GET" action="{{ route('admin.inventory.haccp.traceability') }}" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 280px; position: relative;">
                <input type="text" name="batch_number" value="{{ $batchNumber }}" placeholder="{{ __('Enter or scan batch number (e.g. LOT-20261106-0001)...') }}" class="form-control" style="border-radius: 10px; padding-left: 2.5rem; font-family: monospace; font-size: 1.05rem;" required>
                <i class="fa-solid fa-barcode" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 1.1rem;"></i>
            </div>
            <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 700; background: #10b981; border-color: #10b981; padding: 0.6rem 1.5rem;">
                <i class="fa-solid fa-magnifying-glass"></i> {{ __('Trace Batch') }}
            </button>
        </form>
    </div>

    @if($traceResult && $traceResult['batch'])
        @php $batch = $traceResult['batch']; @endphp

        <!-- Batch Passport Header Card -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1.25rem; margin-bottom: 1.25rem;">
                <div>
                    <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: #6366f1; font-family: monospace; font-size: 0.9rem; margin-bottom: 0.5rem;">
                        {{ $batch->batch_number }}
                    </span>
                    <h2 style="font-size: 1.35rem; font-weight: 800; color: var(--text-main); margin: 0;">
                        {{ $batch->ingredient->name }}
                    </h2>
                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
                        SKU: <code>{{ $batch->ingredient->sku }}</code> | {{ ucfirst($batch->ingredient->type) }}
                    </div>
                </div>

                <div style="text-align: right;">
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Warehouse On-Hand') }}</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #10b981;">
                        {{ number_format((float)$batch->remaining_quantity, 2) }} {{ $batch->unit->code }}
                    </div>
                    <small style="color: var(--text-muted);">{{ $batch->warehouse->name }}</small>
                </div>
            </div>

            <!-- 3-Column Upstream & Dates Summary -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem; font-size: 0.85rem;">
                <!-- Upstream Origin -->
                <div style="background: var(--bg-hover); border-radius: 12px; padding: 1rem;">
                    <strong style="color: var(--text-main); display: block; margin-bottom: 0.5rem;">
                        <i class="fa-solid fa-arrow-turn-down" style="color: #6366f1; margin-right: 0.35rem;"></i> {{ __('Upstream Origin') }}
                    </strong>
                    @if($traceResult['source'])
                        @if($traceResult['source']['type'] === 'purchase')
                            <div>{{ __('Source') }}: <strong>{{ __('Supplier Purchase') }}</strong></div>
                            <div>{{ __('Supplier') }}: <strong>{{ $traceResult['source']['supplier_name'] }}</strong></div>
                            <div>{{ __('PO #') }}: <strong>{{ $traceResult['source']['po_number'] }}</strong></div>
                        @else
                            <div>{{ __('Source') }}: <strong>{{ __('Kitchen Production') }}</strong></div>
                            <div>{{ __('Order #') }}: <strong>{{ $traceResult['source']['order_number'] }}</strong></div>
                            <div>{{ __('Recipe') }}: <strong>{{ $traceResult['source']['recipe_name'] }}</strong></div>
                        @endif
                    @else
                        <span style="color: var(--text-muted);">{{ __('Initial Opening Balance') }}</span>
                    @endif
                </div>

                <!-- Life Dates -->
                <div style="background: var(--bg-hover); border-radius: 12px; padding: 1rem;">
                    <strong style="color: var(--text-main); display: block; margin-bottom: 0.5rem;">
                        <i class="fa-solid fa-calendar-day" style="color: #f59e0b; margin-right: 0.35rem;"></i> {{ __('Production & Expiry') }}
                    </strong>
                    <div>{{ __('Produced / Received') }}: <strong>{{ $batch->production_date ? $batch->production_date->toVendorTimezone()->format('M d, Y') : '-' }}</strong></div>
                    <div>{{ __('Expiry Date') }}: <strong style="color: {{ $batch->isExpired() ? '#ef4444' : '#10b981' }};">{{ $batch->expiry_date ? $batch->expiry_date->toVendorTimezone()->format('M d, Y') : __('N/A') }}</strong></div>
                    <div>{{ __('Status') }}: <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; text-transform: uppercase;">{{ $batch->status }}</span></div>
                </div>

                <!-- Valuation -->
                <div style="background: var(--bg-hover); border-radius: 12px; padding: 1rem;">
                    <strong style="color: var(--text-main); display: block; margin-bottom: 0.5rem;">
                        <i class="fa-solid fa-coins" style="color: #10b981; margin-right: 0.35rem;"></i> {{ __('Cost Accounting') }}
                    </strong>
                    <div>{{ __('Unit Cost') }}: <strong>{{ number_format((float)$batch->unit_cost, 2) }} {{ Auth::user()->vendor->currency }} / {{ $batch->unit->code }}</strong></div>
                    <div>{{ __('Total Current Value') }}: <strong>{{ number_format((float)($batch->remaining_quantity * $batch->unit_cost), 2) }} {{ Auth::user()->vendor->currency }}</strong></div>
                </div>
            </div>
        </div>

        <!-- Downstream Recall Impact Table -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; margin-bottom: 2rem; box-shadow: var(--shadow-card);">
            <div style="padding: 1.25rem 1.5rem; background: var(--bg-hover); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-users" style="color: #ef4444;"></i> {{ __('Downstream Customer Orders Recall Impact') }}
                </h3>
                <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; font-weight: 800;">
                    {{ $traceResult['customer_orders']->count() }} {{ __('Potentially Affected Orders') }}
                </span>
            </div>

            <div class="table-responsive">
                <table class="table" style="margin: 0; vertical-align: middle;">
                    <thead style="background: var(--bg-hover); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted);">
                        <tr>
                            <th style="padding: 1rem 1.25rem;">{{ __('Order #') }}</th>
                            <th style="padding: 1rem;">{{ __('Customer') }}</th>
                            <th style="padding: 1rem;">{{ __('Dishes / Items Containing Ingredient') }}</th>
                            <th style="padding: 1rem; text-align: right;">{{ __('Order Total') }}</th>
                            <th style="padding: 1rem; text-align: center;">{{ __('Order Status') }}</th>
                            <th style="padding: 1rem 1.25rem; text-align: right;">{{ __('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($traceResult['customer_orders'] as $order)
                            <tr style="border-top: 1px solid var(--border-color);">
                                <td style="padding: 1rem 1.25rem; font-weight: 700;">
                                    {{ $order->order_number }}
                                </td>
                                <td style="padding: 1rem;">
                                    <div style="font-weight: 600; color: var(--text-main);">{{ $order->customer_name ?: __('Guest') }}</div>
                                    <small style="color: var(--text-muted);">{{ $order->customer_phone ?: '-' }}</small>
                                </td>
                                <td style="padding: 1rem;">
                                    @foreach($order->items as $item)
                                        <span class="badge" style="background: rgba(107, 114, 128, 0.15); color: var(--text-muted); margin-right: 0.25rem;">
                                            {{ $item->product_name }} (x{{ $item->quantity }})
                                        </span>
                                    @endforeach
                                </td>
                                <td style="padding: 1rem; text-align: right; font-weight: 700;">
                                    {{ number_format((float)$order->total_amount, 2) }}
                                </td>
                                <td style="padding: 1rem; text-align: center;">
                                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; text-transform: uppercase;">
                                        {{ $order->status }}
                                    </span>
                                </td>
                                <td style="padding: 1rem 1.25rem; text-align: right; font-size: 0.85rem; color: var(--text-muted);">
                                    {{ $order->created_at->toVendorTimezone()->format('M d, Y H:i') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="padding: 2.5rem; text-align: center; color: var(--text-muted);">
                                    <i class="fa-solid fa-shield-check" style="font-size: 2rem; color: #10b981; margin-bottom: 0.5rem; display: block;"></i>
                                    {{ __('No customer orders have consumed this batch yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    @elseif($batchNumber !== '')
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 3rem; text-align: center;">
            <i class="fa-solid fa-triangle-exclamation" style="font-size: 2.5rem; color: #ef4444; margin-bottom: 1rem; display: block;"></i>
            <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.5rem;">
                {{ __('Batch Not Found') }}
            </h3>
            <p style="color: var(--text-muted);">
                {{ __('No inventory batch record matches batch number') }} <code>{{ $batchNumber }}</code>.
            </p>
        </div>
    @endif
</div>
@endsection

