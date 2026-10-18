<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Reserve a Table') }} — {{ $vendor->name }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Noto+Sans+Armenian:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        :root {
            --primary: {{ $vendor->primary_color ?: '#3b82f6' }};
            --primary-dark: #1d4ed8;
            --accent: {{ $vendor->accent_color ?: '#10b981' }};
            --bg-color: {{ $vendor->theme_mode === 'dark' ? '#0f172a' : '#f8fafc' }};
            --card-bg: {{ $vendor->theme_mode === 'dark' ? '#1e293b' : '#ffffff' }};
            --card-border: {{ $vendor->theme_mode === 'dark' ? '#334155' : '#e2e8f0' }};
            --text-main: {{ $vendor->theme_mode === 'dark' ? '#f8fafc' : '#0f172a' }};
            --text-muted: {{ $vendor->theme_mode === 'dark' ? '#94a3b8' : '#64748b' }};
            --input-bg: {{ $vendor->theme_mode === 'dark' ? '#0f172a' : '#f1f5f9' }};
            --radius-lg: 16px;
            --radius-md: 12px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }

        body {
            font-family: 'Plus Jakarta Sans', 'Noto Sans Armenian', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            padding-bottom: 80px;
        }

        .reserve-container {
            max-width: 680px;
            margin: 0 auto;
            padding: 1.25rem 1rem;
        }

        .header-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1rem;
            border-radius: var(--radius-md);
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            color: var(--text-main);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.2s;
        }

        .brand-pill {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .brand-logo {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            object-fit: cover;
            border: 1px solid var(--card-border);
        }

        .brand-info h1 {
            font-size: 1.15rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .brand-info p {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .step-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }

        .step-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .step-number {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .step-title {
            font-size: 1.05rem;
            font-weight: 700;
        }

        /* Date Scrollable Track */
        .dates-track {
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
            scrollbar-width: thin;
        }

        .date-chip {
            flex: 0 0 auto;
            min-width: 82px;
            padding: 0.75rem 0.5rem;
            border-radius: var(--radius-md);
            border: 1px solid var(--card-border);
            background: var(--input-bg);
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .date-chip.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.35);
        }

        .date-chip .day-name { font-size: 0.75rem; font-weight: 600; opacity: 0.8; }
        .date-chip .date-num { font-size: 1.1rem; font-weight: 800; margin-top: 0.2rem; }

        /* Party Size Buttons */
        .party-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 0.5rem;
        }
        @media(max-width: 480px) {
            .party-grid { grid-template-columns: repeat(4, 1fr); }
        }

        .party-chip {
            padding: 0.65rem 0.25rem;
            text-align: center;
            border-radius: var(--radius-md);
            background: var(--input-bg);
            border: 1px solid var(--card-border);
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }

        .party-chip.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        /* Time Slots Grid */
        .slots-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.6rem;
        }
        @media(max-width: 480px) {
            .slots-grid { grid-template-columns: repeat(3, 1fr); }
        }

        .slot-btn {
            padding: 0.75rem 0.4rem;
            border-radius: var(--radius-md);
            background: var(--input-bg);
            border: 1px solid var(--card-border);
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.2rem;
        }

        .slot-btn.disabled {
            opacity: 0.4;
            cursor: not-allowed;
            text-decoration: line-through;
        }

        .slot-btn.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.35);
        }

        .slot-btn .slot-time { font-size: 1rem; font-weight: 700; }
        .slot-btn .slot-meta { font-size: 0.68rem; opacity: 0.8; }

        /* Form Inputs */
        .form-group {
            margin-bottom: 0.9rem;
        }

        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 0.35rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            background: var(--input-bg);
            border: 1px solid var(--card-border);
            color: var(--text-main);
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-input:focus {
            border-color: var(--primary);
        }

        /* Pre-Order Section */
        .preorder-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            background: var(--input-bg);
            border: 1px dashed var(--primary);
            cursor: pointer;
            margin-bottom: 0.75rem;
        }

        .preorder-toggle .title {
            font-weight: 700;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary);
        }

        .dish-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.6rem 0;
            border-bottom: 1px solid var(--card-border);
            gap: 0.5rem;
        }

        .dish-qty-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .qty-btn {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-weight: bold;
        }

        /* Sticky Submit Footer */
        .submit-btn {
            width: 100%;
            padding: 1rem;
            border-radius: var(--radius-md);
            background: var(--primary);
            color: #fff;
            border: none;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
            transition: all 0.2s;
        }

        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .alert-box {
            padding: 0.85rem 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }
        .alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #059669; }
    </style>
