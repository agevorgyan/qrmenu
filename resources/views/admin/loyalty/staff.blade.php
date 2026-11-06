@extends('layouts.app')

@section('content')
<div class="content-wrapper" x-data="{
    searchQuery: '',
    loading: false,
    customerData: null,
    errorMessage: '',
    successMessage: '',
    pointsToIssue: '',
    pointsToRedeem: '',
    voucherCode: '',
    selectedProgramId: '{{ $stampPrograms->first()?->id }}',

    async lookupCustomer() {
        this.errorMessage = '';
        this.successMessage = '';
        if (!this.searchQuery.trim()) {
            this.errorMessage = 'Please enter a phone number, member code, or scan a QR token.';
            return;
        }
        this.loading = true;
        try {
            const res = await fetch('{{ route('admin.loyalty.staff.lookup') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify({ query: this.searchQuery })
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Customer not found');
            this.customerData = data.overview;
        } catch (e) {
            this.errorMessage = e.message;
            this.customerData = null;
        } finally {
            this.loading = false;
        }
    },

    async issuePoints() {
        if (!this.customerData || !this.pointsToIssue) return;
        this.loading = true;
        try {
            const res = await fetch('{{ route('admin.loyalty.staff.points.issue') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify({
                    customer_id: this.customerData.customer.id,
                    points: parseInt(this.pointsToIssue)
                })
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Failed to issue points');
            this.successMessage = data.message;
            this.customerData.points.balance = data.new_balance;
            this.pointsToIssue = '';
        } catch (e) {
            this.errorMessage = e.message;
        } finally {
            this.loading = false;
        }
    },

    async redeemPoints() {
        if (!this.customerData || !this.pointsToRedeem) return;
        this.loading = true;
        try {
            const res = await fetch('{{ route('admin.loyalty.staff.points.redeem') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify({
                    customer_id: this.customerData.customer.id,
                    points: parseInt(this.pointsToRedeem)
                })
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Failed to redeem points');
            this.successMessage = data.message;
            this.customerData.points.balance = data.new_balance;
            this.pointsToRedeem = '';
        } catch (e) {
            this.errorMessage = e.message;
        } finally {
            this.loading = false;
        }
    },

    async redeemVoucher() {
        if (!this.voucherCode.trim()) return;
        this.loading = true;
        try {
            const res = await fetch('{{ route('admin.loyalty.staff.rewards.redeem') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify({ code: this.voucherCode })
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || (data.errors ? Object.values(data.errors)[0][0] : 'Invalid voucher'));
            this.successMessage = `${data.message} (${data.reward_title})`;
            this.voucherCode = '';
            if (this.customerData) this.lookupCustomer();
        } catch (e) {
            this.errorMessage = e.message;
        } finally {
            this.loading = false;
        }
    },

    async issueStamp() {
        if (!this.customerData || !this.selectedProgramId) return;
        this.loading = true;
        try {
            const res = await fetch('{{ route('admin.loyalty.staff.stamps.issue') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify({
                    customer_id: this.customerData.customer.id,
                    program_id: this.selectedProgramId,
                    stamps: 1
                })
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Failed to issue stamp');
            this.successMessage = `${data.message} (${data.stamps_collected}/${data.stamps_required})`;
        } catch (e) {
            this.errorMessage = e.message;
        } finally {
            this.loading = false;
        }
    }
}">

    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-qrcode" style="color: #ec4899;"></i>
                <span>Staff Loyalty Scanner & Operations</span>
            </h1>
            <p style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.25rem;">
                Scan customer QR tokens, look up members by phone, issue points, and redeem rewards.
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="{{ route('admin.loyalty.index') }}" class="btn btn-secondary" style="display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Back to Overview</span>
            </a>
        </div>
    </div>

    <!-- Feedback messages -->
    <template x-if="errorMessage">
        <div style="padding: 1rem; border-radius: 12px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; font-size: 0.875rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span x-text="errorMessage"></span>
        </div>
    </template>

    <template x-if="successMessage">
        <div style="padding: 1rem; border-radius: 12px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; font-size: 0.875rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-circle-check"></i>
            <span x-text="successMessage"></span>
        </div>
    </template>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
        
        <!-- Customer Lookup Panel -->
        <div class="card" style="padding: 1.5rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px;">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-main); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-magnifying-glass" style="color: #6366f1;"></i>
                <span>Member Search & QR Scanner</span>
            </h3>

            <div style="margin-bottom: 1rem;">
                <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem; display: block;">
                    Phone, Member ID, or Scan Code
                </label>
                <div style="display: flex; gap: 0.5rem;">
                    <input type="text" 
                           x-model="searchQuery" 
                           @keydown.enter="lookupCustomer"
                           placeholder="e.g. +374 91 123456, MEM-00001, or lq_..." 
                           class="form-control" 
                           style="flex: 1;">
                    <button type="button" @click="lookupCustomer" :disabled="loading" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-search"></i>
                        <span>Find</span>
                    </button>
                </div>
            </div>

            <!-- Fast Voucher Redemption -->
            <div style="padding-top: 1.25rem; border-top: 1px solid var(--border-color); margin-top: 1.25rem;">
                <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem; display: block;">
                    Redeem Reward Voucher Code
                </label>
                <div style="display: flex; gap: 0.5rem;">
                    <input type="text" 
                           x-model="voucherCode" 
                           @keydown.enter="redeemVoucher"
                           placeholder="e.g. RWD-AB12CD34" 
                           class="form-control" 
                           style="flex: 1; text-transform: uppercase; font-family: monospace;">
                    <button type="button" @click="redeemVoucher" :disabled="loading" class="btn btn-success" style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-check"></i>
                        <span>Redeem</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Customer Card & Operations Panel -->
        <div class="card" style="padding: 1.5rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px;">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-main); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-id-card" style="color: #ec4899;"></i>
                <span>Active Member Card</span>
            </h3>

            <template x-if="!customerData">
                <div style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted); font-size: 0.875rem;">
                    <i class="fa-solid fa-user-clock" style="font-size: 2rem; margin-bottom: 0.75rem; display: block; opacity: 0.4;"></i>
                    No member selected. Scan a QR code or search to begin operations.
                </div>
            </template>

            <template x-if="customerData">
                <div>
                    <!-- Member summary banner -->
                    <div style="padding: 1rem; border-radius: 12px; background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.2); margin-bottom: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h4 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0;" x-text="customerData.customer.name"></h4>
                                <span style="font-size: 0.8rem; color: var(--text-muted);" x-text="customerData.customer.phone || customerData.customer.email"></span>
                            </div>
                            <span class="badge" style="background: #6366f1; color: #fff; font-size: 0.75rem; padding: 0.25rem 0.6rem; border-radius: 8px;" x-text="customerData.tier.name"></span>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid rgba(99,102,241,0.15);">
                            <div>
                                <span style="font-size: 0.75rem; color: var(--text-muted);">Points Balance</span>
                                <div style="font-size: 1.5rem; font-weight: 900; color: #6366f1;" x-text="customerData.points.balance"></div>
                            </div>
                            <div>
                                <span style="font-size: 0.75rem; color: var(--text-muted);">Cashback</span>
                                <div style="font-size: 1.5rem; font-weight: 900; color: #10b981;" x-text="customerData.cashback.balance + ' ' + customerData.cashback.currency"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Operations buttons -->
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <!-- Issue Points -->
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <input type="number" x-model="pointsToIssue" placeholder="Points to issue" class="form-control" style="flex: 1;">
                            <button type="button" @click="issuePoints" :disabled="loading" class="btn btn-primary" style="white-space: nowrap;">
                                <i class="fa-solid fa-plus mr-1"></i> Issue Points
                            </button>
                        </div>

                        <!-- Redeem Points -->
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <input type="number" x-model="pointsToRedeem" placeholder="Points to redeem" class="form-control" style="flex: 1;">
                            <button type="button" @click="redeemPoints" :disabled="loading" class="btn btn-danger" style="white-space: nowrap;">
                                <i class="fa-solid fa-minus mr-1"></i> Redeem Points
                            </button>
                        </div>

                        <!-- Issue Stamp -->
                        @if($stampPrograms->isNotEmpty())
                            <div style="display: flex; gap: 0.5rem; align-items: center; padding-top: 0.75rem; border-top: 1px solid var(--border-color);">
                                <select x-model="selectedProgramId" class="form-control" style="flex: 1;">
                                    @foreach($stampPrograms as $sp)
                                        <option value="{{ $sp->id }}">{{ $sp->name }} ({{ $sp->stamps_required }} stamps)</option>
                                    @endforeach
                                </select>
                                <button type="button" @click="issueStamp" :disabled="loading" class="btn btn-secondary" style="white-space: nowrap;">
                                    <i class="fa-solid fa-stamp mr-1"></i> +1 Stamp
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </template>
        </div>

    </div>

</div>
@endsection

