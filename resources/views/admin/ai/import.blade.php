@extends('layouts.app')

@section('title', 'AI Menu Toolkit - ' . $vendor->name)

@section('styles')
<style>
    .ai-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        padding: clamp(1.25rem, 3vw, 1.85rem);
        position: relative;
        overflow: hidden;
        box-shadow: var(--shadow-card);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 560px;
        box-sizing: border-box;
    }

    .ai-card-accent-orange {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #f59e0b, #ec4899);
    }

    .ai-card-accent-blue {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #3b82f6, #8b5cf6);
    }

    /* Segmented Tabs Strip */
    .ai-tabs-strip {
        display: flex;
        align-items: center;
        background: var(--input-bg);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        padding: 5px;
        gap: 6px;
        margin-bottom: 1.5rem;
        width: 100%;
        box-sizing: border-box;
    }

    .ai-tab-btn {
        flex: 1 1 0%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.65rem 0.5rem;
        border-radius: 10px;
        border: 1px solid transparent;
        background: transparent;
        color: var(--text-muted);
        font-family: 'Inter', -apple-system, sans-serif;
        font-size: 0.85rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        white-space: nowrap;
        outline: none;
        box-sizing: border-box;
    }

    .ai-tab-btn:hover {
        color: var(--text-main);
        background: rgba(148, 163, 184, 0.08);
    }

    .ai-tab-btn.active {
        background: var(--bg-card);
        color: var(--text-main);
        border-color: var(--border-color);
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.04);
    }

    /* Modern Dropzone */
    .ai-dropzone {
        border: 2px dashed var(--border-color);
        border-radius: 16px;
        padding: 1.75rem 1.25rem;
        text-align: center;
        background: var(--bg-body);
        transition: all 0.25s ease;
        position: relative;
        cursor: pointer;
    }

    .ai-dropzone:hover {
        border-color: var(--primary);
        background: rgba(245, 158, 11, 0.03);
    }

    .ai-dropzone input[type="file"] {
        position: absolute;
        inset: 0;
        opacity: 0;
        width: 100%;
        height: 100%;
        cursor: pointer;
        z-index: 10;
    }

    /* Format Badge Chips */
    .format-chips-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        margin-top: 0.85rem;
    }

    .format-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.25rem 0.55rem;
        border-radius: 8px;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        line-height: 1.2;
    }

    /* Input Field Styling */
    .ai-input-wrap {
        position: relative;
        width: 100%;
    }

    .ai-input-wrap input {
        width: 100%;
        box-sizing: border-box;
        padding: 0.85rem 1rem 0.85rem 2.85rem;
        background: var(--bg-body);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        color: var(--text-main);
        font-size: 0.9rem;
        font-family: 'Inter', sans-serif;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .ai-input-wrap input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px var(--primary-glow);
    }

    .ai-input-wrap .input-prefix-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        font-size: 1rem;
        pointer-events: none;
    }

    .ai-textarea {
        width: 100%;
        box-sizing: border-box;
        padding: 0.95rem 1.15rem;
        background: var(--bg-body);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        color: var(--text-main);
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.85rem;
        outline: none;
        resize: vertical;
        line-height: 1.55;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .ai-textarea:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px var(--primary-glow);
    }

    .ai-info-banner {
        padding: 0.85rem 1rem;
        border-radius: 14px;
        font-size: 0.8rem;
        line-height: 1.5;
        display: flex;
        align-items: flex-start;
        gap: 0.6rem;
    }
</style>
@endsection

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div style="min-width: 0; flex: 1;">
        <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
            <span style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
            </span>
            <span>{{ __('AI Menu Toolkit') }}</span>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0; word-break: break-word;">
            {{ __('Extract menus from PDF/Word files or instantly translate your entire menu with 1 click') }}
        </p>
    </div>
</div>

