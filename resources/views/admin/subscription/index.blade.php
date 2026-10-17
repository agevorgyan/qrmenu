@extends('layouts.app')

@section('title', __('My Subscription') . ' - ' . $vendor->name)

@section('content')
@php
    $plansMap = $allPlans->mapWithKeys(fn($p) => [$p->id => (float) $p->price])->toArray();
@endphp
<div x-data="{
    renewModalOpen: false,
    selectedPlanId: {{ $vendor->subscription_plan_id ?? ($allPlans->first()?->id ?? 1) }},
    periodMonths: 1,
    paymentMethod: 'idram',
    pricesMap: @json($plansMap),
    openRenewModal(planId) {
        this.selectedPlanId = planId;
        this.renewModalOpen = true;
    },
    calculateTotal() {
        const base = this.pricesMap[this.selectedPlanId] || 0;
        let total = base * this.periodMonths;
        if (this.periodMonths == 12) total = total * 0.80;
        else if (this.periodMonths == 6) total = total * 0.90;
        return Number(Math.round(total)).toLocaleString();
    }
}">
<div class="page-header" style="margin-bottom: 2rem;">
    <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
        <span style="background: rgba(245, 158, 11, 0.15); color: var(--primary); width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
            <i class="fa-solid fa-file-invoice-dollar"></i>
        </span>
        <span>{{ __('Subscription & Billing') }}</span>
    </h1>
    <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0; word-break: break-word;">
        {{ __('View your current subscription status, plan features, and billing history') }}
    </p>
</div>

<!-- Subscription Status Overview Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Current Plan Card -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.25rem, 3vw, 1.85rem); position: relative; overflow: hidden; box-shadow: var(--shadow-card); display: flex; flex-direction: column; justify-content: space-between;">
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--primary), #ea580c);"></div>

        <div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem;">
                <div>
                    <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.04em;">{{ __('Current Plan') }}</span>
                    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin: 0.2rem 0 0 0; word-break: break-word;">
                        {{ __($vendor->plan?->name ?? strtoupper($vendor->subscription_plan)) }}
                    </h3>
                </div>
                @if($vendor->isExpired())
                    <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; padding: 0.3rem 0.65rem; font-size: 0.78rem; font-weight: 700;">
                        🔴 {{ __('Expired / Inactive') }}
                    </span>
                @elseif($vendor->isTrialing())
                    <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 8px; padding: 0.3rem 0.65rem; font-size: 0.78rem; font-weight: 700;">
                        ⏳ {{ __('Trial (14 days)') }}
                    </span>
                @else
                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px; padding: 0.3rem 0.65rem; font-size: 0.78rem; font-weight: 700;">
                        🟢 {{ __('Active') }}
                    </span>
                @endif
            </div>

            <div style="font-family: 'Outfit', sans-serif; font-size: 1.5rem; font-weight: 800; color: var(--primary); margin-bottom: 0.75rem;">
                {{ $vendor->plan?->formatted_price ?? '—' }}
            </div>

            <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                @if($vendor->isTrialing())
                    <div>⏱ {{ __('Trial period ends:') }} <strong>{{ $vendor->trial_ends_at ? $vendor->trial_ends_at->format('d.m.Y H:i') : '—' }}</strong></div>
                @else
                    <div>📅 {{ __('Subscription ends:') }} <strong>{{ $vendor->subscription_expires_at ? $vendor->subscription_expires_at->format('d.m.Y') : __('Unlimited') }}</strong></div>
                @endif
            </div>
        </div>

        <div style="background: var(--bg-body); border: 1px solid var(--border-color); padding: 0.85rem 1.15rem; border-radius: 14px; display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">{{ __('Remaining Time:') }}</span>
            <span style="font-family: 'Outfit', sans-serif; font-size: 1.2rem; font-weight: 800; color: {{ $vendor->daysLeft() <= 3 ? '#ef4444' : '#10b981' }};">
                {{ $vendor->daysLeft() }} {{ __('days') }}
            </span>
        </div>
    </div>

    <!-- Online Renewal & Upgrade Card -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.25rem, 3vw, 1.85rem); position: relative; overflow: hidden; box-shadow: var(--shadow-card); display: flex; flex-direction: column; justify-content: space-between;">
        <div>
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0 0 0.5rem 0; display: flex; align-items: center; gap: 0.5rem;">
                <span style="color: #10b981;"><i class="fa-solid fa-credit-card"></i></span>
                <span>{{ __('Online Renewal & Billing') }}</span>
            </h3>
            <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.6; margin: 0; word-break: break-word;">
                {{ __('Renew your subscription directly here with Idram, Telcell, FastShift, ArCa/Ameriabank or Stripe. Save up to 20% on 6-month and 12-month billing periods.') }}
            </p>
        </div>

        <div style="margin-top: 1.5rem; display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <button type="button" 
                    @click="openRenewModal({{ $vendor->subscription_plan_id ?? ($allPlans->first()?->id ?? 1) }})" 
                    class="btn btn-primary" 
                    style="flex: 1; min-width: 200px; justify-content: center; border-radius: 12px; font-weight: 800; padding: 0.85rem 1.5rem; box-shadow: 0 4px 14px var(--primary-glow); display: inline-flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-bolt"></i>
                <span>{{ __('Renew Subscription') }}</span>
            </button>
        </div>
    </div>
