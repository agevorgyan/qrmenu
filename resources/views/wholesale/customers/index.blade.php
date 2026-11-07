@extends('layouts.app')

@section('title', __('B2B Customers Directory'))

@section('content')
<div class="content-wrapper">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6366f1;">
                    {{ __('B2B Wholesale Subsystem') }}
                </span>
            </div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
                {{ __('B2B Customer Directory') }}
            </h1>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
                {{ __('Manage corporate clients, tax identification numbers (TIN), credit limits, and delivery locations.') }}
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <a href="{{ route('admin.wholesale.customers.create') }}" 
               style="padding: 0.65rem 1.25rem; border-radius: 10px; background: #6366f1; color: #fff; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);">
                <i class="fa-solid fa-user-plus"></i>
                <span>{{ __('New B2B Customer') }}</span>
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
        <form method="GET" action="{{ route('admin.wholesale.customers.index') }}" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 220px;">
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search customer by name, TIN, phone, or email...') }}"
                       style="width: 100%; padding: 0.55rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.88rem;">
            </div>

            <div style="min-width: 170px;">
                <select name="tax_regime" style="width: 100%; padding: 0.55rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.88rem;">
                    <option value="">{{ __('All Tax Regimes') }}</option>
                    <option value="vat_payer" {{ ($filters['tax_regime'] ?? '') === 'vat_payer' ? 'selected' : '' }}>{{ __('VAT Payer (ԱԱՀ)') }}</option>
                    <option value="turnover_tax" {{ ($filters['tax_regime'] ?? '') === 'turnover_tax' ? 'selected' : '' }}>{{ __('Turnover Tax (Շրջհարկ)') }}</option>
                    <option value="micro_business" {{ ($filters['tax_regime'] ?? '') === 'micro_business' ? 'selected' : '' }}>{{ __('Micro-business') }}</option>
                    <option value="individual" {{ ($filters['tax_regime'] ?? '') === 'individual' ? 'selected' : '' }}>{{ __('Individual / Non-commercial') }}</option>
                </select>
            </div>

            <button type="submit" style="padding: 0.55rem 1rem; border-radius: 8px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-weight: 700; cursor: pointer;">
                <i class="fa-solid fa-filter"></i> {{ __('Filter') }}
            </button>
            @if(!empty($filters))
                <a href="{{ route('admin.wholesale.customers.index') }}" style="color: var(--text-muted); font-size: 0.85rem; text-decoration: underline;">
                    {{ __('Clear') }}
                </a>
            @endif
        </form>
    </div>

    <!-- Table -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.02);">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
            <thead>
                <tr style="border-bottom: 1px solid var(--border-color); background: var(--bg-main); text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">
                    <th style="padding: 0.85rem 1.25rem;">{{ __('Customer / Legal Entity') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('TIN (ՀՎՀՀ)') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Tax Regime') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Credit Limit / Terms') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('AR Balance') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Status') }}</th>
                    <th style="padding: 0.85rem 1.25rem; text-align: right;">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($customers as $c)
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 1rem 1.25rem;">
                            <div style="font-weight: 700; color: var(--text-main);">
                                <a href="{{ route('admin.wholesale.customers.show', $c) }}" style="color: inherit; text-decoration: none;">
                                    {{ $c->name }}
                                </a>
                            </div>
                            @if($c->legal_name && $c->legal_name !== $c->name)
                                <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $c->legal_name }}</div>
                            @endif
                        </td>
                        <td style="padding: 1rem 1rem; font-family: monospace; font-weight: 700;">
                            {{ $c->tin ?? '—' }}
                        </td>
                        <td style="padding: 1rem 1rem;">
                            @if($c->tax_regime === 'vat_payer')
                                <span style="font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 6px; background: rgba(99, 102, 241, 0.1); color: #6366f1;">
                                    {{ __('VAT Payer') }}
                                </span>
                            @else
                                <span style="font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 6px; background: rgba(245, 158, 11, 0.1); color: #d97706;">
                                    {{ ucfirst(str_replace('_', ' ', $c->tax_regime)) }}
                                </span>
                            @endif
                        </td>
                        <td style="padding: 1rem 1rem;">
                            @if($c->is_credit_allowed)
                                <div style="font-weight: 700; color: var(--text-main);">
                                    {{ (float)$c->credit_limit > 0 ? number_format($c->credit_limit, 0) . ' AMD' : __('Unlimited') }}
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                    {{ $c->payment_term_days > 0 ? __(':days days net', ['days' => $c->payment_term_days]) : __('Immediate') }}
                                </div>
                            @else
                                <span style="font-size: 0.78rem; color: var(--text-muted);">{{ __('Prepayment / COD') }}</span>
                            @endif
                        </td>
                        <td style="padding: 1rem 1rem;">
                            <span style="font-weight: 700; color: {{ (float)$c->current_balance > 0 ? '#ef4444' : 'var(--text-main)' }};">
                                {{ number_format($c->current_balance, 2) }} AMD
                            </span>
                        </td>
                        <td style="padding: 1rem 1rem;">
                            @if($c->is_active)
                                <span style="font-size: 0.75rem; font-weight: 700; color: #10b981;">{{ __('Active') }}</span>
                            @else
                                <span style="font-size: 0.75rem; font-weight: 700; color: #ef4444;">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td style="padding: 1rem 1.25rem; text-align: right;">
                            <a href="{{ route('admin.wholesale.customers.show', $c) }}" 
                               style="padding: 0.4rem 0.75rem; border-radius: 8px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-size: 0.82rem; font-weight: 600; text-decoration: none; margin-right: 0.35rem;">
                                {{ __('Details') }}
                            </a>
                            <a href="{{ route('admin.wholesale.customers.edit', $c) }}" 
                               style="padding: 0.4rem 0.75rem; border-radius: 8px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-size: 0.82rem; font-weight: 600; text-decoration: none;">
                                {{ __('Edit') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding: 3rem 1rem; text-align: center; color: var(--text-muted);">
                            <i class="fa-solid fa-users-slash" style="font-size: 2rem; margin-bottom: 0.75rem; color: var(--border-color); display: block;"></i>
                            {{ __('No B2B customers found. Add your first wholesale client to start issuing orders.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 1.5rem;">
        {{ $customers->links() }}
    </div>
</div>
@endsection

