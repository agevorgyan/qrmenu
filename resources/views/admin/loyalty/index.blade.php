@extends('layouts.app')

@section('content')
<div class="content-wrapper">

    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-gift" style="color: #ec4899;"></i>
                <span>{{ __('Loyalty & Digital Wallet Management') }}</span>
            </h1>
            <p style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('Enterprise customer loyalty programs, Apple & Google Wallet passes, points, cashback, and automated rewards.') }}
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="{{ route('admin.loyalty.staff') }}" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-qrcode"></i>
                <span>{{ __('Staff Scanner') }}</span>
            </a>
            <a href="{{ route('admin.loyalty.designer') }}" class="btn btn-secondary" style="display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-palette"></i>
                <span>{{ __('Card Designer') }}</span>
            </a>
            <a href="{{ route('admin.loyalty.settings') }}" class="btn btn-secondary" style="display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-gear"></i>
                <span>{{ __('Settings') }}</span>
            </a>
        </div>
    </div>

    <!-- KPI Metric Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
        
        <!-- Total Members -->
        <div class="card" style="padding: 1.25rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Total Members') }}</span>
                <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(99, 102, 241, 0.1); display: flex; align-items: center; justify-content: center; color: #6366f1;">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
            <div style="font-size: 1.75rem; font-weight: 900; color: var(--text-main);">{{ number_format($totalMembers) }}</div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                <span style="color: #10b981; font-weight: 700;">{{ number_format($activeMembers) }}</span> {{ __('active in last 30d') }}
            </div>
        </div>

        <!-- Outstanding Points Liability -->
        <div class="card" style="padding: 1.25rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Points Outstanding') }}</span>
                <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(236, 72, 153, 0.1); display: flex; align-items: center; justify-content: center; color: #ec4899;">
                    <i class="fa-solid fa-coins"></i>
                </div>
            </div>
            <div style="font-size: 1.75rem; font-weight: 900; color: #ec4899;">{{ number_format($outstandingPoints) }}</div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('Issued: ') }}{{ number_format($totalPointsIssued) }} | {{ __('Redeemed: ') }}{{ number_format($totalPointsRedeemed) }}
            </div>
        </div>

        <!-- Outstanding Cashback -->
        <div class="card" style="padding: 1.25rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Cashback Balance') }}</span>
                <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(16, 185, 129, 0.1); display: flex; align-items: center; justify-content: center; color: #10b981;">
                    <i class="fa-solid fa-money-bill-wave"></i>
                </div>
            </div>
            <div style="font-size: 1.75rem; font-weight: 900; color: #10b981;">{{ number_format($outstandingCashback, 2) }} <span style="font-size: 1rem; font-weight: 600;">{{ $program->currency }}</span></div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">{{ __('Real-time customer liabilities') }}</div>
        </div>

        <!-- Loyalty Associated Revenue -->
        <div class="card" style="padding: 1.25rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Loyalty Sales') }}</span>
                <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(245, 158, 11, 0.1); display: flex; align-items: center; justify-content: center; color: #f59e0b;">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
            </div>
            <div style="font-size: 1.75rem; font-weight: 900; color: var(--text-main);">{{ number_format($loyaltyRevenue, 2) }} <span style="font-size: 1rem; font-weight: 600;">{{ $program->currency }}</span></div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">{{ __('Revenue from member orders') }}</div>
        </div>

    </div>

    <!-- Main Grid: Tier Distribution & Recent Transactions -->
    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem; align-items: start;">
        
        <!-- Tier Distribution Card -->
        <div class="card" style="padding: 1.5rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px;">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-main); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-layer-group" style="color: #6366f1;"></i>
                <span>{{ __('Membership Tiers') }}</span>
            </h3>

            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                @foreach(['Platinum' => '#6366f1', 'Gold' => '#eab308', 'Silver' => '#94a3b8', 'Bronze' => '#cd7f32'] as $tierName => $color)
                    @php $count = $tierDistribution[$tierName] ?? 0; @endphp
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; border-radius: 12px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color);">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 10px; height: 10px; border-radius: 50%; background: {{ $color }};"></div>
                            <span style="font-weight: 700; font-size: 0.875rem; color: var(--text-main);">{{ $tierName }}</span>
                        </div>
                        <span style="font-weight: 800; font-size: 0.875rem; color: var(--text-main);">{{ number_format($count) }}</span>
                    </div>
                @endforeach
            </div>

            <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--border-color); text-align: center;">
                <a href="{{ route('admin.loyalty.tiers') }}" class="btn btn-secondary btn-sm" style="width: 100%;">
                    {{ __('Manage Tiers & Rules') }}
                </a>
            </div>
        </div>

        <!-- Recent Transactions Card -->
        <div class="card" style="padding: 1.5rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-clock-rotate-left" style="color: #ec4899;"></i>
                    <span>{{ __('Recent Ledger Activity') }}</span>
                </h3>
                <a href="{{ route('admin.loyalty.transactions') }}" style="font-size: 0.8rem; color: #6366f1; font-weight: 700; text-decoration: none;">
                    {{ __('View All') }} &rarr;
                </a>
            </div>

            <div class="table-responsive">
                <table class="table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-color); font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); text-align: left;">
                            <th style="padding: 0.75rem 0.5rem;">{{ __('Date') }}</th>
                            <th style="padding: 0.75rem 0.5rem;">{{ __('Member') }}</th>
                            <th style="padding: 0.75rem 0.5rem;">{{ __('Type') }}</th>
                            <th style="padding: 0.75rem 0.5rem; text-align: right;">{{ __('Points') }}</th>
                            <th style="padding: 0.75rem 0.5rem; text-align: right;">{{ __('Balance') }}</th>
                        </tr>
                    </thead>
                    <tbody style="font-size: 0.85rem;">
                        @forelse($recentTransactions as $tx)
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.03);">
                                <td style="padding: 0.75rem 0.5rem; color: var(--text-muted);">{{ $tx->created_at->format('M d, H:i') }}</td>
                                <td style="padding: 0.75rem 0.5rem; font-weight: 600; color: var(--text-main);">
                                    {{ $tx->customer?->name ?? 'Guest' }}
                                </td>
                                <td style="padding: 0.75rem 0.5rem;">
                                    <span class="badge" style="background: rgba(99,102,241,0.1); color: #818cf8; font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 6px;">
                                        {{ $tx->type->label() }}
                                    </span>
                                </td>
                                <td style="padding: 0.75rem 0.5rem; text-align: right; font-weight: 800; color: {{ $tx->points >= 0 ? '#10b981' : '#f43f5e' }};">
                                    {{ $tx->points >= 0 ? '+' : '' }}{{ $tx->points }}
                                </td>
                                <td style="padding: 0.75rem 0.5rem; text-align: right; font-weight: 700; color: var(--text-main);">
                                    {{ number_format($tx->balance_after) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                    {{ __('No loyalty transactions recorded yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>
@endsection

