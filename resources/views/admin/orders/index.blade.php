@extends('layouts.app')

@section('title', __('Live Kitchen Orders') . ' - ' . $vendor->name)

@section('content')
<!-- Load Pusher & Laravel Echo for Reverb WebSockets -->
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.3.0/dist/web/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.65rem;">
            <span style="width: 40px; height: 40px; border-radius: 12px; background: rgba(239, 68, 68, 0.15); color: #ef4444; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                <i class="fa-solid fa-bell-concierge"></i>
            </span>
            <h1 style="font-size: 1.75rem; font-weight: 800; margin: 0; color: var(--text-main);">
                {{ __('Kitchen Orders Display') }}
            </h1>
        </div>
        <p style="color: var(--text-muted); font-size: 0.88rem; margin-top: 0.35rem;">
            {{ __('Real-time table and delivery orders for') }} <strong style="color: var(--primary);">{{ $location?->name ?? __('All Branches') }}</strong>.
        </p>
    </div>

    <!-- Live Controls: Connection Status & Sound Toggle -->
    <div style="display: flex; gap: 0.65rem; align-items: center; flex-wrap: wrap;">
        <!-- Live Status Pill -->
        <div id="liveStatusPill" style="display: flex; gap: 0.5rem; align-items: center; background: rgba(16, 185, 129, 0.12); color: #10b981; padding: 0.5rem 1rem; border-radius: 9999px; font-size: 0.82rem; font-weight: 700; border: 1px solid rgba(16, 185, 129, 0.28); transition: all 0.3s ease;">
            <span class="pulse-dot" style="width: 8px; height: 8px; background: #10b981; border-radius: 50%; display: inline-block;"></span>
            <span id="liveStatusText">{{ __('⚡ WebSockets Live') }}</span>
        </div>

        <!-- Audio Toggle Button -->
        <button id="soundToggleBtn" onclick="toggleKitchenSound()" class="btn btn-secondary" style="padding: 0.5rem 0.9rem; font-size: 0.82rem; display: flex; align-items: center; gap: 0.45rem; border-radius: 12px;">
            <i id="soundIcon" class="fa-solid fa-volume-high" style="color: var(--primary);"></i>
            <span id="soundText">{{ __('Sound On') }}</span>
        </button>

        <!-- Test Sound Button -->
        <button onclick="playKitchenChime(true)" class="btn btn-secondary" style="padding: 0.5rem 0.75rem; font-size: 0.82rem; border-radius: 12px;" title="{{ __('Test notification sound') }}">
            <i class="fa-solid fa-bell" style="color: #f59e0b;"></i>
        </button>
    </div>
</div>

<!-- New Order Alert Banner (Hidden by default, flashes when new order arrives) -->
<div id="newOrderBanner" style="display: none; background: linear-gradient(135deg, #ef4444, #f97316); color: #ffffff; padding: 0.9rem 1.25rem; border-radius: 14px; margin-bottom: 1.5rem; box-shadow: 0 10px 25px rgba(239, 68, 68, 0.4); align-items: center; justify-content: space-between; animation: slideDown 0.4s ease; flex-wrap: wrap; gap: 0.75rem;">
    <div style="display: flex; align-items: center; gap: 0.75rem; min-width: 0;">
        <span style="font-size: 1.5rem; animation: ringBell 0.8s infinite alternate;">🔔</span>
        <div style="min-width: 0;">
            <strong style="font-size: 1.05rem; font-family: 'Outfit';">{{ __('NEW ORDER RECEIVED!') }}</strong>
            <div style="font-size: 0.82rem; opacity: 0.95;" id="newOrderBannerText">{{ __('New order received from table.') }}</div>
        </div>
    </div>
    <button onclick="dismissNewOrderBanner()" style="background: rgba(255,255,255,0.25); border: none; color: #fff; padding: 0.4rem 0.9rem; border-radius: 10px; font-weight: 700; cursor: pointer;">
        {{ __('Dismiss') }}
    </button>
</div>