</div>

<!-- Included Features Section -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.25rem, 3vw, 1.85rem); margin-bottom: 2rem; box-shadow: var(--shadow-card);">
    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0 0 1.25rem 0; display: flex; align-items: center; gap: 0.5rem;">
        <span style="color: var(--primary);"><i class="fa-solid fa-sparkles"></i></span>
        <span>{{ __('Features Included in Your Plan') }}</span>
    </h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 240px), 1fr)); gap: 0.85rem;">
        @foreach($vendor->plan?->features ?? ['QR Menu', 'Product, Image & Price Management', 'Multilingual Menu', 'QR Code Generator', 'Analytics Dashboard'] as $feat)
            <div style="display: flex; align-items: center; gap: 0.65rem; background: var(--bg-body); border: 1px solid var(--border-color); padding: 0.75rem 1rem; border-radius: 12px; font-size: 0.88rem; color: var(--text-main); font-weight: 600;">
                <i class="fa-solid fa-circle-check" style="color: #10b981; flex-shrink: 0;"></i>
                <span style="word-break: break-word;">{{ __($feat) }}</span>
            </div>
        @endforeach
    </div>
</div>

<!-- Available Plans Comparison -->
<div style="margin-bottom: 2rem;">
    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0 0 1.25rem 0; display: flex; align-items: center; gap: 0.5rem;">
        <span style="color: var(--primary);"><i class="fa-solid fa-list-check"></i></span>
        <span>{{ __('All Subscription Plans') }}</span>
    </h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 260px), 1fr)); gap: 1.25rem;">
        @foreach($allPlans as $plan)
            <div class="card" style="background: var(--bg-card); border: 2px solid {{ $vendor->subscription_plan_id == $plan->id ? 'var(--primary)' : 'var(--border-color)' }}; border-radius: 20px; padding: 1.5rem; position: relative; box-shadow: var(--shadow-card); display: flex; flex-direction: column; justify-content: space-between;">
                @if($vendor->subscription_plan_id == $plan->id)
                    <span style="position: absolute; top: -11px; right: 15px; background: var(--primary); color: #fff; font-size: 0.7rem; font-weight: 800; padding: 0.2rem 0.7rem; border-radius: 9999px; letter-spacing: 0.04em;">
                        {{ __('CURRENT') }}
                    </span>
                @endif
                <div>
                    <h4 style="font-family: 'Outfit', sans-serif; font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0;">{{ __($plan->name) }}</h4>
                    <div style="font-family: 'Outfit', sans-serif; font-size: 1.4rem; font-weight: 800; color: var(--primary); margin: 0.4rem 0;">
                        {{ $plan->formatted_price }}
                    </div>
                    <p style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 1rem; line-height: 1.5; word-break: break-word;">
                        {{ __($plan->description) }}
                    </p>
                    <ul style="list-style: none; padding: 0; margin: 0 0 1.25rem 0; font-size: 0.82rem; color: var(--text-muted); display: flex; flex-direction: column; gap: 0.4rem;">
                        @foreach($plan->features ?? [] as $f)
                            <li style="display: flex; align-items: flex-start; gap: 0.4rem; word-break: break-word;">
                                <i class="fa-solid fa-check" style="color: #10b981; margin-top: 0.2rem; flex-shrink: 0;"></i>
                                <span>{{ __($f) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <button type="button" 
                        @click="openRenewModal({{ $plan->id }})" 
                        class="btn {{ $vendor->subscription_plan_id == $plan->id ? 'btn-primary' : 'btn-secondary' }}" 
                        style="width: 100%; justify-content: center; border-radius: 12px; font-weight: 700; padding: 0.65rem 1rem;">
                    {{ $vendor->subscription_plan_id == $plan->id ? __('Renew this plan') : __('Select & Pay') }}
                </button>
            </div>
        @endforeach
    </div>
</div>

<!-- Interactive Renewal & Payment Modal (Alpine.js) -->
<div x-show="renewModalOpen" x-cloak style="position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); z-index: 999; display: flex; align-items: center; justify-content: center; padding: 1.25rem;">
    <div @click.away="renewModalOpen = false" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 24px; width: 100%; max-width: 540px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35);">
        
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <span style="width: 36px; height: 36px; border-radius: 10px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                    <i class="fa-solid fa-credit-card"></i>
                </span>
                <h3 style="margin: 0; font-family: 'Outfit', sans-serif; font-size: 1.2rem; font-weight: 800; color: var(--text-main);">
                    {{ __('Renew Subscription') }}
                </h3>
            </div>
            <button type="button" @click="renewModalOpen = false" style="background: transparent; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form action="{{ route('admin.subscription.renew') }}" method="POST" style="padding: 1.5rem;">
            @csrf

            <!-- 1. Select Plan -->
            <div style="margin-bottom: 1.25rem;">
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.04em;">
                    {{ __('1. Select Plan') }}
                </label>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 0.6rem;">
                    @foreach($allPlans as $p)
                        <label style="display: flex; flex-direction: column; padding: 0.85rem; border-radius: 12px; border: 2px solid; cursor: pointer; transition: all 0.2s;"
                               :style="selectedPlanId == {{ $p->id }} ? 'border-color: var(--primary); background: rgba(37, 99, 235, 0.05);' : 'border-color: var(--border-color); background: var(--bg-body);'">
                            <input type="radio" name="subscription_plan_id" value="{{ $p->id }}" x-model="selectedPlanId" style="display: none;">
                            <span style="font-weight: 800; font-size: 0.95rem; color: var(--text-main);">{{ __($p->name) }}</span>
                            <span style="font-size: 0.85rem; font-weight: 700; color: var(--primary); margin-top: 0.25rem;">{{ number_format($p->price, 0, '.', ' ') }} {{ $p->currency }}/{{ __('month') }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- 2. Select Duration Period -->
            <div style="margin-bottom: 1.25rem;">
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.04em;">
                    {{ __('2. Select Billing Period') }}
                </label>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem;">
                    <label style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 0.75rem 0.5rem; border-radius: 12px; border: 2px solid; cursor: pointer; text-align: center; transition: all 0.2s;"
                           :style="periodMonths == 1 ? 'border-color: var(--primary); background: rgba(37, 99, 235, 0.05);' : 'border-color: var(--border-color); background: var(--bg-body);'">
                        <input type="radio" name="period_months" value="1" x-model="periodMonths" style="display: none;">
                        <span style="font-weight: 800; font-size: 0.95rem; color: var(--text-main);">{{ __('1 Month') }}</span>
                        <span style="font-size: 0.7rem; color: var(--text-muted); margin-top: 2px;">{{ __('Standard') }}</span>
                    </label>

                    <label style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 0.75rem 0.5rem; border-radius: 12px; border: 2px solid; cursor: pointer; text-align: center; transition: all 0.2s;"
                           :style="periodMonths == 3 ? 'border-color: var(--primary); background: rgba(37, 99, 235, 0.05);' : 'border-color: var(--border-color); background: var(--bg-body);'">
                        <input type="radio" name="period_months" value="3" x-model="periodMonths" style="display: none;">
                        <span style="font-weight: 800; font-size: 0.95rem; color: var(--text-main);">{{ __('3 Months') }}</span>
                        <span style="font-size: 0.7rem; color: var(--text-muted); margin-top: 2px;">{{ __('Quarterly') }}</span>
                    </label>

                    <label style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 0.75rem 0.5rem; border-radius: 12px; border: 2px solid; cursor: pointer; text-align: center; position: relative; transition: all 0.2s;"
                           :style="periodMonths == 6 ? 'border-color: var(--primary); background: rgba(37, 99, 235, 0.05);' : 'border-color: var(--border-color); background: var(--bg-body);'">
                        <input type="radio" name="period_months" value="6" x-model="periodMonths" style="display: none;">
                        <span style="position: absolute; top: -8px; right: 4px; background: #10b981; color: #fff; font-size: 0.62rem; font-weight: 800; padding: 1px 4px; border-radius: 4px;">-10%</span>
                        <span style="font-weight: 800; font-size: 0.95rem; color: var(--text-main);">{{ __('6 Months') }}</span>
                        <span style="font-size: 0.7rem; color: #10b981; font-weight: 700; margin-top: 2px;">{{ __('10% Off') }}</span>
                    </label>

                    <label style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 0.75rem 0.5rem; border-radius: 12px; border: 2px solid; cursor: pointer; text-align: center; position: relative; transition: all 0.2s;"
                           :style="periodMonths == 12 ? 'border-color: var(--primary); background: rgba(37, 99, 235, 0.05);' : 'border-color: var(--border-color); background: var(--bg-body);'">
                        <input type="radio" name="period_months" value="12" x-model="periodMonths" style="display: none;">
                        <span style="position: absolute; top: -8px; right: 4px; background: #ea580c; color: #fff; font-size: 0.62rem; font-weight: 800; padding: 1px 4px; border-radius: 4px;">-20%</span>
                        <span style="font-weight: 800; font-size: 0.95rem; color: var(--text-main);">{{ __('12 Months') }}</span>
                        <span style="font-size: 0.7rem; color: #ea580c; font-weight: 700; margin-top: 2px;">{{ __('20% Off') }}</span>
                    </label>
                </div>
            </div>

            <!-- 3. Select Payment Method -->
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.04em;">
                    {{ __('3. Select Payment Method') }}
                </label>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.6rem;">
                    @php
                        $paymentMethods = [
                            ['code' => 'idram', 'name' => __('Idram Wallet / QR'), 'icon' => 'fa-solid fa-wallet', 'color' => '#f97316'],
                            ['code' => 'telcell', 'name' => __('Telcell Wallet'), 'icon' => 'fa-solid fa-mobile-screen-button', 'color' => '#eab308'],
                            ['code' => 'fastshift', 'name' => __('FastShift'), 'icon' => 'fa-solid fa-bolt', 'color' => '#06b6d4'],
                            ['code' => 'arca', 'name' => __('ArCa / Ameriabank'), 'icon' => 'fa-solid fa-credit-card', 'color' => '#2563eb'],
                            ['code' => 'stripe', 'name' => __('Stripe (Cards)'), 'icon' => 'fa-brands fa-stripe', 'color' => '#6366f1'],
                            ['code' => 'bank_transfer', 'name' => __('Bank Transfer'), 'icon' => 'fa-solid fa-building-columns', 'color' => '#64748b'],
                        ];
                    @endphp
                    @foreach($paymentMethods as $pm)
                        <label style="display: flex; align-items: center; gap: 0.65rem; padding: 0.75rem 0.85rem; border-radius: 12px; border: 2px solid; cursor: pointer; transition: all 0.2s;"
                               :style="paymentMethod == '{{ $pm['code'] }}' ? 'border-color: var(--primary); background: rgba(37, 99, 235, 0.05);' : 'border-color: var(--border-color); background: var(--bg-body);'">
                            <input type="radio" name="payment_method" value="{{ $pm['code'] }}" x-model="paymentMethod" style="display: none;">
                            <span style="width: 28px; height: 28px; border-radius: 8px; background: {{ $pm['color'] }}20; color: {{ $pm['color'] }}; display: inline-flex; align-items: center; justify-content: center; font-size: 0.95rem; flex-shrink: 0;">
                                <i class="{{ $pm['icon'] }}"></i>
                            </span>
                            <span style="font-weight: 700; font-size: 0.84rem; color: var(--text-main);">{{ $pm['name'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Price Breakdown Summary Box -->
            <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 16px; padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.86rem; color: var(--text-muted); margin-bottom: 0.4rem;">
                    <span>{{ __('Selected billing period:') }}</span>
                    <span style="font-weight: 700; color: var(--text-main);" x-text="periodMonths + ' ' + (periodMonths === 1 ? '{{ __('month') }}' : '{{ __('months') }}')"></span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.86rem; color: var(--text-muted); margin-bottom: 0.65rem;">
                    <span>{{ __('Discount:') }}</span>
                    <span style="font-weight: 700; color: #10b981;" x-text="periodMonths == 12 ? '20%' : (periodMonths == 6 ? '10%' : '0%')"></span>
                </div>
                <div style="border-top: 1px dashed var(--border-color); padding-top: 0.65rem; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 800; font-size: 1rem; color: var(--text-main);">{{ __('Total Due:') }}</span>
                    <span style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.35rem; color: var(--primary);" x-text="calculateTotal() + ' ' + '{{ $vendor->currency ?? 'AMD' }}'"></span>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" @click="renewModalOpen = false" class="btn btn-secondary" style="border-radius: 12px; font-weight: 700; padding: 0.75rem 1.25rem;">
                    {{ __('Cancel') }}
                </button>
                <button type="submit" class="btn btn-primary" style="border-radius: 12px; font-weight: 800; padding: 0.75rem 1.75rem; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px var(--primary-glow);">
                    <i class="fa-solid fa-lock"></i>
                    <span>{{ __('Pay & Renew') }}</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Payment History Table -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 0; overflow: hidden; box-shadow: var(--shadow-card);">
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color);">
        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
            <span style="color: var(--primary);"><i class="fa-solid fa-receipt"></i></span>
            <span>{{ __('Payment History') }}</span>
        </h3>
    </div>

    @if($vendor->payments->count() > 0)
        <div class="responsive-table-wrapper">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                <thead>
                    <tr style="background: var(--bg-body); border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em;">
                        <th style="padding: 1rem 1.25rem;">{{ __('Invoice #') }}</th>
                        <th style="padding: 1rem 1.25rem;">{{ __('Date') }}</th>
                        <th style="padding: 1rem 1.25rem;">{{ __('Plan') }}</th>
                        <th style="padding: 1rem 1.25rem;">{{ __('Amount') }}</th>
                        <th style="padding: 1rem 1.25rem;">{{ __('Period') }}</th>
                        <th style="padding: 1rem 1.25rem;">{{ __('Method') }}</th>
                        <th style="padding: 1rem 1.25rem; text-align: right;">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($vendor->payments as $p)
                        <tr style="border-bottom: 1px solid var(--border-color); color: var(--text-main); transition: background 0.15s ease;">
                            <td style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-main); font-family: 'Outfit', sans-serif;">{{ $p->invoice_number ?? '—' }}</td>
                            <td style="padding: 1rem 1.25rem; font-size: 0.85rem; color: var(--text-muted); white-space: nowrap;">{{ $p->created_at->format('d.m.Y H:i') }}</td>
                            <td style="padding: 1rem 1.25rem; font-weight: 600;">{{ $p->plan?->name ?? 'Standard' }}</td>
                            <td style="padding: 1rem 1.25rem; font-weight: 800; color: #10b981; font-family: 'Outfit', sans-serif; white-space: nowrap;">{{ number_format($p->amount, 0, '.', ' ') }} {{ $p->currency }}</td>
                            <td style="padding: 1rem 1.25rem; font-size: 0.82rem; color: var(--text-muted); white-space: nowrap;">
                                {{ $p->period_start ? $p->period_start->format('d.m.Y') : '—' }} - {{ $p->period_end ? $p->period_end->format('d.m.Y') : '—' }}
                            </td>
                            <td style="padding: 1rem 1.25rem; font-size: 0.85rem; font-weight: 600;">{{ ucfirst(str_replace('_', ' ', $p->payment_method)) }}</td>
                            <td style="padding: 1rem 1.25rem; text-align: right; white-space: nowrap;">
                                <span class="badge" style="background: {{ $p->status == 'paid' ? 'rgba(16, 185, 129, 0.15)' : 'rgba(245, 158, 11, 0.15)' }}; color: {{ $p->status == 'paid' ? '#10b981' : '#f59e0b' }}; border: 1px solid {{ $p->status == 'paid' ? 'rgba(16, 185, 129, 0.3)' : 'rgba(245, 158, 11, 0.3)' }}; padding: 0.25rem 0.6rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700;">
                                    {{ ucfirst($p->status) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div style="text-align: center; padding: 3.5rem 1rem; color: var(--text-muted);">
            <div style="width: 56px; height: 56px; border-radius: 16px; background: rgba(245, 158, 11, 0.1); color: var(--primary); display: inline-flex; align-items: center; justify-content: center; font-size: 1.6rem; margin-bottom: 0.85rem;">
                <i class="fa-solid fa-receipt"></i>
            </div>
            <div style="font-size: 1rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem;">{{ __('No payment history available yet') }}</div>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">{{ __('You are currently on the 14-day free trial period.') }}</p>
        </div>
    @endif
</div>
</div>
@endsection
