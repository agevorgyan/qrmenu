@extends('layouts.app')

@section('title', __('Internal Asset Transfers') . ' - ' . config('app.name'))

@section('content')
<div x-data="{ transferModalOpen: false }" style="display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Header -->
    <div class="card" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fa-solid fa-money-bill-transfer" style="color: #06b6d4;"></i>
                    {{ __('Cash & Asset Transfers') }}
                </h1>
                <p style="margin: 0.25rem 0 0; color: var(--text-muted); font-size: 0.85rem;">
                    {{ __('Internal asset-to-asset transfers (Tills, Safes, Bank Accounts). Strictly non-revenue and non-expense.') }}
                </p>
            </div>

            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <button type="button" @click="transferModalOpen = true" class="btn btn-primary" style="gap: 0.5rem;">
                    <i class="fa-solid fa-plus"></i>
                    <span>{{ __('New Asset Transfer') }}</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Transfers Table -->
    <div class="card" style="padding: 1.5rem;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Transfer #') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Source Asset') }}</th>
                        <th>{{ __('Destination Asset') }}</th>
                        <th style="text-align: right;">{{ __('Amount') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Journal Entry') }}</th>
                        <th>{{ __('Initiated By') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transfers as $trf)
                        <tr>
                            <td style="font-family: monospace; font-weight: 800;">{{ $trf->transfer_number }}</td>
                            <td style="font-size: 0.85rem;">{{ $trf->transfer_date->format('Y-m-d') }}</td>
                            <td>
                                <span class="badge badge-secondary">
                                    <i class="fa-solid fa-arrow-up-from-bracket" style="margin-right: 0.3rem;"></i>
                                    {{ strtoupper($trf->from_type) }} #{{ $trf->from_id }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-secondary">
                                    <i class="fa-solid fa-arrow-down-to-bracket" style="margin-right: 0.3rem;"></i>
                                    {{ strtoupper($trf->to_type) }} #{{ $trf->to_id }}
                                </span>
                            </td>
                            <td style="text-align: right; font-weight: 800; color: #06b6d4; font-size: 1.05rem;">
                                {{ number_format($trf->amount, 2) }} {{ $trf->currency }}
                            </td>
                            <td>
                                <span class="badge badge-emerald">{{ __('Completed') }}</span>
                            </td>
                            <td>
                                @if($trf->journalEntry)
                                    <a href="{{ route('admin.finance.ledger.entries.show', $trf->journalEntry) }}" class="badge badge-primary" style="text-decoration: none;">
                                        {{ $trf->journalEntry->entry_number }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $trf->initiatedByUser?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">
                                {{ __('No asset transfers recorded yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top: 1rem;">
            {{ $transfers->links() }}
        </div>
    </div>

    <!-- New Transfer Modal -->
    <div x-show="transferModalOpen" x-cloak style="position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);">
        <div @click.away="transferModalOpen = false" class="card" style="width: 100%; max-width: 520px; padding: 2rem; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                    {{ __('Execute Internal Asset Transfer') }}
                </h3>
                <button type="button" @click="transferModalOpen = false" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--text-muted);">&times;</button>
            </div>

            <form method="POST" action="{{ route('admin.finance.transfers.store') }}" style="display: flex; flex-direction: column; gap: 1rem;">
                @csrf

                <div>
                    <label class="form-label">{{ __('Transfer Type') }} *</label>
                    <select name="type" class="form-control" required>
                        <option value="safe_to_bank">{{ __('Safe -> Corporate Bank Account (Cash Deposit)') }}</option>
                        <option value="till_to_safe">{{ __('Till Register -> Vault / Safe (Drop)') }}</option>
                        <option value="safe_to_till">{{ __('Vault / Safe -> Till Register (Float Top-up)') }}</option>
                        <option value="bank_to_safe">{{ __('Corporate Bank -> Vault / Safe (Cash Withdrawal)') }}</option>
                        <option value="bank_to_bank">{{ __('Corporate Bank -> Corporate Bank (Inter-account)') }}</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Source Account Type') }} *</label>
                        <select name="source_type" class="form-control" required>
                            <option value="safe">{{ __('Main Vault / Safe') }}</option>
                            <option value="register">{{ __('Cash Register Till') }}</option>
                            <option value="bank_account">{{ __('Bank Account') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Source ID (If Reg/Bank)') }}</label>
                        <input type="number" name="source_id" class="form-control" placeholder="ID or leave empty">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Destination Type') }} *</label>
                        <select name="destination_type" class="form-control" required>
                            <option value="bank_account">{{ __('Bank Account') }}</option>
                            <option value="safe">{{ __('Main Vault / Safe') }}</option>
                            <option value="register">{{ __('Cash Register Till') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Destination ID (If Reg/Bank)') }}</label>
                        <input type="number" name="destination_id" class="form-control" placeholder="ID or leave empty">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Amount') }} *</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="0.00" required>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Transfer Date') }}</label>
                        <input type="date" name="transfer_date" class="form-control" value="{{ date('Y-m-d') }}">
                    </div>
                </div>

                <div>
                    <label class="form-label">{{ __('Notes / Armored Car Bag #') }}</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Deposit bag #88291 picked up by armored courier"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem;">
                    <button type="button" @click="transferModalOpen = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Execute Transfer') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

