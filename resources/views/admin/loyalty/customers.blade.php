@extends('layouts.app')

@section('content')
<div class="content-wrapper" x-data="{
    adjustModalOpen: false,
    selectedCustomerId: null,
    selectedCustomerName: '',
    adjustType: 'points',
    adjustAmount: '',
    adjustNotes: '',

    openAdjustModal(id, name) {
        this.selectedCustomerId = id;
        this.selectedCustomerName = name;
        this.adjustAmount = '';
        this.adjustNotes = '';
        this.adjustModalOpen = true;
    }
}">

    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-users" style="color: #6366f1;"></i>
                <span>{{ __('Loyalty Members & Balances') }}</span>
            </h1>
            <p style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('Inspect member balances, membership tiers, and perform authorized ledger adjustments.') }}
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

    <!-- Filters -->
    <div class="card" style="padding: 1.25rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; margin-bottom: 1.5rem;">
        <form method="GET" action="{{ route('admin.loyalty.customers') }}" style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 240px;">
                <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('Search by member name, phone or email...') }}" class="form-control">
            </div>
            <div style="min-width: 160px;">
                <select name="tier" class="form-control">
                    <option value="all">{{ __('All Tiers') }}</option>
                    @foreach(['Bronze', 'Silver', 'Gold', 'Platinum'] as $t)
                        <option value="{{ $t }}" {{ $tier === $t ? 'selected' : '' }}>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-filter mr-1"></i> {{ __('Filter') }}
            </button>
            @if($search || $tier)
                <a href="{{ route('admin.loyalty.customers') }}" class="btn btn-secondary">
                    {{ __('Clear') }}
                </a>
            @endif
        </form>
    </div>

    <!-- Customers Table -->
    <div class="card" style="padding: 1.5rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px;">
        <div class="table-responsive">
            <table class="table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color); font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); text-align: left;">
                        <th style="padding: 0.75rem 0.5rem;">{{ __('Member') }}</th>
                        <th style="padding: 0.75rem 0.5rem;">{{ __('Contact') }}</th>
                        <th style="padding: 0.75rem 0.5rem;">{{ __('Tier') }}</th>
                        <th style="padding: 0.75rem 0.5rem; text-align: right;">{{ __('Points') }}</th>
                        <th style="padding: 0.75rem 0.5rem; text-align: right;">{{ __('Cashback') }}</th>
                        <th style="padding: 0.75rem 0.5rem;">{{ __('Enrolled') }}</th>
                        <th style="padding: 0.75rem 0.5rem; text-align: right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody style="font-size: 0.85rem;">
                    @forelse($customers as $c)
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.03);">
                            <td style="padding: 0.75rem 0.5rem;">
                                <div style="font-weight: 700; color: var(--text-main);">{{ $c->name }}</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">MEM-{{ str_pad($c->id, 8, '0', STR_PAD_LEFT) }}</div>
                            </td>
                            <td style="padding: 0.75rem 0.5rem; color: var(--text-muted);">
                                <div>{{ $c->phone ?: '-' }}</div>
                                <div style="font-size: 0.75rem;">{{ $c->email }}</div>
                            </td>
                            <td style="padding: 0.75rem 0.5rem;">
                                <span class="badge" style="background: rgba(99,102,241,0.15); color: #818cf8; font-weight: 700; padding: 0.25rem 0.6rem; border-radius: 8px;">
                                    {{ $c->loyalty_tier ?: 'Bronze' }}
                                </span>
                            </td>
                            <td style="padding: 0.75rem 0.5rem; text-align: right; font-weight: 800; color: #6366f1;">
                                {{ number_format($c->pointsAccount?->current_balance ?? $c->loyalty_points) }}
                            </td>
                            <td style="padding: 0.75rem 0.5rem; text-align: right; font-weight: 800; color: #10b981;">
                                {{ number_format($c->cashbackAccount?->current_balance ?? 0, 2) }}
                            </td>
                            <td style="padding: 0.75rem 0.5rem; color: var(--text-muted);">
                                {{ $c->created_at->format('M d, Y') }}
                            </td>
                            <td style="padding: 0.75rem 0.5rem; text-align: right;">
                                <button type="button" 
                                        @click="openAdjustModal({{ $c->id }}, '{{ addslashes($c->name) }}')"
                                        class="btn btn-secondary btn-sm"
                                        title="{{ __('Adjust Balance') }}">
                                    <i class="fa-solid fa-pen-to-square mr-1"></i> {{ __('Adjust') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                {{ __('No customers found matching the search criteria.') }}
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

    <!-- Manual Adjustment Modal -->
    <div x-show="adjustModalOpen" 
         x-transition 
         style="position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 1rem;"
         style="display: none;">
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; max-width: 480px; width: 100%; padding: 2rem; position: relative;"
             @click.away="adjustModalOpen = false">
            <button type="button" @click="adjustModalOpen = false" style="position: absolute; top: 1.25rem; right: 1.25rem; background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.25rem;">
                <i class="fa-solid fa-xmark"></i>
            </button>

            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem;">
                {{ __('Manual Balance Adjustment') }}
            </h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                {{ __('Member: ') }}<strong style="color: var(--text-main);" x-text="selectedCustomerName"></strong>
            </p>

            <form method="POST" action="{{ route('admin.loyalty.adjust_balance') }}">
                @csrf
                <input type="hidden" name="customer_id" :value="selectedCustomerId">

                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <div>
                        <label class="form-label">{{ __('Account Type') }}</label>
                        <select name="type" x-model="adjustType" class="form-control" required>
                            <option value="points">{{ __('Points') }}</option>
                            <option value="cashback">{{ __('Cashback') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Amount (+ to credit, - to debit)') }}</label>
                        <input type="number" step="any" name="amount" x-model="adjustAmount" class="form-control" placeholder="{{ __('e.g. 500 or -200') }}" required>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Reason / Audit Note') }}</label>
                        <textarea name="notes" x-model="adjustNotes" class="form-control" rows="2" placeholder="{{ __('Reason for adjustment (required for audit compliance)') }}" required></textarea>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem;">
                        <button type="button" @click="adjustModalOpen = false" class="btn btn-secondary">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit" class="btn btn-primary">
                            {{ __('Apply Adjustment') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

