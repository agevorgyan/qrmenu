@extends('layouts.app')

@section('title', $bankAccount->bank_name . ' - ' . config('app.name'))

@section('content')
<div x-data="{ importModalOpen: false, reconcileModalOpen: false }" style="display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Header -->
    <div class="card" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <a href="{{ route('admin.finance.bank_accounts.index') }}" style="color: var(--text-muted); font-size: 0.85rem; text-decoration: none; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.5rem;">
                    <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Bank Accounts') }}
                </a>
                <h1 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fa-solid fa-building-columns" style="color: #3b82f6;"></i>
                    {{ $bankAccount->bank_name }} — {{ $bankAccount->account_name }}
                    <span class="badge badge-secondary">{{ $bankAccount->currency }}</span>
                </h1>
                <p style="margin: 0.25rem 0 0; color: var(--text-muted); font-size: 0.85rem; font-family: monospace;">
                    {{ __('Account #') }}: {{ $bankAccount->account_number }} | {{ __('IBAN') }}: {{ $bankAccount->iban ?? 'N/A' }}
                </p>
            </div>

            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <button type="button" @click="importModalOpen = true" class="btn btn-secondary">
                    <i class="fa-solid fa-file-import"></i> {{ __('Import Statement (CSV)') }}
                </button>
                <button type="button" @click="reconcileModalOpen = true" class="btn btn-primary">
                    <i class="fa-solid fa-scale-balanced"></i> {{ __('Reconcile Statement') }}
                </button>
            </div>
        </div>
    </div>

    <!-- Balance KPI -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem;">
        <div class="card" style="padding: 1.25rem;">
            <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('General Ledger Book Balance') }}</div>
            <div style="font-size: 1.5rem; font-weight: 800; color: #3b82f6; margin-top: 0.25rem;">
                {{ number_format($bankAccount->current_balance, 2) }} {{ $bankAccount->currency }}
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">{{ __('Real-time Chart of Accounts balance') }}</div>
        </div>

        <div class="card" style="padding: 1.25rem;">
            <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Unreconciled Bank Lines') }}</div>
            <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-top: 0.25rem;">
                {{ $bankAccount->transactions()->where('reconciliation_status', 'unreconciled')->count() }}
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">{{ __('Lines needing GL match') }}</div>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="card" style="padding: 1.5rem;">
        <h3 style="margin-top: 0; font-size: 1.15rem; font-weight: 800; color: var(--text-main);">
            <i class="fa-solid fa-list" style="color: #3b82f6; margin-right: 0.5rem;"></i>
            {{ __('Bank Statement Transactions') }}
        </h3>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Description') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Reference #') }}</th>
                        <th style="text-align: right;">{{ __('Amount') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Matched Ledger Line') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $tx)
                        <tr>
                            <td style="font-size: 0.85rem;">{{ $tx->transaction_date->format('Y-m-d') }}</td>
                            <td>{{ $tx->description ?? '—' }}</td>
                            <td>
                                @if($tx->type === 'credit')
                                    <span class="badge badge-emerald">{{ __('Credit (Inflow)') }}</span>
                                @else
                                    <span class="badge badge-rose">{{ __('Debit (Outflow)') }}</span>
                                @endif
                            </td>
                            <td style="font-family: monospace; font-size: 0.85rem;">{{ $tx->reference_number ?? '—' }}</td>
                            <td style="text-align: right; font-weight: 800; color: {{ $tx->type === 'credit' ? '#10b981' : '#ef4444' }};">
                                {{ $tx->type === 'credit' ? '+' : '-' }}{{ number_format($tx->amount, 2) }}
                            </td>
                            <td>
                                @if($tx->reconciliation_status === 'matched')
                                    <span class="badge badge-emerald">{{ __('Matched') }}</span>
                                @elseif($tx->reconciliation_status === 'reconciled')
                                    <span class="badge badge-primary">{{ __('Reconciled') }}</span>
                                @else
                                    <span class="badge badge-amber">{{ __('Unreconciled') }}</span>
                                @endif
                            </td>
                            <td>
                                @if($tx->matchedJournalEntryLine)
                                    <span style="font-size: 0.8rem; font-family: monospace;">Line #{{ $tx->matchedJournalEntryLine->id }}</span>
                                @else
                                    <span style="color: var(--text-muted); font-size: 0.8rem;">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">
                                {{ __('No bank transactions found. Click Import Statement to upload CSV.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top: 1rem;">
            {{ $transactions->links() }}
        </div>
    </div>

    <!-- Import Statement Modal -->
    <div x-show="importModalOpen" x-cloak style="position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);">
        <div @click.away="importModalOpen = false" class="card" style="width: 100%; max-width: 480px; padding: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                    {{ __('Import Bank Statement CSV') }}
                </h3>
                <button type="button" @click="importModalOpen = false" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--text-muted);">&times;</button>
            </div>

            <form method="POST" action="{{ route('admin.finance.bank_accounts.import', $bankAccount) }}" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 1rem;">
                @csrf

                <div style="padding: 0.75rem; border-radius: 8px; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); font-size: 0.85rem; color: var(--text-main);">
                    {{ __('Expected CSV columns: Date (YYYY-MM-DD), Description, Amount, Type (credit/debit), Reference') }}
                </div>

                <div>
                    <label class="form-label">{{ __('Statement File (CSV)') }} *</label>
                    <input type="file" name="statement_file" class="form-control" accept=".csv,.txt" required>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem;">
                    <button type="button" @click="importModalOpen = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Import Transactions') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reconcile Modal -->
    <div x-show="reconcileModalOpen" x-cloak style="position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);">
        <div @click.away="reconcileModalOpen = false" class="card" style="width: 100%; max-width: 480px; padding: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                    {{ __('Perform Bank Statement Reconciliation') }}
                </h3>
                <button type="button" @click="reconcileModalOpen = false" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--text-muted);">&times;</button>
            </div>

            <form method="POST" action="{{ route('admin.finance.bank_accounts.reconcile', $bankAccount) }}" style="display: flex; flex-direction: column; gap: 1rem;">
                @csrf

                <div>
                    <label class="form-label">{{ __('Statement Date') }} *</label>
                    <input type="date" name="statement_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>

                <div>
                    <label class="form-label">{{ __('Statement Ending Balance') }} *</label>
                    <input type="number" step="0.01" name="statement_balance" class="form-control" placeholder="0.00" required>
                    <span style="font-size: 0.75rem; color: var(--text-muted);">{{ __('Current GL book balance is:') }} {{ number_format($bankAccount->current_balance, 2) }} {{ $bankAccount->currency }}</span>
                </div>

                <div>
                    <label class="form-label">{{ __('Reconciliation Notes') }}</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. End of month statement reconciliation"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem;">
                    <button type="button" @click="reconcileModalOpen = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Save Reconciliation') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

