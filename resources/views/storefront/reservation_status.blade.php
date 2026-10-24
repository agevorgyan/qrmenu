<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Reservation') }} {{ $reservation->reference }} — {{ $vendor->name }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Noto+Sans+Armenian:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @php
        $primaryHex = ltrim($vendor->primary_color ?: '#3b82f6', '#');
        if (strlen($primaryHex) === 3) {
            $primaryHex = $primaryHex[0].$primaryHex[0].$primaryHex[1].$primaryHex[1].$primaryHex[2].$primaryHex[2];
        }
        $r = hexdec(substr($primaryHex, 0, 2) ?: '3b');
        $g = hexdec(substr($primaryHex, 2, 2) ?: '82');
        $b = hexdec(substr($primaryHex, 4, 2) ?: 'f6');
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        $primaryTextColor = $luminance > 0.62 ? '#0f172a' : '#ffffff';
        $isDark = ($vendor->theme_mode === 'dark');
    @endphp

    <style>
        :root {
            --primary: {{ $vendor->primary_color ?: '#3b82f6' }};
            --primary-contrast: {{ $primaryTextColor }};
            --accent: {{ $vendor->accent_color ?: '#10b981' }};
            @if($isDark)
            --bg-color: #0b1120;
            --card-bg: #141d2e;
            --card-border: #233149;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --text-subtle: #cbd5e1;
            --input-bg: #0d1525;
            --shadow-card: 0 4px 20px -2px rgba(0, 0, 0, 0.45);
            @else
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --card-border: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --text-subtle: #475569;
            --input-bg: #ffffff;
            --shadow-card: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
            @endif
            --radius-lg: 16px;
            --radius-md: 12px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', 'Noto Sans Armenian', -apple-system, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            padding-bottom: 60px;
        }

        .status-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 1.5rem 1rem;
        }

        .nav-back {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            color: var(--text-main);
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-md);
            padding: 0.6rem 1rem;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            margin-bottom: 1.25rem;
            box-shadow: var(--shadow-card);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .nav-back:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateX(-2px);
        }

        .hero-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-lg);
            padding: 2rem 1.25rem;
            text-align: center;
            margin-bottom: 1.25rem;
            box-shadow: var(--shadow-card);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.45rem 1rem;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 800;
            letter-spacing: 0.03em;
            margin-bottom: 0.85rem;
        }

        .status-confirmed { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.35); }
        .status-pending { background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.35); }
        .status-seated { background: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.35); }
        .status-completed { background: rgba(100, 116, 139, 0.15); color: #94a3b8; border: 1px solid rgba(100, 116, 139, 0.35); }
        .status-cancelled, .status-rejected { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.35); }

        .ref-number {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 0.35rem;
            color: var(--text-main);
        }

        .detail-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-lg);
            padding: 1.35rem;
            margin-bottom: 1.25rem;
            box-shadow: var(--shadow-card);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--card-border);
            font-size: 0.95rem;
        }

        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: var(--text-muted); font-weight: 500; }
        .detail-value { font-weight: 700; color: var(--text-main); }

        .cancel-btn {
            width: 100%;
            padding: 0.85rem;
            border-radius: var(--radius-md);
            background: rgba(239, 68, 68, 0.08);
            color: #ef4444;
            border: 1.5px solid rgba(239, 68, 68, 0.3);
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 1rem;
        }

        .cancel-btn:hover {
            background: rgba(239, 68, 68, 0.16);
            border-color: #ef4444;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            z-index: 100;
        }

        .modal-box {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-lg);
            max-width: 440px;
            width: 100%;
            padding: 1.5rem;
            box-shadow: var(--shadow-card);
        }
    </style>
</head>
<body>

