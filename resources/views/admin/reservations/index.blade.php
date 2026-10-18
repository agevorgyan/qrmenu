@extends('layouts.app')

@section('title', __('Table Reservations') . ' — ' . $vendor->name)

@section('content')
<div style="max-width: 1400px; margin: 0 auto; width: 100%; box-sizing: border-box;" x-data="adminReservationsApp()">
    <!-- Top Action Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.65rem;">
                <span style="background: rgba(236, 72, 153, 0.12); color: #ec4899; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-calendar-check"></i>
                </span>
                <span>{{ __('Table Reservations') }}</span>
            </h1>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0;">
                {{ __('Manage customer table bookings, seatings, pre-orders, and table availability in real time.') }}
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('admin.tables.index') }}" class="btn btn-secondary">
                <i class="fa-solid fa-chair" style="color: #06b6d4;"></i> {{ __('Manage Tables') }}
            </a>
            <a href="{{ route('admin.settings.reservations') }}" class="btn btn-secondary">
                <i class="fa-solid fa-sliders" style="color: #8b5cf6;"></i> {{ __('Booking Rules') }}
            </a>
            <a href="{{ route('admin.settings.reservations.notifications') }}" class="btn btn-secondary">
                <i class="fa-solid fa-envelope" style="color: #f59e0b;"></i> {{ __('Email Notifications') }}
            </a>
        </div>
    </div>

    <!-- Metrics Row -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <!-- Metric 1: Bookings in Selected Period -->
        <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; margin-bottom: 0;">
            <div style="width: 50px; height: 50px; border-radius: 14px; background: rgba(59, 130, 246, 0.12); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0;">
                <i class="fa-solid fa-calendar-week"></i>
            </div>
            <div style="min-width: 0;">
                <div style="font-size: 1.6rem; font-weight: 800; color: var(--text-main); line-height: 1.2;">{{ $metrics['period_bookings'] }}</div>
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; margin-top: 0.15rem;">
                    {{ __('Bookings in Period') }}
                </div>
            </div>
        </div>

        <!-- Metric 2: Today's Bookings -->
        <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; margin-bottom: 0;">
            <div style="width: 50px; height: 50px; border-radius: 14px; background: rgba(16, 185, 129, 0.12); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0;">
                <i class="fa-solid fa-calendar-day"></i>
            </div>
            <div style="min-width: 0;">
                <div style="font-size: 1.6rem; font-weight: 800; color: #10b981; line-height: 1.2;">{{ $metrics['total_today'] }}</div>
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; margin-top: 0.15rem;">
                    {{ __('Today\'s Bookings') }}
                </div>
            </div>
        </div>

        <!-- Metric 3: Pending Requests -->
        <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; margin-bottom: 0;">
            <div style="width: 50px; height: 50px; border-radius: 14px; background: rgba(245, 158, 11, 0.12); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0;">
                <i class="fa-solid fa-clock"></i>
            </div>
            <div style="min-width: 0;">
                <div style="font-size: 1.6rem; font-weight: 800; color: #f59e0b; line-height: 1.2;">{{ $metrics['pending'] }}</div>
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; margin-top: 0.15rem;">
                    {{ __('Pending Requests') }}
                </div>
            </div>
        </div>

        <!-- Metric 4: Attached Pre-Orders -->
        <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; margin-bottom: 0;">
            <div style="width: 50px; height: 50px; border-radius: 14px; background: rgba(99, 102, 241, 0.12); color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0;">
                <i class="fa-solid fa-utensils"></i>
            </div>
            <div style="min-width: 0;">
                <div style="font-size: 1.6rem; font-weight: 800; color: #6366f1; line-height: 1.2;">{{ $metrics['preorders_count'] }}</div>
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; margin-top: 0.15rem;">
                    {{ __('Attached Pre-Orders') }}
                </div>
            </div>
        </div>
    </div>

    <!-- Filters & Date Range Toolbar -->
    <div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
        <form method="GET" action="{{ route('admin.reservations.index') }}" id="reservationsFilterForm">
            <!-- Preset Period Quick Switcher -->
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; margin-bottom: 1.15rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
                <span style="font-size: 0.82rem; font-weight: 700; color: var(--text-muted); display: inline-flex; align-items: center; gap: 0.35rem; margin-right: 0.25rem;">
                    <i class="fa-solid fa-calendar-days" style="color: var(--primary);"></i> {{ __('Period:') }}
                </span>

                <a href="{{ route('admin.reservations.index', array_merge(request()->except(['date_preset', 'date_from', 'date_to', 'date', 'page']), ['date_preset' => 'upcoming_30'])) }}"
                   class="btn {{ $datePreset === 'upcoming_30' ? 'btn-primary' : 'btn-secondary' }}"
                   style="padding: 0.35rem 0.85rem; font-size: 0.82rem; border-radius: 9999px;">
                    ✨ {{ __('Upcoming 30 Days') }}
                </a>

                <a href="{{ route('admin.reservations.index', array_merge(request()->except(['date_preset', 'date_from', 'date_to', 'date', 'page']), ['date_preset' => 'today'])) }}"
                   class="btn {{ $datePreset === 'today' ? 'btn-primary' : 'btn-secondary' }}"
                   style="padding: 0.35rem 0.85rem; font-size: 0.82rem; border-radius: 9999px;">
                    {{ __('Today') }}
                </a>

                <a href="{{ route('admin.reservations.index', array_merge(request()->except(['date_preset', 'date_from', 'date_to', 'date', 'page']), ['date_preset' => 'tomorrow'])) }}"
                   class="btn {{ $datePreset === 'tomorrow' ? 'btn-primary' : 'btn-secondary' }}"
                   style="padding: 0.35rem 0.85rem; font-size: 0.82rem; border-radius: 9999px;">
                    {{ __('Tomorrow') }}
                </a>

                <a href="{{ route('admin.reservations.index', array_merge(request()->except(['date_preset', 'date_from', 'date_to', 'date', 'page']), ['date_preset' => 'next_7_days'])) }}"
                   class="btn {{ $datePreset === 'next_7_days' ? 'btn-primary' : 'btn-secondary' }}"
                   style="padding: 0.35rem 0.85rem; font-size: 0.82rem; border-radius: 9999px;">
                    {{ __('Next 7 Days') }}
                </a>

                <a href="{{ route('admin.reservations.index', array_merge(request()->except(['date_preset', 'date_from', 'date_to', 'date', 'page']), ['date_preset' => 'this_month'])) }}"
                   class="btn {{ $datePreset === 'this_month' ? 'btn-primary' : 'btn-secondary' }}"
                   style="padding: 0.35rem 0.85rem; font-size: 0.82rem; border-radius: 9999px;">
                    {{ __('This Month') }}
                </a>

                <a href="{{ route('admin.reservations.index', array_merge(request()->except(['date_preset', 'date_from', 'date_to', 'date', 'page']), ['date_preset' => 'all'])) }}"
                   class="btn {{ $datePreset === 'all' ? 'btn-primary' : 'btn-secondary' }}"
                   style="padding: 0.35rem 0.85rem; font-size: 0.82rem; border-radius: 9999px;">
                    {{ __('All (Upcoming & Past)') }}
                </a>
            </div>

            <!-- Detailed Date Range, Status & Search Fields -->
            <input type="hidden" name="date_preset" value="{{ $datePreset }}" id="datePresetInput">

            <div style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; justify-content: space-between;">
                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: flex-end;">
                    <div style="min-width: 140px;">
                        <label class="form-label">{{ __('From Date') }}</label>
                        <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control" style="font-size: 0.88rem; padding: 0.45rem 0.75rem;" onchange="document.getElementById('datePresetInput').value='custom';">
                    </div>

                    <div style="min-width: 140px;">
                        <label class="form-label">{{ __('To Date') }}</label>
                        <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control" style="font-size: 0.88rem; padding: 0.45rem 0.75rem;" onchange="document.getElementById('datePresetInput').value='custom';">
                    </div>

                    <div style="min-width: 160px;">
                        <label class="form-label">{{ __('Status') }}</label>
                        <select name="status" class="form-control" style="font-size: 0.88rem; padding: 0.45rem 0.75rem;" onchange="this.form.submit()">
                            <option value="all">{{ __('All Statuses') }}</option>
                            <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>⏳ {{ __('Pending') }}</option>
                            <option value="confirmed" {{ $status === 'confirmed' ? 'selected' : '' }}>✅ {{ __('Confirmed') }}</option>
                            <option value="seated" {{ $status === 'seated' ? 'selected' : '' }}>🍽️ {{ __('Seated') }}</option>
                            <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>🏁 {{ __('Completed') }}</option>
                            <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>❌ {{ __('Cancelled') }}</option>
                            <option value="no_show" {{ $status === 'no_show' ? 'selected' : '' }}>⚠️ {{ __('No-Show') }}</option>
                        </select>
                    </div>

                    @if($locations->count() > 1)
                    <div style="min-width: 170px;">
                        <label class="form-label">{{ __('Location') }}</label>
                        <select name="location_id" class="form-control" style="font-size: 0.88rem; padding: 0.45rem 0.75rem;" onchange="this.form.submit()">
                            <option value="">{{ __('All Locations') }}</option>
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}" {{ (string)$locationId === (string)$loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                </div>

                <div style="display: flex; gap: 0.5rem; align-items: flex-end; flex-wrap: wrap;">
                    <div style="min-width: 210px;">
                        <label class="form-label">{{ __('Search') }}</label>
                        <div style="position: relative;">
                            <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('Ref, customer, phone...') }}" class="form-control" style="font-size: 0.88rem; padding: 0.45rem 0.75rem 0.45rem 2rem;">
                            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.8rem;"></i>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="padding: 0.55rem 1.15rem; font-size: 0.88rem;">
                        <i class="fa-solid fa-filter"></i> {{ __('Filter') }}
                    </button>
                    @if($datePreset !== 'upcoming_30' || $status || $locationId || $search)
                        <a href="{{ route('admin.reservations.index') }}" class="btn btn-secondary" style="padding: 0.55rem 0.9rem; font-size: 0.88rem;" title="{{ __('Reset to default upcoming 30 days') }}">
                            <i class="fa-solid fa-rotate-left"></i> {{ __('Reset') }}
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    <!-- Active Period Indicator -->
    @if($dateFrom && $dateTo)
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; padding: 0.65rem 1rem; border-radius: 12px; background: rgba(59, 130, 246, 0.06); border: 1px solid rgba(59, 130, 246, 0.2); font-size: 0.85rem; color: var(--text-main);">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-calendar-check" style="color: #3b82f6;"></i>
                <span>
                    <strong>{{ __('Period:') }}</strong>
                    {{ \Carbon\Carbon::parse($dateFrom)->translatedFormat('d M Y') }} — {{ \Carbon\Carbon::parse($dateTo)->translatedFormat('d M Y') }}
                    <span style="color: var(--text-muted); margin-left: 0.35rem;">
                        @if($datePreset === 'upcoming_30')
                            ({{ __('Upcoming 30 Days') }})
                        @elseif($datePreset === 'today')
                            ({{ __('Today') }})
                        @elseif($datePreset === 'tomorrow')
                            ({{ __('Tomorrow') }})
                        @elseif($datePreset === 'next_7_days')
                            ({{ __('Next 7 Days') }})
                        @elseif($datePreset === 'this_month')
                            ({{ __('This Month') }})
                        @endif
                    </span>
                </span>
            </div>
            <div style="font-weight: 700; color: var(--text-muted);">
                {{ $reservations->total() }} {{ __('results') }}
            </div>
        </div>
    @endif

    <!-- Reservations Table -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; border-collapse: collapse; margin-bottom: 0;">
                <thead>
                    <tr style="background: rgba(0,0,0,0.02); border-bottom: 1px solid var(--border-color); text-align: left; font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">
                        <th style="padding: 1rem 1.25rem;">{{ __('Reference & Status') }}</th>
                        <th style="padding: 1rem 1.25rem;">{{ __('Customer') }}</th>
                        <th style="padding: 1rem 1.25rem;">{{ __('Date & Time') }}</th>
                        <th style="padding: 1rem 1.25rem;">{{ __('Party & Table') }}</th>
                        <th style="padding: 1rem 1.25rem;">{{ __('Pre-Order') }}</th>
                        <th style="padding: 1rem 1.25rem; text-align: right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reservations as $res)
                    @php
                        $isToday = $res->reservation_date === $todayStr;
                        $isTomorrow = $res->reservation_date === \Carbon\Carbon::tomorrow($vendor->timezone ?: 'Asia/Yerevan')->format('Y-m-d');
                    @endphp
                    <tr style="border-bottom: 1px solid var(--border-color); font-size: 0.9rem; transition: background 0.15s; {{ $isToday ? 'background: rgba(16, 185, 129, 0.03);' : '' }}">
                        <!-- Reference & Status -->
                        <td style="padding: 1rem 1.25rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <a href="javascript:void(0)" @click="inspectReservation({{ $res->id }})" style="font-weight: 800; font-size: 0.95rem; color: var(--text-main); text-decoration: none; border-bottom: 1px dashed var(--border-color);">
                                    {{ $res->reference }}
                                </a>
                            </div>
                            <div style="margin-top: 0.4rem;">
                                @if($res->status === 'confirmed')
                                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); font-size: 0.72rem; padding: 0.2rem 0.55rem; border-radius: 9999px; font-weight: 700;">
                                        ✅ {{ __('Confirmed') }}
                                    </span>
                                @elseif($res->status === 'pending')
                                    <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); font-size: 0.72rem; padding: 0.2rem 0.55rem; border-radius: 9999px; font-weight: 700;">
                                        ⏳ {{ __('Pending') }}
                                    </span>
                                @elseif($res->status === 'seated')
                                    <span class="badge" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); font-size: 0.72rem; padding: 0.2rem 0.55rem; border-radius: 9999px; font-weight: 700;">
                                        🍽️ {{ __('Seated') }}
                                    </span>
                                @elseif($res->status === 'completed')
                                    <span class="badge" style="background: rgba(100, 116, 139, 0.15); color: #64748b; border: 1px solid rgba(100, 116, 139, 0.3); font-size: 0.72rem; padding: 0.2rem 0.55rem; border-radius: 9999px; font-weight: 700;">
                                        🏁 {{ __('Completed') }}
                                    </span>
                                @elseif($res->status === 'cancelled')
                                    <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); font-size: 0.72rem; padding: 0.2rem 0.55rem; border-radius: 9999px; font-weight: 700;">
                                        ❌ {{ __('Cancelled') }}
                                    </span>
                                @else
                                    <span class="badge" style="background: rgba(100, 116, 139, 0.15); color: #64748b; font-size: 0.72rem; padding: 0.2rem 0.55rem; border-radius: 9999px; font-weight: 700;">
                                        {{ ucfirst($res->status) }}
                                    </span>
                                @endif
                            </div>
                        </td>

                        <!-- Customer Details -->
                        <td style="padding: 1rem 1.25rem;">
                            <div style="font-weight: 700; color: var(--text-main);">{{ $res->customer_name }}</div>
                            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem; display: flex; flex-direction: column; gap: 0.15rem;">
                                <a href="mailto:{{ $res->customer_email }}" style="color: inherit; text-decoration: none;">
                                    <i class="fa-solid fa-envelope" style="width: 14px; opacity: 0.7;"></i> {{ $res->customer_email }}
                                </a>
                                @if($res->customer_phone)
                                    <a href="tel:{{ $res->customer_phone }}" style="color: inherit; text-decoration: none;">
                                        <i class="fa-solid fa-phone" style="width: 14px; opacity: 0.7;"></i> {{ $res->customer_phone }}
                                    </a>
                                @endif
                            </div>
                        </td>

                        <!-- Date & Time -->
                        <td style="padding: 1rem 1.25rem;">
                            <div style="display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
                                <span style="font-weight: 800; color: var(--text-main);">
                                    {{ \Carbon\Carbon::parse($res->reservation_date)->translatedFormat('D, d M Y') }}
                                </span>
                                @if($isToday)
                                    <span style="background: #10b981; color: #fff; font-size: 0.65rem; font-weight: 800; padding: 0.15rem 0.45rem; border-radius: 6px; text-transform: uppercase;">
                                        {{ __('Today') }}
                                    </span>
                                @elseif($isTomorrow)
                                    <span style="background: #3b82f6; color: #fff; font-size: 0.65rem; font-weight: 800; padding: 0.15rem 0.45rem; border-radius: 6px; text-transform: uppercase;">
                                        {{ __('Tomorrow') }}
                                    </span>
                                @endif
                            </div>
                            <div style="font-size: 0.85rem; color: var(--primary); font-weight: 800; margin-top: 0.25rem;">
                                <i class="fa-regular fa-clock"></i> {{ $res->getFormattedTime() }}
                                <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500;">
                                    ({{ $res->duration_minutes ?: 90 }} {{ __('min') }})
                                </span>
                            </div>
                        </td>

                        <!-- Party & Table -->
                        <td style="padding: 1rem 1.25rem;">
                            <div style="display: flex; align-items: center; gap: 0.4rem; font-weight: 700; color: var(--text-main);">
                                <i class="fa-solid fa-users" style="color: var(--text-muted); font-size: 0.85rem;"></i>
                                <span>{{ $res->guest_count }} {{ __('guests') }}</span>
                            </div>
                            <div style="font-size: 0.82rem; margin-top: 0.25rem; color: var(--text-muted);">
                                <strong>{{ __('Table') }}:</strong>
                                <span style="color: var(--text-main); font-weight: 600;">
                                    #{{ $res->table_number }} {{ $res->table_name ? '(' . $res->table_name . ')' : '' }}
                                </span>
                                @if($res->restaurantTable && $res->restaurantTable->section)
                                    <span class="badge" style="background: rgba(100, 116, 139, 0.1); color: var(--text-muted); font-size: 0.68rem; padding: 0.1rem 0.4rem; border-radius: 4px; margin-left: 0.25rem;">
                                        {{ $res->restaurantTable->section }}
                                    </span>
                                @endif
                            </div>
                        </td>

                        <!-- Pre-Order Summary -->
                        <td style="padding: 1rem 1.25rem;">
                            @if($res->preOrder && !empty($res->preOrder->items))
                                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                    <span class="badge" style="background: rgba(16, 185, 129, 0.12); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.25); font-size: 0.75rem; padding: 0.15rem 0.5rem; border-radius: 6px; font-weight: 700;">
                                        {{ count($res->preOrder->items) }} {{ __('items') }}
                                    </span>
                                    <span style="font-weight: 800; font-size: 0.88rem; color: var(--text-main);">
                                        {{ number_format((float)$res->preOrder->total_amount, 0, '.', ',') }} {{ $res->preOrder->currency }}
                                    </span>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.3rem; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    {{ $res->preOrder->getFormattedSummary(app()->getLocale()) }}
                                </div>
                            @else
                                <span style="font-size: 0.8rem; color: var(--text-muted);">{{ __('No pre-order') }}</span>
                            @endif
                        </td>

                        <!-- Actions -->
                        <td style="padding: 1rem 1.25rem; text-align: right;">
                            <div style="display: inline-flex; gap: 0.4rem; align-items: center; justify-content: flex-end;">
                                @if($res->status === 'pending')
                                    <form method="POST" action="{{ route('admin.reservations.status', $res->id) }}" style="display: inline;">
                                        @csrf
                                        <input type="hidden" name="status" value="confirmed">
                                        <button type="submit" class="btn btn-primary" style="padding: 0.4rem 0.75rem; font-size: 0.8rem;" title="{{ __('Confirm') }}">
                                            <i class="fa-solid fa-check"></i> {{ __('Confirm') }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.reservations.status', $res->id) }}" style="display: inline;" onsubmit="return confirm('{{ __('Reject this reservation request?') }}')">
                                        @csrf
                                        <input type="hidden" name="status" value="cancelled">
                                        <button type="submit" class="btn btn-secondary" style="padding: 0.4rem 0.65rem; font-size: 0.8rem; color: #ef4444;" title="{{ __('Reject') }}">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </form>
                                @elseif($res->status === 'confirmed')
                                    <form method="POST" action="{{ route('admin.reservations.status', $res->id) }}" style="display: inline;">
                                        @csrf
                                        <input type="hidden" name="status" value="seated">
                                        <button type="submit" class="btn btn-secondary" style="padding: 0.4rem 0.75rem; font-size: 0.8rem; color: #3b82f6; border-color: rgba(59, 130, 246, 0.4);" title="{{ __('Seat') }}">
                                            <i class="fa-solid fa-chair"></i> {{ __('Seat') }}
                                        </button>
                                    </form>
                                @elseif($res->status === 'seated')
                                    <form method="POST" action="{{ route('admin.reservations.status', $res->id) }}" style="display: inline;">
                                        @csrf
                                        <input type="hidden" name="status" value="completed">
                                        <button type="submit" class="btn btn-secondary" style="padding: 0.4rem 0.75rem; font-size: 0.8rem; color: #10b981;" title="{{ __('Done') }}">
                                            <i class="fa-solid fa-flag-checkered"></i> {{ __('Done') }}
                                        </button>
                                    </form>
                                @endif

                                <button type="button" class="btn btn-secondary" style="padding: 0.4rem 0.65rem; font-size: 0.8rem;" @click="inspectReservation({{ $res->id }})" title="{{ __('View Details') }}">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" style="padding: 3.5rem 1rem; text-align: center; color: var(--text-muted);">
                            <div style="width: 60px; height: 60px; border-radius: 18px; background: rgba(100, 116, 139, 0.1); color: var(--text-muted); display: inline-flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 1rem;">
                                <i class="fa-solid fa-calendar-xmark"></i>
                            </div>
                            <p style="font-size: 1.05rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem;">
                                {{ __('No reservations found for the selected criteria.') }}
                            </p>
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                                {{ __('Try choosing a different date range or clearing your status and location filters.') }}
                            </p>
                            <a href="{{ route('admin.reservations.index') }}" class="btn btn-primary" style="font-size: 0.85rem; padding: 0.5rem 1.2rem;">
                                <i class="fa-solid fa-rotate-left"></i> {{ __('Upcoming 30 Days') }}
                            </a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($reservations->hasPages())
        <div style="padding: 1.25rem; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div style="font-size: 0.85rem; color: var(--text-muted);">
                {{ __('Showing') }} {{ $reservations->firstItem() }} {{ __('of') }} {{ $reservations->total() }} {{ __('results') }}
            </div>
            <div>
                {{ $reservations->links() }}
            </div>
        </div>
        @endif
    </div>

    <!-- Live Inspection Drawer (Sliding Modal) -->
    <div x-show="showDrawer" class="modal-backdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 1050; display: flex; justify-content: flex-end;" x-cloak>
        <div class="card" style="width: 100%; max-width: 520px; height: 100vh; border-radius: 0; overflow-y: auto; padding: 1.5rem; margin: 0; box-shadow: -5px 0 25px rgba(0,0,0,0.25);" @click.outside="showDrawer = false">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">
                <h3 style="font-size: 1.2rem; font-weight: 800; color: var(--text-main);" x-text="activeRes ? activeRes.reference : '{{ __('Reservation') }}'"></h3>
                <button type="button" class="btn btn-secondary" style="padding: 0.4rem 0.6rem;" @click="showDrawer = false">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <template x-if="isLoadingDrawer">
                <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                    <i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>
                    <p style="margin-top: 0.5rem;">{{ __('Loading details...') }}</p>
                </div>
            </template>

            <template x-if="!isLoadingDrawer && activeRes">
                <div>
                    <!-- Status & Quick Transitions -->
                    <div style="margin-bottom: 1.25rem; padding: 1rem; border-radius: 12px; background: rgba(0,0,0,0.02); border: 1px solid var(--border-color);">
                        <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">{{ __('CURRENT STATUS & TRANSITION') }}</div>
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <button type="button" class="btn btn-secondary" :class="{ 'btn-primary': activeRes.status === 'confirmed' }" @click="updateStatusApi('confirmed')">
                                <i class="fa-solid fa-check"></i> {{ __('Confirmed') }}
                            </button>
                            <button type="button" class="btn btn-secondary" :class="{ 'btn-primary': activeRes.status === 'seated' }" @click="updateStatusApi('seated')">
                                <i class="fa-solid fa-chair"></i> {{ __('Seated') }}
                            </button>
                            <button type="button" class="btn btn-secondary" :class="{ 'btn-primary': activeRes.status === 'completed' }" @click="updateStatusApi('completed')">
                                <i class="fa-solid fa-flag-checkered"></i> {{ __('Completed') }}
                            </button>
                            <button type="button" class="btn btn-secondary" style="color: #ef4444;" @click="updateStatusApi('cancelled')">
                                <i class="fa-solid fa-xmark"></i> {{ __('Cancel') }}
                            </button>
                            <button type="button" class="btn btn-secondary" style="color: #f59e0b;" @click="updateStatusApi('no_show')">
                                ⚠️ {{ __('No-Show') }}
                            </button>
                        </div>
                    </div>

                    <!-- Customer Info -->
                    <div style="margin-bottom: 1.25rem;">
                        <h4 style="font-size: 0.95rem; font-weight: 800; margin-bottom: 0.5rem;">{{ __('Guest Information') }}</h4>
                        <div style="padding: 0.75rem; border-radius: 8px; background: rgba(0,0,0,0.02);">
                            <div style="font-weight: 700;" x-text="activeRes.customer_name"></div>
                            <div style="font-size: 0.85rem; color: var(--text-muted);" x-text="activeRes.customer_email"></div>
                            <div style="font-size: 0.85rem; color: var(--text-muted);" x-text="activeRes.customer_phone || '{{ __('No phone provided') }}'"></div>
                        </div>
                    </div>

                    <!-- Booking Schedule -->
                    <div style="margin-bottom: 1.25rem;">
                        <h4 style="font-size: 0.95rem; font-weight: 800; margin-bottom: 0.5rem;">{{ __('Schedule & Table') }}</h4>
                        <div style="padding: 0.75rem; border-radius: 8px; background: rgba(0,0,0,0.02); font-size: 0.9rem;">
                            <div><strong>{{ __('Date') }}:</strong> <span x-text="activeRes.reservation_date"></span></div>
                            <div><strong>{{ __('Time') }}:</strong> <span x-text="activeRes.reservation_time"></span> (<span x-text="activeRes.duration_minutes"></span> min)</div>
                            <div><strong>{{ __('Guests') }}:</strong> <span x-text="activeRes.guest_count"></span></div>
                            <div><strong>{{ __('Assigned Table') }}:</strong> <span x-text="activeRes.table_name || activeRes.table_number || '{{ __('Auto-assigned') }}'"></span></div>
                            <template x-if="activeRes.special_requests">
                                <div style="margin-top: 0.4rem; padding-top: 0.4rem; border-top: 1px dashed var(--border-color); color: #d97706;">
                                    <strong>{{ __('Special Requests') }}:</strong> <span x-text="activeRes.special_requests"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Pre-Order Items Breakdown -->
                    <template x-if="activeRes.pre_order && activeRes.pre_order.items && activeRes.pre_order.items.length > 0">
                        <div style="margin-bottom: 1.25rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <h4 style="font-size: 0.95rem; font-weight: 800;">{{ __('Pre-Ordered Dishes') }}</h4>
                                <span class="badge badge-emerald" x-text="activeRes.pre_order.status"></span>
                            </div>
                            <div style="border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden;">
                                <template x-for="item in activeRes.pre_order.items" :key="item.product_id">
                                    <div style="padding: 0.6rem 0.75rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; font-size: 0.88rem;">
                                        <div>
                                            <strong x-text="item.quantity + 'x ' + item.name"></strong>
                                            <template x-if="item.variation_name">
                                                <span style="color: var(--text-muted); font-size: 0.75rem;" x-text="'(' + item.variation_name + ')'"></span>
                                            </template>
                                        </div>
                                        <div style="font-weight: 700;" x-text="Number(item.subtotal).toLocaleString() + ' ' + activeRes.pre_order.currency"></div>
                                    </div>
                                </template>
                                <div style="padding: 0.75rem; background: rgba(0,0,0,0.02); display: flex; justify-content: space-between; font-weight: 800;">
                                    <span>{{ __('Pre-Order Total:') }}</span>
                                    <span style="color: var(--primary);" x-text="Number(activeRes.pre_order.total_amount).toLocaleString() + ' ' + activeRes.pre_order.currency"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
function adminReservationsApp() {
    return {
        showDrawer: false,
        isLoadingDrawer: false,
        activeRes: null,

        async inspectReservation(id) {
            this.showDrawer = true;
            this.isLoadingDrawer = true;
            try {
                const res = await fetch(`{{ url('/admin/reservations') }}/${id}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.activeRes = data.reservation;
                }
            } catch (err) {
                alert('{{ __('Failed to load reservation details.') }}');
            } finally {
                this.isLoadingDrawer = false;
            }
        },

        async updateStatusApi(newStatus) {
            if (!this.activeRes) return;
            try {
                const res = await fetch(`{{ url('/admin/reservations') }}/${this.activeRes.id}/status`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ status: newStatus })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.activeRes.status = newStatus;
                    window.location.reload();
                } else {
                    alert(data.message || '{{ __('Status update failed.') }}');
                }
            } catch (err) {
                alert('{{ __('Connection error.') }}');
            }
        }
    };
}
</script>
@endsection
