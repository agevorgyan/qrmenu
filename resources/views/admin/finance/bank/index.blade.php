@extends('layouts.app')

@section('title', __('Bank Accounts & Reconciliation') . ' - ' . config('app.name'))

@section('content')
<div x-data="{ addModalOpen: false }" style="display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Header -->
    <div class="card" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fa-solid fa-building-columns" style="color: #3b82f6;"></i>
                    {{ __('Corporate Bank Accounts & Statements') }}
                </h1>
                <p style="margin: 0.25rem 0 0; color: var(--text-muted); font-size: 0.85rem;">
                    {{ __('Manage company bank accounts, import electronic bank statements, and perform periodic ledger reconciliations.') }}
                </p>
            </div>

            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <button type="button" @click="addModalOpen = true" class="btn btn-primary" style="gap: 0.5rem;">
                    <i class="fa-solid fa-plus"></i>
                    <span>{{ __('Add Bank Account') }}</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Accounts Table -->
    <div class="card" style="padding: 1.5rem;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Bank / Account Name') }}</th>
                        <th>{{ __('Account Number') }}</th>
                        <th>{{ __('IBAN / SWIFT') }}</th>
                        <th>{{ __('Currency') }}</th>
                        <th style="text-align: right;">{{ __('Ledger Balance') }}</th>
                        <th>{{ __('Unreconciled Lines') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th style="text-align: right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $acc)
                        <tr>
                            <td>
                                <div style="font-weight: 800; color: var(--text-main);">{{ $acc->bank_name }}</div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">{{ $acc->account_name }}</div>
                            </td>
                            <td style="font-family: monospace; font-weight: 700;">{{ $acc->account_number }}</td>
                            <td style="font-family: monospace; font-size: 0.85rem;">
                                <div>{{ $acc->iban ?? '—' }}</div>
                                @if($acc->swift_bic)
                                    <div style="color: var(--text-muted); font-size: 0.75rem;">BIC: {{ $acc->swift_bic }}</div>
                                @endif
                            </td>
                            <td><span class="badge badge-secondary">{{ $acc->currency }}</span></td>
                            <td style="text-align: right; font-weight: 800; color: #3b82f6; font-size: 1.1rem;">
                                {{ number_format($acc->current_balance, 2) }} {{ $acc->currency }}
                            </td>
                            <td>
                                @if($acc->unreconciled_count > 0)
                                    <span class="badge badge-amber">{{ $acc->unreconciled_count }} {{ __('pending') }}</span>
                                @else
                                    <span class="badge badge-emerald">{{ __('Reconciled') }}</span>
                                @endif
                            </td>
                            <td>
                                @if($acc->is_active)
                                    <span class="badge badge-emerald">{{ __('Active') }}</span>
                                @else
                                    <span class="badge badge-secondary">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                <a href="{{ route('admin.finance.bank_accounts.show', $acc) }}" class="btn btn-xs btn-primary">
                                    <i class="fa-solid fa-file-import"></i> {{ __('Statement & Match') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">
                                {{ __('No bank accounts added yet. Click Add Bank Account to connect one.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top: 1rem;">
            {{ $accounts->links() }}
        </div>
    </div>

    <!-- Add Bank Account Modal -->
    <div x-show="addModalOpen" x-cloak style="position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);">
        <div @click.away="addModalOpen = false" class="card" style="width: 100%; max-width: 520px; padding: 2rem; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                    {{ __('Add Corporate Bank Account') }}
                </h3>
                <button type="button" @click="addModalOpen = false" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--text-muted);">&times;</button>
            </div>

            <form method="POST" action="{{ route('admin.finance.bank_accounts.store') }}" style="display: flex; flex-direction: column; gap: 1rem;">
                @csrf

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Bank Name') }} *</label>
                        <input type="text" name="bank_name" class="form-control" placeholder="e.g. Ameriabank CJSC" required>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Account Label / Name') }} *</label>
                        <input type="text" name="account_name" class="form-control" placeholder="e.g. Primary Operating Account" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Account Number') }} *</label>
                        <input type="text" name="account_number" class="form-control" placeholder="e.g. 1570088921880100" required>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Currency') }} *</label>
                        <select name="currency" class="form-control" required>
                            <option value="AMD">AMD (Armenian Dram)</option>
                            <option value="USD">USD (US Dollar)</option>
                            <option value="EUR">EUR (Euro)</option>
                            <option value="RUB">RUB (Russian Ruble)</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('IBAN') }}</label>
                        <input type="text" name="iban" class="form-control" placeholder="e.g. AM1570088921880100">
                    </div>

                    <div>
                        <label class="form-label">{{ __('SWIFT / BIC') }}</label>
                        <input type="text" name="swift_bic" class="form-control" placeholder="e.g. ARMBAM22">
                    </div>
                </div>

                <div>
                    <label class="form-label">{{ __('Opening Balance') }}</label>
                    <input type="number" step="0.01" min="0" name="opening_balance" class="form-control" placeholder="0.00">
                    <span style="font-size: 0.75rem; color: var(--text-muted);">{{ __('Initial ledger opening balance.') }}</span>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem;">
                    <button type="button" @click="addModalOpen = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Save Account') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

