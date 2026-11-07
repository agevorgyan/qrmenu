@extends('layouts.app')

@section('title', __('Other Incomes') . ' - ' . config('app.name'))

@section('content')
<div x-data="{ addModalOpen: false }" style="display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Header -->
    <div class="card" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fa-solid fa-hand-holding-dollar" style="color: #10b981;"></i>
                    {{ __('Other Operating Incomes') }}
                </h1>
                <p style="margin: 0.25rem 0 0; color: var(--text-muted); font-size: 0.85rem;">
                    {{ __('Record non-menu revenues such as private event rentals, catering deposits, merchandise, and supplier rebates.') }}
                </p>
            </div>

            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <button type="button" @click="addModalOpen = true" class="btn btn-primary" style="gap: 0.5rem;">
                    <i class="fa-solid fa-plus"></i>
                    <span>{{ __('Record Other Income') }}</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Incomes Table -->
    <div class="card" style="padding: 1.5rem;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Income # / Title') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Branch') }}</th>
                        <th>{{ __('Payer / Customer') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Method / Account') }}</th>
                        <th style="text-align: right;">{{ __('Amount') }}</th>
                        <th>{{ __('Recorded By') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($incomes as $inc)
                        <tr>
                            <td>
                                <div style="font-weight: 800; color: var(--text-main);">{{ $inc->income_number }}</div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">{{ $inc->title }}</div>
                            </td>
                            <td>{{ $inc->category?->name ?? __('Operating Income') }}</td>
                            <td>{{ $inc->location?->name ?? __('All Branches') }}</td>
                            <td>{{ $inc->customer_payer ?? '—' }}</td>
                            <td style="font-size: 0.85rem;">{{ $inc->income_date->format('Y-m-d') }}</td>
                            <td>
                                <span class="badge badge-secondary">{{ strtoupper($inc->payment_method) }}</span>
                                @if($inc->cashRegister)
                                    <span style="font-size: 0.75rem; color: var(--text-muted);">({{ $inc->cashRegister->name }})</span>
                                @elseif($inc->bankAccount)
                                    <span style="font-size: 0.75rem; color: var(--text-muted);">({{ $inc->bankAccount->account_name }})</span>
                                @endif
                            </td>
                            <td style="text-align: right; font-weight: 800; color: #10b981; font-size: 1.05rem;">
                                +{{ number_format($inc->amount, 2) }} {{ $inc->currency }}
                            </td>
                            <td>{{ $inc->createdByUser?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">
                                {{ __('No other incomes recorded yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top: 1rem;">
            {{ $incomes->links() }}
        </div>
    </div>

    <!-- Record Income Modal -->
    <div x-show="addModalOpen" x-cloak style="position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);">
        <div @click.away="addModalOpen = false" class="card" style="width: 100%; max-width: 520px; padding: 2rem; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                    {{ __('Record Other Operating Income') }}
                </h3>
                <button type="button" @click="addModalOpen = false" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--text-muted);">&times;</button>
            </div>

            <form method="POST" action="{{ route('admin.finance.incomes.store') }}" x-data="{ method: 'bank_transfer' }" style="display: flex; flex-direction: column; gap: 1rem;">
                @csrf

                <div>
                    <label class="form-label">{{ __('Income Title') }} *</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. VIP Banquet Hall Rental Deposit" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Category') }}</label>
                        <select name="financial_category_id" class="form-control">
                            <option value="">{{ __('Other Operating Income (4090)') }}</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Branch / Location') }}</label>
                        <select name="location_id" class="form-control">
                            <option value="">{{ __('All Branches') }}</option>
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Amount Received') }} *</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="0.00" required>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Date Received') }} *</label>
                        <input type="date" name="income_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Settlement Method') }} *</label>
                        <select name="payment_method" x-model="method" class="form-control" required>
                            <option value="bank_transfer">{{ __('Bank Transfer') }}</option>
                            <option value="cash">{{ __('Cash into Till') }}</option>
                            <option value="card">{{ __('Card Terminal') }}</option>
                        </select>
                    </div>

                    <div x-show="method === 'bank_transfer'">
                        <label class="form-label">{{ __('Bank Account') }}</label>
                        <select name="bank_account_id" class="form-control">
                            @foreach($bankAccounts as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->account_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div x-show="method === 'cash'">
                        <label class="form-label">{{ __('Cash Register') }}</label>
                        <select name="cash_register_id" class="form-control">
                            @foreach($registers as $reg)
                                <option value="{{ $reg->id }}">{{ $reg->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="form-label">{{ __('Payer / Client Name') }}</label>
                    <input type="text" name="customer_payer" class="form-control" placeholder="e.g. ACME Corp Event Planning">
                </div>

                <div>
                    <label class="form-label">{{ __('Reference # / Notes') }}</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Wire reference TR-88421"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem;">
                    <button type="button" @click="addModalOpen = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Post Income to Ledger') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

