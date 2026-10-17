@extends('layouts.app')

@section('title', 'Customers & CRM - ' . $vendor->name)

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div style="min-width: 0; flex: 1;">
        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                <span style="background: rgba(245, 158, 11, 0.15); color: var(--primary); width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-users-gear"></i>
                </span>
                <span>{{ __('Customers & CRM') }}</span>
            </h1>
            @if($selectedLocation)
                <span style="background: rgba(79, 70, 229, 0.15); color: #4f46e5; border: 1px solid rgba(79, 70, 229, 0.3); border-radius: 8px; padding: 0.3rem 0.75rem; font-size: 0.8rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem;">
                    <i class="fa-solid fa-location-dot"></i> {{ $selectedLocation->name }}
                </span>
            @else
                <span style="background: rgba(107, 114, 128, 0.15); color: var(--text-muted); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.3rem 0.75rem; font-size: 0.8rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem;">
                    <i class="fa-solid fa-layer-group"></i> {{ __('All Locations') }}
                </span>
            @endif
        </div>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0; word-break: break-word;">
            {{ __('Manage your customer directory, view order histories, marketing consents, and export records') }}
        </p>
    </div>
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        <a href="{{ route('admin.customers.export') }}" class="btn btn-secondary" style="font-size: 0.88rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; border-radius: 12px;">
            <i class="fa-solid fa-file-excel" style="color: #10b981;"></i> {{ __('Export CSV / Excel') }}
        </a>
        <button class="btn btn-primary" onclick="document.getElementById('addCustomerModal').style.display='flex'" style="font-size: 0.88rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; border-radius: 12px; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.3);">
            <i class="fa-solid fa-user-plus"></i> + {{ __('Add Customer') }}
        </button>
    </div>
</div>

<!-- Metrics Overview Cards -->
<div class="grid-3" style="margin-bottom: 1.75rem;">
    <div class="card kpi-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.35rem 1.5rem; display: flex; align-items: center; gap: 1.25rem; box-shadow: var(--shadow-card); position: relative; overflow: hidden;">
        <div style="width: 52px; height: 52px; border-radius: 14px; background: rgba(79, 70, 229, 0.15); color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0;">
            <i class="fa-solid fa-users"></i>
        </div>
        <div style="min-width: 0; flex: 1;">
            <div style="font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.04em;">{{ __('Total Customers') }}</div>
            <div style="font-family: 'Outfit', sans-serif; font-size: 1.65rem; font-weight: 800; color: var(--text-main); margin-top: 0.15rem; line-height: 1.2;">{{ number_format($totalCustomers) }}</div>
        </div>
    </div>

    <div class="card kpi-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.35rem 1.5rem; display: flex; align-items: center; gap: 1.25rem; box-shadow: var(--shadow-card); position: relative; overflow: hidden;">
        <div style="width: 52px; height: 52px; border-radius: 14px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0;">
            <i class="fa-solid fa-bullhorn"></i>
        </div>
        <div style="min-width: 0; flex: 1;">
            <div style="font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.04em;">{{ __('Marketing Opt-in') }}</div>
            <div style="font-family: 'Outfit', sans-serif; font-size: 1.65rem; font-weight: 800; color: #10b981; margin-top: 0.15rem; line-height: 1.2;">{{ number_format($optedInCustomers) }}</div>
        </div>
    </div>

    <div class="card kpi-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.35rem 1.5rem; display: flex; align-items: center; gap: 1.25rem; box-shadow: var(--shadow-card); position: relative; overflow: hidden;">
        <div style="width: 52px; height: 52px; border-radius: 14px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0;">
            <i class="fa-solid fa-coins"></i>
        </div>
        <div style="min-width: 0; flex: 1;">
            <div style="font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.04em;">{{ __('Customer Revenue') }}</div>
            <div style="font-family: 'Outfit', sans-serif; font-size: 1.65rem; font-weight: 800; color: #f59e0b; margin-top: 0.15rem; line-height: 1.2; word-break: break-word;">{{ number_format($totalRevenue) }} {{ $vendor->currency }}</div>
        </div>
    </div>