<!-- Audio Permission / Activation Prompt (Shown only if browser requires user gesture to allow sound) -->
<div id="audioUnlockBanner" style="display: none; background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.35); color: #f59e0b; padding: 0.75rem 1.25rem; border-radius: 14px; margin-bottom: 1.25rem; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
    <div style="display: flex; align-items: center; gap: 0.65rem;">
        <i class="fa-solid fa-volume-high" style="font-size: 1.1rem;"></i>
        <div style="font-size: 0.85rem;">
            <strong>{{ __('Order Alert Audio:') }}</strong> {{ __('Click to enable sound notifications for new orders in this browser.') }}
        </div>
    </div>
    <button type="button" onclick="enableAndTestAudio()" class="btn btn-primary" style="padding: 0.4rem 0.9rem; font-size: 0.8rem; border-radius: 10px;">
        <i class="fa-solid fa-bell"></i> {{ __('Enable Sound') }}
    </button>
</div>

<!-- Filters Bar -->
<div class="card" style="padding: 0.85rem 1rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
    <div style="display: flex; gap: 0.4rem; flex-wrap: wrap; align-items: center;">
        <a href="?status=all" class="btn {{ request('status', 'all') == 'all' ? 'btn-primary' : 'btn-secondary' }}" style="padding: 0.4rem 0.85rem; font-size: 0.8rem; border-radius: 10px;">
            {{ __('All Orders') }}
        </a>
        <a href="?status=pending" class="btn {{ request('status') == 'pending' ? 'btn-primary' : 'btn-secondary' }}" style="padding: 0.4rem 0.85rem; font-size: 0.8rem; border-radius: 10px;">
            ⏳ {{ __('Pending') }}
            @if(isset($pendingCount) && $pendingCount > 0)
                <span style="background: #ef4444; color: #fff; padding: 0.1rem 0.45rem; border-radius: 9999px; font-size: 0.7rem; margin-left: 0.35rem; font-weight: 800;">{{ $pendingCount }}</span>
            @endif
        </a>
        <a href="?status=preparing" class="btn {{ request('status') == 'preparing' ? 'btn-primary' : 'btn-secondary' }}" style="padding: 0.4rem 0.85rem; font-size: 0.8rem; border-radius: 10px;">
            🔥 {{ __('Preparing') }}
        </a>
        <a href="?status=ready" class="btn {{ request('status') == 'ready' ? 'btn-primary' : 'btn-secondary' }}" style="padding: 0.4rem 0.85rem; font-size: 0.8rem; border-radius: 10px;">
            🔔 {{ __('Ready') }}
        </a>
        <a href="?status=completed" class="btn {{ request('status') == 'completed' ? 'btn-primary' : 'btn-secondary' }}" style="padding: 0.4rem 0.85rem; font-size: 0.8rem; border-radius: 10px;">
            ✅ {{ __('Completed') }}
        </a>
    </div>

    <div id="pollInfoContainer" style="font-size: 0.8rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.4rem;">
        <span style="color: #10b981; font-weight: 600; display: inline-flex; align-items: center; gap: 0.35rem;">
            <i class="fa-solid fa-bolt text-xs"></i> <span>{{ __('Real-time (WebSockets)') }}</span>
        </span>
    </div>
</div>

<!-- Waiter Calls & Bill Requests Container -->
<div id="waiterCallsContainer">
    @include('admin.orders.partials.waiter_call_cards', ['waiterCalls' => $waiterCalls])
</div>

<!-- Orders Grid Container -->
<div id="ordersContainer">
    @include('admin.orders.partials.order_cards', ['orders' => $orders, 'vendor' => $vendor])
</div>

