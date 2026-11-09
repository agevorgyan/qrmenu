@extends('layouts.app')

@section('title', 'Vendor Subscriptions & Billing - SuperAdmin')

@section('styles')
<style>
    .subs-desktop-table {
        display: block;
    }
    .subs-mobile-cards {
        display: none;
    }

    .filter-panel {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 18px;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        box-shadow: var(--shadow-card);
    }

    .sub-filter-pill {
        padding: 0.35rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.78rem;
        font-weight: 600;
        cursor: pointer;
        border: 1px solid var(--border-color);
        background: var(--input-bg);
        color: var(--text-muted);
        transition: all 0.2s ease;
    }
    .sub-filter-pill:hover {
        color: var(--text-main);
        background: var(--nav-hover);
    }
    .sub-filter-pill.active {
        background: var(--nav-active);
        border-color: var(--nav-active-border);
        color: var(--primary);
        font-weight: 700;
    }

    @media (max-width: 992px) {
        .subs-desktop-table {
            display: none !important;
        }
        .subs-mobile-cards {
            display: flex !important;
            flex-direction: column;
            gap: 1rem;
        }
        .sub-mobile-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            padding: 1.25rem;
            box-shadow: var(--shadow-card);
        }
    }
</style>
@endsection

@section('content')
<div x-data="{
    search: '',
    statusFilter: 'all',
    planFilter: 'all',
    matches(name, email, status, plan) {
        const q = this.search.toLowerCase().trim();
        const matchesSearch = !q || 
            (name && name.toLowerCase().includes(q)) || 
            (email && email.toLowerCase().includes(q));
        const matchesStatus = this.statusFilter === 'all' || status === this.statusFilter;
        const matchesPlan = this.planFilter === 'all' || plan.toLowerCase() === this.planFilter.toLowerCase();
        return matchesSearch && matchesStatus && matchesPlan;
    }
}">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                <span class="badge badge-purple">
                    <i class="fa-solid fa-credit-card"></i> {{ __('Billing & Payments') }}
                </span>
                <span style="font-size: 0.78rem; color: var(--text-muted);">{{ __('Subscriptions') }}</span>
            </div>
            <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
                {{ __('Partner Subscriptions & Payments') }}
            </h1>
        </div>
        <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
            <a href="{{ route('superadmin.settings.index') }}#payments" class="btn btn-secondary" style="border-radius: 10px; font-size: 0.82rem;">
                <i class="fa-solid fa-credit-card"></i> {{ __('Payment Gateways') }}
            </a>
            <a href="{{ route('superadmin.plans.index') }}" class="btn btn-secondary" style="border-radius: 10px; font-size: 0.82rem;">
                <i class="fa-solid fa-sliders"></i> {{ __('Plan Builder & Catalog') }}
            </a>
            <a href="{{ route('superadmin.subscriptions.payments.index') }}" class="btn btn-secondary" style="border-radius: 10px; font-size: 0.82rem;">
                <i class="fa-solid fa-file-invoice-dollar"></i> {{ __('Billing & Payments') }}
            </a>
            <a href="{{ route('superadmin.subscriptions.audit_logs') }}" class="btn btn-secondary" style="border-radius: 10px; font-size: 0.82rem;">
                <i class="fa-solid fa-shield-halved"></i> {{ __('Audit Logs') }}
            </a>
        </div>
    </div>

    <!-- Stats KPI Row -->
    <div class="grid-4" style="gap: 1rem; margin-bottom: 1.5rem;">
        <div class="stat-kpi-card" style="padding: 1.15rem 1.25rem;">
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">{{ __('Total Accounts') }}</div>
            <div style="font-size: 1.85rem; font-weight: 800; font-family: 'Outfit'; color: var(--text-main); margin-top: 0.2rem;">
                {{ $vendors->count() }}
            </div>
        </div>
        <div class="stat-kpi-card" style="padding: 1.15rem 1.25rem;">
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">{{ __('Active Subscribers') }}</div>
            <div style="font-size: 1.85rem; font-weight: 800; font-family: 'Outfit'; color: #10b981; margin-top: 0.2rem;">
                {{ $vendors->filter(fn($v) => !$v->isExpired() && !$v->isTrialing())->count() }}
            </div>
        </div>
        <div class="stat-kpi-card" style="padding: 1.15rem 1.25rem;">
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">{{ __('Trial') }}</div>
            <div style="font-size: 1.85rem; font-weight: 800; font-family: 'Outfit'; color: #f59e0b; margin-top: 0.2rem;">
                {{ $vendors->filter(fn($v) => $v->isTrialing())->count() }}
            </div>
        </div>
        <div class="stat-kpi-card" style="padding: 1.15rem 1.25rem;">
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">{{ __('Expired / Suspended') }}</div>
            <div style="font-size: 1.85rem; font-weight: 800; font-family: 'Outfit'; color: #ef4444; margin-top: 0.2rem;">
                {{ $vendors->filter(fn($v) => $v->isExpired())->count() }}
            </div>
        </div>
    </div>

    <!-- Search & Filter Panel -->
    <div class="filter-panel">
        <div style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; justify-content: space-between;">
            <!-- Search -->
            <div style="flex: 1; min-width: 250px; position: relative;">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.85rem;"></i>
                <input type="text" x-model="search" placeholder="{{ __('Search by partner name or email...') }}" class="form-input" style="padding-left: 2.5rem; padding-right: 2rem; border-radius: 12px;">
                <button x-show="search.length > 0" @click="search = ''" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Status Pills -->
            <div style="display: flex; gap: 0.4rem; flex-wrap: wrap; align-items: center;">
                <span class="sub-filter-pill" :class="{ 'active': statusFilter === 'all' }" @click="statusFilter = 'all'">{{ __('All') }}</span>
                <span class="sub-filter-pill" :class="{ 'active': statusFilter === 'active' }" @click="statusFilter = 'active'">🟢 {{ __('Active') }}</span>
                <span class="sub-filter-pill" :class="{ 'active': statusFilter === 'trialing' }" @click="statusFilter = 'trialing'">⏳ {{ __('Trial') }}</span>
                <span class="sub-filter-pill" :class="{ 'active': statusFilter === 'expired' }" @click="statusFilter = 'expired'">🔴 {{ __('Expired') }}</span>
            </div>

            <!-- Plan Selector -->
            <div>
                <select x-model="planFilter" class="form-select" style="width: auto; padding: 0.5rem 0.85rem; border-radius: 10px; font-size: 0.8rem;">
                    <option value="all">{{ __('All Plans') }}</option>
                    @foreach($plans as $p)
                        <option value="{{ $p->slug }}">{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Desktop Table View -->
    <div class="glass-card subs-desktop-table" style="overflow: hidden; margin-bottom: 2.5rem;">
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="min-width: 200px;">{{ __('Partner') }}</th>
                        <th>{{ __('Plan') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Trial / Expiration') }}</th>
                        <th>{{ __('Days Left') }}</th>
                        <th>{{ __('Last Payment') }}</th>
                        <th style="text-align: right; min-width: 170px;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($vendors as $v)
                        @php
                            $statusKey = $v->isExpired() ? 'expired' : ($v->isTrialing() ? 'trialing' : 'active');
                            $planSlug = strtolower($v->plan?->slug ?? ($v->subscription_plan ?? 'pro'));
                        @endphp
                        <tr x-show="matches('{{ addslashes($v->name) }}', '{{ addslashes($v->email) }}', '{{ $statusKey }}', '{{ $planSlug }}')">
                            <!-- Vendor -->
                            <td>
                                <div style="font-weight: 700; color: var(--text-main); font-size: 0.92rem;">{{ $v->name }}</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem;">
                                    {{ $v->email }} @if($v->phone) • {{ $v->phone }} @endif
                                </div>
                            </td>

                            <!-- Plan -->
                            <td>
                                <span class="badge badge-indigo">
                                    {{ $v->plan?->name ?? strtoupper($v->subscription_plan ?? 'PRO') }}
                                </span>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem; font-weight: 600;">
                                    {{ $v->plan?->formatted_price ?? '—' }}
                                </div>
                            </td>

                            <!-- Status -->
                            <td>
                                @if($v->isExpired())
                                    <span class="badge badge-rose">
                                        <i class="fa-solid fa-circle" style="font-size: 0.45rem;"></i> {{ __('Expired') }}
                                    </span>
                                @elseif($v->isTrialing())
                                    <span class="badge badge-amber">
                                        <i class="fa-solid fa-clock" style="font-size: 0.65rem;"></i> {{ __('Trial') }}
                                    </span>
                                @else
                                    <span class="badge badge-emerald">
                                        <i class="fa-solid fa-circle-check" style="font-size: 0.65rem;"></i> {{ __('Active') }}
                                    </span>
                                @endif
                            </td>

                            <!-- Expires At -->
                            <td>
                                @if($v->isTrialing())
                                    <div style="font-size: 0.82rem; color: var(--text-main);">
                                        {{ __('Trial ends:') }} <strong>{{ $v->trial_ends_at ? $v->trial_ends_at->format('d.m.Y') : '—' }}</strong>
                                    </div>
                                @else
                                    <div style="font-size: 0.82rem; color: var(--text-main);">
                                        {{ __('Expires:') }} <strong>{{ $v->subscription_expires_at ? $v->subscription_expires_at->format('d.m.Y') : __('Unlimited') }}</strong>
                                    </div>
                                @endif
                            </td>

                            <!-- Days Left -->
                            <td>
                                @if($v->isExpired())
                                    <span style="color: #ef4444; font-weight: 800; font-size: 0.85rem;">0 {{ __('days') }}</span>
                                @else
                                    <span style="color: {{ $v->daysLeft() <= 3 ? '#f59e0b' : '#10b981' }}; font-weight: 800; font-size: 0.85rem;">
                                        {{ $v->daysLeft() }} {{ __('days') }}
                                    </span>
                                @endif
                            </td>

                            <!-- Last Payment -->
                            <td>
                                @if($v->payments->first())
                                    <div style="font-weight: 800; color: #10b981; font-size: 0.88rem;">
                                        {{ number_format($v->payments->first()->amount, 0, '.', ' ') }} {{ $v->payments->first()->currency }}
                                    </div>
                                    <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.1rem;">
                                        {{ $v->payments->first()->created_at->format('d.m.Y') }} ({{ $v->payments->first()->payment_method }})
                                    </div>
                                @else
                                    <span style="font-size: 0.78rem; color: var(--text-muted);">{{ __('No payments') }}</span>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 0.4rem; justify-content: flex-end; flex-wrap: wrap;">
                                    <a href="{{ route('superadmin.subscriptions.usage', $v->id) }}" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.4rem 0.6rem; border-radius: 8px;" title="{{ __('Usage & Limits') }}">
                                        <i class="fa-solid fa-chart-pie"></i> {{ __('Usage') }}
                                    </a>
                                    <a href="{{ route('superadmin.subscriptions.lifecycle', $v->id) }}" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.4rem 0.6rem; border-radius: 8px;" title="{{ __('Subscription History') }}">
                                        <i class="fa-solid fa-clock-rotate-left"></i>
                                    </a>
                                    <button type="button" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.4rem 0.6rem; border-radius: 8px;" title="{{ __('Vendor Overrides & Add-ons') }}" onclick="openOverrideAddonModal({{ json_encode($v) }})">
                                        <i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('Overrides') }}
                                    </button>
                                    <button class="btn btn-secondary" style="font-size: 0.78rem; padding: 0.4rem 0.65rem; border-radius: 8px;" title="{{ __('Adjust Subscription') }}" onclick="openSubModal({{ json_encode($v) }})">
                                        <i class="fa-solid fa-pen-to-square"></i> {{ __('Modify') }}
                                    </button>
                                    <button class="btn btn-primary" style="font-size: 0.78rem; padding: 0.4rem 0.75rem; border-radius: 8px;" title="{{ __('Record Payment') }}" onclick="openPaymentModal({{ json_encode($v) }})">
                                        <i class="fa-solid fa-receipt"></i> {{ __('+ Payment') }}
                                    </button>
                                    <form action="{{ route('superadmin.subscriptions.destroy', $v->id) }}" method="POST" onsubmit="return confirm('{{ __('Վստա՞հ եք, որ ցանկանում եք չեղարկել և հեռացնել այս գործընկերոջ բաժանորդագրությունը:') }}');" style="display: inline; margin: 0;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-secondary" style="font-size: 0.78rem; padding: 0.4rem 0.65rem; border-radius: 8px; color: #ef4444; border-color: rgba(239, 68, 68, 0.3);" title="{{ __('Հեռացնել / Չեղարկել բաժանորդագրությունը') }}">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Mobile Cards View -->
    <div class="subs-mobile-cards">
        @foreach($vendors as $v)
            @php
                $statusKey = $v->isExpired() ? 'expired' : ($v->isTrialing() ? 'trialing' : 'active');
                $planSlug = strtolower($v->plan?->slug ?? ($v->subscription_plan ?? 'pro'));
            @endphp
            <div class="sub-mobile-card" x-show="matches('{{ addslashes($v->name) }}', '{{ addslashes($v->email) }}', '{{ $statusKey }}', '{{ $planSlug }}')">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div>
                        <div style="font-weight: 800; font-size: 1.05rem; color: var(--text-main);">{{ $v->name }}</div>
                        <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem;">
                            {{ $v->email }} @if($v->phone) • {{ $v->phone }} @endif
                        </div>
                    </div>
                    <div>
                        @if($v->isExpired())
                            <span class="badge badge-rose">🔴 {{ __('Expired') }}</span>
                        @elseif($v->isTrialing())
                            <span class="badge badge-amber">⏳ {{ __('Trial') }}</span>
                        @else
                            <span class="badge badge-emerald">🟢 {{ __('Active') }}</span>
                        @endif
                    </div>
                </div>

                <div style="background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.75rem 0.9rem; margin-bottom: 0.85rem; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <span class="badge badge-indigo">{{ $v->plan?->name ?? strtoupper($v->subscription_plan ?? 'PRO') }}</span>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                            {{ $v->plan?->formatted_price ?? '—' }}
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 0.72rem; color: var(--text-muted);">{{ __('Remaining:') }}</div>
                        <div style="font-size: 1.05rem; font-weight: 800; font-family: 'Outfit'; color: {{ $v->isExpired() ? '#ef4444' : ($v->daysLeft() <= 3 ? '#f59e0b' : '#10b981') }};">
                            {{ $v->isExpired() ? '0 ' . __('days') : $v->daysLeft() . ' ' . __('days') }}
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem; margin-bottom: 0.5rem;">
                    <button class="btn btn-secondary" style="justify-content: center; font-size: 0.82rem; padding: 0.55rem 0.75rem;" onclick="openSubModal({{ json_encode($v) }})">
                        <i class="fa-solid fa-pen-to-square"></i> {{ __('Modify') }}
                    </button>
                    <button class="btn btn-primary" style="justify-content: center; font-size: 0.82rem; padding: 0.55rem 0.75rem;" onclick="openPaymentModal({{ json_encode($v) }})">
                        <i class="fa-solid fa-receipt"></i> {{ __('+ Payment') }}
                    </button>
                </div>
                <form action="{{ route('superadmin.subscriptions.destroy', $v->id) }}" method="POST" onsubmit="return confirm('{{ __('Վստա՞հ եք, որ ցանկանում եք չեղարկել և հեռացնել այս գործընկերոջ բաժանորդագրությունը:') }}');" style="margin: 0;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-secondary" style="width: 100%; justify-content: center; font-size: 0.78rem; padding: 0.45rem; color: #ef4444; border-color: rgba(239, 68, 68, 0.3);">
                        <i class="fa-solid fa-trash-can"></i> {{ __('Հեռացնել / Չեղարկել բաժանորդագրությունը') }}
                    </button>
                </form>
            </div>
        @endforeach
    </div>

    <!-- Modern Edit Subscription Modal -->
    <div id="subModal" class="modern-modal-overlay" style="display: none;">
        <div class="modern-modal-box">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 0.85rem; border-bottom: 1px solid var(--border-color);">
                <div style="display: flex; align-items: center; gap: 0.65rem;">
                    <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(99, 102, 241, 0.15); color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                        <i class="fa-solid fa-sliders"></i>
                    </div>
                    <h3 style="font-family: 'Outfit'; font-weight: 800; font-size: 1.25rem; color: var(--text-main); margin: 0;" id="subModalTitle">
                        {{ __('Configure Subscription') }}
                    </h3>
                </div>
                <button onclick="document.getElementById('subModal').style.display='none'" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:1.25rem;">✕</button>
            </div>

            <form id="subForm" method="POST">
                @csrf
                <div style="margin-bottom: 1rem;">
                    <label class="form-label">{{ __('Subscription Plan') }} *</label>
                    <select id="sub_plan_id" name="subscription_plan_id" class="form-select" required>
                        @foreach($plans as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} — {{ $p->formatted_price }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="margin-bottom: 1rem;">
                    <label class="form-label">{{ __('Status') }} *</label>
                    <select id="sub_status" name="subscription_status" class="form-select" required>
                        <option value="trialing">{{ __('Trial (14-day)') }}</option>
                        <option value="active">{{ __('Active') }}</option>
                        <option value="expired">{{ __('Expired / Suspended') }}</option>
                        <option value="cancelled">{{ __('Cancelled') }}</option>
                    </select>
                </div>

                <div style="margin-bottom: 1rem;">
                    <label class="form-label">{{ __('Expires At') }}</label>
                    <input type="date" id="sub_expires_at" name="subscription_expires_at" class="form-input">
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label class="form-label">{{ __('Custom Plan Notes') }}</label>
                    <textarea id="sub_custom_notes" name="custom_plan_notes" rows="3" class="form-textarea" placeholder="{{ __('e.g. Custom agreement details...') }}"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('subModal').style.display='none'">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Save Changes') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modern Record Payment Modal -->
    <div id="paymentModal" class="modern-modal-overlay" style="display: none;">
        <div class="modern-modal-box">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 0.85rem; border-bottom: 1px solid var(--border-color);">
                <div style="display: flex; align-items: center; gap: 0.65rem;">
                    <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                    <h3 style="font-family: 'Outfit'; font-weight: 800; font-size: 1.25rem; color: var(--text-main); margin: 0;" id="payModalTitle">
                        {{ __('Record Payment') }}
                    </h3>
                </div>
                <button onclick="document.getElementById('paymentModal').style.display='none'" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:1.25rem;">✕</button>
            </div>

            <form id="payForm" method="POST">
                @csrf
                <div style="margin-bottom: 1rem;">
                    <label class="form-label">{{ __('Target Plan (for Upgrade or Renewal)') }}</label>
                    <select id="pay_plan_id" name="subscription_plan_id" class="form-select">
                        @foreach($plans as $p)
                            <option value="{{ $p->id }}" data-price="{{ (float) $p->price }}">{{ $p->name }} ({{ number_format($p->price, 0, '.', ' ') }} {{ $p->currency }})</option>
                        @endforeach
                    </select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Amount (֏)') }} *</label>
                        <input type="number" id="pay_amount" name="amount" step="100" min="0" class="form-input" required placeholder="19900">
                    </div>
                    <div>
                        <label class="form-label">{{ __('Payment Method') }}</label>
                        <select name="payment_method" class="form-select">
                            <option value="bank_transfer">{{ __('Bank Transfer') }}</option>
                            <option value="card">{{ __('Bank Card') }}</option>
                            <option value="cash">{{ __('Cash') }}</option>
                            <option value="custom">{{ __('Other / Contractual') }}</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Period Start') }} *</label>
                        <input type="date" name="period_start" class="form-input" required value="{{ date('Y-m-d') }}">
                    </div>
                    <div>
                        <label class="form-label">{{ __('Period End') }} *</label>
                        <input type="date" name="period_end" class="form-input" required value="{{ date('Y-m-d', strtotime('+30 days')) }}">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Invoice Number') }}</label>
                        <input type="text" name="invoice_number" class="form-input" placeholder="INV-10045">
                    </div>
                    <div>
                        <label class="form-label">{{ __('Payment Status') }}</label>
                        <select name="status" class="form-select">
                            <option value="paid">{{ __('Paid') }}</option>
                            <option value="pending">{{ __('Pending') }}</option>
                            <option value="failed">{{ __('Failed') }}</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--text-main); cursor: pointer;">
                        <input type="checkbox" name="extend_subscription" value="1" checked>
                        <span>{{ __('Automatically extend subscription until period end date') }}</span>
                    </label>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('paymentModal').style.display='none'">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Record Payment') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Vendor Overrides & Add-ons Modal -->
    <div id="overrideAddonModal" class="modern-modal-overlay" style="display: none;">
        <div class="modern-modal-box" style="max-width: 680px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; padding-bottom: 0.85rem; border-bottom: 1px solid var(--border-color);">
                <h3 style="font-family: 'Outfit'; font-weight: 800; font-size: 1.25rem; color: var(--text-main); margin: 0;" id="overrideAddonModalTitle">
                    {{ __('Vendor Overrides & Add-ons') }}
                </h3>
                <button onclick="document.getElementById('overrideAddonModal').style.display='none'" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:1.25rem;">✕</button>
            </div>

            <!-- Section A: Individual Feature / Limit / Quota Override -->
            <form id="overrideForm" method="POST" style="margin-bottom: 1.5rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--border-color);">
                @csrf
                <h4 style="font-size: 0.95rem; font-weight: 800; margin: 0 0 0.75rem;">
                    <i class="fa-solid fa-wand-magic-sparkles" style="color: var(--primary);"></i> {{ __('Grant Individual Feature / Limit Override') }}
                </h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-bottom: 0.85rem;">
                    <div>
                        <label class="form-label">{{ __('Feature Code') }} *</label>
                        <select name="feature_key" class="form-select" required>
                            @foreach(($catalogFeatures ?? collect()) as $f)
                                <option value="{{ $f->code }}">{{ $f->localizedName() }} ({{ $f->code }})</option>
                            @endforeach
                            @foreach($availableFeatures as $fc)
                                <option value="{{ $fc }}">{{ $fc }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">{{ __('Access State') }} *</label>
                        <select name="is_enabled" class="form-select">
                            <option value="1">{{ __('Enabled (Grant Access)') }}</option>
                            <option value="0">{{ __('Disabled (Restrict Access)') }}</option>
                        </select>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.85rem; margin-bottom: 0.85rem;">
                    <div>
                        <label class="form-label">{{ __('Limit Override (-1 = Unlimited)') }}</label>
                        <input type="number" name="limit_value" min="-1" class="form-input" placeholder="{{ __('Optional limit') }}">
                    </div>
                    <div>
                        <label class="form-label">{{ __('Usage Quota Override') }}</label>
                        <input type="number" name="usage_quota" min="-1" class="form-input" placeholder="{{ __('Optional quota') }}">
                    </div>
                    <div>
                        <label class="form-label">{{ __('Expires At (Temporary)') }}</label>
                        <input type="date" name="expires_at" class="form-input">
                    </div>
                </div>
                <div style="margin-bottom: 0.85rem;">
                    <label class="form-label">{{ __('Mandatory Reason') }} *</label>
                    <input type="text" name="reason" class="form-input" required minlength="3" placeholder="{{ __('e.g. Promotional 30-day POS trial approved by sales...') }}">
                </div>
                <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-size: 0.82rem;">
                    <i class="fa-solid fa-check"></i> {{ __('Apply Override') }}
                </button>
            </form>

            <!-- Section B: Assign Subscription Add-on -->
            <form id="addonAssignForm" method="POST">
                @csrf
                <h4 style="font-size: 0.95rem; font-weight: 800; margin: 0 0 0.75rem;">
                    <i class="fa-solid fa-puzzle-piece" style="color: #6366f1;"></i> {{ __('Assign Subscription Add-on') }}
                </h4>
                <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 0.85rem; margin-bottom: 0.85rem;">
                    <div>
                        <label class="form-label">{{ __('Add-on Package') }} *</label>
                        <select name="subscription_addon_id" class="form-select" required>
                            @foreach(($subscriptionAddons ?? collect()) as $addon)
                                <option value="{{ $addon->id }}">{{ $addon->localizedName() }} (+{{ $addon->value_increment }} {{ $addon->feature_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">{{ __('Quantity') }}</label>
                        <input type="number" name="quantity" value="1" min="1" max="100" class="form-input" required>
                    </div>
                    <div>
                        <label class="form-label">{{ __('Expires At') }}</label>
                        <input type="date" name="expires_at" class="form-input">
                    </div>
                </div>
                <button type="submit" class="btn btn-secondary" style="border-radius: 10px; font-size: 0.82rem;">
                    <i class="fa-solid fa-plus"></i> {{ __('Assign Add-on to Vendor') }}
                </button>
            </form>
        </div>
    </div>

    <!-- Recent Subscription & Entitlement Audit History -->
    @if(!empty($recentAuditLogs) && $recentAuditLogs->count() > 0)
        <div class="glass-card" style="padding: 1.5rem; margin-top: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="font-family: 'Outfit'; font-size: 1.2rem; font-weight: 800; margin: 0;">
                    <i class="fa-solid fa-shield-halved" style="color: var(--primary);"></i> {{ __('Subscription & Entitlement Audit History') }}
                </h3>
                <a href="{{ route('superadmin.subscriptions.audit_logs') }}" style="font-size: 0.8rem; color: var(--primary); font-weight: 700;">
                    {{ __('View Full Audit Logs') }} →
                </a>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('Action') }}</th>
                            <th>{{ __('Partner') }}</th>
                            <th>{{ __('Actor') }}</th>
                            <th>{{ __('Reason') }}</th>
                            <th>{{ __('Timestamp') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentAuditLogs->take(10) as $log)
                            <tr>
                                <td><code>{{ $log->action_type }}</code></td>
                                <td>{{ $log->vendor?->name ?? 'Platform' }}</td>
                                <td>{{ $log->actor?->name ?? 'System' }}</td>
                                <td>{{ $log->reason ?? '—' }}</td>
                                <td style="font-size: 0.75rem; color: var(--text-muted);">{{ $log->created_at?->format('d.m.Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

<script>
    const i18nConfig = '{{ __('Configure:') }}';
    const i18nPay = '{{ __('Payment:') }}';
    const i18nOverride = '{{ __('Overrides & Add-ons:') }}';

    function openOverrideAddonModal(vendor) {
        document.getElementById('overrideAddonModalTitle').innerText = i18nOverride + ' ' + vendor.name;
        document.getElementById('overrideForm').action = '/superadmin/subscriptions/' + vendor.id + '/overrides';
        document.getElementById('addonAssignForm').action = '/superadmin/subscriptions/' + vendor.id + '/addons';
        document.getElementById('overrideAddonModal').style.display = 'flex';
    }

    function openSubModal(vendor) {
        document.getElementById('subModalTitle').innerText = i18nConfig + ' ' + vendor.name;
        document.getElementById('subForm').action = '/superadmin/subscriptions/' + vendor.id;
        document.getElementById('sub_plan_id').value = vendor.subscription_plan_id || '';
        document.getElementById('sub_status').value = vendor.subscription_status || 'active';
        document.getElementById('sub_expires_at').value = vendor.subscription_expires_at ? vendor.subscription_expires_at.split('T')[0] : '';
        document.getElementById('sub_custom_notes').value = vendor.custom_plan_notes || '';

        document.getElementById('subModal').style.display = 'flex';
    }

    function openPaymentModal(vendor) {
        document.getElementById('payModalTitle').innerText = i18nPay + ' ' + vendor.name;
        document.getElementById('payForm').action = '/superadmin/subscriptions/' + vendor.id + '/payments';
        if (vendor.subscription_plan_id) {
            document.getElementById('pay_plan_id').value = vendor.subscription_plan_id;
        }
        if (vendor.plan) {
            document.getElementById('pay_amount').value = vendor.plan.price;
        }

        document.getElementById('paymentModal').style.display = 'flex';
    }

    document.getElementById('pay_plan_id')?.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        if (opt && opt.dataset.price) {
            document.getElementById('pay_amount').value = opt.dataset.price;
        }
    });
</script>
@endsection
