@extends('layouts.app')

@section('content')
<div class="content-wrapper">

    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-gear" style="color: #6366f1;"></i>
                <span>{{ __('Loyalty Program Settings') }}</span>
            </h1>
            <p style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('Configure calculation ratios, expiration policies, and operational feature toggles.') }}
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

    <form method="POST" action="{{ route('admin.loyalty.settings.update') }}">
        @csrf

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">
            
            <!-- Left: Settings Card -->
            <div class="card" style="padding: 1.75rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-main); margin-bottom: 1.5rem;">
                    {{ __('General Program Configuration') }}
                </h3>

                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <div>
                        <label class="form-label">{{ __('Program Name') }}</label>
                        <input type="text" name="name" value="{{ old('name', $program->name) }}" class="form-control" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label class="form-label">{{ __('Currency Code') }}</label>
                            <input type="text" name="currency" value="{{ old('currency', $program->currency) }}" class="form-control" required maxlength="10">
                            <small style="color: var(--text-muted); font-size: 0.75rem;">e.g. AMD, USD, EUR, RUB</small>
                        </div>
                        <div>
                            <label class="form-label">{{ __('Default Points Ratio') }}</label>
                            <input type="number" step="0.0001" name="default_points_ratio" value="{{ old('default_points_ratio', $program->default_points_ratio) }}" class="form-control" required>
                            <small style="color: var(--text-muted); font-size: 0.75rem;">{{ __('How many points awarded per 1 currency spent') }}</small>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label class="form-label">{{ __('Points Redemption Rate') }}</label>
                            <input type="number" step="0.0001" name="points_redemption_rate" value="{{ old('points_redemption_rate', $program->points_redemption_rate) }}" class="form-control" required>
                            <small style="color: var(--text-muted); font-size: 0.75rem;">{{ __('Currency value of 1 point during discount redemption') }}</small>
                        </div>
                        <div>
                            <label class="form-label">{{ __('Points Expiration (Days)') }}</label>
                            <input type="number" name="points_expiration_days" value="{{ old('points_expiration_days', $program->points_expiration_days) }}" class="form-control" placeholder="{{ __('Leave blank for never') }}">
                            <small style="color: var(--text-muted); font-size: 0.75rem;">{{ __('Days before FIFO lot expires (e.g. 365)') }}</small>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Cashback Expiration (Days)') }}</label>
                        <input type="number" name="cashback_expiration_days" value="{{ old('cashback_expiration_days', $program->cashback_expiration_days) }}" class="form-control" placeholder="{{ __('Leave blank for never') }}">
                    </div>
                </div>

                <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                    <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;">
                        <i class="fa-solid fa-floppy-disk mr-1"></i> {{ __('Save Program Settings') }}
                    </button>
                </div>
            </div>

            <!-- Right: Feature Toggles Card -->
            <div class="card" style="padding: 1.75rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-main); margin-bottom: 1.5rem;">
                    {{ __('Feature Modules') }}
                </h3>

                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <label style="display: flex; items-center; justify-content: space-between; cursor: pointer;">
                        <div>
                            <span style="font-weight: 700; color: var(--text-main); display: block;">{{ __('Master Active State') }}</span>
                            <span style="font-size: 0.75rem; color: var(--text-muted);">Enable loyalty program platform-wide</span>
                        </div>
                        <input type="checkbox" name="is_active" value="1" {{ $program->is_active ? 'checked' : '' }} style="width: 20px; height: 20px;">
                    </label>

                    <label style="display: flex; items-center; justify-content: space-between; cursor: pointer; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                        <div>
                            <span style="font-weight: 700; color: var(--text-main); display: block;">{{ __('Points Earning') }}</span>
                            <span style="font-size: 0.75rem; color: var(--text-muted);">Points on orders & reservations</span>
                        </div>
                        <input type="checkbox" name="points_enabled" value="1" {{ $program->points_enabled ? 'checked' : '' }} style="width: 20px; height: 20px;">
                    </label>

                    <label style="display: flex; items-center; justify-content: space-between; cursor: pointer; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                        <div>
                            <span style="font-weight: 700; color: var(--text-main); display: block;">{{ __('Cashback Ledger') }}</span>
                            <span style="font-size: 0.75rem; color: var(--text-muted);">Currency cashback on orders</span>
                        </div>
                        <input type="checkbox" name="cashback_enabled" value="1" {{ $program->cashback_enabled ? 'checked' : '' }} style="width: 20px; height: 20px;">
                    </label>

                    <label style="display: flex; items-center; justify-content: space-between; cursor: pointer; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                        <div>
                            <span style="font-weight: 700; color: var(--text-main); display: block;">{{ __('Membership Tiers') }}</span>
                            <span style="font-size: 0.75rem; color: var(--text-muted);">Bronze, Silver, Gold, Platinum</span>
                        </div>
                        <input type="checkbox" name="tiers_enabled" value="1" {{ $program->tiers_enabled ? 'checked' : '' }} style="width: 20px; height: 20px;">
                    </label>

                    <label style="display: flex; items-center; justify-content: space-between; cursor: pointer; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                        <div>
                            <span style="font-weight: 700; color: var(--text-main); display: block;">{{ __('Digital Stamp Cards') }}</span>
                            <span style="font-size: 0.75rem; color: var(--text-muted);">Punch card repeat rewards</span>
                        </div>
                        <input type="checkbox" name="stamps_enabled" value="1" {{ $program->stamps_enabled ? 'checked' : '' }} style="width: 20px; height: 20px;">
                    </label>

                    <label style="display: flex; items-center; justify-content: space-between; cursor: pointer; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                        <div>
                            <span style="font-weight: 700; color: var(--text-main); display: block;">{{ __('Referral Program') }}</span>
                            <span style="font-size: 0.75rem; color: var(--text-muted);">Customer invite links & bonuses</span>
                        </div>
                        <input type="checkbox" name="referrals_enabled" value="1" {{ $program->referrals_enabled ? 'checked' : '' }} style="width: 20px; height: 20px;">
                    </label>
                </div>
            </div>

        </div>
    </form>

</div>
@endsection

