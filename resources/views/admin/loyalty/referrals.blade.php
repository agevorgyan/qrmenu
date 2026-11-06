@extends('layouts.app')

@section('content')
<div class="content-wrapper">

    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-users-viewfinder" style="color: #3b82f6;"></i>
                <span>{{ __('Customer Referral Program') }}</span>
            </h1>
            <p style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('Reward existing patrons for inviting friends and automate reward distribution upon qualified first orders.') }}
            </p>
        </div>
        <div>
            <a href="{{ route('admin.loyalty.index') }}" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left mr-1"></i> {{ __('Overview') }}
            </a>
        </div>
    </div>

    @if(session('success'))
        <div style="padding: 1rem; border-radius: 12px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; font-size: 0.875rem; margin-bottom: 1.5rem;">
            <i class="fa-solid fa-circle-check mr-1"></i> {{ session('success') }}
        </div>
    @endif

    <!-- Referral Stats KPIs -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
        <div class="card" style="padding: 1.25rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px;">
            <span style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">{{ __('Total Invitations') }}</span>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-top: 0.25rem;">
                {{ number_format($totalReferrals) }}
            </div>
        </div>

        <div class="card" style="padding: 1.25rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px;">
            <span style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">{{ __('Qualified Conversions') }}</span>
            <div style="font-size: 1.75rem; font-weight: 800; color: #10b981; margin-top: 0.25rem;">
                {{ number_format($qualifiedReferrals) }}
            </div>
        </div>

        <div class="card" style="padding: 1.25rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px;">
            <span style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">{{ __('Conversion Rate') }}</span>
            <div style="font-size: 1.75rem; font-weight: 800; color: #3b82f6; margin-top: 0.25rem;">
                {{ $totalReferrals > 0 ? number_format(($qualifiedReferrals / $totalReferrals) * 100, 1) : 0 }}%
            </div>
        </div>

        <div class="card" style="padding: 1.25rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px;">
            <span style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">{{ __('Total Points Awarded') }}</span>
            <div style="font-size: 1.75rem; font-weight: 800; color: #f59e0b; margin-top: 0.25rem;">
                {{ number_format($totalReferralPoints) }} pts
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem; align-items: start;">
        
        <!-- Left: Referral Reward Rules Configuration -->
        <div class="card" style="padding: 1.5rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px;">
            <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin-bottom: 1.25rem;">
                <i class="fa-solid fa-sliders text-primary mr-1"></i> {{ __('Referral Reward Settings') }}
            </h3>

            @php
                $refSettings = $program->settings['referral'] ?? ['referrer_points' => 200, 'referred_points' => 100, 'min_order_spend' => 10.0];
            @endphp

            <form method="POST" action="{{ route('admin.loyalty.referrals.settings') }}">
                @csrf
                @method('PUT')

                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <div>
                        <label class="form-label">{{ __('Referrer Bonus (Points to inviter)') }}</label>
                        <input type="number" name="referrer_points" value="{{ old('referrer_points', $refSettings['referrer_points'] ?? 200) }}" class="form-control" required min="0">
                        <small style="color: var(--text-muted); font-size: 0.75rem;">{{ __('Awarded after referee completes qualifying order.') }}</small>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Referee Bonus (Points to new customer)') }}</label>
                        <input type="number" name="referred_points" value="{{ old('referred_points', $refSettings['referred_points'] ?? 100) }}" class="form-control" required min="0">
                        <small style="color: var(--text-muted); font-size: 0.75rem;">{{ __('Credited immediately or upon qualifying checkout.') }}</small>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Min Spend for Order Qualification') }}</label>
                        <input type="number" step="0.01" name="min_order_spend" value="{{ old('min_order_spend', $refSettings['min_order_spend'] ?? 10.0) }}" class="form-control" required min="0">
                        <small style="color: var(--text-muted); font-size: 0.75rem;">{{ __('Prevents fraudulent low-value order triggers.') }}</small>
                    </div>

                    <button type="submit" class="btn btn-primary" style="margin-top: 0.5rem;">
                        <i class="fa-solid fa-save mr-1"></i> {{ __('Save Reward Rules') }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Right: Referrals Log Table -->
        <div class="card" style="padding: 1.5rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px;">
            <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin-bottom: 1.25rem;">
                <i class="fa-solid fa-list-check text-primary mr-1"></i> {{ __('Recent Referral Activity') }}
            </h3>

            @if($referrals->isEmpty())
                <div style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted);">
                    <i class="fa-solid fa-user-plus" style="font-size: 2.5rem; opacity: 0.3; margin-bottom: 0.75rem; display: block;"></i>
                    <p style="margin: 0;">{{ __('No referrals tracked yet. Patrons can share their unique invite links via their customer portal.') }}</p>
                </div>
            @else
                <div class="table-responsive" style="overflow-x: auto;">
                    <table class="table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--border-color); text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                                <th style="padding: 0.75rem;">{{ __('Date') }}</th>
                                <th style="padding: 0.75rem;">{{ __('Referrer') }}</th>
                                <th style="padding: 0.75rem;">{{ __('Referred Customer') }}</th>
                                <th style="padding: 0.75rem;">{{ __('Code') }}</th>
                                <th style="padding: 0.75rem;">{{ __('Status') }}</th>
                                <th style="padding: 0.75rem; text-align: right;">{{ __('Bonus Awarded') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($referrals as $ref)
                                <tr style="border-bottom: 1px solid var(--border-color); font-size: 0.85rem;">
                                    <td style="padding: 0.75rem; color: var(--text-muted);">
                                        {{ $ref->created_at->format('M d, Y') }}
                                    </td>
                                    <td style="padding: 0.75rem; font-weight: 600; color: var(--text-main);">
                                        {{ $ref->referrer?->name ?: __('Guest') }}
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $ref->referrer?->phone }}</div>
                                    </td>
                                    <td style="padding: 0.75rem; font-weight: 600; color: var(--text-main);">
                                        {{ $ref->referred?->name ?: __('Invited User') }}
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $ref->referred?->phone }}</div>
                                    </td>
                                    <td style="padding: 0.75rem;">
                                        <code style="background: var(--bg-main); padding: 0.2rem 0.4rem; border-radius: 4px; font-size: 0.75rem;">
                                            {{ $ref->referral_code }}
                                        </code>
                                    </td>
                                    <td style="padding: 0.75rem;">
                                        @if($ref->status === 'qualified')
                                            <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #34d399; font-weight: 600; padding: 0.2rem 0.5rem; border-radius: 20px;">
                                                <i class="fa-solid fa-check mr-1"></i> {{ __('Qualified') }}
                                            </span>
                                        @elseif($ref->status === 'pending')
                                            <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24; font-weight: 600; padding: 0.2rem 0.5rem; border-radius: 20px;">
                                                <i class="fa-solid fa-clock mr-1"></i> {{ __('Pending') }}
                                            </span>
                                        @else
                                            <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #f87171; font-weight: 600; padding: 0.2rem 0.5rem; border-radius: 20px;">
                                                {{ ucfirst($ref->status) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td style="padding: 0.75rem; text-align: right; font-weight: 700; color: #10b981;">
                                        +{{ number_format($ref->referrer_points_awarded) }} pts
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 1.5rem;">
                    {{ $referrals->links() }}
                </div>
            @endif
        </div>

    </div>

</div>
@endsection

