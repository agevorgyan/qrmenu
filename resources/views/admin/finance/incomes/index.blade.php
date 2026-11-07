@extends('layouts.app')

@section('title', __('Other Incomes') . ' - ' . config('app.name'))

@section('content')
<div x-data="{ addModalOpen: false }" style="max-width: 1400px; margin: 0 auto; width: 100%; display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Page Header -->
    <div class="card" style="padding: 1.5rem; margin-bottom: 0;">
        <div class="page-header-flex" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(16, 185, 129, 0.12); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                    <i class="fa-solid fa-hand-holding-dollar"></i>
                </div>
                <div>
                    <h1 style="font-size: clamp(1.25rem, 2.5vw, 1.65rem); font-weight: 800; margin: 0; color: var(--text-main); line-height: 1.2;">
                        {{ __('Other Operating Incomes') }}
                    </h1>
                    <p style="margin: 0.35rem 0 0; color: var(--text-muted); font-size: 0.875rem;">
                        {{ __('Record non-menu revenues such as private event rentals, catering deposits, merchandise, and supplier rebates.') }}
                    </p>
                </div>
            </div>

            <div class="page-header-actions" style="display: flex; align-items: center; gap: 0.75rem;">
                <button type="button" @click="addModalOpen = true" class="btn btn-primary" style="padding: 0.65rem 1.25rem;">
                    <i class="fa-solid fa-plus"></i>
                    <span>{{ __('Record Other Income') }}</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Incomes Table Card -->
    <div class="card card-flush" style="border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-card);">
        <div class="table-responsive" style="margin-bottom: 0; border: none; border-radius: 0;">
            <table class="table" style="min-width: 960px;">
                <thead>
                    <tr>
                        <th style="min-width: 180px;">{{ __('Income # / Title') }}</th>
                        <th style="min-width: 150px;">{{ __('Category') }}</th>
                        <th style="min-width: 140px;">{{ __('Branch') }}</th>
                        <th style="min-width: 150px;">{{ __('Payer / Customer') }}</th>
                        <th style="width: 110px;">{{ __('Date') }}</th>
                        <th style="min-width: 160px;">{{ __('Method / Account') }}</th>
                        <th style="min-width: 140px; text-align: right;">{{ __('Amount') }}</th>
                        <th style="min-width: 140px;">{{ __('Recorded By') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($incomes as $inc)
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.65rem;">
                                    <div style="width: 34px; height: 34px; border-radius: 8px; background: rgba(16, 185, 129, 0.1); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; flex-shrink: 0;">
                                        <i class="fa-solid fa-arrow-down-left-and-up-right-to-center"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight: 800; color: var(--text-main); font-size: 0.9rem;">{{ $inc->income_number }}</div>
                                        <div style="font-size: 0.775rem; color: var(--text-muted);">{{ $inc->title }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-secondary" style="font-weight: 600;">
                                    {{ $inc->category?->name ?? __('Operating Income') }}
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.35rem; font-size: 0.85rem; color: var(--text-main);">
                                    <i class="fa-solid fa-location-dot" style="font-size: 0.75rem; color: #06b6d4;"></i>
                                    <span>{{ $inc->location?->name ?? __('All Branches') }}</span>
                                </div>
                            </td>
                            <td>
                                <span style="font-size: 0.85rem; color: var(--text-main); font-weight: 500;">
                                    {{ $inc->customer_payer ?? '—' }}
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 0.825rem; color: var(--text-muted); font-weight: 500;">
                                    {{ $inc->income_date->format('Y-m-d') }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-secondary" style="font-size: 0.725rem; font-weight: 700;">
                                    {{ strtoupper($inc->payment_method) }}
                                </span>
                                @if($inc->cashRegister)
                                    <span style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 0.15rem;">
                                        <i class="fa-solid fa-cash-register" style="font-size: 0.7rem;"></i> {{ $inc->cashRegister->name }}
                                    </span>
                                @elseif($inc->bankAccount)
                                    <span style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 0.15rem;">
                                        <i class="fa-solid fa-building-columns" style="font-size: 0.7rem;"></i> {{ $inc->bankAccount->account_name }}
                                    </span>
                                @endif
                            </td>
                            <td style="text-align: right; font-weight: 800; color: #10b981; font-size: 1.05rem;">
                                +{{ number_format($inc->amount, 2) }} {{ $inc->currency }}
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem;">
                                    <div style="width: 22px; height: 22px; border-radius: 50%; background: rgba(59, 130, 246, 0.1); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 700;">
                                        {{ mb_substr($inc->createdByUser?->name ?? '?', 0, 1) }}
                                    </div>
                                    <span>{{ $inc->createdByUser?->name ?? '—' }}</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 3.5rem 1.5rem;">
                                <div style="width: 54px; height: 54px; border-radius: 16px; background: rgba(16, 185, 129, 0.08); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 1rem;">
                                    <i class="fa-solid fa-hand-holding-dollar"></i>
                                </div>
                                <div style="font-weight: 700; font-size: 1rem; color: var(--text-main); margin-bottom: 0.25rem;">
                                    {{ __('No other incomes recorded yet.') }}
                                </div>
                                <p style="font-size: 0.85rem; margin: 0 0 1rem; color: var(--text-muted);">
                                    {{ __('Record non-menu revenues like catering deposits, private room bookings or merchandise.') }}
                                </p>
                                <button type="button" @click="addModalOpen = true" class="btn btn-sm btn-primary">
                                    <i class="fa-solid fa-plus"></i> {{ __('Record Other Income') }}
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($incomes->hasPages())
            <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); background: var(--bg-card);">
                {{ $incomes->links() }}
            </div>
        @endif
    </div>

    <!-- Record Income Modal -->
    <div x-show="addModalOpen" x-cloak class="modern-modal-overlay">
        <div @click.away="addModalOpen = false" class="modern-modal-box" style="max-width: 540px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(16, 185, 129, 0.12); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
                        <i class="fa-solid fa-hand-holding-dollar"></i>
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                            {{ __('Record Other Operating Income') }}
                        </h3>
                        <p style="margin: 0.15rem 0 0; font-size: 0.8rem; color: var(--text-muted);">
                            {{ __('Recognize revenue and deposit to cash register or bank account') }}
                        </p>
                    </div>
                </div>
                <button type="button" @click="addModalOpen = false" class="btn-icon btn-secondary" style="border: none;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('admin.finance.incomes.store') }}" x-data="{ method: 'bank_transfer' }" style="display: flex; flex-direction: column; gap: 1.15rem;">
                @csrf

                <div>
                    <label class="form-label">{{ __('Income Title') }} *</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. VIP Banquet Hall Rental Deposit" required>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Category') }}</label>
                        <select name="category_id" class="form-control">
                            <option value="">{{ __('Select Category') }}</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Branch / Location') }}</label>
                        <select name="location_id" class="form-control">
                            <option value="">{{ __('All Locations') }}</option>
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Amount') }} *</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="50000.00" required>
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

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Payment Method') }} *</label>
                        <select name="payment_method" x-model="method" class="form-control" required>
                            <option value="bank_transfer">{{ __('Bank Transfer') }}</option>
                            <option value="cash">{{ __('Cash Till Deposit') }}</option>
                            <option value="card">{{ __('Card Terminal') }}</option>
                            <option value="online">{{ __('Online Payment') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Date') }} *</label>
                        <input type="date" name="income_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                </div>

                <div x-show="method === 'bank_transfer'" x-cloak>
                    <label class="form-label">{{ __('Destination Bank Account') }}</label>
                    <select name="bank_account_id" class="form-control">
                        <option value="">{{ __('Select Bank Account') }}</option>
                        @foreach($bankAccounts as $ba)
                            <option value="{{ $ba->id }}">{{ $ba->bank_name }} - {{ $ba->account_name }} ({{ $ba->account_number }})</option>
                        @endforeach
                    </select>
                </div>

                <div x-show="method === 'cash'" x-cloak>
                    <label class="form-label">{{ __('Destination Cash Register') }}</label>
                    <select name="cash_register_id" class="form-control">
                        <option value="">{{ __('Select Cash Register') }}</option>
                        @foreach($cashRegisters as $cr)
                            <option value="{{ $cr->id }}">{{ $cr->name }} ({{ $cr->code }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label">{{ __('Customer / Payer Name') }}</label>
                    <input type="text" name="customer_payer" class="form-control" placeholder="e.g. Acme Events LLC or Armen Sargsyan">
                </div>

                <div>
                    <label class="form-label">{{ __('Internal Memo / Description') }}</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="{{ __('Optional notes regarding invoice, contract, or agreement...') }}"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                    <button type="button" @click="addModalOpen = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem;">{{ __('Save Income') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
