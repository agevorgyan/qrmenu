@extends('layouts.app')

@section('title', 'Customer History - ' . ($customer->name ?? 'Guest'))

@section('styles')
<style>
    .timeline {
        position: relative;
        padding-left: 2.5rem;
        margin-top: 1.5rem;
    }
    .timeline::before {
        content: '';
        position: absolute;
        left: 0.95rem;
        top: 0.5rem;
        bottom: 0.5rem;
        width: 3px;
        background: var(--border-color);
        border-radius: 9999px;
    }
    .timeline-item {
        position: relative;
        margin-bottom: 2rem;
    }
    .timeline-icon {
        position: absolute;
        left: -2.5rem;
        top: 0.2rem;
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), #d97706);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.88rem;
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.35);
        border: 2px solid var(--bg-card);
    }
    @media (max-width: 640px) {
        .timeline {
            padding-left: 1.85rem;
        }
        .timeline::before {
            left: 0.6rem;
        }
        .timeline-icon {
            left: -1.85rem;
            width: 28px;
            height: 28px;
            font-size: 0.75rem;
        }
    }
</style>
@endsection

@section('content')
<div style="margin-bottom: 1.5rem;">
    <a href="{{ route('admin.customers.index') }}" class="btn btn-secondary" style="font-size: 0.88rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px;">
        <i class="fa-solid fa-arrow-left"></i> Back to Customers List
    </a>
</div>

<!-- Customer Profile Header Card -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.25rem, 3vw, 1.85rem); margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1.25rem;">
        <div style="display: flex; gap: 1.25rem; align-items: center; min-width: 0; flex: 1;">
            <div style="width: clamp(54px, 8vw, 70px); height: clamp(54px, 8vw, 70px); border-radius: 20px; background: rgba(79, 70, 229, 0.15); color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 1.85rem; flex-shrink: 0;">
                <i class="fa-solid fa-user-gear"></i>
            </div>
            <div style="min-width: 0;">
                <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.3rem, 2.5vw, 1.75rem); font-weight: 800; color: var(--text-main); margin: 0; word-break: break-word;">
                    {{ $customer->name ?? __('Guest') }}
                </h1>
                <div style="display: flex; gap: 0.85rem; color: var(--text-muted); font-size: 0.85rem; margin-top: 0.4rem; flex-wrap: wrap; align-items: center;">
                    @if($customer->phone)
                        <span style="display: inline-flex; align-items: center; gap: 0.35rem;"><i class="fa-solid fa-phone" style="color: var(--primary);"></i> {{ $customer->phone }}</span>
                    @endif
                    @if($customer->email)
                        <span style="display: inline-flex; align-items: center; gap: 0.35rem;"><i class="fa-solid fa-envelope" style="color: var(--primary);"></i> {{ $customer->email }}</span>
                    @endif
                    @if($customer->birthdate)
                        <span style="color: #ec4899; font-weight: 600; display: inline-flex; align-items: center; gap: 0.35rem;">
                            <i class="fa-solid fa-cake-candles"></i> {{ $customer->birthdate->format('d M Y') }} ({{ $customer->birthdate->age }} {{ __('yrs') }})
                        </span>
                    @endif
                    @if($customer->address)
                        <span style="display: inline-flex; align-items: center; gap: 0.35rem;"><i class="fa-solid fa-map-pin" style="color: #f59e0b;"></i> {{ $customer->address }}</span>
                    @endif
                    <span style="display: inline-flex; align-items: center; gap: 0.35rem;"><i class="fa-solid fa-calendar-day"></i> {{ __('Registered') }}: {{ $customer->created_at->format('M Y') }}</span>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 0.65rem; align-items: center; flex-wrap: wrap;">
            <span style="background: rgba(79, 70, 229, 0.15); color: #6366f1; border: 1px solid rgba(79, 70, 229, 0.3); padding: 0.4rem 0.85rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                <i class="fa-solid fa-location-dot"></i> {{ $customer->location?->name ?? __('All Locations') }}
            </span>
            @if($customer->marketing_opt_in)
                <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); padding: 0.4rem 0.85rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                    <i class="fa-solid fa-check-double"></i> {{ __('Marketing Subscribed') }}
                </span>
            @else
                <span style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); padding: 0.4rem 0.85rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                    <i class="fa-solid fa-xmark"></i> {{ __('Disabled') }}
                </span>
            @endif
        </div>
    </div>

    @if($customer->notes)
        <div style="margin-top: 1.25rem; padding: 0.85rem 1.1rem; background: var(--bg-body); border-left: 4px solid var(--primary); border-radius: 10px; font-size: 0.88rem; color: var(--text-main); word-break: break-word;">
            <i class="fa-solid fa-note-sticky" style="color: var(--primary); margin-right: 0.45rem;"></i> <strong>{{ __('Notes') }}:</strong> {{ $customer->notes }}
        </div>
    @endif
