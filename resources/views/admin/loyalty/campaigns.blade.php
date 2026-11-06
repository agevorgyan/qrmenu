@extends('layouts.app')

@section('content')
<div class="content-wrapper">

    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-bullhorn" style="color: #8b5cf6;"></i>
                <span>{{ __('Automated Marketing Campaigns') }}</span>
            </h1>
            <p style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('Drive retention and win-backs with event-triggered bonus points, birthday treats, and welcome incentives.') }}
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="{{ route('admin.loyalty.index') }}" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left mr-1"></i> {{ __('Overview') }}
            </a>
            <button type="button" class="btn btn-primary" onclick="openCreateCampaignModal()">
                <i class="fa-solid fa-plus mr-1"></i> {{ __('Create Campaign') }}
            </button>
        </div>
    </div>

    @if(session('success'))
        <div style="padding: 1rem; border-radius: 12px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; font-size: 0.875rem; margin-bottom: 1.5rem;">
            <i class="fa-solid fa-circle-check mr-1"></i> {{ session('success') }}
        </div>
    @endif

    <!-- Campaigns Grid -->
    @if($campaigns->isEmpty())
        <div class="card" style="padding: 3rem 1.5rem; text-align: center; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px;">
            <i class="fa-solid fa-bullhorn" style="font-size: 3rem; color: var(--text-muted); opacity: 0.3; margin-bottom: 1rem; display: block;"></i>
            <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.5rem;">{{ __('No Campaigns Active') }}</h3>
            <p style="color: var(--text-muted); font-size: 0.875rem; max-width: 450px; margin: 0 auto 1.5rem auto;">
                {{ __('Automate customer engagement by creating a Welcome bonus for new signups or an automated Birthday gift.') }}
            </p>
            <button type="button" class="btn btn-primary" onclick="openCreateCampaignModal()">
                <i class="fa-solid fa-plus mr-1"></i> {{ __('Launch First Campaign') }}
            </button>
        </div>
    @else
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.5rem;">
            @foreach($campaigns as $camp)
                <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1.5rem; display: flex; flex-direction: column;">
                    
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                        <div>
                            <span class="badge" style="background: rgba(139, 92, 246, 0.15); color: #a78bfa; text-transform: uppercase; font-size: 0.7rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 6px; display: inline-block; margin-bottom: 0.4rem;">
                                {{ str_replace('_', ' ', $camp->trigger_type->value) }}
                            </span>
                            <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-main); margin: 0;">{{ $camp->name }}</h3>
                        </div>

                        <form method="POST" action="{{ route('admin.loyalty.campaigns.toggle', $camp) }}">
                            @csrf
                            <button type="submit" style="background: none; border: none; cursor: pointer;">
                                @if($camp->is_active)
                                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #34d399; font-weight: 600; padding: 0.3rem 0.6rem; border-radius: 20px;">
                                        <i class="fa-solid fa-play mr-1"></i> {{ __('Active') }}
                                    </span>
                                @else
                                    <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #f87171; font-weight: 600; padding: 0.3rem 0.6rem; border-radius: 20px;">
                                        <i class="fa-solid fa-pause mr-1"></i> {{ __('Paused') }}
                                    </span>
                                @endif
                            </button>
                        </form>
                    </div>

                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem; flex: 1;">
                        {{ $camp->description ?: __('Triggers automatically based on customer events and inactivity rules.') }}
                    </p>

                    <!-- Reward Badge & Stats -->
                    <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 12px; padding: 1rem; margin-bottom: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">{{ __('Incentive Reward') }}</span>
                            <span style="font-size: 0.9rem; font-weight: 700; color: #10b981;">
                                @if($camp->reward_type === 'points')
                                    +{{ number_format($camp->reward_value) }} pts
                                @elseif($camp->reward_type === 'cashback')
                                    +{{ number_format($camp->reward_value, 2) }} {{ $vendor->currency }}
                                @else
                                    {{ $camp->reward?->title ?: __('Reward Voucher') }}
                                @endif
                            </span>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; color: var(--text-muted);">
                            <span>{{ __('Times Triggered:') }}</span>
                            <span style="font-weight: 700; color: var(--text-main);">{{ number_format($camp->events_count) }} {{ __('customers') }}</span>
                        </div>
                    </div>

                    <div style="border-top: 1px solid var(--border-color); padding-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.75rem; color: var(--text-muted);">
                            @if($camp->starts_at || $camp->ends_at)
                                <i class="fa-solid fa-calendar mr-1"></i> {{ $camp->starts_at?->format('M d') }} - {{ $camp->ends_at?->format('M d, Y') ?: 'Ongoing' }}
                            @else
                                <i class="fa-solid fa-infinity mr-1"></i> {{ __('Continuous Event') }}
                            @endif
                        </span>

                        <form method="POST" action="{{ route('admin.loyalty.campaigns.destroy', $camp) }}" onsubmit="return confirm('{{ __('Delete this campaign?') }}')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 8px; padding: 0.35rem 0.75rem;">
                                <i class="fa-solid fa-trash mr-1"></i> {{ __('Delete') }}
                            </button>
                        </form>
                    </div>

                </div>
            @endforeach
        </div>

        <div style="margin-top: 2rem;">
            {{ $campaigns->links() }}
        </div>
    @endif

    <!-- Create Campaign Modal -->
    <div id="createCampaignModal" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(4px); align-items: center; justify-content: center;">
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; width: 100%; max-width: 580px; padding: 2rem; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin: 0;">
                    <i class="fa-solid fa-bullhorn text-primary mr-1"></i> {{ __('Create Marketing Campaign') }}
                </h3>
                <button type="button" onclick="closeCreateCampaignModal()" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('admin.loyalty.campaigns.store') }}">
                @csrf
                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <div>
                        <label class="form-label">{{ __('Campaign Name') }}</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Birthday Double Treat or 30-Day Win-Back" required>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Description') }}</label>
                        <textarea name="description" rows="2" class="form-control" placeholder="{{ __('Short notes or customer message...') }}"></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label class="form-label">{{ __('Trigger Condition') }}</label>
                            <select name="trigger_type" class="form-control" required id="campTriggerSelect" onchange="toggleCampTriggerFields()">
                                <option value="welcome">{{ __('Welcome Sign-up') }}</option>
                                <option value="birthday">{{ __('Customer Birthday') }}</option>
                                <option value="first_order">{{ __('First Completed Order') }}</option>
                                <option value="inactive_customer">{{ __('Inactive Win-Back') }}</option>
                                <option value="seasonal">{{ __('Seasonal / Holiday') }}</option>
                                <option value="custom">{{ __('Custom Scheduled') }}</option>
                            </select>
                        </div>
                        <div id="daysInactiveWrapper" style="display: none;">
                            <label class="form-label">{{ __('Days of Inactivity') }}</label>
                            <input type="number" name="days_inactive" class="form-control" placeholder="e.g. 30" min="1">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label class="form-label">{{ __('Reward Incentive Type') }}</label>
                            <select name="reward_type" class="form-control" required id="campRewardTypeSelect" onchange="toggleCampRewardFields()">
                                <option value="points">{{ __('Bonus Points') }}</option>
                                <option value="cashback">{{ __('Direct Cashback') }}</option>
                                <option value="reward_voucher">{{ __('Voucher / Free Product') }}</option>
                            </select>
                        </div>
                        <div id="rewardValueWrapper">
                            <label class="form-label">{{ __('Reward Amount (pts or curr)') }}</label>
                            <input type="number" step="0.01" name="reward_value" value="100" class="form-control" required min="0">
                        </div>
                    </div>

                    <div id="catalogRewardSelectWrapper" style="display: none;">
                        <label class="form-label">{{ __('Linked Catalog Reward') }}</label>
                        <select name="reward_id" class="form-control">
                            <option value="">-- {{ __('Select Catalog Item') }} --</option>
                            @foreach($rewards as $rew)
                                <option value="{{ $rew->id }}">{{ $rew->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label class="form-label">{{ __('Start Date (Optional)') }}</label>
                            <input type="date" name="starts_at" class="form-control">
                        </div>
                        <div>
                            <label class="form-label">{{ __('End Date (Optional)') }}</label>
                            <input type="date" name="ends_at" class="form-control">
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1rem;">
                        <button type="button" onclick="closeCreateCampaignModal()" class="btn btn-secondary">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-save mr-1"></i> {{ __('Save & Launch') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function openCreateCampaignModal() {
    document.getElementById('createCampaignModal').style.display = 'flex';
}
function closeCreateCampaignModal() {
    document.getElementById('createCampaignModal').style.display = 'none';
}
function toggleCampTriggerFields() {
    const trigger = document.getElementById('campTriggerSelect').value;
    const daysWrap = document.getElementById('daysInactiveWrapper');
    daysWrap.style.display = (trigger === 'inactive_customer') ? 'block' : 'none';
}
function toggleCampRewardFields() {
    const rewType = document.getElementById('campRewardTypeSelect').value;
    const catWrap = document.getElementById('catalogRewardSelectWrapper');
    const valWrap = document.getElementById('rewardValueWrapper');

    if (rewType === 'reward_voucher') {
        catWrap.style.display = 'block';
        valWrap.style.display = 'none';
    } else {
        catWrap.style.display = 'none';
        valWrap.style.display = 'block';
    }
}
</script>
@endsection

