@extends('layouts.app')

@section('title', __('B2B Customer: :name', ['name' => $customer->name]))

@section('content')
<div class="content-wrapper">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <a href="{{ route('admin.wholesale.customers.index') }}" style="color: #6366f1; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;">
                <i class="fa-solid fa-arrow-left"></i>
                <span>{{ __('Back to Customers') }}</span>
            </a>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    {{ $customer->name }}
                </h1>
                @if($customer->is_active)
                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; background: rgba(16, 185, 129, 0.12); color: #10b981;">
                        <i class="fa-solid fa-check-circle"></i> {{ __('Active') }}
                    </span>
                @else
                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; background: rgba(239, 68, 68, 0.12); color: #ef4444;">
                        <i class="fa-solid fa-ban"></i> {{ __('Inactive') }}
                    </span>
                @endif
            </div>
            <div style="color: var(--text-muted); font-size: 0.88rem; margin-top: 0.35rem;">
                {{ $customer->legal_name ?? $customer->name }}
                @if($customer->tin)
                    &bull; TIN (ՀՎՀՀ): <strong style="color: var(--text-main); font-family: monospace;">{{ $customer->tin }}</strong>
                @endif
                &bull; {{ ucfirst(str_replace('_', ' ', $customer->tax_regime)) }}
            </div>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <a href="{{ route('admin.wholesale.customers.edit', $customer) }}"
               style="padding: 0.65rem 1.25rem; border-radius: 10px; background: #6366f1; color: #fff; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3);">
                <i class="fa-solid fa-pen-to-square"></i>
                <span>{{ __('Edit Customer') }}</span>
            </a>
        </div>
    </div>

    <!-- Cards Row -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <!-- Card 1: Credit & AR -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem;">
            <div style="font-size: 0.85rem; font-weight: 800; color: #6366f1; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-credit-card"></i>
                <span>{{ __('Accounts Receivable & Credit') }}</span>
            </div>

            <div style="margin-bottom: 1rem;">
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">{{ __('Outstanding Balance (Owed)') }}</div>
                <div style="font-size: 1.75rem; font-weight: 800; color: {{ (float)$customer->current_balance > 0 ? '#ef4444' : 'var(--text-main)' }};">
                    {{ number_format($customer->current_balance, 2) }} AMD
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">{{ __('Credit Limit') }}</div>
                    <div style="font-size: 1rem; font-weight: 700; color: var(--text-main);">
                        @if($customer->is_credit_allowed)
                            {{ (float)$customer->credit_limit > 0 ? number_format($customer->credit_limit, 0) . ' AMD' : __('Unlimited') }}
                        @else
                            <span style="color: #ef4444;">{{ __('Disabled') }}</span>
                        @endif
                    </div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">{{ __('Payment Terms') }}</div>
                    <div style="font-size: 1rem; font-weight: 700; color: var(--text-main);">
                        {{ $customer->payment_term_days > 0 ? __(':days Days', ['days' => $customer->payment_term_days]) : __('Immediate') }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2: Contact Info -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem;">
            <div style="font-size: 0.85rem; font-weight: 800; color: #10b981; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-address-book"></i>
                <span>{{ __('Contact Details') }}</span>
            </div>

            <div style="margin-bottom: 0.75rem;">
                <div style="font-size: 0.75rem; color: var(--text-muted);">{{ __('Phone') }}</div>
                <div style="font-size: 1rem; font-weight: 700; color: var(--text-main);">{{ $customer->phone ?? '—' }}</div>
            </div>

            <div style="margin-bottom: 0.75rem;">
                <div style="font-size: 0.75rem; color: var(--text-muted);">{{ __('Email') }}</div>
                <div style="font-size: 1rem; font-weight: 700; color: var(--text-main);">{{ $customer->email ?? '—' }}</div>
            </div>

            <div style="padding-top: 0.75rem; border-top: 1px solid var(--border-color); font-size: 0.85rem; color: var(--text-muted);">
                {{ $customer->notes ?? __('No notes registered for this account.') }}
            </div>
        </div>
    </div>

    <!-- Delivery Locations Grid -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.75rem; margin-bottom: 2rem;">
        <h2 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0 0 1rem 0;">
            {{ __('Delivery & Supply Locations') }}
        </h2>

        @if($customer->addresses->count() > 0)
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                @foreach($customer->addresses as $addr)
                    <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <strong style="color: var(--text-main); font-size: 0.95rem;">{{ $addr->name }}</strong>
                            @if($addr->is_default)
                                <span style="font-size: 0.7rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 4px; background: rgba(99, 102, 241, 0.15); color: #6366f1;">
                                    {{ __('Default') }}
                                </span>
                            @endif
                        </div>
                        <div style="color: var(--text-muted); font-size: 0.88rem; line-height: 1.4;">
                            {{ $addr->full_address }}
                        </div>
                        @if($addr->contact_name)
                            <div style="margin-top: 0.5rem; font-size: 0.8rem; color: var(--text-muted);">
                                <i class="fa-solid fa-user"></i> {{ $addr->contact_name }} ({{ $addr->contact_phone }})
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div style="text-align: center; padding: 1.5rem; background: var(--bg-main); border-radius: 12px; border: 1px dashed var(--border-color); margin-bottom: 1.5rem; color: var(--text-muted); font-size: 0.88rem;">
                {{ __('No delivery addresses recorded.') }}
            </div>
        @endif

        <!-- Add Address Form -->
        <form method="POST" action="{{ route('admin.wholesale.customers.addresses.store', $customer) }}"
              style="background: var(--bg-main); border-radius: 12px; padding: 1.25rem; border: 1px solid var(--border-color);">
            @csrf
            <div style="font-weight: 700; font-size: 0.85rem; color: var(--text-main); margin-bottom: 0.75rem;">
                {{ __('Add Delivery Location') }}
            </div>
            <div style="display: grid; grid-template-columns: 1.5fr 2fr 1fr 1.5fr auto; gap: 0.75rem; align-items: end;">
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.25rem;">
                        {{ __('Point Name') }} *
                    </label>
                    <input type="text" name="name" placeholder="Branch #2" required
                           style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); font-size: 0.85rem;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.25rem;">
                        {{ __('Address') }} *
                    </label>
                    <input type="text" name="address_line1" placeholder="Komitas 22" required
                           style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); font-size: 0.85rem;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.25rem;">
                        {{ __('City') }}
                    </label>
                    <input type="text" name="city" value="Yerevan"
                           style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); font-size: 0.85rem;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.25rem;">
                        {{ __('Contact / Phone') }}
                    </label>
                    <input type="text" name="contact_phone" placeholder="+374 91 000000"
                           style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); font-size: 0.85rem;">
                </div>
                <div>
                    <button type="submit" 
                            style="padding: 0.5rem 1rem; border-radius: 8px; background: #6366f1; color: #fff; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.85rem;">
                        <i class="fa-solid fa-plus"></i> {{ __('Add') }}
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Pricing Agreements & Contracts -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.75rem;">
        <h2 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0 0 1rem 0;">
            {{ __('Pricing Agreements & Contract Overrides') }}
        </h2>

        @if($customer->pricingAgreements->count() > 0)
            <div style="overflow-x: auto; margin-bottom: 1.5rem;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-color); text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                            <th style="padding: 0.75rem 1rem;">{{ __('Type') }}</th>
                            <th style="padding: 0.75rem 1rem;">{{ __('Target / Product') }}</th>
                            <th style="padding: 0.75rem 1rem;">{{ __('Agreed Term / Price') }}</th>
                            <th style="padding: 0.75rem 1rem;">{{ __('Validity') }}</th>
                            <th style="padding: 0.75rem 1rem;">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($customer->pricingAgreements as $agr)
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 0.85rem 1rem; font-weight: 700;">
                                    @if($agr->product_id)
                                        <span style="color: #6366f1;">{{ __('Product Contract') }}</span>
                                    @else
                                        <span style="color: #10b981;">{{ __('Price List Assignment') }}</span>
                                    @endif
                                </td>
                                <td style="padding: 0.85rem 1rem;">
                                    @if($agr->product)
                                        {{ $agr->product->name }} [{{ $agr->product->sku }}]
                                    @elseif($agr->priceList)
                                        {{ $agr->priceList->name }} ({{ $agr->priceList->code }})
                                    @endif
                                </td>
                                <td style="padding: 0.85rem 1rem; font-weight: 700;">
                                    @if($agr->contract_price)
                                        {{ number_format($agr->contract_price, 2) }} AMD
                                    @elseif($agr->discount_percent)
                                        {{ (float)$agr->discount_percent }}% {{ __('Discount') }}
                                    @else
                                        {{ __('Catalog / Tier Rates') }}
                                    @endif
                                </td>
                                <td style="padding: 0.85rem 1rem; font-size: 0.82rem; color: var(--text-muted);">
                                    {{ $agr->valid_from?->format('d/m/Y') ?? 'Any' }} &mdash; {{ $agr->valid_to?->format('d/m/Y') ?? 'Ongoing' }}
                                </td>
                                <td style="padding: 0.85rem 1rem;">
                                    @if($agr->isCurrentlyValid())
                                        <span style="color: #10b981; font-weight: 700; font-size: 0.75rem;">{{ __('Active') }}</span>
                                    @else
                                        <span style="color: #ef4444; font-weight: 700; font-size: 0.75rem;">{{ __('Expired / Inactive') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div style="text-align: center; padding: 1.5rem; background: var(--bg-main); border-radius: 12px; border: 1px dashed var(--border-color); margin-bottom: 1.5rem; color: var(--text-muted); font-size: 0.88rem;">
                {{ __('No custom pricing agreements assigned. Standard base wholesale catalog pricing applies.') }}
            </div>
        @endif

        <!-- Assign Pricing Form -->
        <form method="POST" action="{{ route('admin.wholesale.customers.pricing.store', $customer) }}"
              style="background: var(--bg-main); border-radius: 12px; padding: 1.25rem; border: 1px solid var(--border-color);">
            @csrf
            <div style="font-weight: 700; font-size: 0.85rem; color: var(--text-main); margin-bottom: 0.75rem;">
                {{ __('Assign Price List or Product Contract') }}
            </div>
            <div style="display: grid; grid-template-columns: 2fr 2fr 1.5fr 1.5fr auto; gap: 0.75rem; align-items: end;">
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.25rem;">
                        {{ __('Wholesale Price List') }}
                    </label>
                    <select name="price_list_id" style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); font-size: 0.85rem;">
                        <option value="">{{ __('None (Product-specific contract)') }}</option>
                        @foreach($availablePriceLists as $pl)
                            <option value="{{ $pl->id }}">{{ $pl->name }} ({{ $pl->code }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.25rem;">
                        {{ __('Product (Optional)') }}
                    </label>
                    <select name="product_id" style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); font-size: 0.85rem;">
                        <option value="">{{ __('All Products in Price List') }}</option>
                        @foreach($availableProducts as $prod)
                            <option value="{{ $prod->id }}">{{ $prod->name }} [{{ $prod->sku }}]</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.25rem;">
                        {{ __('Contract Price (AMD)') }}
                    </label>
                    <input type="number" step="0.01" name="contract_price" placeholder="1150.00"
                           style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); font-size: 0.85rem;">
                </div>

                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.25rem;">
                        {{ __('Discount (%)') }}
                    </label>
                    <input type="number" step="0.01" name="discount_percent" placeholder="5.0"
                           style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); font-size: 0.85rem;">
                </div>

                <div>
                    <button type="submit" 
                            style="padding: 0.5rem 1rem; border-radius: 8px; background: #6366f1; color: #fff; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.85rem;">
                        <i class="fa-solid fa-link"></i> {{ __('Assign') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

