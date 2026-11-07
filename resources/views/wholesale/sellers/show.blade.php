@extends('layouts.app')

@section('title', __('Seller Profile: :name', ['name' => $seller->name]))

@section('content')
<div class="content-wrapper" x-data="{
    bankModalOpen: false,
    locationModalOpen: false
}">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <a href="{{ route('admin.wholesale.sellers.index') }}" style="color: #6366f1; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;">
                <i class="fa-solid fa-arrow-left"></i>
                <span>{{ __('Back to Seller Companies') }}</span>
            </a>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    {{ $seller->name }}
                </h1>
                <span style="font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.65rem; border-radius: 9999px; {{ $seller->tax_regime->isVatPayer() ? 'background: rgba(16, 185, 129, 0.15); color: #10b981;' : 'background: rgba(99, 102, 241, 0.15); color: #6366f1;' }}">
                    {{ $seller->tax_regime->label() }}
                </span>
            </div>
            <div style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
                {{ $seller->legal_name }} • {{ __('TIN (ՀՎՀՀ)') }}: <strong style="color: var(--text-main); font-family: monospace;">{{ $seller->tin }}</strong> {{ $seller->vat_number ? '• '.$seller->vat_number : '' }}
            </div>
        </div>

        <div style="display: flex; gap: 0.75rem; align-items: center;">
            <a href="{{ route('admin.wholesale.sellers.edit', $seller) }}" 
               style="padding: 0.65rem 1.25rem; border-radius: 10px; background: #6366f1; color: #fff; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);">
                <i class="fa-solid fa-pen-to-square"></i>
                <span>{{ __('Edit Seller') }}</span>
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
        <div style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 12px; padding: 1rem 1.25rem; color: #10b981; font-weight: 600; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- 3-Column Profile Summary Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
        <!-- Card 1: Tax Identity -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.75rem;">
                {{ __('Tax & PEK Credentials') }}
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.88rem;">
                <div><strong>{{ __('TIN (ՀՎՀՀ)') }}:</strong> <span style="font-family: monospace;">{{ $seller->tin }}</span></div>
                <div><strong>{{ __('VAT Number') }}:</strong> {{ $seller->vat_number ?: __('None') }}</div>
                <div><strong>{{ __('PEK Username') }}:</strong> {{ $seller->pek_username ?: __('Not set') }}</div>
                <div><strong>{{ __('Active Series') }}:</strong> {{ $seller->activeTaxProfile?->document_series ?? $seller->tax_regime->defaultDocumentSeries() }}</div>
            </div>
        </div>

        <!-- Card 2: Legal Address -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.75rem;">
                {{ __('Official Addresses') }}
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.88rem;">
                <div><strong>{{ __('Legal Address') }}:</strong> {{ $seller->address }}</div>
                <div><strong>{{ __('Supply Location') }}:</strong> {{ $seller->default_supply_location ?: $seller->address }}</div>
            </div>
        </div>

        <!-- Card 3: Default Bank -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.75rem;">
                {{ __('Settlement Banking') }}
            </div>
            @if($seller->defaultBankAccount)
                <div style="display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.88rem;">
                    <div><strong>{{ __('Bank') }}:</strong> {{ $seller->defaultBankAccount->bank_name }}</div>
                    <div><strong>{{ __('Account') }}:</strong> <span style="font-family: monospace;">{{ $seller->defaultBankAccount->account_number }}</span> ({{ $seller->defaultBankAccount->currency }})</div>
                    @if($seller->defaultBankAccount->iban)
                        <div><strong>IBAN:</strong> {{ $seller->defaultBankAccount->iban }}</div>
                    @endif
                </div>
            @else
                <div style="color: var(--text-muted); font-size: 0.85rem;">
                    {{ __('No default bank account configured.') }}
                </div>
            @endif
        </div>
    </div>

    <!-- Section 1: Effective Tax Profiles History -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 2rem;">
        <h2 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0 0 1rem 0;">
            {{ __('Effective Tax Profiles & Historical Snapshots') }}
        </h2>
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.88rem;">
            <thead>
                <tr style="background: var(--bg-main); border-bottom: 1px solid var(--border-color); color: var(--text-muted); text-transform: uppercase; font-size: 0.72rem;">
                    <th style="padding: 0.75rem 1rem;">{{ __('Tax Regime') }}</th>
                    <th style="padding: 0.75rem 1rem;">{{ __('VAT Rate') }}</th>
                    <th style="padding: 0.75rem 1rem;">{{ __('Document Series') }}</th>
                    <th style="padding: 0.75rem 1rem;">{{ __('Effective From') }}</th>
                    <th style="padding: 0.75rem 1rem;">{{ __('Effective To') }}</th>
                    <th style="padding: 0.75rem 1rem;">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($seller->taxProfiles as $profile)
                    @php
                        $isCurrent = $profile->effective_from <= now()->toDateString() && ($profile->effective_to === null || $profile->effective_to >= now()->toDateString());
                    @endphp
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 0.85rem 1rem; font-weight: 700; color: var(--text-main);">
                            {{ $profile->tax_regime->label() }}
                        </td>
                        <td style="padding: 0.85rem 1rem;">
                            {{ $profile->is_vat_payer ? number_format($profile->vat_rate, 1).'%' : __('Exempt / 0%') }}
                        </td>
                        <td style="padding: 0.85rem 1rem; font-weight: 800;">
                            {{ $profile->document_series }}
                        </td>
                        <td style="padding: 0.85rem 1rem;">
                            {{ $profile->effective_from->format('Y-m-d') }}
                        </td>
                        <td style="padding: 0.85rem 1rem;">
                            {{ $profile->effective_to ? $profile->effective_to->format('Y-m-d') : __('Current (No End Date)') }}
                        </td>
                        <td style="padding: 0.85rem 1rem;">
                            @if($isCurrent)
                                <span style="color: #10b981; font-weight: 700; font-size: 0.75rem;">● {{ __('Active Profile') }}</span>
                            @else
                                <span style="color: var(--text-muted); font-size: 0.75rem;">{{ __('Historical') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Section 2: Bank Accounts -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <div>
                <h2 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    {{ __('Registered Bank Accounts') }}
                </h2>
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">
                    {{ __('Commercial bank accounts included in official PEK Accounting Documents.') }}
                </div>
            </div>
            <button type="button" @click="bankModalOpen = true" 
                    style="padding: 0.5rem 1rem; border-radius: 8px; background: #6366f1; color: #fff; font-weight: 700; border: none; cursor: pointer; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 0.4rem;">
                <i class="fa-solid fa-plus"></i>
                <span>{{ __('Add Bank Account') }}</span>
            </button>
        </div>

        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.88rem;">
            <thead>
                <tr style="background: var(--bg-main); border-bottom: 1px solid var(--border-color); color: var(--text-muted); text-transform: uppercase; font-size: 0.72rem;">
                    <th style="padding: 0.75rem 1rem;">{{ __('Bank Name') }}</th>
                    <th style="padding: 0.75rem 1rem;">{{ __('Account Number') }}</th>
                    <th style="padding: 0.75rem 1rem;">{{ __('Currency') }}</th>
                    <th style="padding: 0.75rem 1rem;">{{ __('Default') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($seller->bankAccounts as $account)
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 0.85rem 1rem; font-weight: 700; color: var(--text-main);">
                            {{ $account->bank_name }}
                        </td>
                        <td style="padding: 0.85rem 1rem; font-family: monospace;">
                            {{ $account->account_number }}
                        </td>
                        <td style="padding: 0.85rem 1rem; font-weight: 700;">
                            {{ $account->currency }}
                        </td>
                        <td style="padding: 0.85rem 1rem;">
                            @if($account->is_default)
                                <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-weight: 700; font-size: 0.75rem; padding: 0.2rem 0.6rem; border-radius: 9999px;">
                                    {{ __('DEFAULT') }}
                                </span>
                            @else
                                <span style="color: var(--text-muted); font-size: 0.8rem;">&mdash;</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="padding: 2rem; text-align: center; color: var(--text-muted);">
                            {{ __('No bank accounts added yet.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Section 3: Supply Locations -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <div>
                <h2 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    {{ __('Supply Locations') }}
                </h2>
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">
                    {{ __('Physical distribution centers and dispatch points.') }}
                </div>
            </div>
            <button type="button" @click="locationModalOpen = true" 
                    style="padding: 0.5rem 1rem; border-radius: 8px; background: #6366f1; color: #fff; font-weight: 700; border: none; cursor: pointer; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 0.4rem;">
                <i class="fa-solid fa-plus"></i>
                <span>{{ __('Add Supply Location') }}</span>
            </button>
        </div>

        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.88rem;">
            <thead>
                <tr style="background: var(--bg-main); border-bottom: 1px solid var(--border-color); color: var(--text-muted); text-transform: uppercase; font-size: 0.72rem;">
                    <th style="padding: 0.75rem 1rem;">{{ __('Location Name') }}</th>
                    <th style="padding: 0.75rem 1rem;">{{ __('Address') }}</th>
                    <th style="padding: 0.75rem 1rem;">{{ __('Linked Warehouse') }}</th>
                    <th style="padding: 0.75rem 1rem;">{{ __('Default') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($seller->locations as $loc)
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 0.85rem 1rem; font-weight: 700; color: var(--text-main);">
                            {{ $loc->name }}
                        </td>
                        <td style="padding: 0.85rem 1rem;">
                            {{ $loc->address }}
                        </td>
                        <td style="padding: 0.85rem 1rem;">
                            {{ $loc->warehouse?->name ?? __('No Warehouse Linked') }}
                        </td>
                        <td style="padding: 0.85rem 1rem;">
                            @if($loc->is_default)
                                <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-weight: 700; font-size: 0.75rem; padding: 0.2rem 0.6rem; border-radius: 9999px;">
                                    {{ __('DEFAULT') }}
                                </span>
                            @else
                                <span style="color: var(--text-muted); font-size: 0.8rem;">&mdash;</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="padding: 2rem; text-align: center; color: var(--text-muted);">
                            {{ __('No locations added yet.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Add Bank Account Modal -->
    <div x-show="bankModalOpen" 
         x-transition.opacity 
         style="position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(4px); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 1.5rem;"
         @keydown.escape.window="bankModalOpen = false"
         style="display: none;">
        
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; width: 100%; max-width: 500px; box-shadow: 0 20px 50px rgba(0,0,0,0.4); overflow: hidden;"
             @click.away="bankModalOpen = false">
            
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-main);">
                    {{ __('Add Seller Bank Account') }}
                </h3>
                <button type="button" @click="bankModalOpen = false" style="background: none; border: none; color: var(--text-muted); font-size: 1.2rem; cursor: pointer;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="{{ route('admin.wholesale.sellers.bank_accounts.store', $seller) }}" method="POST" style="padding: 1.5rem;">
                @csrf
                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                        {{ __('Bank Name') }} <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="bank_name" required placeholder="«ԱՄԵՐԻԱԲԱՆԿ» ՓԲԸ"
                           style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
                </div>

                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                        {{ __('Account Number (16 Digits)') }} <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="account_number" required placeholder="1570077654886500"
                           style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem; font-family: monospace;">
                </div>

                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                        {{ __('Currency') }} <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="currency" value="AMD" required
                           style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.88rem; color: var(--text-main); cursor: pointer;">
                        <input type="checkbox" name="is_default" value="1">
                        <span>{{ __('Set as primary default bank account') }}</span>
                    </label>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" @click="bankModalOpen = false" 
                            style="padding: 0.65rem 1.25rem; border-radius: 10px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-weight: 600; cursor: pointer;">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit" 
                            style="padding: 0.65rem 1.5rem; border-radius: 10px; background: #6366f1; border: none; color: #fff; font-weight: 700; cursor: pointer;">
                        {{ __('Save Bank Account') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Location Modal -->
    <div x-show="locationModalOpen" 
         x-transition.opacity 
         style="position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(4px); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 1.5rem;"
         @keydown.escape.window="locationModalOpen = false"
         style="display: none;">
        
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; width: 100%; max-width: 500px; box-shadow: 0 20px 50px rgba(0,0,0,0.4); overflow: hidden;"
             @click.away="locationModalOpen = false">
            
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-main);">
                    {{ __('Add Supply Location') }}
                </h3>
                <button type="button" @click="locationModalOpen = false" style="background: none; border: none; color: var(--text-muted); font-size: 1.2rem; cursor: pointer;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="{{ route('admin.wholesale.sellers.locations.store', $seller) }}" method="POST" style="padding: 1.5rem;">
                @csrf
                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                        {{ __('Location Name') }} <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="name" required placeholder="{{ __('e.g. North Hub Dispatch Facility') }}"
                           style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
                </div>

                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                        {{ __('Full Physical Address (<SupplyLocation> in XML)') }} <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="address" required placeholder="Հայաստան, ԵՐԵՎԱՆ, ԴԱՎԹԱՇԵՆ..."
                           style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
                </div>

                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                        {{ __('Linked Warehouse (Optional)') }}
                    </label>
                    <select name="warehouse_id" style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
                        <option value="">{{ __('No Warehouse Linked') }}</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.88rem; color: var(--text-main); cursor: pointer;">
                        <input type="checkbox" name="is_default" value="1">
                        <span>{{ __('Set as default supply location') }}</span>
                    </label>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" @click="locationModalOpen = false" 
                            style="padding: 0.65rem 1.25rem; border-radius: 10px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-weight: 600; cursor: pointer;">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit" 
                            style="padding: 0.65rem 1.5rem; border-radius: 10px; background: #6366f1; border: none; color: #fff; font-weight: 700; cursor: pointer;">
                        {{ __('Save Location') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

