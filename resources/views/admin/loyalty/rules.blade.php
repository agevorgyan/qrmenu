@extends('layouts.app')

@section('content')
<div class="content-wrapper">

    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-calculator" style="color: #6366f1;"></i>
                <span>{{ __('Earning Rules Engine') }}</span>
            </h1>
            <p style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('Define advanced earning mechanics for Points and Cashback across spend tiers and conditions.') }}
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="{{ route('admin.loyalty.index') }}" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left mr-1"></i> {{ __('Overview') }}
            </a>
            <button type="button" class="btn btn-primary" onclick="openCreateRuleModal()">
                <i class="fa-solid fa-plus mr-1"></i> {{ __('Create Earning Rule') }}
            </button>
        </div>
    </div>

    @if(session('success'))
        <div style="padding: 1rem; border-radius: 12px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; font-size: 0.875rem; margin-bottom: 1.5rem;">
            <i class="fa-solid fa-circle-check mr-1"></i> {{ session('success') }}
        </div>
    @endif

    <!-- Rules Sections -->
    <div style="display: flex; flex-direction: column; gap: 2rem;">

        <!-- Section 1: Points Earning Rules -->
        <div class="card" style="padding: 1.5rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <div>
                    <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #6366f1;"></span>
                        {{ __('Points Earning Rules') }}
                    </h3>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0.25rem 0 0 0;">
                        {{ __('Evaluated in priority order. Higher priority rules take precedence.') }}
                    </p>
                </div>
                <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: #818cf8; font-weight: 600; padding: 0.35rem 0.75rem; border-radius: 20px;">
                    {{ $pointsRules->count() }} {{ __('Active Rules') }}
                </span>
            </div>

            @if($pointsRules->isEmpty())
                <div style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted);">
                    <i class="fa-solid fa-coins" style="font-size: 2.5rem; opacity: 0.3; margin-bottom: 0.75rem; display: block;"></i>
                    <p style="margin: 0;">{{ __('No specific points rules configured. Default ratio') }} (<strong>{{ $program->default_points_ratio }} pts / {{ $program->currency }}</strong>) {{ __('applies to all orders.') }}</p>
                </div>
            @else
                <div class="table-responsive" style="overflow-x: auto;">
                    <table class="table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--border-color); text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                                <th style="padding: 0.75rem 1rem;">{{ __('Priority') }}</th>
                                <th style="padding: 0.75rem 1rem;">{{ __('Rule Name') }}</th>
                                <th style="padding: 0.75rem 1rem;">{{ __('Type') }}</th>
                                <th style="padding: 0.75rem 1rem;">{{ __('Earning Rate') }}</th>
                                <th style="padding: 0.75rem 1rem;">{{ __('Min Order Spend') }}</th>
                                <th style="padding: 0.75rem 1rem;">{{ __('Max Order Pts') }}</th>
                                <th style="padding: 0.75rem 1rem;">{{ __('Status') }}</th>
                                <th style="padding: 0.75rem 1rem; text-align: right;">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pointsRules as $rule)
                                <tr style="border-bottom: 1px solid var(--border-color); font-size: 0.875rem;">
                                    <td style="padding: 1rem;">
                                        <span class="badge" style="background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-weight: 700; padding: 0.25rem 0.5rem; border-radius: 6px;">
                                            {{ $rule->priority }}
                                        </span>
                                    </td>
                                    <td style="padding: 1rem; font-weight: 600; color: var(--text-main);">
                                        {{ $rule->name }}
                                    </td>
                                    <td style="padding: 1rem;">
                                        <span class="badge" style="background: rgba(99, 102, 241, 0.1); color: #818cf8; text-transform: capitalize; padding: 0.25rem 0.5rem; border-radius: 6px;">
                                            {{ $rule->type }}
                                        </span>
                                    </td>
                                    <td style="padding: 1rem; font-weight: 700; color: #10b981;">
                                        @if($rule->type === 'multiplier')
                                            {{ $rule->rate_value }}x
                                        @elseif($rule->type === 'fixed')
                                            {{ (int) $rule->rate_value }} pts
                                        @else
                                            {{ $rule->rate_value }} pts / {{ $program->currency }}
                                        @endif
                                    </td>
                                    <td style="padding: 1rem; color: var(--text-muted);">
                                        {{ $rule->min_order_spend ? number_format($rule->min_order_spend, 2) . ' ' . $program->currency : __('None') }}
                                    </td>
                                    <td style="padding: 1rem; color: var(--text-muted);">
                                        {{ $rule->max_points_per_order ? number_format($rule->max_points_per_order) . ' pts' : __('Unlimited') }}
                                    </td>
                                    <td style="padding: 1rem;">
                                        <form method="POST" action="{{ route('admin.loyalty.rules.toggle', $rule) }}">
                                            @csrf
                                            <button type="submit" style="background: none; border: none; cursor: pointer;">
                                                @if($rule->is_active)
                                                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #34d399; font-weight: 600; padding: 0.25rem 0.6rem; border-radius: 20px;">
                                                        <i class="fa-solid fa-check mr-1"></i> {{ __('Active') }}
                                                    </span>
                                                @else
                                                    <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #f87171; font-weight: 600; padding: 0.25rem 0.6rem; border-radius: 20px;">
                                                        <i class="fa-solid fa-ban mr-1"></i> {{ __('Disabled') }}
                                                    </span>
                                                @endif
                                            </button>
                                        </form>
                                    </td>
                                    <td style="padding: 1rem; text-align: right;">
                                        <form method="POST" action="{{ route('admin.loyalty.rules.destroy', $rule) }}" onsubmit="return confirm('{{ __('Are you sure you want to delete this rule?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); padding: 0.3rem 0.6rem; border-radius: 6px;">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Section 2: Cashback Earning Rules -->
        <div class="card" style="padding: 1.5rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <div>
                    <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #10b981;"></span>
                        {{ __('Cashback Earning Rules') }}
                    </h3>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0.25rem 0 0 0;">
                        {{ __('Credited directly into independent customer currency balances upon completed orders.') }}
                    </p>
                </div>
                <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #34d399; font-weight: 600; padding: 0.35rem 0.75rem; border-radius: 20px;">
                    {{ $cashbackRules->count() }} {{ __('Active Rules') }}
                </span>
            </div>

            @if($cashbackRules->isEmpty())
                <div style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted);">
                    <i class="fa-solid fa-wallet" style="font-size: 2.5rem; opacity: 0.3; margin-bottom: 0.75rem; display: block;"></i>
                    <p style="margin: 0;">{{ __('No custom cashback rules defined. Default cashback percentage') }} (<strong>{{ $program->default_cashback_percent }}%</strong>) {{ __('applies when enabled.') }}</p>
                </div>
            @else
                <div class="table-responsive" style="overflow-x: auto;">
                    <table class="table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--border-color); text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                                <th style="padding: 0.75rem 1rem;">{{ __('Priority') }}</th>
                                <th style="padding: 0.75rem 1rem;">{{ __('Rule Name') }}</th>
                                <th style="padding: 0.75rem 1rem;">{{ __('Type') }}</th>
                                <th style="padding: 0.75rem 1rem;">{{ __('Cashback Rate') }}</th>
                                <th style="padding: 0.75rem 1rem;">{{ __('Min Order Spend') }}</th>
                                <th style="padding: 0.75rem 1rem;">{{ __('Status') }}</th>
                                <th style="padding: 0.75rem 1rem; text-align: right;">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cashbackRules as $rule)
                                <tr style="border-bottom: 1px solid var(--border-color); font-size: 0.875rem;">
                                    <td style="padding: 1rem;">
                                        <span class="badge" style="background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-weight: 700; padding: 0.25rem 0.5rem; border-radius: 6px;">
                                            {{ $rule->priority }}
                                        </span>
                                    </td>
                                    <td style="padding: 1rem; font-weight: 600; color: var(--text-main);">
                                        {{ $rule->name }}
                                    </td>
                                    <td style="padding: 1rem;">
                                        <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: #34d399; text-transform: capitalize; padding: 0.25rem 0.5rem; border-radius: 6px;">
                                            {{ $rule->type }}
                                        </span>
                                    </td>
                                    <td style="padding: 1rem; font-weight: 700; color: #10b981;">
                                        @if($rule->type === 'percentage')
                                            {{ $rule->rate_value }}%
                                        @else
                                            {{ number_format($rule->rate_value, 2) }} {{ $program->currency }}
                                        @endif
                                    </td>
                                    <td style="padding: 1rem; color: var(--text-muted);">
                                        {{ $rule->min_order_spend ? number_format($rule->min_order_spend, 2) . ' ' . $program->currency : __('None') }}
                                    </td>
                                    <td style="padding: 1rem;">
                                        <form method="POST" action="{{ route('admin.loyalty.rules.toggle', $rule) }}">
                                            @csrf
                                            <button type="submit" style="background: none; border: none; cursor: pointer;">
                                                @if($rule->is_active)
                                                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #34d399; font-weight: 600; padding: 0.25rem 0.6rem; border-radius: 20px;">
                                                        <i class="fa-solid fa-check mr-1"></i> {{ __('Active') }}
                                                    </span>
                                                @else
                                                    <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #f87171; font-weight: 600; padding: 0.25rem 0.6rem; border-radius: 20px;">
                                                        <i class="fa-solid fa-ban mr-1"></i> {{ __('Disabled') }}
                                                    </span>
                                                @endif
                                            </button>
                                        </form>
                                    </td>
                                    <td style="padding: 1rem; text-align: right;">
                                        <form method="POST" action="{{ route('admin.loyalty.rules.destroy', $rule) }}" onsubmit="return confirm('{{ __('Are you sure you want to delete this rule?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); padding: 0.3rem 0.6rem; border-radius: 6px;">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>

    <!-- Create Rule Modal -->
    <div id="createRuleModal" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(4px); align-items: center; justify-content: center;">
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; width: 100%; max-width: 550px; padding: 2rem; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin: 0;">
                    <i class="fa-solid fa-plus-circle text-primary mr-1"></i> {{ __('New Earning Rule') }}
                </h3>
                <button type="button" onclick="closeCreateRuleModal()" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('admin.loyalty.rules.store') }}">
                @csrf
                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <div>
                        <label class="form-label">{{ __('Rule Name') }}</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Weekend Double Points or High Roller 10%" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label class="form-label">{{ __('Reward Type') }}</label>
                            <select name="reward_type" class="form-control" required>
                                <option value="points">{{ __('Points') }}</option>
                                <option value="cashback">{{ __('Cashback') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">{{ __('Calculation Mode') }}</label>
                            <select name="type" class="form-control" required>
                                <option value="spending">{{ __('Per Spend Ratio (Points / Spend)') }}</option>
                                <option value="percentage">{{ __('Percentage (e.g. 5% cashback)') }}</option>
                                <option value="fixed">{{ __('Fixed Flat Amount') }}</option>
                                <option value="multiplier">{{ __('Multiplier Factor (e.g. 2x)') }}</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label class="form-label">{{ __('Rate / Multiplier Value') }}</label>
                            <input type="number" step="0.0001" name="rate_value" class="form-control" placeholder="e.g. 1.5 or 5.0" required>
                        </div>
                        <div>
                            <label class="form-label">{{ __('Priority (Higher wins)') }}</label>
                            <input type="number" name="priority" value="10" class="form-control">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label class="form-label">{{ __('Min Order Spend') }}</label>
                            <input type="number" step="0.01" name="min_order_spend" class="form-control" placeholder="0.00">
                        </div>
                        <div>
                            <label class="form-label">{{ __('Max Points Cap (Optional)') }}</label>
                            <input type="number" name="max_points_per_order" class="form-control" placeholder="e.g. 1000">
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1rem;">
                        <button type="button" onclick="closeCreateRuleModal()" class="btn btn-secondary">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-save mr-1"></i> {{ __('Save Earning Rule') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function openCreateRuleModal() {
    document.getElementById('createRuleModal').style.display = 'flex';
}
function closeCreateRuleModal() {
    document.getElementById('createRuleModal').style.display = 'none';
}
</script>
@endsection

