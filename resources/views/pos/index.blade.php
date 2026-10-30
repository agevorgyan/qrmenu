<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $vendor->name ?? 'elam.menu' }} — {{ __('Restaurant POS') }}</title>

    <!-- Google Fonts: Inter & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Alpine.js & FontAwesome Icons -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">

    <style>
        :root {
            --bg-body: #0a0e1a;
            --bg-card: #12192c;
            --bg-card-hover: #18223c;
            --bg-header: rgba(14, 20, 36, 0.95);
            --border-color: rgba(255, 255, 255, 0.08);
            --border-color-light: rgba(255, 255, 255, 0.04);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --text-subtle: #64748b;
            --primary: #f59e0b;
            --primary-hover: #d97706;
            --primary-glow: rgba(245, 158, 11, 0.25);
            --primary-gradient: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --font-main: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-display: 'Outfit', sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            user-select: none;
            -webkit-user-select: none;
            -webkit-touch-callout: none;
        }

        body {
            font-family: var(--font-main);
            background-color: var(--bg-body);
            color: var(--text-main);
            height: 100vh;
            width: 100vw;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Top POS Navigation Bar */
        .pos-header {
            height: 64px;
            background: var(--bg-header);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1rem;
            z-index: 50;
            flex-shrink: 0;
            backdrop-filter: blur(12px);
        }

        .pos-brand-group {
            display: flex;
            align-items: center;
            gap: 1rem;
            min-width: 0;
        }

        .pos-logo-badge {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.25rem;
            font-weight: 800;
            box-shadow: 0 4px 12px var(--primary-glow);
            flex-shrink: 0;
        }

        .pos-title-block {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .pos-vendor-name {
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--text-main);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pos-branch-select {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 6px;
            cursor: pointer;
            outline: none;
        }

        /* Mode Switcher Tabs */
        .pos-mode-switcher {
            display: flex;
            align-items: center;
            background: rgba(0, 0, 0, 0.35);
            padding: 4px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            gap: 4px;
        }

        .pos-mode-btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            background: transparent;
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.15s ease;
            min-height: 40px;
        }

        .pos-mode-btn:hover {
            color: var(--text-main);
            background: rgba(255, 255, 255, 0.05);
        }

        .pos-mode-btn.active {
            background: var(--primary-gradient);
            color: #ffffff;
            box-shadow: 0 2px 10px var(--primary-glow);
        }

        /* Right Actions Cluster */
        .pos-actions-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 6px 10px;
            border-radius: 9999px;
            background: rgba(16, 185, 129, 0.12);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.25);
        }

        .status-pill.offline {
            background: rgba(239, 68, 68, 0.12);
            color: #f87171;
            border-color: rgba(239, 68, 68, 0.25);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            animation: pulse-dot 2s infinite;
        }

        @keyframes pulse-dot {
            0% { opacity: 1; }
            50% { opacity: 0.4; }
            100% { opacity: 1; }
        }

        .pos-tool-btn {
            min-width: 42px;
            height: 42px;
            border-radius: 10px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .pos-tool-btn:hover {
            background: var(--bg-card-hover);
            color: var(--text-main);
            border-color: rgba(255, 255, 255, 0.15);
        }

        .pos-user-badge {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 4px 10px 4px 6px;
            border-radius: 24px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            cursor: pointer;
        }

        .pos-user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary);
            color: #000;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            overflow: hidden;
        }

        .pos-user-info {
            display: flex;
            flex-direction: column;
            line-height: 1.15;
            text-align: left;
        }

        .pos-user-name {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-main);
        }

        .pos-user-role {
            font-size: 0.65rem;
            color: var(--primary);
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        /* Main POS Stage Container */
        .pos-stage {
            flex: 1;
            display: flex;
            overflow: hidden;
            position: relative;
        }

        /* Foundation Mode Panes */
        .pos-mode-pane {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .pos-placeholder-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            max-width: 600px;
            margin: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .pos-placeholder-icon {
            font-size: 3.5rem;
            color: var(--primary);
            margin-bottom: 1.5rem;
            filter: drop-shadow(0 4px 12px var(--primary-glow));
        }

        .pos-lock-screen {
            position: absolute;
            inset: 0;
            background: rgba(10, 14, 26, 0.96);
            backdrop-filter: blur(20px);
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .pin-pad-container {
            width: 320px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.5rem;
        }

        .pin-display {
            display: flex;
            gap: 1rem;
            margin-bottom: 0.5rem;
        }

        .pin-dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid var(--primary);
            background: transparent;
            transition: all 0.15s ease;
        }

        .pin-dot.filled {
            background: var(--primary);
            box-shadow: 0 0 10px var(--primary-glow);
        }

        .pin-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            width: 100%;
        }

        .pin-key {
            height: 64px;
            border-radius: 14px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.12s ease;
        }

        .pin-key:active {
            transform: scale(0.95);
            background: var(--primary);
            color: #000;
        }
    </style>
</head>
<body x-data="posApp()" x-init="initPos()">

    <!-- 1. Header Bar -->
    <header class="pos-header">
        <!-- Brand & Branch Info -->
        <div class="pos-brand-group">
            <div class="pos-logo-badge">
                <i class="fa-solid fa-cash-register"></i>
            </div>
            <div class="pos-title-block">
                <span class="pos-vendor-name">{{ $vendor->name }}</span>
                <span style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.72rem; color: var(--text-muted);">
                    <i class="fa-solid fa-store" style="color: var(--primary);"></i>
                    <select class="pos-branch-select" x-model="activeLocationId" @change="switchBranch($event.target.value)">
                        @foreach($vendor->locations as $loc)
                            <option value="{{ $loc->id }}" {{ ($bootstrapData['active_location_id'] ?? null) == $loc->id ? 'selected' : '' }}>
                                {{ $loc->name }}
                            </option>
                        @endforeach
                    </select>
                </span>
            </div>
        </div>

        <!-- Mode Switcher -->
        <div class="pos-mode-switcher">
            <button type="button" 
                    class="pos-mode-btn" 
                    :class="{ 'active': activeMode === 'waiter' }"
                    @click="setMode('waiter')">
                <i class="fa-solid fa-user-tie"></i>
                <span>{{ __('Waiter Mode') }}</span>
            </button>
            <button type="button" 
                    class="pos-mode-btn" 
                    :class="{ 'active': activeMode === 'cashier' }"
                    @click="setMode('cashier')">
                <i class="fa-solid fa-receipt"></i>
                <span>{{ __('Cashier Mode') }}</span>
            </button>
            <button type="button" 
                    class="pos-mode-btn" 
                    :class="{ 'active': activeMode === 'manager' }"
                    @click="setMode('manager')">
                <i class="fa-solid fa-chart-line"></i>
                <span>{{ __('Manager Mode') }}</span>
            </button>
        </div>

        <!-- Right System Indicators & Tools -->
        <div class="pos-actions-group">
            <!-- Online / Offline Status Badge -->
            <div class="status-pill" :class="{ 'offline': !isOnline }">
                <span class="status-dot"></span>
                <span x-text="isOnline ? '{{ __('Online') }}' : '{{ __('Offline') }}'"></span>
            </div>

            <!-- Fullscreen Toggle -->
            <button type="button" class="pos-tool-btn" @click="toggleFullscreen()" title="{{ __('Toggle Fullscreen') }}">
                <i class="fa-solid" :class="isFullscreen ? 'fa-compress' : 'fa-expand'"></i>
            </button>

            <!-- Quick Lock Screen -->
            <button type="button" class="pos-tool-btn" @click="lockTerminal()" title="{{ __('Lock Terminal') }}">
                <i class="fa-solid fa-lock"></i>
            </button>

            <!-- Employee Profile Badge -->
            <div class="pos-user-badge" @click="showEmployeeMenu = !showEmployeeMenu">
                <div class="pos-user-avatar">
                    @if($user->avatar_url)
                        <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" style="width: 100%; height: 100%; object-fit: cover;">
                    @else
                        {{ strtoupper(substr($user->display_name ?: $user->name, 0, 1)) }}
                    @endif
                </div>
                <div class="pos-user-info">
                    <span class="pos-user-name">{{ $user->display_name ?: $user->name }}</span>
                    <span class="pos-user-role" x-text="activeMode"></span>
                </div>
                <i class="fa-solid fa-chevron-down" style="font-size: 0.65rem; color: var(--text-muted); margin-left: 2px;"></i>
            </div>

            <!-- Return to Admin -->
            <a href="{{ route('admin.dashboard') }}" class="pos-tool-btn" title="{{ __('Back to Dashboard') }}">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </a>
        </div>
    </header>

    <!-- 2. Main POS Workspace Stage -->
    <main class="pos-stage">
        <!-- Mode: Waiter -->
        <section class="pos-mode-pane" x-show="activeMode === 'waiter'" style="display: flex; padding: 2rem;">
            <div class="pos-placeholder-card">
                <i class="fa-solid fa-user-tie pos-placeholder-icon"></i>
                <h2 style="font-family: var(--font-display); font-size: 1.6rem; margin-bottom: 0.75rem;">
                    {{ __('Waiter Mode Active') }}
                </h2>
                <p style="color: var(--text-muted); font-size: 0.95rem; line-height: 1.5; margin-bottom: 1.5rem;">
                    {{ __('Interactive table management, category menu browser, and order submission interface ready for operations.') }}
                </p>
                <div style="display: flex; gap: 1rem;">
                    <button type="button" class="pos-mode-btn active" style="padding: 10px 20px;" @click="fetchOrders()">
                        <i class="fa-solid fa-rotate"></i>
                        <span>{{ __('Sync Orders') }}</span>
                    </button>
                    <a href="{{ route('admin.tables.index') }}" class="pos-mode-btn" style="border: 1px solid var(--border-color); padding: 10px 20px;">
                        <i class="fa-solid fa-table-cells"></i>
                        <span>{{ __('Manage Tables') }}</span>
                    </a>
                </div>
            </div>
        </section>

        <!-- Mode: Cashier -->
        <section class="pos-mode-pane" x-show="activeMode === 'cashier'" style="display: flex; padding: 2rem;" x-cloak>
            <div class="pos-placeholder-card">
                <i class="fa-solid fa-receipt pos-placeholder-icon"></i>
                <h2 style="font-family: var(--font-display); font-size: 1.6rem; margin-bottom: 0.75rem;">
                    {{ __('Cashier Mode Active') }}
                </h2>
                <p style="color: var(--text-muted); font-size: 0.95rem; line-height: 1.5; margin-bottom: 1.5rem;">
                    {{ __('Bill settlement, cash/card payment collection, split bill, and fiscal receipt generation.') }}
                </p>
                <button type="button" class="pos-mode-btn active" style="padding: 10px 20px;" @click="fetchOrders('ready')">
                    <i class="fa-solid fa-list-check"></i>
                    <span>{{ __('Open Unpaid Orders') }}</span>
                </button>
            </div>
        </section>

        <!-- Mode: Manager -->
        <section class="pos-mode-pane" x-show="activeMode === 'manager'" style="display: flex; padding: 2rem;" x-cloak>
            <div class="pos-placeholder-card">
                <i class="fa-solid fa-chart-line pos-placeholder-icon"></i>
                <h2 style="font-family: var(--font-display); font-size: 1.6rem; margin-bottom: 0.75rem;">
                    {{ __('Manager Mode Active') }}
                </h2>
                <p style="color: var(--text-muted); font-size: 0.95rem; line-height: 1.5; margin-bottom: 1.5rem;">
                    {{ __('Realtime operational overview, shift management, device pairing, employee performance, and overrides.') }}
                </p>
                <div style="display: flex; gap: 1rem;">
                    <a href="{{ route('admin.orders.index') }}" class="pos-mode-btn active" style="padding: 10px 20px;">
                        <i class="fa-solid fa-bell-concierge"></i>
                        <span>{{ __('Full Order Feed') }}</span>
                    </a>
                    <a href="{{ route('admin.analytics.index') }}" class="pos-mode-btn" style="border: 1px solid var(--border-color); padding: 10px 20px;">
                        <i class="fa-solid fa-chart-pie"></i>
                        <span>{{ __('Analytics') }}</span>
                    </a>
                </div>
            </div>
        </section>

        <!-- Quick PIN Lock Screen -->
        <div class="pos-lock-screen" x-show="isLocked" x-cloak>
            <div class="pin-pad-container">
                <div style="text-align: center;">
                    <div class="pos-user-avatar" style="width: 54px; height: 54px; font-size: 1.4rem; margin: 0 auto 0.75rem;">
                        {{ strtoupper(substr($user->display_name ?: $user->name, 0, 1)) }}
                    </div>
                    <h3 style="font-family: var(--font-display); font-size: 1.25rem; font-weight: 700;">
                        {{ $user->display_name ?: $user->name }}
                    </h3>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">
                        {{ __('Enter 4-digit PIN to unlock') }}
                    </p>
                </div>

                <div class="pin-display">
                    <template x-for="i in 4" :key="i">
                        <span class="pin-dot" :class="{ 'filled': enteredPin.length >= i }"></span>
                    </template>
                </div>

                <div class="pin-grid">
                    <template x-for="digit in [1, 2, 3, 4, 5, 6, 7, 8, 9]" :key="digit">
                        <button type="button" class="pin-key" @click="pressPin(digit)" x-text="digit"></button>
                    </template>
                    <button type="button" class="pin-key" style="font-size: 1rem; color: var(--danger);" @click="clearPin()">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                    <button type="button" class="pin-key" @click="pressPin(0)">0</button>
                    <button type="button" class="pin-key" style="font-size: 1rem; color: var(--primary);" @click="backspacePin()">
                        <i class="fa-solid fa-delete-left"></i>
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script>
        function posApp() {
            return {
                activeMode: '{{ $mode }}',
                activeLocationId: {{ $bootstrapData['active_location_id'] ?? 'null' }},
                isOnline: navigator.onLine,
                isFullscreen: false,
                isLocked: false,
                enteredPin: '',
                showEmployeeMenu: false,
                bootstrapData: @json($bootstrapData),

                initPos() {
                    window.addEventListener('online', () => this.isOnline = true);
                    window.addEventListener('offline', () => this.isOnline = false);

                    // Auto-lock setup from POS settings
                    const autoLockSec = this.bootstrapData.settings?.auto_lock_seconds || 0;
                    if (autoLockSec > 0) {
                        this.setupAutoLock(autoLockSec);
                    }
                },

                setMode(mode) {
                    this.activeMode = mode;
                },

                switchBranch(locationId) {
                    this.activeLocationId = locationId;
                    this.fetchBootstrap();
                },

                async fetchBootstrap() {
                    try {
                        const res = await fetch(`/pos/api/bootstrap?location_id=${this.activeLocationId}`, {
                            headers: { 'Accept': 'application/json' }
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.bootstrapData = data.data;
                        }
                    } catch (e) {
                        console.error('POS bootstrap sync failed:', e);
                    }
                },

                async fetchOrders(status = null) {
                    try {
                        let url = `/pos/api/orders?location_id=${this.activeLocationId}`;
                        if (status) url += `&status=${status}`;
                        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        const data = await res.json();
                        if (data.success) {
                            alert(`Found ${data.meta.total} orders.`);
                        }
                    } catch (e) {
                        console.error('Fetch orders error:', e);
                    }
                },

                toggleFullscreen() {
                    if (!document.fullscreenElement) {
                        document.documentElement.requestFullscreen().catch(() => {});
                        this.isFullscreen = true;
                    } else {
                        document.exitFullscreen().catch(() => {});
                        this.isFullscreen = false;
                    }
                },

                lockTerminal() {
                    this.isLocked = true;
                    this.enteredPin = '';
                },

                pressPin(digit) {
                    if (this.enteredPin.length < 6) {
                        this.enteredPin += digit.toString();
                        if (this.enteredPin.length >= 4) {
                            this.submitUnlock();
                        }
                    }
                },

                clearPin() {
                    this.enteredPin = '';
                },

                backspacePin() {
                    this.enteredPin = this.enteredPin.slice(0, -1);
                },

                submitUnlock() {
                    // For client quick unlock or server session unlock
                    setTimeout(() => {
                        this.isLocked = false;
                        this.enteredPin = '';
                    }, 300);
                },

                setupAutoLock(seconds) {
                    let timer;
                    const reset = () => {
                        clearTimeout(timer);
                        timer = setTimeout(() => {
                            if (!this.isLocked) this.lockTerminal();
                        }, seconds * 1000);
                    };
                    ['touchstart', 'mousedown', 'keydown'].forEach(evt => {
                        window.addEventListener(evt, reset, { passive: true });
                    });
                    reset();
                }
            };
        }
    </script>
</body>
</html>
