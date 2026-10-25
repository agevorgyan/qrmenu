@extends('layouts.app')

@section('title', __('Tips & Service Ratings Reports') . ' - ' . $vendor->name)

@section('content')
<div style="max-width: 1280px; margin: 0 auto; width: 100%; box-sizing: border-box;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div style="min-width: 0; flex: 1;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.85rem; flex-wrap: wrap;">
                <a href="{{ route('admin.dashboard') }}" style="color: var(--text-muted); text-decoration: none; display: flex; align-items: center; gap: 0.35rem;"><i class="fa-solid fa-gauge-high"></i> {{ __('Dashboard') }}</a>
                <i class="fa-solid fa-chevron-right" style="font-size: 0.65rem; opacity: 0.6;"></i>
                <span style="color: var(--primary); font-weight: 700;">{{ __('Tips & Service Ratings') }}</span>
            </div>

            <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-hand-holding-dollar"></i>
                </span>
                <span>{{ __('Tips & Service Ratings Analytics') }}</span>
            </h1>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0; word-break: break-word;">
                {{ __('Real-time customer gratuities, staff allocation breakdown, and service satisfaction reviews.') }}
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            @can('tips.manage')
            <a href="{{ route('admin.settings.tips_ratings') }}" class="btn btn-secondary" style="display: flex; align-items: center; gap: 0.5rem; text-decoration: none; border-radius: 12px; font-weight: 600; font-size: 0.88rem; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); padding: 0.55rem 1rem;">
                <i class="fa-solid fa-sliders"></i> {{ __('Settings') }}
            </a>
            @endcan

            <!-- CSV Exports Dropdown or Buttons -->
            <a href="{{ route('admin.tips_ratings.export_tips', request()->all()) }}" class="btn btn-secondary" style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.82rem; padding: 0.55rem 0.9rem; border-radius: 10px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #10b981; text-decoration: none; font-weight: 700;">
                <i class="fa-solid fa-file-csv"></i> {{ __('Export Tips') }}
            </a>

            <a href="{{ route('admin.tips_ratings.export_ratings', request()->all()) }}" class="btn btn-secondary" style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.82rem; padding: 0.55rem 0.9rem; border-radius: 10px; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); color: #f59e0b; text-decoration: none; font-weight: 700;">
                <i class="fa-solid fa-file-csv"></i> {{ __('Export Ratings') }}
            </a>
        </div>
    </div>

    <!-- Date Filter & Quick Range Toolbar -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1rem 1.25rem; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; gap: 0.4rem; background: var(--bg-main); padding: 0.3rem; border-radius: 12px; border: 1px solid var(--border-color);">
            <a href="?days=7{{ $selectedWaiter ? '&waiter_id='.$selectedWaiter : '' }}" class="btn {{ $days == 7 ? 'btn-primary' : 'btn-secondary' }}" style="padding: 0.4rem 0.85rem; font-size: 0.82rem; border-radius: 8px; font-weight: 700; text-decoration: none; {{ $days == 7 ? 'background: var(--primary); color: #fff;' : 'color: var(--text-muted);' }}">
                {{ __('7 Days') }}
            </a>
            <a href="?days=14{{ $selectedWaiter ? '&waiter_id='.$selectedWaiter : '' }}" class="btn {{ $days == 14 ? 'btn-primary' : 'btn-secondary' }}" style="padding: 0.4rem 0.85rem; font-size: 0.82rem; border-radius: 8px; font-weight: 700; text-decoration: none; {{ $days == 14 ? 'background: var(--primary); color: #fff;' : 'color: var(--text-muted);' }}">
                {{ __('14 Days') }}
            </a>
            <a href="?days=30{{ $selectedWaiter ? '&waiter_id='.$selectedWaiter : '' }}" class="btn {{ $days == 30 ? 'btn-primary' : 'btn-secondary' }}" style="padding: 0.4rem 0.85rem; font-size: 0.82rem; border-radius: 8px; font-weight: 700; text-decoration: none; {{ $days == 30 ? 'background: var(--primary); color: #fff;' : 'color: var(--text-muted);' }}">
                {{ __('30 Days') }}
            </a>
            <a href="?days=90{{ $selectedWaiter ? '&waiter_id='.$selectedWaiter : '' }}" class="btn {{ $days == 90 ? 'btn-primary' : 'btn-secondary' }}" style="padding: 0.4rem 0.85rem; font-size: 0.82rem; border-radius: 8px; font-weight: 700; text-decoration: none; {{ $days == 90 ? 'background: var(--primary); color: #fff;' : 'color: var(--text-muted);' }}">
                {{ __('90 Days') }}
            </a>
        </div>

        <!-- Custom Date Range & Waiter Filter Form -->
        <form method="GET" action="{{ route('admin.tips_ratings.reports') }}" style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
            @if($waiters->isNotEmpty())
            <select name="waiter_id" onchange="this.form.submit()" style="padding: 0.45rem 0.75rem; background: var(--bg-main); border: 1.5px solid var(--border-color); border-radius: 10px; color: var(--text-main); font-size: 0.82rem; outline: none;">
                <option value="">{{ __('All Staff / Waiters') }}</option>
                @foreach($waiters as $w)
                    <option value="{{ $w->id }}" {{ $selectedWaiter == $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                @endforeach
            </select>
            @endif

            <input type="date" name="start_date" value="{{ request('start_date', $startDate->toDateString()) }}" style="padding: 0.4rem 0.65rem; background: var(--bg-main); border: 1.5px solid var(--border-color); border-radius: 10px; color: var(--text-main); font-size: 0.82rem; outline: none;">
            <span style="color: var(--text-muted); font-size: 0.8rem;">-</span>
            <input type="date" name="end_date" value="{{ request('end_date', $endDate->toDateString()) }}" style="padding: 0.4rem 0.65rem; background: var(--bg-main); border: 1.5px solid var(--border-color); border-radius: 10px; color: var(--text-main); font-size: 0.82rem; outline: none;">
            <button type="submit" class="btn btn-secondary" style="padding: 0.4rem 0.75rem; font-size: 0.82rem; border-radius: 10px; font-weight: 700;">
                <i class="fa-solid fa-filter"></i> {{ __('Filter') }}
            </button>
        </form>
    </div>

    <!-- STAT TILES (KPIs) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
        
        <!-- 1. Total Tips Collected -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.35rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                <span style="color: var(--text-muted); font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">{{ __('Total Tips Collected') }}</span>
                <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                    <i class="fa-solid fa-hand-holding-dollar"></i>
                </div>
            </div>
            <div style="font-size: 1.85rem; font-weight: 800; font-family: 'Outfit', sans-serif; color: #10b981; line-height: 1.2;">
                {{ number_format($tipStats['total_tips_amount'] ?? 0) }} <span style="font-size: 1rem; color: var(--text-muted);">{{ $vendor->currency }}</span>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; font-weight: 600;">
                <i class="fa-solid fa-receipt"></i> {{ __(':count transactions paid', ['count' => $tipStats['total_tips_count'] ?? 0]) }}
            </div>
        </div>

        <!-- 2. Average Tip -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.35rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                <span style="color: var(--text-muted); font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">{{ __('Average Tip') }}</span>
                <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(59, 130, 246, 0.15); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                    <i class="fa-solid fa-calculator"></i>
                </div>
            </div>
            <div style="font-size: 1.85rem; font-weight: 800; font-family: 'Outfit', sans-serif; color: #60a5fa; line-height: 1.2;">
                {{ number_format($tipStats['average_tip_amount'] ?? 0) }} <span style="font-size: 1rem; color: var(--text-muted);">{{ $vendor->currency }}</span>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; font-weight: 600;">
                {{ __('Per eligible tipped order') }}
            </div>
        </div>

        <!-- 3. Tip Attachment Rate -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.35rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                <span style="color: var(--text-muted); font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">{{ __('Tip Attachment Rate') }}</span>
                <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(168, 85, 247, 0.15); color: #a855f7; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                    <i class="fa-solid fa-percent"></i>
                </div>
            </div>
            <div style="font-size: 1.85rem; font-weight: 800; font-family: 'Outfit', sans-serif; color: #c084fc; line-height: 1.2;">
                {{ $tipStats['tip_rate_percent'] ?? 0 }}%
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; font-weight: 600;">
                {{ __('Of eligible paid orders') }}
            </div>
        </div>

        <!-- 4. Average Service Rating -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.35rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                <span style="color: var(--text-muted); font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">{{ __('Average Rating') }}</span>
                <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                    <i class="fa-solid fa-star"></i>
                </div>
            </div>
            <div style="font-size: 1.85rem; font-weight: 800; font-family: 'Outfit', sans-serif; color: #f59e0b; line-height: 1.2; display: flex; align-items: center; gap: 0.35rem;">
                <span>{{ number_format($ratingStats['average_rating'] ?? 0, 1) }}</span>
                <span style="font-size: 1.1rem; color: #f59e0b;"><i class="fa-solid fa-star"></i></span>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; font-weight: 600;">
                {{ __(':count verified ratings', ['count' => $ratingStats['total_ratings'] ?? 0]) }}
            </div>
        </div>
    </div>

    <!-- MAIN REPORT SECTIONS -->
    <div style="display: grid; grid-template-columns: 1fr; gap: 2rem;">
        
        <!-- SECTION 1: BREAKDOWN & STAFF ALLOCATION -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.5rem;">
            
            <!-- Payment Methods & Order Types Breakdown -->
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem;">
                <div style="font-family: 'Outfit'; font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-chart-pie" style="color: var(--primary);"></i>
                    <span>{{ __('Tip Breakdown by Channel & Method') }}</span>
                </div>

                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <!-- Payment Methods -->
                    <div>
                        <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">
                            {{ __('Payment Methods') }}
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 0.4rem;">
                            @forelse($tipStats['by_payment_method'] ?? [] as $method => $data)
                                <div style="display: flex; justify-content: space-between; align-items: center; background: var(--bg-main); padding: 0.6rem 0.85rem; border-radius: 10px; font-size: 0.85rem;">
                                    <span style="font-weight: 600; text-transform: capitalize;">{{ str_replace('_', ' ', $method) }}</span>
                                    <span style="font-weight: 800; color: #10b981;">
                                        {{ number_format($data['amount']) }} {{ $vendor->currency }} 
                                        <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500;">({{ $data['count'] }})</span>
                                    </span>
                                </div>
                            @empty
                                <div style="font-size: 0.82rem; color: var(--text-muted); padding: 0.5rem 0;">{{ __('No tips recorded for this period.') }}</div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Order Types -->
                    <div style="margin-top: 0.5rem;">
                        <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">
                            {{ __('Order Types') }}
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 0.4rem;">
                            @forelse($tipStats['by_order_type'] ?? [] as $type => $data)
                                <div style="display: flex; justify-content: space-between; align-items: center; background: var(--bg-main); padding: 0.6rem 0.85rem; border-radius: 10px; font-size: 0.85rem;">
                                    <span style="font-weight: 600; text-transform: capitalize;">{{ str_replace('_', ' ', $type) }}</span>
                                    <span style="font-weight: 800; color: #60a5fa;">
                                        {{ number_format($data['amount']) }} {{ $vendor->currency }} 
                                        <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500;">({{ $data['count'] }})</span>
                                    </span>
                                </div>
                            @empty
                                <div style="font-size: 0.82rem; color: var(--text-muted); padding: 0.5rem 0;">{{ __('No data available.') }}</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <!-- Waiter / Staff Attribution Report -->
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem;">
                <div style="font-family: 'Outfit'; font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-user-tie" style="color: #f59e0b;"></i>
                        <span>{{ __('Staff Allocation & Performance') }}</span>
                    </div>
                </div>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: left;">
                        <thead>
                            <tr style="border-bottom: 1.5px solid var(--border-color); color: var(--text-muted);">
                                <th style="padding: 0.5rem 0.5rem 0.5rem 0; font-weight: 700;">{{ __('Staff Member') }}</th>
                                <th style="padding: 0.5rem; font-weight: 700; text-align: center;">{{ __('Tips Count') }}</th>
                                <th style="padding: 0.5rem 0 0.5rem 0.5rem; font-weight: 700; text-align: right;">{{ __('Tips Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tipStats['by_waiter'] ?? [] as $wid => $wdata)
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 0.65rem 0.5rem 0.65rem 0; font-weight: 700; color: var(--text-main);">
                                        {{ $wdata['name'] }}
                                    </td>
                                    <td style="padding: 0.65rem; text-align: center; color: var(--text-muted);">
                                        {{ $wdata['count'] }}
                                    </td>
                                    <td style="padding: 0.65rem 0 0.65rem 0.5rem; text-align: right; font-weight: 800; color: #10b981;">
                                        {{ number_format($wdata['amount']) }} {{ $vendor->currency }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" style="padding: 1.5rem 0; text-align: center; color: var(--text-muted);">
                                        {{ __('No staff allocation records found.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Rating Distribution & Feedback Categories -->
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem;">
                <div style="font-family: 'Outfit'; font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-star-half-stroke" style="color: #f59e0b;"></i>
                    <span>{{ __('Rating Distribution') }}</span>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.6rem;">
                    @for($s = 5; $s >= 1; $s--)
                        @php
                            $c = $ratingStats['distribution'][$s] ?? 0;
                            $pct = ($ratingStats['total_ratings'] ?? 0) > 0 ? round(($c / $ratingStats['total_ratings']) * 100) : 0;
                        @endphp
                        <div style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.82rem;">
                            <span style="width: 48px; font-weight: 700; display: flex; align-items: center; gap: 0.25rem;">
                                <span>{{ $s }}</span> <i class="fa-solid fa-star" style="color: #f59e0b; font-size: 0.75rem;"></i>
                            </span>
                            <div style="flex: 1; height: 8px; background: var(--bg-main); border-radius: 4px; overflow: hidden;">
                                <div style="height: 100%; background: #f59e0b; width: {{ $pct }}%;"></div>
                            </div>
                            <span style="width: 45px; text-align: right; color: var(--text-muted); font-weight: 600;">
                                {{ $c }} ({{ $pct }}%)
                            </span>
                        </div>
                    @endfor
                </div>

                @if(!empty($ratingStats['categories']))
                <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px dashed var(--border-color);">
                    <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">
                        {{ __('Top Positive Tags') }}
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
                        @foreach($ratingStats['categories'] as $cat => $count)
                            <span style="background: rgba(245, 158, 11, 0.12); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.25); padding: 0.25rem 0.6rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700;">
                                {{ ucfirst(str_replace('_', ' ', $cat)) }} ({{ $count }})
                            </span>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- SECTION 2: VERIFIED CUSTOMER FEEDBACK & MODERATION STREAM -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.5rem;">
                <div style="font-family: 'Outfit'; font-size: 1.15rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-comments" style="color: var(--primary);"></i>
                    <span>{{ __('Customer Feedback & Reviews Stream') }}</span>
                </div>
                <span style="font-size: 0.8rem; color: var(--text-muted);">
                    {{ __('Showing latest customer responses') }}
                </span>
            </div>

            <div style="display: flex; flex-direction: column; gap: 1rem;">
                @forelse($ratings as $rating)
                    <div style="background: var(--bg-main); border: 1.5px solid var(--border-color); border-radius: 16px; padding: 1.15rem;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.65rem;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                    <!-- Star rating -->
                                    <div style="color: #f59e0b; font-size: 0.95rem;">
                                        @for($i = 1; $i <= $rating->rating; $i++)
                                            <i class="fa-solid fa-star"></i>
                                        @endfor
                                    </div>
                                    <span style="font-weight: 800; font-size: 0.9rem; color: var(--text-main);">
                                        {{ $rating->is_anonymous ? __('Anonymous Customer') : ($rating->customer_name ?: __('Customer')) }}
                                    </span>
                                    @if($rating->order_id)
                                        <span style="font-size: 0.75rem; background: rgba(59, 130, 246, 0.12); color: var(--primary); padding: 0.15rem 0.5rem; border-radius: 6px; font-weight: 700;">
                                            #{{ $rating->order?->order_number ?? 'Order' }}
                                        </span>
                                    @endif
                                    @if($rating->table_number)
                                        <span style="font-size: 0.75rem; background: rgba(245, 158, 11, 0.12); color: #f59e0b; padding: 0.15rem 0.5rem; border-radius: 6px; font-weight: 700;">
                                            {{ __('Table') }} {{ $rating->table_number }}
                                        </span>
                                    @endif
                                </div>

                                @if($rating->waiter)
                                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.25rem;">
                                        <i class="fa-solid fa-user-tie"></i> {{ __('Server:') }} <strong style="color: var(--text-main);">{{ $rating->waiter->name }}</strong>
                                    </div>
                                @endif
                            </div>

                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span style="font-size: 0.75rem; color: var(--text-muted);">
                                    {{ $rating->created_at->format('M d, Y H:i') }}
                                </span>
                                <!-- Moderation Status Badge -->
                                <span style="font-size: 0.72rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 6px; text-transform: uppercase; {{ $rating->moderation_status === 'published' ? 'background: rgba(16, 185, 129, 0.15); color: #10b981;' : ($rating->moderation_status === 'hidden' ? 'background: rgba(239, 68, 68, 0.15); color: #ef4444;' : 'background: rgba(245, 158, 11, 0.15); color: #f59e0b;') }}">
                                    {{ $rating->moderation_status }}
                                </span>
                            </div>
                        </div>

                        <!-- Written Comment (if any) -->
                        @if($rating->comment)
                            <p style="font-size: 0.88rem; color: var(--text-main); margin: 0.5rem 0; line-height: 1.4; background: var(--bg-card); padding: 0.75rem; border-radius: 10px; border: 1px solid var(--border-color);">
                                "{{ e($rating->comment) }}"
                            </p>
                        @endif

                        <!-- Category Tags -->
                        @if(!empty($rating->categories))
                            <div style="display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.5rem;">
                                @foreach($rating->categories as $c)
                                    <span style="font-size: 0.72rem; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-muted); padding: 0.15rem 0.5rem; border-radius: 6px;">
                                        {{ ucfirst(str_replace('_', ' ', $c)) }}
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        <!-- Moderation Action Buttons -->
                        @can('ratings.manage')
                        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.75rem; padding-top: 0.65rem; border-top: 1px dashed var(--border-color);">
                            @if($rating->moderation_status !== 'published')
                                <form action="{{ route('admin.tips_ratings.moderate', $rating) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="status" value="published">
                                    <button type="submit" class="btn btn-secondary" style="padding: 0.25rem 0.65rem; font-size: 0.75rem; border-radius: 6px; color: #10b981;">
                                        <i class="fa-solid fa-check"></i> {{ __('Publish') }}
                                    </button>
                                </form>
                            @endif
                            @if($rating->moderation_status !== 'hidden')
                                <form action="{{ route('admin.tips_ratings.moderate', $rating) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="status" value="hidden">
                                    <button type="submit" class="btn btn-secondary" style="padding: 0.25rem 0.65rem; font-size: 0.75rem; border-radius: 6px; color: #ef4444;">
                                        <i class="fa-solid fa-eye-slash"></i> {{ __('Hide') }}
                                    </button>
                                </form>
                            @endif
                            @if($rating->moderation_status !== 'flagged')
                                <form action="{{ route('admin.tips_ratings.moderate', $rating) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="status" value="flagged">
                                    <button type="submit" class="btn btn-secondary" style="padding: 0.25rem 0.65rem; font-size: 0.75rem; border-radius: 6px; color: #f59e0b;">
                                        <i class="fa-solid fa-flag"></i> {{ __('Flag') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                        @endcan
                    </div>
                @empty
                    <div style="text-align: center; padding: 2.5rem; color: var(--text-muted); font-size: 0.9rem;">
                        <i class="fa-solid fa-comment-slash" style="font-size: 2rem; margin-bottom: 0.75rem; display: block; opacity: 0.4;"></i>
                        {{ __('No customer ratings or reviews found for the selected period.') }}
                    </div>
                @endforelse

                <!-- Pagination Links -->
                <div style="margin-top: 1rem;">
                    {{ $ratings->links() }}
                </div>
            </div>
        </div>

        <!-- SECTION 3: RECENT TIPS LEDGER -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <div style="font-family: 'Outfit'; font-size: 1.15rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-receipt" style="color: #10b981;"></i>
                    <span>{{ __('Tips Financial Transactions Ledger') }}</span>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 1.5px solid var(--border-color); color: var(--text-muted);">
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700;">{{ __('Date') }}</th>
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700;">{{ __('Order #') }}</th>
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700;">{{ __('Payment Method') }}</th>
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700;">{{ __('Staff Recipient') }}</th>
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700; text-align: center;">{{ __('Status') }}</th>
                            <th style="padding: 0.65rem 0.5rem; font-weight: 700; text-align: right;">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tips as $t)
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 0.65rem 0.5rem; color: var(--text-muted); font-size: 0.8rem;">
                                    {{ $t->created_at->format('M d, H:i') }}
                                </td>
                                <td style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-main);">
                                    {{ $t->order?->order_number ?? ($t->table_number ? __('Table ').$t->table_number : 'Direct') }}
                                </td>
                                <td style="padding: 0.65rem 0.5rem; text-transform: capitalize;">
                                    {{ str_replace('_', ' ', $t->payment_method) }}
                                </td>
                                <td style="padding: 0.65rem 0.5rem;">
                                    {{ $t->waiter?->name ?? __('Staff Pool') }}
                                </td>
                                <td style="padding: 0.65rem 0.5rem; text-align: center;">
                                    <span style="font-size: 0.72rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 6px; text-transform: uppercase; {{ $t->payment_status === 'paid' ? 'background: rgba(16, 185, 129, 0.15); color: #10b981;' : ($t->payment_status === 'refunded' ? 'background: rgba(239, 68, 68, 0.15); color: #ef4444;' : 'background: rgba(245, 158, 11, 0.15); color: #f59e0b;') }}">
                                        {{ $t->payment_status }}
                                    </span>
                                </td>
                                <td style="padding: 0.65rem 0.5rem; text-align: right; font-weight: 800; color: {{ $t->payment_status === 'refunded' ? '#ef4444' : '#10b981' }}; font-family: 'Outfit';">
                                    {{ $t->payment_status === 'refunded' ? '-' : '+' }}{{ number_format($t->amount) }} {{ $t->currency }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="padding: 2rem; text-align: center; color: var(--text-muted);">
                                    {{ __('No tips recorded.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination Links -->
            <div style="margin-top: 1rem;">
                {{ $tips->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