<!-- Thermal Receipt Print Modal -->
<div id="thermalReceiptModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(6px); padding: 1rem;">
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; max-width: 480px; width: 100%; box-shadow: 0 25px 50px rgba(0,0,0,0.3); overflow: hidden; display: flex; flex-direction: column; max-height: 90vh;">
        <!-- Modal Header -->
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 0.6rem;">
                <span style="width: 36px; height: 36px; border-radius: 10px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                    <i class="fa-solid fa-print"></i>
                </span>
                <div>
                    <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: var(--text-main);" id="receiptModalTitle">{{ __('Print Receipt') }}</h3>
                    <div style="font-size: 0.78rem; color: var(--text-muted);">{{ __('ESC/POS Thermal Printer') }}</div>
                </div>
            </div>
            <button type="button" onclick="closeThermalReceiptModal()" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Receipt Mono Preview -->
        <div style="padding: 1.25rem; overflow-y: auto; flex: 1; background: #fff; color: #111;">
            <pre id="receiptTextPreview" style="font-family: 'Courier New', Courier, monospace; font-size: 0.82rem; line-height: 1.35; white-space: pre-wrap; word-break: break-word; margin: 0; padding: 0.75rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; color: #111;"></pre>
        </div>

        <!-- Print Actions Footer -->
        <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--border-color); display: flex; flex-direction: column; gap: 0.6rem; background: var(--bg-body);">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem;">
                <button type="button" onclick="printViaBluetooth()" id="btnBluetoothPrint" class="btn btn-primary" style="display: flex; align-items: center; justify-content: center; gap: 0.4rem; padding: 0.65rem; font-size: 0.82rem; border-radius: 12px;">
                    <i class="fa-brands fa-bluetooth-b"></i> {{ __('Web Bluetooth') }}
                </button>
                <a id="btnRawBtPrint" href="#" class="btn btn-secondary" style="display: flex; align-items: center; justify-content: center; gap: 0.4rem; padding: 0.65rem; font-size: 0.82rem; border-radius: 12px; text-decoration: none;">
                    <i class="fa-solid fa-mobile-screen"></i> {{ __('RawBT (Android)') }}
                </a>
            </div>
            <button type="button" onclick="printViaBrowser()" class="btn btn-secondary" style="display: flex; align-items: center; justify-content: center; gap: 0.4rem; padding: 0.65rem; font-size: 0.85rem; border-radius: 12px;">
                <i class="fa-solid fa-print"></i> {{ __('Print via Browser (System Dialog)') }}
            </button>
        </div>
    </div>
</div>

<style>
@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.08); opacity: 0.85; }
    100% { transform: scale(1); opacity: 1; }
}
@keyframes ringBell {
    0% { transform: rotate(-15deg); }
    100% { transform: rotate(15deg); }
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}
.pulse-dot {
    animation: pulse 1.5s infinite;
}
</style>

<script>
// Sound Settings (Saved in LocalStorage)
let isSoundEnabled = localStorage.getItem('kitchen_sound_enabled') !== 'false';
let lastOrderId = {{ $orders->first()?->id ?? 0 }};
let isWebSocketConnected = false;
let pollSecondsRemaining = 6;
let isPolling = false;
let audioCtx = null;
let pollTimer = null;

const orderTranslations = {
    soundOn: @json(__('Sound On')),
    soundOff: @json(__('Sound Off')),
    wsLive: @json(__('⚡ WebSockets Live')),
    wsActive: @json(__('⚡ WebSockets (Reverb) Active')),
    wsConnecting: @json(__('⚡ WebSockets Connecting...')),
    pollingMode: @json(__('⚠️ Polling Mode')),
    pollingActive: @json(__('Polling active')),
    connectionError: @json(__('Connection error (retrying...)')),
    realTimeWs: @json(__('Real-time (WebSockets)')),
    nextUpdateIn: @json(__('Next update in:')),
    newOrderReceived: @json(__('New order received')),
    pendingColon: @json(__('pending:')),
    newOrderExcl: @json(__('NEW ORDER!')),
    liveKitchenOrders: @json(__('Live Kitchen Orders')),
    orderStatusUpdateFailed: @json(__('Failed to update order status.')),
    sessionExpired: @json(__('Session expired or error occurred.')),
    receiptLoadFailed: @json(__('Failed to load receipt.')),
    receipt: @json(__('Receipt')),
    callFrom: @json(__('Call from')),
    table: @json(__('Table')),
    takeaway: @json(__('Takeaway')),
    totalColon: @json(__('Total:')),
};