</head>
<body>

<div class="reserve-container" x-data="reservationBookingApp()">
    <!-- Header -->
    <header class="header-bar">
        <a href="{{ route('client.menu', ['vendor_slug' => $vendor->slug, 'location_slug' => $location?->slug]) }}" class="back-btn">
            <i class="fa-solid fa-arrow-left"></i>
            <span>{{ __('Back to Menu') }}</span>
        </a>
        <div class="brand-pill">
            @if($vendor->logo)
                <img src="{{ $vendor->logo }}" alt="{{ $vendor->name }}" class="brand-logo">
            @endif
            <div class="brand-info">
                <h1>{{ $vendor->name }}</h1>
                <p>{{ $location?->name ?: __('Table Reservation') }}</p>
            </div>
        </div>
    </header>

    <!-- Error Alert Banner -->
    <template x-if="errorMessage">
        <div class="alert-box alert-error">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span x-text="errorMessage"></span>
        </div>
    </template>

    <!-- Step 1: Date & Guests -->
    <section class="step-card">
        <div class="step-header">
            <span class="step-number">1</span>
            <span class="step-title">{{ __('Choose Date & Guests') }}</span>
        </div>

        <label class="form-label">{{ __('Select Date') }}</label>
        <div class="dates-track">
            @foreach($availableDates as $idx => $d)
                <div class="date-chip" :class="{ 'active': selectedDate === '{{ $d['date'] }}' }" @click="selectDate('{{ $d['date'] }}')">
                    <div class="day-name">{{ $d['day_name'] }}</div>
                    <div class="date-num">{{ substr($d['date'], 8, 2) }}</div>
                </div>
            @endforeach
        </div>

        <div style="margin-top: 1.25rem;">
            <label class="form-label">{{ __('Number of Guests') }}</label>
            <div class="party-grid">
                @for($p = max(1, $settings['min_party_size'] ?? 1); $p <= min(12, $settings['max_party_size'] ?? 20); $p++)
                    <div class="party-chip" :class="{ 'active': guestCount === {{ $p }} }" @click="selectGuests({{ $p }})">
                        <span>{{ $p }} {{ $p === 1 ? __('guest') : __('guests') }}</span>
                    </div>
                @endfor
            </div>
        </div>
    </section>

    <!-- Step 2: Time Slot -->
    <section class="step-card" x-show="selectedDate">
        <div class="step-header">
            <span class="step-number">2</span>
            <span class="step-title">{{ __('Available Time Slots') }}</span>
        </div>

        <div x-show="isLoadingSlots" style="text-align: center; padding: 1.5rem; color: var(--text-muted);">
            <i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>
            <p style="margin-top: 0.5rem;">{{ __('Checking table availability...') }}</p>
        </div>

        <div x-show="!isLoadingSlots && timeSlots.length > 0" class="slots-grid">
            <template x-for="slot in timeSlots" :key="slot.time">
                <button type="button" 
                        class="slot-btn" 
                        :class="{ 'disabled': !slot.available, 'active': selectedTime === slot.time }"
                        :disabled="!slot.available"
                        @click="selectTime(slot)">
                    <span class="slot-time" x-text="slot.time"></span>
                    <span class="slot-meta" x-text="slot.available ? (slot.available_tables_count + ' {{ __('free') }}') : '{{ __('full') }}'"></span>
                </button>
            </template>
        </div>

        <div x-show="!isLoadingSlots && timeSlots.length === 0" style="text-align: center; padding: 1rem; color: var(--text-muted);">
            <p>{{ __('No available slots found for this party size on this day.') }}</p>
        </div>
    </section>

    <!-- Step 3: Table Preference (Optional) -->
    <section class="step-card" x-show="selectedTime && eligibleTables.length > 0">
        <div class="step-header">
            <span class="step-number">3</span>
            <span class="step-title">{{ __('Table Selection (Optional)') }}</span>
        </div>
        <div class="form-group">
            <label class="form-label">{{ __('Select a Table or Auto-Assign') }}</label>
            <select class="form-input" x-model="selectedTableId">
                <option value="">✨ {{ __('Auto-assign optimal table (Recommended)') }}</option>
                <template x-for="t in eligibleTables" :key="t.id">
                    <option :value="t.id" x-text="t.display_name + (t.section ? ' (' + t.section + ')' : '') + ' — ' + t.capacity + ' seats'"></option>
                </template>
            </select>
        </div>
    </section>

    <!-- Step 4: Contact Details -->
    <section class="step-card" x-show="selectedTime">
        <div class="step-header">
            <span class="step-number">4</span>
            <span class="step-title">{{ __('Guest Details') }}</span>
        </div>

        <div class="form-group">
            <label class="form-label">{{ __('Your Name') }} *</label>
            <input type="text" class="form-input" x-model="customerName" placeholder="{{ __('e.g. Aram Vardanyan') }}" required>
        </div>

        <div class="form-group">
            <label class="form-label">{{ __('Email Address') }} *</label>
            <input type="email" class="form-input" x-model="customerEmail" placeholder="{{ __('name@example.com') }}" required>
            <small style="color: var(--text-muted); font-size: 0.75rem;">{{ __('Confirmation reference and tracking link will be sent here.') }}</small>
        </div>

        <div class="form-group">
            <label class="form-label">{{ __('Phone Number') }}</label>
            <input type="tel" class="form-input" x-model="customerPhone" placeholder="{{ __('+374 91 000000') }}">
        </div>

        <div class="form-group">
            <label class="form-label">{{ __('Special Requests') }}</label>
            <textarea class="form-input" rows="2" x-model="specialRequests" placeholder="{{ __('e.g. Window booth, quiet area, celebrating anniversary...') }}"></textarea>
        </div>
    </section>

    <!-- Step 5: Optional Pre-Order from Menu -->
    @if($vendor->isPreOrderFeatureEnabled())
    <section class="step-card" x-show="selectedTime">
        <div class="step-header">
            <span class="step-number">5</span>
            <span class="step-title">{{ __('Pre-Order Food & Drinks (Optional)') }}</span>
        </div>

        <div class="preorder-toggle" @click="showPreOrder = !showPreOrder">
            <span class="title">
                <i class="fa-solid fa-utensils"></i>
                <span x-text="showPreOrder ? '{{ __('Hide Pre-Order Menu') }}' : '{{ __('+ Add Pre-Order for Arrival') }}'"></span>
            </span>
            <span style="font-weight: 700; color: var(--text-main);" x-show="preOrderItems.length > 0" x-text="preOrderItems.length + ' {{ __('dishes selected') }}'"></span>
        </div>

        <div x-show="showPreOrder" x-transition style="margin-top: 1rem;">
            @foreach($categories as $cat)
                @if($cat->products->count() > 0)
                    <div style="margin-bottom: 1rem;">
                        <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--primary); margin-bottom: 0.5rem;">{{ $cat->name }}</h4>
                        @foreach($cat->products as $prod)
                            <div class="dish-row">
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; font-size: 0.9rem;">{{ $prod->name }}</div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);">{{ number_format((float) $prod->price) }} {{ $vendor->currency }}</div>
                                </div>
                                <div class="dish-qty-control">
                                    <template x-if="getItemQty({{ $prod->id }}) > 0">
                                        <button type="button" class="qty-btn" @click="changeQty({{ $prod->id }}, -1)">-</button>
                                    </template>
                                    <span style="font-weight: 700; min-width: 20px; text-align: center;" x-text="getItemQty({{ $prod->id }})"></span>
                                    <button type="button" class="qty-btn" @click="changeQty({{ $prod->id }}, 1, '{{ addslashes($prod->name) }}', {{ (float) $prod->price }})">+</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endforeach

            <!-- Pre-order summary card -->
            <div x-show="preOrderItems.length > 0" style="padding: 0.85rem; border-radius: var(--radius-md); background: var(--input-bg); margin-top: 1rem;">
                <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 0.95rem;">
                    <span>{{ __('Estimated Pre-Order Total:') }}</span>
                    <span x-text="formatCurrency(calculatePreOrderTotal()) + ' ' + '{{ $vendor->currency }}'"></span>
                </div>
            </div>
        </div>
    </section>
    @endif

    <!-- Submit Action -->
    <div x-show="selectedTime" style="margin-top: 1.5rem;">
        <button type="button" class="submit-btn" :disabled="isSubmitting" @click="submitBooking()">
            <template x-if="isSubmitting">
                <span><i class="fa-solid fa-circle-notch fa-spin"></i> {{ __('Securing Reservation...') }}</span>
            </template>
            <template x-if="!isSubmitting">
                <span><i class="fa-solid fa-check-circle"></i> {{ __('Confirm Reservation') }}</span>
            </template>
        </button>
    </div>
