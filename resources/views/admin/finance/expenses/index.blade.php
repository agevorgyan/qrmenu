@extends('layouts.app')

@section('title', __('Expenses & Accounts Payable') . ' - ' . config('app.name'))

@section('content')
<div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Header -->
    <div class="card" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fa-solid fa-receipt" style="color: #ef4444;"></i>
                    {{ __('Expenses & Accounts Payable') }}
                </h1>
                <p style="margin: 0.25rem 0 0; color: var(--text-muted); font-size: 0.85rem;">
                    {{ __('Track operational expenses, approval limits, partial disbursements, and supplier settlements.') }}
                </p>
            </div>

            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <a href="{{ route('admin.finance.expenses.create') }}" class="btn btn-primary" style="gap: 0.5rem;">
                    <i class="fa-solid fa-plus"></i>
                    <span>{{ __('Record New Expense') }}</span>
                </a>
            </div>
        </div>

        <!-- Filters Form -->
        <form method="GET" action="{{ route('admin.finance.expenses.index') }}" style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-top: 1.25rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
            <input type="text" name="search" class="form-control" placeholder="{{ __('Search expense #, title, invoice...') }}" value="{{ request('search') }}" style="max-width: 260px;">

            <select name="status" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <option value="">{{ __('All Statuses') }}</option>
                <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>{{ __('Draft') }}</option>
                <option value="pending_approval" {{ request('status') === 'pending_approval' ? 'selected' : '' }}>{{ __('Pending Approval') }}</option>
                <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>{{ __('Approved') }}</option>
                <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>{{ __('Rejected') }}</option>
            </select>

            <select name="payment_status" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <option value="">{{ __('All Payments') }}</option>
                <option value="unpaid" {{ request('payment_status') === 'unpaid' ? 'selected' : '' }}>{{ __('Unpaid') }}</option>
                <option value="partially_paid" {{ request('payment_status') === 'partially_paid' ? 'selected' : '' }}>{{ __('Partially Paid') }}</option>
                <option value="paid" {{ request('payment_status') === 'paid' ? 'selected' : '' }}>{{ __('Paid') }}</option>
            </select>

            <select name="category_id" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <option value="">{{ __('All Categories') }}</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>

            <select name="location_id" class="form-control" style="width: auto;" onchange="this.form.submit()">
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

    <!-- Expenses Table -->
    <div class="card" style="padding: 1.5rem;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Expense # / Title') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Branch') }}</th>
                        <th>{{ __('Supplier') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th style="text-align: right;">{{ __('Total') }}</th>
                        <th style="text-align: right;">{{ __('Paid') }}</th>
                        <th style="text-align: right;">{{ __('Outstanding') }}</th>
                        <th>{{ __('Approval') }}</th>
                        <th>{{ __('Payment') }}</th>
                        <th style="text-align: right;">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expenses as $exp)
                        @php
                            $outstanding = max(0, (float)$exp->total_amount - (float)$exp->paid_amount);
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.finance.expenses.show', $exp) }}" style="font-weight: 800; color: var(--text-main); text-decoration: none;">
                                    {{ $exp->expense_number }}
                                </a>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">{{ $exp->title }}</div>
                            </td>
                            <td>{{ $exp->category?->name ?? 'General Expense' }}</td>
                            <td>{{ $exp->location?->name ?? 'All Branches' }}</td>
                            <td>{{ $exp->supplier?->name ?? '—' }}</td>
                            <td style="font-size: 0.85rem;">{{ $exp->expense_date->format('Y-m-d') }}</td>
                            <td style="text-align: right; font-weight: 700;">{{ number_format($exp->total_amount, 2) }} {{ $exp->currency }}</td>
                            <td style="text-align: right; font-weight: 600; color: #10b981;">{{ number_format($exp->paid_amount, 2) }}</td>
                            <td style="text-align: right; font-weight: 800; color: {{ $outstanding > 0 ? '#ef4444' : '#10b981' }};">
                                {{ number_format($outstanding, 2) }}
                            </td>
                            <td>
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
                            <td>
                                @if($exp->payment_status === 'paid')
                                    <span class="badge badge-emerald">{{ __('Paid') }}</span>
                                @elseif($exp->payment_status === 'partially_paid')
                                    <span class="badge badge-amber">{{ __('Partial') }}</span>
                                @else
                                    <span class="badge badge-rose">{{ __('Unpaid') }}</span>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                <a href="{{ route('admin.finance.expenses.show', $exp) }}" class="btn btn-xs btn-secondary">
                                    {{ __('View / Pay') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">
                                {{ __('No expenses recorded matching criteria.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top: 1rem;">
            {{ $expenses->links() }}
        </div>
    </div>
</div>
@endsection

