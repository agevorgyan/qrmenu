@extends('layouts.app')

@section('title', __('Salary Payouts') . ' - ' . $vendor->name)

@section('content')
<div x-data="{
    showRecordModal: false,
    selectedItem: null,
    amountToPay: 0,
    onItemChange(e) {
        const opt = e.target.selectedOptions[0];
        if (opt) {
            this.amountToPay = opt.dataset.remaining || 0;
        }
    }
}" style="display: flex; flex-direction: column; gap: 1.5rem;">

    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="min-width: 0; flex: 1;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span style="background: rgba(5, 150, 105, 0.15); color: #059669; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-money-bill-wave"></i>
                </span>
                <div>
                    <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.35rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0;">
                        {{ __('Salary Payouts') }}
                    </h1>
                    <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0.25rem 0 0;">
                        {{ __('Record and audit salary disbursements, bank transfers, and cash payout tranches') }}
                    </p>
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <a href="{{ route('admin.hr.payroll.index') }}" class="btn btn-secondary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px;">
                <i class="fa-solid fa-calculator"></i> {{ __('Payroll Periods') }}
            </a>
            @can('hr.payroll.pay')
                <button type="button" @click="showRecordModal = true" class="btn btn-primary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px; box-shadow: 0 4px 14px rgba(5, 150, 105, 0.3);">
                    <i class="fa-solid fa-plus-circle"></i> {{ __('Record Payout') }}
                </button>
            @endcan
        </div>
    </div>

    <!-- Payouts Table -->
    <div class="card" style="border-radius: 14px; overflow: hidden; padding: 0;">
        <div class="table-responsive">
            <table class="table" style="margin: 0; vertical-align: middle;">
                <thead style="background: rgba(255,255,255,0.03); border-bottom: 1px solid var(--border-color);">
                    <tr>
                        <th style="padding: 1rem 1.25rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Payment Date') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Employee') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Payroll Period') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Amount (AMD)') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Method') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Reference #') }}</th>
                        <th style="padding: 1rem 1.25rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; text-align: right;">{{ __('Recorded By') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $pmt)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-main);">
                                {{ \Carbon\Carbon::parse($pmt->payment_date)->format('d.m.Y H:i') }}
                            </td>
                            <td style="padding: 1rem 1rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(5, 150, 105, 0.15); color: #059669; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem; flex-shrink: 0;">
                                        {{ strtoupper(mb_substr($pmt->user?->name ?? '?', 0, 1)) }}
                                    </div>
                                    <div style="font-weight: 700; color: var(--text-main);">
                                        {{ $pmt->user?->name }}
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 1rem 1rem; color: var(--text-muted);">
                                {{ $pmt->period?->name ?? '—' }}
                            </td>
                            <td style="padding: 1rem 1rem; font-weight: 900; color: #22c55e; font-size: 1.05rem;">
                                {{ number_format((float) $pmt->amount, 2) }} AMD
                            </td>
                            <td style="padding: 1rem 1rem;">
                                <span class="badge" style="background: rgba(255,255,255,0.08); text-transform: capitalize;">
                                    {{ str_replace('_', ' ', $pmt->payment_method) }}
                                </span>
                            </td>
                            <td style="padding: 1rem 1rem; font-family: monospace; font-size: 0.85rem;">
                                {{ $pmt->reference_number ?? '—' }}
                            </td>
                            <td style="padding: 1rem 1.25rem; text-align: right; color: var(--text-muted); font-size: 0.85rem;">
                                {{ $pmt->creator?->name ?? 'Admin' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 3rem 1.5rem; color: var(--text-muted);">
                                <div style="font-size: 2.5rem; color: var(--border-color); margin-bottom: 0.75rem;">
                                    <i class="fa-solid fa-receipt"></i>
                                </div>
                                <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0 0 0.25rem; color: var(--text-main);">
                                    {{ __('No salary payouts recorded yet') }}
                                </h3>
                                <p style="font-size: 0.85rem; margin: 0 0 1rem;">
                                    {{ __('Once payroll is approved, record disbursements to complete the pay cycle.') }}
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($payments->hasPages())
            <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--border-color);">
                {{ $payments->links() }}
            </div>
        @endif
    </div>

    <!-- RECORD PAYOUT MODAL -->
    <div x-show="showRecordModal" x-cloak class="modal-backdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div @click.away="showRecordModal = false" class="card" style="width: 100%; max-width: 500px; padding: 1.5rem; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">{{ __('Record Salary Disbursement') }}</h3>
                <button type="button" @click="showRecordModal = false" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.hr.payments.store') }}">
                @csrf
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Select Employee & Period') }} <span style="color: #ef4444;">*</span></label>
                        <select name="payroll_item_id" @change="onItemChange($event)" class="form-control" required style="border-radius: 10px; height: 42px;">
                            <option value="">{{ __('Select...') }}</option>
                            @foreach($unpaidItems as $ui)
                                @php
                                    $rem = max(0, (float)$ui->net_pay - (float)$ui->paid_amount);
                                @endphp
                                <option value="{{ $ui->id }}" data-remaining="{{ $rem }}">
                                    {{ $ui->user?->name }} • {{ $ui->period?->name }} ({{ __('Rem: :rem AMD', ['rem' => number_format($rem, 2)]) }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Payout Amount (AMD)') }} <span style="color: #ef4444;">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="amount" x-model="amountToPay" class="form-control" required style="border-radius: 10px; height: 42px;">
                    </div>

                    <div>
                        <label class="form-label">{{ __('Payment Method') }}</label>
                        <select name="payment_method" class="form-control" required style="border-radius: 10px; height: 42px;">
                            <option value="bank_transfer">{{ __('Bank Transfer') }}</option>
                            <option value="cash">{{ __('Cash') }}</option>
                            <option value="other">{{ __('Other') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Bank Reference # / Transaction ID') }}</label>
                        <input type="text" name="reference_number" placeholder="{{ __('Optional bank transaction ID') }}" class="form-control" style="border-radius: 10px; height: 42px;">
                    </div>

                    <div>
                        <label class="form-label">{{ __('Notes') }}</label>
                        <input type="text" name="notes" placeholder="{{ __('Optional payment note') }}" class="form-control" style="border-radius: 10px; height: 42px;">
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="button" @click="showRecordModal = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Save Payout') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

