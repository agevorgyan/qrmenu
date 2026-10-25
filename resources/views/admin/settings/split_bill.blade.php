@extends('layouts.app')

@section('title', __('Split Bill Settings') . ' - ' . $vendor->name)

@section('content')
<div style="max-width: 1050px; margin: 0 auto; width: 100%; box-sizing: border-box;" x-data="splitBillSettingsApp()">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
        <div style="min-width: 0; flex: 1;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.85rem; flex-wrap: wrap;">
                <a href="{{ route('admin.dashboard') }}" style="color: var(--text-muted); text-decoration: none; display: flex; align-items: center; gap: 0.35rem;"><i class="fa-solid fa-gauge-high"></i> {{ __('Dashboard') }}</a>
                <i class="fa-solid fa-chevron-right" style="font-size: 0.65rem; opacity: 0.6;"></i>
                <a href="{{ route('admin.settings.index') }}" style="color: var(--text-muted); text-decoration: none;">{{ __('Settings') }}</a>
                <i class="fa-solid fa-chevron-right" style="font-size: 0.65rem; opacity: 0.6;"></i>
                <span style="color: var(--primary); font-weight: 700;">{{ __('Split Bill') }}</span>
            </div>

            <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <span style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(37, 99, 235, 0.2)); color: #3b82f6; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);">
                    <i class="fa-solid fa-divide"></i>
                </span>
                <span>{{ __('Split Bill Configuration') }}</span>
                <span style="font-size: 0.72rem; font-weight: 700; background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff; padding: 0.2rem 0.6rem; border-radius: 999px; letter-spacing: 0.04em;">PRO SUITE</span>
            </h1>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0 0;">
                {{ __('Allow guests at a table or order to divide their bill equally, select specific dishes, or pay custom amounts seamlessly.') }}
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <button type="submit" form="splitBillForm" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; border-radius: 12px; font-weight: 700; padding: 0.65rem 1.4rem; font-size: 0.92rem; box-shadow: 0 4px 14px rgba(59, 130, 246, 0.35); background: linear-gradient(135deg, #3b82f6, #2563eb); border: none; color: #fff; cursor: pointer;">
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

    <!-- Active Bills Warning if Disabling -->
    @if ($activeBillsCount > 0)
        <div x-show="!isEnabled" style="background: rgba(245, 158, 11, 0.12); border: 1.5px solid #f59e0b; border-radius: 16px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; color: var(--text-main); display: flex; align-items: flex-start; gap: 0.85rem;" x-cloak>
            <i class="fa-solid fa-triangle-exclamation" style="color: #f59e0b; font-size: 1.35rem; margin-top: 0.1rem; flex-shrink: 0;"></i>
            <div>
                <div style="font-weight: 800; font-size: 0.95rem; color: #d97706;">{{ __('Active Split Bills In Progress') }}</div>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem; line-height: 1.45;">
                    {{ __('There are currently :count active split bill sessions in progress. Disabling this module will hide the split option for new requests, but existing partially paid bills must still be settled by your staff.', ['count' => $activeBillsCount]) }}
                </div>
            </div>
        </div>
    @endif

    <form id="splitBillForm" action="{{ route('admin.settings.split_bill.update') }}" method="POST">
        @csrf

        <div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem;">

            <!-- MASTER ENABLE SWITCH CARD -->
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 1.5rem; flex-wrap: wrap;">
                    <div style="max-width: 600px;">
                        <div style="font-family: 'Outfit'; font-size: 1.2rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 0.6rem;">
                            <i class="fa-solid fa-toggle-on" style="color: #3b82f6;"></i>
                            <span>{{ __('Enable Split Bill Module') }}</span>
                        </div>
                        <p style="font-size: 0.88rem; color: var(--text-muted); margin: 0.35rem 0 0 0; line-height: 1.45;">
                            {{ __('When enabled, guests can split their order directly from their phone by scanning the table QR code or opening the order tracker link.') }}
                        </p>
                    </div>

                    <label class="modern-toggle" style="position: relative; display: inline-block; width: 56px; height: 30px; cursor: pointer; flex-shrink: 0;">
                        <input type="checkbox" name="is_enabled" value="1" x-model="isEnabled" style="opacity: 0; width: 0; height: 0;">
                        <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; border-radius: 34px; transition: 0.3s;"
                              :style="isEnabled ? 'background: #3b82f6;' : 'background: #cbd5e1;'"></span>
                        <span style="position: absolute; content: ''; height: 22px; width: 22px; left: 4px; bottom: 4px; background: white; border-radius: 50%; transition: 0.3s;"
                              :style="isEnabled ? 'transform: translateX(26px);' : ''"></span>
                    </label>
                </div>
            </div>

            <!-- DETAILED CONFIGURATION (CONDITIONAL ON IS_ENABLED) -->
            <div x-show="isEnabled" x-transition:enter="transition ease-out duration-250" x-transition:enter-start="opacity-0 transform -translate-y-2" x-transition:enter-end="opacity-100 transform translate-y-0" style="display: flex; flex-direction: column; gap: 1.5rem;">

                <!-- SPLITTING METHODS -->
                <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);">
                    <div style="font-family: 'Outfit'; font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.35rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-shapes" style="color: #3b82f6;"></i>
                        <span>{{ __('Supported Splitting Methods') }}</span>
                    </div>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                        {{ __('Choose which payment breakdown options are available to your customers.') }}
                    </p>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.15rem;">
                        
                        <!-- 1. Equal Split -->
                        <div style="background: var(--bg-main); border: 1.5px solid var(--border-color); border-radius: 16px; padding: 1.15rem; display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 800; color: var(--text-main); font-size: 0.95rem;">
                                    <i class="fa-solid fa-users-viewfinder" style="color: #3b82f6;"></i>
                                    <span>{{ __('Equal Split') }}</span>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.35rem; line-height: 1.4;">
                                    {{ __('Divide remaining balance equally among 2 to :max people with penny-accurate rounding.', ['max' => $settings->max_participants ?? 10]) }}
                                </div>
                            </div>
                            <label class="modern-toggle" style="position: relative; display: inline-block; width: 48px; height: 26px; cursor: pointer; flex-shrink: 0;">
                                <input type="checkbox" name="allow_equal_split" value="1" x-model="allowEqual" style="opacity: 0; width: 0; height: 0;">
                                <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; border-radius: 34px; transition: 0.3s;"
                                      :style="allowEqual ? 'background: #3b82f6;' : 'background: #cbd5e1;'"></span>
                                <span style="position: absolute; content: ''; height: 18px; width: 18px; left: 4px; bottom: 4px; background: white; border-radius: 50%; transition: 0.3s;"
                                      :style="allowEqual ? 'transform: translateX(22px);' : ''"></span>
                            </label>
                        </div>

                        <!-- 2. Split By Item (Dishes) -->
                        <div style="background: var(--bg-main); border: 1.5px solid var(--border-color); border-radius: 16px; padding: 1.15rem; display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 800; color: var(--text-main); font-size: 0.95rem;">
                                    <i class="fa-solid fa-utensils" style="color: #10b981;"></i>
                                    <span>{{ __('Split by Dishes / Items') }}</span>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.35rem; line-height: 1.4;">
                                    {{ __('Guests pick the exact dishes they ordered, with automatic proportional tax and service fee sharing.') }}
                                </div>
                            </div>
                            <label class="modern-toggle" style="position: relative; display: inline-block; width: 48px; height: 26px; cursor: pointer; flex-shrink: 0;">
                                <input type="checkbox" name="allow_item_split" value="1" x-model="allowItem" style="opacity: 0; width: 0; height: 0;">
                                <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; border-radius: 34px; transition: 0.3s;"
                                      :style="allowItem ? 'background: #10b981;' : 'background: #cbd5e1;'"></span>
                                <span style="position: absolute; content: ''; height: 18px; width: 18px; left: 4px; bottom: 4px; background: white; border-radius: 50%; transition: 0.3s;"
                                      :style="allowItem ? 'transform: translateX(22px);' : ''"></span>
                            </label>
                        </div>

                        <!-- 3. Custom Partial Amount -->
                        <div style="background: var(--bg-main); border: 1.5px solid var(--border-color); border-radius: 16px; padding: 1.15rem; display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 800; color: var(--text-main); font-size: 0.95rem;">
                                    <i class="fa-solid fa-money-bill-wave" style="color: #f59e0b;"></i>
                                    <span>{{ __('Custom Amount Payment') }}</span>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.35rem; line-height: 1.4;">
                                    {{ __('Guests can enter any custom amount up to the remaining unpaid balance.') }}
                                </div>
                            </div>
                            <label class="modern-toggle" style="position: relative; display: inline-block; width: 48px; height: 26px; cursor: pointer; flex-shrink: 0;">
                                <input type="checkbox" name="allow_custom_amount" value="1" x-model="allowCustom" style="opacity: 0; width: 0; height: 0;">
                                <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; border-radius: 34px; transition: 0.3s;"
                                      :style="allowCustom ? 'background: #f59e0b;' : 'background: #cbd5e1;'"></span>
                                <span style="position: absolute; content: ''; height: 18px; width: 18px; left: 4px; bottom: 4px; background: white; border-radius: 50%; transition: 0.3s;"
                                      :style="allowCustom ? 'transform: translateX(22px);' : ''"></span>
                            </label>
                        </div>

                        <!-- 4. Share Bill Link / QR -->
                        <div style="background: var(--bg-main); border: 1.5px solid var(--border-color); border-radius: 16px; padding: 1.15rem; display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 800; color: var(--text-main); font-size: 0.95rem;">
                                    <i class="fa-solid fa-qrcode" style="color: #8b5cf6;"></i>
                                    <span>{{ __('Shareable Bill Link & QR') }}</span>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.35rem; line-height: 1.4;">
                                    {{ __('Display a share button and instant QR code so other table guests can open the bill on their phone.') }}
                                </div>
                            </div>
                            <label class="modern-toggle" style="position: relative; display: inline-block; width: 48px; height: 26px; cursor: pointer; flex-shrink: 0;">
                                <input type="checkbox" name="allow_share_link" value="1" x-model="allowShare" style="opacity: 0; width: 0; height: 0;">
                                <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; border-radius: 34px; transition: 0.3s;"
                                      :style="allowShare ? 'background: #8b5cf6;' : 'background: #cbd5e1;'"></span>
                                <span style="position: absolute; content: ''; height: 18px; width: 18px; left: 4px; bottom: 4px; background: white; border-radius: 50%; transition: 0.3s;"
                                      :style="allowShare ? 'transform: translateX(22px);' : ''"></span>
                            </label>
                        </div>

                    </div>
                </div>

                <!-- RULES & LIMITS -->
                <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);">
                    <div style="font-family: 'Outfit'; font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.35rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-sliders" style="color: #3b82f6;"></i>
                        <span>{{ __('Business Rules & Approval Controls') }}</span>
                    </div>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                        {{ __('Manage participant constraints and staff validation requirements.') }}
                    </p>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
                        <!-- Max Participants -->
                        <div>
                            <label style="display: block; font-weight: 700; font-size: 0.88rem; color: var(--text-main); margin-bottom: 0.4rem;">
                                {{ __('Maximum Equal Split Participants') }}
                            </label>
                            <input type="number" name="max_participants" min="2" max="50" x-model="maxParticipants" required
                                   style="width: 100%; padding: 0.75rem 1rem; border-radius: 12px; border: 1.5px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.95rem; font-family: 'Outfit'; font-weight: 700; box-sizing: border-box;">
                            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem;">
                                {{ __('Recommended: 10 people. Minimum 2, maximum 50.') }}
                            </div>
                        </div>

                        <!-- Staff Approval Toggle -->
                        <div style="background: var(--bg-main); border: 1.5px solid var(--border-color); border-radius: 16px; padding: 1.15rem; display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 800; color: var(--text-main); font-size: 0.95rem;">
                                    <i class="fa-solid fa-user-check" style="color: #eab308;"></i>
                                    <span>{{ __('Require Staff Approval for Cash Splits') }}</span>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.35rem; line-height: 1.4;">
                                    {{ __('When enabled, cash split payments remain pending until confirmed by a waiter or manager.') }}
                                </div>
                            </div>
                            <label class="modern-toggle" style="position: relative; display: inline-block; width: 48px; height: 26px; cursor: pointer; flex-shrink: 0;">
                                <input type="checkbox" name="require_staff_approval" value="1" x-model="requireStaffApproval" style="opacity: 0; width: 0; height: 0;">
                                <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; border-radius: 34px; transition: 0.3s;"
                                      :style="requireStaffApproval ? 'background: #eab308;' : 'background: #cbd5e1;'"></span>
                                <span style="position: absolute; content: ''; height: 18px; width: 18px; left: 4px; bottom: 4px; background: white; border-radius: 50%; transition: 0.3s;"
                                      :style="requireStaffApproval ? 'transform: translateX(22px);' : ''"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- LIVE INTERACTIVE CUSTOMER PREVIEW CARD -->
                <div style="background: var(--bg-card); border: 1.5px dashed var(--border-color); border-radius: 20px; padding: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
                        <div style="font-family: 'Outfit'; font-size: 1.1rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-mobile-screen" style="color: #3b82f6;"></i>
                            <span>{{ __('Customer Experience Live Preview') }}</span>
                        </div>
                        <span style="font-size: 0.75rem; background: var(--bg-main); border: 1px solid var(--border-color); padding: 0.2rem 0.6rem; border-radius: 8px; color: var(--text-muted); font-weight: 600;">
                            {{ __('Interactive Simulation') }}
                        </span>
                    </div>

                    <div style="max-width: 440px; margin: 0 auto; background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 22px; padding: 1.25rem; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);">
                        <!-- Sample Bill Header -->
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem; margin-bottom: 0.85rem;">
                            <div>
                                <span style="font-size: 0.75rem; font-weight: 700; color: #3b82f6;">TABLE #4</span>
                                <div style="font-family: 'Outfit'; font-weight: 800; font-size: 1.1rem; color: var(--text-main);">Order ORD-1082</div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 0.75rem; color: var(--text-muted);">{{ __('Total Bill') }}</div>
                                <div style="font-family: 'Outfit'; font-weight: 800; font-size: 1.15rem; color: var(--text-main);">12,000 {{ $vendor->currency }}</div>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div style="margin-bottom: 1rem;">
                            <div style="display: flex; justify-content: space-between; font-size: 0.78rem; font-weight: 700; margin-bottom: 0.35rem;">
                                <span style="color: #10b981;">{{ __('Paid') }}: 4,000 {{ $vendor->currency }}</span>
                                <span style="color: #ef4444;">{{ __('Remaining') }}: 8,000 {{ $vendor->currency }}</span>
                            </div>
                            <div style="height: 8px; background: rgba(0,0,0,0.06); border-radius: 999px; overflow: hidden; display: flex;">
                                <div style="width: 33.3%; background: #10b981;"></div>
                                <div style="width: 66.7%; background: rgba(59, 130, 246, 0.3);"></div>
                            </div>
                        </div>

                        <!-- Tabs Preview -->
                        <div style="display: flex; gap: 0.4rem; margin-bottom: 1rem;">
                            <template x-if="allowEqual">
                                <div style="flex: 1; padding: 0.5rem; text-align: center; background: #3b82f6; color: white; border-radius: 10px; font-size: 0.78rem; font-weight: 700;">
                                    {{ __('Equal') }}
                                </div>
                            </template>
                            <template x-if="allowItem">
                                <div style="flex: 1; padding: 0.5rem; text-align: center; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 10px; font-size: 0.78rem; font-weight: 700;">
                                    {{ __('By Dishes') }}
                                </div>
                            </template>
                            <template x-if="allowCustom">
                                <div style="flex: 1; padding: 0.5rem; text-align: center; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 10px; font-size: 0.78rem; font-weight: 700;">
                                    {{ __('Custom') }}
                                </div>
                            </template>
                        </div>

                        <!-- Equal Split Simulation Box -->
                        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 0.85rem; text-align: center; margin-bottom: 0.85rem;">
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.35rem;">
                                {{ __('Split remaining 8,000 :curr between 2 people', ['curr' => $vendor->currency]) }}
                            </div>
                            <div style="font-family: 'Outfit'; font-size: 1.35rem; font-weight: 800; color: #3b82f6;">
                                4,000 {{ $vendor->currency }} <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">/ {{ __('person') }}</span>
                            </div>
                        </div>

                        <div style="display: flex; gap: 0.5rem;">
                            <button type="button" class="btn" style="flex: 1; padding: 0.75rem; border-radius: 12px; background: #3b82f6; color: white; border: none; font-weight: 800; font-size: 0.88rem;">
                                <i class="fa-solid fa-credit-card"></i> {{ __('Pay My Share') }} (4,000 {{ $vendor->currency }})
                            </button>
                            <template x-if="allowShare">
                                <button type="button" class="btn" style="padding: 0.75rem 0.9rem; border-radius: 12px; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); font-size: 0.88rem;">
                                    <i class="fa-solid fa-share-nodes"></i>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </form>
</div>

<script>
function splitBillSettingsApp() {
    return {
        isEnabled: {{ $settings->is_enabled ? 'true' : 'false' }},
        allowEqual: {{ $settings->allow_equal_split ? 'true' : 'false' }},
        allowItem: {{ $settings->allow_item_split ? 'true' : 'false' }},
        allowCustom: {{ $settings->allow_custom_amount ? 'true' : 'false' }},
        allowShare: {{ $settings->allow_share_link ? 'true' : 'false' }},
        maxParticipants: {{ (int) ($settings->max_participants ?? 10) }},
        requireStaffApproval: {{ $settings->require_staff_approval ? 'true' : 'false' }}
    };
}
</script>
@endsection
