@extends('layouts.app')

@section('title', __('Edit Seller Company: :name', ['name' => $seller->name]))

@section('content')
<div class="content-wrapper" style="max-width: 900px; margin: 0 auto;">
    <!-- Header -->
    <div style="margin-bottom: 2rem;">
        <a href="{{ route('admin.wholesale.sellers.show', $seller) }}" style="color: #6366f1; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;">
            <i class="fa-solid fa-arrow-left"></i>
            <span>{{ __('Back to Seller Profile') }}</span>
        </a>
        <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
            {{ __('Edit Seller: :name', ['name' => $seller->name]) }}
        </h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
            {{ __('Update legal entity registration, active tax regime and PEK username.') }}
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

    <form method="POST" action="{{ route('admin.wholesale.sellers.update', $seller) }}" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
        @csrf
        @method('PUT')

        <!-- Section 1: Legal Entity Info -->
        <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0 0 1.25rem 0; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
            1. {{ __('Company Identity & Legal Registration') }}
        </h2>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Trading / Display Name') }} <span style="color: #ef4444;">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name', $seller->name) }}" required
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Full Legal Entity Name') }} <span style="color: #ef4444;">*</span>
                </label>
                <input type="text" name="legal_name" value="{{ old('legal_name', $seller->legal_name) }}" required
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('TIN / ՀՎՀՀ (8 Digits)') }} <span style="color: #ef4444;">*</span>
                </label>
                <input type="text" name="tin" value="{{ old('tin', $seller->tin) }}" required maxlength="8" pattern="\d{8}"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem; font-family: monospace;">
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('VAT Reg. Number (Optional)') }}
                </label>
                <input type="text" name="vat_number" value="{{ old('vat_number', $seller->vat_number) }}"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
            </div>
        </div>

        <!-- Section 2: Tax Regime -->
        <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 2rem 0 1.25rem 0; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
            2. {{ __('Tax Regime & e-Invoicing') }}
        </h2>

        <div style="margin-bottom: 1.5rem;">
            <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                {{ __('Applicable Tax Regime') }} <span style="color: #ef4444;">*</span>
            </label>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                @foreach($regimes as $regime)
                    <label style="border: 2px solid var(--border-color); border-radius: 12px; padding: 1rem; cursor: pointer; display: flex; align-items: flex-start; gap: 0.75rem;">
                        <input type="radio" name="tax_regime" value="{{ $regime->value }}" {{ old('tax_regime', $seller->tax_regime->value) === $regime->value ? 'checked' : '' }} style="margin-top: 0.25rem;">
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
                    {{ __('PEK Username / Account ID (<User> tag)') }}
                </label>
                <input type="text" name="pek_username" value="{{ old('pek_username', $seller->pek_username) }}"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Status') }}
                </label>
                <select name="is_active" style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
                    <option value="1" {{ old('is_active', $seller->is_active ? '1' : '0') == '1' ? 'selected' : '' }}>{{ __('Active') }}</option>
                    <option value="0" {{ old('is_active', $seller->is_active ? '1' : '0') == '0' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                </select>
            </div>
        </div>

        <!-- Section 3: Addresses -->
        <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 2rem 0 1.25rem 0; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
            3. {{ __('Addresses') }}
        </h2>

        <div style="margin-bottom: 1.25rem;">
            <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                {{ __('Legal Registered Address') }} <span style="color: #ef4444;">*</span>
            </label>
            <input type="text" name="address" value="{{ old('address', $seller->address) }}" required
                   style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
        </div>

        <div style="margin-bottom: 1.5rem;">
            <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                {{ __('Default Physical Supply Location') }}
            </label>
            <input type="text" name="default_supply_location" value="{{ old('default_supply_location', $seller->default_supply_location) }}"
                   style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
            <a href="{{ route('admin.wholesale.sellers.show', $seller) }}" 
               style="padding: 0.65rem 1.25rem; border-radius: 10px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-weight: 600; text-decoration: none;">
                {{ __('Cancel') }}
            </a>
            <button type="submit" 
                    style="padding: 0.65rem 1.5rem; border-radius: 10px; background: #6366f1; border: none; color: #fff; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);">
                <i class="fa-solid fa-check"></i>
                <span>{{ __('Update Seller') }}</span>
            </button>
        </div>
    </form>
</div>
@endsection

