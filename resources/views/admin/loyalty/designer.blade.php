@extends('layouts.app')

@section('content')
<div class="content-wrapper">

    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-palette" style="color: #6366f1;"></i>
                <span>{{ __('Visual Wallet Card Designer') }}</span>
            </h1>
            <p style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('Customize real Apple Wallet (.pkpass) and Google Wallet digital passes with live visual preview.') }}
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem; align-items: center;">
            <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: #818cf8; font-weight: 700; padding: 0.4rem 0.8rem; border-radius: 20px;">
                {{ __('Design Version') }} {{ $design->version }}
            </span>
            <a href="{{ route('admin.loyalty.index') }}" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left mr-1"></i> {{ __('Overview') }}
            </a>
        </div>
    </div>

    @if(session('success'))
        <div style="padding: 1rem; border-radius: 12px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; font-size: 0.875rem; margin-bottom: 1.5rem;">
            <i class="fa-solid fa-circle-check mr-1"></i> {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.loyalty.designer.update') }}" enctype="multipart/form-data">
        @csrf

        <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 2rem; align-items: start;">
            
            <!-- Left: Card Customization Controls -->
            <div class="card" style="padding: 1.75rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px;">
                <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-sliders text-primary"></i>
                    {{ __('Pass Configuration') }}
                </h3>

                <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                    
                    <div>
                        <label class="form-label">{{ __('Card Header Title') }}</label>
                        <input type="text" id="inputCardTitle" name="card_title" value="{{ old('card_title', $design->card_title) }}" class="form-control" required oninput="updateLivePreview()">
                        <small style="color: var(--text-muted); font-size: 0.75rem;">{{ __('Displayed at the top of the Apple and Google Wallet passes.') }}</small>
                    </div>

                    <!-- Color Pickers -->
                    <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem;">
                        <span style="display: block; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 1rem;">
                            {{ __('Color Theme Palette') }}
                        </span>

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                            <div>
                                <label class="form-label" style="font-size: 0.75rem;">{{ __('Background') }}</label>
                                <div style="display: flex; gap: 0.4rem; align-items: center;">
                                    <input type="color" id="pickerBg" value="{{ $design->background_color }}" oninput="syncColor('pickerBg', 'inputBg'); updateLivePreview();" style="width: 36px; height: 36px; padding: 0; border: none; background: none; cursor: pointer; border-radius: 6px;">
                                    <input type="text" id="inputBg" name="background_color" value="{{ old('background_color', $design->background_color) }}" class="form-control form-control-sm" required oninput="syncColor('inputBg', 'pickerBg'); updateLivePreview();">
                                </div>
                            </div>

                            <div>
                                <label class="form-label" style="font-size: 0.75rem;">{{ __('Text (Foreground)') }}</label>
                                <div style="display: flex; gap: 0.4rem; align-items: center;">
                                    <input type="color" id="pickerFg" value="{{ $design->foreground_color }}" oninput="syncColor('pickerFg', 'inputFg'); updateLivePreview();" style="width: 36px; height: 36px; padding: 0; border: none; background: none; cursor: pointer; border-radius: 6px;">
                                    <input type="text" id="inputFg" name="foreground_color" value="{{ old('foreground_color', $design->foreground_color) }}" class="form-control form-control-sm" required oninput="syncColor('inputFg', 'pickerFg'); updateLivePreview();">
                                </div>
                            </div>

                            <div>
                                <label class="form-label" style="font-size: 0.75rem;">{{ __('Field Labels') }}</label>
                                <div style="display: flex; gap: 0.4rem; align-items: center;">
                                    <input type="color" id="pickerLbl" value="{{ $design->label_color }}" oninput="syncColor('pickerLbl', 'inputLbl'); updateLivePreview();" style="width: 36px; height: 36px; padding: 0; border: none; background: none; cursor: pointer; border-radius: 6px;">
                                    <input type="text" id="inputLbl" name="label_color" value="{{ old('label_color', $design->label_color) }}" class="form-control form-control-sm" required oninput="syncColor('inputLbl', 'pickerLbl'); updateLivePreview();">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pass Fields -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label class="form-label">{{ __('Top Header Field') }}</label>
                            <select name="header_field" id="selectHeaderField" class="form-control" onchange="updateLivePreview()">
                                <option value="tier" {{ $design->header_field === 'tier' ? 'selected' : '' }}>{{ __('Membership Tier (e.g. Gold)') }}</option>
                                <option value="points" {{ $design->header_field === 'points' ? 'selected' : '' }}>{{ __('Points Balance') }}</option>
                                <option value="cashback" {{ $design->header_field === 'cashback' ? 'selected' : '' }}>{{ __('Cashback Balance') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">{{ __('Primary Highlight Field') }}</label>
                            <select name="primary_field" id="selectPrimaryField" class="form-control" onchange="updateLivePreview()">
                                <option value="points" {{ $design->primary_field === 'points' ? 'selected' : '' }}>{{ __('Points Balance (Large)') }}</option>
                                <option value="cashback" {{ $design->primary_field === 'cashback' ? 'selected' : '' }}>{{ __('Cashback Amount') }}</option>
                                <option value="customer_name" {{ $design->primary_field === 'customer_name' ? 'selected' : '' }}>{{ __('Customer Full Name') }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Scanner Barcode Standard') }}</label>
                        <select name="barcode_type" class="form-control">
                            <option value="PKBarcodeFormatQR" {{ $design->barcode_type === 'PKBarcodeFormatQR' ? 'selected' : '' }}>QR Code (Recommended for all phone scanners)</option>
                            <option value="PKBarcodeFormatPDF417" {{ $design->barcode_type === 'PKBarcodeFormatPDF417' ? 'selected' : '' }}>PDF417 (Dense 2D Barcode)</option>
                            <option value="PKBarcodeFormatCode128" {{ $design->barcode_type === 'PKBarcodeFormatCode128' ? 'selected' : '' }}>Code 128 (Linear Optical Laser Scanner)</option>
                        </select>
                    </div>

                    <!-- Asset Uploads -->
                    <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem;">
                        <span style="display: block; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 1rem;">
                            {{ __('Pass Imagery & Branding Assets') }}
                        </span>

                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <div>
                                <label class="form-label" style="font-size: 0.8rem;">{{ __('Pass Logo (Recommended: 320x100 transparent PNG)') }}</label>
                                <input type="file" name="logo" class="form-control form-control-sm" accept="image/png,image/jpeg">
                                @if($design->logo_url)
                                    <div style="margin-top: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <img src="{{ $design->logo_url }}" alt="Logo" style="height: 28px; object-fit: contain; background: #fff; padding: 2px; border-radius: 4px;">
                                        <small style="color: var(--text-muted);">{{ __('Current Logo Active') }}</small>
                                    </div>
                                @endif
                            </div>

                            <div>
                                <label class="form-label" style="font-size: 0.8rem;">{{ __('Strip Background Banner (Recommended: 1125x432 PNG)') }}</label>
                                <input type="file" name="strip_image" class="form-control form-control-sm" accept="image/png,image/jpeg">
                                @if($design->strip_image_url)
                                    <div style="margin-top: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <img src="{{ $design->strip_image_url }}" alt="Strip" style="height: 36px; object-fit: cover; border-radius: 4px; border: 1px solid var(--border-color);">
                                        <small style="color: var(--text-muted);">{{ __('Current Strip Banner Active') }}</small>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="padding: 0.85rem; font-size: 1rem; font-weight: 700;">
                        <i class="fa-solid fa-cloud-arrow-up mr-2"></i> {{ __('Save & Publish Pass Design') }}
                    </button>

                </div>
            </div>

            <!-- Right: Interactive Live Pass Mockup -->
            <div style="position: sticky; top: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                        <i class="fa-solid fa-mobile-screen mr-1"></i> {{ __('Live Wallet Preview') }}
                    </span>
                    <div style="display: flex; gap: 0.4rem;">
                        <button type="button" id="tabAppleBtn" onclick="switchPreviewTab('apple')" style="padding: 0.3rem 0.75rem; border-radius: 8px; font-size: 0.75rem; font-weight: 700; cursor: pointer; background: #6366f1; color: #fff; border: none;">
                            <i class="fa-brands fa-apple mr-1"></i> Apple Wallet
                        </button>
                        <button type="button" id="tabGoogleBtn" onclick="switchPreviewTab('google')" style="padding: 0.3rem 0.75rem; border-radius: 8px; font-size: 0.75rem; font-weight: 700; cursor: pointer; background: var(--bg-card); color: var(--text-muted); border: 1px solid var(--border-color);">
                            <i class="fa-brands fa-google mr-1"></i> Google Wallet
                        </button>
                    </div>
                </div>

                <!-- Apple Pass Mockup Container -->
                <div id="applePreviewContainer" style="background: {{ $design->background_color }}; color: {{ $design->foreground_color }}; border-radius: 20px; box-shadow: 0 15px 40px rgba(0,0,0,0.4); padding: 1.5rem; width: 100%; max-width: 360px; margin: 0 auto; transition: background 0.2s ease, color 0.2s ease;">
                    
                    <!-- Header -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-utensils" style="font-size: 1.1rem;"></i>
                            <span id="previewCardTitle" style="font-weight: 800; font-size: 1rem; letter-spacing: -0.01em;">{{ $design->card_title }}</span>
                        </div>
                        <div style="text-align: right;">
                            <span id="previewHeaderLabel" style="font-size: 0.65rem; text-transform: uppercase; display: block; color: {{ $design->label_color }}; font-weight: 600;">MEMBERSHIP TIER</span>
                            <span id="previewHeaderVal" style="font-size: 0.85rem; font-weight: 800; color: {{ $design->foreground_color }};">GOLD VIP</span>
                        </div>
                    </div>

                    <!-- Strip Banner / Secondary Card Section -->
                    <div id="previewStrip" style="height: 100px; border-radius: 12px; margin-bottom: 1.25rem; background: {{ $design->strip_image_url ? 'url('.$design->strip_image_url.') center/cover' : 'linear-gradient(135deg, rgba(255,255,255,0.15) 0%, rgba(0,0,0,0.2) 100%)' }}; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.1);">
                        <span style="font-size: 0.8rem; font-weight: 600; opacity: 0.7;">{{ $vendor->name }}</span>
                    </div>

                    <!-- Primary Highlight Field -->
                    <div style="margin-bottom: 1.5rem;">
                        <span id="previewPrimaryLabel" style="font-size: 0.7rem; text-transform: uppercase; display: block; color: {{ $design->label_color }}; font-weight: 700; margin-bottom: 0.2rem;">REWARD POINTS</span>
                        <span id="previewPrimaryVal" style="font-size: 2.2rem; font-weight: 900; line-height: 1; letter-spacing: -0.02em; color: {{ $design->foreground_color }};">1,450 <span style="font-size: 1rem; font-weight: 600;">PTS</span></span>
                    </div>

                    <!-- Customer Auxiliary Row -->
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1.5rem; font-size: 0.8rem; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 0.75rem;">
                        <div>
                            <span style="font-size: 0.65rem; text-transform: uppercase; display: block; color: {{ $design->label_color }}; font-weight: 600;">MEMBER</span>
                            <span style="font-weight: 700; color: {{ $design->foreground_color }};">Jane Doe</span>
                        </div>
                        <div style="text-align: right;">
                            <span style="font-size: 0.65rem; text-transform: uppercase; display: block; color: {{ $design->label_color }}; font-weight: 600;">CASHBACK</span>
                            <span style="font-weight: 700; color: {{ $design->foreground_color }};">$18.50</span>
                        </div>
                    </div>

                    <!-- Barcode Box -->
                    <div style="background: #ffffff; border-radius: 12px; padding: 1.25rem 1rem; text-align: center; color: #000000; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                        <i class="fa-solid fa-qrcode" style="font-size: 5rem; color: #111827;"></i>
                        <span style="display: block; font-family: monospace; font-size: 0.8rem; font-weight: 700; margin-top: 0.5rem; letter-spacing: 0.1em;">
                            LOY-8392-4910
                        </span>
                        <span style="display: block; font-size: 0.65rem; color: #6b7280; margin-top: 0.2rem;">
                            Scan at checkout for points & rewards
                        </span>
                    </div>

                </div>

                <!-- Google Wallet Mockup Container (Hidden by default) -->
                <div id="googlePreviewContainer" style="display: none; background: #ffffff; color: #1f2937; border-radius: 24px; box-shadow: 0 15px 40px rgba(0,0,0,0.3); padding: 1.5rem; width: 100%; max-width: 360px; margin: 0 auto; border: 1px solid #e5e7eb;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span style="width: 28px; height: 28px; border-radius: 50%; background: #4285f4; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">
                                <i class="fa-solid fa-store"></i>
                            </span>
                            <span style="font-weight: 700; font-size: 0.95rem;">{{ $vendor->name }}</span>
                        </div>
                        <i class="fa-brands fa-google-pay" style="font-size: 1.75rem; color: #5f6368;"></i>
                    </div>

                    <div style="background: {{ $design->background_color }}; color: {{ $design->foreground_color }}; border-radius: 16px; padding: 1.25rem; margin-bottom: 1.25rem;">
                        <span style="font-size: 0.7rem; text-transform: uppercase; color: {{ $design->label_color }}; font-weight: 700;">LOYALTY CARD</span>
                        <h4 id="googleCardTitle" style="font-size: 1.25rem; font-weight: 800; margin: 0.25rem 0 0.75rem 0; color: {{ $design->foreground_color }};">{{ $design->card_title }}</h4>
                        <div style="font-size: 1.75rem; font-weight: 900; color: {{ $design->foreground_color }};">1,450 PTS</div>
                    </div>

                    <div style="text-align: center; padding: 1rem; background: #f9fafb; border-radius: 14px; border: 1px dashed #d1d5db;">
                        <i class="fa-solid fa-qrcode" style="font-size: 4.5rem; color: #111827;"></i>
                        <span style="display: block; font-family: monospace; font-size: 0.75rem; color: #4b5563; margin-top: 0.5rem;">LOY-8392-4910</span>
                    </div>
                </div>

            </div>

        </div>
    </form>

</div>

<script>
function syncColor(fromId, toId) {
    document.getElementById(toId).value = document.getElementById(fromId).value;
}

function updateLivePreview() {
    const title = document.getElementById('inputCardTitle').value;
    const bg = document.getElementById('inputBg').value;
    const fg = document.getElementById('inputFg').value;
    const lbl = document.getElementById('inputLbl').value;

    const appleContainer = document.getElementById('applePreviewContainer');
    appleContainer.style.background = bg;
    appleContainer.style.color = fg;

    document.getElementById('previewCardTitle').innerText = title;
    document.getElementById('previewCardTitle').style.color = fg;
    document.getElementById('previewPrimaryVal').style.color = fg;
    document.getElementById('previewHeaderVal').style.color = fg;

    document.getElementById('previewHeaderLabel').style.color = lbl;
    document.getElementById('previewPrimaryLabel').style.color = lbl;

    const googleCard = document.querySelector('#googlePreviewContainer > div:nth-child(2)');
    if (googleCard) {
        googleCard.style.background = bg;
        googleCard.style.color = fg;
        document.getElementById('googleCardTitle').innerText = title;
        document.getElementById('googleCardTitle').style.color = fg;
    }
}

function switchPreviewTab(tab) {
    const appleBtn = document.getElementById('tabAppleBtn');
    const googleBtn = document.getElementById('tabGoogleBtn');
    const appleCont = document.getElementById('applePreviewContainer');
    const googleCont = document.getElementById('googlePreviewContainer');

    if (tab === 'apple') {
        appleCont.style.display = 'block';
        googleCont.style.display = 'none';
        appleBtn.style.background = '#6366f1';
        appleBtn.style.color = '#fff';
        googleBtn.style.background = 'var(--bg-card)';
        googleBtn.style.color = 'var(--text-muted)';
    } else {
        appleCont.style.display = 'none';
        googleCont.style.display = 'block';
        googleBtn.style.background = '#6366f1';
        googleBtn.style.color = '#fff';
        appleBtn.style.background = 'var(--bg-card)';
        appleBtn.style.color = 'var(--text-muted)';
    }
}
</script>
@endsection

