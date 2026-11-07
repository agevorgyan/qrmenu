@extends('layouts.app')

@section('title', __('Internal Asset Transfers') . ' - ' . config('app.name'))

@section('content')
<div x-data="{ transferModalOpen: false }" style="max-width: 1400px; margin: 0 auto; width: 100%; display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Page Header -->
    <div class="card" style="padding: 1.5rem; margin-bottom: 0;">
        <div class="page-header-flex" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(6, 182, 212, 0.12); color: #06b6d4; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                    <i class="fa-solid fa-money-bill-transfer"></i>
                </div>
                <div>
                    <h1 style="font-size: clamp(1.25rem, 2.5vw, 1.65rem); font-weight: 800; margin: 0; color: var(--text-main); line-height: 1.2;">
                        {{ __('Cash & Asset Transfers') }}
                    </h1>
                    <p style="margin: 0.35rem 0 0; color: var(--text-muted); font-size: 0.875rem;">
                        {{ __('Internal asset-to-asset transfers (Tills, Safes, Bank Accounts). Strictly non-revenue and non-expense.') }}
                    </p>
                </div>
            </div>

            <div class="page-header-actions" style="display: flex; align-items: center; gap: 0.75rem;">
                <button type="button" @click="transferModalOpen = true" class="btn btn-primary" style="padding: 0.65rem 1.25rem;">
                    <i class="fa-solid fa-plus"></i>
                    <span>{{ __('New Asset Transfer') }}</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Transfers Table Card -->
    <div class="card card-flush" style="border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-card);">
        <div class="table-responsive" style="margin-bottom: 0; border: none; border-radius: 0;">
            <table class="table" style="min-width: 960px;">
                <thead>
                    <tr>
                        <th style="width: 140px;">{{ __('Transfer #') }}</th>
                        <th style="width: 110px;">{{ __('Date') }}</th>
                        <th style="min-width: 180px;">{{ __('Source Asset') }}</th>
                        <th style="min-width: 180px;">{{ __('Destination Asset') }}</th>
                        <th style="min-width: 140px; text-align: right;">{{ __('Amount') }}</th>
                        <th style="width: 110px; text-align: center;">{{ __('Status') }}</th>
                        <th style="min-width: 150px; text-align: center;">{{ __('Journal Entry') }}</th>
                        <th style="min-width: 140px;">{{ __('Initiated By') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transfers as $trf)
                        <tr>
                            <td>
                                <span style="font-family: monospace; font-weight: 800; font-size: 0.9rem; color: #06b6d4; background: rgba(6, 182, 212, 0.08); padding: 0.2rem 0.5rem; border-radius: 6px;">
                                    {{ $trf->transfer_number }}
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">
                                    {{ $trf->transfer_date->format('Y-m-d') }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-secondary" style="font-size: 0.775rem; font-weight: 700;">
                                    <i class="fa-solid fa-arrow-up-from-bracket" style="margin-right: 0.3rem; color: #ef4444;"></i>
                                    {{ strtoupper($trf->from_type) }} #{{ $trf->from_id }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-secondary" style="font-size: 0.775rem; font-weight: 700;">
                                    <i class="fa-solid fa-arrow-down-to-bracket" style="margin-right: 0.3rem; color: #10b981;"></i>
                                    {{ strtoupper($trf->to_type) }} #{{ $trf->to_id }}
                                </span>
                            </td>
                            <td style="text-align: right; font-weight: 800; color: #06b6d4; font-size: 1.05rem;">
                                {{ number_format($trf->amount, 2) }} {{ $trf->currency }}
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-emerald">{{ __('Completed') }}</span>
                            </td>
                            <td style="text-align: center;">
                                @if($trf->journalEntry)
                                    <a href="{{ route('admin.finance.ledger.entries.show', $trf->journalEntry) }}" class="badge badge-blue" style="text-decoration: none; font-family: monospace;">
                                        <i class="fa-solid fa-book-journal-whills" style="font-size: 0.7rem;"></i>
                                        <span>{{ $trf->journalEntry->entry_number }}</span>
                                    </a>
                                @else
                                    <span style="color: var(--text-muted);">—</span>
                                @endif
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem;">
                                    <div style="width: 22px; height: 22px; border-radius: 50%; background: rgba(6, 182, 212, 0.1); color: #06b6d4; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 700;">
                                        {{ mb_substr($trf->initiatedByUser?->name ?? '?', 0, 1) }}
                                    </div>
                                    <span>{{ $trf->initiatedByUser?->name ?? '—' }}</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 3.5rem 1.5rem;">
                                <div style="width: 54px; height: 54px; border-radius: 16px; background: rgba(6, 182, 212, 0.08); color: #06b6d4; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 1rem;">
                                    <i class="fa-solid fa-money-bill-transfer"></i>
                                </div>
                                <div style="font-weight: 700; font-size: 1rem; color: var(--text-main); margin-bottom: 0.25rem;">
                                    {{ __('No asset transfers recorded yet.') }}
                                </div>
                                <p style="font-size: 0.85rem; margin: 0 0 1rem; color: var(--text-muted);">
                                    {{ __('Transfer physical cash between registers and bank accounts safely.') }}
                                </p>
                                <button type="button" @click="transferModalOpen = true" class="btn btn-sm btn-primary">
                                    <i class="fa-solid fa-plus"></i> {{ __('New Asset Transfer') }}
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($transfers->hasPages())
            <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); background: var(--bg-card);">
                {{ $transfers->links() }}
            </div>
        @endif
    </div>

    <!-- New Transfer Modal -->
    <div x-show="transferModalOpen" x-cloak class="modern-modal-overlay">
        <div @click.away="transferModalOpen = false" class="modern-modal-box" style="max-width: 540px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(6, 182, 212, 0.12); color: #06b6d4; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
                        <i class="fa-solid fa-money-bill-transfer"></i>
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                            {{ __('Execute Internal Asset Transfer') }}
                        </h3>
                        <p style="margin: 0.15rem 0 0; font-size: 0.8rem; color: var(--text-muted);">
                            {{ __('Debit destination asset and credit source asset atomically') }}
                        </p>
                    </div>
                </div>
                <button type="button" @click="transferModalOpen = false" class="btn-icon btn-secondary" style="border: none;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('admin.finance.transfers.store') }}" style="display: flex; flex-direction: column; gap: 1.15rem;">
                @csrf

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Source Asset Type') }} *</label>
                        <select name="from_type" class="form-control" required>
                            <option value="cash_register">{{ __('Cash Register') }}</option>
                            <option value="bank_account">{{ __('Bank Account') }}</option>
                            <option value="safe">{{ __('Main Safe / Vault') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Source Identifier / ID') }} *</label>
                        <input type="number" name="from_id" class="form-control" placeholder="e.g. 1" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Destination Asset Type') }} *</label>
                        <select name="to_type" class="form-control" required>
                            <option value="bank_account">{{ __('Bank Account') }}</option>
                            <option value="cash_register">{{ __('Cash Register') }}</option>
                            <option value="safe">{{ __('Main Safe / Vault') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Destination Identifier / ID') }} *</label>
                        <input type="number" name="to_id" class="form-control" placeholder="e.g. 1" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Transfer Amount') }} *</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="100000.00" required>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Currency') }} *</label>
                        <select name="currency" class="form-control" required>
                            <option value="AMD">AMD (֏) — Armenian Dram</option>
                            <option value="USD">USD ($) — US Dollar</option>
                            <option value="EUR">EUR (€) — Euro</option>
                            <option value="RUB">RUB (₽) — Russian Ruble</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="form-label">{{ __('Transfer Date') }} *</label>
                    <input type="date" name="transfer_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>

                <div>
                    <label class="form-label">{{ __('Transfer Memo / Reason') }}</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="{{ __('e.g. Daily cash till collection to main vault...') }}"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                    <button type="button" @click="transferModalOpen = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem;">{{ __('Execute Transfer') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
