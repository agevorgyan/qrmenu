@extends('layouts.app')

@section('title', __('Table QR Code Studio') . ' - ' . $vendor->name)

@section('content')
<style>
    @media print {
        body * { visibility: hidden; }
        #printableFlyer, #printableFlyer * { visibility: visible; }
        #printableFlyer { 
            position: absolute; 
            left: 0; 
            top: 0; 
            width: 100%; 
            max-width: 100% !important;
            border: none !important; 
            box-shadow: none !important; 
        }
    }
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;" class="no-print">
    <div>
        <div style="display: flex; align-items: center; gap: 0.65rem;">
            <span style="width: 40px; height: 40px; border-radius: 12px; background: rgba(6, 182, 212, 0.15); color: #06b6d4; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                <i class="fa-solid fa-qrcode"></i>
            </span>
            <h1 style="font-size: 1.75rem; font-weight: 800; margin: 0; color: var(--text-main);">
                {{ __('Table QR Code Studio') }}
            </h1>
        </div>
        <p style="color: var(--text-muted); font-size: 0.88rem; margin-top: 0.35rem;">
            {{ __('Generate printable Dine-In table stand flyers for') }} <strong style="color: var(--primary);">{{ $location?->name ?? __('All Locations') }}</strong>.
        </p>
    </div>
    <button onclick="window.print()" class="btn btn-primary">
        <i class="fa-solid fa-print"></i> {{ __('Print Table Stand Flyer') }}
    </button>
</div>

<div class="grid-2 no-print" x-data="{ tableNum: '4', qrType: 'dine_in' }">
    <!-- QR Customizer Controls -->
    <div class="card" style="height: fit-content;">
        <h3 style="font-size: 1.15rem; font-weight: 800; margin-bottom: 1.25rem; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-sliders" style="color: var(--primary);"></i> {{ __('Stand Configuration') }}
        </h3>

        <div style="margin-bottom: 1.25rem;">
            <label class="form-label">{{ __('Table Number') }}</label>
            <input type="text" x-model="tableNum" class="form-input" style="font-size: 1.15rem; font-weight: 800; font-family: 'Outfit';">
        </div>

        <div style="margin-bottom: 1.25rem;">
            <label class="form-label">{{ __('QR Code Purpose') }}</label>
            <select x-model="qrType" class="form-select">
                <option value="dine_in">{{ __('Dine-In Table Menu (Scan to browse & order)') }}</option>
                <option value="ordering">{{ __('Dedicated WhatsApp / Takeaway Order QR') }}</option>
            </select>
        </div>

        <div style="background: var(--input-bg); border: 1px solid var(--border-color); padding: 1.15rem; border-radius: 14px; font-size: 0.82rem; color: var(--text-muted); line-height: 1.5;">
            <div style="font-weight: 700; color: var(--primary); margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.4rem;">
                <i class="fa-solid fa-lightbulb"></i> {{ __('Pro Tip') }}
            </div>
            {{ __('Print a stand flyer for each table (Table 1 to Table :max). When guests scan at the table, the table number automatically locks onto their order for the kitchen!', ['max' => $location?->table_count ?? 20]) }}
        </div>
    </div>

    <!-- Printable Table Stand Flyer Preview -->
    <div class="card" id="printableFlyer" style="background: var(--bg-card); border: 2px solid var(--primary); border-radius: 24px; padding: 2.5rem 2rem; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative; max-width: min(440px, 100%); margin: 0 auto; box-sizing: border-box; box-shadow: var(--shadow-card);">
        <!-- Logo -->
        <img src="{{ $vendor->logo ?? 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=150&q=80' }}" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary); margin-bottom: 1rem; box-shadow: 0 4px 15px var(--primary-glow);" alt="{{ $vendor->name }}">

        <h2 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem; word-break: break-word;">{{ $vendor->name }}</h2>
        <div style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.25rem;">{{ $location?->name ?? __('Main Branch') }}</div>

        <!-- Table Badge -->
        <div style="background: var(--primary-gradient); color: #ffffff; font-weight: 900; font-size: 1.2rem; padding: 0.35rem 1.5rem; border-radius: 9999px; font-family: 'Outfit'; margin-bottom: 1.25rem; letter-spacing: 0.05em; box-shadow: 0 4px 12px var(--primary-glow);" x-text="'{{ __('TABLE') }} ' + tableNum">
            TABLE 4
        </div>

        <!-- QR Code Image Canvas -->
        <div style="background: #ffffff; padding: 1rem; border-radius: 18px; margin-bottom: 1.25rem; box-shadow: 0 10px 30px rgba(0,0,0,0.15); max-width: 100%;">
            <img :src="'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent('{{ rtrim($vendor->getStorefrontUrl($location?->slug), '/') }}?table=' + tableNum + '&mode=' + qrType)" style="width: 175px; height: 175px; display: block; max-width: 100%;" alt="Table QR">
        </div>

        <div style="font-family: 'Outfit'; font-weight: 800; font-size: 1.05rem; color: var(--text-main); margin-bottom: 0.25rem;">
            📱 {{ __('SCAN TO VIEW DIGITAL MENU') }}
        </div>
        <div style="font-size: 0.8rem; color: var(--text-muted);">
            {{ __('Point phone camera to view menu & place order') }}
        </div>

        <div style="margin-top: 1.75rem; font-size: 0.7rem; color: var(--text-muted); opacity: 0.75;">
            {{ __('Powered by QRMenu SaaS Platform') }}
        </div>
    </div>
</div>
@endsection
