@extends('layouts.app')

@section('title', __('Add Seller Company'))

@section('content')
<div class="content-wrapper" style="max-width: 900px; margin: 0 auto;">
    <!-- Header -->
    <div style="margin-bottom: 2rem;">
        <a href="{{ route('admin.wholesale.sellers.index') }}" style="color: #6366f1; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;">
            <i class="fa-solid fa-arrow-left"></i>
            <span>{{ __('Back to Seller Companies') }}</span>
        </a>
        <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
            {{ __('Register New Seller Company') }}
        </h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
            {{ __('Configure legal entity details, Armenian tax identification (TIN), tax regime, and banking information.') }}
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

    <form method="POST" action="{{ route('admin.wholesale.sellers.store') }}" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
        @csrf

        <!-- Section 1: Legal Entity Info -->
        <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0 0 1.25rem 0; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
            1. {{ __('Company Identity & Legal Registration') }}
        </h2>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Trading / Display Name') }} <span style="color: #ef4444;">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="{{ __('e.g. Sales Plus LLC') }}"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Full Legal Entity Name (for PEK Tax Documents)') }} <span style="color: #ef4444;">*</span>
                </label>
                <input type="text" name="legal_name" value="{{ old('legal_name') }}" required placeholder="«ՍԵՅԼՍ ՊԼՅՈՒՍ» ՍՊԸ"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('TIN / ՀՎՀՀ (8 Digits)') }} <span style="color: #ef4444;">*</span>
                </label>
                <input type="text" name="tin" value="{{ old('tin') }}" required maxlength="8" pattern="\d{8}" placeholder="08293072"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem; font-family: monospace;">
                <span style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.25rem; display: block;">
                    {{ __('Armenian Tax Identification Number, strictly 8 numeric digits.') }}
                </span>
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('VAT Reg. Number (Optional)') }}
                </label>
                <input type="text" name="vat_number" value="{{ old('vat_number') }}" placeholder="08293072/1"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
                <span style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.25rem; display: block;">
                    {{ __('Defaults automatically to {TIN}/1 for VAT payers if left empty.') }}
                </span>
            </div>
        </div>

        <!-- Section 2: Tax Regime -->
        <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 2rem 0 1.25rem 0; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
            2. {{ __('Tax Regime & e-Invoicing Specification') }}
        </h2>

        <div style="margin-bottom: 1.5rem;">
            <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                {{ __('Applicable Tax Regime') }} <span style="color: #ef4444;">*</span>
            </label>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                @foreach($regimes as $regime)
                    <label style="border: 2px solid var(--border-color); border-radius: 12px; padding: 1rem; cursor: pointer; display: flex; align-items: flex-start; gap: 0.75rem; transition: border-color 0.2s ease;">
                        <input type="radio" name="tax_regime" value="{{ $regime->value }}" {{ old('tax_regime', 'vat_payer') === $regime->value ? 'checked' : '' }} style="margin-top: 0.25rem;">
                        <div>
                            <div style="font-weight: 700; color: var(--text-main); font-size: 0.9rem;">{{ $regime->label() }}</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
                                {{ __('Issues: :doc', ['doc' => $regime->documentTypeName()]) }}
                            </div>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('PEK Username / Account ID (for XML <User> tag)') }}
                </label>
                <input type="text" name="pek_username" value="{{ old('pek_username') }}" placeholder="salesplus25"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Warehouse Mapping') }}
                </label>
                <select name="warehouse_id" style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
                    <option value="">{{ __('No Warehouse Linked') }}</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ old('warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- Section 3: Addresses -->
        <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 2rem 0 1.25rem 0; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
            3. {{ __('Addresses & Locations') }}
        </h2>

        <div style="margin-bottom: 1.25rem;">
            <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                {{ __('Legal Registered Address (Armenian PEK Address Format)') }} <span style="color: #ef4444;">*</span>
            </label>
            <input type="text" name="address" value="{{ old('address') }}" required placeholder="ԵՐԵՎԱՆ ԴԱՎԹԱՇԵՆ ԴԱՎԹԱՇԵՆ 1 ԹՂՄ. 38 7 ԲՆ."
                   style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
        </div>

        <div style="margin-bottom: 1.5rem;">
            <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                {{ __('Default Physical Supply Location (<SupplyLocation> in XML)') }}
            </label>
            <input type="text" name="default_supply_location" value="{{ old('default_supply_location') }}" placeholder="Հայաստան, ԿՈՏԱՅՔ, ԶՈՎՈՒՆԻ, ԶՈՎՈՒՆԻ, 30փ 2նրբ 31, 182տար"
                   style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
        </div>

        <!-- Section 4: Initial Bank Account -->
        <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 2rem 0 1.25rem 0; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
            4. {{ __('Default Corporate Bank Account (Required for Accounting Documents)') }}
        </h2>

        <div style="display: grid; grid-template-columns: 1fr 1fr 120px; gap: 1.25rem; margin-bottom: 1.5rem;">
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Commercial Bank Name') }}
                </label>
                <input type="text" name="bank_name" value="{{ old('bank_name') }}" placeholder="«ԱՄԵՐԻԱԲԱՆԿ» ՓԲԸ"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Account Number (16 Digits)') }}
                </label>
                <input type="text" name="account_number" value="{{ old('account_number') }}" placeholder="1570077654886500"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem; font-family: monospace;">
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Currency') }}
                </label>
                <input type="text" name="currency" value="{{ old('currency', 'AMD') }}"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem; font-weight: 700;">
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
            <a href="{{ route('admin.wholesale.sellers.index') }}" 
               style="padding: 0.65rem 1.25rem; border-radius: 10px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-weight: 600; text-decoration: none;">
                {{ __('Cancel') }}
            </a>
            <button type="submit" 
                    style="padding: 0.65rem 1.5rem; border-radius: 10px; background: #6366f1; border: none; color: #fff; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);">
                <i class="fa-solid fa-check"></i>
                <span>{{ __('Save & Register Seller') }}</span>
            </button>
        </div>
    </form>
</div>
@endsection

