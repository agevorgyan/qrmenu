<!DOCTYPE html>
<html lang="{{ $lang ?? 'hy' }}" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Split Bill') }} — {{ $order->order_number }} | {{ $vendor->name }}</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- QR Code Generator Lib -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>

    <style>
        :root {
            --primary: {{ $vendor->primary_color ?? '#e11d48' }};
            --primary-rgb: {{ $vendor->primary_color ? hexdec(substr($vendor->primary_color, 1, 2)) . ', ' . hexdec(substr($vendor->primary_color, 3, 2)) . ', ' . hexdec(substr($vendor->primary_color, 5, 2)) : '225, 29, 72' }};
            --bg-main: #0f172a;
            --bg-card: #1e293b;
            --bg-elevated: #334155;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: rgba(255, 255, 255, 0.08);
            --accent: #3b82f6;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        @media (prefers-color-scheme: light) {
            :root {
                --bg-main: #f8fafc;
                --bg-card: #ffffff;
                --bg-elevated: #f1f5f9;
                --text-main: #0f172a;
                --text-muted: #64748b;
                --border-color: #e2e8f0;
            }
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-main);
            color: var(--text-main);
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        .split-container {
            width: 100%;
            max-width: 580px;
            margin: 0 auto;
            padding: 1rem 1rem calc(2.5rem + env(safe-area-inset-bottom)) 1rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .glass-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1.25rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        }

        .tab-btn {
            flex: 1;
            padding: 0.75rem 0.5rem;
            border-radius: 12px;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            border: 1.5px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-muted);
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.35rem;
        }

        .tab-btn.active {
            background: var(--accent);
            border-color: var(--accent);
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(59, 130, 246, 0.3);
        }

        .btn-pay {
            width: 100%;
            padding: 1rem;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--accent), #2563eb);
            color: #ffffff;
            border: none;
            font-weight: 800;
            font-size: 1.05rem;
            font-family: 'Outfit', sans-serif;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.35);
            transition: transform 0.15s, opacity 0.15s;
        }

        .btn-pay:active {
            transform: scale(0.98);
        }

        .btn-pay:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            box-shadow: none;
        }

        .stepper-btn {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-main);
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .stepper-btn:active {
            background: var(--bg-elevated);
        }

        [x-cloak] { display: none !important; }
    </style>
</head>
<body>

