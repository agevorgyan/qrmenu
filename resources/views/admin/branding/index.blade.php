@extends('layouts.app')

@section('title', 'Theme & Storefront Branding - ' . $vendor->name)

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.85rem; font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.75rem;">
            <span style="background: rgba(245, 158, 11, 0.15); color: var(--primary); width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
            </span>
            Split-Screen Live Customizer
        </h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0 0;">
            Customize visual templates, brand colors, and identity with an instant real-time mobile preview.
        </p>
    </div>

    <div style="display: flex; gap: 0.75rem;">
        <a href="{{ route('client.menu', ['vendor_slug' => $vendor->slug]) }}" target="_blank" class="btn btn-secondary" style="display: flex; align-items: center; gap: 0.5rem; text-decoration: none;">
            <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Storefront
        </a>
    </div>
</div>

<div class="customizer-split-grid">
    <!-- LEFT COLUMN: Customizer Controls -->
    <div class="customizer-controls">
        <form action="{{ route('admin.branding.update') }}" method="POST" id="brandingForm" enctype="multipart/form-data">
            @csrf

            <!-- 1. Menu Template -->
            <div class="card" style="margin-bottom: 1.5rem;">
                <div class="section-header">
                    <span class="step-num">1</span>
                    <div>
                        <h3 class="section-title">Digital Menu Template</h3>
                        <p class="section-desc">Select the storefront design architecture for your guests</p>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                    @foreach($templates as $tmpl)
                        <label class="template-choice-card {{ $vendor->menu_template_id == $tmpl->id ? 'selected' : '' }}">
                            <input type="radio" name="menu_template_id" value="{{ $tmpl->id }}" {{ $vendor->menu_template_id == $tmpl->id ? 'checked' : '' }} onchange="updateTemplateSelection(this)">
                            <img src="{{ $tmpl->preview_image }}" alt="{{ $tmpl->name }}" class="template-thumb">
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div style="font-weight: 700; color: var(--text-main); font-size: 0.98rem;">{{ $tmpl->name }}</div>
                                    @if($vendor->menu_template_id == $tmpl->id)
                                        <span class="active-badge"><i class="fa-solid fa-check"></i> Active</span>
                                    @endif
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">{{ $tmpl->description }}</div>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- 2. Brand Colors & Palette Presets -->
            <div class="card" style="margin-bottom: 1.5rem;">
                <div class="section-header">
                    <span class="step-num">2</span>
                    <div>
                        <h3 class="section-title">Brand Colors & Palette</h3>
                        <p class="section-desc">Changes update live in the mobile preview iframe</p>
                    </div>
                </div>

                <!-- Palette Quick-Presets -->
                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.04em;">
                        Quick Palette Presets
                    </label>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                        <button type="button" class="preset-pill" onclick="applyPreset('#e11d48', '#f59e0b', '#4f46e5')">
                            <span class="preset-dots" style="background: linear-gradient(135deg, #e11d48, #f59e0b);"></span>
                            Ruby Bistro
                        </button>
                        <button type="button" class="preset-pill" onclick="applyPreset('#f59e0b', '#ef4444', '#10b981')">
                            <span class="preset-dots" style="background: linear-gradient(135deg, #f59e0b, #ef4444);"></span>
                            Amber Gold
                        </button>
                        <button type="button" class="preset-pill" onclick="applyPreset('#10b981', '#06b6d4', '#6366f1')">
                            <span class="preset-dots" style="background: linear-gradient(135deg, #10b981, #06b6d4);"></span>
                            Emerald Fresh
                        </button>
                        <button type="button" class="preset-pill" onclick="applyPreset('#06b6d4', '#3b82f6', '#8b5cf6')">
                            <span class="preset-dots" style="background: linear-gradient(135deg, #06b6d4, #3b82f6);"></span>
                            Cyan Modern
                        </button>
                        <button type="button" class="preset-pill" onclick="applyPreset('#d4af37', '#e2e8f0', '#71717a')">
                            <span class="preset-dots" style="background: linear-gradient(135deg, #d4af37, #71717a);"></span>
                            Luxury Gold
                        </button>
                    </div>
                </div>

                <!-- Color Pickers Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
                    <div>
                        <label class="color-label">Primary Color</label>
                        <div class="color-input-wrapper">
                            <input type="color" name="primary_color" id="primaryColorInput" value="{{ $vendor->primary_color ?? '#e11d48' }}" class="color-picker-wheel">
                            <input type="text" id="primaryColorHex" value="{{ $vendor->primary_color ?? '#e11d48' }}" class="color-hex-text">
                        </div>
                    </div>
                    <div>
                        <label class="color-label">Accent Color</label>
                        <div class="color-input-wrapper">
                            <input type="color" name="accent_color" id="accentColorInput" value="{{ $vendor->accent_color ?? '#f59e0b' }}" class="color-picker-wheel">
                            <input type="text" id="accentColorHex" value="{{ $vendor->accent_color ?? '#f59e0b' }}" class="color-hex-text">
                        </div>
                    </div>
                    <div>
                        <label class="color-label">Secondary Color</label>
                        <div class="color-input-wrapper">
                            <input type="color" name="secondary_color" id="secondaryColorInput" value="{{ $vendor->secondary_color ?? '#4f46e5' }}" class="color-picker-wheel">
                            <input type="text" id="secondaryColorHex" value="{{ $vendor->secondary_color ?? '#4f46e5' }}" class="color-hex-text">
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="color-label">Background Color</label>
                        <div class="color-input-wrapper">
                            <input type="color" name="bg_color" id="bgColorInput" value="{{ $vendor->bg_color ?? ($vendor->theme_mode == 'light' ? '#f8fafc' : '#09090b') }}" class="color-picker-wheel">
                            <input type="text" id="bgColorHex" value="{{ $vendor->bg_color ?? ($vendor->theme_mode == 'light' ? '#f8fafc' : '#09090b') }}" class="color-hex-text">
                        </div>
                    </div>
                    <div>
                        <label class="color-label">Text Color</label>
                        <div class="color-input-wrapper">
                            <input type="color" name="text_color" id="textColorInput" value="{{ $vendor->text_color ?? ($vendor->theme_mode == 'light' ? '#0f172a' : '#f4f4f5') }}" class="color-picker-wheel">
                            <input type="text" id="textColorHex" value="{{ $vendor->text_color ?? ($vendor->theme_mode == 'light' ? '#0f172a' : '#f4f4f5') }}" class="color-hex-text">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Theme Mode (Dark / Light) -->
            <div class="card" style="margin-bottom: 1.5rem;">
                <div class="section-header">
                    <span class="step-num">3</span>
                    <div>
                        <h3 class="section-title">Storefront Theme Mode</h3>
                        <p class="section-desc">Default ambience for visiting guests</p>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <label class="mode-choice-card {{ $vendor->theme_mode == 'dark' ? 'selected' : '' }}">
                        <input type="radio" name="theme_mode" value="dark" {{ $vendor->theme_mode == 'dark' ? 'checked' : '' }} onchange="onThemeModeChange('dark')">
                        <i class="fa-solid fa-moon" style="font-size: 1.3rem; color: #f59e0b;"></i>
                        <div>
                            <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">Sleek Dark Mode</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">High-contrast dark luxury</div>
                        </div>
                    </label>
                    <label class="mode-choice-card {{ $vendor->theme_mode == 'light' ? 'selected' : '' }}">
                        <input type="radio" name="theme_mode" value="light" {{ $vendor->theme_mode == 'light' ? 'checked' : '' }} onchange="onThemeModeChange('light')">
                        <i class="fa-solid fa-sun" style="font-size: 1.3rem; color: #f59e0b;"></i>
                        <div>
                            <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">Clean Light Mode</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">Bright minimal daytime look</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- 4. Desktop Max Width Setting -->
            <div class="card" style="margin-bottom: 1.5rem;">
                <div class="section-header">
                    <span class="step-num">4</span>
                    <div>
                        <h3 class="section-title">Desktop Frame Width</h3>
                        <p class="section-desc">Set the maximum menu frame width when viewed on desktop and laptop screens</p>
                    </div>
                </div>

                @php
                    $currentMaxWidth = $vendor->getDesktopMaxWidth();
                @endphp

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 0.65rem;">
                    <label class="mode-choice-card {{ $currentMaxWidth === '480px' ? 'selected' : '' }}" style="padding: 0.85rem 0.5rem; flex-direction: column; text-align: center; justify-content: center; gap: 0.4rem; cursor: pointer;">
                        <input type="radio" name="desktop_max_width" value="480px" {{ $currentMaxWidth === '480px' ? 'checked' : '' }} onchange="onDesktopWidthChange('480px')">
                        <i class="fa-solid fa-mobile-screen" style="font-size: 1.25rem; color: var(--primary);"></i>
                        <div>
                            <div style="font-weight: 700; color: var(--text-main); font-size: 0.88rem;">480px</div>
                            <div style="font-size: 0.7rem; color: var(--text-muted);">Compact</div>
                        </div>
                    </label>

                    <label class="mode-choice-card {{ $currentMaxWidth === '600px' ? 'selected' : '' }}" style="padding: 0.85rem 0.5rem; flex-direction: column; text-align: center; justify-content: center; gap: 0.4rem; cursor: pointer;">
                        <input type="radio" name="desktop_max_width" value="600px" {{ $currentMaxWidth === '600px' ? 'checked' : '' }} onchange="onDesktopWidthChange('600px')">
                        <i class="fa-solid fa-tablet-screen-button" style="font-size: 1.25rem; color: #10b981;"></i>
                        <div>
                            <div style="font-weight: 700; color: var(--text-main); font-size: 0.88rem;">600px <span style="font-size: 0.6rem; background: #10b981; color: #fff; padding: 0.1rem 0.3rem; border-radius: 4px;">Top</span></div>
                            <div style="font-size: 0.7rem; color: var(--text-muted);">Standard</div>
                        </div>
                    </label>

                    <label class="mode-choice-card {{ $currentMaxWidth === '680px' ? 'selected' : '' }}" style="padding: 0.85rem 0.5rem; flex-direction: column; text-align: center; justify-content: center; gap: 0.4rem; cursor: pointer;">
                        <input type="radio" name="desktop_max_width" value="680px" {{ $currentMaxWidth === '680px' ? 'checked' : '' }} onchange="onDesktopWidthChange('680px')">
                        <i class="fa-solid fa-tablet" style="font-size: 1.25rem; color: #3b82f6;"></i>
                        <div>
                            <div style="font-weight: 700; color: var(--text-main); font-size: 0.88rem;">680px</div>
                            <div style="font-size: 0.7rem; color: var(--text-muted);">Wide Mobile</div>
                        </div>
                    </label>

                    <label class="mode-choice-card {{ $currentMaxWidth === '768px' ? 'selected' : '' }}" style="padding: 0.85rem 0.5rem; flex-direction: column; text-align: center; justify-content: center; gap: 0.4rem; cursor: pointer;">
                        <input type="radio" name="desktop_max_width" value="768px" {{ $currentMaxWidth === '768px' ? 'checked' : '' }} onchange="onDesktopWidthChange('768px')">
                        <i class="fa-solid fa-tablets" style="font-size: 1.25rem; color: #8b5cf6;"></i>
                        <div>
                            <div style="font-weight: 700; color: var(--text-main); font-size: 0.88rem;">768px</div>
                            <div style="font-size: 0.7rem; color: var(--text-muted);">Tablet</div>
                        </div>
                    </label>

                    <label class="mode-choice-card {{ $currentMaxWidth === '100%' ? 'selected' : '' }}" style="padding: 0.85rem 0.5rem; flex-direction: column; text-align: center; justify-content: center; gap: 0.4rem; cursor: pointer;">
                        <input type="radio" name="desktop_max_width" value="100%" {{ $currentMaxWidth === '100%' ? 'checked' : '' }} onchange="onDesktopWidthChange('100%')">
                        <i class="fa-solid fa-expand" style="font-size: 1.25rem; color: #64748b;"></i>
                        <div>
                            <div style="font-weight: 700; color: var(--text-main); font-size: 0.88rem;">100%</div>
                            <div style="font-size: 0.7rem; color: var(--text-muted);">Full Width</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- 5. Brand Logo & Cover Header -->
            <div class="card" style="margin-bottom: 1.5rem;">
                <div class="section-header">
                    <span class="step-num">5</span>
                    <div>
                        <h3 class="section-title">Brand Media & Identity</h3>
                        <p class="section-desc">Upload logo and hero cover photo</p>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <!-- Logo Upload / URL -->
                    <div>
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                            <i class="fa-solid fa-image" style="color: var(--primary);"></i> Restaurant Logo
                        </label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; align-items: center;">
                            <div>
                                <input type="file" name="logo_file" accept="image/*" class="file-input">
                                <small style="color: var(--text-muted); font-size: 0.72rem; display: block; margin-top: 0.25rem;">📁 Upload file (PNG/JPG)</small>
                            </div>
                            <div>
                                <input type="text" name="logo" id="logoUrlInput" value="{{ $vendor->logo }}" placeholder="/storage/... or https://..." class="form-input-control" oninput="debounceReloadPreview()">
                                <small style="color: var(--text-muted); font-size: 0.72rem; display: block; margin-top: 0.25rem;">🔗 Or image URL / path</small>
                            </div>
                        </div>
                    </div>

                    <!-- Cover Image Upload / URL -->
                    <div>
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                            <i class="fa-solid fa-panorama" style="color: var(--primary);"></i> Storefront Cover Header
                        </label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; align-items: center;">
                            <div>
                                <input type="file" name="cover_file" accept="image/*" class="file-input">
                                <small style="color: var(--text-muted); font-size: 0.72rem; display: block; margin-top: 0.25rem;">📁 Upload banner photo</small>
                            </div>
                            <div>
                                <input type="text" name="cover_image" id="coverUrlInput" value="{{ $vendor->cover_image }}" placeholder="/storage/... or https://..." class="form-input-control" oninput="debounceReloadPreview()">
                                <small style="color: var(--text-muted); font-size: 0.72rem; display: block; margin-top: 0.25rem;">🔗 Or cover image URL / path</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. Custom CSS (Advanced) -->
            <div class="card" style="margin-bottom: 1.75rem;">
                <div class="section-header">
                    <span class="step-num">6</span>
                    <div>
                        <h3 class="section-title">Custom CSS Overrides</h3>
                        <p class="section-desc">Advanced fine-tuning with custom style rules</p>
                    </div>
                </div>

                <div>
                    <textarea name="custom_css" id="customCssInput" rows="3" class="code-editor-box" placeholder="/* Custom CSS here */&#10;.dish-card { border-radius: 20px; }" oninput="debounceReloadPreview()">{{ $vendor->custom_css }}</textarea>
                </div>
            </div>

            <!-- Sticky Save Footer CTA -->
            <div style="position: sticky; bottom: 1.25rem; z-index: 30;">
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; font-size: 1.05rem; padding: 0.95rem; font-weight: 800; border-radius: 14px; box-shadow: 0 10px 25px rgba(245, 158, 11, 0.4);">
                    <i class="fa-solid fa-floppy-disk"></i> Save Theme & Branding
                </button>
            </div>
        </form>
    </div>

    <!-- RIGHT COLUMN: Sticky Live Smartphone Mockup -->
    <div class="customizer-preview-col">
        <div class="sticky-preview-wrapper">
            <!-- Mockup Toolbar -->
            <div class="preview-toolbar">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <button type="button" class="toolbar-btn active" id="btnDeviceMobile" onclick="setDeviceWidth('375px', this)" title="Mobile View (375px)">
                        <i class="fa-solid fa-mobile-screen"></i> 375px
                    </button>
                    <button type="button" class="toolbar-btn" id="btnDeviceTablet" onclick="setDeviceWidth('440px', this)" title="Compact / Large Mobile (440px)">
                        <i class="fa-solid fa-tablet-screen-button"></i> 440px
                    </button>
                    <button type="button" class="toolbar-btn" id="btnDeviceDesktop" onclick="setDeviceWidth('600px', this)" title="Desktop View (600px)">
                        <i class="fa-solid fa-desktop"></i> 600px
                    </button>
                </div>

                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <button type="button" class="toolbar-btn" onclick="forceReloadPreview()" title="Refresh Preview">
                        <i class="fa-solid fa-rotate-right" id="refreshSpinIcon"></i>
                    </button>
                    <a href="{{ route('client.menu', ['vendor_slug' => $vendor->slug]) }}" target="_blank" class="toolbar-btn" title="Open Fullscreen in New Tab">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </div>
            </div>

            <!-- Realistic Smartphone Frame -->
            <div class="phone-mockup-frame" id="phoneMockupFrame">
                <!-- Phone Top Bar & Dynamic Island -->
                <div class="phone-status-bar">
                    <span style="font-weight: 700; font-size: 0.72rem; letter-spacing: -0.01em;">19:42</span>
                    <div class="dynamic-island">
                        <span class="island-camera"></span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.35rem; font-size: 0.72rem;">
                        <i class="fa-solid fa-signal" style="font-size: 0.65rem;"></i>
                        <i class="fa-solid fa-wifi" style="font-size: 0.65rem;"></i>
                        <i class="fa-solid fa-battery-full" style="font-size: 0.75rem;"></i>
                    </div>
                </div>

                <!-- Iframe Container -->
                <div class="phone-screen-container">
                    <div class="preview-loading-spinner" id="previewLoader">
                        <i class="fa-solid fa-circle-notch fa-spin"></i>
                        <span>Loading preview...</span>
                    </div>
                    <iframe id="previewIframe" 
                            src="{{ route('client.menu', ['vendor_slug' => $vendor->slug]) }}" 
                            onload="onIframeLoaded()"
                            allow="geolocation; microphone; camera">
                    </iframe>
                </div>

                <!-- Phone Bottom Home Indicator -->
                <div class="phone-bottom-bar">
                    <div class="home-indicator"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .customizer-split-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.15fr) minmax(0, 0.95fr);
        gap: 2rem;
        align-items: start;
        width: 100%;
        box-sizing: border-box;
    }

    @media (max-width: 1080px) {
        .customizer-split-grid {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
    }

    .section-header {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        margin-bottom: 1.25rem;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 0.85rem;
    }

    .step-num {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--primary);
        color: #ffffff;
        font-weight: 800;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .section-title {
        font-family: 'Outfit', sans-serif;
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--text-main);
        margin: 0;
    }

    .section-desc {
        font-size: 0.8rem;
        color: var(--text-muted);
        margin: 0.15rem 0 0 0;
    }

    .template-choice-card {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.85rem 1rem;
        background: var(--input-bg);
        border: 2px solid var(--border-color);
        border-radius: 14px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .template-choice-card:hover {
        border-color: rgba(245, 158, 11, 0.4);
    }

    .template-choice-card.selected {
        border-color: var(--primary);
        background: rgba(245, 158, 11, 0.05);
    }

    .template-thumb {
        width: 58px;
        height: 58px;
        object-fit: cover;
        border-radius: 10px;
        border: 1px solid var(--border-color);
    }

    .active-badge {
        font-size: 0.72rem;
        font-weight: 700;
        background: var(--primary);
        color: #fff;
        padding: 0.2rem 0.6rem;
        border-radius: 9999px;
    }

    .preset-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.4rem 0.8rem;
        background: var(--input-bg);
        border: 1px solid var(--border-color);
        border-radius: 9999px;
        color: var(--text-main);
        font-size: 0.78rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .preset-pill:hover {
        border-color: var(--primary);
        transform: translateY(-1px);
    }

    .preset-dots {
        width: 14px;
        height: 14px;
        border-radius: 50%;
        display: inline-block;
    }

    .color-label {
        display: block;
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--text-muted);
        margin-bottom: 0.4rem;
    }

    .color-input-wrapper {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        background: var(--input-bg);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        padding: 0.35rem 0.5rem;
    }

    .color-picker-wheel {
        width: 32px;
        height: 32px;
        border: none;
        border-radius: 6px;
        background: none;
        cursor: pointer;
        padding: 0;
    }

    .color-hex-text {
        flex: 1;
        background: none;
        border: none;
        color: var(--text-main);
        font-family: monospace;
        font-size: 0.85rem;
        font-weight: 700;
        outline: none;
        width: 100%;
    }

    .mode-choice-card {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        padding: 1rem;
        background: var(--input-bg);
        border: 2px solid var(--border-color);
        border-radius: 14px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .mode-choice-card.selected {
        border-color: var(--primary);
        background: rgba(245, 158, 11, 0.05);
    }

    .form-input-control {
        width: 100%;
        padding: 0.65rem 0.85rem;
        background: var(--input-bg);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        color: var(--text-main);
        font-size: 0.85rem;
        outline: none;
    }

    .file-input {
        width: 100%;
        padding: 0.5rem;
        background: var(--input-bg);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        color: var(--text-main);
        font-size: 0.78rem;
        outline: none;
    }

    .code-editor-box {
        width: 100%;
        padding: 0.85rem;
        background: #090d16;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        color: #38bdf8;
        font-family: 'JetBrains Mono', monospace, Consolas;
        font-size: 0.82rem;
        line-height: 1.5;
        outline: none;
        resize: vertical;
    }

    /* Sticky Live Preview Column */
    .customizer-preview-col {
        position: relative;
    }

    .sticky-preview-wrapper {
        position: sticky;
        top: 1.5rem;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .preview-toolbar {
        width: 100%;
        max-width: min(375px, 100%);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 0.4rem 0.6rem;
        margin-bottom: 0.85rem;
        box-sizing: border-box;
    }

    .toolbar-btn {
        background: var(--input-bg);
        border: 1px solid var(--border-color);
        color: var(--text-muted);
        padding: 0.35rem 0.65rem;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        transition: all 0.15s ease;
        text-decoration: none;
    }

    .toolbar-btn:hover, .toolbar-btn.active {
        background: var(--primary);
        color: #ffffff;
        border-color: var(--primary);
    }

    /* Smartphone Mockup Frame */
    .phone-mockup-frame {
        width: 375px;
        max-width: 100%;
        height: 720px;
        max-height: 85vh;
        background: #0b0f19;
        border: 10px solid #1e293b;
        border-radius: 46px;
        box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.08);
        position: relative;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        margin: 0 auto;
        box-sizing: border-box;
        transition: width 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .phone-status-bar {
        height: 38px;
        background: #000000;
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 1.25rem;
        position: relative;
        z-index: 10;
        flex-shrink: 0;
    }

    .dynamic-island {
        width: 86px;
        height: 22px;
        background: #000000;
        border-radius: 9999px;
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        top: 6px;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding-right: 8px;
    }

    .island-camera {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background: #111827;
        border: 1px solid #1f2937;
    }

    .phone-screen-container {
        flex: 1;
        position: relative;
        overflow: hidden;
        background: #ffffff;
    }

    .phone-screen-container iframe {
        width: 100%;
        height: 100%;
        border: none;
        display: block;
    }

    .preview-loading-spinner {
        position: absolute;
        inset: 0;
        background: rgba(11, 15, 25, 0.7);
        backdrop-filter: blur(4px);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        color: #ffffff;
        font-size: 0.85rem;
        font-weight: 600;
        z-index: 5;
        transition: opacity 0.2s ease;
        pointer-events: none;
        opacity: 0;
    }

    .preview-loading-spinner.visible {
        opacity: 1;
        pointer-events: auto;
    }

    .phone-bottom-bar {
        height: 20px;
        background: #000000;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .home-indicator {
        width: 120px;
        height: 4px;
        background: rgba(255, 255, 255, 0.4);
        border-radius: 9999px;
    }
</style>

<script>
    let reloadDebounceTimer = null;
    const baseUrl = "{{ route('client.menu', ['vendor_slug' => $vendor->slug]) }}";

    function setDeviceWidth(width, btn) {
        document.getElementById('phoneMockupFrame').style.width = width;
        document.querySelectorAll('.preview-toolbar .toolbar-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');
    }

    function syncHexToPicker(pickerId, hexValue) {
        const picker = document.getElementById(pickerId);
        if (picker && /^#[0-9A-F]{6}$/i.test(hexValue)) {
            picker.value = hexValue;
        }
    }

    function syncPickerToHex(hexId, pickerValue) {
        const hex = document.getElementById(hexId);
        if (hex) hex.value = pickerValue;
    }

    function applyPreset(primary, accent, secondary) {
        document.getElementById('primaryColorInput').value = primary;
        document.getElementById('primaryColorHex').value = primary;
        document.getElementById('accentColorInput').value = accent;
        document.getElementById('accentColorHex').value = accent;
        document.getElementById('secondaryColorInput').value = secondary;
        document.getElementById('secondaryColorHex').value = secondary;
        
        injectColorsDirectly();
        debounceReloadPreview();
    }

    function onThemeModeChange(mode) {
        document.querySelectorAll('.mode-choice-card').forEach(c => c.classList.remove('selected'));
        const activeCard = document.querySelector(`input[name="theme_mode"][value="${mode}"]`)?.closest('.mode-choice-card');
        if (activeCard) activeCard.classList.add('selected');

        const bgInput = document.getElementById('bgColorInput');
        const bgHex = document.getElementById('bgColorHex');
        const textInput = document.getElementById('textColorInput');
        const textHex = document.getElementById('textColorHex');

        if (mode === 'light') {
            if (bgInput.value === '#09090b') { bgInput.value = '#f8fafc'; bgHex.value = '#f8fafc'; }
            if (textInput.value === '#f4f4f5') { textInput.value = '#0f172a'; textHex.value = '#0f172a'; }
        } else {
            if (bgInput.value === '#f8fafc') { bgInput.value = '#09090b'; bgHex.value = '#09090b'; }
            if (textInput.value === '#0f172a') { textInput.value = '#f4f4f5'; textHex.value = '#f4f4f5'; }
        }

        injectColorsDirectly();
        debounceReloadPreview();
    }

    function updateTemplateSelection(radio) {
        document.querySelectorAll('.template-choice-card').forEach(c => c.classList.remove('selected'));
        radio.closest('.template-choice-card')?.classList.add('selected');
        forceReloadPreview();
    }

    function onDesktopWidthChange(width) {
        document.querySelectorAll('input[name="desktop_max_width"]').forEach(i => {
            const card = i.closest('.mode-choice-card');
            if (card) card.classList.toggle('selected', i.value === width);
        });
        debounceReloadPreview();
    }

    // Instant direct style update inside iframe for zero-lag color feedback
    function injectColorsDirectly() {
        const iframe = document.getElementById('previewIframe');
        if (!iframe) return;

        const primary = document.getElementById('primaryColorInput')?.value;
        const accent = document.getElementById('accentColorInput')?.value;
        const secondary = document.getElementById('secondaryColorInput')?.value;
        const bg = document.getElementById('bgColorInput')?.value;
        const text = document.getElementById('textColorInput')?.value;
        const themeMode = document.querySelector('input[name="theme_mode"]:checked')?.value;

        try {
            const doc = iframe.contentDocument || iframe.contentWindow?.document;
            if (doc && doc.documentElement) {
                if (primary) doc.documentElement.style.setProperty('--primary', primary);
                if (accent) doc.documentElement.style.setProperty('--accent', accent);
                if (secondary) doc.documentElement.style.setProperty('--secondary', secondary);
                if (bg) doc.documentElement.style.setProperty('--bg-main', bg);
                if (text) doc.documentElement.style.setProperty('--text-main', text);
                if (themeMode) {
                    doc.documentElement.setAttribute('data-theme', themeMode);
                    doc.documentElement.className = themeMode;
                }
            }
        } catch (e) {
            // Cross-origin fallback handled by URL reload
        }
    }

    function debounceReloadPreview() {
        injectColorsDirectly();
        if (reloadDebounceTimer) clearTimeout(reloadDebounceTimer);
        reloadDebounceTimer = setTimeout(() => {
            reloadIframeUrl();
        }, 300);
    }

    function forceReloadPreview() {
        const spinIcon = document.getElementById('refreshSpinIcon');
        if (spinIcon) spinIcon.classList.add('fa-spin');
        reloadIframeUrl();
    }

    function reloadIframeUrl() {
        const form = document.getElementById('brandingForm');
        const iframe = document.getElementById('previewIframe');
        const loader = document.getElementById('previewLoader');
        if (!form || !iframe) return;

        const formData = new FormData(form);
        const params = new URLSearchParams();

        for (let [key, val] of formData.entries()) {
            if (val && typeof val === 'string' && !['logo_file', 'cover_file', '_token'].includes(key)) {
                params.append(key, val);
            }
        }

        if (loader) loader.classList.add('visible');
        iframe.src = baseUrl + '?' + params.toString();
    }

    function onIframeLoaded() {
        const loader = document.getElementById('previewLoader');
        const spinIcon = document.getElementById('refreshSpinIcon');
        if (loader) loader.classList.remove('visible');
        if (spinIcon) spinIcon.classList.remove('fa-spin');
        injectColorsDirectly();
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Two-way bindings for color inputs
        const colorFields = [
            { picker: 'primaryColorInput', hex: 'primaryColorHex' },
            { picker: 'accentColorInput', hex: 'accentColorHex' },
            { picker: 'secondaryColorInput', hex: 'secondaryColorHex' },
            { picker: 'bgColorInput', hex: 'bgColorHex' },
            { picker: 'textColorInput', hex: 'textColorHex' }
        ];

        colorFields.forEach(f => {
            const pickerEl = document.getElementById(f.picker);
            const hexEl = document.getElementById(f.hex);
            if (pickerEl && hexEl) {
                pickerEl.addEventListener('input', function() {
                    hexEl.value = this.value;
                    injectColorsDirectly();
                    debounceReloadPreview();
                });
                hexEl.addEventListener('input', function() {
                    syncHexToPicker(f.picker, this.value);
                    injectColorsDirectly();
                    debounceReloadPreview();
                });
            }
        });
    });
</script>
@endsection
