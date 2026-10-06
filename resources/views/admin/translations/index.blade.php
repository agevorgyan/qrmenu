@extends('layouts.app')

@section('title', __('Translations & AI Localization') . ' - ' . $vendor->name)

@section('content')
<!-- Hero Banner -->
<div class="card" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.12) 0%, rgba(139, 92, 246, 0.08) 50%, rgba(245, 158, 11, 0.06) 100%); border-color: rgba(99, 102, 241, 0.2); padding: 1.75rem 2rem; margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem;">
        <div style="display: flex; align-items: center; gap: 1.25rem;">
            <div style="width: 54px; height: 54px; border-radius: 16px; background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.6rem; box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);">
                <i class="fa-solid fa-language"></i>
            </div>
            <div>
                <h1 style="font-size: 1.65rem; font-weight: 800; margin: 0; color: var(--text-main);">
                    {{ __('Translations & AI Localization') }}
                </h1>
                <p style="color: var(--text-muted); font-size: 0.88rem; margin-top: 0.25rem;">
                    {{ __('Translate your menu into multiple languages with AI assistance, human review, and real-time storefront synchronization.') }}
                </p>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <a href="{{ route('admin.translations.glossary.index') }}" class="btn" style="border-color: var(--border-color); font-weight: 600;">
                <i class="fa-solid fa-book-bookmark"></i> {{ __('Terminology Glossary') }}
            </a>
            <button type="button" class="btn" onclick="document.getElementById('langSettingsModal').style.display='flex';" style="border-color: var(--border-color); font-weight: 600;">
                <i class="fa-solid fa-gear"></i> {{ __('Language Settings') }}
            </button>
            <form action="{{ route('admin.translations.batch_ai') }}" method="POST" style="margin: 0;">
                @csrf
                <input type="hidden" name="target_locale" value="{{ $targetLocale }}">
                <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 700; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('Translate All with AI') }} ({{ strtoupper($targetLocale) }})
                </button>
            </form>
        </div>
    </div>

    <!-- Progress Bar -->
    <div style="margin-top: 1.5rem; background: rgba(0,0,0,0.15); border-radius: 12px; padding: 1rem 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; font-size: 0.85rem;">
            <span style="font-weight: 700; color: var(--text-main);">
                {{ __('Target Language Coverage') }} ({{ strtoupper($targetLocale) }}): <strong>{{ $stats['percentage'] }}%</strong>
            </span>
            <span style="color: var(--text-muted);">
                {{ $stats['published'] }} {{ __('published of') }} {{ $stats['total_fields'] }} {{ __('total fields') }}
            </span>
        </div>
        <div style="height: 10px; border-radius: 999px; background: rgba(255,255,255,0.08); overflow: hidden;">
            <div style="height: 100%; width: {{ $stats['percentage'] }}%; background: linear-gradient(90deg, #6366f1, #10b981); border-radius: 999px; transition: width 0.4s ease;"></div>
        </div>
    </div>
</div>

@if(session('success'))
    <div style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); color: #10b981; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
        <i class="fa-solid fa-circle-check"></i>
        <span>{{ session('success') }}</span>
    </div>
@endif

@if(session('error'))
    <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span>{{ session('error') }}</span>
    </div>
@endif

@if($errors->any())
    <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem;">
        <div style="font-weight: 700; margin-bottom: 0.4rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>{{ __('Please correct the following errors:') }}</span>
        </div>
        <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.88rem;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<!-- Language Selector Tabs -->
