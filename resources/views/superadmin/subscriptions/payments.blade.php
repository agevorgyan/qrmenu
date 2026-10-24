@extends('layouts.app')

@section('title', __('Payment & Invoice History - SuperAdmin'))

@section('content')
<!-- Page Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
            <a href="{{ route('superadmin.subscriptions.index') }}" style="color: var(--text-muted); text-decoration: none; font-size: 0.85rem; font-weight: 600;">
                <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Subscriptions') }}
            </a>
            <span style="color: var(--text-muted);">•</span>
            <span class="badge badge-purple">{{ __('Financial Audit') }}</span>
        </div>
        <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
            {{ __('Payment & Invoice History') }}
        </h1>
    </div>
</div>

<!-- KPI Stats Row -->
<div class="grid-4" style="gap: 1rem; margin-bottom: 1.5rem;">
    <div class="stat-kpi-card" style="padding: 1.15rem 1.25rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">{{ __('Total Invoices') }}</div>
        <div style="font-size: 1.85rem; font-weight: 800; font-family: 'Outfit'; color: var(--text-main); margin-top: 0.2rem;">
            {{ $stats['total_payments'] }}
        </div>
    </div>
    <div class="stat-kpi-card" style="padding: 1.15rem 1.25rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">{{ __('Completed / Paid') }}</div>
        <div style="font-size: 1.85rem; font-weight: 800; font-family: 'Outfit'; color: #10b981; margin-top: 0.2rem;">
            {{ $stats['completed'] }}
        </div>
    </div>
    <div class="stat-kpi-card" style="padding: 1.15rem 1.25rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">{{ __('Failed Payments') }}</div>
        <div style="font-size: 1.85rem; font-weight: 800; font-family: 'Outfit'; color: #ef4444; margin-top: 0.2rem;">
            {{ $stats['failed'] }}
        </div>
    </div>
    <div class="stat-kpi-card" style="padding: 1.15rem 1.25rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">{{ __('Pending / Processing') }}</div>
        <div style="font-size: 1.85rem; font-weight: 800; font-family: 'Outfit'; color: #f59e0b; margin-top: 0.2rem;">
            {{ $stats['pending'] }}
        </div>
    </div>
</div>

<!-- Search & Status Filter Panel -->
<div class="filter-panel" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1.25rem; margin-bottom: 1.5rem;">
    <form method="GET" action="{{ route('superadmin.subscriptions.payments.index') }}" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; justify-content: space-between; margin: 0;">
        <div style="flex: 1; min-width: 250px; position: relative;">
            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.85rem;"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by invoice number, partner name or email...') }}" class="form-input" style="padding-left: 2.5rem; border-radius: 12px; width: 100%;">
        </div>

        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('superadmin.subscriptions.payments.index') }}" class="sub-filter-pill {{ !request('status') ? 'active' : '' }}" style="text-decoration: none;">
                {{ __('All') }}
            </a>
            <a href="{{ route('superadmin.subscriptions.payments.index', ['status' => 'completed']) }}" class="sub-filter-pill {{ request('status') === 'completed' ? 'active' : '' }}" style="text-decoration: none;">
                🟢 {{ __('Completed') }}
            </a>
            <a href="{{ route('superadmin.subscriptions.payments.index', ['status' => 'failed']) }}" class="sub-filter-pill {{ request('status') === 'failed' ? 'active' : '' }}" style="text-decoration: none; color: #ef4444;">
                🔴 {{ __('Failed Review') }}
            </a>
            <a href="{{ route('superadmin.subscriptions.payments.index', ['status' => 'pending']) }}" class="sub-filter-pill {{ request('status') === 'pending' ? 'active' : '' }}" style="text-decoration: none;">
                ⏳ {{ __('Pending') }}
            </a>
            <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-size: 0.82rem; padding: 0.5rem 1rem;">
                {{ __('Filter') }}
            </button>
        </div>
    </form>
</div>

<!-- Payments Table -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 0; overflow: hidden; box-shadow: var(--shadow-card);">
    @if($payments->count() > 0)
        <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="padding: 1rem 1.25rem;">{{ __('Invoice #') }}</th>
                        <th>{{ __('Partner') }}</th>
                        <th>{{ __('Plan') }}</th>
                        <th>{{ __('Amount') }}</th>
                        <th>{{ __('Payment Method') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Period') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th style="text-align: right;">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payments as $p)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 1rem 1.25rem; font-family: 'Outfit'; font-weight: 700; color: var(--text-main);">
                                {{ $p->invoice_number ?? '—' }}
                            </td>
                            <td>
                                <div style="font-weight: 700; color: var(--text-main); font-size: 0.9rem;">
                                    {{ $p->vendor?->name ?? '—' }}
                                </div>
                                <div style="font-size: 0.74rem; color: var(--text-muted);">
                                    {{ $p->vendor?->email ?? '' }}
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-indigo">{{ $p->plan?->name ?? 'Standard' }}</span>
                            </td>
                            <td style="font-weight: 800; font-family: 'Outfit'; color: #10b981; font-size: 0.95rem;">
                                {{ number_format($p->amount, 0, '.', ' ') }} {{ $p->currency }}
                            </td>
                            <td style="font-size: 0.84rem; font-weight: 600;">
                                {{ ucfirst(str_replace('_', ' ', $p->payment_method)) }}
                            </td>
                            <td style="font-size: 0.82rem; color: var(--text-muted); white-space: nowrap;">
                                {{ $p->created_at ? $p->created_at->format('d.m.Y H:i') : '—' }}
                            </td>
                            <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                                {{ $p->period_start ? $p->period_start->format('d.m.Y') : '—' }} - {{ $p->period_end ? $p->period_end->format('d.m.Y') : '—' }}
                            </td>
                            <td>
                                @if(in_array($p->status, ['paid', 'completed']))
                                    <span class="badge badge-emerald">{{ __('Paid') }}</span>
                                @elseif($p->status === 'failed')
                                    <span class="badge badge-rose">{{ __('Failed') }}</span>
                                @else
                                    <span class="badge badge-amber">{{ ucfirst($p->status) }}</span>
                                @endif
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="{{ route('admin.subscription.invoices.show', $p->id) }}" target="_blank" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.35rem 0.65rem; border-radius: 8px;">
                                    <i class="fa-solid fa-file-invoice"></i> {{ __('Invoice') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($payments->hasPages())
            <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--border-color);">
                {{ $payments->links() }}
            </div>
        @endif
    @else
        <div style="text-align: center; padding: 3.5rem 1rem; color: var(--text-muted);">
            <div style="font-size: 1.1rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem;">
                {{ __('No payments found matching the current criteria.') }}
            </div>
        </div>
    @endif
</div>
@endsection