</div>

<!-- Upcoming Birthdays & CRM Automation Widget -->
@php
    $crmSettings = $vendor->getCrmSettings();
    $bdayDiscount = $crmSettings['birthday_discount_percent'] ?? 15;
@endphp

@if(isset($upcomingBirthdays) && count($upcomingBirthdays) > 0)
<div class="card" style="background: linear-gradient(135deg, rgba(236, 72, 153, 0.08), rgba(245, 158, 11, 0.05)); border: 1px solid rgba(236, 72, 153, 0.25); border-radius: 20px; padding: 1.25rem 1.5rem; margin-bottom: 1.75rem; box-shadow: var(--shadow-card);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem;">
        <div style="display: flex; align-items: center; gap: 0.65rem;">
            <span style="width: 36px; height: 36px; border-radius: 10px; background: rgba(236, 72, 153, 0.2); color: #ec4899; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                <i class="fa-solid fa-cake-candles"></i>
            </span>
            <div>
                <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit';">
                    🎂 {{ __('Upcoming Birthdays (CRM Loyalty Automation)') }}
                </h3>
                <p style="margin: 0.15rem 0 0; font-size: 0.8rem; color: var(--text-muted);">
                    {{ __('Customers celebrating birthdays in the next 14 days — Auto discount:') }} <strong>{{ $bdayDiscount }}%</strong>
                </p>
            </div>
        </div>
        <span style="background: rgba(236, 72, 153, 0.15); color: #ec4899; font-weight: 800; font-size: 0.8rem; padding: 0.25rem 0.75rem; border-radius: 9999px;">
            {{ count($upcomingBirthdays) }} {{ __('customers') }}
        </span>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr)); gap: 0.85rem;">
        @foreach($upcomingBirthdays as $item)
            @php
                $cust = $item['customer'];
                $isToday = $item['is_today'];
                $days = $item['days_until'];
            @endphp
            <div style="background: var(--bg-card); border: 1.5px solid {{ $isToday ? '#ec4899' : 'var(--border-color)' }}; border-radius: 14px; padding: 0.85rem 1rem; display: flex; justify-content: space-between; align-items: center; gap: 0.75rem;">
                <div style="min-width: 0;">
                    <div style="display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.2rem;">
                        <strong style="font-size: 0.92rem; color: var(--text-main);">{{ $cust->name ?? __('Guest') }}</strong>
                        @if($isToday)
                            <span style="background: #ec4899; color: #fff; font-size: 0.68rem; font-weight: 800; padding: 0.1rem 0.4rem; border-radius: 6px;">🎉 {{ __('TODAY') }}</span>
                        @else
                            <span style="background: rgba(236, 72, 153, 0.12); color: #ec4899; font-size: 0.7rem; font-weight: 700; padding: 0.1rem 0.4rem; border-radius: 6px;">{{ __('In :days days', ['days' => $days]) }}</span>
                        @endif
                    </div>
                    <div style="font-size: 0.78rem; color: var(--text-muted);">
                        <span>📞 {{ $cust->phone ?? __('No phone') }}</span> &bull; <span>{{ $item['birthday_date'] }}</span>
                    </div>
                </div>

                @if(!empty($cust->phone))
                <form action="{{ route('admin.customers.birthday_sms', $cust->id) }}" method="POST" style="margin: 0;">
                    @csrf
                    <button type="submit" class="btn btn-primary" style="padding: 0.45rem 0.75rem; font-size: 0.78rem; border-radius: 10px; display: inline-flex; align-items: center; gap: 0.35rem; white-space: nowrap;" title="{{ __('Send Birthday Greeting SMS') }}">
                        <i class="fa-solid fa-paper-plane"></i> SMS
                    </button>
                </form>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endif