<div class="grid-2" style="gap: 1.75rem;" x-data="{ tab: 'file', isProcessing: false, fileName: '' }">
    <!-- Tool A: AI Multi-Format & URL Menu Extractor -->
    <div class="ai-card">
        <div class="ai-card-accent-orange"></div>

        <!-- Processing Loading Overlay -->
        <div x-show="isProcessing" x-transition style="position: absolute; inset: 0; background: var(--modal-overlay); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 50; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 2rem;">
            <div style="width: 60px; height: 60px; border-radius: 18px; background: var(--primary-gradient); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.8rem; box-shadow: 0 10px 25px var(--primary-glow); margin-bottom: 1.25rem;">
                <i class="fa-solid fa-wand-magic-sparkles fa-spin"></i>
            </div>
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.4rem;">
                {{ __('AI is extracting menu...') }}
            </h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); max-width: 320px; margin: 0; line-height: 1.5;">
                {{ __('Structuring dishes, prices, descriptions, and categories. Please wait.') }}
            </p>
        </div>

        <div>
            <div style="display: flex; align-items: center; gap: 0.85rem; margin-bottom: 0.75rem;">
                <span style="width: 44px; height: 44px; border-radius: 14px; background: rgba(245, 158, 11, 0.15); color: var(--primary); display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-brain"></i>
                </span>
                <div>
                    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0;">
                        1. {{ __('AI Smart Import (Omni-Extractor)') }}
                    </h3>
                    <div style="display: flex; align-items: center; gap: 0.4rem; margin-top: 0.2rem;">
                        <span class="badge badge-amber" style="font-size: 0.68rem;">{{ __('Excel, Word, CSV, Images, PDF & Web URL') }}</span>
                    </div>
                </div>
            </div>

            <p style="font-size: 0.86rem; color: var(--text-muted); margin: 0 0 1.35rem 0; line-height: 1.5; word-break: break-word;">
                {{ __('Upload any file format, attach a restaurant website URL, or paste menu text. AI will automatically generate categories, dishes, prices, and descriptions.') }}
            </p>

            <!-- Segmented Control Tabs -->
            <div class="ai-tabs-strip">
                <button type="button" @click="tab = 'file'" class="ai-tab-btn" :class="{ 'active': tab === 'file' }">
                    <i class="fa-solid fa-file-arrow-up" style="color: #10b981;"></i>
                    <span>{{ __('Files') }}</span>
                </button>
                <button type="button" @click="tab = 'url'" class="ai-tab-btn" :class="{ 'active': tab === 'url' }">
                    <i class="fa-solid fa-globe" style="color: #3b82f6;"></i>
                    <span>{{ __('Website URL') }}</span>
                </button>
                <button type="button" @click="tab = 'text'" class="ai-tab-btn" :class="{ 'active': tab === 'text' }">
                    <i class="fa-solid fa-align-left" style="color: #f59e0b;"></i>
                    <span>{{ __('Text') }}</span>
                </button>
            </div>

            <form action="{{ route('admin.ai.import.process') }}" method="POST" enctype="multipart/form-data" @submit="isProcessing = true">
                @csrf
                <input type="hidden" name="import_source" :value="tab">

                <!-- Tab 1: Multi-format Files -->
                <div x-show="tab === 'file'" style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                        <i class="fa-solid fa-cloud-arrow-up" style="color: var(--primary); margin-right: 0.35rem;"></i>
                        {{ __('Upload Menu File') }}
                    </label>

                    <div class="ai-dropzone">
                        <input type="file" name="menu_file" id="menuFileInput" accept=".xlsx,.xls,.csv,.docx,.doc,.pdf,.jpg,.jpeg,.png,.webp,.txt,.json" @change="fileName = $event.target.files[0]?.name || ''">
                        <div style="pointer-events: none;">
                            <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--input-bg); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: var(--primary); margin: 0 auto 0.75rem;">
                                <i class="fa-solid fa-file-circle-plus"></i>
                            </div>
                            <div style="font-size: 0.92rem; font-weight: 800; color: var(--text-main);" x-text="fileName ? ('{{ __('Selected file:') }} ' + fileName) : '{{ __('Click or drag file here') }}'"></div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;">
                                {{ __('Maximum size: 20 MB. All formats supported.') }}
                            </div>
                        </div>
                    </div>

                    <!-- Supported Format Badges -->
                    <div class="format-chips-grid">
                        <span class="format-chip badge-emerald">
                            <i class="fa-solid fa-file-excel"></i> {{ __('Excel (.xlsx, .xls)') }}
                        </span>
                        <span class="format-chip badge-cyan">
                            <i class="fa-solid fa-file-csv"></i> {{ __('CSV (.csv)') }}
                        </span>
                        <span class="format-chip badge-indigo">
                            <i class="fa-solid fa-file-word"></i> {{ __('Word (.docx, .doc)') }}
                        </span>
                        <span class="format-chip badge-purple">
                            <i class="fa-solid fa-image"></i> {{ __('Images (.jpg, .png, .webp)') }}
                        </span>
                        <span class="format-chip badge-rose">
                            <i class="fa-solid fa-file-pdf"></i> {{ __('PDF (.pdf)') }}
                        </span>
                        <span class="format-chip badge-amber">
                            <i class="fa-solid fa-file-lines"></i> {{ __('TXT / JSON') }}
                        </span>
                    </div>
                </div>

                <!-- Tab 2: Website URL Scraping -->
                <div x-show="tab === 'url'" style="margin-bottom: 1.5rem;" x-cloak>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                        <i class="fa-solid fa-link" style="color: #3b82f6; margin-right: 0.35rem;"></i>
                        {{ __('Restaurant or Menu Website URL') }}
                    </label>

                    <div class="ai-input-wrap">
                        <i class="fa-solid fa-globe input-prefix-icon" style="color: #3b82f6;"></i>
                        <input type="url" name="website_url" placeholder="https://example-restaurant.com/menu">
                    </div>

                    <div class="ai-info-banner" style="background: rgba(59, 130, 246, 0.08); border: 1px dashed rgba(59, 130, 246, 0.25); color: var(--text-muted); margin-top: 0.85rem;">
                        <i class="fa-solid fa-wand-magic-sparkles" style="color: #3b82f6; margin-top: 0.15rem; flex-shrink: 0;"></i>
                        <span>{{ __('Paste the official website, online menu, or delivery page link. AI will analyze the site structure and extract dishes.') }}</span>
                    </div>
                </div>

                <!-- Tab 3: Direct Text -->
                <div x-show="tab === 'text'" style="margin-bottom: 1.5rem;" x-cloak>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                        <i class="fa-solid fa-paste" style="color: var(--primary); margin-right: 0.35rem;"></i>
                        {{ __('Paste Menu Text or Price List') }}
                    </label>
                    <textarea name="menu_text" rows="7" class="ai-textarea" placeholder="STARTERS&#10;Truffle Hummus 3500 AMD&#10;Fried Calamari 4500 AMD&#10;&#10;MAINS&#10;Ribeye Steak 12500 AMD"></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; box-sizing: border-box; justify-content: center; border-radius: 12px; font-weight: 800; padding: 0.85rem 1.5rem; font-size: 0.95rem; box-shadow: 0 4px 14px var(--primary-glow); gap: 0.5rem;">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    <span>{{ __('Extract & Review Menu') }}</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Tool B: AI One-Click Translation -->
    <div class="ai-card">
        <div class="ai-card-accent-blue"></div>

        <div>
            <div style="display: flex; align-items: center; gap: 0.85rem; margin-bottom: 0.75rem;">
                <span style="width: 44px; height: 44px; border-radius: 14px; background: rgba(59, 130, 246, 0.15); color: #3b82f6; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-language"></i>
                </span>
                <div>
                    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0;">
                        2. {{ __('AI 1-Click Translation') }}
                    </h3>
                    <div style="display: flex; align-items: center; gap: 0.4rem; margin-top: 0.2rem;">
                        <span class="badge badge-indigo" style="font-size: 0.68rem;">{{ __('Multilingual Deep Translation') }}</span>
                    </div>
                </div>
            </div>

            <p style="font-size: 0.86rem; color: var(--text-muted); margin: 0 0 1.35rem 0; line-height: 1.5; word-break: break-word;">
                {{ __('Instantly translate your existing dish names, descriptions, and categories into any selected language so international guests can order with confidence:') }}
            </p>

            <form id="translateMenuForm" action="{{ route('admin.ai.translate') }}" method="POST">
                @csrf
                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                        {{ __('Select Target Language') }}
                    </label>
                    <select name="target_language" required style="width: 100%; box-sizing: border-box; padding: 0.85rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; color: var(--text-main); font-size: 0.92rem; font-weight: 700; outline: none; cursor: pointer;">
                        @php
                            $supportedLangs = $vendor->getSupportedLanguages();
                            $existingCodes = array_map(fn($l) => strtolower($l['code'] ?? ''), $supportedLangs);
                            $extraPresets = [
                                ['code' => 'hy', 'name' => 'Armenian', 'flag' => '🇦🇲'],
                                ['code' => 'en', 'name' => 'English', 'flag' => '🇬🇧'],
                                ['code' => 'ru', 'name' => 'Russian', 'flag' => '🇷🇺'],
                                ['code' => 'fr', 'name' => 'French', 'flag' => '🇫🇷'],
                                ['code' => 'de', 'name' => 'German', 'flag' => '🇩🇪'],
                                ['code' => 'es', 'name' => 'Spanish', 'flag' => '🇪🇸'],
                                ['code' => 'it', 'name' => 'Italian', 'flag' => '🇮🇹'],
                                ['code' => 'ge', 'name' => 'Georgian', 'flag' => '🇬🇪'],
                                ['code' => 'ar', 'name' => 'Arabic', 'flag' => '🇦🇪'],
                            ];
                        @endphp
                        <optgroup label="{{ __('Partner Active Languages') }}">
                            @foreach($supportedLangs as $lang)
                                <option value="{{ $lang['code'] }}">{{ $lang['flag'] ?? '🌐' }} {{ $lang['name'] }} ({{ strtoupper($lang['code']) }})</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="{{ __('Other Available Languages') }}">
                            @foreach($extraPresets as $extra)
                                @if(!in_array($extra['code'], $existingCodes))
                                    <option value="{{ $extra['code'] }}">{{ $extra['flag'] }} {{ $extra['name'] }}</option>
                                @endif
                            @endforeach
                        </optgroup>
                    </select>
                </div>

                <div style="margin-bottom: 1.25rem; display: flex; align-items: flex-start; gap: 0.75rem; background: var(--bg-body); padding: 0.85rem 1rem; border-radius: 12px; border: 1px solid var(--border-color);">
                    <input type="checkbox" id="overwrite_existing" name="overwrite_existing" value="1" checked style="width: 18px; height: 18px; margin-top: 2px; accent-color: #3b82f6; cursor: pointer;">
                    <label for="overwrite_existing" style="font-size: 0.84rem; font-weight: 600; color: var(--text-main); cursor: pointer; line-height: 1.4;">
                        <span>{{ __('Fix / Overwrite existing translations') }}</span>
                        <span style="display: block; font-size: 0.76rem; color: var(--text-muted); font-weight: normal; margin-top: 2px;">
                            {{ __('If checked, already translated items and descriptions will be re-translated and updated via AI.') }}
                        </span>
                    </label>
                </div>

                <div class="ai-info-banner" style="background: rgba(59, 130, 246, 0.08); border: 1px dashed rgba(59, 130, 246, 0.25); color: var(--text-muted); margin-bottom: 1.5rem;">
                    <i class="fa-solid fa-circle-info" style="color: #3b82f6; margin-top: 0.15rem; flex-shrink: 0;"></i>
                    <span>{{ __('After translation completes, all dishes, descriptions, and categories will receive translated versions for the selected menu language.') }}</span>
                </div>

                <button type="submit" id="btnTranslateMenu" class="btn btn-primary" style="width: 100%; box-sizing: border-box; justify-content: center; background: linear-gradient(135deg, #3b82f6, #2563eb); border-color: #2563eb; color: #fff; border-radius: 12px; font-weight: 800; padding: 0.85rem 1.5rem; font-size: 0.95rem; box-shadow: 0 4px 14px rgba(59, 130, 246, 0.35); gap: 0.5rem; transition: all 0.2s ease;">
                    <i class="fa-solid fa-language"></i>
                    <span>{{ __('Translate / Update Full Menu Now') }}</span>
                </button>

                <div id="translateStatusBox" style="display: none; align-items: center; gap: 0.85rem; margin-top: 1.25rem; padding: 1rem 1.2rem; background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.25); border-radius: 14px; color: var(--text-main); font-size: 0.88rem; font-weight: 600; line-height: 1.4;">
                    <i class="fa-solid fa-circle-notch fa-spin" style="color: #3b82f6; font-size: 1.35rem; flex-shrink: 0;"></i>
                    <span>{{ __('AI is translating dishes, categories, and descriptions. Please wait and do not close this page...') }}</span>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Section 3: Dynamic Menu Language Management (Add / Edit / Remove) -->