// Initialize Sound Button
function updateSoundUI() {
    const icon = document.getElementById('soundIcon');
    const text = document.getElementById('soundText');
    if (isSoundEnabled) {
        icon.className = 'fa-solid fa-volume-high';
        icon.style.color = '#10b981';
        text.innerText = orderTranslations.soundOn;
    } else {
        icon.className = 'fa-solid fa-volume-xmark';
        icon.style.color = '#ef4444';
        text.innerText = orderTranslations.soundOff;
    }
}
updateSoundUI();

function toggleKitchenSound() {
    isSoundEnabled = !isSoundEnabled;
    localStorage.setItem('kitchen_sound_enabled', isSoundEnabled);
    updateSoundUI();
    if (isSoundEnabled) {
        playKitchenChime(true);
    }
}

// Audio Context Setup with Autoplay Safeguards
function getAudioContext() {
    if (!audioCtx) {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (AudioContextClass) {
            audioCtx = new AudioContextClass();
        }
    }
    return audioCtx;
}

function unlockAudio() {
    const ctx = getAudioContext();
    if (ctx && ctx.state === 'suspended') {
        ctx.resume().then(() => {
            const banner = document.getElementById('audioUnlockBanner');
            if (banner) banner.style.display = 'none';
        }).catch(err => {
            console.warn('Audio resume note:', err);
        });
    } else if (ctx && ctx.state === 'running') {
        const banner = document.getElementById('audioUnlockBanner');
        if (banner) banner.style.display = 'none';
    }
}

function checkAudioStatus() {
    try {
        const ctx = getAudioContext();
        if (ctx && ctx.state === 'suspended') {
            const banner = document.getElementById('audioUnlockBanner');
            if (banner) banner.style.display = 'flex';
        }
    } catch (e) {
        console.warn('Audio status check:', e);
    }
}

function enableAndTestAudio() {
    unlockAudio();
    playKitchenChime(true);
    const banner = document.getElementById('audioUnlockBanner');
    if (banner) banner.style.display = 'none';
}

// Unlock audio on any user gesture anywhere on screen
['click', 'touchstart', 'pointerdown', 'keydown'].forEach(evt => {
    document.addEventListener(evt, unlockAudio, { passive: true });
});

// Crisp Restaurant Bell Chime using Web Audio API (No external file needed)
function playKitchenChime(force = false) {
    if (!isSoundEnabled && !force) return;

    try {
        const ctx = getAudioContext();
        if (!ctx) return;

        if (ctx.state === 'suspended') {
            ctx.resume();
        }

        const now = ctx.currentTime;

        // Tone 1: High crisp chime (E5 - 659.25Hz)
        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(659.25, now);
        gain1.gain.setValueAtTime(0.4, now);
        gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.9);
        osc1.connect(gain1);
        gain1.connect(ctx.destination);
        osc1.start(now);
        osc1.stop(now + 0.9);

        // Tone 2: Harmonic Brass Ding (B5 - 987.77Hz)
        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'triangle';
        osc2.frequency.setValueAtTime(987.77, now + 0.12);
        gain2.gain.setValueAtTime(0.5, now + 0.12);
        gain2.gain.exponentialRampToValueAtTime(0.001, now + 1.4);
        osc2.connect(gain2);
        gain2.connect(ctx.destination);
        osc2.start(now + 0.12);
        osc2.stop(now + 1.4);

        // Tone 3: Rich Octave resonance (E6 - 1318.5Hz)
        const osc3 = ctx.createOscillator();
        const gain3 = ctx.createGain();
        osc3.type = 'sine';
        osc3.frequency.setValueAtTime(1318.5, now + 0.25);
        gain3.gain.setValueAtTime(0.35, now + 0.25);
        gain3.gain.exponentialRampToValueAtTime(0.001, now + 1.6);
        osc3.connect(gain3);
        gain3.connect(ctx.destination);
        osc3.start(now + 0.25);
        osc3.stop(now + 1.6);

    } catch (e) {
        console.warn('Audio Context notification failed:', e);
    }
}