<!-- Search & Filters -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1.1rem 1.25rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
    <form method="GET" action="{{ route('admin.customers.index') }}" style="display: flex; gap: 0.85rem; flex-wrap: wrap; align-items: center;">
        <div style="flex: 1; position: relative; min-width: min(100%, 240px);">
            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.9rem;"></i>
            <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('Search by name, phone, or email...') }}" style="width: 100%; box-sizing: border-box; padding: 0.65rem 1rem 0.65rem 2.6rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.9rem; outline: none;">
        </div>

        <div style="min-width: min(100%, 200px); flex-shrink: 0;">
            <select name="location_id" onchange="this.form.submit()" style="width: 100%; box-sizing: border-box; padding: 0.65rem 0.9rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.88rem; outline: none; font-weight: 600;">
                <option value="all" {{ $activeLocationId === 'all' ? 'selected' : '' }}>🏢 {{ __('All Locations') }}</option>
                @foreach($locations as $loc)
                    <option value="{{ $loc->id }}" {{ $activeLocationId == $loc->id ? 'selected' : '' }}>
                        📍 {{ $loc->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div style="min-width: min(100%, 180px); flex-shrink: 0;">
            <select name="marketing" onchange="this.form.submit()" style="width: 100%; box-sizing: border-box; padding: 0.65rem 0.9rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.88rem; outline: none; font-weight: 600;">
                <option value="">{{ __('All Statuses') }}</option>
                <option value="yes" {{ $consentFilter == 'yes' ? 'selected' : '' }}>✅ {{ __('Subscribed Only') }}</option>
                <option value="no" {{ $consentFilter == 'no' ? 'selected' : '' }}>❌ {{ __('Disabled') }}</option>
            </select>
        </div>

        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
            <button type="submit" class="btn btn-secondary" style="font-size: 0.88rem; font-weight: 600; padding: 0.65rem 1.1rem; border-radius: 12px;">
                <i class="fa-solid fa-filter"></i> {{ __('Filter') }}
            </button>
            @if($search || $consentFilter || ($activeLocationId && $activeLocationId !== 'all'))
                <a href="{{ route('admin.customers.index') }}" class="btn btn-secondary" style="font-size: 0.88rem; padding: 0.65rem 1rem; border-radius: 12px; color: var(--text-muted);">
                    <i class="fa-solid fa-xmark"></i> {{ __('Clear') }}
                </a>
            @endif
        </div>
    </form>
</div>

<!-- Customer Directory Table Card -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 0; overflow: hidden; box-shadow: var(--shadow-card);">
    <div class="responsive-table-wrapper">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
            <thead>
                <tr style="background: var(--bg-body); border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em;">
                    <th style="padding: 1rem 1.25rem;">{{ __('Customer') }}</th>
                    <th style="padding: 1rem 1.25rem;">{{ __('Location') }}</th>
                    <th style="padding: 1rem 1.25rem;">{{ __('Phone') }}</th>
                    <th style="padding: 1rem 1.25rem;">{{ __('Email') }}</th>
                    <th style="padding: 1rem 1.25rem;">{{ __('Orders') }}</th>
                    <th style="padding: 1rem 1.25rem;">{{ __('Total Spent') }}</th>
                    <th style="padding: 1rem 1.25rem;">{{ __('Marketing') }}</th>
                    <th style="padding: 1rem 1.25rem;">{{ __('Last Order') }}</th>
                    <th style="padding: 1rem 1.25rem; text-align: right;">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($customers as $c)
                    <tr style="border-bottom: 1px solid var(--border-color); color: var(--text-main); transition: background 0.15s ease;">
                        <td style="padding: 1rem 1.25rem; font-weight: 700; max-width: 220px;">
                            <a href="{{ route('admin.customers.show', $c->id) }}" style="color: var(--text-main); text-decoration: none; display: inline-flex; align-items: center; gap: 0.45rem; font-weight: 700;">
                                <span style="width: 32px; height: 32px; border-radius: 10px; background: rgba(245, 158, 11, 0.15); color: var(--primary); display: inline-flex; align-items: center; justify-content: center; font-size: 0.95rem; flex-shrink: 0;">
                                    <i class="fa-solid fa-user"></i>
                                </span>
                                <span class="truncate-text" style="max-width: 160px;">{{ $c->name ?? __('Guest') }}</span>
                            </a>
                            @if($c->birthdate)
                                <div style="font-size: 0.75rem; color: #ec4899; font-weight: 600; margin-top: 0.25rem; display: flex; align-items: center; gap: 0.35rem;">
                                    <i class="fa-solid fa-cake-candles"></i> {{ $c->birthdate->format('d M Y') }} ({{ $c->birthdate->age }} {{ __('yrs') }})
                                </div>
                            @endif
                            @if($c->address)
                                <div class="truncate-text" style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem; max-width: 180px;" title="{{ $c->address }}">
                                    <i class="fa-solid fa-map-pin" style="color: #f59e0b; margin-right: 0.25rem;"></i>{{ $c->address }}
                                </div>
                            @endif
                        </td>
                        <td style="padding: 1rem 1.25rem; white-space: nowrap;">
                            <span style="background: var(--bg-body); border: 1px solid var(--border-color); padding: 0.25rem 0.6rem; border-radius: 8px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.35rem;">
                                <i class="fa-solid fa-location-dot" style="color: var(--primary);"></i> {{ $c->location?->name ?? __('All') }}
                            </span>
                        </td>
                        <td style="padding: 1rem 1.25rem; color: var(--text-muted); font-size: 0.88rem; font-weight: 600; white-space: nowrap;">
                            {{ $c->phone ?? '—' }}
                        </td>
                        <td style="padding: 1rem 1.25rem; color: var(--text-muted); font-size: 0.88rem; max-width: 180px;">
                            <span class="truncate-text" style="max-width: 170px;" title="{{ $c->email }}">{{ $c->email ?? '—' }}</span>
                        </td>
                        <td style="padding: 1rem 1.25rem; white-space: nowrap;">
                            <span style="background: var(--bg-body); border: 1px solid var(--border-color); padding: 0.25rem 0.65rem; border-radius: 8px; font-weight: 700; font-size: 0.85rem;">
                                {{ $c->total_orders_count }} {{ $c->total_orders_count === 1 ? __('order') : __('orders') }}
                            </span>
                        </td>
                        <td style="padding: 1rem 1.25rem; font-weight: 800; color: #10b981; font-family: 'Outfit', sans-serif; font-size: 1rem; white-space: nowrap;">
                            {{ number_format($c->total_spent) }} {{ $vendor->currency }}
                        </td>
                        <td style="padding: 1rem 1.25rem; white-space: nowrap;">
                            @if($c->marketing_opt_in)
                                <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); padding: 0.25rem 0.6rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                                    <i class="fa-solid fa-check"></i> {{ __('Subscribed') }}
                                </span>
                            @else
                                <span style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); padding: 0.25rem 0.6rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                                    <i class="fa-solid fa-xmark"></i> {{ __('Disabled') }}
                                </span>
                            @endif
                        </td>
                        <td style="padding: 1rem 1.25rem; font-size: 0.82rem; color: var(--text-muted); white-space: nowrap;">
                            {{ $c->last_order_at ? $c->last_order_at->diffForHumans() : '—' }}
                        </td>
                        <td style="padding: 1rem 1.25rem; text-align: right; white-space: nowrap;">
                            <div style="display: inline-flex; gap: 0.4rem; justify-content: flex-end;">
                                <a href="{{ route('admin.customers.show', $c->id) }}" class="btn btn-secondary" style="padding: 0.4rem 0.7rem; font-size: 0.78rem; border-radius: 8px;" title="{{ __('History') }}">
                                    <i class="fa-solid fa-timeline"></i>
                                </a>
                                <button class="btn btn-secondary" style="padding: 0.4rem 0.7rem; font-size: 0.78rem; border-radius: 8px;" onclick="editCustomer({{ json_encode($c) }})" title="{{ __('Edit') }}">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <form action="{{ route('admin.customers.destroy', $c->id) }}" method="POST" onsubmit="return confirm('{{ __('Are you sure you want to delete this customer?') }}')" style="display: inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-danger" style="padding: 0.4rem 0.7rem; font-size: 0.78rem; border-radius: 8px;" title="{{ __('Delete') }}">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 3.5rem 1.5rem; color: var(--text-muted);">
                            <div style="width: 60px; height: 60px; border-radius: 16px; background: rgba(245, 158, 11, 0.1); color: var(--primary); display: inline-flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 1rem;">
                                <i class="fa-solid fa-users"></i>
                            </div>
                            <div style="font-size: 1.05rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem;">{{ __('No customers found') }}</div>
                            <p style="font-size: 0.85rem; color: var(--text-muted); max-width: 420px; margin: 0 auto;">
                                {{ __('Customer data will automatically populate as guests place orders through your digital menu.') }}
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div style="margin-top: 1.5rem;">
    {{ $customers->appends(request()->query())->links() }}
