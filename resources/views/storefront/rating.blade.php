<!DOCTYPE html>
<html lang="{{ $lang ?? 'hy' }}" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('tips.rate_service_title') ?? 'Գնահատել սպասարկումը' }} — {{ $vendor->name }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>

    <style>
        :root {
            --primary: {{ $vendor->brand_primary_color ?? '#3b82f6' }};
            --accent: {{ $vendor->brand_accent_color ?? '#f59e0b' }};
            --bg-main: #0b0f19;
            --bg-card: #111827;
            --border-color: rgba(255, 255, 255, 0.08);
            --text-main: #f9fafb;
            --text-muted: #9ca3af;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
            background: var(--bg-main);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 1.5rem 1rem 3rem 1rem;
        }

        .rating-card {
            width: 100%;
            max-width: 520px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        }

        .card-header {
            padding: 2rem 1.5rem 1.5rem 1.5rem;
            text-align: center;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.03) 0%, transparent 100%);
            border-bottom: 1px solid var(--border-color);
        }

        .logo-wrap {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            background: var(--bg-main);
            border: 1px solid var(--border-color);
            margin: 0 auto 1rem auto;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .logo-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .card-body {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .star-box {
            background: var(--bg-main);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            padding: 1.25rem;
            text-align: center;
        }

        .star-btn {
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 0.35rem;
            font-size: 2.2rem;
            transition: transform 0.15s ease;
        }

        .cat-chip {
            padding: 0.5rem 0.85rem;
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            border: 1.5px solid var(--border-color);
            background: var(--bg-main);
            color: var(--text-main);
        }

        .cat-chip.is-active {
            background: rgba(245, 158, 11, 0.15);
            border-color: #f59e0b;
            color: #d97706;
        }

        .tip-pill {
            background: var(--bg-main);
            border: 1.5px solid var(--border-color);
            border-radius: 12px;
            padding: 0.65rem 0.5rem;
            color: var(--text-main);
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.15rem;
            transition: all 0.2s;
        }

        .tip-pill.is-active {
            border-color: #10b981;
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
        }

        .input-control {
            width: 100%;
            padding: 0.75rem 0.85rem;
            background: var(--bg-main);
            border: 1.5px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-main);
            font-size: 0.9rem;
            outline: none;
        }

        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.35);
        }
    </style>
