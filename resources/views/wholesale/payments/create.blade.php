@extends('layouts.app')

@section('title', __('Record Wholesale Payment'))

@section('content')
<div class="content-wrapper" style="max-width: 780px; margin: 0 auto;">
    <!-- Header -->
    <div style="margin-bottom: 2rem;">
        <a href="{{ route('admin.wholesale.payments.index') }}" style="color: #6366f1; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;">
            <i class="fa-solid fa-arrow-left"></i>
            <span>{{ __('Back to Payments') }}</span>
        </a>
        <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
            {{ __('Record Payment / Collection') }}
        </h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
            {{ __('Post cash receipt or bank transfer, credit customer AR balance, and balance General Ledger accounts (2211, 2511, 2521).') }}
        </p>
    </div>

    @if($errors->any())
        <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 12px; padding: 1rem 1.25rem; color: #ef4444; margin-bottom: 1.5rem;">
            <div style="font-weight: 700; margin-bottom: 0.5rem;">{{ __('Please correct the following errors:') }}</div>
            <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.88rem;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.wholesale.payments.store') }}" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
        @csrf

        @if($selectedSale)
            <input type="hidden" name="sale_id" value="{{ $selectedSale->id }}">
            <div style="background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
                <div style="font-weight: 700; color: #6366f1; font-size: 0.9rem; margin-bottom: 0.2rem;">
                    {{ __('Applying payment towards Sale Invoice :num', ['num' => $selectedSale->invoice_number]) }}
                </div>
                <div style="font-size: 0.82rem; color: var(--text-muted);">
                    {{ __('Invoice Total') }}: {{ number_format($selectedSale->total_amount, 2) }} AMD &bull;
                    {{ __('Currently Paid') }}: {{ number_format($selectedSale->paid_amount, 2) }} AMD &bull;
                    {{ __('Balance Due') }}: <strong>{{ number_format(max(0, (float)$selectedSale->total_amount - (float)$selectedSale->paid_amount), 2) }} AMD</strong>
                </div>
            </div>
        @endif

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Paying Customer') }} <span style="color: #ef4444;">*</span>
                </label>
                <select name="customer_id" required style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" {{ (old('customer_id', $customerId ?? ($selectedSale?->customer_id))) == $c->id ? 'selected' : '' }}>
                            {{ $c->name }} ({{ __('AR Balance') }}: {{ number_format($c->current_balance, 2) }} AMD)
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Receiving Seller Entity') }} <span style="color: #ef4444;">*</span>
                </label>
                <select name="seller_id" required style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
                    @foreach($sellers as $s)
                        <option value="{{ $s->id }}" {{ (old('seller_id', $selectedSale?->seller_id)) == $s->id ? 'selected' : '' }}>
                            {{ $s->name }} (TIN: {{ $s->tin }})
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Payment Amount (AMD)') }} <span style="color: #ef4444;">*</span>
                </label>
                <input type="number" step="0.01" name="amount" value="{{ old('amount', $selectedSale ? max(0, (float)$selectedSale->total_amount - (float)$selectedSale->paid_amount) : '') }}" required placeholder="100000.00"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.95rem; font-weight: 800;">
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Payment Method') }} <span style="color: #ef4444;">*</span>
                </label>
                <select name="payment_method" required style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
                    <option value="BANK_TRANSFER" {{ old('payment_method') === 'BANK_TRANSFER' ? 'selected' : '' }}>{{ __('Bank Wire Transfer (2521)') }}</option>
                    <option value="CASH" {{ old('payment_method') === 'CASH' ? 'selected' : '' }}>{{ __('Cash to Till / Register (2511)') }}</option>
                    <option value="CARD" {{ old('payment_method') === 'CARD' ? 'selected' : '' }}>{{ __('Card Acquirer POS') }}</option>
                    <option value="CHECK" {{ old('payment_method') === 'CHECK' ? 'selected' : '' }}>{{ __('Check / Promissory') }}</option>
                </select>
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Payment Date') }}
                </label>
                <input type="date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Bank Reference / Transaction #') }}
                </label>
                <input type="text" name="reference_number" value="{{ old('reference_number') }}" placeholder="TR-2026-998811"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem; font-family: monospace;">
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Cash Register (If Cash collection)') }}
                </label>
                <select name="cash_register_id" style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
                    <option value="">{{ __('None / Direct Treasury') }}</option>
                    @foreach($cashRegisters as $reg)
                        <option value="{{ $reg->id }}">{{ $reg->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div style="margin-bottom: 1.5rem;">
            <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                {{ __('Notes') }}
            </label>
            <textarea name="notes" rows="2" style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">{{ old('notes') }}</textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
            <a href="{{ route('admin.wholesale.payments.index') }}" 
               style="padding: 0.65rem 1.25rem; border-radius: 10px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-weight: 600; text-decoration: none;">
                {{ __('Cancel') }}
            </a>
            <button type="submit" 
                    style="padding: 0.65rem 1.5rem; border-radius: 10px; background: #6366f1; border: none; color: #fff; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);">
                <i class="fa-solid fa-check"></i>
                <span>{{ __('Record Payment & Post to GL') }}</span>
            </button>
        </div>
    </form>
</div>
@endsection

