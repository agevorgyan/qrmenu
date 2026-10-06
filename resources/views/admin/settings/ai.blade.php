@extends('layouts.app')

@section('title', __('AI Settings & AI Waiter') . ' - ' . $vendor->name)

@section('content')
<div style="max-width: 1100px; margin: 0 auto; width: 100%; box-sizing: border-box;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
        <div style="min-width: 0; flex: 1;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.85rem;">
                <a href="{{ route('admin.settings.index') }}" style="color: var(--text-muted); text-decoration: none; display: flex; align-items: center; gap: 0.35rem;">
                    <i class="fa-solid fa-arrow-left"></i> {{ __('Settings') }}
                </a>
                <span style="color: var(--text-muted);">/</span>
                <span style="color: var(--primary); font-weight: 700;">{{ __('AI Settings & Models') }}</span>
            </div>

            <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <span style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(236, 72, 153, 0.2)); color: #8b5cf6; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; box-shadow: 0 4px 12px rgba(139, 92, 246, 0.25);">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                </span>
                <span>{{ __('AI Waiter & Culinary Intelligence') }}</span>
                <span style="font-size: 0.72rem; font-weight: 700; background: linear-gradient(135deg, #8b5cf6, #ec4899); color: #fff; padding: 0.2rem 0.6rem; border-radius: 999px; letter-spacing: 0.04em;">PRO SUITE</span>
            </h1>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0 0; word-break: break-word;">
                {{ __('Manage the AI waiter, priority dishes and ingredients, question sequencing, and AI providers:') }}
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('admin.settings.index') }}" class="btn btn-secondary" style="display: flex; align-items: center; gap: 0.5rem; text-decoration: none; border-radius: 12px; font-weight: 600; font-size: 0.88rem;">
                <i class="fa-solid fa-sliders"></i> {{ __('General Settings') }}
            </a>
            <button type="submit" form="aiSettingsForm" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; border-radius: 12px; font-weight: 700; padding: 0.65rem 1.4rem; font-size: 0.92rem; box-shadow: 0 4px 14px rgba(139, 92, 246, 0.35); background: linear-gradient(135deg, #8b5cf6, #7c3aed); border: none; color: #fff;">
                <i class="fa-solid fa-floppy-disk"></i> {{ __('Save All Changes') }}
            </button>
        </div>
    </div>

    @if (session('success'))
        <div style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 14px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; color: #10b981; display: flex; align-items: center; gap: 0.65rem;">
            <i class="fa-solid fa-circle-check" style="font-size: 1.2rem;"></i>
            <span style="font-weight: 600; font-size: 0.92rem;">{{ session('success') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 14px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; color: #ef4444;">
            <div style="font-weight: 700; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                <i class="fa-solid fa-circle-exclamation"></i> {{ __('Attention: Please correct the errors in the form') }}
            </div>
            <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.88rem;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- 1. AI WAITER ANALYTICS & CONVERSION FUNNEL (Specification Section 28) -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; margin-bottom: 1.75rem; box-shadow: var(--shadow-card);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(139, 92, 246, 0.15); color: #8b5cf6; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <div>
                    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0;">
                        {{ __('AI Waiter Analytics & Conversion') }}
                    </h3>
                    <span style="font-size: 0.78rem; color: var(--text-muted);">
                        {{ __('Customer interactions, recommendation views, and completed orders') }}
                    </span>
                </div>
            </div>

            <!-- Language Distribution Badges -->
            <div style="display: flex; gap: 0.4rem; align-items: center;">
                <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">{{ __('Languages:') }}</span>
                <span style="font-size: 0.75rem; font-weight: 700; background: var(--bg-body); border: 1px solid var(--border-color); padding: 0.2rem 0.5rem; border-radius: 6px;">
                    🇦🇲 {{ $analytics['languages']['hy'] ?? 0 }}
                </span>
                <span style="font-size: 0.75rem; font-weight: 700; background: var(--bg-body); border: 1px solid var(--border-color); padding: 0.2rem 0.5rem; border-radius: 6px;">
                    🇬🇧 {{ $analytics['languages']['en'] ?? 0 }}
                </span>
                <span style="font-size: 0.75rem; font-weight: 700; background: var(--bg-body); border: 1px solid var(--border-color); padding: 0.2rem 0.5rem; border-radius: 6px;">
                    🇷🇺 {{ $analytics['languages']['ru'] ?? 0 }}
                </span>
            </div>
        </div>

        <!-- 4 Metric Cards Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
            <!-- Total Sessions -->
            <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem;">
                <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem;">
                    {{ __('AI Sessions') }}
                </div>
                <div style="font-family: 'Outfit', sans-serif; font-size: 1.65rem; font-weight: 800; color: var(--text-main);">
                    {{ number_format($analytics['total_sessions']) }}
                </div>
                <div style="font-size: 0.75rem; color: #8b5cf6; margin-top: 0.25rem; font-weight: 600;">
                    <i class="fa-solid fa-users"></i> {{ __('All scans') }}
                </div>
            </div>

            <!-- Recommendations Viewed -->
            <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem;">
                <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem;">
                    {{ __('Recommendations Viewed') }}
                </div>
                <div style="font-family: 'Outfit', sans-serif; font-size: 1.65rem; font-weight: 800; color: #3b82f6;">
                    {{ number_format($analytics['recommended_count']) }}
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                    {{ $analytics['total_sessions'] > 0 ? round(($analytics['recommended_count'] / $analytics['total_sessions']) * 100, 1) : 0 }}% {{ __('funnel from questions') }}
                </div>
            </div>

            <!-- Added to Cart -->
            <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem;">
                <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem;">
                    {{ __('Added to Cart') }}
                </div>
                <div style="font-family: 'Outfit', sans-serif; font-size: 1.65rem; font-weight: 800; color: #10b981;">
                    {{ number_format($analytics['added_to_cart_count']) }}
                </div>
                <div style="font-size: 0.75rem; color: #10b981; margin-top: 0.25rem; font-weight: 600;">
                    <i class="fa-solid fa-cart-shopping"></i> {{ __('Active interest') }}
                </div>
            </div>

            <!-- Orders & Revenue -->
            <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem;">
                <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem;">
                    {{ __('AI Orders & Revenue') }}
                </div>
                <div style="font-family: 'Outfit', sans-serif; font-size: 1.65rem; font-weight: 800; color: #f59e0b;">
                    {{ number_format($analytics['total_revenue']) }} <span style="font-size: 0.95rem;">{{ $vendor->currency }}</span>
                </div>
                <div style="font-size: 0.75rem; color: #f59e0b; margin-top: 0.25rem; font-weight: 600;">
                    {{ $analytics['ordered_count'] }} {{ __('orders.orders') }} • {{ $analytics['conversion_rate'] }}% {{ __('Conversion') }}
                </div>
            </div>
        </div>

        <!-- Conversion Funnel Bar -->
        <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem;">
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.65rem; text-transform: uppercase; letter-spacing: 0.04em;">
                {{ __('Conversion Funnel') }}
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 140px; background: rgba(139, 92, 246, 0.1); border-left: 3px solid #8b5cf6; padding: 0.5rem 0.75rem; border-radius: 8px;">
                    <div style="font-size: 0.72rem; color: var(--text-muted);">1. QR Scan</div>
                    <div style="font-size: 0.95rem; font-weight: 800; color: var(--text-main);">{{ $analytics['total_sessions'] }}</div>
                </div>
                <i class="fa-solid fa-arrow-right" style="color: var(--text-muted); font-size: 0.75rem;"></i>
                <div style="flex: 1; min-width: 140px; background: rgba(59, 130, 246, 0.1); border-left: 3px solid #3b82f6; padding: 0.5rem 0.75rem; border-radius: 8px;">
                    <div style="font-size: 0.72rem; color: var(--text-muted);">2. Recommendations</div>
                    <div style="font-size: 0.95rem; font-weight: 800; color: var(--text-main);">{{ $analytics['recommended_count'] }}</div>
                </div>
                <i class="fa-solid fa-arrow-right" style="color: var(--text-muted); font-size: 0.75rem;"></i>
                <div style="flex: 1; min-width: 140px; background: rgba(16, 185, 129, 0.1); border-left: 3px solid #10b981; padding: 0.5rem 0.75rem; border-radius: 8px;">
                    <div style="font-size: 0.72rem; color: var(--text-muted);">3. Cart</div>
                    <div style="font-size: 0.95rem; font-weight: 800; color: var(--text-main);">{{ $analytics['added_to_cart_count'] }}</div>
                </div>
                <i class="fa-solid fa-arrow-right" style="color: var(--text-muted); font-size: 0.75rem;"></i>
                <div style="flex: 1; min-width: 140px; background: rgba(245, 158, 11, 0.1); border-left: 3px solid #f59e0b; padding: 0.5rem 0.75rem; border-radius: 8px;">
                    <div style="font-size: 0.72rem; color: var(--text-muted);">4. Order</div>
                    <div style="font-size: 0.95rem; font-weight: 800; color: var(--text-main);">{{ $analytics['ordered_count'] }} ({{ $analytics['conversion_rate'] }}%)</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. MAIN CONFIGURATION TABS -->
    <div style="display: flex; gap: 0.5rem; margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; overflow-x: auto;">
        <button type="button" class="tab-btn active-tab" onclick="switchAiTab('tab-general', this)">
            <i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('AI Waiter Assistant') }}
        </button>
        <button type="button" class="tab-btn" onclick="switchAiTab('tab-priorities', this)">
            <i class="fa-solid fa-award"></i> {{ __('Priorities & Ranking') }}
        </button>
        <button type="button" class="tab-btn" onclick="switchAiTab('tab-questions', this)">
            <i class="fa-solid fa-list-check"></i> {{ __('Question Engine') }}
        </button>
        <button type="button" class="tab-btn" onclick="switchAiTab('tab-provider', this)">
            <i class="fa-solid fa-microchip"></i> {{ __('AI Provider & API Engine') }}
        </button>
    </div>

    <form action="{{ route('admin.settings.ai.update') }}" method="POST" id="aiSettingsForm">
        @csrf

        <!-- ================= TAB 1: GENERAL AI WAITER SETTINGS ================= -->
        <div id="tab-general" class="ai-tab-pane" style="display: block;">
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1.2rem;">
                    <div>
                        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-robot" style="color: #8b5cf6;"></i>
                            <span>{{ __('AI Waiter Core Settings') }}</span>
                        </h3>
                        <p style="margin: 0.25rem 0 0 0; font-size: 0.85rem; color: var(--text-muted);">
                            {{ __('Configure persona name, welcome message, languages, and interaction mode') }}
                        </p>
                    </div>

                    <label style="display: inline-flex; align-items: center; gap: 0.75rem; cursor: pointer; background: var(--bg-body); border: 1px solid var(--border-color); padding: 0.55rem 1rem; border-radius: 12px;">
                        <input type="checkbox" name="ai_waiter_enabled" value="1" id="aiWaiterToggle" {{ old('ai_waiter_enabled', $vendor->ai_waiter_enabled) ? 'checked' : '' }} style="width: 18px; height: 18px; accent-color: #8b5cf6; cursor: pointer;">
                        <span style="font-size: 0.92rem; font-weight: 700; color: var(--text-main);">
                            {{ __('Enable AI Waiter') }}
                        </span>
                    </label>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
                    <!-- Name -->
                    <div class="form-group">
                        <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                            {{ __('AI Waiter Name / Persona') }}
                        </label>
                        <input type="text" name="ai_waiter_name" id="aiWaiterNameInput" value="{{ old('ai_waiter_name', $vendor->ai_waiter_name ?? 'AI Waiter') }}" class="form-control" style="width: 100%; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem; font-size: 0.95rem; font-weight: 600;">
                        <small style="color: var(--text-muted); font-size: 0.75rem; display: block; margin-top: 0.35rem;">
                            {{ __('The AI will introduce itself with this name during greetings and consultations') }}
                        </small>
                    </div>

                    <!-- Personality / Tone -->
                    <div class="form-group">
                        <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                            {{ __('AI Waiter Personality / Tone') }}
                        </label>
                        <select name="ai_waiter_personality" class="form-control" style="width: 100%; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem; font-size: 0.92rem; font-weight: 600;">
                            <option value="friendly" {{ ($aiWaiterConfig['personality'] ?? '') === 'friendly' ? 'selected' : '' }}>😊 Friendly & Warm (Default)</option>
                            <option value="professional" {{ ($aiWaiterConfig['personality'] ?? '') === 'professional' ? 'selected' : '' }}>🎩 Professional & Courteous</option>
                            <option value="sommelier" {{ ($aiWaiterConfig['personality'] ?? '') === 'sommelier' ? 'selected' : '' }}>🍷 Sommelier & Gourmet Expert</option>
                            <option value="concise" {{ ($aiWaiterConfig['personality'] ?? '') === 'concise' ? 'selected' : '' }}>⚡ Concise & Fast</option>
                        </select>
                        <small style="color: var(--text-muted); font-size: 0.75rem; display: block; margin-top: 0.35rem;">
                            {{ __('Defines the conversational tone for recommendations and dish commentary') }}
                        </small>
                    </div>
                </div>

                <!-- Custom Welcome Greeting -->
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                        {{ __('Custom Welcome Message (Intro)') }}
                    </label>
                    <textarea name="ai_waiter_welcome_text" id="aiWaiterWelcomeInput" rows="2" class="form-control" placeholder="e.g. Welcome! I am your AI waiter. I will help you choose exactly what you like:" style="width: 100%; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.85rem 1rem; font-size: 0.92rem; resize: vertical;">{{ old('ai_waiter_welcome_text', $vendor->ai_waiter_welcome_text) }}</textarea>
                    <small style="color: var(--text-muted); font-size: 0.75rem; display: block; margin-top: 0.35rem;">
                        {{ __('If empty, the system will use the default greeting for the active language') }}
                    </small>
                </div>

                <!-- Languages & Feature Checkboxes -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
                    <!-- Allowed Languages -->
                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.15rem;">
                        <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.65rem;">
                            <i class="fa-solid fa-globe" style="color: #8b5cf6;"></i> {{ __('Available Languages for AI') }}
                        </label>
                        <div style="display: flex; gap: 0.65rem; flex-wrap: wrap;">
                            @php
                                $configuredLangs = $aiWaiterConfig['languages'] ?? $vendor->getSupportedLanguageCodes();
                                $allSupportedLangs = $vendor->getSupportedLanguages();
                            @endphp
                            @foreach ($allSupportedLangs as $slang)
                                @php
                                    $scode = strtolower($slang['code'] ?? '');
                                    $sname = $slang['native_name'] ?? ($slang['name'] ?? strtoupper($scode));
                                    $sflag = $slang['flag'] ?? '🌐';
                                @endphp
                                <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.88rem; font-weight: 600; cursor: pointer;">
                                    <input type="checkbox" name="ai_waiter_languages[]" value="{{ $scode }}" {{ in_array($scode, $configuredLangs, true) ? 'checked' : '' }} style="accent-color: #8b5cf6;">
                                    <span>{{ $sflag }} {{ $sname }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <!-- Behavior Toggles -->
                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.15rem; display: flex; flex-direction: column; gap: 0.65rem;">
                        <label style="display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; font-weight: 600; cursor: pointer;">
                            <input type="checkbox" name="auto_popup" value="1" {{ !empty($aiWaiterConfig['auto_popup']) ? 'checked' : '' }} style="accent-color: #8b5cf6;">
                            <span>{{ __('Automatically show Intro on first QR scan') }}</span>
                        </label>
                        <label style="display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; font-weight: 600; cursor: pointer;">
                            <input type="checkbox" name="free_text_enabled" value="1" {{ !empty($aiWaiterConfig['free_text_enabled']) ? 'checked' : '' }} style="accent-color: #8b5cf6;">
                            <span>{{ __('Allow Free-Text Responses ("Or type your own preference")') }}</span>
                        </label>
                        <label style="display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; font-weight: 600; cursor: pointer;">
                            <input type="checkbox" name="ai_chat_enabled" value="1" {{ !empty($aiWaiterConfig['ai_chat_enabled']) ? 'checked' : '' }} style="accent-color: #8b5cf6;">
                            <span>{{ __('Enable Interactive AI Chat ("💬 Ask the AI waiter")') }}</span>
                        </label>
                    </div>
                </div>

                <!-- Live Widget Simulation Preview Card -->
                <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.85rem;">
                        <span style="font-weight: 700; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 0.4rem;">
                            <i class="fa-solid fa-eye" style="color: #8b5cf6;"></i> {{ __('Live Mobile Widget Preview') }}
                        </span>
                        <span style="font-size: 0.72rem; background: rgba(16, 185, 129, 0.15); color: #10b981; font-weight: 700; padding: 0.15rem 0.45rem; border-radius: 6px;">
                            {{ __('Storefront Preview') }}
                        </span>
                    </div>

                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1.25rem; max-width: 480px; margin: 0 auto; box-shadow: 0 4px 16px rgba(0,0,0,0.06);">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                            <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #8b5cf6, #ec4899); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; box-shadow: 0 2px 8px rgba(139, 92, 246, 0.4);">
                                <i class="fa-solid fa-wand-magic-sparkles"></i>
                            </div>
                            <div>
                                <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-main);" id="previewWaiterName">
                                    {{ $vendor->ai_waiter_name ?? 'AI Waiter' }}
                                </div>
                                <div style="font-size: 0.75rem; color: #10b981; display: flex; align-items: center; gap: 0.3rem;">
                                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span>
                                    <span>{{ __('Online • Ready to recommend') }}</span>
                                </div>
                            </div>
                        </div>

                        <div style="background: var(--bg-body); border-radius: 12px; padding: 0.85rem 1rem; font-size: 0.88rem; color: var(--text-main); line-height: 1.45; border-left: 3px solid #8b5cf6;" id="previewWelcomeMessage">
                            {{ $vendor->ai_waiter_welcome_text ?: 'Welcome! I am your AI waiter. I will help you choose exactly what you like.' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= TAB 2: PRIORITIES & RANKING RULES (Sections 46-58) ================= -->
        <div id="tab-priorities" class="ai-tab-pane" style="display: none;">
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
                <div style="margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem;">
                    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-award" style="color: #f59e0b;"></i>
                        <span>{{ __('Restaurant Priorities & Ranking Engine') }}</span>
                    </h3>
                    <p style="margin: 0.25rem 0 0 0; font-size: 0.85rem; color: var(--text-muted);">
                        {{ __('«AI understands the customer, but the restaurant controls what it wants AI to recommend.»') }}
                    </p>
                </div>

                <!-- Priority 1: Explicitly Promoted Products Table -->
                <div style="margin-bottom: 2rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; flex-wrap: wrap; gap: 0.5rem;">
                        <div>
                            <strong style="font-size: 0.95rem; color: var(--text-main); display: flex; align-items: center; gap: 0.4rem;">
                                <span style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; padding: 0.15rem 0.5rem; border-radius: 6px; font-size: 0.75rem;">Priority 1</span>
                                {{ __('Promoted Products (High Priority)') }}
                            </strong>
                            <small style="color: var(--text-muted); font-size: 0.78rem; display: block;">
                                {{ __('These dishes receive highest recommendation priority when matching customer preferences:') }}
                            </small>
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="addPromotedProductRow()" style="border-radius: 10px; font-size: 0.82rem; padding: 0.4rem 0.85rem;">
                            <i class="fa-solid fa-plus"></i> {{ __('Add Dish') }}
                        </button>
                    </div>

                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; overflow: hidden;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem; text-align: left;">
                            <thead>
                                <tr style="background: rgba(0,0,0,0.03); border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase;">
                                    <th style="padding: 0.75rem 1rem;">{{ __('Product') }}</th>
                                    <th style="padding: 0.75rem 1rem; width: 140px;">{{ __('Priority (1–100)') }}</th>
                                    <th style="padding: 0.75rem 1rem; width: 100px; text-align: center;">{{ __('Active') }}</th>
                                    <th style="padding: 0.75rem 1rem; width: 60px;"></th>
                                </tr>
                            </thead>
                            <tbody id="promotedProductsTbody">
                                @php
                                    $promotedList = $aiWaiterConfig['promoted_products'] ?? [];
                                @endphp
                                @forelse($promotedList as $idx => $p)
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 0.65rem 1rem;">
                                            <select name="promoted_products[{{ $idx }}][product_id]" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1.5px solid var(--border-color); color: var(--text-main); font-size: 0.88rem; font-weight: 600; padding: 0.6rem 0.85rem; border-radius: 10px;">
                                                @foreach($products as $prod)
                                                    <option value="{{ $prod->id }}" {{ $p['product_id'] == $prod->id ? 'selected' : '' }}>
                                                        {{ $prod->name }} ({{ number_format($prod->price) }} {{ $vendor->currency }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td style="padding: 0.65rem 1rem;">
                                            <input type="number" name="promoted_products[{{ $idx }}][priority]" value="{{ $p['priority'] ?? 90 }}" min="1" max="100" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1.5px solid var(--border-color); color: var(--text-main); font-size: 0.92rem; font-weight: 700; text-align: center; padding: 0.6rem 0.5rem; border-radius: 10px;">
                                        </td>
                                        <td style="padding: 0.65rem 1rem; text-align: center;">
                                            <input type="checkbox" name="promoted_products[{{ $idx }}][active]" value="1" {{ !empty($p['active']) ? 'checked' : '' }} style="width: 20px; height: 20px; accent-color: #8b5cf6; cursor: pointer;">
                                        </td>
                                        <td style="padding: 0.65rem 1rem; text-align: right;">
                                            <button type="button" onclick="this.closest('tr').remove()" style="background: rgba(239, 68, 68, 0.1); border: none; color: #ef4444; width: 34px; height: 34px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.95rem; transition: background 0.15s ease;">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr id="emptyPromotedRow">
                                        <td colspan="4" style="padding: 1.5rem; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                                            {{ __('No promoted dishes added yet. Click "Add Dish":') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Priority 2: Preferred Ingredients Table -->
                <div style="margin-bottom: 2rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; flex-wrap: wrap; gap: 0.5rem;">
                        <div>
                            <strong style="font-size: 0.95rem; color: var(--text-main); display: flex; align-items: center; gap: 0.4rem;">
                                <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; padding: 0.15rem 0.5rem; border-radius: 6px; font-size: 0.75rem;">Priority 2</span>
                                {{ __('Preferred Target Ingredients') }}
                            </strong>
                            <small style="color: var(--text-muted); font-size: 0.78rem; display: block;">
                                {{ __('Dishes containing these ingredients will receive higher promotion by AI:') }}
                            </small>
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="addPreferredIngredientRow()" style="border-radius: 10px; font-size: 0.82rem; padding: 0.4rem 0.85rem;">
                            <i class="fa-solid fa-plus"></i> {{ __('Add Ingredient') }}
                        </button>
                    </div>

                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; overflow: hidden;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem; text-align: left;">
                            <thead>
                                <tr style="background: rgba(0,0,0,0.03); border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase;">
                                    <th style="padding: 0.75rem 1rem;">{{ __('Ingredient Name') }}</th>
                                    <th style="padding: 0.75rem 1rem; width: 140px;">{{ __('Priority (1–100)') }}</th>
                                    <th style="padding: 0.75rem 1rem; width: 100px; text-align: center;">{{ __('Active') }}</th>
                                    <th style="padding: 0.75rem 1rem; width: 60px;"></th>
                                </tr>
                            </thead>
                            <tbody id="preferredIngredientsTbody">
                                @php
                                    $ingredientList = $vendor->getAiPreferredIngredients();
                                @endphp
                                @forelse($ingredientList as $idx => $ing)
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 0.65rem 1rem;">
                                            <input type="text" name="preferred_ingredients[{{ $idx }}][ingredient]" value="{{ $ing['ingredient'] }}" placeholder="e.g. Beef, Chicken, Truffle, Salmon..." class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1.5px solid var(--border-color); color: var(--text-main); font-size: 0.9rem; font-weight: 600; padding: 0.6rem 0.85rem; border-radius: 10px;">
                                        </td>
                                        <td style="padding: 0.65rem 1rem;">
                                            <input type="number" name="preferred_ingredients[{{ $idx }}][priority]" value="{{ $ing['priority'] ?? 80 }}" min="1" max="100" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1.5px solid var(--border-color); color: var(--text-main); font-size: 0.92rem; font-weight: 700; text-align: center; padding: 0.6rem 0.5rem; border-radius: 10px;">
                                        </td>
                                        <td style="padding: 0.65rem 1rem; text-align: center;">
                                            <input type="checkbox" name="preferred_ingredients[{{ $idx }}][active]" value="1" {{ !empty($ing['active']) ? 'checked' : '' }} style="width: 20px; height: 20px; accent-color: #8b5cf6; cursor: pointer;">
                                        </td>
                                        <td style="padding: 0.65rem 1rem; text-align: right;">
                                            <button type="button" onclick="this.closest('tr').remove()" style="background: rgba(239, 68, 68, 0.1); border: none; color: #ef4444; width: 34px; height: 34px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.95rem; transition: background 0.15s ease;">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr id="emptyIngredientRow">
                                        <td colspan="4" style="padding: 1.5rem; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                                            {{ __('No preferred ingredients added yet. Click "Add Ingredient":') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Priority 3 & 4: Product Groups & Tags Priorities -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
                    <!-- Group Priorities -->
                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.2rem;">
                        <strong style="font-size: 0.9rem; color: var(--text-main); display: block; margin-bottom: 0.75rem;">
                            {{ __('Dish Category Priorities (AI Groups)') }}
                        </strong>
                        @php
                            $gp = $aiWaiterConfig['group_priorities'] ?? [];
                        @endphp
                        <div style="display: flex; flex-direction: column; gap: 0.65rem; font-size: 0.85rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: 600; color: var(--text-main);">⭐ Bestseller</span>
                                <input type="number" name="group_priorities[bestseller]" value="{{ $gp['bestseller'] ?? 90 }}" min="1" max="100" style="width: 75px; text-align: center; font-weight: 700; border-radius: 10px; border: 1.5px solid var(--border-color); background: var(--bg-card); color: var(--text-main); padding: 0.45rem 0.5rem; font-size: 0.92rem;">
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: 600; color: var(--text-main);">👨‍🍳 Chef Recommendation</span>
                                <input type="number" name="group_priorities[chef_recommendation]" value="{{ $gp['chef_recommendation'] ?? 85 }}" min="1" max="100" style="width: 75px; text-align: center; font-weight: 700; border-radius: 10px; border: 1.5px solid var(--border-color); background: var(--bg-card); color: var(--text-main); padding: 0.45rem 0.5rem; font-size: 0.92rem;">
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: 600; color: var(--text-main);">💎 High Margin</span>
                                <input type="number" name="group_priorities[high_margin]" value="{{ $gp['high_margin'] ?? 75 }}" min="1" max="100" style="width: 75px; text-align: center; font-weight: 700; border-radius: 10px; border: 1.5px solid var(--border-color); background: var(--bg-card); color: var(--text-main); padding: 0.45rem 0.5rem; font-size: 0.92rem;">
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: 600; color: var(--text-main);">✨ New Products</span>
                                <input type="number" name="group_priorities[new_products]" value="{{ $gp['new_products'] ?? 65 }}" min="1" max="100" style="width: 75px; text-align: center; font-weight: 700; border-radius: 10px; border: 1.5px solid var(--border-color); background: var(--bg-card); color: var(--text-main); padding: 0.45rem 0.5rem; font-size: 0.92rem;">
                            </div>
                        </div>
                    </div>

                    <!-- Configurable Scoring Weights (Section 49) -->
                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.2rem;">
                        <strong style="font-size: 0.9rem; color: var(--text-main); display: block; margin-bottom: 0.75rem;">
                            {{ __('Scoring Engine Weights (%)') }}
                        </strong>
                        @php
                            $sw = $vendor->getAiScoringWeights();
                        @endphp
                        <div style="display: flex; flex-direction: column; gap: 0.55rem; font-size: 0.82rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: 600; color: var(--text-main);">Restaurant Explicit Priority</span>
                                <div style="display: flex; align-items: center; gap: 0.35rem;">
                                    <input type="number" name="scoring_weights[restaurant_priority]" value="{{ $sw['restaurant_priority'] ?? 30 }}" min="0" max="100" style="width: 65px; text-align: center; font-weight: 700; border-radius: 10px; border: 1.5px solid var(--border-color); background: var(--bg-card); color: var(--text-main); padding: 0.45rem 0.5rem; font-size: 0.92rem;">
                                    <span style="font-weight: 700; color: var(--text-muted);">%</span>
                                </div>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: 600; color: var(--text-main);">Preferred Ingredient Match</span>
                                <div style="display: flex; align-items: center; gap: 0.35rem;">
                                    <input type="number" name="scoring_weights[preferred_ingredient]" value="{{ $sw['preferred_ingredient'] ?? 20 }}" min="0" max="100" style="width: 65px; text-align: center; font-weight: 700; border-radius: 10px; border: 1.5px solid var(--border-color); background: var(--bg-card); color: var(--text-main); padding: 0.45rem 0.5rem; font-size: 0.92rem;">
                                    <span style="font-weight: 700; color: var(--text-muted);">%</span>
                                </div>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: 600; color: var(--text-main);">Customer Preference Match</span>
                                <div style="display: flex; align-items: center; gap: 0.35rem;">
                                    <input type="number" name="scoring_weights[customer_preference]" value="{{ $sw['customer_preference'] ?? 25 }}" min="0" max="100" style="width: 65px; text-align: center; font-weight: 700; border-radius: 10px; border: 1.5px solid var(--border-color); background: var(--bg-card); color: var(--text-main); padding: 0.45rem 0.5rem; font-size: 0.92rem;">
                                    <span style="font-weight: 700; color: var(--text-muted);">%</span>
                                </div>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: 600; color: var(--text-main);">Dietary Compatibility</span>
                                <div style="display: flex; align-items: center; gap: 0.35rem;">
                                    <input type="number" name="scoring_weights[dietary_compatibility]" value="{{ $sw['dietary_compatibility'] ?? 10 }}" min="0" max="100" style="width: 65px; text-align: center; font-weight: 700; border-radius: 10px; border: 1.5px solid var(--border-color); background: var(--bg-card); color: var(--text-main); padding: 0.45rem 0.5rem; font-size: 0.92rem;">
                                    <span style="font-weight: 700; color: var(--text-muted);">%</span>
                                </div>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: 600; color: var(--text-main);">Taste & Flavor / Spiciness</span>
                                <div style="display: flex; align-items: center; gap: 0.35rem;">
                                    <input type="number" name="scoring_weights[taste_spiciness]" value="{{ $sw['taste_spiciness'] ?? 5 }}" min="0" max="100" style="width: 65px; text-align: center; font-weight: 700; border-radius: 10px; border: 1.5px solid var(--border-color); background: var(--bg-card); color: var(--text-main); padding: 0.45rem 0.5rem; font-size: 0.92rem;">
                                    <span style="font-weight: 700; color: var(--text-muted);">%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= TAB 3: QUESTION ENGINE ================= -->
        <div id="tab-questions" class="ai-tab-pane" style="display: none;">
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
                <div style="margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem;">
                    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-list-check" style="color: #8b5cf6;"></i>
                        <span>{{ __('AI Question Engine Configuration') }}</span>
                    </h3>
                    <p style="margin: 0.25rem 0 0 0; font-size: 0.85rem; color: var(--text-muted);">
                        {{ __('Manage interactive questionnaire prompts, priorities, and status') }}
                    </p>
                </div>

                @php
                    $qConfig = $aiWaiterConfig['questions'] ?? [];
                @endphp

                <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                    <!-- Question 1: Mood -->
                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <span style="font-size: 1.5rem;">🍽️</span>
                            <div>
                                <strong style="font-size: 0.95rem; color: var(--text-main); display: block;">1. Mood</strong>
                                <small style="color: var(--text-muted); font-size: 0.78rem;">"What mood are you in today?" (Hearty, Light, Spicy, Fresh, Sweet, Surprise me)</small>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; font-weight: 600; color: var(--text-muted);">
                                <span>Priority:</span>
                                <input type="number" name="questions[mood][priority]" value="{{ $qConfig['mood']['priority'] ?? 1 }}" min="1" max="10" style="width: 55px; text-align: center; font-weight: 700; border-radius: 8px; border: 1.5px solid var(--border-color); background: var(--bg-card); color: var(--text-main); padding: 0.35rem 0.4rem; font-size: 0.9rem;">
                            </div>
                            <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; font-weight: 700; cursor: pointer;">
                                <input type="checkbox" name="questions[mood][enabled]" value="1" {{ !empty($qConfig['mood']['enabled']) ? 'checked' : '' }} style="width: 18px; height: 18px; accent-color: #8b5cf6; cursor: pointer;">
                                <span>{{ __('Active') }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Question 2: Preference / Protein -->
                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <span style="font-size: 1.5rem;">🥩</span>
                            <div>
                                <strong style="font-size: 0.95rem; color: var(--text-main); display: block;">2. Preference / Protein</strong>
                                <small style="color: var(--text-muted); font-size: 0.78rem;">"What do you prefer?" (Beef, Chicken, Seafood, Vegetarian, Anything)</small>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; font-weight: 600; color: var(--text-muted);">
                                <span>Priority:</span>
                                <input type="number" name="questions[preference][priority]" value="{{ $qConfig['preference']['priority'] ?? 2 }}" min="1" max="10" style="width: 55px; text-align: center; font-weight: 700; border-radius: 8px; border: 1.5px solid var(--border-color); background: var(--bg-card); color: var(--text-main); padding: 0.35rem 0.4rem; font-size: 0.9rem;">
                            </div>
                            <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; font-weight: 700; cursor: pointer;">
                                <input type="checkbox" name="questions[preference][enabled]" value="1" {{ !empty($qConfig['preference']['enabled']) ? 'checked' : '' }} style="width: 18px; height: 18px; accent-color: #8b5cf6; cursor: pointer;">
                                <span>{{ __('Active') }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Question 3: Spiciness -->
                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <span style="font-size: 1.5rem;">🌶️</span>
                            <div>
                                <strong style="font-size: 0.95rem; color: var(--text-main); display: block;">3. Spiciness</strong>
                                <small style="color: var(--text-muted); font-size: 0.78rem;">"How spicy do you like it?" (Not spicy, Mild, Medium, Extra spicy)</small>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; font-weight: 600; color: var(--text-muted);">
                                <span>Priority:</span>
                                <input type="number" name="questions[spiciness][priority]" value="{{ $qConfig['spiciness']['priority'] ?? 3 }}" min="1" max="10" style="width: 55px; text-align: center; font-weight: 700; border-radius: 8px; border: 1.5px solid var(--border-color); background: var(--bg-card); color: var(--text-main); padding: 0.35rem 0.4rem; font-size: 0.9rem;">
                            </div>
                            <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; font-weight: 700; cursor: pointer;">
                                <input type="checkbox" name="questions[spiciness][enabled]" value="1" {{ !empty($qConfig['spiciness']['enabled']) ? 'checked' : '' }} style="width: 18px; height: 18px; accent-color: #8b5cf6; cursor: pointer;">
                                <span>{{ __('Active') }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Question 4: Occasion -->
                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <span style="font-size: 1.5rem;">🎉</span>
                            <div>
                                <strong style="font-size: 0.95rem; color: var(--text-main); display: block;">4. Occasion</strong>
                                <small style="color: var(--text-muted); font-size: 0.78rem;">"What is the occasion today?" (Solo, Date, Friends, Family, Celebration)</small>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; font-weight: 600; color: var(--text-muted);">
                                <span>Priority:</span>
                                <input type="number" name="questions[occasion][priority]" value="{{ $qConfig['occasion']['priority'] ?? 4 }}" min="1" max="10" style="width: 55px; text-align: center; font-weight: 700; border-radius: 8px; border: 1.5px solid var(--border-color); background: var(--bg-card); color: var(--text-main); padding: 0.35rem 0.4rem; font-size: 0.9rem;">
                            </div>
                            <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; font-weight: 700; cursor: pointer;">
                                <input type="checkbox" name="questions[occasion][enabled]" value="1" {{ !empty($qConfig['occasion']['enabled']) ? 'checked' : '' }} style="width: 18px; height: 18px; accent-color: #8b5cf6; cursor: pointer;">
                                <span>{{ __('Active') }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Question 5: Budget -->
                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <span style="font-size: 1.5rem;">💵</span>
                            <div>
                                <strong style="font-size: 0.95rem; color: var(--text-main); display: block;">5. Budget</strong>
                                <small style="color: var(--text-muted); font-size: 0.78rem;">"What budget range do you have in mind?" (Budget, Moderate, Premium, Any)</small>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; font-weight: 600; color: var(--text-muted);">
                                <span>Priority:</span>
                                <input type="number" name="questions[budget][priority]" value="{{ $qConfig['budget']['priority'] ?? 5 }}" min="1" max="10" style="width: 55px; text-align: center; font-weight: 700; border-radius: 8px; border: 1.5px solid var(--border-color); background: var(--bg-card); color: var(--text-main); padding: 0.35rem 0.4rem; font-size: 0.9rem;">
                            </div>
                            <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; font-weight: 700; cursor: pointer;">
                                <input type="checkbox" name="questions[budget][enabled]" value="1" {{ !empty($qConfig['budget']['enabled']) ? 'checked' : '' }} style="width: 18px; height: 18px; accent-color: #8b5cf6; cursor: pointer;">
                                <span>{{ __('Active') }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Question 6: Drink -->
                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <span style="font-size: 1.5rem;">🍹</span>
                            <div>
                                <strong style="font-size: 0.95rem; color: var(--text-main); display: block;">6. Drink</strong>
                                <small style="color: var(--text-muted); font-size: 0.78rem;">"What would you like to drink?" (Water, Lemonade, Cocktail, Wine, Beer, You choose)</small>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; font-weight: 600; color: var(--text-muted);">
                                <span>Priority:</span>
                                <input type="number" name="questions[drink][priority]" value="{{ $qConfig['drink']['priority'] ?? 6 }}" min="1" max="10" style="width: 55px; text-align: center; font-weight: 700; border-radius: 8px; border: 1.5px solid var(--border-color); background: var(--bg-card); color: var(--text-main); padding: 0.35rem 0.4rem; font-size: 0.9rem;">
                            </div>
                            <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; font-weight: 700; cursor: pointer;">
                                <input type="checkbox" name="questions[drink][enabled]" value="1" {{ !empty($qConfig['drink']['enabled']) ? 'checked' : '' }} style="width: 18px; height: 18px; accent-color: #8b5cf6; cursor: pointer;">
                                <span>{{ __('Active') }}</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= TAB 4: AI PROVIDER & API ENGINE ================= -->
        <div id="tab-provider" class="ai-tab-pane" style="display: none;">
            @php
                $activeProviderKey = old('ai_provider', $vendor->getAiProvider());
                $activeModel = old('ai_model', $vendor->getAiModel());
                $hasCustomKey = $vendor->hasCustomAiConfig();
                $maskedApiKey = app(\App\Services\CredentialService::class)->mask($vendor, 'ai', 'api_key');
                $savedBaseUrl = $vendor->getAiBaseUrl();
            @endphp

            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem;">
                    <div>
                        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-microchip" style="color: #4285F4;"></i>
                            <span>{{ __('AI Provider & Model Engine') }}</span>
                        </h3>
                        <p style="margin: 0.25rem 0 0 0; font-size: 0.85rem; color: var(--text-muted);">
                            {{ __('Select the AI engine that generates recommendations and sommelier tasting notes') }}
                        </p>
                    </div>

                    <!-- Live Connection Test Button -->
                    <button type="button" id="btnTestConnection" class="btn btn-secondary" style="border-radius: 12px; font-weight: 700; font-size: 0.88rem; padding: 0.6rem 1.15rem; display: inline-flex; align-items: center; gap: 0.4rem; border-color: rgba(139, 92, 246, 0.4); color: #8b5cf6;">
                        <i class="fa-solid fa-bolt" id="testIcon"></i>
                        <span id="testBtnText">{{ __('Test Connection') }}</span>
                    </button>
                </div>

                <!-- Test Result Notification Area -->
                <div id="testResultBox" style="display: none; border-radius: 12px; padding: 0.85rem 1rem; margin-bottom: 1.25rem; font-size: 0.88rem; font-weight: 600;"></div>

                <!-- Providers Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                    @foreach($providers as $pKey => $pConfig)
                        @php
                            $isSelected = ($activeProviderKey === $pKey);
                            $brandColor = $pConfig['color'] ?? '#8b5cf6';
                        @endphp
                        <label class="provider-card {{ $isSelected ? 'active-provider' : '' }}" style="display: flex; flex-direction: column; justify-content: space-between; border: 2px solid {{ $isSelected ? '#8b5cf6' : 'var(--border-color)' }}; border-radius: 16px; padding: 1.15rem; cursor: pointer; transition: all 0.2s; background: {{ $isSelected ? 'rgba(139, 92, 246, 0.08)' : 'var(--bg-body)' }}; gap: 0.75rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div style="display: flex; align-items: center; gap: 0.65rem;">
                                    <span style="width: 38px; height: 38px; border-radius: 10px; background: {{ $brandColor }}15; color: {{ $brandColor }}; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                                        <i class="{{ $pConfig['icon'] }}"></i>
                                    </span>
                                    <div>
                                        <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-main);">{{ $pConfig['name'] }}</div>
                                        <span style="font-size: 0.68rem; font-weight: 700; color: {{ $brandColor }}; background: {{ $brandColor }}18; padding: 0.1rem 0.4rem; border-radius: 4px;">
                                            {{ $pConfig['badge'] ?? strtoupper($pKey) }}
                                        </span>
                                    </div>
                                </div>
                                <input type="radio" name="ai_provider" value="{{ $pKey }}" {{ $isSelected ? 'checked' : '' }} class="provider-radio" style="accent-color: {{ $brandColor }}; width: 18px; height: 18px; cursor: pointer;">
                            </div>

                            <p style="margin: 0; font-size: 0.8rem; color: var(--text-muted); line-height: 1.4;">
                                {{ $pConfig['description'] }}
                            </p>
                        </label>
                    @endforeach
                </div>

                <!-- Parameters Block -->
                <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.4rem;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr)); gap: 1.25rem;">
                        <!-- Model Selection Dropdown -->
                        <div class="form-group">
                            <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                                {{ __('AI Model (Model Name)') }} <span style="color: #ef4444;">*</span>
                            </label>
                            <select name="ai_model" id="aiModelSelect" class="form-control" style="width: 100%; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem; font-size: 0.95rem; font-weight: 600;">
                                <!-- Populated dynamically -->
                            </select>
                        </div>

                        <!-- Custom Model Input -->
                        <div class="form-group" id="customModelGroup" style="display: none;">
                            <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                                {{ __('Exact Model Identifier (Custom)') }}
                            </label>
                            <input type="text" name="custom_model" id="customModelInput" value="{{ old('custom_model', $activeModel) }}" placeholder="e.g. deepseek-chat, gpt-4o, llama3.3:70b" class="form-control" style="width: 100%; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem; font-size: 0.95rem; font-weight: 600;">
                        </div>

                        <!-- API Key Input -->
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                                <span>{{ __('API Key') }}</span>
                                @if($hasCustomKey)
                                    <span style="font-size: 0.72rem; color: #10b981; font-weight: 700;">
                                        ✓ {{ __('Configured (encrypted)') }}
                                    </span>
                                @endif
                            </label>
                            <div style="position: relative; display: flex; align-items: center;">
                                <input type="password" name="ai_api_key" id="aiApiKeyInput" value="" placeholder="{{ $hasCustomKey ? '•••••••• (' . ($maskedApiKey ?: 'Configured') . ')' : 'Enter custom API key or leave blank for system default' }}" class="form-control" style="width: 100%; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 3rem 0.75rem 1rem; font-size: 0.95rem; font-family: monospace;">
                                <button type="button" id="toggleApiKeyVisibility" style="position: absolute; right: 0.75rem; background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1rem;">
                                    <i class="fa-solid fa-eye" id="eyeIcon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Base URL Input -->
                        <div class="form-group" id="baseUrlGroup" style="grid-column: 1 / -1;">
                            <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                                {{ __('Custom API Base URL (Ollama, vLLM or Proxy)') }}
                            </label>
                            <input type="url" name="ai_base_url" id="aiBaseUrlInput" value="{{ old('ai_base_url', $savedBaseUrl) }}" placeholder="http://localhost:11434/v1" class="form-control" style="width: 100%; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem; font-size: 0.92rem; font-family: monospace;">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sticky Save Bar -->
        <div style="display: flex; justify-content: flex-end; align-items: center; gap: 1rem; padding: 1.25rem 0; margin-bottom: 2rem;">
            <a href="{{ route('admin.settings.index') }}" class="btn btn-secondary" style="border-radius: 12px; padding: 0.75rem 1.4rem; font-weight: 600;">
                {{ __('Cancel') }}
            </a>
            <button type="submit" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; border-radius: 12px; font-weight: 700; padding: 0.75rem 1.75rem; font-size: 0.95rem; box-shadow: 0 4px 14px rgba(139, 92, 246, 0.35); background: linear-gradient(135deg, #8b5cf6, #7c3aed); border: none; color: #fff;">
                <i class="fa-solid fa-floppy-disk"></i> {{ __('Save AI Settings') }}
            </button>
        </div>
    </form>
</div>

<style>
.tab-btn {
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    padding: 0.65rem 1rem;
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--text-muted);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
    transition: all 0.2s;
}
.tab-btn:hover {
    color: var(--text-main);
}
.tab-btn.active-tab {
    color: #8b5cf6;
    border-bottom-color: #8b5cf6;
}

.form-control {
    background: var(--bg-card);
    border: 1.5px solid var(--border-color);
    color: var(--text-main);
    box-sizing: border-box;
    border-radius: 12px;
    padding: 0.65rem 0.9rem;
    font-size: 0.9rem;
    font-weight: 600;
    transition: all 0.2s ease;
}
.form-control:focus {
    border-color: #8b5cf6 !important;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15) !important;
    outline: none;
}
select.form-control {
    appearance: none;
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%238b5cf6'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.85rem center;
    background-size: 1.1rem;
    padding-right: 2.2rem;
    cursor: pointer;
}
</style>

<script>
function switchAiTab(tabId, btn) {
    document.querySelectorAll('.ai-tab-pane').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active-tab'));
    const target = document.getElementById(tabId);
    if (target) target.style.display = 'block';
    if (btn) btn.classList.add('active-tab');
}

// Add Promoted Product Row
let promotedIdx = {{ count($aiWaiterConfig['promoted_products'] ?? []) }};
const productsList = @json($products);
function addPromotedProductRow() {
    const emptyRow = document.getElementById('emptyPromotedRow');
    if (emptyRow) emptyRow.remove();

    const tbody = document.getElementById('promotedProductsTbody');
    const tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid var(--border-color)';

    let opts = '';
    productsList.forEach(p => {
        opts += `<option value="${p.id}">${p.name} (${Number(p.price).toLocaleString()} {{ $vendor->currency }})</option>`;
    });

    tr.innerHTML = `
        <td style="padding: 0.65rem 1rem;">
            <select name="promoted_products[${promotedIdx}][product_id]" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1.5px solid var(--border-color); color: var(--text-main); font-size: 0.88rem; font-weight: 600; padding: 0.6rem 0.85rem; border-radius: 10px;">
                ${opts}
            </select>
        </td>
        <td style="padding: 0.65rem 1rem;">
            <input type="number" name="promoted_products[${promotedIdx}][priority]" value="90" min="1" max="100" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1.5px solid var(--border-color); color: var(--text-main); font-size: 0.92rem; font-weight: 700; text-align: center; padding: 0.6rem 0.5rem; border-radius: 10px;">
        </td>
        <td style="padding: 0.65rem 1rem; text-align: center;">
            <input type="checkbox" name="promoted_products[${promotedIdx}][active]" value="1" checked style="width: 20px; height: 20px; accent-color: #8b5cf6; cursor: pointer;">
        </td>
        <td style="padding: 0.65rem 1rem; text-align: right;">
            <button type="button" onclick="this.closest('tr').remove()" style="background: rgba(239, 68, 68, 0.1); border: none; color: #ef4444; width: 34px; height: 34px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.95rem; transition: background 0.15s ease;">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    promotedIdx++;
}

// Add Preferred Ingredient Row
let ingredientIdx = {{ count($vendor->getAiPreferredIngredients()) }};
function addPreferredIngredientRow() {
    const emptyRow = document.getElementById('emptyIngredientRow');
    if (emptyRow) emptyRow.remove();

    const tbody = document.getElementById('preferredIngredientsTbody');
    const tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid var(--border-color)';

    tr.innerHTML = `
        <td style="padding: 0.65rem 1rem;">
            <input type="text" name="preferred_ingredients[${ingredientIdx}][ingredient]" placeholder="e.g. Beef, Chicken, Truffle, Salmon..." class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1.5px solid var(--border-color); color: var(--text-main); font-size: 0.9rem; font-weight: 600; padding: 0.6rem 0.85rem; border-radius: 10px;">
        </td>
        <td style="padding: 0.65rem 1rem;">
            <input type="number" name="preferred_ingredients[${ingredientIdx}][priority]" value="80" min="1" max="100" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1.5px solid var(--border-color); color: var(--text-main); font-size: 0.92rem; font-weight: 700; text-align: center; padding: 0.6rem 0.5rem; border-radius: 10px;">
        </td>
        <td style="padding: 0.65rem 1rem; text-align: center;">
            <input type="checkbox" name="preferred_ingredients[${ingredientIdx}][active]" value="1" checked style="width: 20px; height: 20px; accent-color: #8b5cf6; cursor: pointer;">
        </td>
        <td style="padding: 0.65rem 1rem; text-align: right;">
            <button type="button" onclick="this.closest('tr').remove()" style="background: rgba(239, 68, 68, 0.1); border: none; color: #ef4444; width: 34px; height: 34px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.95rem; transition: background 0.15s ease;">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    ingredientIdx++;
}

document.addEventListener('DOMContentLoaded', function() {
    const providersData = @json($providers);
    let currentProvider = "{{ $activeProviderKey }}";
    let currentModel = "{{ $activeModel }}";

    const providerRadios = document.querySelectorAll('.provider-radio');
    const modelSelect = document.getElementById('aiModelSelect');
    const customModelGroup = document.getElementById('customModelGroup');
    const customModelInput = document.getElementById('customModelInput');
    const baseUrlGroup = document.getElementById('baseUrlGroup');
    const baseUrlInput = document.getElementById('aiBaseUrlInput');
    const apiKeyInput = document.getElementById('aiApiKeyInput');

    const aiWaiterNameInput = document.getElementById('aiWaiterNameInput');
    const aiWaiterWelcomeInput = document.getElementById('aiWaiterWelcomeInput');
    const previewWaiterName = document.getElementById('previewWaiterName');
    const previewWelcomeMessage = document.getElementById('previewWelcomeMessage');

    if (aiWaiterNameInput && previewWaiterName) {
        aiWaiterNameInput.addEventListener('input', function() {
            previewWaiterName.textContent = this.value.trim() || 'AI Waiter';
        });
    }

    if (aiWaiterWelcomeInput && previewWelcomeMessage) {
        aiWaiterWelcomeInput.addEventListener('input', function() {
            previewWelcomeMessage.textContent = this.value.trim() || 'Welcome! I am your AI waiter. I will help you choose exactly what you like.';
        });
    }

    const toggleApiKeyVisibility = document.getElementById('toggleApiKeyVisibility');
    const eyeIcon = document.getElementById('eyeIcon');
    if (toggleApiKeyVisibility && apiKeyInput) {
        toggleApiKeyVisibility.addEventListener('click', function() {
            if (apiKeyInput.type === 'password') {
                apiKeyInput.type = 'text';
                eyeIcon.className = 'fa-solid fa-eye-slash';
            } else {
                apiKeyInput.type = 'password';
                eyeIcon.className = 'fa-solid fa-eye';
            }
        });
    }

    function updateProviderUI(providerKey, keepExistingModel = false) {
        const config = providersData[providerKey];
        if (!config) return;

        currentProvider = providerKey;

        document.querySelectorAll('.provider-card').forEach(card => {
            const radio = card.querySelector('.provider-radio');
            if (radio && radio.value === providerKey) {
                card.style.borderColor = '#8b5cf6';
                card.style.background = 'rgba(139, 92, 246, 0.08)';
            } else {
                card.style.borderColor = 'var(--border-color)';
                card.style.background = 'var(--bg-body)';
            }
        });

        modelSelect.innerHTML = '';
        let foundSelected = false;
        Object.entries(config.models).forEach(([mCode, mLabel]) => {
            const opt = document.createElement('option');
            opt.value = mCode;
            opt.textContent = mLabel;
            if (keepExistingModel && mCode === currentModel) {
                opt.selected = true;
                foundSelected = true;
            } else if (!keepExistingModel && mCode === config.default_model) {
                opt.selected = true;
                foundSelected = true;
            }
            modelSelect.appendChild(opt);
        });

        const customOpt = document.createElement('option');
        customOpt.value = 'custom';
        customOpt.textContent = '✨ Custom Model...';
        if (keepExistingModel && !foundSelected && currentModel) {
            customOpt.selected = true;
            customModelInput.value = currentModel;
        }
        modelSelect.appendChild(customOpt);

        handleModelSelectChange();

        if (providerKey === 'custom') {
            baseUrlGroup.style.display = 'block';
            if (!baseUrlInput.value) {
                baseUrlInput.value = config.default_base_url || 'http://localhost:11434/v1';
            }
        } else {
            baseUrlGroup.style.display = 'block';
            baseUrlInput.placeholder = config.default_base_url ? `Default: ${config.default_base_url}` : 'Default API host';
        }
    }

    function handleModelSelectChange() {
        if (modelSelect.value === 'custom') {
            customModelGroup.style.display = 'block';
        } else {
            customModelGroup.style.display = 'none';
        }
    }

    modelSelect.addEventListener('change', handleModelSelectChange);

    providerRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked) {
                updateProviderUI(this.value, false);
            }
        });
    });

    updateProviderUI(currentProvider, true);

    // Live Connection Test AJAX
    const btnTest = document.getElementById('btnTestConnection');
    const testIcon = document.getElementById('testIcon');
    const testBtnText = document.getElementById('testBtnText');
    const testResultBox = document.getElementById('testResultBox');

    btnTest.addEventListener('click', function() {
        const provider = document.querySelector('input[name="ai_provider"]:checked')?.value || 'gemini';
        let model = modelSelect.value;
        if (model === 'custom') {
            model = customModelInput.value.trim();
        }
        const apiKey = apiKeyInput.value.trim();
        const baseUrl = baseUrlInput.value.trim();

        btnTest.disabled = true;
        testIcon.className = 'fa-solid fa-spinner fa-spin';
        testBtnText.textContent = 'Testing...';
        testResultBox.style.display = 'none';

        fetch("{{ route('admin.settings.ai.test') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                ai_provider: provider,
                ai_model: model,
                ai_api_key: apiKey,
                ai_base_url: baseUrl
            })
        })
        .then(response => response.json())
        .then(data => {
            btnTest.disabled = false;
            testIcon.className = 'fa-solid fa-bolt';
            testBtnText.textContent = 'Test Connection';

            testResultBox.style.display = 'block';
            if (data.success) {
                testResultBox.style.background = 'rgba(16, 185, 129, 0.12)';
                testResultBox.style.border = '1px solid rgba(16, 185, 129, 0.3)';
                testResultBox.style.color = '#10b981';
                testResultBox.innerHTML = `
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                        <span style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-circle-check" style="font-size: 1.1rem;"></i>
                            <span>${data.message}</span>
                        </span>
                        <span style="background: rgba(16, 185, 129, 0.2); padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.8rem; font-weight: 700;">
                            ⚡ ${data.latency_ms} ms
                        </span>
                    </div>
                `;
            } else {
                testResultBox.style.background = 'rgba(239, 68, 68, 0.12)';
                testResultBox.style.border = '1px solid rgba(239, 68, 68, 0.3)';
                testResultBox.style.color = '#ef4444';
                testResultBox.innerHTML = `
                    <div style="display: flex; align-items: flex-start; gap: 0.5rem;">
                        <i class="fa-solid fa-circle-xmark" style="font-size: 1.1rem; margin-top: 0.15rem;"></i>
                        <div>
                            <div>${data.message}</div>
                            <div style="font-size: 0.8rem; opacity: 0.85; margin-top: 0.25rem;">Please check the API Key, model name, or network connection.</div>
                        </div>
                    </div>
                `;
            }
        })
        .catch(err => {
            btnTest.disabled = false;
            testIcon.className = 'fa-solid fa-bolt';
            testBtnText.textContent = 'Test Connection';

            testResultBox.style.display = 'block';
            testResultBox.style.background = 'rgba(239, 68, 68, 0.12)';
            testResultBox.style.border = '1px solid rgba(239, 68, 68, 0.3)';
            testResultBox.style.color = '#ef4444';
            testResultBox.innerHTML = `
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-circle-xmark" style="font-size: 1.1rem;"></i>
                    <span>Network error during request. Please try again.</span>
                </div>
            `;
        });
    });
});
</script>
@endsection
