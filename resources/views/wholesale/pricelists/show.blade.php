@extends('layouts.app')

@section('title', __('Price List: :name', ['name' => $priceList->name]))

@section('content')
<div class="content-wrapper">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <a href="{{ route('admin.wholesale.pricelists.index') }}" style="color: #6366f1; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;">
                <i class="fa-solid fa-arrow-left"></i>
                <span>{{ __('Back to Price Lists') }}</span>
            </a>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    {{ $priceList->name }}
                </h1>
                <span style="font-family: monospace; font-size: 0.85rem; font-weight: 700; background: rgba(99, 102, 241, 0.12); color: #6366f1; padding: 0.25rem 0.6rem; border-radius: 6px;">
                    {{ $priceList->code }}
                </span>
                @if($priceList->isCurrentlyEffective())
                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; background: rgba(16, 185, 129, 0.12); color: #10b981;">
                        <i class="fa-solid fa-check-circle"></i> {{ __('Active') }}
                    </span>
                @else
                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; background: rgba(239, 68, 68, 0.12); color: #ef4444;">
                        <i class="fa-solid fa-ban"></i> {{ __('Inactive / Expired') }}
                    </span>
                @endif
            </div>
            <div style="color: var(--text-muted); font-size: 0.88rem; margin-top: 0.35rem;">
                {{ __('Currency') }}: <strong style="color: var(--text-main);">{{ $priceList->currency }}</strong>
                &bull; {{ __('Effective') }}: {{ $priceList->effective_from?->format('d/m/Y') ?? 'Immediate' }} &mdash; {{ $priceList->effective_to?->format('d/m/Y') ?? 'Indefinite' }}
            </div>
        </div>
    </div>

    <!-- Items List Section -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.75rem; margin-bottom: 2rem;">
        <h2 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0 0 1rem 0;">
            {{ __('Price List Items & Custom Rates') }}
        </h2>

        @if($priceList->items->count() > 0)
            <div style="overflow-x: auto; margin-bottom: 1.5rem;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-color); text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                            <th style="padding: 0.75rem 1rem;">{{ __('Product') }}</th>
                            <th style="padding: 0.75rem 1rem;">{{ __('SKU') }}</th>
                            <th style="padding: 0.75rem 1rem;">{{ __('Min Qty Threshold') }}</th>
                            <th style="padding: 0.75rem 1rem;">{{ __('Base Catalog Price') }}</th>
                            <th style="padding: 0.75rem 1rem;">{{ __('Price List Rate') }}</th>
                            <th style="padding: 0.75rem 1rem;">{{ __('Diff / Saving') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($priceList->items as $item)
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 0.85rem 1rem; font-weight: 700; color: var(--text-main);">
                                    {{ $item->product?->name }}
                                </td>
                                <td style="padding: 0.85rem 1rem; font-family: monospace;">
                                    {{ $item->product?->sku }}
                                </td>
                                <td style="padding: 0.85rem 1rem;">
                                    >= {{ (float)$item->min_quantity }} {{ $item->product?->unit?->code }}
                                </td>
                                <td style="padding: 0.85rem 1rem; color: var(--text-muted);">
                                    {{ number_format($item->product?->base_wholesale_price ?? 0, 2) }} {{ $priceList->currency }}
                                </td>
                                <td style="padding: 0.85rem 1rem; font-weight: 800; color: #6366f1;">
                                    {{ number_format($item->price, 2) }} {{ $priceList->currency }}
                                </td>
                                <td style="padding: 0.85rem 1rem;">
                                    @php
                                        $base = (float)($item->product?->base_wholesale_price ?? 0);
                                        $diff = $base - (float)$item->price;
                                    @endphp
                                    @if($diff > 0)
                                        <span style="color: #10b981; font-weight: 700;">-{{ number_format($diff, 2) }} ({{ round(($diff / $base) * 100, 1) }}%)</span>
                                    @else
                                        <span style="color: var(--text-muted);">{{ __('Standard') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div style="text-align: center; padding: 2rem; background: var(--bg-main); border-radius: 12px; border: 1px dashed var(--border-color); margin-bottom: 1.5rem; color: var(--text-muted); font-size: 0.88rem;">
                {{ __('No custom item prices added to this price list yet.') }}
            </div>
        @endif

        <!-- Add Item Form -->
        <form method="POST" action="{{ route('admin.wholesale.pricelists.items.store', $priceList) }}"
              style="background: var(--bg-main); border-radius: 12px; padding: 1.25rem; border: 1px solid var(--border-color);">
            @csrf
            <div style="font-weight: 700; font-size: 0.85rem; color: var(--text-main); margin-bottom: 0.75rem;">
                {{ __('Add / Update Product Price') }}
            </div>
            <div style="display: grid; grid-template-columns: 3fr 1.5fr 1.5fr auto; gap: 0.75rem; align-items: end;">
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.25rem;">
                        {{ __('Select Product') }} *
                    </label>
                    <select name="product_id" required style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); font-size: 0.85rem;">
                        @foreach($availableProducts as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} [{{ $p->sku }}] ({{ __('Base:') }} {{ number_format($p->base_wholesale_price, 2) }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.25rem;">
                        {{ __('Price (:cur)', ['cur' => $priceList->currency]) }} *
                    </label>
                    <input type="number" step="0.01" name="price" placeholder="1050.00" required
                           style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); font-size: 0.85rem;">
                </div>

                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.25rem;">
                        {{ __('Min Quantity') }}
                    </label>
                    <input type="number" step="0.0001" name="min_quantity" value="1"
                           style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); font-size: 0.85rem;">
                </div>

                <div>
                    <button type="submit" 
                            style="padding: 0.5rem 1rem; border-radius: 8px; background: #6366f1; color: #fff; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.85rem;">
                        <i class="fa-solid fa-plus"></i> {{ __('Save Item') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

