<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $vendor->name ?? 'elam.menu' }} — {{ __('Kitchen Display System (KDS)') }}</title>

    <link rel="manifest" href="/pos-manifest.json">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#070a12">
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/pos-sw.js').catch(() => {});
            });
        }
    </script>

    <!-- Google Fonts: Inter & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Alpine.js & FontAwesome Icons -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">

    <style>
        :root {
            --bg-body: #070a12;
            --bg-card: #0f172a;
            --bg-card-header: #1e293b;
            --bg-card-hover: #182234;
            --bg-header: rgba(15, 23, 42, 0.95);
            --border-color: rgba(255, 255, 255, 0.1);
            --border-color-light: rgba(255, 255, 255, 0.05);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --text-subtle: #64748b;
            --primary: #f59e0b;
            --primary-hover: #d97706;
            --primary-glow: rgba(245, 158, 11, 0.25);
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

        /* Header Bar */
        .kds-header {
            height: 64px;
            background: var(--bg-header);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.25rem;
            z-index: 50;
            flex-shrink: 0;
        }

        .kds-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .kds-logo-badge {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: #fff;
            box-shadow: 0 4px 12px var(--primary-glow);
        }

        .kds-title {
            font-family: var(--font-display);
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.02em;
        }

        .kds-station-tabs {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(0, 0, 0, 0.4);
            padding: 0.25rem;
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }

        .kds-tab-btn {
            border: none;
            background: transparent;
            color: var(--text-muted);
            padding: 0.45rem 0.9rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .kds-tab-btn:hover {
            color: var(--text-main);
            background: rgba(255, 255, 255, 0.05);
        }

        .kds-tab-btn.active {
            background: var(--primary);
            color: #000;
            font-weight: 700;
            box-shadow: 0 2px 8px var(--primary-glow);
        }

        .kds-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .kds-tool-btn {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.15s ease;
        }

        .kds-tool-btn:hover {
            background: rgba(255, 255, 255, 0.12);
            color: var(--text-main);
        }

        .kds-tool-btn.active {
            color: var(--primary);
            border-color: var(--primary);
        }

        /* Main Workspace: Ticket Grid */
        .kds-board {
            flex: 1;
            padding: 1.25rem;
            overflow-x: auto;
            overflow-y: hidden;
            display: flex;
            gap: 1.25rem;
            align-items: stretch;
        }

        .kds-ticket-column {
            width: 340px;
            min-width: 340px;
            height: 100%;
            display: flex;
            flex-direction: column;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
            transition: transform 0.15s ease, border-color 0.15s ease;
        }

        .kds-ticket-column.priority-normal {
            border-top: 4px solid var(--success);
        }

        .kds-ticket-column.priority-warning {
            border-top: 4px solid var(--warning);
        }

        .kds-ticket-column.priority-delayed {
            border-top: 4px solid var(--danger);
            animation: pulse-danger 2s infinite ease-in-out;
        }

        @keyframes pulse-danger {
            0%, 100% { box-shadow: 0 0 0 rgba(239, 68, 68, 0); }
            50% { box-shadow: 0 0 16px rgba(239, 68, 68, 0.35); }
        }

        /* Ticket Header */
        .ticket-header {
            padding: 0.9rem 1rem;
            background: var(--bg-card-header);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .ticket-meta-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .ticket-order-num {
            font-family: var(--font-display);
            font-size: 1.25rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.01em;
        }

        .ticket-timer-badge {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            font-family: var(--font-display);
            font-size: 0.95rem;
            font-weight: 800;
            letter-spacing: 0.02em;
        }

        .timer-normal {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.4);
        }

        .timer-warning {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.4);
        }

        .timer-delayed {
            background: rgba(239, 68, 68, 0.25);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.5);
        }

        .ticket-details-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .ticket-table-name {
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        /* Special Notes Banner */
        .ticket-order-notes {
            margin: 0.5rem 1rem 0;
            padding: 0.45rem 0.65rem;
            background: rgba(245, 158, 11, 0.12);
            border-left: 3px solid var(--warning);
            border-radius: 4px;
            font-size: 0.78rem;
            color: #fde68a;
            display: flex;
            align-items: flex-start;
            gap: 0.4rem;
        }

        /* Items Scroll Area */
        .ticket-items-list {
            flex: 1;
            overflow-y: auto;
            padding: 0.75rem 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .ticket-item-row {
            padding: 0.6rem 0.75rem;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color-light);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s ease;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .ticket-item-row:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: var(--border-color);
        }

        .ticket-item-row.status-ready {
            background: rgba(16, 185, 129, 0.08);
            border-color: rgba(16, 185, 129, 0.3);
            opacity: 0.7;
        }

        .ticket-item-row.status-ready .item-title {
            text-decoration: line-through;
            color: var(--text-muted);
        }

        .item-main-line {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .item-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex: 1;
        }

        .item-qty-badge {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            padding: 0.15rem 0.45rem;
            border-radius: 5px;
            font-size: 0.85rem;
            font-weight: 800;
            min-width: 28px;
            text-align: center;
        }

        .ticket-item-row.status-ready .item-qty-badge {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
        }

        .item-title {
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text-main);
        }

        .item-status-icon {
            font-size: 1rem;
            color: var(--text-subtle);
        }

        .ticket-item-row.status-ready .item-status-icon {
            color: var(--success);
        }

        /* Modifiers and Notes */
        .item-modifiers {
            display: flex;
            flex-wrap: wrap;
            gap: 0.3rem;
            padding-left: 2.25rem;
        }

        .mod-tag {
            font-size: 0.72rem;
            font-weight: 700;
            padding: 0.15rem 0.4rem;
            border-radius: 4px;
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.35);
        }

        .item-custom-note {
            font-size: 0.74rem;
            color: #93c5fd;
            padding-left: 2.25rem;
            font-style: italic;
        }

        /* Ticket Footer Actions */
        .ticket-footer {
            padding: 0.75rem 1rem;
            background: var(--bg-card-header);
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-bump {
            flex: 1;
            padding: 0.65rem 0.8rem;
            border: none;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }

        .btn-bump-ready {
            background: var(--success);
            color: #000;
        }

        .btn-bump-ready:hover {
            filter: brightness(1.1);
        }

        .btn-bump-served {
            background: var(--primary);
            color: #000;
        }

        .btn-bump-served:hover {
            filter: brightness(1.1);
        }

        .btn-ticket-tool {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 0.65rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-ticket-tool:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
        }

        /* Empty State */
        .kds-empty-state {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            color: var(--text-muted);
        }

        .kds-empty-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: var(--primary);
        }

        /* Audio Alert Indicator */
        .audio-pill {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            cursor: pointer;
        }

        .audio-pill.active {
            color: var(--success);
            border-color: rgba(16, 185, 129, 0.4);
            background: rgba(16, 185, 129, 0.1);
        }
    </style>