</head>
<body x-data="standaloneRatingApp()">

    <div class="rating-card">
        <!-- Card Header -->
        <div class="card-header">
            @if($vendor->logo)
                <div class="logo-wrap">
                    <img src="{{ $vendor->logo }}" alt="{{ $vendor->name }}">
                </div>
            @else
                <div class="logo-wrap" style="color: var(--primary); font-size: 1.5rem;">
                    <i class="fa-solid fa-utensils"></i>
                </div>
            @endif
            <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.35rem; font-weight: 800; margin-bottom: 0.25rem;">
                {{ $vendor->name }}
            </h1>
            <p style="font-size: 0.82rem; color: var(--text-muted);">
                {{ __('tips.rate_service_subtitle') ?? 'Ձեր կարծիքն օգնում է մեզ էլ ավելի կատարելագործել սպասարկումը' }}
            </p>
            @if($order)
                <div style="margin-top: 0.75rem; display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); padding: 0.25rem 0.75rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; color: var(--primary);">
                    <i class="fa-solid fa-receipt"></i>
                    <span>{{ __('tips.order_reference') ?? 'Պատվեր' }} #{{ $order->order_number }}</span>
                    @if($order->table_number)
                        <span>• {{ __('menu.table') }} {{ $order->table_number }}</span>
                    @endif
                </div>
            @elseif($table)
                <div style="margin-top: 0.75rem; display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2); padding: 0.25rem 0.75rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; color: #f59e0b;">
                    <i class="fa-solid fa-chair"></i>
                    <span>{{ __('menu.table') }} {{ $table }}</span>
                </div>
            @endif
        </div>

        <!-- Success Screen -->
        <template x-if="submitted">
            <div style="padding: 3rem 1.5rem; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 1rem;">
                <div style="width: 72px; height: 72px; border-radius: 50%; background: rgba(16, 185, 129, 0.15); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 2.2rem;">
                    <i class="fa-solid fa-check"></i>
                </div>
                <h3 style="font-family: 'Outfit'; font-size: 1.4rem; font-weight: 800;">
                    {{ __('tips.thank_you_rating_title') ?? 'Շնորհակալություն գնահատականի համար!' }}
                </h3>
                <p style="font-size: 0.88rem; color: var(--text-muted); max-width: 320px;">
                    {{ __('tips.thank_you_rating_desc') ?? 'Ձեր արձագանքը հաջողությամբ գրանցվել է և շատ արժեքավոր է մեր թիմի համար:' }}
                </p>
                <a href="{{ route('client.menu', ['vendor_slug' => $vendor->slug]) }}" 
                   style="margin-top: 1rem; padding: 0.85rem 1.75rem; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 0.9rem;">
                    {{ __('menu.back_to_menu') ?? 'Վերադառնալ մենյուին' }}
                </a>
            </div>
        </template>

        <!-- Form Body -->
        <template x-if="!submitted">
            <div class="card-body">
                
                @if($vendor->isRatingsEnabled())
                <!-- 1. Star Rating -->
                <div class="star-box">
                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.75rem;">
                        {{ __('tips.select_rating') ?? 'Ընտրեք գնահատականը' }}
                    </div>
                    <div style="display: flex; justify-content: center; gap: 0.5rem;">
                        <template x-for="star in ratingScale" :key="star">
                            <button type="button" 
                                    @click="score = star" 
                                    class="star-btn"
                                    :style="score >= star ? 'color: #f59e0b; transform: scale(1.1);' : 'color: rgba(156, 163, 175, 0.3);'">
                                <i class="fa-solid fa-star"></i>
                            </button>
                        </template>
                    </div>
                    <div style="margin-top: 0.65rem; font-weight: 700; font-size: 0.95rem; color: var(--text-main);">
                        <span x-text="score === 5 ? '{{ __('tips.rating_5_stars') ?? 'Գերազանց' }}' : (score === 4 ? '{{ __('tips.rating_4_stars') ?? 'Շատ լավ' }}' : (score === 3 ? '{{ __('tips.rating_3_stars') ?? 'Լավ' }}' : (score === 2 ? '{{ __('tips.rating_2_stars') ?? 'Բավարար' }}' : '{{ __('tips.rating_1_star') ?? 'Վատ' }}')))"></span>
                    </div>
                </div>

                <!-- Feedback Categories -->
                @if(!empty($tipSetting->feedback_categories))
                <div>
                    <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem; display: block;">
                        {{ __('tips.what_went_well') ?? 'Ի՞նչն էր առանձնահատուկ' }}
                    </label>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                        @foreach($tipSetting->feedback_categories as $cat)
                            <button type="button" 
                                    @click="toggleCategory('{{ $cat }}')"
                                    class="cat-chip"
                                    :class="{ 'is-active': selectedCategories.includes('{{ $cat }}') }">
                                {{ ucfirst(str_replace('_', ' ', $cat)) }}
                            </button>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Waiter Selection -->
                @if($tipSetting->allow_waiter_selection)
                <div>
                    <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem; display: flex; align-items: center; gap: 0.4rem;">
                        <i class="fa-solid fa-user-tie"></i>
                        <span>{{ __('tips.waiter_attribution') ?? 'Սպասարկող մատուցող' }}</span>
                    </label>
                    <select x-model="waiterId" class="input-control">
                        <option :value="null">{{ __('tips.general_service') ?? 'Ընդհանուր սպասարկում' }}</option>
                        @foreach($vendor->users()->whereIn('role', ['staff', 'manager', 'cashier'])->get() as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <!-- Written Comment -->
                <div>
                    <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.4rem; display: block;">
                        {{ __('tips.your_feedback') ?? 'Ձեր մեկնաբանությունը' }}
                    </label>
                    <textarea x-model="comment" 
                              rows="3" 
                              maxlength="1000"
                              placeholder="{{ __('tips.comment_placeholder') ?? 'Ձեր տպավորությունները...' }}"
                              class="input-control" 
                              style="resize: vertical;"></textarea>
                </div>

                <!-- Anonymous Toggle -->
                @if($tipSetting->allow_anonymous_ratings)
                <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer; font-size: 0.85rem;">
                    <input type="checkbox" x-model="isAnonymous" style="width: 18px; height: 18px; accent-color: var(--primary);">
                    <span>{{ __('tips.submit_anonymously') ?? 'Ուղարկել անանուն' }}</span>
                </label>
                @endif
                @endif

                <!-- Direct Tip Option (when tips are enabled) -->
                @if($vendor->isTipsEnabled())
                <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 18px; padding: 1.25rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <div style="font-size: 0.85rem; font-weight: 800; display: flex; align-items: center; gap: 0.4rem;">
                            <i class="fa-solid fa-hand-holding-dollar" style="color: #10b981;"></i>
                            <span>{{ __('tips.leave_tip_title') ?? 'Թողնել թեյավճար' }}</span>
                        </div>
                        <template x-if="tipAmount > 0">
                            <span style="font-weight: 800; color: #10b981; font-size: 0.85rem;">
                                +<span x-text="Number(tipAmount).toLocaleString()"></span> {{ $vendor->currency }}
                            </span>
                        </template>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(80px, 1fr)); gap: 0.5rem;">
                        <button type="button" @click="tipAmount = 0; customTip = false" class="tip-pill" :class="{ 'is-active': tipAmount === 0 && !customTip }">
                            <span>{{ __('tips.no_tip') ?? 'Առանց' }}</span>
                        </button>
                        @foreach($tipSetting->fixed_tip_amounts ?? [200, 500, 1000] as $amt)
                            <button type="button" @click="tipAmount = {{ $amt }}; customTip = false" class="tip-pill" :class="{ 'is-active': tipAmount === {{ $amt }} && !customTip }">
                                <span>{{ number_format($amt) }} {{ $vendor->currency }}</span>
                            </button>
                        @endforeach
                        @if($tipSetting->allow_custom_tip)
                            <button type="button" @click="customTip = true" class="tip-pill" :class="{ 'is-active': customTip }">
                                <span>{{ __('tips.custom_amount') ?? 'Այլ' }}</span>
                            </button>
                        @endif
                    </div>

                    <template x-if="customTip">
                        <div style="margin-top: 0.75rem;">
                            <input type="number" x-model.number="tipAmount" placeholder="0 {{ $vendor->currency }}" class="input-control">
                        </div>
                    </template>
                </div>
                @endif

                <!-- Error Alert -->
                <template x-if="errorMessage">
                    <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 10px; padding: 0.75rem; color: #ef4444; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span x-text="errorMessage"></span>
                    </div>
                </template>

                <!-- Submit Button -->
                <button type="button" 
                        @click="submitAll()" 
                        :disabled="isSubmitting"
                        class="btn-submit">
                    <template x-if="!isSubmitting">
                        <span style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-paper-plane"></i>
                            <span>{{ __('tips.send_feedback') ?? 'Ուղարկել' }}</span>
                        </span>
                    </template>
                    <template x-if="isSubmitting">
                        <span style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-spinner fa-spin"></i>
                            <span>{{ __('tips.sending') ?? 'Ուղարկվում է...' }}</span>
                        </span>
                    </template>
                </button>
            </div>
        </template>
    </div>

    <script>
        function standaloneRatingApp() {
            return {
                ratingScale: {{ (int) ($tipSetting->rating_scale ?? 5) }},
                score: 5,
                selectedCategories: [],
                waiterId: {{ $order?->waiter_id ? $order->waiter_id : 'null' }},
                comment: '',
                isAnonymous: false,
                tipAmount: 0,
                customTip: false,
                isSubmitting: false,
                submitted: false,
                errorMessage: '',

                toggleCategory(cat) {
                    if (this.selectedCategories.includes(cat)) {
                        this.selectedCategories = this.selectedCategories.filter(c => c !== cat);
                    } else {
                        this.selectedCategories.push(cat);
                    }
                },

                async submitAll() {
                    this.isSubmitting = true;
                    this.errorMessage = '';

                    try {
                        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                        
                        @if($vendor->isRatingsEnabled())
                        const ratingRes = await fetch('{{ route("client.rating.submit", ["vendor_slug" => $vendor->slug]) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf
                            },
                            body: JSON.stringify({
                                order_number: '{{ $order?->order_number }}' || null,
                                table_number: '{{ $table ?? $order?->table_number }}' || null,
                                waiter_id: this.waiterId,
                                rating: this.score,
                                categories: this.selectedCategories,
                                comment: this.comment,
                                is_anonymous: this.isAnonymous,
                                customer_name: this.isAnonymous ? null : '{{ $order?->customer_name }}'
                            })
                        });

                        const ratingData = await ratingRes.json().catch(() => ({}));
                        if (!ratingRes.ok || !ratingData.success) {
                            this.errorMessage = ratingData.message || '{{ __('tips.error_submitting_rating') ?? 'Չհաջողվեց գրանցել գնահատականը' }}';
                            this.isSubmitting = false;
                            return;
                        }
                        @endif

                        @if($vendor->isTipsEnabled())
                        if (this.tipAmount > 0) {
                            await fetch('{{ route("client.tip.submit", ["vendor_slug" => $vendor->slug]) }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrf
                                },
                                body: JSON.stringify({
                                    order_number: '{{ $order?->order_number }}' || null,
                                    table_number: '{{ $table ?? $order?->table_number }}' || null,
                                    waiter_id: this.waiterId,
                                    amount: this.tipAmount,
                                    payment_method: 'cash'
                                })
                            }).catch(() => {});
                        }
                        @endif

                        this.submitted = true;
                    } catch (e) {
                        this.errorMessage = 'Կապի խնդիր: Խնդրում ենք փորձել կրկին:';
                    } finally {
                        this.isSubmitting = false;
                    }
                }
            };
        }
    </script>
</body>
</html>
