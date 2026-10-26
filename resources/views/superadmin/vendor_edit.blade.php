@extends('layouts.app')

@section('title', '«' . $vendor->name . '» - Գործընկերոջ Կառավարում - SuperAdmin')

@section('styles')
<style>
    .vendor-edit-container {
        max-width: 1200px;
        margin: 0 auto;
        padding-bottom: 3rem;
    }
    .ve-tabs-nav {
        display: flex;
        gap: 0.5rem;
        border-bottom: 2px solid var(--border-color);
        margin-bottom: 1.75rem;
        overflow-x: auto;
    }
    .ve-tab-btn {
        background: none;
        border: none;
        padding: 0.85rem 1.25rem;
        font-family: 'Outfit', sans-serif;
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--text-muted);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.6rem;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
        white-space: nowrap;
        transition: all 0.2s ease;
    }
    .ve-tab-btn:hover {
        color: var(--text-main);
    }
    .ve-tab-btn.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
    }
    .ve-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        padding: clamp(1.25rem, 2.5vw, 2rem);
        margin-bottom: 1.75rem;
        box-shadow: var(--shadow-card);
    }
    .ve-section-title {
        font-family: 'Outfit', sans-serif;
        font-size: 1.15rem;
        font-weight: 800;
        color: var(--text-main);
        margin-bottom: 1.25rem;
        padding-bottom: 0.65rem;
        border-bottom: 1px solid var(--border-color-light);
        display: flex;
        align-items: center;
        gap: 0.65rem;
    }
    .form-grid-3 {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.25rem;
    }
    .form-grid-2 {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.25rem;
    }
    @media (max-width: 900px) {
        .form-grid-3 {
            grid-template-columns: 1fr;
        }
        .form-grid-2 {
            grid-template-columns: 1fr;
        }
    }
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
    }
    .form-group.full-width {
        grid-column: 1 / -1;
    }
    .form-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }
    .form-input, .form-select, .form-textarea {
        background: var(--input-bg);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 0.75rem 1rem;
        color: var(--text-main);
        font-size: 0.92rem;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
        box-sizing: border-box;
        width: 100%;
    }
    .form-input:focus, .form-select:focus, .form-textarea:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px var(--primary-glow);
    }
    .input-hint {
        font-size: 0.75rem;
        color: var(--text-subtle);
    }
    .btn-submit-main {
        background: var(--primary-gradient);
        color: #ffffff;
        font-weight: 700;
        padding: 0.75rem 1.85rem;
        border-radius: 12px;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        box-shadow: 0 4px 14px var(--primary-glow);
        font-size: 0.92rem;
    }
    .btn-submit-main:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 20px var(--primary-glow);
    }
    .branch-card {
        background: var(--input-bg);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 1.25rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 1rem;
        transition: border-color 0.2s;
    }
    .branch-card:hover {
        border-color: var(--primary);
    }
    .table-count-badge {
        background: rgba(99, 102, 241, 0.12);
        color: #6366f1;
        border: 1px solid rgba(99, 102, 241, 0.25);
        padding: 0.35rem 0.75rem;
        border-radius: 8px;
        font-weight: 800;
        font-family: 'Outfit', sans-serif;
        font-size: 0.95rem;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }
</style>
@endsection