<div style="display: flex; gap: 0.6rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1.5rem; overflow-x: auto;">
    @foreach($supportedLanguages as $langItem)
        @php
            $langCode = is_array($langItem) ? ($langItem['code'] ?? '') : $langItem;
            $isActive = ($langCode === $targetLocale);
            $langName = is_array($langItem) 
                ? (($langItem['flag'] ?? '🌐') . ' ' . ($langItem['name'] ?? strtoupper($langCode)))
                : match($langCode) {
                    'hy' => '🇦🇲 Հայերեն',
                    'en' => '🇬🇧 English',
                    'ru' => '🇷🇺 Русский',
                    default => '🌐 ' . strtoupper($langCode),
                };
        @endphp
        <a href="{{ route('admin.translations.index', ['locale' => $langCode, 'status' => $status, 'type' => $type]) }}" 
           class="btn" 
           style="padding: 0.6rem 1.25rem; font-weight: 700; border-radius: 12px; {{ $isActive ? 'background: #6366f1; color: #ffffff; border-color: #6366f1; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);' : 'background: var(--input-bg); color: var(--text-muted); border-color: var(--border-color);' }}">
            {{ $langName }}
            @if($langCode === $defaultLangCode)
                <span style="font-size: 0.7rem; opacity: 0.8; margin-left: 0.35rem;">({{ __('Primary') }})</span>
            @endif
        </a>
    @endforeach
</div>

<!-- Filters and Search -->
<div class="card" style="padding: 1.25rem 1.5rem; margin-bottom: 1.5rem;">
    <form action="{{ route('admin.translations.index') }}" method="GET" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: space-between;">
        <input type="hidden" name="locale" value="{{ $targetLocale }}">

        <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center;">
            <div>
                <select name="status" onchange="this.form.submit()" class="form-control" style="padding: 0.55rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-main); font-size: 0.88rem;">
                    <option value="all" {{ $status === 'all' ? 'selected' : '' }}>{{ __('All Statuses') }}</option>
                    <option value="published" {{ $status === 'published' ? 'selected' : '' }}>{{ __('Published') }} ({{ $stats['published'] }})</option>
                    <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>{{ __('Approved') }} ({{ $stats['approved'] }})</option>
                    <option value="draft" {{ $status === 'draft' ? 'selected' : '' }}>{{ __('Draft') }} ({{ $stats['draft'] }})</option>
                    <option value="needs_review" {{ $status === 'needs_review' ? 'selected' : '' }}>{{ __('Needs Review / Outdated') }} ({{ $stats['needs_review'] }})</option>
                    <option value="failed" {{ $status === 'failed' ? 'selected' : '' }}>{{ __('Failed') }}</option>
                </select>
            </div>

            <div>
                <select name="type" onchange="this.form.submit()" class="form-control" style="padding: 0.55rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-main); font-size: 0.88rem;">
                    <option value="all" {{ $type === 'all' ? 'selected' : '' }}>{{ __('All Types') }}</option>
                    <option value="Product" {{ $type === 'Product' ? 'selected' : '' }}>{{ __('Products') }}</option>
                    <option value="Category" {{ $type === 'Category' ? 'selected' : '' }}>{{ __('Categories') }}</option>
                </select>
            </div>
        </div>

        <div style="display: flex; gap: 0.5rem; min-width: 280px;">
            <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('Search source or translation...') }}" class="form-control" style="flex: 1; padding: 0.55rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-main); font-size: 0.88rem;">
            <button type="submit" class="btn btn-primary" style="padding: 0.55rem 1rem;">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
        </div>
    </form>
</div>