<div class="status-container" x-data="reservationStatusApp()">
    <a href="{{ route('client.menu', ['vendor_slug' => $vendor->slug, 'location_slug' => $reservation->location?->slug]) }}" class="nav-back">
        <i class="fa-solid fa-arrow-left"></i>
        <span>{{ __('Back to Menu') }}</span>
    </a>

    <!-- Hero Card -->
    <div class="hero-card">
        <span class="status-badge status-{{ $reservation->status }}">
            <i class="fa-solid {{ $reservation->status === 'confirmed' ? 'fa-circle-check' : ($reservation->status === 'pending' ? 'fa-clock' : 'fa-info-circle') }}"></i>
            {{ __(ucfirst($reservation->status)) }}
        </span>
        <div class="ref-number">{{ $reservation->reference }}</div>
        <p style="color: var(--text-muted); font-size: 0.9rem;">{{ __('Please present this reference upon arrival at the host stand.') }}</p>
    </div>

    <!-- Booking Details Card -->
    <div class="detail-card">
        <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 0.75rem;">{{ __('Reservation Details') }}</h3>
        
        <div class="detail-row">
            <span class="detail-label">{{ __('Restaurant') }}</span>
            <span class="detail-value">{{ $vendor->name }}</span>
        </div>
        @if($reservation->location)
        <div class="detail-row">
            <span class="detail-label">{{ __('Location') }}</span>
            <span class="detail-value">{{ $reservation->location->name }} ({{ $reservation->location->address }})</span>
        </div>
        @endif
        <div class="detail-row">
            <span class="detail-label">{{ __('Date & Time') }}</span>
            <span class="detail-value">{{ $reservation->getFormattedDate() }} • {{ $reservation->getFormattedTime() }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">{{ __('Party Size') }}</span>
            <span class="detail-value">{{ $reservation->guest_count }} {{ $reservation->guest_count === 1 ? __('guest') : __('guests') }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">{{ __('Table') }}</span>
            <span class="detail-value">{{ $reservation->table_name ?: ($reservation->restaurantTable?->display_name ?: __('Assigned on arrival')) }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">{{ __('Guest Name') }}</span>
            <span class="detail-value">{{ $reservation->customer_name }}</span>
        </div>
        @if($reservation->special_requests)
        <div class="detail-row">
            <span class="detail-label">{{ __('Special Requests') }}</span>
            <span class="detail-value">{{ $reservation->special_requests }}</span>
        </div>
        @endif
    </div>

    <!-- Pre-Order Breakdown Card (If attached) -->
    @if($reservation->preOrder && !empty($reservation->preOrder->items))
    <div class="detail-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
            <h3 style="font-size: 1.05rem; font-weight: 700;">{{ __('Attached Pre-Order') }}</h3>
            <span class="status-badge status-{{ $reservation->preOrder->status }}" style="font-size: 0.75rem; padding: 0.2rem 0.6rem; margin-bottom: 0;">
                {{ __(ucfirst($reservation->preOrder->status)) }}
            </span>
        </div>

        @foreach($reservation->preOrder->items as $item)
            <div class="detail-row">
                <div>
                    <div style="font-weight: 700;">{{ $item['quantity'] }}x {{ $item['name'] }}</div>
                    @if(!empty($item['variation_name']))
                        <div style="font-size: 0.8rem; color: var(--text-muted);">{{ $item['variation_name'] }}</div>
                    @endif
                </div>
                <div class="detail-value">{{ number_format((float) ($item['subtotal'] ?? 0)) }} {{ $reservation->preOrder->currency }}</div>
            </div>
        @endforeach

        <div class="detail-row" style="margin-top: 0.5rem; font-size: 1.05rem; border-top: 2px solid var(--card-border);">
            <span class="detail-label" style="font-weight: 700; color: var(--text-main);">{{ __('Pre-Order Total') }}</span>
            <span class="detail-value" style="color: var(--primary);">{{ number_format((float) $reservation->preOrder->total_amount) }} {{ $reservation->preOrder->currency }}</span>
        </div>
    </div>
    @endif

    <!-- Cancellation Policy / Button -->
    @if($reservation->canCancel())
    <div class="detail-card" style="text-align: center;">
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">
            {{ __('Plans changed? You can cancel your reservation online according to the cancellation policy.') }}
        </p>
        <button type="button" class="cancel-btn" @click="showCancelModal = true">
            <i class="fa-solid fa-xmark"></i> {{ __('Cancel Reservation') }}
        </button>
    </div>
    @endif

    <!-- Cancel Confirmation Modal -->
    <div class="modal-overlay" x-show="showCancelModal" x-cloak>
        <div class="modal-box">
            <h3 style="font-size: 1.15rem; font-weight: 800; margin-bottom: 0.5rem; color: #dc2626;">{{ __('Cancel Reservation?') }}</h3>
            <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1rem;">
                {{ __('Are you sure you want to cancel this booking? This will release the table and notify the restaurant.') }}
            </p>
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem;">{{ __('Reason for cancellation (optional)') }}</label>
                <input type="text" x-model="cancelReason" class="detail-card" style="width: 100%; padding: 0.6rem; border-radius: 8px; margin-bottom: 0;" placeholder="{{ __('e.g. Schedule conflict') }}">
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <button type="button" style="flex: 1; padding: 0.75rem; border-radius: 8px; border: 1px solid var(--card-border); background: var(--input-bg); font-weight: 600; cursor: pointer;" @click="showCancelModal = false">{{ __('Keep Booking') }}</button>
                <button type="button" style="flex: 1; padding: 0.75rem; border-radius: 8px; border: none; background: #dc2626; color: #fff; font-weight: 700; cursor: pointer;" :disabled="isCancelling" @click="confirmCancellation()">
                    <span x-text="isCancelling ? '{{ __('Cancelling...') }}' : '{{ __('Yes, Cancel') }}'"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function reservationStatusApp() {
    return {
        showCancelModal: false,
        cancelReason: '',
        isCancelling: false,

        async confirmCancellation() {
            this.isCancelling = true;
            try {
                const res = await fetch(`{{ route('client.reservation.cancel', ['vendor_slug' => $vendor->slug, 'reference' => $reservation->reference]) }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        token: '{{ $reservation->uuid }}',
                        reason: this.cancelReason
                    })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    window.location.reload();
                } else {
                    alert(data.message || '{{ __('Could not cancel reservation.') }}');
                }
            } catch (err) {
                alert('{{ __('Connection error. Please try again.') }}');
            } finally {
                this.isCancelling = false;
                this.showCancelModal = false;
            }
        }
    };
}
</script>
</body>
</html>