function dismissNewOrderBanner() {
    document.getElementById('newOrderBanner').style.display = 'none';
}

// Fetch Live Orders via AJAX Feed
async function fetchKitchenFeed(silent = false) {
    if (isPolling) return;
    isPolling = true;

    try {
        const currentUrl = new URL(window.location.href);
        const status = currentUrl.searchParams.get('status') || 'all';
        const feedUrl = `{{ route('admin.orders.feed') }}?status=${encodeURIComponent(status)}&last_order_id=${lastOrderId}`;

        const res = await fetch(feedUrl, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });

        if (res.ok) {
            const contentType = res.headers.get("content-type");
            if (contentType && contentType.indexOf("application/json") === -1) {
                console.warn('Session expired or HTML returned, reloading...');
                window.location.reload();
                return;
            }
            const data = await res.json();
            if (data.success) {
                // If new order arrived
                if (data.has_new && data.latest_order_id > lastOrderId) {
                    playKitchenChime();

                    // Show Banner
                    const banner = document.getElementById('newOrderBanner');
                    const bannerText = document.getElementById('newOrderBannerText');
                    if (banner && bannerText) {
                        bannerText.innerText = `${orderTranslations.newOrderReceived} (${orderTranslations.pendingColon} ${data.pending_count}).`;
                        banner.style.display = 'flex';
                    }

                    // Update Title with Alert
                    document.title = `(1) 🔔 ${orderTranslations.newOrderExcl} - ${@json($vendor->name)}`;
                    setTimeout(() => {
                        document.title = `${orderTranslations.liveKitchenOrders} - ${@json($vendor->name)}`;
                    }, 8000);
                }

                // Update container with rendered HTML only if changed or has new content
                const ordersEl = document.getElementById('ordersContainer');
                if (ordersEl && (data.has_new || ordersEl.innerHTML !== data.html)) {
                    ordersEl.innerHTML = data.html;
                }
                const waiterCallsEl = document.getElementById('waiterCallsContainer');
                if (waiterCallsEl && data.waiter_calls_html !== undefined && waiterCallsEl.innerHTML !== data.waiter_calls_html) {
                    waiterCallsEl.innerHTML = data.waiter_calls_html;
                }
                lastOrderId = Math.max(lastOrderId, data.latest_order_id);

                // Update status text only when in fallback polling mode
                if (!isWebSocketConnected) {
                    const statusText = document.getElementById('liveStatusText');
                    if (statusText) {
                        statusText.innerText = `${orderTranslations.pollingActive} (${new Date().toLocaleTimeString()})`;
                    }
                }
            }
        }
    } catch (err) {
        console.error('Kitchen live feed error:', err);
        if (!isWebSocketConnected) {
            const statusText = document.getElementById('liveStatusText');
            if (statusText) {
                statusText.innerText = orderTranslations.connectionError;
            }
        }
    } finally {
        isPolling = false;
        pollSecondsRemaining = isWebSocketConnected ? 120 : 6;
    }
}

// Mark Waiter Call / Bill Request Attended
async function markWaiterCallAttended(callId) {
    try {
        const res = await fetch(`/admin/waiter-calls/${callId}/status`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify({ status: 'attended' })
        });

        if (res.ok) {
            fetchKitchenFeed(true);
        }
    } catch (e) {
        console.error('Waiter call update error:', e);
    }
}

// Status update with AJAX
async function changeOrderStatus(orderId, newStatus) {
    try {
        const res = await fetch(`/admin/orders/${orderId}/status`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify({ status: newStatus })
        });

        if (res.ok) {
            // Silently refresh the list
            fetchKitchenFeed(true);
        } else {
            alert(orderTranslations.orderStatusUpdateFailed);
        }
    } catch (e) {
        console.error('Order status update error:', e);
    }
}

