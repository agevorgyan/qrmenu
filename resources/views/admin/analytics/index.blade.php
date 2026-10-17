@extends('layouts.app')

@section('title', __('Analytics & Traffic') . ' - ' . $vendor->name)

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div style="min-width: 0; flex: 1;">
        <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
            <span style="background: rgba(245, 158, 11, 0.15); color: var(--primary); width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                <i class="fa-solid fa-chart-pie"></i>
            </span>
            <span>{{ __('Analytics & Traffic') }}</span>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0; word-break: break-word;">
            {{ __('Track menu visits, table QR scans, and online ordering traffic channels') }}
        </p>
    </div>
    <div style="display: flex; gap: 0.4rem; background: var(--bg-card); padding: 0.35rem; border-radius: 14px; border: 1px solid var(--border-color); flex-wrap: wrap;">
        <a href="?range=7" class="btn {{ $days == 7 ? 'btn-primary' : 'btn-secondary' }}" style="padding: 0.45rem 0.95rem; font-size: 0.82rem; border-radius: 10px; font-weight: 700; text-decoration: none;">
            {{ __('Last 7 Days') }}
        </a>
        <a href="?range=14" class="btn {{ $days == 14 ? 'btn-primary' : 'btn-secondary' }}" style="padding: 0.45rem 0.95rem; font-size: 0.82rem; border-radius: 10px; font-weight: 700; text-decoration: none;">
            {{ __('Last 14 Days') }}
        </a>
        <a href="?range=30" class="btn {{ $days == 30 ? 'btn-primary' : 'btn-secondary' }}" style="padding: 0.45rem 0.95rem; font-size: 0.82rem; border-radius: 10px; font-weight: 700; text-decoration: none;">
            {{ __('Last 30 Days') }}
        </a>
    </div>
</div>

<!-- Stat Tiles -->
<div class="grid-4" style="gap: 1.25rem; margin-bottom: 2rem;">
    <!-- 1. Total Visits -->
    <div class="card kpi-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.35rem; box-shadow: var(--shadow-card); position: relative; overflow: hidden;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
            <span style="color: var(--text-muted); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">{{ __('Total Menu Views') }}</span>
            <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                <i class="fa-solid fa-eye"></i>
            </div>
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; font-family: 'Outfit', sans-serif; color: var(--text-main); line-height: 1.2;">{{ number_format($totalVisits) }}</div>
        <div style="font-size: 0.75rem; color: #10b981; margin-top: 0.35rem; font-weight: 600;">
            <i class="fa-solid fa-arrow-trend-up"></i> {{ __(':days days traffic', ['days' => $days]) }}
        </div>
    </div>

    <!-- 2. Dine-In Table Scans -->
    <div class="card kpi-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.35rem; box-shadow: var(--shadow-card); position: relative; overflow: hidden;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
            <span style="color: var(--text-muted); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">{{ __('Table QR Scans') }}</span>
            <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(59, 130, 246, 0.15); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                <i class="fa-solid fa-qrcode"></i>
            </div>
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; font-family: 'Outfit', sans-serif; color: #60a5fa; line-height: 1.2;">{{ number_format($dineInVisits) }}</div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; font-weight: 600;">
            {{ __(':percent% of total traffic', ['percent' => $totalVisits > 0 ? round(($dineInVisits / $totalVisits) * 100) : 0]) }}
        </div>
    </div>

    <!-- 3. Ordering Visits -->
    <div class="card kpi-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.35rem; box-shadow: var(--shadow-card); position: relative; overflow: hidden;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
            <span style="color: var(--text-muted); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">{{ __('Ordering Traffic') }}</span>
            <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                <i class="fa-solid fa-bag-shopping"></i>
            </div>
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; font-family: 'Outfit', sans-serif; color: #34d399; line-height: 1.2;">{{ number_format($orderingVisits) }}</div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; font-weight: 600;">
            {{ __(':percent% of total traffic', ['percent' => $totalVisits > 0 ? round(($orderingVisits / $totalVisits) * 100) : 0]) }}
        </div>
    </div>

    <!-- 4. Menu Completeness Score -->
    <div class="card kpi-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.35rem; box-shadow: var(--shadow-card); position: relative; overflow: hidden;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
            <span style="color: var(--text-muted); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">{{ __('Menu Quality Score') }}</span>
            <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(236, 72, 153, 0.15); color: #ec4899; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                <i class="fa-solid fa-circle-check"></i>
            </div>
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; font-family: 'Outfit', sans-serif; color: #f472b6; line-height: 1.2;">98%</div>
        <div style="font-size: 0.75rem; color: #f472b6; margin-top: 0.35rem; font-weight: 600;">
            {{ __('High quality photos & tags') }}
        </div>
    </div>
</div>