<div class="split-container" x-data="storefrontSplitBillApp()" x-init="initApp()">

    <!-- Top Navigation Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: env(safe-area-inset-top);">
        <a href="{{ route('client.menu', ['vendor_slug' => $vendor->slug]) }}" 
           style="background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 0.95rem;">
            <i class="fa-solid fa-arrow-left"></i>
        </a>

        <div style="text-align: center;">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--accent); text-transform: uppercase; letter-spacing: 0.05em;">
                {{ __('Split Bill') }}
            </div>
            <div style="font-family: 'Outfit'; font-weight: 800; font-size: 1.15rem; color: var(--text-main);">
                {{ $order->order_number }}
            </div>
        </div>

        <button type="button" @click="showShareModal = true" 
                style="background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.95rem;">
            <i class="fa-solid fa-share-nodes"></i>
        </button>
    </div>

    <!-- Active Bill Hero Card with Live Balances -->
    <div class="glass-card" style="position: relative; overflow: hidden;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.85rem;">
            <div>
                <span style="font-size: 0.75rem; background: rgba(59, 130, 246, 0.15); color: var(--accent); padding: 0.2rem 0.6rem; border-radius: 8px; font-weight: 700;">
                    @if($order->table_number)
                        {{ __('Table') }} #{{ $order->table_number }}
                    @else
                        {{ __('Order') }}
                    @endif
                </span>
                <h2 style="font-family: 'Outfit'; font-size: 1.55rem; font-weight: 800; color: var(--text-main); margin-top: 0.35rem;" x-text="formatMoney(bill.total_amount) + ' ' + bill.currency"></h2>
                <div style="font-size: 0.78rem; color: var(--text-muted);">{{ __('Total Bill Amount') }}</div>
            </div>

            <!-- Fully Paid Badge -->
            <template x-if="bill.is_fully_paid">
                <span style="background: rgba(16, 185, 129, 0.15); border: 1.5px solid var(--success); color: var(--success); font-weight: 800; font-size: 0.8rem; padding: 0.35rem 0.85rem; border-radius: 999px; display: inline-flex; align-items: center; gap: 0.4rem;">
                    <i class="fa-solid fa-circle-check"></i> {{ __('Fully Paid') }}
                </span>
            </template>
        </div>

        <!-- Live Progress Bar -->
        <div style="margin-bottom: 0.65rem;">
            <div style="height: 10px; background: rgba(0,0,0,0.1); border-radius: 999px; overflow: hidden; display: flex;">
                <div :style="'width: ' + getPaidPercent() + '%; background: var(--success); transition: width 0.4s;'"></div>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: 700;">
            <span style="color: var(--success); display: flex; align-items: center; gap: 0.3rem;">
                <i class="fa-solid fa-check"></i>
                <span>{{ __('Paid') }}: <strong x-text="formatMoney(bill.paid_amount) + ' ' + bill.currency"></strong></span>
            </span>
            <span style="color: var(--danger); display: flex; align-items: center; gap: 0.3rem;">
                <i class="fa-solid fa-clock"></i>
                <span>{{ __('Remaining') }}: <strong x-text="formatMoney(bill.remaining_amount) + ' ' + bill.currency"></strong></span>
            </span>
        </div>
    </div>

    <!-- Celebration Screen when Bill is 100% Settled -->
    <template x-if="bill.is_fully_paid">
        <div class="glass-card" style="text-align: center; padding: 2rem 1.25rem; border-color: rgba(16, 185, 129, 0.4); background: linear-gradient(135deg, rgba(16, 185, 129, 0.08), rgba(16, 185, 129, 0.02));">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: var(--success); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 1rem; box-shadow: 0 6px 20px rgba(16, 185, 129, 0.35);">
                <i class="fa-solid fa-check-double"></i>
            </div>
            <h3 style="font-family: 'Outfit'; font-size: 1.4rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.4rem;">
                {{ __('Bill is Fully Settled!') }}
            </h3>
            <p style="font-size: 0.88rem; color: var(--text-muted); max-width: 320px; margin: 0 auto 1.5rem auto;">
                {{ __('All shares and items for this order have been paid. Thank you for visiting!') }}
            </p>
            <a href="{{ route('client.menu', ['vendor_slug' => $vendor->slug]) }}" class="btn-pay" style="text-decoration: none;">
                <i class="fa-solid fa-utensils"></i> {{ __('Back to Menu') }}
            </a>
        </div>
    </template>

    <!-- Interactive Splitting Interface (When Remaining > 0) -->
    <template x-if="!bill.is_fully_paid">
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">

            <!-- Method Selection Tabs -->
            <div style="display: flex; gap: 0.5rem;">
                <template x-if="settings.allow_equal_split">
                    <button type="button" class="tab-btn" :class="{ 'active': activeTab === 'equal' }" @click="activeTab = 'equal'">
                        <i class="fa-solid fa-users-viewfinder" style="font-size: 1.1rem;"></i>
                        <span>{{ __('Equal Split') }}</span>
                    </button>
                </template>

                <template x-if="settings.allow_item_split">
                    <button type="button" class="tab-btn" :class="{ 'active': activeTab === 'by_item' }" @click="activeTab = 'by_item'">
                        <i class="fa-solid fa-utensils" style="font-size: 1.1rem;"></i>
                        <span>{{ __('By Dishes') }}</span>
                    </button>
                </template>

                <template x-if="settings.allow_custom_amount">
                    <button type="button" class="tab-btn" :class="{ 'active': activeTab === 'custom' }" @click="activeTab = 'custom'">
                        <i class="fa-solid fa-money-bill-wave" style="font-size: 1.1rem;"></i>
                        <span>{{ __('Custom Amount') }}</span>
                    </button>
                </template>
            </div>

            <!-- TAB 1: EQUAL SPLIT -->
            <div class="glass-card" x-show="activeTab === 'equal'" x-cloak>
                <div style="text-align: center; margin-bottom: 1.25rem;">
                    <div style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 0.5rem;">
                        {{ __('Divide remaining balance among people') }}
                    </div>

                    <!-- Participant Stepper -->
                    <div style="display: inline-flex; align-items: center; gap: 1.25rem; background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 16px; padding: 0.4rem 0.85rem;">
                        <button type="button" class="stepper-btn" @click="decrementParticipants()" :disabled="participantsCount <= 2">
                            <i class="fa-solid fa-minus"></i>
                        </button>
                        <div style="font-family: 'Outfit'; font-size: 1.35rem; font-weight: 800; min-width: 48px; color: var(--text-main);" x-text="participantsCount"></div>
                        <button type="button" class="stepper-btn" @click="incrementParticipants()" :disabled="participantsCount >= settings.max_participants">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                </div>

                <!-- Per Person Amount Display -->
                <div style="background: var(--bg-main); border: 1.5px solid var(--accent); border-radius: 16px; padding: 1rem; text-align: center; margin-bottom: 1.25rem;">
                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.25rem;">{{ __('Your share to pay') }}</div>
                    <div style="font-family: 'Outfit'; font-size: 1.85rem; font-weight: 800; color: var(--accent);" x-text="formatMoney(getEqualShare()) + ' ' + bill.currency"></div>
                </div>
            </div>

            <!-- TAB 2: SPLIT BY DISHES -->
            <div class="glass-card" x-show="activeTab === 'by_item'" x-cloak>
                <div style="font-size: 0.85rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.85rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-receipt" style="color: var(--success);"></i>
                    <span>{{ __('Select dishes you want to pay for') }}</span>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.25rem;">
                    <template x-for="item in items" :key="item.order_item_id">
                        <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 14px; padding: 0.75rem 0.85rem; display: flex; justify-content: space-between; align-items: center; gap: 0.75rem;">
                            <div style="min-width: 0; flex: 1;">
                                <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="item.name"></div>
                                <div style="font-size: 0.78rem; color: var(--text-muted); display: flex; gap: 0.5rem; margin-top: 0.2rem;">
                                    <span x-text="formatMoney(item.unit_price) + ' ' + bill.currency"></span>
                                    <span>•</span>
                                    <span style="color: var(--accent);" x-text="'{{ __('Available') }}: ' + item.available_quantity + ' / ' + item.ordered_quantity"></span>
                                </div>
                            </div>

                            <!-- Item Qty Selector -->
                            <template x-if="item.available_quantity > 0">
                                <div style="display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0;">
                                    <button type="button" class="stepper-btn" @click="decrementItem(item.order_item_id)" :disabled="getItemSelectedQty(item.order_item_id) <= 0">
                                        <i class="fa-solid fa-minus" style="font-size: 0.75rem;"></i>
                                    </button>
                                    <span style="font-family: 'Outfit'; font-weight: 800; min-width: 22px; text-align: center;" x-text="getItemSelectedQty(item.order_item_id)"></span>
                                    <button type="button" class="stepper-btn" @click="incrementItem(item.order_item_id, item.available_quantity)" :disabled="getItemSelectedQty(item.order_item_id) >= item.available_quantity">
                                        <i class="fa-solid fa-plus" style="font-size: 0.75rem;"></i>
                                    </button>
                                </div>
                            </template>

                            <template x-if="item.available_quantity <= 0">
                                <span style="font-size: 0.75rem; background: rgba(16, 185, 129, 0.15); color: var(--success); padding: 0.2rem 0.5rem; border-radius: 6px; font-weight: 700;">
                                    {{ __('Paid') }}
                                </span>
                            </template>
                        </div>
                    </template>
                </div>

                <!-- Item Total Preview -->
                <div style="background: var(--bg-main); border: 1.5px solid var(--success); border-radius: 16px; padding: 0.85rem; text-align: center; margin-bottom: 0.5rem;">
                    <div style="font-size: 0.78rem; color: var(--text-muted);">{{ __('Total for selected dishes (including fees)') }}</div>
                    <div style="font-family: 'Outfit'; font-size: 1.6rem; font-weight: 800; color: var(--success);" x-text="formatMoney(getItemSplitTotal()) + ' ' + bill.currency"></div>
                </div>
            </div>

            <!-- TAB 3: CUSTOM AMOUNT -->
            <div class="glass-card" x-show="activeTab === 'custom'" x-cloak>
                <div style="font-size: 0.85rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.5rem;">
                    {{ __('Enter Custom Payment Amount') }}
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem;">
                    {{ __('Max payable right now') }}: <strong style="color: var(--accent);" x-text="formatMoney(bill.remaining_amount) + ' ' + bill.currency"></strong>
                </div>

                <!-- Quick Percentage Pills -->
                <div style="display: flex; gap: 0.4rem; margin-bottom: 1rem;">
                    <button type="button" @click="setCustomPercent(0.25)" style="flex: 1; padding: 0.5rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-weight: 700; font-size: 0.8rem; cursor: pointer;">25%</button>
                    <button type="button" @click="setCustomPercent(0.50)" style="flex: 1; padding: 0.5rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-weight: 700; font-size: 0.8rem; cursor: pointer;">50%</button>
                    <button type="button" @click="setCustomPercent(0.75)" style="flex: 1; padding: 0.5rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-weight: 700; font-size: 0.8rem; cursor: pointer;">75%</button>
                    <button type="button" @click="setCustomPercent(1.00)" style="flex: 1; padding: 0.5rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-weight: 700; font-size: 0.8rem; cursor: pointer;">100%</button>
                </div>

                <div style="position: relative; margin-bottom: 0.5rem;">
                    <input type="number" step="any" min="1" :max="bill.remaining_amount" x-model.number="customAmount" placeholder="0"
                           style="width: 100%; padding: 0.85rem 1rem; border-radius: 14px; border: 1.5px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-family: 'Outfit'; font-size: 1.35rem; font-weight: 800; box-sizing: border-box;">
                    <span style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-weight: 700;" x-text="bill.currency"></span>
                </div>
            </div>

            <!-- Participant Name Input & Payment Method Picker -->
            <div class="glass-card">
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem;">
                        {{ __('Your Name / Nickname (Optional)') }}
                    </label>
                    <input type="text" x-model="participantName" placeholder="{{ __('e.g. Karen, Anna') }}" maxlength="50"
                           style="width: 100%; padding: 0.75rem 1rem; border-radius: 12px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem; box-sizing: border-box;">
                </div>

                <!-- Payment Method Select -->
                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.45rem;">
                        {{ __('Choose Payment Method') }}
                    </label>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem;">
                        <button type="button" @click="paymentMethod = 'cash'" 
                                :style="paymentMethod === 'cash' ? 'border-color: var(--accent); background: rgba(59, 130, 246, 0.15);' : 'border-color: var(--border-color); background: var(--bg-main);'"
                                style="padding: 0.75rem; border-radius: 12px; border: 1.5px solid; color: var(--text-main); font-size: 0.82rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <i class="fa-solid fa-money-bill-wave" style="color: #10b981;"></i>
                            <span>{{ __('Cash / Terminal') }}</span>
                        </button>

                        <button type="button" @click="paymentMethod = 'idram'" 
                                :style="paymentMethod === 'idram' ? 'border-color: var(--accent); background: rgba(59, 130, 246, 0.15);' : 'border-color: var(--border-color); background: var(--bg-main);'"
                                style="padding: 0.75rem; border-radius: 12px; border: 1.5px solid; color: var(--text-main); font-size: 0.82rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <i class="fa-solid fa-wallet" style="color: #f97316;"></i>
                            <span>Idram</span>
                        </button>

                        <button type="button" @click="paymentMethod = 'telcell'" 
                                :style="paymentMethod === 'telcell' ? 'border-color: var(--accent); background: rgba(59, 130, 246, 0.15);' : 'border-color: var(--border-color); background: var(--bg-main);'"
                                style="padding: 0.75rem; border-radius: 12px; border: 1.5px solid; color: var(--text-main); font-size: 0.82rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <i class="fa-solid fa-mobile-screen" style="color: #eab308;"></i>
                            <span>Telcell</span>
                        </button>

                        <button type="button" @click="paymentMethod = 'stripe'" 
                                :style="paymentMethod === 'stripe' ? 'border-color: var(--accent); background: rgba(59, 130, 246, 0.15);' : 'border-color: var(--border-color); background: var(--bg-main);'"
                                style="padding: 0.75rem; border-radius: 12px; border: 1.5px solid; color: var(--text-main); font-size: 0.82rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <i class="fa-solid fa-credit-card" style="color: #6366f1;"></i>
                            <span>Card / Stripe</span>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="button" class="btn-pay" @click="submitSplit()" :disabled="isSubmitting || getPayableAmount() <= 0">
                    <template x-if="!isSubmitting">
                        <span style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-lock"></i>
                            <span>{{ __('Pay') }} <strong x-text="formatMoney(getPayableAmount()) + ' ' + bill.currency"></strong></span>
                        </span>
                    </template>
                    <template x-if="isSubmitting">
                        <span style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-spinner fa-spin"></i>
                            <span>{{ __('Processing...') }}</span>
                        </span>
                    </template>
                </button>
            </div>

        </div>
    </template>

    <!-- Participants Payments Ledger -->
    <template x-if="payments && payments.length > 0">
        <div class="glass-card">
            <div style="font-family: 'Outfit'; font-size: 0.95rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
                <span style="display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-user-group" style="color: var(--accent);"></i>
                    <span>{{ __('Participants Who Contributed') }}</span>
                </span>
                <span style="font-size: 0.75rem; color: var(--text-muted);" x-text="payments.length + ' {{ __('payments') }}'"></span>
            </div>

            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <template x-for="p in payments" :key="p.uuid">
                    <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.65rem 0.85rem; display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 0.6rem;">
                            <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--bg-elevated); display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 800; color: var(--accent);" x-text="(p.participant_name || 'G')[0].toUpperCase()"></div>
                            <div>
                                <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-main);" x-text="p.participant_name || '{{ __('Guest') }}'"></div>
                                <div style="font-size: 0.72rem; color: var(--text-muted);" x-text="p.payment_method + ' • ' + p.created_at_time"></div>
                            </div>
                        </div>

                        <div style="text-align: right;">
                            <div style="font-family: 'Outfit'; font-weight: 800; font-size: 0.95rem; color: var(--success);" x-text="formatMoney(p.amount) + ' ' + p.currency"></div>
                            <span style="font-size: 0.68rem; font-weight: 700; text-transform: uppercase;"
                                  :style="p.is_paid ? 'color: var(--success);' : 'color: var(--warning);'"
                                  x-text="p.is_paid ? '{{ __('Paid') }}' : '{{ __('Pending') }}'"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </template>

    <!-- Share Bill Modal -->
    <div x-show="showShareModal" style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 1.25rem;" x-cloak>
        <div class="glass-card" style="width: 100%; max-width: 400px; text-align: center; position: relative;">
            <button type="button" @click="showShareModal = false" style="position: absolute; right: 1rem; top: 1rem; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer;">
                ✕
            </button>

            <h3 style="font-family: 'Outfit'; font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.35rem;">
                {{ __('Share Bill with Table') }}
            </h3>
            <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                {{ __('Friends can scan this QR code or use the link to pay their share directly from their phones.') }}
            </p>

            <!-- QR Container -->
            <div id="billQrContainer" style="display: inline-flex; padding: 1rem; background: #ffffff; border-radius: 16px; margin-bottom: 1.25rem;"></div>

            <div style="display: flex; gap: 0.5rem;">
                <input type="text" readonly :value="shareUrl" style="flex: 1; padding: 0.7rem 0.85rem; border-radius: 12px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-muted); font-size: 0.78rem; text-overflow: ellipsis;">
                <button type="button" @click="copyShareLink()" class="btn-pay" style="width: auto; padding: 0.7rem 1.15rem; font-size: 0.85rem;">
                    <i class="fa-solid fa-copy"></i> <span x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy') }}'"></span>
                </button>
            </div>
        </div>
    </div>