<!-- Translations Table Card -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
            <thead>
                <tr style="background: rgba(255,255,255,0.02); border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.05em;">
                    <th style="padding: 1rem 1.5rem;">{{ __('Item / Field') }}</th>
                    <th style="padding: 1rem 1.5rem; width: 32%;">{{ __('Source Text') }} ({{ strtoupper($defaultLangCode) }})</th>
                    <th style="padding: 1rem 1.5rem; width: 32%;">{{ __('Translation') }} ({{ strtoupper($targetLocale) }})</th>
                    <th style="padding: 1rem 1rem;">{{ __('Status') }}</th>
                    <th style="padding: 1rem 1rem;">{{ __('Source') }}</th>
                    <th style="padding: 1rem 1.5rem; text-align: right;">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($translations as $item)
                    @php
                        $entityTitle = $item->translatable?->name ?? 'Item #' . $item->translatable_id;
                        $entityIcon = ($item->translatable_type === 'App\Models\Category' || str_ends_with($item->translatable_type, 'Category')) 
                            ? 'fa-folder' 
                            : 'fa-burger';
                    @endphp
                    <tr style="border-bottom: 1px solid var(--table-row-border); transition: background-color 0.15s ease;">
                        <td style="padding: 1rem 1.5rem;">
                            <div style="display: flex; align-items: center; gap: 0.65rem;">
                                <i class="fa-solid {{ $entityIcon }}" style="color: #6366f1;"></i>
                                <div>
                                    <div style="font-weight: 700; color: var(--text-main);">{{ $entityTitle }}</div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">
                                        {{ $item->field }}
                                    </div>
                                </div>
                            </div>
                        </td>

                        <td style="padding: 1rem 1.5rem;">
                            <div style="color: var(--text-main); font-size: 0.88rem; max-height: 4.5rem; overflow: hidden; text-overflow: ellipsis;">
                                {{ $item->source_text }}
                            </div>
                        </td>

                        <td style="padding: 1rem 1.5rem;">
                            @if(!empty($item->translation))
                                <div style="color: var(--text-main); font-weight: 600; font-size: 0.88rem; max-height: 4.5rem; overflow: hidden; text-overflow: ellipsis;">
                                    {{ $item->translation }}
                                </div>
                            @else
                                <span style="font-style: italic; color: var(--text-muted); font-size: 0.85rem;">
                                    {{ __('Not translated yet') }}
                                </span>
                            @endif

                            @if(!empty($item->metadata['ai_proposal']))
                                <div style="margin-top: 0.35rem; display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.15rem 0.5rem; background: rgba(99, 102, 241, 0.15); color: #818cf8; border-radius: 6px; font-size: 0.72rem; font-weight: 700;">
                                    <i class="fa-solid fa-sparkles"></i> {{ __('AI Proposal Pending Review') }}
                                </div>
                            @endif
                        </td>

                        <td style="padding: 1rem 1rem;">
                            @if($item->status === 'published')
                                <span class="badge badge-emerald" style="font-size: 0.72rem; padding: 0.2rem 0.55rem;">
                                    <i class="fa-solid fa-check-double"></i> {{ __('Published') }}
                                </span>
                            @elseif($item->status === 'approved')
                                <span class="badge badge-amber" style="font-size: 0.72rem; padding: 0.2rem 0.55rem;">
                                    <i class="fa-solid fa-check"></i> {{ __('Approved') }}
                                </span>
                            @elseif($item->status === 'draft')
                                <span class="badge" style="background: rgba(148, 163, 184, 0.2); color: #94a3b8; font-size: 0.72rem; padding: 0.2rem 0.55rem;">
                                    <i class="fa-regular fa-file"></i> {{ __('Draft') }}
                                </span>
                            @elseif($item->status === 'needs_review' || $item->is_outdated)
                                <span class="badge" style="background: rgba(245, 158, 11, 0.2); color: #f59e0b; font-size: 0.72rem; padding: 0.2rem 0.55rem;">
                                    <i class="fa-solid fa-clock-rotate-left"></i> {{ __('Outdated') }}
                                </span>
                            @else
                                <span class="badge" style="background: rgba(239, 68, 68, 0.2); color: #ef4444; font-size: 0.72rem; padding: 0.2rem 0.55rem;">
                                    {{ $item->status }}
                                </span>
                            @endif
                        </td>

                        <td style="padding: 1rem 1rem;">
                            <span style="font-size: 0.78rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">
                                {{ $item->source_type }}
                            </span>
                        </td>

                        <td style="padding: 1rem 1.5rem; text-align: right;">
                            <a href="{{ route('admin.translations.edit', $item) }}" class="btn btn-primary" style="padding: 0.4rem 0.85rem; font-size: 0.8rem; font-weight: 700;">
                                <i class="fa-solid fa-sliders"></i> {{ __('Review & Edit') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding: 3.5rem; text-align: center; color: var(--text-muted);">
                            <div style="font-size: 2.5rem; margin-bottom: 0.75rem; color: #6366f1;">
                                <i class="fa-solid fa-language"></i>
                            </div>
                            <h4 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.35rem;">
                                {{ __('No translation records found for') }} {{ strtoupper($targetLocale) }}
                            </h4>
                            <p style="font-size: 0.85rem; margin-bottom: 1.25rem;">
                                {{ __('Click "Translate All with AI" to generate initial draft translations for your entire menu.') }}
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($translations->hasPages())
        <div style="padding: 1.25rem 1.5rem; border-top: 1px solid var(--border-color);">
            {{ $translations->links() }}
        </div>
    @endif
</div>

<!-- Modal: Restaurant Language Settings -->
<div id="langSettingsModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card" style="width: 100%; max-width: 540px; padding: 2rem; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; margin: 0; color: var(--text-main);">
                {{ __('Restaurant Language Settings') }}
            </h3>
            <button type="button" onclick="document.getElementById('langSettingsModal').style.display='none';" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form action="{{ route('admin.translations.languages.sync') }}" method="POST">
            @csrf
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.75rem;">
                    {{ __('Select Active Languages for Customers:') }}
                </label>

                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    @foreach($allSystemLanguages as $lang)
                        <label style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: 10px; cursor: pointer; background: var(--input-bg);">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <span style="font-size: 1.4rem;">{{ $lang->flag ?? '🌐' }}</span>
                                <div>
                                    <span style="font-weight: 700; color: var(--text-main);">{{ $lang->name }}</span>
                                    <span style="font-size: 0.8rem; color: var(--text-muted); margin-left: 0.35rem;">({{ $lang->native_name }})</span>
                                </div>
                            </div>
                            <input type="checkbox" name="active_locales[]" value="{{ $lang->code }}" {{ in_array($lang->code, $supportedCodes ?? []) ? 'checked' : '' }} style="width: 20px; height: 20px; accent-color: #6366f1;">
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Quick Add Custom Language (if not listed above) -->
            <details style="margin-bottom: 1.5rem; background: rgba(99, 102, 241, 0.05); border: 1px dashed rgba(99, 102, 241, 0.3); border-radius: 10px; padding: 0.75rem 1rem;">
                <summary style="font-size: 0.85rem; font-weight: 700; color: #6366f1; cursor: pointer;">
                    <i class="fa-solid fa-plus-circle"></i> {{ __('Add another language (e.g. Portuguese, Greek...)') }}
                </summary>
                <div style="margin-top: 0.75rem; display: grid; grid-template-columns: 1fr 1.5fr 1fr; gap: 0.6rem;">
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 0.2rem;">ISO Code (e.g. pt)</label>
                        <input type="text" name="new_code" placeholder="pt" maxlength="10" class="form-control" style="font-size: 0.85rem; padding: 0.45rem 0.6rem; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-body); color: var(--text-main);">
                    </div>
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 0.2rem;">Language Name</label>
                        <input type="text" name="new_name" placeholder="Portuguese" maxlength="60" class="form-control" style="font-size: 0.85rem; padding: 0.45rem 0.6rem; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-body); color: var(--text-main);">
                    </div>
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 0.2rem;">Flag Emoji</label>
                        <input type="text" name="new_flag" placeholder="🇵🇹" maxlength="10" class="form-control" style="font-size: 0.85rem; padding: 0.45rem 0.6rem; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-body); color: var(--text-main);">
                    </div>
                </div>
            </details>

            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Primary (Default) Menu Language:') }}
                </label>
                <select name="default_locale" class="form-control" style="width: 100%; padding: 0.65rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-main);">
                    @foreach($allSystemLanguages as $lang)
                        <option value="{{ $lang->code }}" {{ $lang->code === $defaultLangCode ? 'selected' : '' }}>
                            {{ $lang->flag }} {{ $lang->name }} ({{ $lang->code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="document.getElementById('langSettingsModal').style.display='none';" class="btn" style="border-color: var(--border-color);">
                    {{ __('Cancel') }}
                </button>
                <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.4rem;">
                    <i class="fa-solid fa-check"></i> {{ __('Save Preferences') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
