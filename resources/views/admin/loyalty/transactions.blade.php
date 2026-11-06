@extends('layouts.app')

@section('content')
<div class="content-wrapper">

    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-book-bookmark" style="color: #6366f1;"></i>
                <span>{{ __('Loyalty Ledger Transactions') }}</span>
            </h1>
            <p style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('Immutable double-entry audit history of all point and cashback issuances, redemptions, and expirations.') }}
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="{{ route('admin.loyalty.transactions.export', ['ledger' => $ledger]) }}" class="btn btn-secondary">
                <i class="fa-solid fa-file-csv mr-1"></i> {{ __('Export CSV') }}
            </a>
            <a href="{{ route('admin.loyalty.index') }}" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left mr-1"></i> {{ __('Overview') }}
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card" style="padding: 1.25rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; margin-bottom: 1.5rem;">
        <form method="GET" action="{{ route('admin.loyalty.transactions') }}" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
            
            <div style="min-width: 180px;">
                <label class="form-label" style="font-size: 0.75rem;">{{ __('Ledger Account') }}</label>
                <select name="ledger" class="form-control" onchange="this.form.submit()">
                    <option value="points" {{ $ledger === 'points' ? 'selected' : '' }}>{{ __('Points Ledger') }}</option>
                    <option value="cashback" {{ $ledger === 'cashback' ? 'selected' : '' }}>{{ __('Cashback Ledger') }}</option>
                </select>
            </div>

            <div style="min-width: 200px;">
                <label class="form-label" style="font-size: 0.75rem;">{{ __('Transaction Type') }}</label>
                <select name="type" class="form-control" onchange="this.form.submit()">
                    <option value="all">{{ __('All Transaction Types') }}</option>
                    <option value="earned_order" {{ $type === 'earned_order' ? 'selected' : '' }}>{{ __('Earned on Order') }}</option>
                    <option value="redeemed" {{ $type === 'redeemed' ? 'selected' : '' }}>{{ __('Redeemed') }}</option>
                    <option value="bonus_campaign" {{ $type === 'bonus_campaign' ? 'selected' : '' }}>{{ __('Campaign Bonus') }}</option>
                    <option value="referral_bonus" {{ $type === 'referral_bonus' ? 'selected' : '' }}>{{ __('Referral Bonus') }}</option>
                    <option value="expired" {{ $type === 'expired' ? 'selected' : '' }}>{{ __('Expired') }}</option>
                    <option value="refund_reversal" {{ $type === 'refund_reversal' ? 'selected' : '' }}>{{ __('Refund Reversal') }}</option>
                    <option value="manual_adjustment" {{ $type === 'manual_adjustment' ? 'selected' : '' }}>{{ __('Manual Staff Adjustment') }}</option>
                </select>
            </div>

            <div style="margin-left: auto;">
                <a href="{{ route('admin.loyalty.transactions') }}" class="btn btn-sm btn-secondary">
                    <i class="fa-solid fa-rotate-left mr-1"></i> {{ __('Reset Filters') }}
                </a>
            </div>

        </form>
    </div>

    <!-- Ledger Table -->
    <div class="card" style="padding: 1.5rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px;">
        @if($transactions->isEmpty())
            <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                <i class="fa-solid fa-receipt" style="font-size: 2.5rem; opacity: 0.3; margin-bottom: 0.75rem; display: block;"></i>
                <p style="margin: 0;">{{ __('No transactions found matching your criteria.') }}</p>
            </div>
        @else
            <div class="table-responsive" style="overflow-x: auto;">
                <table class="table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-color); text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                            <th style="padding: 0.75rem 1rem;">{{ __('Tx ID') }}</th>
                            <th style="padding: 0.75rem 1rem;">{{ __('Date & Time') }}</th>
                            <th style="padding: 0.75rem 1rem;">{{ __('Customer') }}</th>
                            <th style="padding: 0.75rem 1rem;">{{ __('Type') }}</th>
                            <th style="padding: 0.75rem 1rem; text-align: right;">{{ __('Amount / Change') }}</th>
                            <th style="padding: 0.75rem 1rem; text-align: right;">{{ __('Before') }}</th>
                            <th style="padding: 0.75rem 1rem; text-align: right;">{{ __('After') }}</th>
                            <th style="padding: 0.75rem 1rem;">{{ __('Notes / Reference') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $tx)
                            <tr style="border-bottom: 1px solid var(--border-color); font-size: 0.875rem;">
                                <td style="padding: 0.85rem 1rem; font-family: monospace; color: var(--text-muted); font-size: 0.75rem;">
                                    #{{ $tx->id }}
                                </td>
                                <td style="padding: 0.85rem 1rem; color: var(--text-muted); font-size: 0.8rem; white-space: nowrap;">
                                    {{ $tx->created_at->format('M d, Y H:i') }}
                                </td>
                                <td style="padding: 0.85rem 1rem;">
                                    @if($tx->customer)
                                        <div style="font-weight: 600; color: var(--text-main);">
                                            {{ $tx->customer->name ?: __('Guest Member') }}
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">
                                            {{ $tx->customer->phone }}
                                        </div>
                                    @else
                                        <span style="color: var(--text-muted);">{{ __('Anonymous') }}</span>
                                    @endif
                                </td>
                                <td style="padding: 0.85rem 1rem;">
                                    <span class="badge" style="background: rgba(99, 102, 241, 0.1); color: #818cf8; text-transform: capitalize; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 6px;">
                                        {{ str_replace('_', ' ', $tx->type->value) }}
                                    </span>
                                </td>
                                
                                @php
                                    $isDebit = in_array($tx->type->value, ['redeemed', 'expired', 'refund_reversal']) || ($ledger === 'points' && isset($tx->points) && $tx->points < 0);
                                    $amountVal = $ledger === 'points' ? $tx->points : $tx->amount;
                                @endphp

                                <td style="padding: 0.85rem 1rem; text-align: right; font-weight: 800; font-size: 0.95rem; color: {{ $isDebit ? '#ef4444' : '#10b981' }}; white-space: nowrap;">
                                    {{ $isDebit ? '-' : '+' }}
                                    @if($ledger === 'points')
                                        {{ number_format(abs($amountVal)) }} pts
                                    @else
                                        {{ number_format(abs($amountVal), 2) }} {{ $vendor->currency }}
                                    @endif
                                </td>
                                <td style="padding: 0.85rem 1rem; text-align: right; color: var(--text-muted); font-size: 0.8rem;">
                                    {{ $ledger === 'points' ? number_format($tx->balance_before) : number_format($tx->balance_before, 2) }}
                                </td>
                                <td style="padding: 0.85rem 1rem; text-align: right; font-weight: 600; color: var(--text-main); font-size: 0.85rem;">
                                    {{ $ledger === 'points' ? number_format($tx->balance_after) : number_format($tx->balance_after, 2) }}
                                </td>
                                <td style="padding: 0.85rem 1rem; font-size: 0.8rem; color: var(--text-muted); max-width: 250px;">
                                    @if(isset($tx->order) && $tx->order)
                                        <span class="badge" style="background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-size: 0.7rem; padding: 0.15rem 0.4rem; border-radius: 4px; margin-right: 0.3rem;">
                                            Order #{{ $tx->order->order_number ?? $tx->order->id }}
                                        </span>
                                    @endif
                                    {{ $tx->notes }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 1.5rem;">
                {{ $transactions->appends(request()->query())->links() }}
            </div>
        @endif
    </div>

</div>
@endsection

