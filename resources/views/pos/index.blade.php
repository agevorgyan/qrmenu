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
            gap: 0.65rem;
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
            transition: all 0.15s ease;
        }

        .pos-user-badge:hover {
            background: var(--bg-card-hover);
            border-color: rgba(255, 255, 255, 0.15);
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
            overflow-y: auto;
            padding: 1.5rem;
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
            max-width: 650px;
            margin: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .pos-placeholder-icon {
            font-size: 3.5rem;
            color: var(--primary);
            margin-bottom: 1.5rem;
            filter: drop-shadow(0 4px 12px var(--primary-glow));
        }

        /* Modal Overlays & Drawers */
        .pos-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(5, 8, 15, 0.85);
            backdrop-filter: blur(12px);
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .pos-modal-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
            overflow: hidden;
        }

        .pos-modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .pos-modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        /* Employee Grid for Quick Switch */
        .employee-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 1rem;
        }

        .employee-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 1.25rem 0.75rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .employee-card:hover, .employee-card.selected {
            background: rgba(245, 158, 11, 0.1);
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .employee-avatar-lg {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: var(--primary);
            color: #000;
            font-size: 1.25rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.75rem;
            overflow: hidden;
        }

        /* Lock Screen & PIN Keypad */
        .pos-lock-screen {
            position: absolute;
            inset: 0;
            background: rgba(10, 14, 26, 0.96);
            backdrop-filter: blur(20px);
            z-index: 120;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .pin-pad-container {
            width: 320px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.25rem;
        }

        .pin-display {
            display: flex;
            gap: 1rem;
            margin-bottom: 0.25rem;
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
            gap: 0.85rem;
            width: 100%;
        }

        .pin-key {
            height: 60px;
            border-radius: 12px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            font-size: 1.4rem;
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

        /* Device Table in Manager Mode */
        .device-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.85rem;
        }

        .device-table th {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-muted);
            font-weight: 600;
        }

        .device-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border-color-light);
            color: var(--text-main);
        }

        /* Form Inputs */
        .pos-input {
            width: 100%;
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 10px 14px;
            color: var(--text-main);
            font-size: 0.9rem;
            outline: none;
        }

        .pos-input:focus {
            border-color: var(--primary);
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

            <!-- Quick Employee Switcher Button -->
            <button type="button" class="pos-tool-btn" @click="openEmployeeSwitcher()" title="{{ __('Quick Switch Employee') }}" style="color: var(--primary);">
                <i class="fa-solid fa-users-gear"></i>
            </button>

            <!-- Employee Profile Badge & Dropdown Trigger -->
            <div class="pos-user-badge" @click="openProfileModal()">
                <div class="pos-user-avatar">
                    <template x-if="currentUser.avatar_url">
                        <img :src="currentUser.avatar_url" style="width: 100%; height: 100%; object-fit: cover;">
                    </template>
                    <template x-if="!currentUser.avatar_url">
                        <span x-text="currentUser.display_name.charAt(0).toUpperCase()"></span>
                    </template>
                </div>
                <div class="pos-user-info">
                    <span class="pos-user-name" x-text="currentUser.display_name"></span>
                    <span class="pos-user-role" x-text="activeMode"></span>
                </div>
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
        <section class="pos-mode-pane" x-show="activeMode === 'waiter'" style="display: flex;">
            <div class="pos-placeholder-card">
                <i class="fa-solid fa-user-tie pos-placeholder-icon"></i>
                <h2 style="font-family: var(--font-display); font-size: 1.6rem; margin-bottom: 0.75rem;">
                    {{ __('Waiter Mode Active') }}
                </h2>
                <p style="color: var(--text-muted); font-size: 0.95rem; line-height: 1.5; margin-bottom: 1.5rem;">
                    {{ __('Interactive table management, category menu browser, and order submission interface ready for operations.') }}
                </p>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center;">
                    <button type="button" class="pos-mode-btn active" style="padding: 10px 20px;" @click="openEmployeeSwitcher()">
                        <i class="fa-solid fa-users"></i>
                        <span>{{ __('Switch Employee') }}</span>
                    </button>
                    <button type="button" class="pos-mode-btn" style="border: 1px solid var(--border-color); padding: 10px 20px;" @click="openSetPinModal()">
                        <i class="fa-solid fa-key"></i>
                        <span>{{ __('Change PIN') }}</span>
                    </button>
                </div>
            </div>
        </section>

        <!-- Mode: Cashier -->
        <section class="pos-mode-pane" x-show="activeMode === 'cashier'" style="display: flex;" x-cloak>
            <div class="pos-placeholder-card">
                <i class="fa-solid fa-receipt pos-placeholder-icon"></i>
                <h2 style="font-family: var(--font-display); font-size: 1.6rem; margin-bottom: 0.75rem;">
                    {{ __('Cashier Mode Active') }}
                </h2>
                <p style="color: var(--text-muted); font-size: 0.95rem; line-height: 1.5; margin-bottom: 1.5rem;">
                    {{ __('Bill settlement, cash/card payment collection, split bill, and fiscal receipt generation.') }}
                </p>
                <button type="button" class="pos-mode-btn active" style="padding: 10px 20px;" @click="lockTerminal()">
                    <i class="fa-solid fa-lock"></i>
                    <span>{{ __('Lock Terminal') }}</span>
                </button>
            </div>
        </section>

        <!-- Mode: Manager -->
        <section class="pos-mode-pane" x-show="activeMode === 'manager'" style="display: flex;" x-cloak>
            <div style="width: 100%; max-width: 1000px; margin: 0 auto; display: flex; flex-direction: column; gap: 1.5rem;">
                <!-- Header with Action Button -->
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="font-family: var(--font-display); font-size: 1.5rem; font-weight: 700;">
                            {{ __('Device Management') }}
                        </h2>
                        <p style="color: var(--text-muted); font-size: 0.85rem;">
                            {{ __('Authorized POS terminals and mobile devices for this location.') }}
                        </p>
                    </div>
                    <button type="button" class="pos-mode-btn active" @click="openRegisterDeviceModal()">
                        <i class="fa-solid fa-plus"></i>
                        <span>{{ __('Register Device') }}</span>
                    </button>
                </div>

                <!-- Devices Card -->
                <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden;">
                    <table class="device-table">
                        <thead>
                            <tr>
                                <th>{{ __('Device Name') }}</th>
                                <th>{{ __('Device Type') }}</th>
                                <th>{{ __('Pairing Code') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Last Seen') }}</th>
                                <th style="text-align: right;">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="dev in devices" :key="dev.id">
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-main);" x-text="dev.name"></div>
                                        <div style="font-size: 0.7rem; color: var(--text-subtle);" x-text="dev.uuid"></div>
                                    </td>
                                    <td style="text-transform: capitalize;" x-text="dev.device_type"></td>
                                    <td>
                                        <code style="background: rgba(255,255,255,0.06); padding: 3px 6px; border-radius: 4px; color: var(--primary); font-weight: 700;" x-text="dev.pairing_code || 'PAIRED'"></code>
                                    </td>
                                    <td>
                                        <span class="status-pill" :class="{ 'offline': !dev.is_active }">
                                            <span class="status-dot"></span>
                                            <span x-text="dev.is_active ? '{{ __('Active') }}' : '{{ __('Inactive') }}'"></span>
                                        </span>
                                    </td>
                                    <td style="font-size: 0.75rem; color: var(--text-muted);" x-text="dev.last_seen_at ? new Date(dev.last_seen_at).toLocaleTimeString() : '—'"></td>
                                    <td style="text-align: right;">
                                        <button type="button" class="pos-tool-btn" style="width: 32px; height: 32px; font-size: 0.8rem;" @click="regenerateCode(dev.id)" title="{{ __('Regenerate Code') }}">
                                            <i class="fa-solid fa-rotate"></i>
                                        </button>
                                        <button type="button" class="pos-tool-btn" style="width: 32px; height: 32px; font-size: 0.8rem; color: var(--danger);" @click="revokeDevice(dev.id)" title="{{ __('Revoke Device') }}">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="devices.length === 0">
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                        {{ __('No POS devices registered yet.') }}
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- 3. Quick Employee Switcher Modal -->
        <div class="pos-modal-overlay" x-show="showEmployeeSwitcher" x-cloak>
            <div class="pos-modal-card">
                <div class="pos-modal-header">
                    <h3 style="font-family: var(--font-display); font-size: 1.2rem; font-weight: 700;">
                        {{ __('Select Employee') }}
                    </h3>
                    <button type="button" class="pos-tool-btn" style="width: 34px; height: 34px;" @click="showEmployeeSwitcher = false">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="pos-modal-body">
                    <div class="employee-grid">
                        <template x-for="emp in employees" :key="emp.id">
                            <div class="employee-card" @click="selectEmployeeForSwitch(emp)">
                                <div class="employee-avatar-lg">
                                    <template x-if="emp.avatar_url">
                                        <img :src="emp.avatar_url" style="width: 100%; height: 100%; object-fit: cover;">
                                    </template>
                                    <template x-if="!emp.avatar_url">
                                        <span x-text="emp.display_name.charAt(0).toUpperCase()"></span>
                                    </template>
                                </div>
                                <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-main);" x-text="emp.display_name"></span>
                                <span style="font-size: 0.65rem; color: var(--primary); text-transform: uppercase; font-weight: 700; margin-top: 4px;" x-text="emp.role"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. PIN Change Modal -->
        <div class="pos-modal-overlay" x-show="showPinModal" x-cloak>
            <div class="pos-modal-card" style="max-width: 400px;">
                <div class="pos-modal-header">
                    <h3 style="font-family: var(--font-display); font-size: 1.2rem; font-weight: 700;">
                        {{ __('Set New 4-Digit PIN') }}
                    </h3>
                    <button type="button" class="pos-tool-btn" style="width: 34px; height: 34px;" @click="showPinModal = false">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="pos-modal-body">
                    <label style="font-size: 0.8rem; color: var(--text-muted);">{{ __('Enter new 4 to 6 digit numeric PIN') }}</label>
                    <input type="password" maxlength="6" class="pos-input" x-model="newPin" placeholder="••••" style="text-align: center; font-size: 1.5rem; letter-spacing: 6px;">
                    <button type="button" class="pos-mode-btn active" style="justify-content: center; width: 100%; height: 46px;" @click="submitNewPin()">
                        <span>{{ __('Save PIN') }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 5. Register Device Modal -->
        <div class="pos-modal-overlay" x-show="showRegisterDeviceModal" x-cloak>
            <div class="pos-modal-card" style="max-width: 450px;">
                <div class="pos-modal-header">
                    <h3 style="font-family: var(--font-display); font-size: 1.2rem; font-weight: 700;">
                        {{ __('Register Device') }}
                    </h3>
                    <button type="button" class="pos-tool-btn" style="width: 34px; height: 34px;" @click="showRegisterDeviceModal = false">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="pos-modal-body">
                    <div>
                        <label style="font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 6px;">{{ __('Device Name') }}</label>
                        <input type="text" class="pos-input" x-model="newDeviceName" placeholder="e.g. Main Terminal, Bar Tablet">
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 6px;">{{ __('Device Type') }}</label>
                        <select class="pos-input" x-model="newDeviceType">
                            <option value="terminal">{{ __('Terminal') }}</option>
                            <option value="tablet">{{ __('Tablet') }}</option>
                            <option value="mobile">{{ __('Mobile Handheld') }}</option>
                            <option value="kds">{{ __('Kitchen Display (KDS)') }}</option>
                        </select>
                    </div>
                    <button type="button" class="pos-mode-btn active" style="justify-content: center; width: 100%; height: 46px;" @click="submitDeviceRegistration()">
                        <span>{{ __('Register Device') }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 6. Quick PIN Pad Lock Screen / Employee Switch Screen -->
        <div class="pos-lock-screen" x-show="isLocked || switchingTargetUser" x-cloak>
            <div class="pin-pad-container">
                <div style="text-align: center;">
                    <div class="employee-avatar-lg" style="margin: 0 auto 0.75rem;">
                        <template x-if="switchingTargetUser">
                            <span x-text="switchingTargetUser.display_name.charAt(0).toUpperCase()"></span>
                        </template>
                        <template x-if="!switchingTargetUser">
                            <span x-text="currentUser.display_name.charAt(0).toUpperCase()"></span>
                        </template>
                    </div>
                    <h3 style="font-family: var(--font-display); font-size: 1.25rem; font-weight: 700;" 
                        x-text="switchingTargetUser ? switchingTargetUser.display_name : currentUser.display_name"></h3>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">
                        {{ __('Enter 4-digit PIN to unlock') }}
                    </p>
                    <div x-show="pinError" style="color: var(--danger); font-size: 0.75rem; margin-top: 6px; font-weight: 600;" x-text="pinError"></div>
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
                    <button type="button" class="pin-key" style="font-size: 1rem; color: var(--danger);" @click="cancelPinAction()">
                        <i class="fa-solid fa-xmark"></i>
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
                pinError: '',
                bootstrapData: @json($bootstrapData),
                currentUser: @json($bootstrapData['user']),
                employees: [],
                devices: [],
                showEmployeeSwitcher: false,
                showPinModal: false,
                showRegisterDeviceModal: false,
                switchingTargetUser: null,
                newPin: '',
                newDeviceName: '',
                newDeviceType: 'terminal',

                initPos() {
                    window.addEventListener('online', () => this.isOnline = true);
                    window.addEventListener('offline', () => this.isOnline = false);

                    this.fetchEmployees();
                    this.loadDevices();

                    // Auto-lock timer setup
                    const autoLockSec = this.bootstrapData.settings?.auto_lock_seconds || 120;
                    if (autoLockSec > 0) {
                        this.setupAutoLock(autoLockSec);
                    }
                },

                setMode(mode) {
                    this.activeMode = mode;
                    if (mode === 'manager') {
                        this.loadDevices();
                    }
                },

                switchBranch(locationId) {
                    this.activeLocationId = locationId;
                    this.fetchBootstrap();
                    this.fetchEmployees();
                },

                async fetchBootstrap() {
                    try {
                        const res = await fetch(`/pos/api/bootstrap?location_id=${this.activeLocationId}`, {
                            headers: { 'Accept': 'application/json' }
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.bootstrapData = data.data;
                            this.currentUser = data.data.user;
                        }
                    } catch (e) {
                        console.error('POS bootstrap sync failed:', e);
                    }
                },

                async fetchEmployees() {
                    try {
                        const res = await fetch(`/pos/api/auth/employees?location_id=${this.activeLocationId || ''}`, {
                            headers: { 'Accept': 'application/json' }
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.employees = data.employees;
                        }
                    } catch (e) {
                        console.error('Fetch employees failed:', e);
                    }
                },

                async loadDevices() {
                    try {
                        const res = await fetch(`/pos/api/devices?location_id=${this.activeLocationId || ''}`, {
                            headers: { 'Accept': 'application/json' }
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.devices = data.devices;
                        }
                    } catch (e) {
                        console.error('Load devices failed:', e);
                    }
                },

                openEmployeeSwitcher() {
                    this.fetchEmployees();
                    this.showEmployeeSwitcher = true;
                },

                selectEmployeeForSwitch(emp) {
                    this.showEmployeeSwitcher = false;
                    this.switchingTargetUser = emp;
                    this.enteredPin = '';
                    this.pinError = '';
                },

                openSetPinModal() {
                    this.newPin = '';
                    this.showPinModal = true;
                },

                async submitNewPin() {
                    if (!/^\d{4,6}$/.test(this.newPin)) {
                        alert('{{ __('PIN must be between 4 and 6 numeric digits.') }}');
                        return;
                    }
                    try {
                        const res = await fetch('/pos/api/auth/pin', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({ pin: this.newPin })
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.showPinModal = false;
                            alert('{{ __('PIN code updated successfully.') }}');
                        } else {
                            alert(data.message || 'Error updating PIN');
                        }
                    } catch (e) {
                        console.error(e);
                    }
                },

                openRegisterDeviceModal() {
                    this.newDeviceName = '';
                    this.newDeviceType = 'terminal';
                    this.showRegisterDeviceModal = true;
                },

                async submitDeviceRegistration() {
                    if (!this.newDeviceName.trim()) return;
                    try {
                        const res = await fetch('/pos/api/devices/register', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                name: this.newDeviceName,
                                device_type: this.newDeviceType,
                                location_id: this.activeLocationId
                            })
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.showRegisterDeviceModal = false;
                            this.loadDevices();
                            alert(`{{ __('Device Registered') }}. {{ __('Pairing Code') }}: ${data.device.pairing_code}`);
                        }
                    } catch (e) {
                        console.error(e);
                    }
                },

                async regenerateCode(deviceId) {
                    try {
                        const res = await fetch(`/pos/api/devices/${deviceId}/regenerate-code`, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.loadDevices();
                            alert(`{{ __('New Pairing Code') }}: ${data.pairing_code}`);
                        }
                    } catch (e) {
                        console.error(e);
                    }
                },

                async revokeDevice(deviceId) {
                    if (!confirm('{{ __('Are you sure you want to revoke this device?') }}')) return;
                    try {
                        const res = await fetch(`/pos/api/devices/${deviceId}`, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.loadDevices();
                        }
                    } catch (e) {
                        console.error(e);
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
                    this.pinError = '';
                    this.switchingTargetUser = null;
                },

                pressPin(digit) {
                    if (this.enteredPin.length < 6) {
                        this.enteredPin += digit.toString();
                        if (this.enteredPin.length >= 4) {
                            this.submitPinAction();
                        }
                    }
                },

                backspacePin() {
                    this.enteredPin = this.enteredPin.slice(0, -1);
                },

                cancelPinAction() {
                    this.enteredPin = '';
                    this.pinError = '';
                    if (this.switchingTargetUser) {
                        this.switchingTargetUser = null;
                    }
                },

                async submitPinAction() {
                    if (this.switchingTargetUser) {
                        // Quick switch employee
                        try {
                            const res = await fetch('/pos/api/auth/quick-switch', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: JSON.stringify({
                                    target_user_id: this.switchingTargetUser.id,
                                    pin: this.enteredPin,
                                    device_uuid: this.bootstrapData.device?.uuid || 'default'
                                })
                            });
                            const data = await res.json();
                            if (data.success) {
                                this.currentUser = data.user;
                                this.switchingTargetUser = null;
                                this.enteredPin = '';
                                this.pinError = '';
                                this.fetchBootstrap();
                            } else {
                                this.pinError = data.message || '{{ __('Incorrect PIN code.') }}';
                                this.enteredPin = '';
                            }
                        } catch (e) {
                            this.pinError = 'Network error during switch';
                            this.enteredPin = '';
                        }
                    } else {
                        // Unlock current terminal session
                        try {
                            const res = await fetch('/pos/api/auth/unlock', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: JSON.stringify({ pin: this.enteredPin })
                            });
                            const data = await res.json();
                            if (data.success) {
                                this.isLocked = false;
                                this.enteredPin = '';
                                this.pinError = '';
                            } else {
                                this.pinError = data.message || '{{ __('Incorrect PIN code.') }}';
                                this.enteredPin = '';
                            }
                        } catch (e) {
                            this.pinError = 'Network error during unlock';
                            this.enteredPin = '';
                        }
                    }
                },

                setupAutoLock(seconds) {
                    let timer;
                    const reset = () => {
                        clearTimeout(timer);
                        timer = setTimeout(() => {
                            if (!this.isLocked && !this.switchingTargetUser) {
                                this.lockTerminal();
                            }
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