</div>

<script>
function storefrontSplitBillApp() {
    return {
        vendorSlug: '{{ $vendor->slug }}',
        token: '{{ $order->tracking_token }}',
        activeTab: 'equal',
        participantsCount: 2,
        customAmount: null,
        participantName: '',
        paymentMethod: 'cash',
        isSubmitting: false,
        showShareModal: false,
        copied: false,
        shareUrl: window.location.href,

        bill: {
            total_amount: {{ (float) $splitBill->total_amount }},
            paid_amount: {{ (float) $splitBill->paid_amount }},
            remaining_amount: {{ (float) $splitBill->remaining_amount }},
            currency: '{{ $splitBill->currency }}',
            is_fully_paid: {{ $splitBill->isFullyPaid() ? 'true' : 'false' }}
        },

        settings: {
            allow_equal_split: {{ $settings->allow_equal_split ? 'true' : 'false' }},
            allow_item_split: {{ $settings->allow_item_split ? 'true' : 'false' }},
            allow_custom_amount: {{ $settings->allow_custom_amount ? 'true' : 'false' }},
            allow_share_link: {{ $settings->allow_share_link ? 'true' : 'false' }},
            max_participants: {{ (int) ($settings->max_participants ?? 10) }}
        },

        items: @json($items),
        selectedItemQuantities: {},
        payments: @json($splitBill->payments()->orderBy('created_at', 'desc')->get()->map(fn($p) => [
            'uuid' => $p->uuid,
            'participant_name' => $p->participant_name,
            'amount' => (float) $p->amount,
            'currency' => $p->currency,
            'status' => $p->status,
            'payment_method' => $p->payment_method,
            'is_paid' => $p->isPaid(),
            'created_at_time' => $p->created_at->format('H:i')
        ])),

        initApp() {
            // Default active tab to first enabled method
            if (!this.settings.allow_equal_split) {
                this.activeTab = this.settings.allow_item_split ? 'by_item' : 'custom';
            }

            // Generate QR Code
            this.$nextTick(() => {
                const el = document.getElementById('billQrContainer');
                if (el && typeof QRCode !== 'undefined') {
                    new QRCode(el, {
                        text: this.shareUrl,
                        width: 160,
                        height: 160,
                        colorDark: '#0f172a',
                        colorLight: '#ffffff',
                        correctLevel: QRCode.CorrectLevel.M
                    });
                }
            });

            // Auto-refresh bill balance every 7 seconds
            setInterval(() => this.refreshBill(), 7000);
        },

        formatMoney(val) {
            return Number(val || 0).toLocaleString(undefined, {
                minimumFractionDigits: (['AMD', '֏', 'RUB', 'JPY'].includes(this.bill.currency)) ? 0 : 2,
                maximumFractionDigits: 2
            });
        },

        getPaidPercent() {
            if (this.bill.total_amount <= 0) return 0;
            return Math.min(100, Math.round((this.bill.paid_amount / this.bill.total_amount) * 100));
        },

        incrementParticipants() {
            if (this.participantsCount < this.settings.max_participants) {
                this.participantsCount++;
            }
        },

        decrementParticipants() {
            if (this.participantsCount > 2) {
                this.participantsCount--;
            }
        },

        getEqualShare() {
            if (this.bill.remaining_amount <= 0 || this.participantsCount <= 0) return 0;
            const decimals = (['AMD', '֏', 'RUB', 'JPY'].includes(this.bill.currency)) ? 0 : 2;
            const factor = Math.pow(10, decimals);
            return Math.round((this.bill.remaining_amount / this.participantsCount) * factor) / factor;
        },

        getItemSelectedQty(itemId) {
            return this.selectedItemQuantities[itemId] || 0;
        },

        incrementItem(itemId, maxAvail) {
            const cur = this.getItemSelectedQty(itemId);
            if (cur < maxAvail) {
                this.selectedItemQuantities[itemId] = cur + 1;
            }
        },

        decrementItem(itemId) {
            const cur = this.getItemSelectedQty(itemId);
            if (cur > 0) {
                this.selectedItemQuantities[itemId] = cur - 1;
            }
        },

        getItemSplitTotal() {
            let sub = 0;
            this.items.forEach(item => {
                const q = this.getItemSelectedQty(item.order_item_id);
                if (q > 0) {
                    sub += (item.unit_price * q);
                }
            });
            return sub;
        },

        setCustomPercent(pct) {
            const amt = Math.round(this.bill.remaining_amount * pct);
            this.customAmount = amt;
        },

        getPayableAmount() {
            if (this.activeTab === 'equal') {
                return this.getEqualShare();
            } else if (this.activeTab === 'by_item') {
                return this.getItemSplitTotal();
            } else if (this.activeTab === 'custom') {
                return Number(this.customAmount) || 0;
            }
            return 0;
        },

        async submitSplit() {
            const amount = this.getPayableAmount();
            if (amount <= 0) {
                alert('{{ __("Please select or enter an amount to pay.") }}');
                return;
            }

            if (amount > this.bill.remaining_amount + 0.01) {
                alert('{{ __("Amount exceeds the remaining unpaid balance.") }}');
                return;
            }

            const payload = {
                split_type: this.activeTab,
                payment_method: this.paymentMethod,
                participant_name: this.participantName,
                amount: amount,
                participants_count: this.participantsCount,
                items: Object.keys(this.selectedItemQuantities)
                    .filter(k => this.selectedItemQuantities[k] > 0)
                    .map(k => ({
                        order_item_id: parseInt(k),
                        quantity: this.selectedItemQuantities[k]
                    })),
                idempotency_key: 'spt_' + Math.random().toString(36).substring(2) + Date.now().toString(36)
            };

            this.isSubmitting = true;

            try {
                const res = await fetch(`/api/m/${this.vendorSlug}/bill/${this.token}/split`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();

                if (!res.ok || !data.success) {
                    alert(data.message || '{{ __("Unable to process split payment.") }}');
                    return;
                }

                // If digital redirect
                if (data.redirect_url) {
                    window.location.href = data.redirect_url;
                    return;
                }

                // If offline / cash
                if (data.requires_approval) {
                    alert(data.message || '{{ __("Your cash split payment request has been submitted for waiter approval.") }}');
                } else {
                    alert('{{ __("Your payment has been successfully recorded!") }}');
                }

                this.selectedItemQuantities = {};
                this.customAmount = null;
                await this.refreshBill();
            } catch (e) {
                alert('{{ __("Network error. Please try again.") }}');
            } finally {
                this.isSubmitting = false;
            }
        },

        async refreshBill() {
            try {
                const res = await fetch(`/api/m/${this.vendorSlug}/bill/${this.token}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.bill = data.bill;
                    this.payments = data.payments;
                    this.items = data.items;
                }
            } catch (e) {}
        },

        copyShareLink() {
            navigator.clipboard.writeText(this.shareUrl).then(() => {
                this.copied = true;
                setTimeout(() => this.copied = false, 2500);
            });
        }
    };
}
</script>

</body>
</html>
