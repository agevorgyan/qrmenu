@extends('layouts.app')

@section('title', __('Add B2B Customer'))

@section('content')
<div class="content-wrapper" style="max-width: 860px; margin: 0 auto;">
    <!-- Header -->
    <div style="margin-bottom: 2rem;">
        <a href="{{ route('admin.wholesale.customers.index') }}" style="color: #6366f1; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;">
            <i class="fa-solid fa-arrow-left"></i>
            <span>{{ __('Back to Customers') }}</span>
        </a>
        <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
            {{ __('New B2B Wholesale Customer') }}
        </h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
            {{ __('Register a corporate buyer with official Armenian TIN, tax profile, credit limits, and delivery point.') }}
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

    <form method="POST" action="{{ route('admin.wholesale.customers.store') }}" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
        @csrf

        <!-- 1. Legal Entity & Tax -->
        <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0 0 1.25rem 0; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
            1. {{ __('Corporate Identity & Tax Profile') }}
        </h2>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Trading / Display Name') }} <span style="color: #ef4444;">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="{{ __('e.g. SAS Supermarkets') }}"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Full Legal Entity Name') }}
                </label>
                <input type="text" name="legal_name" value="{{ old('legal_name') }}" placeholder="{{ __('e.g. SAS Group LLC') }}"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Taxpayer Identification Number (TIN / ՀՎՀՀ)') }}
                </label>
                <input type="text" name="tin" value="{{ old('tin') }}" placeholder="01234567" maxlength="8"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem; font-family: monospace;">
                <span style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.25rem; display: block;">
                    {{ __('8 numeric digits for Armenian entities; leave empty for individuals.') }}
                </span>
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Tax Regime') }} <span style="color: #ef4444;">*</span>
                </label>
                <select name="tax_regime" required style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
                    <option value="vat_payer" {{ old('tax_regime', 'vat_payer') === 'vat_payer' ? 'selected' : '' }}>{{ __('VAT Payer (ԱԱՀ վճարող)') }}</option>
                    <option value="turnover_tax" {{ old('tax_regime') === 'turnover_tax' ? 'selected' : '' }}>{{ __('Turnover Tax (Շրջանառության հարկ)') }}</option>
                    <option value="micro_business" {{ old('tax_regime') === 'micro_business' ? 'selected' : '' }}>{{ __('Micro-business (Միկրոձեռնարկատիրություն)') }}</option>
                    <option value="individual" {{ old('tax_regime') === 'individual' ? 'selected' : '' }}>{{ __('Individual / Non-commercial Buyer') }}</option>
                </select>
            </div>
        </div>

        <!-- 2. Credit Terms -->
        <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 2rem 0 1.25rem 0; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
            2. {{ __('Payment Terms & Credit Controls') }}
        </h2>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
            <div>
                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.88rem; font-weight: 700; color: var(--text-main); cursor: pointer; margin-top: 0.5rem; margin-bottom: 1rem;">
                    <input type="checkbox" name="is_credit_allowed" value="1" {{ old('is_credit_allowed') ? 'checked' : '' }}>
                    <span>{{ __('Allow Postpaid / Credit Sales') }}</span>
                </label>
                <span style="font-size: 0.75rem; color: var(--text-muted); display: block;">
                    {{ __('When unchecked, Wholesale POS requires full payment before order confirmation.') }}
                </span>
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Credit Limit (AMD)') }}
                </label>
                <input type="number" step="0.01" name="credit_limit" value="{{ old('credit_limit', 0) }}"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
                <span style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.25rem; display: block;">
                    {{ __('Maximum outstanding AR balance allowed for this client (0 = unlimited if credit allowed).') }}
                </span>
            </div>
        </div>

        <div style="margin-bottom: 1.5rem; max-width: 320px;">
            <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                {{ __('Payment Terms (Net Days)') }}
            </label>
            <input type="number" step="1" name="payment_term_days" value="{{ old('payment_term_days', 0) }}"
                   style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
        </div>

        <!-- 3. Primary Delivery Address -->
        <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 2rem 0 1.25rem 0; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
            3. {{ __('Primary Delivery Location & Contact') }}
        </h2>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Address Line 1') }}
                </label>
                <input type="text" name="address_line1" value="{{ old('address_line1') }}" placeholder="{{ __('e.g. Isahakyan 35') }}"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('City') }}
                </label>
                <input type="text" name="city" value="{{ old('city', 'Yerevan') }}"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Contact Person') }}
                </label>
                <input type="text" name="contact_person" value="{{ old('contact_person') }}" placeholder="Tigran Petrosyan"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Phone Number') }}
                </label>
                <input type="text" name="phone" value="{{ old('phone') }}" placeholder="+374 10 123456"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Email') }}
                </label>
                <input type="email" name="email" value="{{ old('email') }}" placeholder="procurement@client.am"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
            <a href="{{ route('admin.wholesale.customers.index') }}" 
               style="padding: 0.65rem 1.25rem; border-radius: 10px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-weight: 600; text-decoration: none;">
                {{ __('Cancel') }}
            </a>
            <button type="submit" 
                    style="padding: 0.65rem 1.5rem; border-radius: 10px; background: #6366f1; border: none; color: #fff; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);">
                <i class="fa-solid fa-check"></i>
                <span>{{ __('Save Customer') }}</span>
            </button>
        </div>
    </form>
</div>
@endsection