</div>

<!-- Add Customer Modal -->
<div id="addCustomerModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 100; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card modal-box-responsive" style="width: 100%; max-width: min(520px, 94vw); max-height: 90vh; overflow-y: auto; box-sizing: border-box; padding: clamp(1.25rem, 3vw, 1.85rem); position: relative; border-radius: 20px; background: var(--bg-card); border: 1px solid var(--border-color); box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.35rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.85rem;">
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.6rem;">
                <span style="color: var(--primary);"><i class="fa-solid fa-user-plus"></i></span>
                <span>{{ __('Add New Customer') }}</span>
            </h3>
            <button onclick="document.getElementById('addCustomerModal').style.display='none'" style="background: none; border: none; color: var(--text-muted); font-size: 1.25rem; cursor: pointer; padding: 0.25rem;">✕</button>
        </div>

        <form action="{{ route('admin.customers.store') }}" method="POST">
            @csrf
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Full Name') }} <span style="color: #ef4444;">*</span></label>
                <input type="text" name="name" required placeholder="{{ __('e.g. John Doe') }}" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none; font-weight: 600;">
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Location') }}</label>
                <select name="location_id" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none; font-weight: 600;">
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ $activeLocationId == $loc->id ? 'selected' : '' }}>
                            📍 {{ $loc->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr)); gap: 0.85rem; margin-bottom: 1rem;">
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Phone Number') }}</label>
                    <input type="tel" name="phone" placeholder="+1 234 567 8900" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none; font-weight: 600;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Email Address') }}</label>
                    <input type="email" name="email" placeholder="john@example.com" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none; font-weight: 600;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr)); gap: 0.85rem; margin-bottom: 1rem;">
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                        <i class="fa-solid fa-cake-candles" style="color: #ec4899;"></i> {{ __('Birth Date') }}
                    </label>
                    <input type="date" name="birthdate" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                        <i class="fa-solid fa-map-pin" style="color: #f59e0b;"></i> {{ __('Address') }}
                    </label>
                    <input type="text" name="address" placeholder="{{ __('e.g. 123 Main St, Apt 4') }}" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none;">
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Customer Notes') }}</label>
                <textarea name="notes" rows="2" placeholder="{{ __('e.g. VIP guest, prefers booth seating...') }}" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); outline: none; font-size: 0.9rem; resize: vertical;"></textarea>
            </div>

            <div style="margin-bottom: 1.5rem; background: var(--bg-body); padding: 0.85rem 1rem; border-radius: 12px; border: 1px solid var(--border-color);">
                <label style="display: flex; align-items: center; gap: 0.65rem; font-size: 0.88rem; font-weight: 600; color: var(--text-main); cursor: pointer; user-select: none;">
                    <input type="checkbox" name="marketing_opt_in" value="1" checked style="width: 18px; height: 18px; accent-color: var(--primary);">
                    <span>{{ __('Consents to receive promotional updates and marketing') }}</span>
                </label>
            </div>

            <div style="display: flex; gap: 0.75rem; justify-content: flex-end; flex-wrap: wrap;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('addCustomerModal').style.display='none'" style="border-radius: 12px; font-weight: 600;">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary" style="border-radius: 12px; font-weight: 700; padding: 0.7rem 1.6rem;">{{ __('Save Customer') }}</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Customer Modal -->