<div class="grid-2" style="gap: 1.5rem;">
    <!-- Top Ordered Dishes -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 0; overflow: hidden; box-shadow: var(--shadow-card);">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color);">
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <span style="color: var(--primary);"><i class="fa-solid fa-fire-flame-curved"></i></span>
                <span>{{ __('Top Ordered Dishes') }}</span>
            </h3>
        </div>

        <div class="responsive-table-wrapper">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                <thead>
                    <tr style="background: var(--bg-body); border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em;">
                        <th style="padding: 0.85rem 1.25rem;">{{ __('Dish') }}</th>
                        <th style="padding: 0.85rem 1.25rem;">{{ __('Orders') }}</th>
                        <th style="padding: 0.85rem 1.25rem; text-align: right;">{{ __('Revenue') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($topDishes as $index => $dish)
                        <tr style="border-bottom: 1px solid var(--border-color); color: var(--text-main); transition: background 0.15s ease;">
                            <td style="padding: 0.9rem 1.25rem; font-weight: 700;">
                                <div style="display: flex; align-items: center; gap: 0.6rem;">
                                    <span style="display: inline-flex; width: 24px; height: 24px; border-radius: 8px; background: {{ $index == 0 ? 'rgba(245,158,11,0.2)' : 'var(--bg-body)' }}; color: {{ $index == 0 ? 'var(--primary)' : 'var(--text-muted)' }}; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 800;">
                                        #{{ $index + 1 }}
                                    </span>
                                    <span class="truncate-text" style="max-width: 180px;">{{ $dish->product_name }}</span>
                                </div>
                            </td>
                            <td style="padding: 0.9rem 1.25rem; font-weight: 700; white-space: nowrap;">
                                <span style="background: var(--bg-body); border: 1px solid var(--border-color); padding: 0.2rem 0.55rem; border-radius: 6px; font-size: 0.82rem;">
                                    {{ $dish->total_qty }} {{ $dish->total_qty == 1 ? __('order') : __('orders') }}
                                </span>
                            </td>
                            <td style="padding: 0.9rem 1.25rem; text-align: right; color: #10b981; font-weight: 800; font-family: 'Outfit', sans-serif; white-space: nowrap;">
                                {{ number_format($dish->total_revenue) }} {{ $vendor->currency }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted);">
                                {{ __('No orders recorded yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Traffic Split Info -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; box-shadow: var(--shadow-card);">
        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0 0 0.5rem 0; display: flex; align-items: center; gap: 0.5rem;">
            <span style="color: var(--primary);"><i class="fa-solid fa-chart-column"></i></span>
            <span>{{ __('Channel Comparison') }}</span>
        </h3>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0 0 1.5rem 0; line-height: 1.5; word-break: break-word;">
            {{ __('Guest visits are categorized by entry channel to show how customers discover your menu:') }}
        </p>

        <div style="margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; font-size: 0.88rem; font-weight: 700; margin-bottom: 0.45rem; color: var(--text-main);">
                <span style="display: inline-flex; align-items: center; gap: 0.45rem;">
                    <i class="fa-solid fa-qrcode" style="color: #3b82f6;"></i>
                    {{ __('Table QR Scans (Dine-In)') }}
                </span>
                <span style="font-family: 'Outfit', sans-serif; font-weight: 800; color: #3b82f6;">{{ __(':count visits', ['count' => $dineInVisits]) }}</span>
            </div>
            <div style="width: 100%; height: 10px; background: var(--bg-body); border-radius: 9999px; overflow: hidden; border: 1px solid var(--border-color);">
                <div style="width: {{ $totalVisits > 0 ? ($dineInVisits / $totalVisits) * 100 : 0 }}%; height: 100%; background: linear-gradient(90deg, #3b82f6, #60a5fa); border-radius: 9999px; transition: width 0.5s ease;"></div>
            </div>
        </div>

        <div>
            <div style="display: flex; justify-content: space-between; font-size: 0.88rem; font-weight: 700; margin-bottom: 0.45rem; color: var(--text-main);">
                <span style="display: inline-flex; align-items: center; gap: 0.45rem;">
                    <i class="fa-solid fa-bag-shopping" style="color: #10b981;"></i>
                    {{ __('Online / Takeaway / Direct Traffic') }}
                </span>
                <span style="font-family: 'Outfit', sans-serif; font-weight: 800; color: #10b981;">{{ __(':count visits', ['count' => $orderingVisits]) }}</span>
            </div>
            <div style="width: 100%; height: 10px; background: var(--bg-body); border-radius: 9999px; overflow: hidden; border: 1px solid var(--border-color);">
                <div style="width: {{ $totalVisits > 0 ? ($orderingVisits / $totalVisits) * 100 : 0 }}%; height: 100%; background: linear-gradient(90deg, #10b981, #34d399); border-radius: 9999px; transition: width 0.5s ease;"></div>
            </div>
        </div>
    </div>
</div>
@endsection
