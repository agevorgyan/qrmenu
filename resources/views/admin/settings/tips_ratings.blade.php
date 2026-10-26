@extends('layouts.app')

@section('title', __('Tips & Waiter Ratings Settings') . ' - ' . $vendor->name)

@section('content')
<div style="max-width: 1100px; margin: 0 auto; width: 100%; box-sizing: border-box;" x-data="tipsSettingsApp()">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
        <div style="min-width: 0; flex: 1;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.85rem; flex-wrap: wrap;">
                <a href="{{ route('admin.dashboard') }}" style="color: var(--text-muted); text-decoration: none; display: flex; align-items: center; gap: 0.35rem;"><i class="fa-solid fa-gauge-high"></i> {{ __('Dashboard') }}</a>
                <i class="fa-solid fa-chevron-right" style="font-size: 0.65rem; opacity: 0.6;"></i>
                <a href="{{ route('admin.settings.index') }}" style="color: var(--text-muted); text-decoration: none;">{{ __('Settings') }}</a>
                <i class="fa-solid fa-chevron-right" style="font-size: 0.65rem; opacity: 0.6;"></i>
                <span style="color: var(--primary); font-weight: 700;">{{ __('Tips & Waiter Ratings') }}</span>
            </div>

            <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <span style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(245, 158, 11, 0.2)); color: #10b981; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);">
                    <i class="fa-solid fa-hand-holding-dollar"></i>
                </span>
                <span>{{ __('Tips & Service Ratings Configuration') }}</span>
                <span style="font-size: 0.72rem; font-weight: 700; background: linear-gradient(135deg, #10b981, #059669); color: #fff; padding: 0.2rem 0.6rem; border-radius: 999px; letter-spacing: 0.04em;">PRO SUITE</span>
            </h1>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0 0;">
                {{ __('Configure digital customer tips, waiter attribution, service ratings, and feedback moderation policy.') }}
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('admin.tips_ratings.reports') }}" class="btn btn-secondary" style="display: flex; align-items: center; gap: 0.5rem; text-decoration: none; border-radius: 12px; font-weight: 600; font-size: 0.88rem; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); padding: 0.65rem 1.15rem;">
                <i class="fa-solid fa-chart-line"></i> {{ __('View Reports & Feedback') }}
            </a>
            <button type="submit" form="tipsRatingsForm" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; border-radius: 12px; font-weight: 700; padding: 0.65rem 1.4rem; font-size: 0.92rem; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35); background: linear-gradient(135deg, #10b981, #059669); border: none; color: #fff; cursor: pointer;">
                <i class="fa-solid fa-floppy-disk"></i> {{ __('Save Settings') }}
            </button>
        </div>
    </div>

    @if (session('success'))
        <div style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 14px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; color: #10b981; display: flex; align-items: center; gap: 0.65rem;">
            <i class="fa-solid fa-circle-check" style="font-size: 1.2rem;"></i>
            <span style="font-weight: 600; font-size: 0.92rem;">{{ __(session('success')) }}</span>
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

    <form id="tipsRatingsForm" action="{{ route('admin.settings.tips_ratings.update') }}" method="POST">
        @csrf

        <div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem;">
            
            <!-- MASTER INDEPENDENT TOGGLES CARD -->
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);">
                <div style="font-family: 'Outfit'; font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-toggle-on" style="color: var(--primary);"></i>
                    <span>{{ __('Independent Capability Controls') }}</span>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
                    <!-- Overall Module Switch -->
                    <div style="background: var(--bg-main); border: 1.5px solid var(--border-color); border-radius: 16px; padding: 1.15rem; display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;">
                        <div>
                            <div style="font-weight: 800; color: var(--text-main); font-size: 0.95rem;">{{ __('Overall Module') }}</div>
                            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.25rem;">
                                {{ __('Master toggle for all tipping and rating features across the storefront.') }}
                            </div>
                        </div>
                        <label class="switch" style="position: relative; display: inline-block; width: 48px; height: 26px; flex-shrink: 0;">
                            <input type="checkbox" name="is_enabled" value="1" x-model="isModuleEnabled" {{ old('is_enabled', $settings->is_enabled) ? 'checked' : '' }} style="opacity: 0; width: 0; height: 0;">
                            <span style="position: absolute; cursor: pointer; inset: 0; background-color: #4b5563; transition: .3s; border-radius: 34px;" :style="isModuleEnabled ? 'background-color: #10b981;' : ''">
                                <span style="position: absolute; content: ''; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%;" :style="isModuleEnabled ? 'transform: translateX(22px);' : ''"></span>
                            </span>
                        </label>
                    </div>

                    <!-- Customer Tips Switch -->
                    <div style="background: var(--bg-main); border: 1.5px solid var(--border-color); border-radius: 16px; padding: 1.15rem; display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;">
                        <div>
                            <div style="font-weight: 800; color: var(--text-main); font-size: 0.95rem; display: flex; align-items: center; gap: 0.4rem;">
                                <i class="fa-solid fa-hand-holding-dollar" style="color: #10b981;"></i>
                                <span>{{ __('Customer Tips') }}</span>
                            </div>
                            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.25rem;">
                                {{ __('Enable tip selection during checkout and direct tipping via QR scan.') }}
                            </div>
                        </div>
                        <label class="switch" style="position: relative; display: inline-block; width: 48px; height: 26px; flex-shrink: 0;">
                            <input type="checkbox" name="tips_enabled" value="1" x-model="tipsEnabled" {{ old('tips_enabled', $settings->tips_enabled) ? 'checked' : '' }} style="opacity: 0; width: 0; height: 0;">
                            <span style="position: absolute; cursor: pointer; inset: 0; background-color: #4b5563; transition: .3s; border-radius: 34px;" :style="tipsEnabled ? 'background-color: #10b981;' : ''">
                                <span style="position: absolute; content: ''; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%;" :style="tipsEnabled ? 'transform: translateX(22px);' : ''"></span>
                            </span>
                        </label>
                    </div>

                    <!-- Waiter & Service Ratings Switch -->
                    <div style="background: var(--bg-main); border: 1.5px solid var(--border-color); border-radius: 16px; padding: 1.15rem; display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;">
                        <div>
                            <div style="font-weight: 800; color: var(--text-main); font-size: 0.95rem; display: flex; align-items: center; gap: 0.4rem;">
                                <i class="fa-solid fa-star" style="color: #f59e0b;"></i>
                                <span>{{ __('Waiter & Service Ratings') }}</span>
                            </div>
                            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.25rem;">
                                {{ __('Enable post-visit customer star ratings, category feedback, and comments.') }}
                            </div>
                        </div>
                        <label class="switch" style="position: relative; display: inline-block; width: 48px; height: 26px; flex-shrink: 0;">
                            <input type="checkbox" name="ratings_enabled" value="1" x-model="ratingsEnabled" {{ old('ratings_enabled', $settings->ratings_enabled) ? 'checked' : '' }} style="opacity: 0; width: 0; height: 0;">
                            <span style="position: absolute; cursor: pointer; inset: 0; background-color: #4b5563; transition: .3s; border-radius: 34px;" :style="ratingsEnabled ? 'background-color: #10b981;' : ''">
                                <span style="position: absolute; content: ''; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%;" :style="ratingsEnabled ? 'transform: translateX(22px);' : ''"></span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- TABBED / MULTI-SECTION SETTINGS -->
            <div style="display: grid; grid-template-columns: 2fr 1.2fr; gap: 1.5rem; align-items: start;">
                
                <!-- LEFT COLUMN: SETTINGS FIELDS -->
                <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                    
                    <!-- 1. TIP SETTINGS CARD -->
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem;">
                        <div style="font-family: 'Outfit'; font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-coins" style="color: #10b981;"></i>
                            <span>{{ __('Tip Options & Financial Rules') }}</span>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 1.15rem;">
                            <!-- Predefined Tip Percentages -->
                            <div>
                                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                                    {{ __('Predefined Tip Percentages (%)') }}
                                </label>
                                <input type="text" 
                                       name="tip_percentages_str" 
                                       value="{{ old('tip_percentages_str', implode(', ', $settings->tip_percentages ?? [5, 10, 15, 20])) }}" 
                                       placeholder="5, 10, 15, 20"
                                       style="width: 100%; padding: 0.75rem 0.85rem; background: var(--bg-main); border: 1.5px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.9rem; outline: none;">
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                                    {{ __('Comma-separated integers for quick customer selection buttons.') }}
                                </div>
                            </div>

                            <!-- Predefined Fixed Tip Amounts -->
                            <div>
                                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                                    {{ __('Predefined Fixed Amounts (:currency)', ['currency' => $vendor->currency]) }}
                                </label>
                                <input type="text" 
                                       name="fixed_tip_amounts_str" 
                                       value="{{ old('fixed_tip_amounts_str', implode(', ', $settings->fixed_tip_amounts ?? [200, 500, 1000])) }}" 
                                       placeholder="200, 500, 1000"
                                       style="width: 100%; padding: 0.75rem 0.85rem; background: var(--bg-main); border: 1.5px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.9rem; outline: none;">
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                                    {{ __('Comma-separated monetary values in restaurant currency.') }}
                                </div>
                            </div>

                            <!-- Calculation Base & Default Selection -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div>
                                    <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                                        {{ __('Tip Calculation Base') }}
                                    </label>
                                    <select name="calculation_base" style="width: 100%; padding: 0.75rem 0.85rem; background: var(--bg-main); border: 1.5px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.88rem; outline: none;">
                                        <option value="subtotal" {{ old('calculation_base', $settings->calculation_base) === 'subtotal' ? 'selected' : '' }}>{{ __('Items Subtotal (Eligible)') }}</option>
                                        <option value="after_discount" {{ old('calculation_base', $settings->calculation_base) === 'after_discount' ? 'selected' : '' }}>{{ __('After Birthday/Discounts') }}</option>
                                        <option value="total_with_fees" {{ old('calculation_base', $settings->calculation_base) === 'total_with_fees' ? 'selected' : '' }}>{{ __('Total with Service & Delivery') }}</option>
                                    </select>
                                </div>

                                <div>
                                    <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                                        {{ __('Default Customer Selection') }}
                                    </label>
                                    <select name="default_tip_selection" style="width: 100%; padding: 0.75rem 0.85rem; background: var(--bg-main); border: 1.5px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.88rem; outline: none;">
                                        <option value="none" {{ old('default_tip_selection', $settings->default_tip_selection) === 'none' ? 'selected' : '' }}>{{ __('No Tip (Explicit 0)') }}</option>
                                        <option value="percentage" {{ old('default_tip_selection', $settings->default_tip_selection) === 'percentage' ? 'selected' : '' }}>{{ __('First Percentage') }}</option>
                                        <option value="fixed" {{ old('default_tip_selection', $settings->default_tip_selection) === 'fixed' ? 'selected' : '' }}>{{ __('First Fixed Amount') }}</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Min & Max Tip Limits -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div>
                                    <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                                        {{ __('Minimum Tip (:currency)', ['currency' => $vendor->currency]) }}
                                    </label>
                                    <input type="number" 
                                           step="10" 
                                           name="min_tip_amount" 
                                           value="{{ old('min_tip_amount', $settings->min_tip_amount ?? 0) }}" 
                                           style="width: 100%; padding: 0.75rem 0.85rem; background: var(--bg-main); border: 1.5px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.9rem; outline: none;">
                                </div>

                                <div>
                                    <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                                        {{ __('Maximum Tip Limit (:currency)', ['currency' => $vendor->currency]) }}
                                    </label>
                                    <input type="number" 
                                           step="500" 
                                           name="max_tip_amount" 
                                           value="{{ old('max_tip_amount', $settings->max_tip_amount ?? 50000) }}" 
                                           style="width: 100%; padding: 0.75rem 0.85rem; background: var(--bg-main); border: 1.5px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.9rem; outline: none;">
                                </div>
                            </div>

                            <!-- Tip Recipient Policy & Order Types -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div>
                                    <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                                        {{ __('Tip Allocation Policy') }}
                                    </label>
                                    <select name="allocation_policy" style="width: 100%; padding: 0.75rem 0.85rem; background: var(--bg-main); border: 1.5px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.88rem; outline: none;">
                                        <option value="waiter" {{ old('allocation_policy', $settings->allocation_policy) === 'waiter' ? 'selected' : '' }}>{{ __('Assigned Waiter / Server') }}</option>
                                        <option value="pool" {{ old('allocation_policy', $settings->allocation_policy) === 'pool' ? 'selected' : '' }}>{{ __('Shared Staff Pool') }}</option>
                                        <option value="house" {{ old('allocation_policy', $settings->allocation_policy) === 'house' ? 'selected' : '' }}>{{ __('House / Restaurant Allocation') }}</option>
                                    </select>
                                </div>

                                <div>
                                    <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                                        {{ __('Applicable Order Types') }}
                                    </label>
                                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; padding-top: 0.4rem;">
                                        @php $appTypes = $settings->applicable_order_types ?? ['dine_in', 'takeaway', 'delivery']; @endphp
                                        <label style="display: flex; align-items: center; gap: 0.35rem; font-size: 0.82rem; color: var(--text-main); cursor: pointer;">
                                            <input type="checkbox" name="applicable_order_types[]" value="dine_in" {{ in_array('dine_in', $appTypes) ? 'checked' : '' }}>
                                            <span>{{ __('Dine-in') }}</span>
                                        </label>
                                        <label style="display: flex; align-items: center; gap: 0.35rem; font-size: 0.82rem; color: var(--text-main); cursor: pointer;">
                                            <input type="checkbox" name="applicable_order_types[]" value="takeaway" {{ in_array('takeaway', $appTypes) ? 'checked' : '' }}>
                                            <span>{{ __('Takeaway') }}</span>
                                        </label>
                                        <label style="display: flex; align-items: center; gap: 0.35rem; font-size: 0.82rem; color: var(--text-main); cursor: pointer;">
                                            <input type="checkbox" name="applicable_order_types[]" value="delivery" {{ in_array('delivery', $appTypes) ? 'checked' : '' }}>
                                            <span>{{ __('Delivery') }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Checkbox Toggles -->
                            <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; padding-top: 0.5rem; border-top: 1px dashed var(--border-color);">
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.85rem; color: var(--text-main);">
                                    <input type="checkbox" name="allow_custom_tip" value="1" {{ old('allow_custom_tip', $settings->allow_custom_tip) ? 'checked' : '' }} style="width: 16px; height: 16px;">
                                    <span>{{ __('Allow Custom Amount Input') }}</span>
                                </label>

                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.85rem; color: var(--text-main);">
                                    <input type="checkbox" name="allow_cash_tips" value="1" {{ old('allow_cash_tips', $settings->allow_cash_tips) ? 'checked' : '' }} style="width: 16px; height: 16px;">
                                    <span>{{ __('Allow Cash Tips') }}</span>
                                </label>

                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.85rem; color: var(--text-main);">
                                    <input type="checkbox" name="allow_waiter_selection" value="1" {{ old('allow_waiter_selection', $settings->allow_waiter_selection) ? 'checked' : '' }} style="width: 16px; height: 16px;">
                                    <span>{{ __('Allow Customer Waiter Selection') }}</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- 2. RATING & SERVICE FEEDBACK CARD -->
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem;">
                        <div style="font-family: 'Outfit'; font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-star-half-stroke" style="color: #f59e0b;"></i>
                            <span>{{ __('Service Ratings & Feedback Policy') }}</span>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 1.15rem;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <!-- Rating Scale -->
                                <div>
                                    <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                                        {{ __('Rating Scale') }}
                                    </label>
                                    <select name="rating_scale" style="width: 100%; padding: 0.75rem 0.85rem; background: var(--bg-main); border: 1.5px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.88rem; outline: none;">
                                        <option value="5" {{ old('rating_scale', $settings->rating_scale) == 5 ? 'selected' : '' }}>{{ __('1 to 5 Stars (Standard)') }}</option>
                                        <option value="3" {{ old('rating_scale', $settings->rating_scale) == 3 ? 'selected' : '' }}>{{ __('1 to 3 Stars (Compact)') }}</option>
                                        <option value="10" {{ old('rating_scale', $settings->rating_scale) == 10 ? 'selected' : '' }}>{{ __('1 to 10 Scale (Detailed)') }}</option>
                                    </select>
                                </div>

                                <!-- Duplicate Prevention Policy -->
                                <div>
                                    <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                                        {{ __('Duplicate Submission Policy') }}
                                    </label>
                                    <select name="duplicate_policy" style="width: 100%; padding: 0.75rem 0.85rem; background: var(--bg-main); border: 1.5px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.88rem; outline: none;">
                                        <option value="once_per_order" {{ in_array(old('duplicate_policy', $settings->duplicate_policy), ['once_per_order', 'per_order'], true) ? 'selected' : '' }}>{{ __('One Rating Per Unique Order') }}</option>
                                        <option value="once_per_session_day" {{ in_array(old('duplicate_policy', $settings->duplicate_policy), ['once_per_session_day', 'per_table_session'], true) ? 'selected' : '' }}>{{ __('One Rating Per Table Visit / Session') }}</option>
                                        <option value="time_window" {{ old('duplicate_policy', $settings->duplicate_policy) === 'time_window' ? 'selected' : '' }}>{{ __('Rate-limited by Time Window') }}</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Rating Submission Window (Hours) -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div>
                                    <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                                        {{ __('Submission Window (Hours)') }}
                                    </label>
                                    <input type="number" 
                                           name="submission_window_hours" 
                                           value="{{ old('submission_window_hours', $settings->submission_window_hours ?? 24) }}" 
                                           min="1" 
                                           max="720"
                                           style="width: 100%; padding: 0.75rem 0.85rem; background: var(--bg-main); border: 1.5px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.9rem; outline: none;">
                                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                                        {{ __('Time after order completion during which customer can rate.') }}
                                    </div>
                                </div>

                                <div>
                                    <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                                        {{ __('Triggering Point') }}
                                    </label>
                                    <select name="trigger_point" style="width: 100%; padding: 0.75rem 0.85rem; background: var(--bg-main); border: 1.5px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.88rem; outline: none;">
                                        <option value="after_order" {{ old('trigger_point', $settings->trigger_point) === 'after_order' ? 'selected' : '' }}>{{ __('After Order Completed / Served') }}</option>
                                        <option value="on_payment" {{ old('trigger_point', $settings->trigger_point) === 'on_payment' ? 'selected' : '' }}>{{ __('Directly Upon Payment Callback') }}</option>
                                        <option value="manual_qr" {{ old('trigger_point', $settings->trigger_point) === 'manual_qr' ? 'selected' : '' }}>{{ __('Only via Dedicated QR Code Scan') }}</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Predefined Feedback Categories -->
                            <div>
                                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem; display: block;">
                                    {{ __('Feedback Categories') }}
                                </label>
                                <input type="text" 
                                       name="feedback_categories_str" 
                                       value="{{ old('feedback_categories_str', implode(', ', $settings->feedback_categories ?? ['food_quality', 'service_speed', 'waiter_politeness', 'cleanliness', 'ambiance'])) }}" 
                                       placeholder="food_quality, service_speed, waiter_politeness, cleanliness, ambiance"
                                       style="width: 100%; padding: 0.75rem 0.85rem; background: var(--bg-main); border: 1.5px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.9rem; outline: none;">
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                                    {{ __('Comma-separated category keys (e.g. food_quality, service_speed, cleanliness).') }}
                                </div>
                            </div>

                            <!-- Rating Toggles -->
                            <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; padding-top: 0.5rem; border-top: 1px dashed var(--border-color);">
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.85rem; color: var(--text-main);">
                                    <input type="checkbox" name="allow_anonymous_ratings" value="1" {{ old('allow_anonymous_ratings', $settings->allow_anonymous_ratings) ? 'checked' : '' }} style="width: 16px; height: 16px;">
                                    <span>{{ __('Allow Anonymous Ratings') }}</span>
                                </label>

                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.85rem; color: var(--text-main);">
                                    <input type="checkbox" name="is_written_feedback_optional" value="1" {{ old('is_written_feedback_optional', $settings->is_written_feedback_optional) ? 'checked' : '' }} style="width: 16px; height: 16px;">
                                    <span>{{ __('Written Comments Optional') }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: LIVE CUSTOMER PREVIEW & DIRECT QR LINK -->
                <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                    
                    <!-- Direct Rating URL Card -->
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.25rem;">
                        <div style="font-weight: 800; color: var(--text-main); font-size: 0.95rem; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.4rem;">
                            <i class="fa-solid fa-qrcode" style="color: var(--primary);"></i>
                            <span>{{ __('Customer Rating & Tip Link') }}</span>
                        </div>
                        <p style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                            {{ __('Share or print this direct link for guests who want to leave a tip or rate service without an active online order.') }}
                        </p>
                        <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 10px; padding: 0.6rem 0.75rem; display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;">
                            <span style="font-size: 0.78rem; font-family: monospace; color: var(--primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                {{ route('client.rate', ['vendor_slug' => $vendor->slug]) }}
                            </span>
                            <a href="{{ route('client.rate', ['vendor_slug' => $vendor->slug]) }}" target="_blank" style="color: var(--text-muted); font-size: 0.85rem;" title="{{ __('Open Preview') }}">
                                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                            </a>
                        </div>
                    </div>

                    <!-- LIVE STOREFRONT PREVIEW MOCKUP -->
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.25rem;">
                        <div style="font-weight: 800; color: var(--text-main); font-size: 0.95rem; margin-bottom: 0.85rem; display: flex; justify-content: space-between; align-items: center;">
                            <span style="display: flex; align-items: center; gap: 0.4rem;">
                                <i class="fa-solid fa-eye" style="color: #06b6d4;"></i>
                                <span>{{ __('Interactive Storefront Preview') }}</span>
                            </span>
                            <span style="font-size: 0.72rem; color: #10b981; background: rgba(16, 185, 129, 0.12); padding: 0.15rem 0.5rem; border-radius: 6px; font-weight: 700;">LIVE</span>
                        </div>

                        <!-- Mockup Container -->
                        <div style="background: var(--bg-main); border: 1.5px solid var(--border-color); border-radius: 16px; padding: 1rem; display: flex; flex-direction: column; gap: 0.85rem;">
                            <div style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                                {{ __('Checkout Tip Selector') }}
                            </div>

                            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.35rem;">
                                <button type="button" 
                                        @click="previewTip = 0"
                                        style="padding: 0.45rem 0.2rem; border-radius: 8px; font-size: 0.75rem; font-weight: 700; cursor: pointer; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main);"
                                        :style="previewTip === 0 ? 'border-color: #10b981; background: rgba(16, 185, 129, 0.15); color: #10b981;' : ''">
                                    0%
                                </button>
                                <button type="button" 
                                        @click="previewTip = 5"
                                        style="padding: 0.45rem 0.2rem; border-radius: 8px; font-size: 0.75rem; font-weight: 700; cursor: pointer; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main);"
                                        :style="previewTip === 5 ? 'border-color: #10b981; background: rgba(16, 185, 129, 0.15); color: #10b981;' : ''">
                                    5%
                                </button>
                                <button type="button" 
                                        @click="previewTip = 10"
                                        style="padding: 0.45rem 0.2rem; border-radius: 8px; font-size: 0.75rem; font-weight: 700; cursor: pointer; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main);"
                                        :style="previewTip === 10 ? 'border-color: #10b981; background: rgba(16, 185, 129, 0.15); color: #10b981;' : ''">
                                    10%
                                </button>
                                <button type="button" 
                                        @click="previewTip = 15"
                                        style="padding: 0.45rem 0.2rem; border-radius: 8px; font-size: 0.75rem; font-weight: 700; cursor: pointer; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main);"
                                        :style="previewTip === 15 ? 'border-color: #10b981; background: rgba(16, 185, 129, 0.15); color: #10b981;' : ''">
                                    15%
                                </button>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 0.5rem; border-top: 1px dashed var(--border-color); font-size: 0.78rem;">
                                <span style="color: var(--text-muted);">{{ __('Calculated Tip (Subtotal 5,000 :curr)', ['curr' => $vendor->currency]) }}</span>
                                <span style="font-weight: 800; color: #10b981;" x-text="'+' + Number(Math.round((5000 * previewTip) / 100)).toLocaleString() + ' {{ $vendor->currency }}'"></span>
                            </div>

                            <div style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-top: 0.5rem;">
                                {{ __('Star Rating Display') }}
                            </div>

                            <div style="display: flex; justify-content: center; gap: 0.35rem; font-size: 1.35rem; color: #f59e0b;">
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    function tipsSettingsApp() {
        return {
            isModuleEnabled: {{ $settings->is_enabled ? 'true' : 'false' }},
            tipsEnabled: {{ $settings->tips_enabled ? 'true' : 'false' }},
            ratingsEnabled: {{ $settings->ratings_enabled ? 'true' : 'false' }},
            previewTip: 10
        };
    }
</script>
@endsection