</div>

<!-- Key Performance Stat Cards -->
<div class="grid-4" style="margin-bottom: 2rem;">
    <div class="card kpi-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1.2rem; text-align: center; box-shadow: var(--shadow-card);">
        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.04em;">{{ __('Total Orders') }}</div>
        <div style="font-family: 'Outfit', sans-serif; font-size: 1.65rem; font-weight: 800; color: var(--text-main); margin-top: 0.2rem;">{{ $customer->total_orders_count }}</div>
    </div>
    <div class="card kpi-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1.2rem; text-align: center; box-shadow: var(--shadow-card);">
        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.04em;">{{ __('Total Spent (LTV)') }}</div>
        <div style="font-family: 'Outfit', sans-serif; font-size: 1.65rem; font-weight: 800; color: #10b981; margin-top: 0.2rem;">{{ number_format($customer->total_spent) }} {{ $vendor->currency }}</div>
    </div>
    <div class="card kpi-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1.2rem; text-align: center; box-shadow: var(--shadow-card);">
        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.04em;">{{ __('Average Order Value (AOV)') }}</div>
        <div style="font-family: 'Outfit', sans-serif; font-size: 1.65rem; font-weight: 800; color: #f59e0b; margin-top: 0.2rem;">
            {{ $customer->total_orders_count > 0 ? number_format($customer->total_spent / $customer->total_orders_count) : 0 }} {{ $vendor->currency }}
        </div>
    </div>
    <div class="card kpi-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1.2rem; text-align: center; box-shadow: var(--shadow-card);">
        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.04em;">{{ __('Last Activity') }}</div>
        <div style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin-top: 0.35rem;">
            {{ $customer->last_order_at ? $customer->last_order_at->diffForHumans() : __('Never') }}
        </div>
    </div>
</div>

</div>