@section('content')
<div class="vendor-edit-container" x-data="{ 
    activeTab: '{{ request('tab', 'details') }}',
    showBranchModal: false,
    showBranchEditModal: false,
    branchEdit: {},
    showUserModal: false,
    showUserEditModal: false,
    userEdit: {}
}">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <a href="{{ route('superadmin.vendors.index') }}" class="btn btn-secondary" style="border-radius: 12px; padding: 0.6rem 0.9rem;" title="Վերադառնալ ցուցակ">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.2rem;">
                    <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.35rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0;">
                        {{ $vendor->name }}
                    </h1>
                    @if($vendor->is_active)
                        <span class="badge badge-emerald"><i class="fa-solid fa-circle" style="font-size: 0.45rem;"></i> Ակտիվ</span>
                    @else
                        <span class="badge badge-rose"><i class="fa-solid fa-circle" style="font-size: 0.45rem;"></i> Կասեցված</span>
                    @endif
                </div>
                <div style="font-size: 0.85rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                    <span><i class="fa-solid fa-store" style="color: var(--primary);"></i> ID: #{{ $vendor->id }}</span>
                    <span>•</span>
                    <span><i class="fa-solid fa-link"></i> {{ $vendor->slug }}</span>
                    @if($vendor->custom_domain)
                        <span>•</span>
                        <span style="color: #6366f1; font-weight: 600;"><i class="fa-solid fa-globe"></i> {{ $vendor->custom_domain }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <a href="{{ $vendor->getStorefrontUrl() }}" target="_blank" class="btn btn-secondary" style="border-radius: 12px; font-size: 0.88rem; padding: 0.65rem 1.15rem;">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> Դիտել Մենյուն
            </a>
            <form action="{{ route('superadmin.vendors.toggle', $vendor->id) }}" method="POST" style="margin: 0;">
                @csrf
                <button type="submit" class="btn btn-secondary" style="border-radius: 12px; font-size: 0.88rem; padding: 0.65rem 1.15rem;">
                    @if($vendor->is_active)
                        <i class="fa-solid fa-pause" style="color: #ef4444;"></i> Կասեցնել
                    @else
                        <i class="fa-solid fa-play" style="color: #10b981;"></i> Ակտիվացնել
                    @endif
                </button>
            </form>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="ve-tabs-nav">
        <button type="button" class="ve-tab-btn" :class="{ 'active': activeTab === 'details' }" @click="activeTab = 'details'">
            <i class="fa-solid fa-sliders"></i> Տվյալներ և Հղումներ
        </button>
        <button type="button" class="ve-tab-btn" :class="{ 'active': activeTab === 'branches' }" @click="activeTab = 'branches'">
            <i class="fa-solid fa-location-dot"></i> Մասնաճյուղեր և Սեղաններ ({{ $vendor->locations->count() }})
        </button>
        <button type="button" class="ve-tab-btn" :class="{ 'active': activeTab === 'users' }" @click="activeTab = 'users'">
            <i class="fa-solid fa-users"></i> Օգտատերեր և Աշխատակազմ ({{ $vendor->users->count() }})
        </button>
        <button type="button" class="ve-tab-btn" :class="{ 'active': activeTab === 'notifications' }" @click="activeTab = 'notifications'">
            <i class="fa-solid fa-bell"></i> Ծանուցումներ & Ամրագրումներ
        </button>
    </div>

    <!-- TAB 1: DETAILS & LINKS -->
    <div x-show="activeTab === 'details'">
        <form action="{{ route('superadmin.vendors.update', $vendor->id) }}" method="POST">
            @csrf
            @method('PUT')

            <!-- Storefront Links & Identity -->
            <div class="ve-card">
                <div class="ve-section-title">
                    <i class="fa-solid fa-link" style="color: #6366f1;"></i>
                    <span>Մենյուի Հղումներ և Դոմեյն</span>
                </div>
                <div class="form-grid-3">
                    <div class="form-group">
                        <label class="form-label">
                            <span>Մենյուի Slug (URL Հասցե) *</span>
                        </label>
                        <input type="text" name="slug" value="{{ old('slug', $vendor->slug) }}" required class="form-input" placeholder="my-restaurant">
                        <span class="input-hint">Հանրային հասցե՝ <code>/m/{{ $vendor->slug }}</code></span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <span>Սեփական Դոմեյն (Custom Domain)</span>
                        </label>
                        <input type="text" name="custom_domain" value="{{ old('custom_domain', $vendor->custom_domain) }}" class="form-input" placeholder="menu.myrestaurant.am">
                        <span class="input-hint">Օրինակ՝ <code>menu.cafe.am</code> (առանց http://)</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <span>Արժույթ *</span>
                        </label>
                        <select name="currency" class="form-select" required>
                            <option value="AMD" {{ in_array(old('currency', $vendor->currency), ['AMD', '֏'], true) ? 'selected' : '' }}>֏ (ՀՀ Դրամ)</option>
                            <option value="USD" {{ old('currency', $vendor->currency) === 'USD' ? 'selected' : '' }}>USD ($ - US Dollar)</option>
                            <option value="EUR" {{ old('currency', $vendor->currency) === 'EUR' ? 'selected' : '' }}>EUR (€ - Euro)</option>
                            <option value="RUB" {{ old('currency', $vendor->currency) === 'RUB' ? 'selected' : '' }}>RUB (₽ - Российский рубль)</option>
                            <option value="GEL" {{ old('currency', $vendor->currency) === 'GEL' ? 'selected' : '' }}>GEL (₾ - Georgian Lari)</option>
                            <option value="GBP" {{ old('currency', $vendor->currency) === 'GBP' ? 'selected' : '' }}>GBP (£ - British Pound)</option>
                            <option value="AED" {{ old('currency', $vendor->currency) === 'AED' ? 'selected' : '' }}>AED (د.إ - UAE Dirham)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- General & Legal Details -->
            <div class="ve-card">
                <div class="ve-section-title">
                    <i class="fa-solid fa-building" style="color: #10b981;"></i>
                    <span>Հիմնական և Իրավաբանական Տվյալներ</span>
                </div>
                <div class="form-grid-3">
                    <div class="form-group">
                        <label class="form-label">Հաստատության Անվանում *</label>
                        <input type="text" name="name" value="{{ old('name', $vendor->name) }}" required class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Տեսակ *</label>
                        <select name="type" class="form-select" required>
                            <option value="restaurant" {{ old('type', $vendor->type) === 'restaurant' ? 'selected' : '' }}>Ռեստորան (Restaurant)</option>
                            <option value="cafe" {{ old('type', $vendor->type) === 'cafe' ? 'selected' : '' }}>Սրճարան (Cafe)</option>
                            <option value="hotel" {{ old('type', $vendor->type) === 'hotel' ? 'selected' : '' }}>Հյուրանոց (Hotel)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Բաժանորդագրության Փաթեթ</label>
                        <select name="subscription_plan_id" class="form-select">
                            <option value="">Ընտրել փաթեթ...</option>
                            @foreach($plans as $p)
                                <option value="{{ $p->id }}" {{ old('subscription_plan_id', $vendor->subscription_plan_id) == $p->id ? 'selected' : '' }}>
                                    {{ $p->name }} ({{ number_format($p->price) }} {{ $p->currency }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Իրավաբանական Անուն (ՍՊԸ / Ա/Ձ)</label>
                        <input type="text" name="legal_name" value="{{ old('legal_name', $vendor->legal_name) }}" class="form-input" placeholder="«Բիստրո» ՍՊԸ">
                    </div>

                    <div class="form-group">
                        <label class="form-label">ՀՎՀՀ (Tax ID)</label>
                        <input type="text" name="tax_id" value="{{ old('tax_id', $vendor->tax_id) }}" class="form-input" placeholder="01234567">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Հանրային Հեռախոսահամար</label>
                        <input type="text" name="phone" value="{{ old('phone', $vendor->phone) }}" class="form-input" placeholder="+374 99 123456">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Հանրային Էլ․ Փոստ</label>
                        <input type="email" name="email" value="{{ old('email', $vendor->email) }}" class="form-input" placeholder="info@bistro.am">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Տնօրենի Անուն</label>
                        <input type="text" name="director_name" value="{{ old('director_name', $vendor->director_name) }}" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Տնօրենի Հեռախոսահամար</label>
                        <input type="text" name="director_phone" value="{{ old('director_phone', $vendor->director_phone) }}" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Մենեջերի Անուն</label>
                        <input type="text" name="contact_person_name" value="{{ old('contact_person_name', $vendor->contact_person_name) }}" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Մենեջերի Հեռախոսահամար</label>
                        <input type="text" name="contact_person_phone" value="{{ old('contact_person_phone', $vendor->contact_person_phone) }}" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Իրավաբանական Հասցե</label>
                        <input type="text" name="legal_address" value="{{ old('legal_address', $vendor->legal_address) }}" class="form-input">
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">Գործունեության / Գլխավոր Հասցե</label>
                        <input type="text" name="operating_address" value="{{ old('operating_address', $vendor->operating_address) }}" class="form-input">
                    </div>
                </div>
            </div>

            <!-- Appearance & Operations -->
            <div class="ve-card">
                <div class="ve-section-title">
                    <i class="fa-solid fa-palette" style="color: var(--primary);"></i>
                    <span>Թեմա, Դիզայն և Պատվերների Պարամետրեր</span>
                </div>
                <div class="form-grid-3">
                    <div class="form-group">
                        <label class="form-label">Մենյուի Թեմա (Template) *</label>
                        <select name="menu_template_id" class="form-select" required>
                            @foreach($templates as $tmpl)
                                <option value="{{ $tmpl->id }}" {{ old('menu_template_id', $vendor->menu_template_id) == $tmpl->id ? 'selected' : '' }}>
                                    {{ $tmpl->name }} ({{ $tmpl->slug }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Գունային Ռեժիմ</label>
                        <select name="theme_mode" class="form-select">
                            <option value="light" {{ old('theme_mode', $vendor->theme_mode) === 'light' ? 'selected' : '' }}>Բաց (Light)</option>
                            <option value="dark" {{ old('theme_mode', $vendor->theme_mode) === 'dark' ? 'selected' : '' }}>Մուգ (Dark)</option>
                            <option value="auto" {{ old('theme_mode', $vendor->theme_mode) === 'auto' ? 'selected' : '' }}>Ավտո (Auto)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Գլխավոր Գույն (Primary)</label>
                        <input type="color" name="primary_color" value="{{ old('primary_color', $vendor->primary_color ?? '#e11d48') }}" style="height: 42px; padding: 2px 6px; border-radius: 10px; cursor: pointer; width: 100%;">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Wi-Fi Անվանում (SSID)</label>
                        <input type="text" name="wifi_ssid" value="{{ old('wifi_ssid', $vendor->wifi_ssid) }}" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Wi-Fi Գաղտնաբառ</label>
                        <input type="text" name="wifi_password" value="{{ old('wifi_password', $vendor->wifi_password) }}" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Աշխատանքային Ժամեր</label>
                        <input type="text" name="working_hours" value="{{ old('working_hours', $vendor->working_hours) }}" class="form-input" placeholder="10:00 - 23:00">
                    </div>
                </div>

                <div style="margin-top: 1.5rem; display: flex; gap: 2rem; flex-wrap: wrap;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; cursor: pointer;">
                        <input type="checkbox" name="delivery_enabled" value="1" {{ old('delivery_enabled', $vendor->delivery_enabled) ? 'checked' : '' }}>
                        <span style="font-weight: 600;">Առաքում (Delivery) Ակտիվ է</span>
                    </label>

                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; cursor: pointer;">
                        <input type="checkbox" name="takeaway_enabled" value="1" {{ old('takeaway_enabled', $vendor->takeaway_enabled) ? 'checked' : '' }}>
                        <span style="font-weight: 600;">Takeaway (Ինքնարտահանում) Ակտիվ է</span>
                    </label>

                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; cursor: pointer;">
                        <input type="checkbox" name="allow_whatsapp_orders" value="1" {{ old('allow_whatsapp_orders', $vendor->allow_whatsapp_orders ?? true) ? 'checked' : '' }}>
                        <span style="font-weight: 600; color: #22c55e;"><i class="fa-brands fa-whatsapp"></i> WhatsApp Կոճակն Ակտիվ է</span>
                    </label>

                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; cursor: pointer;">
                        <input type="checkbox" name="service_fee_enabled" value="1" {{ old('service_fee_enabled', $vendor->service_fee_enabled) ? 'checked' : '' }}>
                        <span style="font-weight: 600;">Սպասարկման Վճար (Service Fee)</span>
                    </label>
                </div>
            </div>

            <!-- Submit Button -->
            <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                <button type="submit" class="btn-submit-main">
                    <i class="fa-solid fa-floppy-disk"></i> Պահպանել Փոփոխությունները
                </button>
            </div>
        </form>
    </div>

    <!-- TAB 2: BRANCHES & TABLES -->
    <div x-show="activeTab === 'branches'" style="display: none;">
        <div class="ve-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0;">
                        Մասնաճյուղեր և Սեղանների Կառավարում
                    </h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0 0 0;">
                        Փոփոխեք մասնաճյուղի տվյալները, ավելացրեք նոր մասնաճյուղ և անմիջապես կարգավորեք սեղանների քանակը։
                    </p>
                </div>
                <button type="button" @click="showBranchModal = true" class="btn btn-primary" style="border-radius: 12px; font-weight: 700;">
                    <i class="fa-solid fa-plus"></i> Ավելացնել Մասնաճյուղ
                </button>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.25rem;">
                @foreach($vendor->locations as $loc)
                    <div class="branch-card">
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                                <div>
                                    <h4 style="font-family: 'Outfit'; font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0;">
                                        {{ $loc->name }}
                                    </h4>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
                                        <code>slug: {{ $loc->slug }}</code>
                                    </div>
                                </div>
                                @if($loc->is_active)
                                    <span class="badge badge-emerald">Ակտիվ</span>
                                @else
                                    <span class="badge badge-rose">Պասիվ</span>
                                @endif
                            </div>

                            <div style="display: flex; flex-direction: column; gap: 0.4rem; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
                                @if($loc->address)
                                    <div><i class="fa-solid fa-map-pin" style="color: #ef4444; width: 16px;"></i> {{ $loc->address }}</div>
                                @endif
                                @if($loc->phone)
                                    <div><i class="fa-solid fa-phone" style="color: #10b981; width: 16px;"></i> {{ $loc->phone }}</div>
                                @endif
                                @if($loc->whatsapp_number)
                                    <div><i class="fa-brands fa-whatsapp" style="color: #22c55e; width: 16px;"></i> {{ $loc->whatsapp_number }}</div>
                                @endif
                                @if($loc->wifi_ssid)
                                    <div><i class="fa-solid fa-wifi" style="color: #06b6d4; width: 16px;"></i> {{ $loc->wifi_ssid }} @if($loc->wifi_password) ({{ $loc->wifi_password }}) @endif</div>
                                @endif
                            </div>

                            <!-- Fast Table Count Updater -->
                            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.85rem; margin-bottom: 0.5rem;">
                                <form action="{{ route('superadmin.vendors.locations.tables', [$vendor->id, $loc->id]) }}" method="POST" style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin: 0;">
                                    @csrf
                                    <div>
                                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                                            Սեղանների Քանակ
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--text-subtle);">QR Menu սեղաններ</div>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <input type="number" name="table_count" value="{{ $loc->table_count }}" min="1" max="500" required class="form-input" style="width: 80px; text-align: center; font-weight: 800; font-size: 1.05rem; padding: 0.4rem 0.5rem;">
                                        <button type="submit" class="btn btn-secondary" style="padding: 0.45rem 0.75rem; font-size: 0.82rem; border-radius: 8px;" title="Թարմացնել սեղանների թիվը">
                                            <i class="fa-solid fa-check"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Card Footer Actions -->
                        <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 0.75rem; border-top: 1px solid var(--border-color);">
                            <span style="font-size: 0.75rem; color: var(--text-muted);">
                                {{ $loc->allow_dine_in_orders ? '🍽️ Dine-in' : '' }} {{ $loc->allow_whatsapp_orders ? '💬 WhatsApp' : '' }}
                            </span>
                            <div style="display: flex; gap: 0.4rem;">
                                <button type="button" @click="branchEdit = {{ json_encode($loc) }}; showBranchEditModal = true" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem; border-radius: 8px;">
                                    <i class="fa-solid fa-pen-to-square"></i> Խմբագրել
                                </button>
                                @if($vendor->locations->count() > 1)
                                    <form action="{{ route('superadmin.vendors.locations.destroy', [$vendor->id, $loc->id]) }}" method="POST" onsubmit="return confirm('Վստա՞հ եք, որ ցանկանում եք ջնջել «{{ $loc->name }}» մասնաճյուղը։');" style="margin: 0;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem; border-radius: 8px; color: #ef4444;" title="Ջնջել մասնաճյուղը">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- TAB 3: USERS & STAFF -->
    <div x-show="activeTab === 'users'" style="display: none;">
        <div class="ve-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0;">
                        Գործընկերոջ Օգտատերեր և Աշխատակազմ
                    </h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0 0 0;">
                        Ավելացրեք նոր աշխատակիցներ, փոփոխեք դերերը, կցված մասնաճյուղերը կամ փոխեք նրանց գաղտնաբառը։
                    </p>
                </div>
                <button type="button" @click="showUserModal = true" class="btn btn-primary" style="border-radius: 12px; font-weight: 700;">
                    <i class="fa-solid fa-user-plus"></i> Ավելացնել Օգտատեր
                </button>
            </div>

            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Անուն</th>
                            <th>Էլ․ Փոստ</th>
                            <th>Դեր (Role)</th>
                            <th>Մասնաճյուղ</th>
                            <th>2FA Կարգավիճակ</th>
                            <th style="text-align: right;">Գործողություններ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($vendor->users as $u)
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-main);">{{ $u->name }}</div>
                                    @if($u->phone)
                                        <div style="font-size: 0.72rem; color: var(--text-muted);">{{ $u->phone }}</div>
                                    @endif
                                </td>
                                <td>
                                    <code>{{ $u->email }}</code>
                                </td>
                                <td>
                                    @if($u->role === 'vendor_owner')
                                        <span class="badge badge-amber"><i class="fa-solid fa-crown"></i> Սեփականատեր</span>
                                    @elseif($u->role === 'branch_manager' || $u->role === 'manager')
                                        <span class="badge badge-indigo"><i class="fa-solid fa-user-tie"></i> Մենեջեր</span>
                                    @elseif($u->role === 'waiter')
                                        <span class="badge badge-cyan"><i class="fa-solid fa-bell-concierge"></i> Մատուցող</span>
                                    @elseif($u->role === 'kitchen_staff')
                                        <span class="badge badge-emerald"><i class="fa-solid fa-kitchen-set"></i> Խոհանոց</span>
                                    @else
                                        <span class="badge">{{ $u->role }}</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $u->location?->name ?? 'Բոլորը (All)' }}
                                </td>
                                <td>
                                    @if($u->two_factor_enabled)
                                        <span class="badge badge-emerald"><i class="fa-solid fa-shield-check"></i> Ակտիվ ({{ $u->two_factor_type }})</span>
                                    @else
                                        <span style="font-size: 0.78rem; color: var(--text-muted);">Անջատված</span>
                                    @endif
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 0.4rem;">
                                        <button type="button" @click="userEdit = {{ json_encode($u) }}; showUserEditModal = true" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem; border-radius: 8px;">
                                            <i class="fa-solid fa-pen-to-square"></i> Խմբագրել
                                        </button>
                                        @if($vendor->users->count() > 1 && $u->id !== auth()->id())
                                            <form action="{{ route('superadmin.vendors.users.destroy', [$vendor->id, $u->id]) }}" method="POST" onsubmit="return confirm('Վստա՞հ եք, որ ցանկանում եք հեռացնել «{{ $u->name }}» օգտատիրոջը։');" style="margin: 0;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem; border-radius: 8px; color: #ef4444;" title="Ջնջել օգտատիրոջը">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 4: RESERVATION & PRE-ORDER NOTIFICATION RESTRICTIONS -->
    <div x-show="activeTab === 'notifications'" style="display: none;">
        @php
            $restrictions = $vendor->superadmin_notification_restrictions;
        @endphp

        <!-- Restrictions Card -->
        <div class="ve-card">
            <div class="ve-section-title">
                <i class="fa-solid fa-shield-halved" style="color: #ef4444;"></i>
                <span>Super Admin Սահմանափակումներ (Tenant Permissions & Overrides)</span>
            </div>
            <p style="font-size: 0.88rem; color: var(--text-muted); margin: -0.5rem 0 1.5rem 0;">
                Այս կարգավորումներն ունեն գերակայություն ռեստորանի անձնական կարգավորումների նկատմամբ։ Եթե այստեղ որևէ ֆունկցիա անջատված է, ռեստորանը չի կարող այն միացնել։
            </p>

            <form action="{{ route('superadmin.vendors.notifications', $vendor->id) }}" method="POST">
                @csrf

                <!-- Authoritative Operating Mode Control -->
                <div style="background: rgba(99, 102, 241, 0.06); border: 1px solid rgba(99, 102, 241, 0.25); border-radius: 14px; padding: 1.25rem; margin-bottom: 1.25rem;">
                    <div style="font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
                        <i class="fa-solid fa-toggle-on" style="color: #6366f1;"></i> Թույլատրված Աշխատանքային Ռեժիմ (Authoritative Operating Mode)
                    </div>
                    <p style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 0.85rem;">
                        Ընտրեք այս ռեստորանի համար Super Admin-ի կողմից լիազորված առավելագույն ռեժիմը։ Գործընկերը չի կարող գերազանցել այս սահմանափակումը։
                    </p>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 0.75rem;">
                        <label style="display: flex; align-items: flex-start; gap: 0.6rem; padding: 0.75rem 0.95rem; border-radius: 10px; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); cursor: pointer;">
                            <input type="radio" name="permitted_mode" value="disabled" {{ ($restrictions['permitted_mode'] ?? '') === 'disabled' ? 'checked' : '' }} style="margin-top: 0.2rem;">
                            <div>
                                <div style="font-size: 0.88rem; font-weight: 700; color: #ef4444;"><i class="fa-solid fa-ban"></i> Անջատված (Disabled)</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">Երկու մոդուլներն էլ արգելափակված են</div>
                            </div>
                        </label>
                        <label style="display: flex; align-items: flex-start; gap: 0.6rem; padding: 0.75rem 0.95rem; border-radius: 10px; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); cursor: pointer;">
                            <input type="radio" name="permitted_mode" value="reservation_only" {{ ($restrictions['permitted_mode'] ?? '') === 'reservation_only' ? 'checked' : '' }} style="margin-top: 0.2rem;">
                            <div>
                                <div style="font-size: 0.88rem; font-weight: 700; color: #f59e0b;"><i class="fa-solid fa-calendar-day"></i> Միայն Ամրագրումներ</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">Սեղանների ամրագրում՝ առանց նախապատվերի</div>
                            </div>
                        </label>
                        <label style="display: flex; align-items: flex-start; gap: 0.6rem; padding: 0.75rem 0.95rem; border-radius: 10px; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); cursor: pointer;">
                            <input type="radio" name="permitted_mode" value="reservation_and_preorder" {{ ($restrictions['permitted_mode'] ?? 'reservation_and_preorder') === 'reservation_and_preorder' ? 'checked' : '' }} style="margin-top: 0.2rem;">
                            <div>
                                <div style="font-size: 0.88rem; font-weight: 700; color: #10b981;"><i class="fa-solid fa-utensils"></i> Ամրագրումներ և Նախապատվերներ</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">Լիարժեք հասանելիություն երկու մոդուլներին</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.25rem;">
                    <!-- Reservations Module -->
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                            <div style="font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fa-solid fa-calendar-check" style="color: #6366f1;"></i> Ամրագրումների Համակարգ
                            </div>
                            <label style="position: relative; display: inline-flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" name="reservations_enabled" value="1" {{ !empty($restrictions['reservations_enabled']) ? 'checked' : '' }} style="width: 1.2rem; height: 1.2rem; accent-color: var(--primary);">
                            </label>
                        </div>
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">
                            Թույլատրել սեղանի ամրագրումների ընդունումը այս ռեստորանի մենյուում։
                        </p>
                    </div>

                    <!-- Pre-orders Module -->
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                            <div style="font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fa-solid fa-utensils" style="color: #10b981;"></i> Նախապատվերներ (Pre-Orders)
                            </div>
                            <label style="position: relative; display: inline-flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" name="preorders_enabled" value="1" {{ !empty($restrictions['preorders_enabled']) ? 'checked' : '' }} style="width: 1.2rem; height: 1.2rem; accent-color: var(--primary);">
                            </label>
                        </div>
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">
                            Թույլատրել ուտեստների նախապես ընտրությունը ամրագրման պահին։
                        </p>
                    </div>

                    <!-- Reservation Emails -->
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                            <div style="font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fa-solid fa-envelope" style="color: #3b82f6;"></i> Ամրագրման Էլ․ Ծանուցումներ
                            </div>
                            <label style="position: relative; display: inline-flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" name="reservation_emails_enabled" value="1" {{ !empty($restrictions['reservation_emails_enabled']) ? 'checked' : '' }} style="width: 1.2rem; height: 1.2rem; accent-color: var(--primary);">
                            </label>
                        </div>
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">
                            Թույլատրել հաստատման, փոփոխման և չեղարկման նամակների ուղարկումը։
                        </p>
                    </div>

                    <!-- Pre-order Emails -->
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                            <div style="font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fa-solid fa-file-invoice" style="color: #8b5cf6;"></i> Նախապատվերի Էլ․ Ծանուցումներ
                            </div>
                            <label style="position: relative; display: inline-flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" name="preorder_emails_enabled" value="1" {{ !empty($restrictions['preorder_emails_enabled']) ? 'checked' : '' }} style="width: 1.2rem; height: 1.2rem; accent-color: var(--primary);">
                            </label>
                        </div>
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">
                            Թույլատրել նախապատվերի մանրամասների էլ․ նամակները հաճախորդին և խոհանոցին։
                        </p>
                    </div>

                    <!-- Customer Reminders -->
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                            <div style="font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fa-solid fa-clock" style="color: #f59e0b;"></i> Հաճախորդի Ավտոմատ Հիշեցումներ
                            </div>
                            <label style="position: relative; display: inline-flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" name="customer_reminders_enabled" value="1" {{ !empty($restrictions['customer_reminders_enabled']) ? 'checked' : '' }} style="width: 1.2rem; height: 1.2rem; accent-color: var(--primary);">
                            </label>
                        </div>
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">
                            Թույլատրել պլանավորված հիշեցումների ուղարկումը ժամանումից առաջ։
                        </p>
                    </div>

                    <!-- Staff Reminders -->
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                            <div style="font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fa-solid fa-bell-concierge" style="color: #ec4899;"></i> Աշխատակազմի Հիշեցումներ
                            </div>
                            <label style="position: relative; display: inline-flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" name="restaurant_reminders_enabled" value="1" {{ !empty($restrictions['restaurant_reminders_enabled']) ? 'checked' : '' }} style="width: 1.2rem; height: 1.2rem; accent-color: var(--primary);">
                            </label>
                        </div>
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">
                            Թույլատրել գալիք ամրագրումների հիշեցումների առաքումը ադմինիստրատորին/մենեջերին։
                        </p>
                    </div>

                    <!-- Template Customization -->
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem; grid-column: 1 / -1;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                            <div style="font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fa-solid fa-pen-to-square" style="color: #06b6d4;"></i> Թույլատրել Շաբլոնների Խմբագրում Ռեստորանի Կողմից
                            </div>
                            <label style="position: relative; display: inline-flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" name="template_customization_allowed" value="1" {{ !empty($restrictions['template_customization_allowed']) ? 'checked' : '' }} style="width: 1.2rem; height: 1.2rem; accent-color: var(--primary);">
                            </label>
                        </div>
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">
                            Եթե ապաակտիվացված է, ռեստորանը կարող է միայն դիտել շաբլոնները և ուղարկել թեստային նամակ, սակայն չի կարող փոփոխել դրանց տեքստը կամ թեման։
                        </p>
                    </div>
                </div>

                <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary" style="border-radius: 12px; font-weight: 700; padding: 0.75rem 1.75rem;">
                        <i class="fa-solid fa-floppy-disk"></i> Պահպանել Super Admin Սահմանափակումները
                    </button>
                </div>
            </form>
        </div>

        <!-- Tenant Notification Delivery Stats & Logs -->
        <div class="ve-card">
            <div class="ve-section-title">
                <i class="fa-solid fa-chart-line" style="color: #6366f1;"></i>
                <span>Ծանուցումների Առաքման Վիճակագրություն & Վերջին Լոգեր</span>
            </div>

            <!-- Stats grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.75rem;">
                <div style="background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 14px; padding: 1.25rem;">
                    <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Ընդհանուր Նամակներ</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #6366f1; margin-top: 0.35rem; font-family: 'Outfit';">
                        {{ $notificationStats['total_emails'] ?? 0 }}
                    </div>
                </div>
                <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 14px; padding: 1.25rem;">
                    <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Հաջող Առաքված</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #10b981; margin-top: 0.35rem; font-family: 'Outfit';">
                        {{ $notificationStats['delivered'] ?? 0 }}
                    </div>
                </div>
                <div style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.2); border-radius: 14px; padding: 1.25rem;">
                    <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Հերթում (Queued)</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #f59e0b; margin-top: 0.35rem; font-family: 'Outfit';">
                        {{ $notificationStats['queued'] ?? 0 }}
                    </div>
                </div>
                <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 14px; padding: 1.25rem;">
                    <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Անհաջող (Failed)</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #ef4444; margin-top: 0.35rem; font-family: 'Outfit';">
                        {{ $notificationStats['failed'] ?? 0 }}
                    </div>
                </div>
            </div>

            <!-- Recent Logs Table -->
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Կարգավիճակ</th>
                            <th>Ստացող</th>
                            <th>Թեմա</th>
                            <th>Կատեգորիա</th>
                            <th>Ամսաթիվ</th>
                            <th>Սխալ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($notificationStats['recent_logs'] ?? [] as $log)
                            <tr>
                                <td>
                                    @if(in_array($log->status, ['sent', 'delivered']))
                                        <span class="badge badge-emerald"><i class="fa-solid fa-check"></i> {{ ucfirst($log->status) }}</span>
                                    @elseif($log->status === 'failed')
                                        <span class="badge badge-rose"><i class="fa-solid fa-xmark"></i> Failed</span>
                                    @elseif($log->status === 'queued')
                                        <span class="badge badge-amber"><i class="fa-solid fa-clock"></i> Queued</span>
                                    @else
                                        <span class="badge">{{ ucfirst($log->status) }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: var(--text-main);">{{ $log->recipient_email }}</div>
                                    @if($log->recipient_name)
                                        <div style="font-size: 0.72rem; color: var(--text-muted);">{{ $log->recipient_name }}</div>
                                    @endif
                                </td>
                                <td>{{ Str::limit($log->subject, 35) }}</td>
                                <td><span class="badge badge-indigo">{{ $log->category }}</span></td>
                                <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                                    {{ $log->created_at->format('d.m.Y H:i') }}
                                </td>
                                <td style="font-size: 0.75rem; color: #ef4444;">
                                    {{ $log->error_summary ?: '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                    Այս գործընկերոջ համար էլ․ փոստի առաքման գրառումներ դեռևս չկան։
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tenant Feature Configuration Audit Trail -->
        <div class="ve-card" style="margin-top: 1.5rem;">
            <div class="ve-section-title">
                <i class="fa-solid fa-clock-rotate-left" style="color: #10b981;"></i>
                <span>Ամրագրումների Կարգավորումների Փոփոխման Պատմություն (Audit Log)</span>
            </div>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin: -0.5rem 0 1.25rem 0;">
                Տվյալներ այն մասին, թե ով, երբ և ինչ փոփոխություններ է կատարել այս ռեստորանի ֆունկցիոնալ կարգավիճակներում։
            </p>

            <div class="table-responsive">
                <table class="table modern-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Ամսաթիվ / Ժամ</th>
                            <th>Կատարող (Admin)</th>
                            <th>Գործողություն / Ամփոփում</th>
                            <th>IP Հասցե</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($featureAuditLogs ?? [] as $audit)
                            <tr>
                                <td style="font-size: 0.82rem; color: var(--text-muted); white-space: nowrap;">
                                    <i class="fa-solid fa-calendar-days" style="color: #6366f1; margin-right: 0.35rem;"></i>
                                    {{ $audit->created_at->format('d.m.Y H:i:s') }}
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-main);">
                                        {{ $audit->actor?->name ?: ($audit->metadata['actor_email'] ?? 'Super Admin') }}
                                    </div>
                                    <div style="font-size: 0.72rem; color: var(--text-muted);">
                                        {{ $audit->actor?->email ?: 'System' }}
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 0.85rem; color: var(--text-main);">
                                        {{ $audit->action }}
                                    </div>
                                    @if(!empty($audit->metadata['permitted_mode']))
                                        <div style="margin-top: 0.25rem;">
                                            <span class="badge badge-indigo">Mode: {{ $audit->metadata['permitted_mode'] }}</span>
                                            <span class="badge {{ !empty($audit->metadata['reservations_enabled']) ? 'badge-emerald' : 'badge-rose' }}">
                                                Res: {{ !empty($audit->metadata['reservations_enabled']) ? 'On' : 'Off' }}
                                            </span>
                                            <span class="badge {{ !empty($audit->metadata['preorders_enabled']) ? 'badge-emerald' : 'badge-rose' }}">
                                                Pre-Order: {{ !empty($audit->metadata['preorders_enabled']) ? 'On' : 'Off' }}
                                            </span>
                                        </div>
                                    @endif
                                </td>
                                <td style="font-size: 0.78rem; font-family: monospace; color: var(--text-muted);">
                                    {{ $audit->ip_address ?: '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 1.5rem; color: var(--text-muted);">
                                    Այս գործընկերոջ կարգավորումների փոփոխման պատմություն դեռևս գրանցված չէ։
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL: ADD BRANCH -->
    <div x-show="showBranchModal" x-transition.opacity class="modern-modal-overlay" style="display: none;">
        <div class="modern-modal-box" @click.outside="showBranchModal = false">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 0.85rem; border-bottom: 1px solid var(--border-color);">
                <h3 style="font-family: 'Outfit'; font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    Ավելացնել Նոր Մասնաճյուղ
                </h3>
                <button type="button" @click="showBranchModal = false" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="{{ route('superadmin.vendors.locations.store', $vendor->id) }}" method="POST">
                @csrf
                <div class="form-grid-2">
                    <div class="form-group full-width">
                        <label class="form-label">Մասնաճյուղի Անվանում *</label>
                        <input type="text" name="name" required class="form-input" placeholder="Օրինակ՝ Կենտրոն Մասնաճյուղ">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Սեղանների Քանակ *</label>
                        <input type="number" name="table_count" value="20" min="1" max="500" required class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Հեռախոսահամար</label>
                        <input type="text" name="phone" class="form-input" placeholder="+374 10 123456">
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">Հասցե</label>
                        <input type="text" name="address" class="form-input" placeholder="ք. Երևան, Ամիրյան 4">
                    </div>

                    <div class="form-group">
                        <label class="form-label">WhatsApp Համար</label>
                        <input type="text" name="whatsapp_number" class="form-input" placeholder="+37499123456">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Նվազագույն Պատվեր (֏)</label>
                        <input type="number" name="minimum_order_amount" value="0" min="0" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Wi-Fi Անուն (SSID)</label>
                        <input type="text" name="wifi_ssid" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Wi-Fi Գաղտնաբառ</label>
                        <input type="text" name="wifi_password" class="form-input">
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">Աշխատանքային Ժամեր</label>
                        <input type="text" name="working_hours" class="form-input" placeholder="Երկ-Կիր 10:00 - 23:00">
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" @click="showBranchModal = false">Չեղարկել</button>
                    <button type="submit" class="btn btn-primary">Ավելացնել</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: EDIT BRANCH -->
    <div x-show="showBranchEditModal" x-transition.opacity class="modern-modal-overlay" style="display: none;">
        <div class="modern-modal-box" @click.outside="showBranchEditModal = false">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 0.85rem; border-bottom: 1px solid var(--border-color);">
                <h3 style="font-family: 'Outfit'; font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    Խմբագրել Մասնաճյուղը
                </h3>
                <button type="button" @click="showBranchEditModal = false" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form :action="'{{ url('/superadmin/vendors/' . $vendor->id . '/locations') }}/' + branchEdit.id" method="POST">
                @csrf
                @method('PUT')
                <div class="form-grid-2">
                    <div class="form-group full-width">
                        <label class="form-label">Մասնաճյուղի Անվանում *</label>
                        <input type="text" name="name" x-model="branchEdit.name" required class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Սեղանների Քանակ *</label>
                        <input type="number" name="table_count" x-model="branchEdit.table_count" min="1" max="500" required class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Հեռախոսահամար</label>
                        <input type="text" name="phone" x-model="branchEdit.phone" class="form-input">
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">Հասցե</label>
                        <input type="text" name="address" x-model="branchEdit.address" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">WhatsApp Համար</label>
                        <input type="text" name="whatsapp_number" x-model="branchEdit.whatsapp_number" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Նվազագույն Պատվեր (֏)</label>
                        <input type="number" name="minimum_order_amount" x-model="branchEdit.minimum_order_amount" min="0" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Wi-Fi Անուն (SSID)</label>
                        <input type="text" name="wifi_ssid" x-model="branchEdit.wifi_ssid" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Wi-Fi Գաղտնաբառ</label>
                        <input type="text" name="wifi_password" x-model="branchEdit.wifi_password" class="form-input">
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">Աշխատանքային Ժամեր</label>
                        <input type="text" name="working_hours" x-model="branchEdit.working_hours" class="form-input">
                    </div>

                    <div class="form-group full-width" style="margin-top: 0.5rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.88rem; cursor: pointer;">
                            <input type="checkbox" name="is_active" value="1" :checked="branchEdit.is_active">
                            <span style="font-weight: 600;">Մասնաճյուղն ակտիվ է</span>
                        </label>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" @click="showBranchEditModal = false">Չեղարկել</button>
                    <button type="submit" class="btn btn-primary">Պահպանել</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: ADD USER -->
    <div x-show="showUserModal" x-transition.opacity class="modern-modal-overlay" style="display: none;">
        <div class="modern-modal-box" @click.outside="showUserModal = false">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 0.85rem; border-bottom: 1px solid var(--border-color);">
                <h3 style="font-family: 'Outfit'; font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    Ավելացնել Օգտատեր / Աշխատակից
                </h3>
                <button type="button" @click="showUserModal = false" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="{{ route('superadmin.vendors.users.store', $vendor->id) }}" method="POST">
                @csrf
                <div class="form-grid-2">
                    <div class="form-group full-width">
                        <label class="form-label">Անուն Ազգանուն *</label>
                        <input type="text" name="name" required class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Էլ․ Փոստ *</label>
                        <input type="email" name="email" required class="form-input" placeholder="user@bistro.am">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Հեռախոսահամար</label>
                        <input type="text" name="phone" class="form-input" placeholder="+374 99 000000">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Դեր (Role) *</label>
                        <select name="role" required class="form-select">
                            <option value="vendor_owner">Սեփականատեր (Owner)</option>
                            <option value="branch_manager">Մասնաճյուղի Մենեջեր (Manager)</option>
                            <option value="waiter">Մատուցող (Waiter)</option>
                            <option value="kitchen_staff">Խոհանոց (Kitchen)</option>
                            <option value="staff">Աշխատակից (Staff)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Կցված Մասնաճյուղ</label>
                        <select name="location_id" class="form-select">
                            <option value="">Բոլոր մասնաճյուղերը</option>
                            @foreach($vendor->locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">Գաղտնաբառ * (նվազագույնը 6 նիշ)</label>
                        <input type="password" name="password" required minlength="6" class="form-input">
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" @click="showUserModal = false">Չեղարկել</button>
                    <button type="submit" class="btn btn-primary">Ավելացնել Օգտատեր</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: EDIT USER -->
    <div x-show="showUserEditModal" x-transition.opacity class="modern-modal-overlay" style="display: none;">
        <div class="modern-modal-box" @click.outside="showUserEditModal = false">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 0.85rem; border-bottom: 1px solid var(--border-color);">
                <h3 style="font-family: 'Outfit'; font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    Խմբագրել Օգտատիրոջ Տվյալները & Գաղտնաբառը
                </h3>
                <button type="button" @click="showUserEditModal = false" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form :action="'{{ url('/superadmin/vendors/' . $vendor->id . '/users') }}/' + userEdit.id" method="POST">
                @csrf
                @method('PUT')
                <div class="form-grid-2">
                    <div class="form-group full-width">
                        <label class="form-label">Անուն Ազգանուն *</label>
                        <input type="text" name="name" x-model="userEdit.name" required class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Էլ․ Փոստ *</label>
                        <input type="email" name="email" x-model="userEdit.email" required class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Հեռախոսահամար</label>
                        <input type="text" name="phone" x-model="userEdit.phone" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Դեր (Role) *</label>
                        <select name="role" x-model="userEdit.role" required class="form-select">
                            <option value="vendor_owner">Սեփականատեր (Owner)</option>
                            <option value="branch_manager">Մասնաճյուղի Մենեջեր (Manager)</option>
                            <option value="waiter">Մատուցող (Waiter)</option>
                            <option value="kitchen_staff">Խոհանոց (Kitchen)</option>
                            <option value="staff">Աշխատակից (Staff)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Կցված Մասնաճյուղ</label>
                        <select name="location_id" x-model="userEdit.location_id" class="form-select">
                            <option value="">Բոլոր մասնաճյուղերը</option>
                            @foreach($vendor->locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">Նոր Գաղտնաբառ (թողեք դատարկ, եթե չեք ցանկանում փոխել)</label>
                        <input type="password" name="password" minlength="6" class="form-input" placeholder="••••••••">
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" @click="showUserEditModal = false">Չեղարկել</button>
                    <button type="submit" class="btn btn-primary">Պահպանել Փոփոխությունները</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