<div class="card" style="margin-top: 2rem; border-radius: 20px; padding: clamp(1.25rem, 3vw, 1.85rem); background: var(--bg-card); border: 1px solid var(--border-color); box-shadow: var(--shadow-card);"
     x-data="{ 
        modalOpen: false, 
        isEdit: false, 
        originalCode: '', 
        code: '', 
        name: '', 
        flag: '',
        openCreate() {
            this.isEdit = false;
            this.originalCode = '';
            this.code = '';
            this.name = '';
            this.flag = '';
            this.modalOpen = true;
        },
        openEdit(lang) {
            this.isEdit = true;
            this.originalCode = lang.code;
            this.code = lang.code;
            this.name = lang.name;
            this.flag = lang.flag || '';
            this.modalOpen = true;
        },
        applyPreset(presetCode, presetName, presetFlag) {
            this.code = presetCode;
            this.name = presetName;
            this.flag = presetFlag;
        }
     }">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem;">
        <div style="display: flex; align-items: center; gap: 0.85rem;">
            <span style="width: 44px; height: 44px; border-radius: 14px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                <i class="fa-solid fa-earth-americas"></i>
            </span>
            <div>
                <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    3. {{ __('Menu Language Management') }}
                </h3>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.2rem 0 0 0;">
                    {{ __('Add new languages, customize display names and flags, or remove unused languages:') }}
                </p>
            </div>
        </div>

        <button type="button" @click="openCreate()" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 700; border-radius: 12px; padding: 0.65rem 1.25rem;">
            <i class="fa-solid fa-plus"></i>
            <span>{{ __('Add New Language') }}</span>
        </button>
    </div>

    <!-- Active Languages Cards Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
        @foreach($supportedLangs as $lang)
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 16px; transition: transform 0.2s, box-shadow 0.2s;">
                <div style="display: flex; align-items: center; gap: 0.85rem;">
                    <span style="font-size: 2rem; line-height: 1;">{{ $lang['flag'] ?? '🌐' }}</span>
                    <div>
                        <div style="font-weight: 800; color: var(--text-main); font-size: 1rem;">
                            {{ $lang['name'] }}
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.4rem; margin-top: 0.2rem;">
                            <span class="badge badge-subtle" style="font-family: monospace; font-size: 0.72rem; text-transform: uppercase; font-weight: 700;">
                                {{ $lang['code'] }}
                            </span>
                            <span style="font-size: 0.75rem; color: var(--text-muted);">
                                {{ __('Active language') }}
                            </span>
                        </div>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 0.4rem;">
                    <!-- Edit Button -->
                    <button type="button" 
                            @click="openEdit({{ json_encode($lang) }})" 
                            class="btn-icon" 
                            title="{{ __('Edit') }}"
                            style="width: 36px; height: 36px; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-muted); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s;">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>

                    <!-- Delete Button -->
                    @if(count($supportedLangs) > 1)
                        <form action="{{ route('admin.ai.languages.destroy', $lang['code']) }}" method="POST" onsubmit="return confirm('{{ __('Are you sure you want to remove this language?') }}')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" 
                                    class="btn-icon" 
                                    title="{{ __('Remove') }}"
                                    style="width: 36px; height: 36px; border-radius: 10px; border: 1px solid rgba(239, 68, 68, 0.2); background: rgba(239, 68, 68, 0.06); color: #ef4444; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s;">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <!-- Quick Preset Buttons -->
    <div style="background: var(--input-bg); border: 1px dashed var(--border-color); border-radius: 14px; padding: 1rem 1.25rem;">
        <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 0.6rem;">
            <i class="fa-solid fa-bolt" style="color: var(--primary); margin-right: 0.35rem;"></i>
            {{ __('Quick add popular languages:') }}
        </span>
        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
            @php
                $presets = [
                    ['code' => 'fr', 'name' => 'Français', 'flag' => '🇫🇷'],
                    ['code' => 'de', 'name' => 'Deutsch', 'flag' => '🇩🇪'],
                    ['code' => 'es', 'name' => 'Español', 'flag' => '🇪🇸'],
                    ['code' => 'it', 'name' => 'Italiano', 'flag' => '🇮🇹'],
                    ['code' => 'ge', 'name' => 'ქართული', 'flag' => '🇬🇪'],
                    ['code' => 'ar', 'name' => 'العربية', 'flag' => '🇦🇪'],
                    ['code' => 'fa', 'name' => 'فارسی', 'flag' => '🇮🇷'],
                ];
            @endphp
            @foreach($presets as $p)
                @if(!in_array($p['code'], $existingCodes))
                    <button type="button" 
                            @click="applyPreset('{{ $p['code'] }}', '{{ $p['name'] }}', '{{ $p['flag'] }}'); modalOpen = true; isEdit = false; originalCode = '';"
                            class="format-chip badge-subtle" 
                            style="cursor: pointer; border: 1px solid var(--border-color); background: var(--bg-card); font-size: 0.8rem; padding: 0.4rem 0.75rem;">
                        <span>{{ $p['flag'] }}</span>
                        <span>+ {{ $p['name'] }} ({{ strtoupper($p['code']) }})</span>
                    </button>
                @endif
            @endforeach
        </div>
    </div>

    <!-- Add/Edit Language Modal -->
    <div x-show="modalOpen" x-cloak style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 999; display: flex; align-items: center; justify-content: center; padding: 1.5rem;">
        <div @click.away="modalOpen = false" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; width: 100%; max-width: 480px; box-shadow: 0 20px 40px rgba(0,0,0,0.25); overflow: hidden;">
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h4 style="margin: 0; font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 800; color: var(--text-main);" x-text="isEdit ? '{{ __('Edit Language') }}' : '{{ __('Add New Language') }}'"></h4>
                <button type="button" @click="modalOpen = false" style="background: transparent; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="{{ route('admin.ai.languages.save') }}" method="POST" style="padding: 1.5rem;">
                @csrf
                <input type="hidden" name="original_code" :value="originalCode">

                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                        {{ __('Language Code (e.g. fr, de, it, ge)') }} <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="code" x-model="code" required pattern="[a-zA-Z]{2,5}" maxlength="5" placeholder="fr" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-weight: 700; font-family: monospace;">
                </div>

                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                        {{ __('Language Name (e.g. Français, Deutsch)') }} <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="name" x-model="name" required maxlength="50" placeholder="Français" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-weight: 700;">
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                        {{ __('Flag Emoji or Icon (e.g. 🇫🇷, 🇩🇪)') }}
                    </label>
                    <input type="text" name="flag" x-model="flag" maxlength="10" placeholder="🇫🇷" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 1.2rem;">
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" @click="modalOpen = false" class="btn btn-secondary" style="border-radius: 10px; font-weight: 700; padding: 0.65rem 1.25rem;">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 800; padding: 0.65rem 1.5rem;">
                        <i class="fa-solid fa-check"></i>
                        <span>{{ __('Save') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('translateMenuForm');
    if (form) {
        form.addEventListener('submit', function() {
            const btn = document.getElementById('btnTranslateMenu');
            const statusBox = document.getElementById('translateStatusBox');
            if (btn) {
                btn.disabled = true;
                btn.style.opacity = '0.75';
                btn.style.cursor = 'not-allowed';
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>{{ __('Translating via AI (please wait)...') }}</span>';
            }
            if (statusBox) {
                statusBox.style.display = 'flex';
            }
        });
    }
});
</script>
@endsection