<!-- Loyalty & Digital Wallet CRM Card -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.25rem, 3vw, 1.85rem); margin-bottom: 2rem; box-shadow: var(--shadow-card);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1.25rem;">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(99, 102, 241, 0.15); color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                <i class="fa-solid fa-crown"></i>
            </div>
            <div>
                <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <span>{{ __('Loyalty & Digital Wallet Profile') }}</span>
                    @php
                        $tierName = $customer->loyalty_tier ?? 'Bronze';
                        $tierColor = match(strtolower($tierName)) {
                            'platinum' => '#6366f1',
                            'gold' => '#eab308',
                            'silver' => '#94a3b8',
                            default => '#cd7f32'
                        };
                    @endphp
                    <span class="badge" style="background: {{ $tierColor }}; color: #fff; font-weight: 700; font-size: 0.75rem; padding: 0.25rem 0.6rem; border-radius: 12px;">
                        <i class="fa-solid fa-crown mr-1"></i> {{ __($tierName) }} {{ __('Tier') }}
                    </span>
                </h2>
                <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0.2rem 0 0 0;">
                    {{ __('Real-time balance, membership tier, stamp cards, and Apple/Google Wallet pass telemetry.') }}
                </p>
            </div>
        </div>

        <div style="display: flex; gap: 0.6rem; align-items: center;">
            <button type="button" class="btn btn-secondary" onclick="openCustomerAdjustModal()" style="font-size: 0.82rem; font-weight: 600; padding: 0.5rem 0.85rem; border-radius: 10px;">
                <i class="fa-solid fa-scale-balanced mr-1"></i> {{ __('Adjust Balance') }}
            </button>
            <a href="{{ route('admin.loyalty.staff', ['search' => $customer->phone ?: $customer->id]) }}" class="btn btn-primary" style="font-size: 0.82rem; font-weight: 600; padding: 0.5rem 0.85rem; border-radius: 10px;">
                <i class="fa-solid fa-qrcode mr-1"></i> {{ __('Staff Scanner') }}
            </a>
        </div>
    </div>

    <!-- Loyalty KPIs -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        
        <!-- Points Balance -->
        <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.1rem;">
            <span style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); display: block;">
                {{ __('Points Balance') }}
            </span>
            <div style="font-family: 'Outfit', sans-serif; font-size: 1.75rem; font-weight: 900; color: #6366f1; margin: 0.2rem 0;">
                {{ number_format($customer->pointsAccount?->current_balance ?? $customer->loyalty_points) }} <span style="font-size: 0.85rem; font-weight: 600;">{{ __('pts') }}</span>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted);">
                {{ __('Lifetime earned:') }} {{ number_format($customer->pointsAccount?->lifetime_earned ?? $customer->loyalty_points) }} {{ __('pts') }}
            </div>
        </div>

        <!-- Cashback Balance -->
        <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.1rem;">
            <span style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); display: block;">
                {{ __('Cashback Balance') }}
            </span>
            <div style="font-family: 'Outfit', sans-serif; font-size: 1.75rem; font-weight: 900; color: #10b981; margin: 0.2rem 0;">
                {{ number_format($customer->cashbackAccount?->current_balance ?? 0.00, 2) }} <span style="font-size: 0.85rem; font-weight: 600;">{{ $vendor->currency }}</span>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted);">
                {{ __('Lifetime earned:') }} {{ number_format($customer->cashbackAccount?->lifetime_earned ?? 0.00, 2) }} {{ $vendor->currency }}
            </div>
        </div>

        <!-- Wallet Passes Status -->
        <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.1rem;">
            <span style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); display: block;">
                {{ __('Digital Wallet Status') }}
            </span>
            <div style="display: flex; gap: 0.75rem; align-items: center; margin-top: 0.6rem;">
                @php
                    $applePass = $walletPasses?->first();
                @endphp
                @if($applePass)
                    <span class="badge" style="background: #000; color: #fff; font-weight: 700; font-size: 0.75rem; padding: 0.3rem 0.6rem; border-radius: 8px;">
                        <i class="fa-brands fa-apple mr-1"></i> {{ __('Apple Pass') }}
                    </span>
                    <span class="badge" style="background: #4285f4; color: #fff; font-weight: 700; font-size: 0.75rem; padding: 0.3rem 0.6rem; border-radius: 8px;">
                        <i class="fa-brands fa-google mr-1"></i> {{ __('Google Pass') }}
                    </span>
                @else
                    <span style="font-size: 0.85rem; color: var(--text-muted); font-style: italic;">
                        {{ __('No pass issued yet') }}
                    </span>
                @endif
            </div>
            @if($applePass)
                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.4rem; font-family: monospace;">
                    {{ $applePass->serial_number }}
                </div>
            @endif
        </div>

        <!-- Active Stamp Card Summary -->
        <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.1rem;">
            <span style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); display: block;">
                {{ __('Stamp Card Progress') }}
            </span>
            @php
                $activeCard = $stampCards?->firstWhere('status', \App\Domains\Loyalty\Enums\StampStatus::Active);
            @endphp
            @if($activeCard)
                <div style="font-family: 'Outfit', sans-serif; font-size: 1.35rem; font-weight: 800; color: #06b6d4; margin: 0.2rem 0;">
                    {{ $activeCard->stamps_collected }} / {{ $activeCard->stamps_required }} <span style="font-size: 0.8rem; font-weight: 600;">{{ __('stamps') }}</span>
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">
                    {{ $activeCard->program?->name }}
                </div>
            @else
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.5rem; font-style: italic;">
                    {{ __('No active punch card') }}
                </div>
            @endif
        </div>

    </div>

    <!-- Recent Loyalty Ledger Activity -->
    @if(!empty($loyaltyTransactions) && $loyaltyTransactions->count() > 0)
        <div style="border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); text-transform: uppercase; letter-spacing: 0.03em;">
                    <i class="fa-solid fa-list-check mr-1 text-primary"></i> {{ __('Recent Loyalty Transactions') }}
                </span>
                <a href="{{ route('admin.loyalty.transactions', ['search' => $customer->phone ?: $customer->id]) }}" style="font-size: 0.75rem; color: var(--primary); text-decoration: none; font-weight: 600;">
                    {{ __('View Full Ledger') }} &rarr;
                </a>
            </div>

            <div class="responsive-table-wrapper">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.82rem;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-color); text-align: left; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase;">
                            <th style="padding: 0.5rem 0.75rem;">{{ __('Date') }}</th>
                            <th style="padding: 0.5rem 0.75rem;">{{ __('Type') }}</th>
                            <th style="padding: 0.5rem 0.75rem; text-align: right;">{{ __('Points Change') }}</th>
                            <th style="padding: 0.5rem 0.75rem; text-align: right;">{{ __('New Balance') }}</th>
                            <th style="padding: 0.5rem 0.75rem;">{{ __('Notes') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($loyaltyTransactions->take(5) as $ltx)
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 0.5rem 0.75rem; color: var(--text-muted);">{{ $ltx->created_at->format('M d, H:i') }}</td>
                                <td style="padding: 0.5rem 0.75rem;">
                                    <span class="badge" style="background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); font-size: 0.7rem; padding: 0.15rem 0.4rem; border-radius: 4px;">
                                        {{ str_replace('_', ' ', $ltx->type->value) }}
                                    </span>
                                </td>
                                <td style="padding: 0.5rem 0.75rem; text-align: right; font-weight: 700; color: {{ $ltx->points >= 0 ? '#10b981' : '#ef4444' }};">
                                    {{ $ltx->points >= 0 ? '+' : '' }}{{ number_format($ltx->points) }} {{ __('pts') }}
                                </td>
                                <td style="padding: 0.5rem 0.75rem; text-align: right; font-weight: 600;">{{ number_format($ltx->balance_after) }}</td>
                                <td style="padding: 0.5rem 0.75rem; color: var(--text-muted);">{{ $ltx->notes }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

<!-- Modal: Adjust Balance Direct from CRM Profile -->
<div id="customerAdjustModal" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(4px); align-items: center; justify-content: center;">
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; width: 100%; max-width: 480px; padding: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-main); margin: 0;">
                <i class="fa-solid fa-scale-balanced text-primary mr-1"></i> {{ __('Adjust Customer Balance') }}
            </h3>
            <button type="button" onclick="closeCustomerAdjustModal()" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('admin.loyalty.adjust_balance') }}">
            @csrf
            <input type="hidden" name="customer_id" value="{{ $customer->id }}">

            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <div>
                    <label class="form-label">{{ __('Account Type') }}</label>
                    <select name="type" class="form-control" required>
                        <option value="points">{{ __('Loyalty Points') }}</option>
                        <option value="cashback">{{ __('Cashback Currency') }} ({{ $vendor->currency }})</option>
                    </select>
                </div>

                <div>
                    <label class="form-label">{{ __('Adjustment Amount (Use negative value to deduct)') }}</label>
                    <input type="number" step="0.01" name="amount" class="form-control" placeholder="e.g. 100 or -50" required>
                </div>

                <div>
                    <label class="form-label">{{ __('Audit Reason / Notes') }}</label>
                    <input type="text" name="notes" class="form-control" placeholder="{{ __('e.g. Loyalty compensation or offline spend') }}" required maxlength="255">
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 0.5rem;">
                    <button type="button" onclick="closeCustomerAdjustModal()" class="btn btn-secondary">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-check mr-1"></i> {{ __('Submit Adjustment') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function openCustomerAdjustModal() {
    document.getElementById('customerAdjustModal').style.display = 'flex';
}
function closeCustomerAdjustModal() {
    document.getElementById('customerAdjustModal').style.display = 'none';
}
</script>
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.25rem, 3vw, 1.85rem); box-shadow: var(--shadow-card);">
    <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.3rem; font-weight: 800; color: var(--text-main); margin: 0 0 1.25rem 0; display: flex; align-items: center; gap: 0.6rem;">
        <span style="color: var(--primary);"><i class="fa-solid fa-clock-rotate-left"></i></span>
        <span>{{ __('Orders Timeline') }}</span>
    </h2>

    @if($orders->count() > 0)
        <div class="timeline">
            @foreach($orders as $order)
                <div class="timeline-item">
                    <div class="timeline-icon">
                        <i class="fa-solid fa-receipt"></i>
                    </div>

                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 16px; padding: clamp(1rem, 2.5vw, 1.35rem); box-shadow: 0 4px 14px rgba(0,0,0,0.06);">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: gap: 0.6rem; margin-bottom: 0.75rem;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0;">{{ $order->order_number }}</h3>
                                    <span style="background: {{ $order->status == 'completed' ? 'rgba(16,185,129,0.15)' : 'rgba(245,158,11,0.15)' }}; color: {{ $order->status == 'completed' ? '#10b981' : '#f59e0b' }}; border: 1px solid {{ $order->status == 'completed' ? 'rgba(16,185,129,0.3)' : 'rgba(245,158,11,0.3)' }}; padding: 0.15rem 0.55rem; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                                        {{ $order->status }}
                                    </span>
                                </div>
                                <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.25rem; display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
                                    <span><i class="fa-solid fa-calendar"></i> {{ $order->created_at->format('d M Y, H:i') }} ({{ $order->created_at->diffForHumans() }})</span>
                                    <span>•</span>
                                    <span><i class="fa-solid fa-location-dot"></i> {{ $order->location?->name ?? __('Main Branch') }}</span>
                                    @if($order->table_number)
                                        <span>•</span>
                                        <span style="font-weight: 700; color: var(--primary);">{{ __('Table') }}: {{ $order->table_number }}</span>
                                    @endif
                                </div>
                            </div>

                            <div style="font-family: 'Outfit', sans-serif; font-size: 1.3rem; font-weight: 800; color: #10b981; white-space: nowrap;">
                                {{ number_format($order->total_amount) }} {{ $vendor->currency }}
                            </div>
                        </div>

                        <!-- Ordered Items List -->
                        <div style="display: flex; flex-direction: column; gap: 0.45rem; margin-top: 0.75rem; border-top: 1px dashed var(--border-color); padding-top: 0.75rem;">
                            @foreach($order->items as $item)
                                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.86rem; color: var(--text-main); flex-wrap: wrap; gap: 0.35rem;">
                                    <span style="word-break: break-word;">
                                        <strong style="color: var(--primary);">{{ $item->quantity }}x</strong> {{ $item->product_name }}
                                        @if($item->variation_name)
                                            <small style="color: var(--text-muted);">({{ $item->variation_name }})</small>
                                        @endif
                                    </span>
                                    <span style="font-weight: 700; font-family: 'Outfit', sans-serif;">{{ number_format($item->subtotal) }} {{ $vendor->currency }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div style="margin-top: 1.25rem;">
            {{ $orders->links() }}
        </div>
    @else
        <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
            <div style="width: 54px; height: 54px; border-radius: 14px; background: rgba(245, 158, 11, 0.1); color: var(--primary); display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 0.75rem;">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <p style="margin: 0; font-size: 0.95rem; font-weight: 600;">{{ __('No orders recorded for this customer yet.') }}</p>
        </div>
    @endif
</div>

<!-- Table Reservations History -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.25rem, 3vw, 1.85rem); margin-top: 1.5rem; box-shadow: var(--shadow-card);">
    <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.3rem; font-weight: 800; color: var(--text-main); margin: 0 0 1.25rem 0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
        <span style="display: flex; align-items: center; gap: 0.6rem;">
            <span style="color: #3b82f6;"><i class="fa-solid fa-calendar-check"></i></span>
            <span>{{ __('Reservations History') }}</span>
        </span>
        <span style="font-size: 0.85rem; font-weight: 700; background: rgba(59, 130, 246, 0.12); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.25); padding: 0.25rem 0.75rem; border-radius: 9999px;">
            {{ isset($reservations) ? $reservations->count() : 0 }} {{ __('Total') }}
        </span>
    </h2>

    @if(isset($reservations) && $reservations->count() > 0)
        <div class="timeline">
            @foreach($reservations as $res)
                <div class="timeline-item">
                    <div class="timeline-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.35);">
                        <i class="fa-solid fa-calendar-day"></i>
                    </div>

                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 16px; padding: clamp(1rem, 2.5vw, 1.35rem); box-shadow: 0 4px 14px rgba(0,0,0,0.06);">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.6rem; margin-bottom: 0.75rem;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0;">#{{ $res->reference }}</h3>
                                    @php
                                        $badgeBg = match($res->status) {
                                            'confirmed' => 'rgba(16,185,129,0.15)',
                                            'cancelled', 'rejected' => 'rgba(239,68,68,0.15)',
                                            'seated' => 'rgba(59,130,246,0.15)',
                                            'completed' => 'rgba(107,114,128,0.15)',
                                            default => 'rgba(245,158,11,0.15)',
                                        };
                                        $badgeColor = match($res->status) {
                                            'confirmed' => '#10b981',
                                            'cancelled', 'rejected' => '#ef4444',
                                            'seated' => '#3b82f6',
                                            'completed' => '#6b7280',
                                            default => '#f59e0b',
                                        };
                                    @endphp
                                    <span style="background: {{ $badgeBg }}; color: {{ $badgeColor }}; border: 1px solid {{ $badgeColor }}40; padding: 0.15rem 0.55rem; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                                        {{ $res->status }}
                                    </span>
                                    @if($res->source)
                                        <span style="background: rgba(99, 102, 241, 0.1); color: #6366f1; border: 1px solid rgba(99, 102, 241, 0.25); padding: 0.12rem 0.5rem; border-radius: 6px; font-size: 0.72rem; font-weight: 600;">
                                            {{ ucfirst($res->source) }}
                                        </span>
                                    @endif
                                </div>
                                <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.25rem; display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
                                    <span><i class="fa-solid fa-clock"></i> {{ $res->getFormattedDate() }} &bull; {{ $res->getFormattedTime() }}</span>
                                    <span>•</span>
                                    <span><i class="fa-solid fa-user-group"></i> {{ $res->guest_count }} {{ __('Guests') }}</span>
                                    <span>•</span>
                                    <span><i class="fa-solid fa-chair"></i> {{ $res->table_name ?: ($res->table_number ? 'Table #' . $res->table_number : __('Unassigned')) }}</span>
                                    @if($res->location)
                                        <span>•</span>
                                        <span><i class="fa-solid fa-location-dot"></i> {{ $res->location->name }}</span>
                                    @endif
                                </div>
                            </div>

                            @if($res->preOrder)
                                <div style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 800; color: #10b981; white-space: nowrap;">
                                    {{ number_format((float) $res->preOrder->total_amount) }} {{ $vendor->currency }}
                                </div>
                            @endif
                        </div>

                        @if($res->special_requests)
                            <div style="font-size: 0.82rem; color: var(--text-main); background: var(--bg-card); border-left: 3px solid #3b82f6; padding: 0.45rem 0.75rem; border-radius: 6px; margin-top: 0.6rem;">
                                <strong>{{ __('Special Requests') }}:</strong> {{ $res->special_requests }}
                            </div>
                        @endif

                        @if($res->preOrder && !empty($res->preOrder->items))
                            <div style="display: flex; flex-direction: column; gap: 0.35rem; margin-top: 0.65rem; border-top: 1px dashed var(--border-color); padding-top: 0.65rem;">
                                <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                                    {{ __('Pre-Ordered Menu Items') }}:
                                </div>
                                @foreach($res->preOrder->items as $item)
                                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.84rem; color: var(--text-main);">
                                        <span>
                                            <strong style="color: var(--primary);">{{ $item['quantity'] ?? 1 }}x</strong> {{ $item['name'] ?? 'Dish' }}
                                            @if(!empty($item['variation_name']))
                                                <small style="color: var(--text-muted);">({{ $item['variation_name'] }})</small>
                                            @endif
                                        </span>
                                        <span style="font-weight: 700; font-family: 'Outfit', sans-serif;">
                                            {{ number_format((float) ($item['subtotal'] ?? 0)) }} {{ $vendor->currency }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted);">
            <div style="width: 50px; height: 50px; border-radius: 14px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; display: inline-flex; align-items: center; justify-content: center; font-size: 1.4rem; margin-bottom: 0.75rem;">
                <i class="fa-solid fa-calendar-xmark"></i>
            </div>
            <p style="margin: 0; font-size: 0.95rem; font-weight: 600;">{{ __('No reservations recorded for this customer yet.') }}</p>
        </div>
    @endif
