@extends('layouts.app')

@section('title', __('Expenses & Accounts Payable') . ' - ' . config('app.name'))

@section('content')
<div style="max-width: 1400px; margin: 0 auto; width: 100%; display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Page Header -->
    <div class="card" style="padding: 1.5rem; margin-bottom: 0;">
        <div class="page-header-flex" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(239, 68, 68, 0.12); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <h1 style="font-size: clamp(1.25rem, 2.5vw, 1.65rem); font-weight: 800; margin: 0; color: var(--text-main); line-height: 1.2;">
                        {{ __('Expenses & Accounts Payable') }}
                    </h1>
                    <p style="margin: 0.35rem 0 0; color: var(--text-muted); font-size: 0.875rem;">
                        {{ __('Track operational expenses, approval limits, partial disbursements, and supplier settlements.') }}
                    </p>
                </div>
            </div>

            <div class="page-header-actions" style="display: flex; align-items: center; gap: 0.75rem;">
                <a href="{{ route('admin.finance.expenses.create') }}" class="btn btn-primary" style="padding: 0.65rem 1.25rem;">
                    <i class="fa-solid fa-plus"></i>
                    <span>{{ __('Record New Expense') }}</span>
                </a>
            </div>
        </div>

        <!-- Filters Form -->
        <form method="GET" action="{{ route('admin.finance.expenses.index') }}" class="filter-bar-flex" style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-top: 1.25rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
            <input type="text" name="search" class="form-control" placeholder="{{ __('Search expense #, title, invoice...') }}" value="{{ request('search') }}" style="max-width: 260px; font-size: 0.875rem;">

            <select name="status" class="form-control" style="width: auto; font-size: 0.875rem;" onchange="this.form.submit()">
                <option value="">{{ __('All Statuses') }}</option>
                <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>{{ __('Draft') }}</option>
                <option value="pending_approval" {{ request('status') === 'pending_approval' ? 'selected' : '' }}>{{ __('Pending Approval') }}</option>
                <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>{{ __('Approved') }}</option>
                <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>{{ __('Rejected') }}</option>
            </select>

            <select name="payment_status" class="form-control" style="width: auto; font-size: 0.875rem;" onchange="this.form.submit()">
                <option value="">{{ __('All Payments') }}</option>
                <option value="unpaid" {{ request('payment_status') === 'unpaid' ? 'selected' : '' }}>{{ __('Unpaid') }}</option>
                <option value="partially_paid" {{ request('payment_status') === 'partially_paid' ? 'selected' : '' }}>{{ __('Partially Paid') }}</option>
                <option value="paid" {{ request('payment_status') === 'paid' ? 'selected' : '' }}>{{ __('Paid') }}</option>
            </select>

            <select name="category_id" class="form-control" style="width: auto; font-size: 0.875rem;" onchange="this.form.submit()">
                <option value="">{{ __('All Categories') }}</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>

            <select name="location_id" class="form-control" style="width: auto; font-size: 0.875rem;" onchange="this.form.submit()">
                <option value="">{{ __('All Branches') }}</option>
                @foreach($locations as $loc)
                    <option value="{{ $loc->id }}" {{ request('location_id') == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                @endforeach
            </select>

            @if(request()->anyFilled(['search', 'status', 'payment_status', 'category_id', 'location_id']))
                <a href="{{ route('admin.finance.expenses.index') }}" class="btn btn-sm btn-secondary">{{ __('Reset') }}</a>
            @endif
        </form>
    </div>

    <!-- Expenses Table Card -->
    <div class="card card-flush" style="border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-card);">
        <div class="table-responsive" style="margin-bottom: 0; border: none; border-radius: 0;">
            <table class="table" style="min-width: 1040px;">
                <thead>
                    <tr>
                        <th style="min-width: 200px;">{{ __('Expense # / Title') }}</th>
                        <th style="min-width: 150px;">{{ __('Category') }}</th>
                        <th style="min-width: 140px;">{{ __('Branch') }}</th>
                        <th style="min-width: 150px;">{{ __('Supplier') }}</th>
                        <th style="width: 110px;">{{ __('Date') }}</th>
                        <th style="min-width: 130px; text-align: right;">{{ __('Total') }}</th>
                        <th style="min-width: 110px; text-align: right;">{{ __('Paid') }}</th>
                        <th style="min-width: 120px; text-align: right;">{{ __('Outstanding') }}</th>
                        <th style="width: 110px; text-align: center;">{{ __('Approval') }}</th>
                        <th style="width: 100px; text-align: center;">{{ __('Payment') }}</th>
                        <th style="min-width: 110px; text-align: right;">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expenses as $exp)
                        @php
                            $outstanding = max(0, (float)$exp->total_amount - (float)$exp->paid_amount);
                        @endphp
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.65rem;">
                                    <div style="width: 34px; height: 34px; border-radius: 8px; background: rgba(239, 68, 68, 0.1); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; flex-shrink: 0;">
                                        <i class="fa-solid fa-file-invoice"></i>
                                    </div>
                                    <div>
                                        <a href="{{ route('admin.finance.expenses.show', $exp) }}" style="font-weight: 800; color: var(--text-main); text-decoration: none; font-size: 0.9rem;">
                                            {{ $exp->expense_number }}
                                        </a>
                                        <div style="font-size: 0.775rem; color: var(--text-muted);">{{ $exp->title }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-secondary" style="font-weight: 600;">
                                    {{ $exp->category?->name ?? 'General Expense' }}
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.35rem; font-size: 0.85rem; color: var(--text-main);">
                                    <i class="fa-solid fa-location-dot" style="font-size: 0.75rem; color: #06b6d4;"></i>
                                    <span>{{ $exp->location?->name ?? 'All Branches' }}</span>
                                </div>
                            </td>
                            <td>
                                <span style="font-size: 0.85rem; color: var(--text-main); font-weight: 500;">
                                    {{ $exp->supplier?->name ?? '—' }}
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 0.825rem; color: var(--text-muted); font-weight: 500;">
                                    {{ $exp->expense_date->format('Y-m-d') }}
                                </span>
                            </td>
                            <td style="text-align: right; font-weight: 800; font-size: 0.95rem; color: var(--text-main);">
                                {{ number_format($exp->total_amount, 2) }} {{ $exp->currency }}
                            </td>
                            <td style="text-align: right; font-weight: 700; font-size: 0.9rem; color: #10b981;">
                                {{ number_format($exp->paid_amount, 2) }}
                            </td>
                            <td style="text-align: right; font-weight: 800; font-size: 0.95rem; color: {{ $outstanding > 0 ? '#ef4444' : '#10b981' }};">
                                {{ number_format($outstanding, 2) }}
                            </td>
                            <td style="text-align: center;">
                                @if($exp->status === 'approved')
                                    <span class="badge badge-emerald">{{ __('Approved') }}</span>
                                @elseif($exp->status === 'pending_approval')
                                    <span class="badge badge-amber">{{ __('Pending') }}</span>
                                @elseif($exp->status === 'rejected')
                                    <span class="badge badge-rose">{{ __('Rejected') }}</span>
                                @else
                                    <span class="badge badge-secondary">{{ __('Draft') }}</span>
                                @endif
                            </td>
                            <td style="text-align: center;">
                                @if($exp->payment_status === 'paid')
                                    <span class="badge badge-emerald">{{ __('Paid') }}</span>
                                @elseif($exp->payment_status === 'partially_paid')
                                    <span class="badge badge-amber">{{ __('Partial') }}</span>
                                @else
                                    <span class="badge badge-rose">{{ __('Unpaid') }}</span>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                <a href="{{ route('admin.finance.expenses.show', $exp) }}" class="btn btn-sm btn-secondary" style="padding: 0.4rem 0.75rem;">
                                    <i class="fa-solid fa-wallet"></i>
                                    <span>{{ __('View / Pay') }}</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" style="text-align: center; color: var(--text-muted); padding: 3.5rem 1.5rem;">
                                <div style="width: 54px; height: 54px; border-radius: 16px; background: rgba(239, 68, 68, 0.08); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 1rem;">
                                    <i class="fa-solid fa-receipt"></i>
                                </div>
                                <div style="font-weight: 700; font-size: 1rem; color: var(--text-main); margin-bottom: 0.25rem;">
                                    {{ __('No expenses recorded matching criteria.') }}
                                </div>
                                <p style="font-size: 0.85rem; margin: 0 0 1rem; color: var(--text-muted);">
                                    {{ __('Click Record New Expense to add an operating or supplier invoice.') }}
                                </p>
                                <a href="{{ route('admin.finance.expenses.create') }}" class="btn btn-sm btn-primary">
                                    <i class="fa-solid fa-plus"></i> {{ __('Record New Expense') }}
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($expenses->hasPages())
            <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); background: var(--bg-card);">
                {{ $expenses->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