// Polling & Heartbeat Timer
function startPollingTimer() {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(() => {
        if (isWebSocketConnected) {
            // While WebSocket is connected, no fast polling and no countdown UI!
            pollSecondsRemaining--;
            if (pollSecondsRemaining <= 0) {
                pollSecondsRemaining = 120; // 2 minutes background safety check
                fetchKitchenFeed(true);
            }
            return;
        }

        // Only run countdown and 6-second polling if WebSocket is NOT connected
        pollSecondsRemaining--;
        const counterEl = document.getElementById('pollCounter');
        if (pollSecondsRemaining <= 0) {
            if (counterEl) counterEl.innerText = '...';
            pollSecondsRemaining = 6;
            fetchKitchenFeed(false);
        } else {
            if (counterEl) counterEl.innerText = pollSecondsRemaining;
        }
    }, 1000);
}

// Initialize Reverb WebSockets with Echo
function initEcho() {
    try {
        if (typeof Echo === 'undefined') {
            console.warn('Echo library not found, activating fallback polling.');
            updateConnectionStatus(false);
            return;
        }

        const reverbKey = @json(config("broadcasting.connections.reverb.key") ?? env("REVERB_APP_KEY", ""));
        // Auto-match browser hostname (handles localhost, 127.0.0.1, or remote domain seamlessly)
        const reverbHost = window.location.hostname || @json(config("broadcasting.connections.reverb.options.host") ?? env("REVERB_HOST", "localhost"));
        const reverbPort = {{ config("broadcasting.connections.reverb.options.port") ?? env("REVERB_PORT", 8080) }};
        const reverbScheme = window.location.protocol === 'https:' ? 'https' : @json(config("broadcasting.connections.reverb.options.scheme") ?? env("REVERB_SCHEME", "http"));

        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: reverbHost,
            wsPort: reverbPort,
            wssPort: reverbPort,
            forceTLS: reverbScheme === 'https',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
            auth: {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            }
        });

        // Listen on vendor's private channel
        window.Echo.private(`vendor.${@json($vendor->id)}`)
            .listen('.OrderCreated', (data) => {
                handleWebSocketOrderCreated(data);
            })
            .listen('OrderCreated', (data) => {
                handleWebSocketOrderCreated(data);
            })
            .listen('.WaiterCalled', (data) => {
                handleWebSocketWaiterCalled(data);
            })
            .listen('WaiterCalled', (data) => {
                handleWebSocketWaiterCalled(data);
            })
            .listen('.OrderStatusUpdated', (data) => {
                fetchKitchenFeed(true);
            })
            .listen('OrderStatusUpdated', (data) => {
                fetchKitchenFeed(true);
            });

        if (window.Echo.connector && window.Echo.connector.pusher) {
            window.Echo.connector.pusher.connection.bind('connected', () => {
                updateConnectionStatus(true);
            });

            window.Echo.connector.pusher.connection.bind('connecting', () => {
                const text = document.getElementById('liveStatusText');
                if (text && !isWebSocketConnected) {
                    text.innerText = orderTranslations.wsConnecting;
                }
            });

            window.Echo.connector.pusher.connection.bind('disconnected', () => {
                updateConnectionStatus(false);
            });

            window.Echo.connector.pusher.connection.bind('unavailable', () => {
                updateConnectionStatus(false);
            });

            window.Echo.connector.pusher.connection.bind('failed', () => {
                updateConnectionStatus(false);
            });
        }
    } catch (err) {
        console.warn('Echo initialization note:', err);
        updateConnectionStatus(false);
    }
}