<div id="editCustomerModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 100; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card modal-box-responsive" style="width: 100%; max-width: min(520px, 94vw); max-height: 90vh; overflow-y: auto; box-sizing: border-box; padding: clamp(1.25rem, 3vw, 1.85rem); position: relative; border-radius: 20px; background: var(--bg-card); border: 1px solid var(--border-color); box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.35rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.85rem;">
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.6rem;">
                <span style="color: var(--primary);"><i class="fa-solid fa-pen-to-square"></i></span>
                <span>{{ __('Edit Customer') }}</span>
            </h3>
            <button onclick="document.getElementById('editCustomerModal').style.display='none'" style="background: none; border: none; color: var(--text-muted); font-size: 1.25rem; cursor: pointer; padding: 0.25rem;">✕</button>
        </div>

        <form id="editCustomerForm" method="POST">
            @csrf
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Full Name') }} <span style="color: #ef4444;">*</span></label>
                <input type="text" id="edit_name" name="name" required style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none; font-weight: 600;">
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Location') }}</label>
                <select id="edit_location_id" name="location_id" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none; font-weight: 600;">
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}">
                            📍 {{ $loc->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr)); gap: 0.85rem; margin-bottom: 1rem;">
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Phone Number') }}</label>
                    <input type="tel" id="edit_phone" name="phone" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none; font-weight: 600;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Email Address') }}</label>
                    <input type="email" id="edit_email" name="email" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none; font-weight: 600;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr)); gap: 0.85rem; margin-bottom: 1rem;">
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                        <i class="fa-solid fa-cake-candles" style="color: #ec4899;"></i> {{ __('Birth Date') }}
                    </label>
                    <input type="date" id="edit_birthdate" name="birthdate" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                        <i class="fa-solid fa-map-pin" style="color: #f59e0b;"></i> {{ __('Address') }}
                    </label>
                    <input type="text" id="edit_address" name="address" placeholder="{{ __('e.g. 123 Main St, Apt 4') }}" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none;">
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Customer Notes') }}</label>
                <textarea id="edit_notes" name="notes" rows="2" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); outline: none; font-size: 0.9rem; resize: vertical;"></textarea>
            </div>

            <div style="margin-bottom: 1.5rem; background: var(--bg-body); padding: 0.85rem 1rem; border-radius: 12px; border: 1px solid var(--border-color);">
                <label style="display: flex; align-items: center; gap: 0.65rem; font-size: 0.88rem; font-weight: 600; color: var(--text-main); cursor: pointer; user-select: none;">
                    <input type="checkbox" id="edit_marketing_opt_in" name="marketing_opt_in" value="1" style="width: 18px; height: 18px; accent-color: var(--primary);">
                    <span>{{ __('Consents to receive promotional updates and marketing') }}</span>
                </label>
            </div>

            <div style="display: flex; gap: 0.75rem; justify-content: flex-end; flex-wrap: wrap;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('editCustomerModal').style.display='none'" style="border-radius: 12px; font-weight: 600;">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary" style="border-radius: 12px; font-weight: 700; padding: 0.7rem 1.6rem;">{{ __('Update Customer') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
    function editCustomer(c) {
        document.getElementById('editCustomerForm').action = "/admin/customers/" + c.id;
        document.getElementById('edit_name').value = c.name || '';
        if (document.getElementById('edit_location_id')) {
            document.getElementById('edit_location_id').value = c.location_id || '';
        }
        document.getElementById('edit_phone').value = c.phone || '';
        document.getElementById('edit_email').value = c.email || '';
        document.getElementById('edit_birthdate').value = c.birthdate ? c.birthdate.substring(0, 10) : '';
        document.getElementById('edit_address').value = c.address || '';
        document.getElementById('edit_notes').value = c.notes || '';
        document.getElementById('edit_marketing_opt_in').checked = !!c.marketing_opt_in;
        document.getElementById('editCustomerModal').style.display = 'flex';
    }
</script>
@endsection
