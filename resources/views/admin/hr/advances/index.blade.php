@extends('layouts.app')

@section('title', __('Employee Advances & Loans') . ' - ' . $vendor->name)

@section('content')
<div x-data="{
    showRequestModal: false,
    showReviewModal: false,
    reviewItem: { id: null, employee: '', amount: 0, action: 'disburse' },
    openReview(adv, action) {
        this.reviewItem = {
            id: adv.id,
            employee: adv.user ? adv.user.name : '',
            amount: parseFloat(adv.amount || 0),
            action: action
        };
        this.showReviewModal = true;
    }
}" style="display: flex; flex-direction: column; gap: 1.5rem;">

    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="min-width: 0; flex: 1;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span style="background: rgba(217, 119, 6, 0.15); color: #d97706; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-hand-holding-dollar"></i>
                </span>
                <div>
                    <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.35rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0;">
                        {{ __('Employee Advances & Loans') }}
                    </h1>
                    <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0.25rem 0 0;">
                        {{ __('Manage salary advance requests, emergency loans, and automatic payroll recovery') }}
                    </p>
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <a href="{{ route('admin.hr.payroll.index') }}" class="btn btn-secondary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px;">
                <i class="fa-solid fa-file-invoice-dollar"></i> {{ __('Payroll') }}
            </a>
            @can('hr.advances.request')
                <button type="button" @click="showRequestModal = true" class="btn btn-primary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px; box-shadow: 0 4px 14px rgba(217, 119, 6, 0.3);">
                    <i class="fa-solid fa-plus-circle"></i> {{ __('Issue / Request Advance') }}
                </button>
            @endcan
        </div>
    </div>

    <!-- Advances Table -->
    <div class="card" style="border-radius: 14px; overflow: hidden; padding: 0;">
        <div class="table-responsive">
            <table class="table" style="margin: 0; vertical-align: middle;">
                <thead style="background: rgba(255,255,255,0.03); border-bottom: 1px solid var(--border-color);">
                    <tr>
                        <th style="padding: 1rem 1.25rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Employee') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Requested Date') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Requested') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Disbursed') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Recovered') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Remaining') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Status') }}</th>
                        <th style="padding: 1rem 1.25rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; text-align: right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($advances as $adv)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 1rem 1.25rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(217, 119, 6, 0.15); color: #d97706; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem; flex-shrink: 0;">
                                        {{ strtoupper(mb_substr($adv->user?->name ?? '?', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div style="font-weight: 700; color: var(--text-main);">
                                            {{ $adv->user?->name ?? __('Unknown') }}
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">
                                            {{ $adv->user?->employeeProfile?->position?->title ?? 'Staff' }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 1rem 1rem; color: var(--text-muted); font-size: 0.88rem;">
                                {{ \Carbon\Carbon::parse($adv->requested_date)->format('d.m.Y') }}
                            </td>
                            <td style="padding: 1rem 1rem; font-weight: 700; color: var(--text-main);">
                                {{ number_format((float) $adv->amount, 2) }} AMD
                            </td>
                            <td style="padding: 1rem 1rem; font-weight: 700; color: #22c55e;">
                                {{ number_format((float) $adv->disbursed_amount, 2) }} AMD
                            </td>
                            <td style="padding: 1rem 1rem; color: #0ea5e9;">
                                {{ number_format((float) $adv->recovered_amount, 2) }} AMD
                            </td>
                            <td style="padding: 1rem 1rem; font-weight: 900; color: {{ (float)$adv->remaining_balance > 0 ? '#ef4444' : '#22c55e' }};">
                                {{ number_format((float) $adv->remaining_balance, 2) }} AMD
                            </td>
                            <td style="padding: 1rem 1rem;">
                                @php
                                    $st = $adv->status;
                                    $badge = match($st) {
                                        'disbursed' => ['bg' => 'rgba(34, 197, 94, 0.15)', 'color' => '#22c55e'],
                                        'recovered' => ['bg' => 'rgba(14, 165, 233, 0.15)', 'color' => '#0ea5e9'],
                                        'rejected' => ['bg' => 'rgba(239, 68, 68, 0.15)', 'color' => '#ef4444'],
                                        'pending' => ['bg' => 'rgba(245, 158, 11, 0.15)', 'color' => '#f59e0b'],
                                        default => ['bg' => 'rgba(148, 163, 184, 0.15)', 'color' => '#94a3b8'],
                                    };
                                @endphp
                                <span class="badge" style="background: {{ $badge['bg'] }}; color: {{ $badge['color'] }}; text-transform: uppercase;">
                                    {{ $st }}
                                </span>
                            </td>
                            <td style="padding: 1rem 1.25rem; text-align: right;">
                                @if($adv->status === 'pending')
                                    @can('hr.advances.approve')
                                        <div style="display: inline-flex; align-items: center; gap: 0.4rem;">
                                            <button type="button" @click="openReview({{ json_encode($adv) }}, 'disburse')" class="btn btn-primary btn-sm" style="border-radius: 6px; font-weight: 700;">
                                                <i class="fa-solid fa-check"></i> {{ __('Disburse') }}
                                            </button>
                                            <button type="button" @click="openReview({{ json_encode($adv) }}, 'reject')" class="btn btn-danger btn-sm" style="border-radius: 6px;">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </div>
                                    @endcan
                                @else
                                    <span style="font-size: 0.75rem; color: var(--text-muted);">
                                        {{ __('Approved by') }} {{ $adv->approver?->name ?? 'Admin' }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 3rem 1.5rem; color: var(--text-muted);">
                                <div style="font-size: 2.5rem; color: var(--border-color); margin-bottom: 0.75rem;">
                                    <i class="fa-solid fa-hand-holding-dollar"></i>
                                </div>
                                <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0 0 0.25rem; color: var(--text-main);">
                                    {{ __('No advance records found') }}
                                </h3>
                                <p style="font-size: 0.85rem; margin: 0 0 1rem;">
                                    {{ __('Staff can request salary advances, or managers can issue one directly.') }}
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($advances->hasPages())
            <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--border-color);">
                {{ $advances->links() }}
            </div>
        @endif
    </div>

    <!-- REQUEST ADVANCE MODAL -->
    <div x-show="showRequestModal" x-cloak class="modal-backdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div @click.away="showRequestModal = false" class="card" style="width: 100%; max-width: 500px; padding: 1.5rem; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">{{ __('Issue / Request Salary Advance') }}</h3>
                <button type="button" @click="showRequestModal = false" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.hr.advances.store') }}">
                @csrf
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Employee') }} <span style="color: #ef4444;">*</span></label>
                        <select name="user_id" class="form-control" required style="border-radius: 10px; height: 42px;">
                            <option value="">{{ __('Select Employee') }}</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Advance Amount (AMD)') }} <span style="color: #ef4444;">*</span></label>
                        <input type="number" step="1000" min="1000" name="amount" placeholder="{{ __('e.g. 50000') }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                    </div>

                    <div>
                        <label class="form-label">{{ __('Disbursement Method') }}</label>
                        <select name="payment_method" class="form-control" required style="border-radius: 10px; height: 42px;">
                            <option value="cash">{{ __('Cash') }}</option>
                            <option value="bank_transfer">{{ __('Bank Transfer') }}</option>
                            <option value="other">{{ __('Other') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Reason / Purpose') }} <span style="color: #ef4444;">*</span></label>
                        <textarea name="reason" rows="2" placeholder="{{ __('e.g. Emergency family medical expense') }}" class="form-control" required style="border-radius: 10px;"></textarea>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="button" @click="showRequestModal = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Submit Advance') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- REVIEW ADVANCE MODAL -->
    <div x-show="showReviewModal" x-cloak class="modal-backdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div @click.away="showReviewModal = false" class="card" style="width: 100%; max-width: 480px; padding: 1.5rem; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">
                    <span x-text="reviewItem.action === 'disburse' ? '{{ __('Disburse Advance') }}' : '{{ __('Reject Advance') }}'"></span>
                </h3>
                <button type="button" @click="showReviewModal = false" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>
            <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                {{ __('Employee:') }} <strong x-text="reviewItem.employee" style="color: var(--text-main);"></strong> • 
                <span x-text="new Intl.NumberFormat().format(reviewItem.amount)"></span> AMD
            </p>
            <form method="POST" :action="'{{ url('admin/hr/advances') }}/' + reviewItem.id + '/review'">
                @csrf
                <input type="hidden" name="action" :value="reviewItem.action">
                <p style="font-size: 0.85rem; color: var(--text-muted);">
                    <span x-show="reviewItem.action === 'disburse'">
                        {{ __('Confirming disbursement will mark the advance as disbursed and queue automatic deduction in the next payroll calculation.') }}
                    </span>
                    <span x-show="reviewItem.action === 'reject'">
                        {{ __('Are you sure you want to reject this salary advance request?') }}
                    </span>
                </p>
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="button" @click="showReviewModal = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn" :class="reviewItem.action === 'disburse' ? 'btn-primary' : 'btn-danger'">
                        <span x-text="reviewItem.action === 'disburse' ? '{{ __('Confirm Disbursement') }}' : '{{ __('Confirm Rejection') }}'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