</div>

<!-- Event Planning Inquiries History -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.25rem, 3vw, 1.85rem); margin-top: 1.5rem; box-shadow: var(--shadow-card);">
    <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.3rem; font-weight: 800; color: var(--text-main); margin: 0 0 1.25rem 0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
        <span style="display: flex; align-items: center; gap: 0.6rem;">
            <span style="color: #f59e0b;"><i class="fa-solid fa-champagne-glasses"></i></span>
            <span>{{ __('Event Planning Inquiries') }}</span>
        </span>
        <span style="font-size: 0.85rem; font-weight: 700; background: rgba(245, 158, 11, 0.12); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.25); padding: 0.25rem 0.75rem; border-radius: 9999px;">
            {{ isset($eventInquiries) ? $eventInquiries->count() : 0 }} {{ __('Total') }}
        </span>
    </h2>

    @if(isset($eventInquiries) && $eventInquiries->count() > 0)
        <div class="timeline">
            @foreach($eventInquiries as $inq)
                <div class="timeline-item">
                    <div class="timeline-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706); box-shadow: 0 4px 12px rgba(245, 158, 11, 0.35);">
                        <i class="fa-solid fa-cake-candles"></i>
                    </div>

                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 16px; padding: clamp(1rem, 2.5vw, 1.35rem); box-shadow: 0 4px 14px rgba(0,0,0,0.06);">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.6rem; margin-bottom: 0.75rem;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                    <a href="{{ route('admin.event_inquiries.show', $inq->id) }}" style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 800; color: var(--primary); text-decoration: none;">
                                        #{{ $inq->reference }}
                                    </a>
                                    @php
                                        $inqBg = match($inq->status) {
                                            'confirmed', 'completed' => 'rgba(16,185,129,0.15)',
                                            'cancelled', 'declined' => 'rgba(239,68,68,0.15)',
                                            'offer_sent', 'negotiation' => 'rgba(59,130,246,0.15)',
                                            default => 'rgba(245,158,11,0.15)',
                                        };
                                        $inqColor = match($inq->status) {
                                            'confirmed', 'completed' => '#10b981',
                                            'cancelled', 'declined' => '#ef4444',
                                            'offer_sent', 'negotiation' => '#3b82f6',
                                            default => '#f59e0b',
                                        };
                                    @endphp
                                    <span style="background: {{ $inqBg }}; color: {{ $inqColor }}; border: 1px solid {{ $inqColor }}40; padding: 0.15rem 0.55rem; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                                        {{ $inq->status }}
                                    </span>
                                    <span style="background: rgba(99, 102, 241, 0.1); color: #6366f1; border: 1px solid rgba(99, 102, 241, 0.25); padding: 0.12rem 0.5rem; border-radius: 6px; font-size: 0.72rem; font-weight: 600;">
                                        {{ ucfirst(str_replace('_', ' ', $inq->event_type)) }}
                                    </span>
                                </div>
                                <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.25rem; display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
                                    <span><i class="fa-solid fa-calendar-day"></i> {{ \Carbon\Carbon::parse($inq->event_date)->format('d M Y') }}</span>
                                    @if($inq->start_time)
                                        <span>•</span>
                                        <span><i class="fa-solid fa-clock"></i> {{ $inq->start_time }}</span>
                                    @endif
                                    <span>•</span>
                                    <span><i class="fa-solid fa-user-group"></i> {{ $inq->guest_count }} {{ __('Guests') }}</span>
                                    @if($inq->location)
                                        <span>•</span>
                                        <span><i class="fa-solid fa-location-dot"></i> {{ $inq->location->name }}</span>
                                    @endif
                                    @if($inq->budget)
                                        <span>•</span>
                                        <span style="font-weight: 700; color: #10b981;">{{ number_format((float) $inq->budget) }} {{ $inq->budget_currency }}</span>
                                    @endif
                                </div>
                            </div>

                            <a href="{{ route('admin.event_inquiries.show', $inq->id) }}" class="btn btn-sm btn-secondary" style="font-size: 0.8rem; font-weight: 600; border-radius: 8px;">
                                {{ __('View Inquiry') }} &rarr;
                            </a>
                        </div>

                        @if($inq->comments || $inq->food_beverage_preferences)
                            <div style="margin-top: 0.65rem; padding: 0.6rem 0.85rem; background: var(--bg-card); border-radius: 8px; font-size: 0.82rem; color: var(--text-muted); line-height: 1.4;">
                                @if($inq->food_beverage_preferences)
                                    <div><strong>{{ __('Preferences') }}:</strong> {{ $inq->food_beverage_preferences }}</div>
                                @endif
                                @if($inq->comments)
                                    <div><strong>{{ __('Notes') }}:</strong> {{ $inq->comments }}</div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted);">
            <div style="width: 50px; height: 50px; border-radius: 14px; background: rgba(245, 158, 11, 0.1); color: #f59e0b; display: inline-flex; align-items: center; justify-content: center; font-size: 1.4rem; margin-bottom: 0.75rem;">
                <i class="fa-solid fa-champagne-glasses"></i>
            </div>
            <p style="margin: 0; font-size: 0.95rem; font-weight: 600;">{{ __('No event inquiries recorded for this customer yet.') }}</p>
        </div>
    @endif
</div>
@endsection
