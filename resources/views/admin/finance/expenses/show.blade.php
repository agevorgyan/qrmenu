@extends('layouts.app')

@section('title', __('Expense ') . $expense->expense_number . ' - ' . config('app.name'))

@section('content')
@php
    $outstanding = max(0, (float)$expense->total_amount - (float)$expense->paid_amount);
@endphp
<div x-data="{ payModalOpen: false }" style="display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Header -->
    <div class="card" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <a href="{{ route('admin.finance.expenses.index') }}" style="color: var(--text-muted); font-size: 0.85rem; text-decoration: none; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.5rem;">
                    <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Expenses') }}
                </a>
                <h1 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fa-solid fa-receipt" style="color: #ef4444;"></i>
                    {{ $expense->expense_number }}
                    @if($expense->status === 'approved')
                        <span class="badge badge-emerald">{{ __('Approved') }}</span>
                    @elseif($expense->status === 'pending_approval')
                        <span class="badge badge-amber">{{ __('Pending Approval') }}</span>
                    @elseif($expense->status === 'rejected')
                        <span class="badge badge-rose">{{ __('Rejected') }}</span>
                    @endif

                    @if($expense->payment_status === 'paid')
                        <span class="badge badge-emerald">{{ __('Fully Paid') }}</span>
                    @elseif($expense->payment_status === 'partially_paid')
                        <span class="badge badge-amber">{{ __('Partially Paid') }}</span>
                    @else
                        <span class="badge badge-rose">{{ __('Unpaid') }}</span>
                    @endif
                </h1>
                <p style="margin: 0.25rem 0 0; color: var(--text-muted); font-size: 0.85rem;">
                    {{ $expense->title }} | {{ __('Date') }}: {{ $expense->expense_date->format('Y-m-d') }}
                </p>
            </div>

            <div style="display: flex; align-items: center; gap: 0.75rem;">
                @if($expense->status === 'pending_approval')
                    @can('finance.expenses.approve')
                        <form method="POST" action="{{ route('admin.finance.expenses.approve', $expense) }}">
                            @csrf
                            <button type="submit" class="btn btn-emerald">
                                <i class="fa-solid fa-check"></i> {{ __('Approve Expense') }}
                            </button>
                        </form>

                        <form method="POST" action="{{ route('admin.finance.expenses.reject', $expense) }}">
                            @csrf
                            <button type="submit" class="btn btn-rose">
                                <i class="fa-solid fa-xmark"></i> {{ __('Reject') }}
                            </button>
                        </form>
                    @endcan
                @endif

                @if($expense->status === 'approved' && $outstanding > 0)
                    @can('finance.expenses.pay')
                        <button type="button" @click="payModalOpen = true" class="btn btn-primary">
                            <i class="fa-solid fa-money-bill-wave"></i> {{ __('Disburse Payment') }}
                        </button>
                    @endcan
                @endif
            </div>
        </div>
    </div>

    <!-- Financial Amounts Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem;">
        <div class="card" style="padding: 1.25rem;">
            <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Total Recognized') }}</div>
            <div style="font-size: 1.4rem; font-weight: 800; color: var(--text-main); margin-top: 0.25rem;">
                {{ number_format($expense->total_amount, 2) }} {{ $expense->currency }}
            </div>
            @if($expense->tax_amount > 0)
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">{{ __('Incl. Tax:') }} {{ number_format($expense->tax_amount, 2) }}</div>
            @endif
        </div>

        <div class="card" style="padding: 1.25rem;">
            <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Total Paid') }}</div>
            <div style="font-size: 1.4rem; font-weight: 800; color: #10b981; margin-top: 0.25rem;">
                {{ number_format($expense->paid_amount, 2) }} {{ $expense->currency }}
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">{{ __('Settled disbursements') }}</div>
        </div>

        <div class="card" style="padding: 1.25rem;">
            <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Outstanding Due') }}</div>
            <div style="font-size: 1.4rem; font-weight: 800; color: {{ $outstanding > 0 ? '#ef4444' : '#10b981' }}; margin-top: 0.25rem;">
                {{ number_format($outstanding, 2) }} {{ $expense->currency }}
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ $expense->due_date ? __('Due: ') . $expense->due_date->format('Y-m-d') : __('No due date') }}
            </div>
        </div>
    </div>

    <!-- Metadata Details -->
    <div class="card" style="padding: 1.5rem;">
        <h3 style="margin-top: 0; font-size: 1.15rem; font-weight: 800; color: var(--text-main);">
            {{ __('Expense Metadata') }}
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">{{ __('Category') }}</div>
                <div style="font-weight: 700; color: var(--text-main);">{{ $expense->category?->name ?? 'General Expense' }}</div>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">{{ __('Branch / Location') }}</div>
                <div style="font-weight: 700; color: var(--text-main);">{{ $expense->location?->name ?? 'All Branches' }}</div>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">{{ __('Supplier / Vendor') }}</div>
                <div style="font-weight: 700; color: var(--text-main);">{{ $expense->supplier?->name ?? '—' }}</div>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">{{ __('Invoice / Tax Ref') }}</div>
                <div style="font-weight: 700; font-family: monospace; color: var(--text-main);">{{ $expense->invoice_number ?? '—' }}</div>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">{{ __('Recorded By') }}</div>
                <div style="font-weight: 700; color: var(--text-main);">{{ $expense->createdByUser?->name ?? '—' }}</div>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">{{ __('Approved By') }}</div>
                <div style="font-weight: 700; color: var(--text-main);">{{ $expense->approvedByUser?->name ?? '—' }}</div>
            </div>
        </div>

        @if($expense->notes)
            <div style="margin-top: 1rem; padding: 0.75rem; border-radius: 8px; background: var(--bg-surface); border: 1px solid var(--border-color); font-size: 0.85rem;">
                <strong>{{ __('Notes:') }}</strong> {{ $expense->notes }}
            </div>
        @endif
    </div>

    <!-- Payments History Table -->
    <div class="card" style="padding: 1.5rem;">
        <h3 style="margin-top: 0; font-size: 1.15rem; font-weight: 800; color: var(--text-main);">
            <i class="fa-solid fa-money-check-dollar" style="color: #10b981; margin-right: 0.5rem;"></i>
            {{ __('Disbursement Payments History') }}
        </h3>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Payment #') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Payment Method') }}</th>
                        <th>{{ __('Source Account') }}</th>
                        <th style="text-align: right;">{{ __('Amount Paid') }}</th>
                        <th>{{ __('Ref / Notes') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expense->payments as $pay)
                        <tr>
                            <td style="font-family: monospace; font-weight: 700;">#{{ $pay->id }}</td>
                            <td>{{ $pay->payment_date->format('Y-m-d') }}</td>
                            <td><span class="badge badge-secondary">{{ strtoupper($pay->payment_method) }}</span></td>
                            <td>
                                @if($pay->cashRegister)
                                    <i class="fa-solid fa-cash-register" style="color: #f59e0b;"></i> {{ $pay->cashRegister->name }}
                                @elseif($pay->bankAccount)
                                    <i class="fa-solid fa-building-columns" style="color: #3b82f6;"></i> {{ $pay->bankAccount->account_name }}
                                @else
                                    —
                                @endif
                            </td>
                            <td style="text-align: right; font-weight: 800; color: #10b981;">
                                {{ number_format($pay->amount, 2) }} {{ $expense->currency }}
                            </td>
                            <td>{{ $pay->reference_number ?? $pay->notes ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                                {{ __('No payments disbursed yet for this expense.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Disburse Payment Modal -->
    <div x-show="payModalOpen" x-cloak style="position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);">
        <div @click.away="payModalOpen = false" class="card" style="width: 100%; max-width: 480px; padding: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                    {{ __('Disburse Expense Payment') }}
                </h3>
                <button type="button" @click="payModalOpen = false" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--text-muted);">&times;</button>
            </div>

            <form method="POST" action="{{ route('admin.finance.expenses.pay', $expense) }}" x-data="{ method: 'bank_transfer' }" style="display: flex; flex-direction: column; gap: 1rem;">
                @csrf

                <div>
                    <label class="form-label">{{ __('Payment Amount') }} *</label>
                    <input type="number" step="0.01" min="0.01" max="{{ $outstanding }}" name="amount" class="form-control" value="{{ $outstanding }}" required>
                    <span style="font-size: 0.75rem; color: var(--text-muted);">{{ __('Max payable outstanding:') }} {{ number_format($outstanding, 2) }} {{ $expense->currency }}</span>
                </div>

                <div>
                    <label class="form-label">{{ __('Payment Method') }} *</label>
                    <select name="payment_method" x-model="method" class="form-control" required>
                        <option value="bank_transfer">{{ __('Corporate Bank Transfer') }}</option>
                        <option value="cash">{{ __('Cash from Active Till Register') }}</option>
                        <option value="card">{{ __('Corporate Card') }}</option>
                    </select>
                </div>

                <div x-show="method === 'bank_transfer'">
                    <label class="form-label">{{ __('Select Bank Account') }}</label>
                    <select name="bank_account_id" class="form-control">
                        @foreach($bankAccounts as $bank)
                            <option value="{{ $bank->id }}">{{ $bank->bank_name }} - {{ $bank->account_name }} ({{ number_format($bank->current_balance, 2) }} {{ $bank->currency }})</option>
                        @endforeach
                    </select>
                </div>

                <div x-show="method === 'cash'">
                    <label class="form-label">{{ __('Select Cash Register Till') }}</label>
                    <select name="cash_register_id" class="form-control">
                        @foreach($registers as $reg)
                            <option value="{{ $reg->id }}">{{ $reg->name }} (Till: {{ number_format($reg->current_balance, 2) }} {{ $reg->currency }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label">{{ __('Bank Reference / Transaction #') }}</label>
                    <input type="text" name="reference_number" class="form-control" placeholder="e.g. TR-99824">
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem;">
                    <button type="button" @click="payModalOpen = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Post Payment to Ledger') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