</head>
<body x-data="kdsApp()" x-init="initKds()">

    <!-- 1. Header Bar -->
    <header class="kds-header">
        <div class="kds-brand">
            <div class="kds-logo-badge">
                <i class="fa-solid fa-fire-burner"></i>
            </div>
            <div>
                <div class="kds-title">{{ $vendor->name }}</div>
                <div style="font-size: 0.72rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.35rem;">
                    <i class="fa-solid fa-kitchen-set" style="color: var(--primary);"></i>
                    <span>{{ __('Kitchen Display System') }}</span>
                    <span>•</span>
                    <span x-text="tickets.length + ' {{ __('active orders') }}'"></span>
                </div>
            </div>
        </div>

        <!-- Station Selector Tabs -->
        <div class="kds-station-tabs">
            <button type="button" 
                    class="kds-tab-btn" 
                    :class="{ 'active': activeStation === 'all' }" 
                    @click="setStation('all')">
                <i class="fa-solid fa-layer-group"></i>
                <span>{{ __('All Stations') }}</span>
                <span class="badge" style="background: rgba(0,0,0,0.3); padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.7rem;" x-text="tickets.length"></span>
            </button>

            @foreach($stations as $st)
                <button type="button" 
                        class="kds-tab-btn" 
                        :class="{ 'active': activeStation === '{{ $st->slug }}' }" 
                        @click="setStation('{{ $st->slug }}')">
                    @if($st->slug === 'bar')
                        <i class="fa-solid fa-martini-glass"></i>
                    @elseif($st->slug === 'coffee')
                        <i class="fa-solid fa-mug-hot"></i>
                    @elseif($st->slug === 'dessert')
                        <i class="fa-solid fa-ice-cream"></i>
                    @else
                        <i class="fa-solid fa-utensils"></i>
                    @endif
                    <span>{{ $st->name }}</span>
                </button>
            @endforeach
        </div>

        <!-- Tools & Options -->
        <div class="kds-actions">
            <!-- Audio Chime Toggle -->
            <button type="button" 
                    class="audio-pill" 
                    :class="{ 'active': soundEnabled }" 
                    @click="toggleSound()" 
                    title="{{ __('Toggle Sound Alert') }}">
                <i class="fa-solid" :class="soundEnabled ? 'fa-volume-high' : 'fa-volume-xmark'"></i>
                <span x-text="soundEnabled ? '{{ __('Sound On') }}' : '{{ __('Muted') }}'"></span>
            </button>

            <!-- Manual Refresh -->
            <button type="button" class="kds-tool-btn" @click="fetchTickets()" title="{{ __('Refresh Orders') }}">
                <i class="fa-solid fa-arrows-rotate" :class="{ 'fa-spin': isRefreshing }"></i>
            </button>

            <!-- Fullscreen -->
            <button type="button" class="kds-tool-btn" @click="toggleFullscreen()" title="{{ __('Fullscreen') }}">
                <i class="fa-solid" :class="isFullscreen ? 'fa-compress' : 'fa-expand'"></i>
            </button>

            <!-- Back to POS Terminal -->
            <a href="{{ route('pos.index') }}" class="kds-tool-btn" title="{{ __('Back to POS Terminal') }}" style="text-decoration: none; color: var(--primary);">
                <i class="fa-solid fa-cash-register"></i>
            </a>
        </div>
    </header>

    <!-- 2. Main Workspace -->
    <main class="kds-board">
        <!-- If No Active Tickets -->
        <template x-if="tickets.length === 0 && !isRefreshing">
            <div class="kds-empty-state">
                <div class="kds-empty-icon">
                    <i class="fa-solid fa-check-double"></i>
                </div>
                <h2 style="font-family: var(--font-display); font-size: 1.5rem; color: #fff;">{{ __('All Orders Clear!') }}</h2>
                <p style="font-size: 0.9rem; max-width: 360px; text-align: center;">
                    {{ __('There are no active orders currently awaiting preparation. New orders will appear here automatically with audio alert.') }}
                </p>
            </div>
        </template>

        <!-- Active Tickets Columns -->
        <template x-for="ticket in tickets" :key="ticket.order_id">
            <div class="kds-ticket-column" :class="'priority-' + ticket.priority">
                <!-- Ticket Header -->
                <div class="ticket-header">
                    <div class="ticket-meta-row">
                        <span class="ticket-order-num" x-text="'#' + ticket.order_number"></span>
                        <div class="ticket-timer-badge" :class="'timer-' + ticket.priority">
                            <i class="fa-regular fa-clock"></i>
                            <span x-text="ticket.elapsed_minutes + 'm'"></span>
                        </div>
                    </div>
                    <div class="ticket-details-row">
                        <span class="ticket-table-name">
                            <i class="fa-solid fa-chair" style="color: var(--primary);"></i>
                            <span x-text="ticket.table_name || 'Takeaway'"></span>
                            <template x-if="ticket.guest_count">
                                <span style="color: var(--text-muted); font-size: 0.72rem;" x-text="'(' + ticket.guest_count + 'p)'"></span>
                            </template>
                        </span>
                        <span style="display: flex; align-items: center; gap: 0.25rem;">
                            <i class="fa-solid fa-user-tie"></i>
                            <span x-text="ticket.waiter_name"></span>
                        </span>
                    </div>
                </div>

                <!-- Special Order Notes -->
                <template x-if="ticket.notes">
                    <div class="ticket-order-notes">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span x-text="ticket.notes"></span>
                    </div>
                </template>

                <!-- Ticket Items List -->
                <div class="ticket-items-list">
                    <template x-for="item in ticket.items" :key="item.id">
                        <div class="ticket-item-row" 
                             :class="{ 'status-ready': item.status === 'ready' || item.status === 'served' }"
                             @click="toggleItemStatus(ticket, item)">
                            <div class="item-main-line">
                                <div class="item-info">
                                    <span class="item-qty-badge" x-text="item.quantity"></span>
                                    <span class="item-title" x-text="item.product_name + (item.variation_name ? ' (' + item.variation_name + ')' : '')"></span>
                                </div>
                                <div class="item-status-icon">
                                    <template x-if="item.status === 'ready' || item.status === 'served'">
                                        <i class="fa-solid fa-circle-check"></i>
                                    </template>
                                    <template x-if="item.status === 'preparing'">
                                        <i class="fa-solid fa-spinner fa-spin" style="color: var(--warning);"></i>
                                    </template>
                                    <template x-if="item.status === 'pending'">
                                        <i class="fa-regular fa-circle"></i>
                                    </template>
                                </div>
                            </div>

                            <!-- Modifiers -->
                            <template x-if="item.modifiers && item.modifiers.length > 0">
                                <div class="item-modifiers">
                                    <template x-for="mod in item.modifiers" :key="typeof mod === 'object' ? mod.name : mod">
                                        <span class="mod-tag" x-text="typeof mod === 'object' ? mod.name : mod"></span>
                                    </template>
                                </div>
                            </template>

                            <!-- Item Notes -->
                            <template x-if="item.notes">
                                <div class="item-custom-note" x-text="'* ' + item.notes"></div>
                            </template>
                        </div>
                    </template>
                </div>

                <!-- Ticket Actions Footer -->
                <div class="ticket-footer">
                    <!-- Print Kitchen Ticket -->
                    <button type="button" 
                            class="btn-ticket-tool" 
                            @click="printTicket(ticket)" 
                            title="{{ __('Print Kitchen Ticket') }}">
                        <i class="fa-solid fa-print"></i>
                    </button>

                    <!-- Bump Actions -->
                    <template x-if="!ticket.all_ready">
                        <button type="button" 
                                class="btn-bump btn-bump-ready" 
                                @click="bumpTicket(ticket, 'ready')">
                            <i class="fa-solid fa-check"></i>
                            <span>{{ __('Mark All Ready') }}</span>
                        </button>
                    </template>

                    <template x-if="ticket.all_ready">
                        <button type="button" 
                                class="btn-bump btn-bump-served" 
                                @click="bumpTicket(ticket, 'served')">
                            <i class="fa-solid fa-bell-concierge"></i>
                            <span>{{ __('Mark Served') }}</span>
                        </button>
                    </template>
                </div>
            </div>
        </template>
    </main>

    <!-- JavaScript & Alpine Application -->
    <script>
        function kdsApp() {
            return {
                tickets: [],
                activeStation: 'all',
                isRefreshing: false,
                isFullscreen: false,
                soundEnabled: true,
                pollInterval: null,
                previousTicketIds: new Set(),
                audioCtx: null,

                initKds() {
                    this.fetchTickets(true);
                    this.initLanBroadcast();
                    // Polling every 5 seconds
                    this.pollInterval = setInterval(() => {
                        this.fetchTickets(false);
                    }, 5000);

                    // Initialize Web Audio API on first user interaction
                    document.addEventListener('click', () => {
                        if (!this.audioCtx) {
                            const AudioContext = window.AudioContext || window.webkitAudioContext;
                            if (AudioContext) {
                                this.audioCtx = new AudioContext();
                            }
                        }
                    }, { once: true });
                },

                setStation(stationSlug) {
                    this.activeStation = stationSlug;
                    this.fetchTickets(true);
                },

                toggleSound() {
                    this.soundEnabled = !this.soundEnabled;
                },

                toggleFullscreen() {
                    if (!document.fullscreenElement) {
                        document.documentElement.requestFullscreen().then(() => {
                            this.isFullscreen = true;
                        }).catch(() => {});
                    } else {
                        document.exitFullscreen().then(() => {
                            this.isFullscreen = false;
                        }).catch(() => {});
                    }
                },

                async fetchTickets(showLoading = false) {
                    if (showLoading) this.isRefreshing = true;
                    try {
                        const url = `/pos/api/kds/tickets?station=${encodeURIComponent(this.activeStation)}`;
                        const res = await fetch(url, { credentials: 'same-origin' });
                        if (!res.ok) throw new Error('Failed to fetch tickets');
                        const data = await res.json();
                        
                        if (data.success) {
                            const newTickets = data.tickets || [];
                            // Check for newly arrived tickets to trigger audio chime
                            let hasNewTickets = false;
                            for (const t of newTickets) {
                                if (!this.previousTicketIds.has(t.order_id)) {
                                    hasNewTickets = true;
                                    break;
                                }
                            }

                            if (hasNewTickets && this.soundEnabled && this.previousTicketIds.size > 0) {
                                this.playOrderChime();
                            }

                            this.previousTicketIds = new Set(newTickets.map(t => t.order_id));
                            this.tickets = newTickets;
                        }
                    } catch (e) {
                        console.error('KDS Fetch Error:', e);
                    } finally {
                        if (showLoading) this.isRefreshing = false;
                    }
                },

                async toggleItemStatus(ticket, item) {
                    const nextStatus = (item.status === 'ready' || item.status === 'served') ? 'preparing' : 'ready';
                    
                    // Optimistic update
                    const prevStatus = item.status;
                    item.status = nextStatus;

                    try {
                        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                        const res = await fetch(`/pos/api/kds/items/${item.id}/status`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ status: nextStatus })
                        });
                        const data = await res.json();
                        if (data.success) {
                            item.status = data.item.status;
                            ticket.order_status = data.order_status;
                            this.recalculateTicketReady(ticket);
                        } else {
                            item.status = prevStatus;
                        }
                    } catch (e) {
                        console.error('Item Status Update Error:', e);
                        item.status = prevStatus;
                    }
                },

                recalculateTicketReady(ticket) {
                    const readyCount = ticket.items.filter(i => i.status === 'ready' || i.status === 'served').length;
                    ticket.all_ready = (ticket.items.length > 0 && readyCount === ticket.items.length);
                },

                async bumpTicket(ticket, targetStatus) {
                    try {
                        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                        const res = await fetch(`/pos/api/kds/orders/${ticket.order_id}/bump`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                status: targetStatus,
                                station: this.activeStation !== 'all' ? this.activeStation : null
                            })
                        });
                        const data = await res.json();
                        if (data.success) {
                            if (targetStatus === 'served') {
                                // Remove ticket from active view
                                this.tickets = this.tickets.filter(t => t.order_id !== ticket.order_id);
                            } else {
                                ticket.items.forEach(i => i.status = 'ready');
                                ticket.all_ready = true;
                                ticket.order_status = data.order_status;
                            }
                        }
                    } catch (e) {
                        console.error('Bump Ticket Error:', e);
                    }
                },

                async printTicket(ticket) {
                    try {
                        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                        const res = await fetch(`/pos/api/kds/orders/${ticket.order_id}/print`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                station: this.activeStation !== 'all' ? this.activeStation : 'kitchen'
                            })
                        });
                        const data = await res.json();
                        if (data.success && data.ticket_text) {
                            // Quick printable fallback window
                            const printWin = window.open('', '_blank', 'width=350,height=500');
                            if (printWin) {
                                printWin.document.write(`<pre style="font-family: monospace; font-size: 13px; line-height: 1.25;">${data.ticket_text}</pre>`);
                                printWin.document.close();
                                printWin.print();
                            }
                        }
                    } catch (e) {
                        console.error('Print Ticket Error:', e);
                    }
                },

                playOrderChime() {
                    try {
                        const AudioContext = window.AudioContext || window.webkitAudioContext;
                        if (!AudioContext) return;
                        if (!this.audioCtx) this.audioCtx = new AudioContext();
                        if (this.audioCtx.state === 'suspended') this.audioCtx.resume();

                        const now = this.audioCtx.currentTime;
                        
                        // First harmonic note: 880Hz (A5)
                        const osc1 = this.audioCtx.createOscillator();
                        const gain1 = this.audioCtx.createGain();
                        osc1.type = 'sine';
                        osc1.frequency.setValueAtTime(880, now);
                        gain1.gain.setValueAtTime(0.2, now);
                        gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.3);
                        osc1.connect(gain1);
                        gain1.connect(this.audioCtx.destination);
                        osc1.start(now);
                        osc1.stop(now + 0.3);

                        // Second melodic note: 1320Hz (E6)
                        const osc2 = this.audioCtx.createOscillator();
                        const gain2 = this.audioCtx.createGain();
                        osc2.type = 'sine';
                        osc2.frequency.setValueAtTime(1320, now + 0.15);
                        gain2.gain.setValueAtTime(0.25, now + 0.15);
                        gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.55);
                        osc2.connect(gain2);
                        gain2.connect(this.audioCtx.destination);
                        osc2.start(now + 0.15);
                        osc2.stop(now + 0.55);
                    } catch (e) {
                        console.warn('Audio chime could not play:', e);
                    }
                },

                initLanBroadcast() {
                    const channelName = 'elam_pos_lan_{{ $vendor->id }}_{{ $device->location_id ?? 0 }}';
                    if ('BroadcastChannel' in window) {
                        this.lanChannel = new BroadcastChannel(channelName);
                        this.lanChannel.onmessage = (event) => {
                            const data = event.data || {};
                            if (data.type === 'ORDER_CREATED' && data.order) {
                                if (!this.tickets.some(t => t.order_id === data.order.id || t.order_number === data.order.order_number)) {
                                    const newTicket = {
                                        order_id: data.order.id,
                                        order_number: data.order.order_number,
                                        table_name: data.order.table_number || 'Takeaway',
                                        waiter_name: 'POS Handheld',
                                        elapsed_minutes: 0,
                                        priority: 'normal',
                                        all_ready: false,
                                        items: (data.order.items || []).map(i => ({
                                            id: Math.random().toString(),
                                            product_name: i.product_name,
                                            variation_name: i.variation_name,
                                            quantity: i.quantity,
                                            status: 'pending',
                                            modifiers: i.modifiers || [],
                                            notes: i.notes
                                        }))
                                    };
                                    this.tickets.unshift(newTicket);
                                    if (this.soundEnabled) this.playOrderChime();
                                }
                            }
                        };
                    }
                }
            };
        }
    </script>
</body>
</html>

