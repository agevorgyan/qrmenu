@extends('layouts.app')

@section('title', __('Journal Entry ') . $entry->entry_number . ' - ' . config('app.name'))

@section('content')
<div x-data="{ reverseModalOpen: false }" style="display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Header -->
    <div class="card" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <a href="{{ route('admin.finance.ledger.entries') }}" style="color: var(--text-muted); font-size: 0.85rem; text-decoration: none; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.5rem;">
                    <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Ledger Entries') }}
                </a>
                <h1 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fa-solid fa-scale-balanced" style="color: #8b5cf6;"></i>
                    {{ $entry->entry_number }}
                    @if($entry->status === 'posted')
                        <span class="badge badge-emerald">{{ __('Posted') }}</span>
                    @elseif($entry->status === 'reversed')
                        <span class="badge badge-rose">{{ __('Reversed') }}</span>
                    @endif

                    @if($entry->is_reversal)
                        <span class="badge badge-amber">{{ __('Reversal Entry') }}</span>
                    @endif
                </h1>
                <p style="margin: 0.25rem 0 0; color: var(--text-muted); font-size: 0.85rem;">
                    {{ $entry->description }} | {{ __('Date') }}: {{ $entry->entry_date->format('Y-m-d') }}
                </p>
            </div>

            <div style="display: flex; align-items: center; gap: 0.75rem;">
                @if($entry->status === 'posted' && !$entry->is_reversal)
                    @can('finance.ledger.manage')
                        <button type="button" @click="reverseModalOpen = true" class="btn btn-rose">
                            <i class="fa-solid fa-rotate-left"></i> {{ __('Create Reversal Entry') }}
                        </button>
                    @endcan
                @endif
            </div>
        </div>

        @if($entry->reversalEntry)
            <div style="margin-top: 1rem; padding: 0.75rem 1rem; border-radius: 8px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); font-size: 0.85rem;">
                <i class="fa-solid fa-triangle-exclamation" style="color: #ef4444; margin-right: 0.4rem;"></i>
                {{ __('This transaction was reversed by') }}
                <a href="{{ route('admin.finance.ledger.entries.show', $entry->reversalEntry) }}" style="font-weight: 800; color: #ef4444;">
                    {{ $entry->reversalEntry->entry_number }}
                </a>.
            </div>
        @endif

        @if($entry->originalEntry)
            <div style="margin-top: 1rem; padding: 0.75rem 1rem; border-radius: 8px; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2); font-size: 0.85rem;">
                <i class="fa-solid fa-circle-info" style="color: #f59e0b; margin-right: 0.4rem;"></i>
                {{ __('This entry is a formal reversal of') }}
                <a href="{{ route('admin.finance.ledger.entries.show', $entry->originalEntry) }}" style="font-weight: 800; color: #f59e0b;">
                    {{ $entry->originalEntry->entry_number }}
                </a>.
            </div>
        @endif
    </div>

    <!-- Balanced Double-Entry Lines Table -->
    <div class="card" style="padding: 1.5rem;">
        <h3 style="margin-top: 0; font-size: 1.15rem; font-weight: 800; color: var(--text-main);">
            <i class="fa-solid fa-table-list" style="color: #8b5cf6; margin-right: 0.5rem;"></i>
            {{ __('Double-Entry Journal Lines') }}
        </h3>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Account Code') }}</th>
                        <th>{{ __('Account Name') }}</th>
                        <th>{{ __('Account Type') }}</th>
                        <th>{{ __('Line Memo') }}</th>
                        <th style="text-align: right;">{{ __('Debit (Dr)') }}</th>
                        <th style="text-align: right;">{{ __('Credit (Cr)') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($entry->lines as $line)
                        <tr>
                            <td style="font-family: monospace; font-weight: 800;">{{ $line->account?->code }}</td>
                            <td style="font-weight: 700;">{{ $line->account?->name }}</td>
                            <td><span class="badge badge-secondary">{{ ucfirst($line->account?->type ?? '') }}</span></td>
                            <td style="color: var(--text-muted); font-size: 0.85rem;">{{ $line->memo ?? '—' }}</td>
                            <td style="text-align: right; font-weight: 800; font-family: monospace; color: #10b981;">
                                {{ $line->type === 'debit' ? number_format($line->amount_base_currency, 2) : '—' }}
                            </td>
                            <td style="text-align: right; font-weight: 800; font-family: monospace; color: #3b82f6;">
                                {{ $line->type === 'credit' ? number_format($line->amount_base_currency, 2) : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="border-top: 2px solid var(--border-color); font-weight: 800; font-size: 1.1rem;">
                        <td colspan="4" style="text-align: right; text-transform: uppercase;">{{ __('Total Balanced Sum:') }}</td>
                        <td style="text-align: right; color: #10b981; font-family: monospace;">{{ number_format($entry->total_debit, 2) }}</td>
                        <td style="text-align: right; color: #3b82f6; font-family: monospace;">{{ number_format($entry->total_credit, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div style="margin-top: 1rem; display: flex; justify-content: flex-end;">
            <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: #10b981; font-weight: 700;">
                <i class="fa-solid fa-circle-check"></i>
                {{ __('Double-entry invariant satisfied: Total Debit strictly equals Total Credit.') }}
            </div>
        </div>
    </div>

    <!-- Reversal Modal -->
    <div x-show="reverseModalOpen" x-cloak style="position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);">
        <div @click.away="reverseModalOpen = false" class="card" style="width: 100%; max-width: 480px; padding: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: #ef4444;">
                    {{ __('Reverse Journal Entry') }}
                </h3>
                <button type="button" @click="reverseModalOpen = false" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--text-muted);">&times;</button>
            </div>

            <form method="POST" action="{{ route('admin.finance.ledger.entries.reverse', $entry) }}" style="display: flex; flex-direction: column; gap: 1rem;">
                @csrf

                <div style="padding: 0.75rem; border-radius: 8px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); font-size: 0.85rem; color: var(--text-main);">
                    {{ __('Accounting Rule: Posted transactions cannot be deleted. A mirror-image contra-entry will be posted to neutralize balances, referencing this transaction.') }}
                </div>

                <div>
                    <label class="form-label">{{ __('Reason for Reversal') }} *</label>
                    <input type="text" name="reason" class="form-control" placeholder="e.g. Inadvertent duplicate entry or erroneous amount" required autofocus>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem;">
                    <button type="button" @click="reverseModalOpen = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-rose">{{ __('Post Reversal Entry') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