function updateConnectionStatus(connected) {
    isWebSocketConnected = connected;
    const pill = document.getElementById('liveStatusPill');
    const text = document.getElementById('liveStatusText');
    const dot = pill ? pill.querySelector('.pulse-dot') : null;
    const pollInfo = document.getElementById('pollInfoContainer');

    if (connected) {
        if (pill) {
            pill.style.background = 'rgba(16, 185, 129, 0.15)';
            pill.style.color = '#10b981';
            pill.style.borderColor = 'rgba(16, 185, 129, 0.3)';
        }
        if (dot) {
            dot.style.background = '#10b981';
            dot.className = 'pulse-dot';
        }
        if (text) text.innerHTML = orderTranslations.wsActive;
        if (pollInfo) {
            pollInfo.innerHTML = '<span style="color: #10b981; font-weight: 600; display: inline-flex; align-items: center; gap: 0.35rem;"><i class="fa-solid fa-bolt text-xs"><\/i> <span>' + orderTranslations.realTimeWs + '<\/span><\/span>';
        }
        pollSecondsRemaining = 120;
    } else {
        if (pill) {
            pill.style.background = 'rgba(245, 158, 11, 0.15)';
            pill.style.color = '#f59e0b';
            pill.style.borderColor = 'rgba(245, 158, 11, 0.3)';
        }
        if (dot) {
            dot.style.background = '#f59e0b';
            dot.className = '';
        }
        if (text) text.innerHTML = orderTranslations.pollingMode;
        if (pollInfo) {
            pollInfo.innerHTML = '<span style="color: #f59e0b; display: inline-flex; align-items: center; gap: 0.35rem;"><i class="fa-solid fa-rotate text-xs"><\/i> <span>' + orderTranslations.nextUpdateIn + ' <strong id="pollCounter">' + pollSecondsRemaining + '<\/strong>s<\/span><\/span>';
        }
        pollSecondsRemaining = 6;
    }
}

function handleWebSocketOrderCreated(data) {
    playKitchenChime();
    const banner = document.getElementById('newOrderBanner');
    const bannerText = document.getElementById('newOrderBannerText');
    if (banner && bannerText) {
        bannerText.innerText = `${orderTranslations.newOrderReceived} #${data.order_number} (${data.table_number ? orderTranslations.table + ' ' + data.table_number : orderTranslations.takeaway}) — ${orderTranslations.totalColon} ${Number(data.total_amount).toLocaleString()} ${@json($vendor->currency)}.`;
        banner.style.display = 'flex';
    }
    document.title = `(1) 🔔 ${orderTranslations.newOrderExcl} #${data.order_number}!`;
    setTimeout(() => {
        document.title = `${orderTranslations.liveKitchenOrders} - ` + @json($vendor->name);
    }, 8000);
    @if(!empty($vendor->getThermalPrinterSettings()['auto_print_live_orders']))
    if (data.order_id) {
        printOrderReceipt(data.order_id, true);
    }
    @endif
    fetchKitchenFeed(true);
}

function handleWebSocketWaiterCalled(data) {
    playKitchenChime();
    const banner = document.getElementById('newOrderBanner');
    const bannerText = document.getElementById('newOrderBannerText');
    if (banner && bannerText) {
        bannerText.innerText = `🔔 ${orderTranslations.callFrom} ${data.table_number ? orderTranslations.table + ' ' + data.table_number : orderTranslations.table}: ${data.type_label}.`;
        banner.style.display = 'flex';
    }
    fetchKitchenFeed(true);
}

let currentReceiptText = '';
let currentReceiptOrderNumber = '';