</div>

<script>
function reservationBookingApp() {
    return {
        selectedDate: '{{ $availableDates[0]['date'] ?? '' }}',
        guestCount: {{ max(2, $settings['min_party_size'] ?? 1) }},
        selectedTime: '',
        selectedTableId: '',
        customerName: '',
        customerEmail: '',
        customerPhone: '',
        specialRequests: '',
        showPreOrder: false,
        preOrderItems: [],
        timeSlots: [],
        eligibleTables: [],
        isLoadingSlots: false,
        isSubmitting: false,
        errorMessage: '',

        init() {
            if (this.selectedDate) {
                this.loadSlots();
            }
        },

        selectDate(date) {
            this.selectedDate = date;
            this.selectedTime = '';
            this.selectedTableId = '';
            this.loadSlots();
        },

        selectGuests(count) {
            this.guestCount = count;
            this.selectedTime = '';
            this.selectedTableId = '';
            this.loadSlots();
        },

        selectTime(slot) {
            this.selectedTime = slot.time;
            this.eligibleTables = slot.eligible_tables || [];
            this.selectedTableId = '';
        },

        async loadSlots() {
            if (!this.selectedDate || !this.guestCount) return;
            this.isLoadingSlots = true;
            this.errorMessage = '';

            try {
                const res = await fetch(`{{ route('client.reserve.availability', ['vendor_slug' => $vendor->slug]) }}?date=${this.selectedDate}&guest_count=${this.guestCount}&location_id={{ $location?->id }}`);
                const data = await res.json();
                if (data.status === 'success') {
                    this.timeSlots = data.slots || [];
                }
            } catch (err) {
                this.errorMessage = '{{ __('Could not load availability. Please refresh and try again.') }}';
            } finally {
                this.isLoadingSlots = false;
            }
        },

        getItemQty(prodId) {
            const found = this.preOrderItems.find(i => i.product_id === prodId);
            return found ? found.quantity : 0;
        },

        changeQty(prodId, delta, name = '', price = 0) {
            let found = this.preOrderItems.find(i => i.product_id === prodId);
            if (!found && delta > 0) {
                found = { product_id: prodId, name: name, unit_price: price, quantity: 0 };
                this.preOrderItems.push(found);
            }
            if (found) {
                found.quantity += delta;
                if (found.quantity <= 0) {
                    this.preOrderItems = this.preOrderItems.filter(i => i.product_id !== prodId);
                }
            }
        },

        calculatePreOrderTotal() {
            return this.preOrderItems.reduce((acc, item) => acc + (item.quantity * item.unit_price), 0);
        },

        formatCurrency(num) {
            return Number(num).toLocaleString();
        },

        async submitBooking() {
            this.errorMessage = '';
            if (!this.customerName.trim()) {
                this.errorMessage = '{{ __('Please enter your name.') }}';
                return;
            }
            if (!this.customerEmail.trim()) {
                this.errorMessage = '{{ __('Please enter your email address.') }}';
                return;
            }

            this.isSubmitting = true;

            const payload = {
                customer_name: this.customerName,
                customer_email: this.customerEmail,
                customer_phone: this.customerPhone,
                guest_count: this.guestCount,
                reservation_date: this.selectedDate,
                reservation_time: this.selectedTime,
                table_id: this.selectedTableId || null,
                special_requests: this.specialRequests,
                locale: '{{ app()->getLocale() }}',
                location_id: {{ $location?->id ?: 'null' }},
                pre_order_items: this.preOrderItems.map(i => ({
                    product_id: i.product_id,
                    quantity: i.quantity
                }))
            };

            try {
                const res = await fetch(`{{ route('client.reserve.submit', ['vendor_slug' => $vendor->slug]) }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (res.ok && data.status === 'success') {
                    window.location.href = data.details_url;
                } else {
                    this.errorMessage = data.message || Object.values(data.errors || {})[0]?.[0] || '{{ __('Reservation submission failed. Please try another time.') }}';
                    this.loadSlots(); // Refresh slots on conflict
                }
            } catch (err) {
                this.errorMessage = '{{ __('Connection error. Please try again.') }}';
            } finally {
                this.isSubmitting = false;
            }
        }
    };
}
</script>
</body>
</html>
