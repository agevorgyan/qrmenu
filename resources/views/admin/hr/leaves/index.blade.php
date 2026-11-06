@extends('layouts.app')

@section('title', __('Leaves & Time Off') . ' - ' . $vendor->name)

@section('content')
<div x-data="{
    showApplyModal: false,
    showReviewModal: false,
    reviewItem: { id: null, employee: '', type: '', days: 0, action: 'approve', notes: '' },
    openReview(req, action) {
        this.reviewItem = {
            id: req.id,
            employee: req.user ? req.user.name : '',
            type: req.leave_type ? req.leave_type.name : '',
            days: req.total_days,
            action: action,
            notes: ''
        };
        this.showReviewModal = true;
    }
}" style="display: flex; flex-direction: column; gap: 1.5rem;">

    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="min-width: 0; flex: 1;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span style="background: rgba(249, 115, 22, 0.15); color: #f97316; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-plane-departure"></i>
                </span>
                <div>
                    <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.35rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0;">
                        {{ __('Leaves & Time Off') }}
                    </h1>
                    <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0.25rem 0 0;">
                        {{ __('Annual leave, sick days, unpaid absences, balance tracking, and review workflows') }}
                    </p>
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <a href="{{ route('admin.hr.leaves.balances') }}" class="btn btn-secondary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px;">
                <i class="fa-solid fa-scale-balanced"></i> {{ __('Leave Balances') }}
            </a>
            @can('hr.leave.request')
                <button type="button" @click="showApplyModal = true" class="btn btn-primary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px; box-shadow: 0 4px 14px rgba(249, 115, 22, 0.3);">
                    <i class="fa-solid fa-plus-circle"></i> {{ __('Apply Leave for Staff') }}
                </button>
            @endcan
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
        <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-radius: 14px;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(245, 158, 11, 0.12); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                <i class="fa-solid fa-hourglass-start"></i>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">{{ __('Pending Requests') }}</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: #f59e0b;">
                    {{ $requests->filter(fn($r) => $r->status === 'pending')->count() }}
                </div>
            </div>
        </div>

        <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-radius: 14px;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(34, 197, 94, 0.12); color: #22c55e; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                <i class="fa-solid fa-calendar-check"></i>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">{{ __('Approved Leaves') }}</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: #22c55e;">
                    {{ $requests->filter(fn($r) => $r->status === 'approved')->count() }}
                </div>
            </div>
        </div>

        <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-radius: 14px;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(239, 68, 68, 0.12); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                <i class="fa-solid fa-calendar-xmark"></i>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">{{ __('Rejected') }}</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: #ef4444;">
                    {{ $requests->filter(fn($r) => $r->status === 'rejected')->count() }}
                </div>
            </div>
        </div>

        <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-radius: 14px;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(14, 165, 233, 0.12); color: #0ea5e9; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                <i class="fa-solid fa-layer-group"></i>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">{{ __('Leave Categories') }}</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-main);">{{ $leaveTypes->count() }}</div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card" style="padding: 1rem; border-radius: 14px;">
        <form method="GET" action="{{ route('admin.hr.leaves.index') }}" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
            <div style="min-width: 170px;">
                <select name="status" class="form-control" style="border-radius: 10px; height: 40px;">
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>{{ __('Pending Approval') }}</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>{{ __('Approved') }}</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>{{ __('Rejected') }}</option>
                </select>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary" style="height: 40px; border-radius: 10px; font-weight: 700;">
                    <i class="fa-solid fa-filter"></i> {{ __('Filter') }}
                </button>
                @if(request()->filled('status'))
                    <a href="{{ route('admin.hr.leaves.index') }}" class="btn btn-secondary" style="height: 40px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Leave Requests Table -->
    <div class="card" style="border-radius: 14px; overflow: hidden; padding: 0;">
        <div class="table-responsive">
            <table class="table" style="margin: 0; vertical-align: middle;">
                <thead style="background: rgba(255,255,255,0.03); border-bottom: 1px solid var(--border-color);">
                    <tr>
                        <th style="padding: 1rem 1.25rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Employee') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Leave Type') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Dates') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Total Days') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Reason') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Status') }}</th>
                        <th style="padding: 1rem 1.25rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; text-align: right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 1rem 1.25rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(249, 115, 22, 0.15); color: #f97316; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.95rem; flex-shrink: 0;">
                                        {{ strtoupper(mb_substr($req->user?->name ?? '?', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div style="font-weight: 700; color: var(--text-main);">
                                            {{ $req->user?->name ?? __('Unknown') }}
                                        </div>
                                        <div style="font-size: 0.78rem; color: var(--text-muted);">
                                            {{ $req->user?->employeeProfile?->position?->title ?? 'Staff' }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 1rem 1rem;">
                                <span style="font-weight: 700; color: #f97316;">
                                    {{ $req->leaveType?->name ?? __('Leave') }}
                                </span>
                                @if($req->leaveType?->is_paid)
                                    <span style="font-size: 0.7rem; background: rgba(34, 197, 94, 0.15); color: #22c55e; padding: 1px 6px; border-radius: 4px; margin-left: 4px;">{{ __('Paid') }}</span>
                                @endif
                            </td>
                            <td style="padding: 1rem 1rem;">
                                <div style="font-weight: 600; color: var(--text-main); font-size: 0.88rem;">
                                    {{ \Carbon\Carbon::parse($req->start_date)->format('d.m.Y') }} — {{ \Carbon\Carbon::parse($req->end_date)->format('d.m.Y') }}
                                </div>
                            </td>
                            <td style="padding: 1rem 1rem; font-weight: 800; font-size: 1rem; color: var(--text-main);">
                                {{ $req->total_days }} {{ __('days') }}
                            </td>
                            <td style="padding: 1rem 1rem;">
                                <span style="font-size: 0.85rem; color: var(--text-muted);">
                                    {{ Str::limit($req->reason, 45) }}
                                </span>
                            </td>
                            <td style="padding: 1rem 1rem;">
                                @php
                                    $st = $req->status;
                                    $badge = match($st) {
                                        'approved' => ['bg' => 'rgba(34, 197, 94, 0.15)', 'color' => '#22c55e'],
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
                                @if($req->status === 'pending')
                                    @can('hr.leave.approve')
                                        <div style="display: inline-flex; align-items: center; gap: 0.4rem;">
                                            <button type="button" @click="openReview({{ json_encode($req) }}, 'approve')" class="btn btn-primary btn-sm" style="border-radius: 6px; font-weight: 700;">
                                                <i class="fa-solid fa-check"></i> {{ __('Approve') }}
                                            </button>
                                            <button type="button" @click="openReview({{ json_encode($req) }}, 'reject')" class="btn btn-danger btn-sm" style="border-radius: 6px;">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </div>
                                    @endcan
                                @else
                                    <span style="font-size: 0.75rem; color: var(--text-muted);">
                                        {{ __('Reviewed by') }} {{ $req->reviewer?->name ?? 'Manager' }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 3rem 1.5rem; color: var(--text-muted);">
                                <div style="font-size: 2.5rem; color: var(--border-color); margin-bottom: 0.75rem;">
                                    <i class="fa-solid fa-plane"></i>
                                </div>
                                <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0 0 0.25rem; color: var(--text-main);">
                                    {{ __('No leave requests found') }}
                                </h3>
                                <p style="font-size: 0.85rem; margin: 0 0 1rem;">
                                    {{ __('Staff can apply for vacations and absences, or you can register one on their behalf.') }}
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($requests->hasPages())
            <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--border-color);">
                {{ $requests->links() }}
            </div>
        @endif
    </div>

    <!-- APPLY LEAVE MODAL -->
    <div x-show="showApplyModal" x-cloak class="modal-backdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div @click.away="showApplyModal = false" class="card" style="width: 100%; max-width: 500px; padding: 1.5rem; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">{{ __('Apply Leave for Staff') }}</h3>
                <button type="button" @click="showApplyModal = false" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.hr.leaves.store') }}" enctype="multipart/form-data">
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
                        <label class="form-label">{{ __('Leave Category') }} <span style="color: #ef4444;">*</span></label>
                        <select name="leave_type_id" class="form-control" required style="border-radius: 10px; height: 42px;">
                            @foreach($leaveTypes as $lt)
                                <option value="{{ $lt->id }}">{{ $lt->name }} ({{ $lt->is_paid ? __('Paid') : __('Unpaid') }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label class="form-label">{{ __('Start Date') }} <span style="color: #ef4444;">*</span></label>
                            <input type="date" name="start_date" value="{{ date('Y-m-d') }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                        </div>
                        <div>
                            <label class="form-label">{{ __('End Date') }} <span style="color: #ef4444;">*</span></label>
                            <input type="date" name="end_date" value="{{ date('Y-m-d') }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">{{ __('Reason / Justification') }} <span style="color: #ef4444;">*</span></label>
                        <textarea name="reason" rows="2" class="form-control" required style="border-radius: 10px;"></textarea>
                    </div>
                    <div>
                        <label class="form-label">{{ __('Attachment (Medical note, certificate - optional)') }}</label>
                        <input type="file" name="attachment" class="form-control" style="border-radius: 10px;">
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="button" @click="showApplyModal = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Submit Leave Request') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- REVIEW MODAL -->
    <div x-show="showReviewModal" x-cloak class="modal-backdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div @click.away="showReviewModal = false" class="card" style="width: 100%; max-width: 480px; padding: 1.5rem; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">
                    <span x-text="reviewItem.action === 'approve' ? '{{ __('Approve Leave') }}' : '{{ __('Reject Leave') }}'"></span>
                </h3>
                <button type="button" @click="showReviewModal = false" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>
            <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                {{ __('Employee:') }} <strong x-text="reviewItem.employee" style="color: var(--text-main);"></strong> • 
                <span x-text="reviewItem.days"></span> {{ __('days of') }} <span x-text="reviewItem.type"></span>
            </p>
            <form method="POST" :action="'{{ url('admin/hr/leaves') }}/' + reviewItem.id + '/review'">
                @csrf
                <input type="hidden" name="action" :value="reviewItem.action">
                <div>
                    <label class="form-label">{{ __('Manager Notes / Comments') }}</label>
                    <textarea name="review_notes" rows="2" class="form-control" style="border-radius: 10px;" placeholder="{{ __('Optional reason or review comments...') }}"></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="button" @click="showReviewModal = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn" :class="reviewItem.action === 'approve' ? 'btn-primary' : 'btn-danger'">
                        <span x-text="reviewItem.action === 'approve' ? '{{ __('Confirm Approval') }}' : '{{ __('Confirm Rejection') }}'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

