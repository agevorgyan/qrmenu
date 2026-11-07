@extends('layouts.app')

@section('title', __('Expense ') . $expense->expense_number . ' - ' . config('app.name'))

@section('styles')
<style>
    @media print {
        .no-print {
            display: none !important;
        }
        .printable-voucher-document {
            display: block !important;
            width: 100% !important;
            max-width: 650px !important;
            margin: 0 auto !important;
            padding: 24px !important;
            background: #ffffff !important;
            color: #000000 !important;
            border: 2px solid #000000 !important;
            border-radius: 4px !important;
        }
        .printable-voucher-document * {
            color: #000000 !important;
        }
    }
</style>
@endsection

@section('content')
@php
    $outstanding = max(0, (float)$expense->total_amount - (float)$expense->paid_amount);
@endphp
<div x-data="{ payModalOpen: false }" style="display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Header -->
    <div class="card no-print" style="padding: 1.5rem;">
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
                <button type="button" onclick="window.print()" class="btn btn-secondary">
                    <i class="fa-solid fa-print"></i> {{ __('Print Voucher') }}
                </button>
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
    <div class="no-print" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem;">
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
    <div class="card no-print" style="padding: 1.5rem;">
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
    <div class="card no-print" style="padding: 1.5rem;">
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

    <!-- Official Printable Disbursement Voucher Document -->
    <div class="print-only printable-voucher-document">
        <div style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 12px; margin-bottom: 16px;">
            <h2 style="font-size: 16pt; font-weight: 800; margin: 0 0 4px 0;">{{ auth()->user()?->vendor?->name }}</h2>
            <div style="font-size: 12pt; font-weight: 700; text-transform: uppercase;">{{ __('PAYMENT DISBURSEMENT VOUCHER') }}</div>
            <div style="font-size: 9pt; color: #444; margin-top: 2px;">{{ $expense->location?->name ?? __('All Branches') }}</div>
        </div>

        <div style="display: flex; justify-content: space-between; font-size: 9.5pt; margin-bottom: 8px;">
            <div><strong>{{ __('Voucher #:') }}</strong> {{ $expense->expense_number }}</div>
            <div><strong>{{ __('Date:') }}</strong> {{ $expense->expense_date->format('Y-m-d') }}</div>
        </div>

        <div style="display: flex; justify-content: space-between; font-size: 9.5pt; margin-bottom: 8px;">
            <div><strong>{{ __('Payee (Supplier):') }}</strong> {{ $expense->supplier?->name ?? '—' }}</div>
            <div><strong>{{ __('Category:') }}</strong> {{ $expense->category?->name ?? 'General Expense' }}</div>
        </div>

        <div style="font-size: 9.5pt; margin-bottom: 12px;">
            <strong>{{ __('Description / Purpose:') }}</strong> {{ $expense->title }}
            @if($expense->notes)
                <div style="font-size: 8.5pt; color: #444; margin-top: 3px;">{{ $expense->notes }}</div>
            @endif
        </div>

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 16px;">
            <thead>
                <tr>
                    <th style="text-align: left; border-bottom: 1.5px solid #000; padding: 6px;">{{ __('Expense Item / Detail') }}</th>
                    <th style="text-align: right; border-bottom: 1.5px solid #000; padding: 6px;">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="padding: 6px; border-bottom: 1px solid #ddd;">{{ $expense->title }} ({{ $expense->category?->name }})</td>
                    <td style="text-align: right; padding: 6px; border-bottom: 1px solid #ddd; font-family: monospace; font-weight: 700;">
                        {{ number_format($expense->total_amount, 2) }} {{ $expense->currency }}
                    </td>
                </tr>
                @if($expense->tax_amount > 0)
                    <tr>
                        <td style="padding: 6px; border-bottom: 1px solid #ddd;">{{ __('Included Taxes') }}</td>
                        <td style="text-align: right; padding: 6px; border-bottom: 1px solid #ddd; font-family: monospace;">
                            {{ number_format($expense->tax_amount, 2) }} {{ $expense->currency }}
                        </td>
                    </tr>
                @endif
            </tbody>
            <tfoot>
                <tr>
                    <td style="padding: 8px 6px; font-weight: 800; border-top: 1.5px solid #000;">{{ __('TOTAL VOUCHER AMOUNT:') }}</td>
                    <td style="text-align: right; padding: 8px 6px; font-weight: 800; border-top: 1.5px solid #000; font-family: monospace; font-size: 11pt;">
                        {{ number_format($expense->total_amount, 2) }} {{ $expense->currency }}
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px 6px; font-weight: 600;">{{ __('Total Paid to Date:') }}</td>
                    <td style="text-align: right; padding: 4px 6px; font-weight: 700; font-family: monospace;">
                        {{ number_format($expense->paid_amount, 2) }} {{ $expense->currency }}
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px 6px; font-weight: 600;">{{ __('Remaining Balance:') }}</td>
                    <td style="text-align: right; padding: 4px 6px; font-weight: 700; font-family: monospace;">
                        {{ number_format($outstanding, 2) }} {{ $expense->currency }}
                    </td>
                </tr>
            </tfoot>
        </table>

        <!-- Signatures (3 cols: Issued by, Approved by, Received by) -->
        <div style="display: flex; justify-content: space-between; margin-top: 40px; padding-top: 10px; page-break-inside: avoid;">
            <div style="width: 30%; text-align: center;">
                <div style="border-bottom: 1px solid #000; height: 35px; margin-bottom: 6px;"></div>
                <div style="font-size: 8pt; font-weight: 700;">{{ __('Prepared / Issued By') }}</div>
                <div style="font-size: 7.5pt; color: #555;">{{ $expense->createdByUser?->name ?? auth()->user()?->name }}</div>
            </div>
            <div style="width: 30%; text-align: center;">
                <div style="border-bottom: 1px solid #000; height: 35px; margin-bottom: 6px;"></div>
                <div style="font-size: 8pt; font-weight: 700;">{{ __('Approved By') }}</div>
                <div style="font-size: 7.5pt; color: #555;">{{ $expense->approvedByUser?->name ?? '________________' }}</div>
            </div>
            <div style="width: 30%; text-align: center;">
                <div style="border-bottom: 1px solid #000; height: 35px; margin-bottom: 6px;"></div>
                <div style="font-size: 8pt; font-weight: 700;">{{ __('Received By (Payee Signature)') }}</div>
                <div style="font-size: 7.5pt; color: #555;">{{ $expense->supplier?->name ?? '________________' }}</div>
            </div>
        </div>

        <div style="text-align: center; margin-top: 25px; font-size: 7.5pt; color: #777;">
            {{ __('Generated by') }} {{ config('app.name') }} | {{ now()->format('Y-m-d H:i') }}
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