async function printOrderReceipt(orderId, isAuto = false) {
    try {
        const res = await fetch(`/admin/orders/${orderId}/receipt-text`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        const contentType = res.headers.get("content-type");
        if (contentType && contentType.indexOf("application/json") === -1) {
            alert(orderTranslations.sessionExpired);
            return;
        }
        const data = await res.json();
        if (data.success) {
            currentReceiptText = data.receipt_text;
            currentReceiptOrderNumber = data.order_number;

            document.getElementById('receiptModalTitle').textContent = `${orderTranslations.receipt} #${data.order_number}`;
            document.getElementById('receiptTextPreview').textContent = data.receipt_text;
            document.getElementById('btnRawBtPrint').href = data.rawbt_url;
            document.getElementById('thermalReceiptModal').style.display = 'flex';

            if (isAuto) {
                printViaBrowser();
            }
        }
    } catch (e) {
        console.error('Failed to load receipt:', e);
        alert(orderTranslations.receiptLoadFailed);
    }
}

function closeThermalReceiptModal() {
    document.getElementById('thermalReceiptModal').style.display = 'none';
}

function printViaBrowser() {
    if (!currentReceiptText) return;

    let iframe = document.getElementById('thermal_receipt_print_frame');
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.id = 'thermal_receipt_print_frame';
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        document.body.appendChild(iframe);
    }

    try {
        const doc = iframe.contentWindow.document;
        const safeReceipt = (currentReceiptText || '')
            .replace(new RegExp('<', 'g'), '&lt;')
            .replace(new RegExp('>', 'g'), '&gt;');

        doc.write([
            '<!DOCTYPE html>',
            '<html>',
            '<head>',
            '<title>Receipt ' + (currentReceiptOrderNumber || '') + '<\/title>',
            '<style>',
            '@page { margin: 0; size: auto; }',
            'body { font-family: "Courier New", Courier, monospace; font-size: 12px; line-height: 1.35; margin: 8px; padding: 0; color: #000; width: 76mm; }',
            'pre { white-space: pre-wrap; word-break: break-word; margin: 0; font-family: inherit; }',
            '<\/style>',
            '<\/head>',
            '<body>',
            '<pre>' + safeReceipt + '<\/pre>',
            '<\/body>',
            '<\/html>'
        ].join(''));
        doc.close();

        setTimeout(() => {
            try {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
            } catch (e) {
                console.error('Print dialog error:', e);
            }
        }, 300);
    } catch (e) {
        console.error('Failed to prepare print iframe:', e);
    }
}

async function printViaBluetooth() {
    if (!navigator.bluetooth) {
        alert('Web Bluetooth API is not supported in this browser (please use Chrome or Edge).');
        return;
    }

    const btn = document.getElementById('btnBluetoothPrint');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"><\/i> Connecting...';

    try {
        const device = await navigator.bluetooth.requestDevice({
            acceptAllDevices: true,
            optionalServices: [
                '000018f0-0000-1000-8000-00805f9b34fb',
                'e7810a71-73ae-499d-8c15-faa9aef0c3f2',
                '49535343-fe7d-4ae5-8fa9-9fafd205e455'
            ]
        });

        const server = await device.gatt.connect();
        const services = await server.getPrimaryServices();
        if (services.length === 0) {
            throw new Error('No services found on bluetooth printer');
        }

        const service = services[0];
        const characteristics = await service.getCharacteristics();
        const writeChar = characteristics.find(c => c.properties.write || c.properties.writeWithoutResponse);

        if (!writeChar) {
            throw new Error('Writable characteristic not found');
        }

        const encoder = new TextEncoder();
        const initCmd = new Uint8Array([0x1B, 0x40]);
        const cutCmd = new Uint8Array([0x1D, 0x56, 0x41, 0x10]);
        const textBytes = encoder.encode(currentReceiptText + "\n\n\n");

        await writeChar.writeValue(initCmd);
        await writeChar.writeValue(textBytes);
        await writeChar.writeValue(cutCmd);

        alert('Receipt sent to Bluetooth printer successfully.');
        closeThermalReceiptModal();
    } catch (e) {
        console.warn('Bluetooth print:', e);
        if (e.name !== 'NotFoundError') {
            alert('Bluetooth printer connection failed. You can use RawBT or Browser Print instead.');
        }
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-brands fa-bluetooth-b"><\/i> Web Bluetooth';
    }
}

let kitchenWakeLock = null;
async function acquireKitchenWakeLock() {
    if ('wakeLock' in navigator) {
        try {
            kitchenWakeLock = await navigator.wakeLock.request('screen');
        } catch (err) {
            console.debug('Screen wake lock not granted:', err);
        }
    }
}

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && !kitchenWakeLock) {
        acquireKitchenWakeLock();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    initEcho();
    startPollingTimer();
    checkAudioStatus();
    acquireKitchenWakeLock();
});
</script>
@endsection
