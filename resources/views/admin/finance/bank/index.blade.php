@extends('layouts.app')

@section('title', __('Bank Accounts & Reconciliation') . ' - ' . config('app.name'))

@section('content')
<div x-data="{ addModalOpen: false }" style="max-width: 1400px; margin: 0 auto; width: 100%; display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Page Header -->
    <div class="card" style="padding: 1.5rem; margin-bottom: 0;">
        <div class="page-header-flex" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(59, 130, 246, 0.12); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                    <i class="fa-solid fa-building-columns"></i>
                </div>
                <div>
                    <h1 style="font-size: clamp(1.25rem, 2.5vw, 1.65rem); font-weight: 800; margin: 0; color: var(--text-main); line-height: 1.2;">
                        {{ __('Corporate Bank Accounts & Statements') }}
                    </h1>
                    <p style="margin: 0.35rem 0 0; color: var(--text-muted); font-size: 0.875rem;">
                        {{ __('Manage company bank accounts, import electronic bank statements, and perform periodic ledger reconciliations.') }}
                    </p>
                </div>
            </div>

            <div class="page-header-actions" style="display: flex; align-items: center; gap: 0.75rem;">
                <button type="button" @click="addModalOpen = true" class="btn btn-primary" style="padding: 0.65rem 1.25rem;">
                    <i class="fa-solid fa-plus"></i>
                    <span>{{ __('Add Bank Account') }}</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Accounts Table Card -->
    <div class="card card-flush" style="border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-card);">
        <div class="table-responsive" style="margin-bottom: 0; border: none; border-radius: 0;">
            <table class="table" style="min-width: 880px;">
                <thead>
                    <tr>
                        <th style="min-width: 200px;">{{ __('Bank / Account Name') }}</th>
                        <th style="min-width: 150px;">{{ __('Account Number') }}</th>
                        <th style="min-width: 160px;">{{ __('IBAN / SWIFT') }}</th>
                        <th style="width: 90px; text-align: center;">{{ __('Currency') }}</th>
                        <th style="min-width: 150px; text-align: right;">{{ __('Ledger Balance') }}</th>
                        <th style="min-width: 140px; text-align: center;">{{ __('Unreconciled Lines') }}</th>
                        <th style="width: 100px; text-align: center;">{{ __('Status') }}</th>
                        <th style="min-width: 140px; text-align: right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $acc)
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0;">
                                        <i class="fa-solid fa-landmark"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight: 800; color: var(--text-main); font-size: 0.95rem;">{{ $acc->bank_name }}</div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);">{{ $acc->account_name }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span style="font-family: monospace; font-weight: 700; font-size: 0.9rem; letter-spacing: 0.04em; background: rgba(0,0,0,0.03); padding: 0.2rem 0.5rem; border-radius: 6px;">
                                    {{ $acc->account_number }}
                                </span>
                            </td>
                            <td>
                                <div style="font-family: monospace; font-size: 0.825rem; color: var(--text-main);">
                                    {{ $acc->iban ?? '—' }}
                                </div>
                                @if($acc->swift_bic)
                                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem;">
                                        <span style="font-weight: 600;">SWIFT:</span> {{ $acc->swift_bic }}
                                    </div>
                                @endif
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-secondary" style="font-weight: 700;">{{ $acc->currency }}</span>
                            </td>
                            <td style="text-align: right;">
                                <span style="font-weight: 800; font-size: 1.05rem; color: #3b82f6;">
                                    {{ number_format($acc->current_balance, 2) }} {{ $acc->currency }}
                                </span>
                            </td>
                            <td style="text-align: center;">
                                @if($acc->unreconciled_count > 0)
                                    <span class="badge badge-amber">
                                        <i class="fa-solid fa-clock-rotate-left"></i>
                                        {{ $acc->unreconciled_count }} {{ __('pending') }}
                                    </span>
                                @else
                                    <span class="badge badge-emerald">
                                        <i class="fa-solid fa-circle-check"></i>
                                        {{ __('Reconciled') }}
                                    </span>
                                @endif
                            </td>
                            <td style="text-align: center;">
                                @if($acc->is_active)
                                    <span class="badge badge-emerald">{{ __('Active') }}</span>
                                @else
                                    <span class="badge badge-secondary">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                <a href="{{ route('admin.finance.bank_accounts.show', $acc) }}" class="btn btn-sm btn-primary">
                                    <i class="fa-solid fa-file-invoice"></i>
                                    <span>{{ __('Statement & Match') }}</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 3.5rem 1.5rem;">
                                <div style="width: 54px; height: 54px; border-radius: 16px; background: rgba(59, 130, 246, 0.08); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 1rem;">
                                    <i class="fa-solid fa-building-columns"></i>
                                </div>
                                <div style="font-weight: 700; font-size: 1rem; color: var(--text-main); margin-bottom: 0.25rem;">
                                    {{ __('No bank accounts added yet.') }}
                                </div>
                                <p style="font-size: 0.85rem; margin: 0 0 1rem; color: var(--text-muted);">
                                    {{ __('Click Add Bank Account to connect one.') }}
                                </p>
                                <button type="button" @click="addModalOpen = true" class="btn btn-sm btn-primary">
                                    <i class="fa-solid fa-plus"></i> {{ __('Add Bank Account') }}
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($accounts->hasPages())
            <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); background: var(--bg-card);">
                {{ $accounts->links() }}
            </div>
        @endif
    </div>

    <!-- Add Bank Account Modal -->
    <div x-show="addModalOpen" x-cloak class="modern-modal-overlay">
        <div @click.away="addModalOpen = false" class="modern-modal-box" style="max-width: 560px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(59, 130, 246, 0.12); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
                        <i class="fa-solid fa-building-columns"></i>
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                            {{ __('Add Corporate Bank Account') }}
                        </h3>
                        <p style="margin: 0.15rem 0 0; font-size: 0.8rem; color: var(--text-muted);">
                            {{ __('Connect a new corporate account to the general ledger') }}
                        </p>
                    </div>
                </div>
                <button type="button" @click="addModalOpen = false" class="btn-icon btn-secondary" style="border: none;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('admin.finance.bank_accounts.store') }}" style="display: flex; flex-direction: column; gap: 1.15rem;">
                @csrf

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Bank Name') }} *</label>
                        <input type="text" name="bank_name" class="form-control" placeholder="e.g. Ameriabank CJSC" required>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Account Label / Name') }} *</label>
                        <input type="text" name="account_name" class="form-control" placeholder="e.g. Primary Operating Account" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Account Number') }} *</label>
                        <input type="text" name="account_number" class="form-control" placeholder="e.g. 1570088921880100" required>
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

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
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
                    <span style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 0.25rem;">
                        {{ __('Initial ledger opening balance.') }}
                    </span>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                    <button type="button" @click="addModalOpen = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem;">{{ __('Save Account') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
