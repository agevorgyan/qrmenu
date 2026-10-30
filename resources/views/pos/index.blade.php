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
            height: 60px;
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
            gap: 0.85rem;
            min-width: 0;
        }

        .pos-logo-badge {
            width: 38px;
            height: 38px;
            border-radius: 9px;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.15rem;
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
            font-size: 0.95rem;
            color: var(--text-main);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pos-branch-select {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            font-size: 0.72rem;
            padding: 2px 6px;
            border-radius: 6px;
            cursor: pointer;
            outline: none;
        }

        /* Mode Switcher Tabs */
        .pos-mode-switcher {
            display: flex;
            align-items: center;
            background: rgba(0, 0, 0, 0.35);
            padding: 3px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            gap: 3px;
        }

        .pos-mode-btn {
            padding: 7px 14px;
            border-radius: 7px;
            border: none;
            background: transparent;
            color: var(--text-muted);
            font-size: 0.82rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.45rem;
            cursor: pointer;
            transition: all 0.15s ease;
            min-height: 36px;
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
            gap: 0.6rem;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.72rem;
            font-weight: 600;
            padding: 5px 9px;
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
            width: 7px;
            height: 7px;
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
            min-width: 38px;
            height: 38px;
            border-radius: 9px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
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
            gap: 0.55rem;
            padding: 3px 9px 3px 5px;
            border-radius: 20px;
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
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--primary);
            color: #000;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            overflow: hidden;
        }

        .pos-user-info {
            display: flex;
            flex-direction: column;
            line-height: 1.15;
            text-align: left;
        }

        .pos-user-name {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-main);
        }

        .pos-user-role {
            font-size: 0.62rem;
            color: var(--primary);
            text-transform: uppercase;
            font-weight: 700;
        }

        /* Main Workspace Stage */
        .pos-stage {
            flex: 1;
            display: flex;
            overflow: hidden;
            position: relative;
        }

        /* ---------------------------------------------------- */
        /* WAITER MODE: TWO-COLUMN TOUCHSCREEN ORDER INTERFACE  */
        /* ---------------------------------------------------- */
        .order-taking-layout {
            flex: 1;
            display: flex;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }

        /* Left/Catalog Column */
        .catalog-pane {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--bg-body);
            border-right: 1px solid var(--border-color);
            overflow: hidden;
            min-width: 0;
        }

        /* Search & Context Header */
        .catalog-top-bar {
            padding: 0.65rem 1rem;
            background: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-shrink: 0;
        }

        .search-box-wrap {
            position: relative;
            flex: 1;
            display: flex;
            align-items: center;
        }

        .search-box-wrap i {
            position: absolute;
            left: 12px;
            color: var(--text-subtle);
            font-size: 0.9rem;
        }

        .catalog-search-input {
            width: 100%;
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid var(--border-color);
            border-radius: 9px;
            padding: 8px 12px 8px 34px;
            color: var(--text-main);
            font-size: 0.88rem;
            outline: none;
            transition: all 0.15s ease;
        }

        .catalog-search-input:focus {
            border-color: var(--primary);
            background: rgba(0, 0, 0, 0.6);
        }

        .table-context-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 6px 12px;
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 8px;
            color: #fbbf24;
            font-weight: 700;
            font-size: 0.82rem;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.15s ease;
        }

        .table-context-chip:hover {
            background: rgba(245, 158, 11, 0.2);
        }

        /* Categories Bar */
        .categories-bar {
            display: flex;
            gap: 0.5rem;
            padding: 0.6rem 1rem;
            background: rgba(14, 20, 36, 0.6);
            border-bottom: 1px solid var(--border-color);
            overflow-x: auto;
            white-space: nowrap;
            flex-shrink: 0;
            scrollbar-width: thin;
        }

        .cat-chip {
            padding: 6px 14px;
            border-radius: 8px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.15s ease;
            min-height: 38px;
        }

        .cat-chip:hover {
            color: var(--text-main);
            background: var(--bg-card-hover);
        }

        .cat-chip.active {
            background: var(--primary-gradient);
            color: #ffffff;
            border-color: transparent;
            box-shadow: 0 2px 8px var(--primary-glow);
        }

        /* Product Grid */
        .products-grid-scroll {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 0.85rem;
        }

        .dish-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            cursor: pointer;
            transition: all 0.15s ease;
            position: relative;
            min-height: 190px;
        }

        .dish-card:hover {
            border-color: rgba(245, 158, 11, 0.4);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        }

        .dish-card:active {
            transform: scale(0.97);
        }

        .dish-img-wrap {
            height: 105px;
            width: 100%;
            background: #18223c;
            position: relative;
            overflow: hidden;
        }

        .dish-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .dish-card:hover .dish-img-wrap img {
            transform: scale(1.05);
        }

        .dish-body {
            padding: 0.65rem 0.75rem;
            display: flex;
            flex-direction: column;
            flex: 1;
            justify-content: space-between;
        }

        .dish-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-main);
            line-height: 1.25;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .dish-price-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 0.5rem;
        }

        .dish-price {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--primary);
            font-family: var(--font-display);
        }

        .dish-variant-badge {
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 4px;
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            font-weight: 700;
        }

        /* ---------------------------------------------------- */
        /* RIGHT PANEL: ORDER CART                               */
        /* ---------------------------------------------------- */
        .cart-pane {
            width: 380px;
            background: var(--bg-card);
            border-left: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            height: 100%;
        }

        .cart-header {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(14, 20, 36, 0.4);
        }

        .cart-type-selector {
            display: flex;
            background: rgba(0, 0, 0, 0.35);
            padding: 2px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            gap: 2px;
        }

        .cart-type-btn {
            padding: 4px 10px;
            border-radius: 6px;
            border: none;
            background: transparent;
            color: var(--text-muted);
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
        }

        .cart-type-btn.active {
            background: var(--primary-gradient);
            color: #fff;
        }

        /* Cart Items Scrollable List */
        .cart-items-list {
            flex: 1;
            overflow-y: auto;
            padding: 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
        }

        .cart-item-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color-light);
            border-radius: 10px;
            padding: 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            transition: all 0.15s ease;
        }

        .cart-item-card:hover {
            border-color: rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.05);
        }

        .cart-item-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .cart-item-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-main);
            line-height: 1.25;
        }

        .cart-item-variant {
            font-size: 0.72rem;
            color: var(--primary);
            font-weight: 600;
            margin-top: 2px;
        }

        .cart-item-mod-pill {
            font-size: 0.68rem;
            padding: 2px 6px;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.06);
            color: var(--text-muted);
            display: inline-block;
            margin-top: 3px;
            margin-right: 3px;
        }

        .cart-item-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 0.4rem;
        }

        .cart-qty-stepper {
            display: flex;
            align-items: center;
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }

        .cart-qty-btn {
            width: 32px;
            height: 32px;
            border: none;
            background: transparent;
            color: var(--text-main);
            font-size: 0.9rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.12s ease;
        }

        .cart-qty-btn:active {
            background: var(--primary);
            color: #000;
        }

        .cart-qty-val {
            min-width: 28px;
            text-align: center;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .cart-item-price {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-main);
            font-family: var(--font-display);
        }

        /* Cart Financials Summary */
        .cart-summary {
            padding: 0.85rem 1rem;
            border-top: 1px solid var(--border-color);
            background: rgba(14, 20, 36, 0.7);
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.82rem;
            color: var(--text-muted);
        }

        .summary-row.total-row {
            margin-top: 0.4rem;
            padding-top: 0.4rem;
            border-top: 1px dashed var(--border-color);
            font-size: 1.15rem;
            font-weight: 800;
            color: #ffffff;
            font-family: var(--font-display);
        }

        .total-amount-highlight {
            color: var(--primary);
            text-shadow: 0 0 12px var(--primary-glow);
        }

        /* Cart Bottom Actions */
        .cart-actions {
            padding: 0.85rem 1rem;
            border-top: 1px solid var(--border-color);
            background: var(--bg-card);
            display: flex;
            gap: 0.5rem;
        }

        .cart-btn-primary {
            flex: 2;
            height: 48px;
            border-radius: 10px;
            border: none;
            background: var(--primary-gradient);
            color: #ffffff;
            font-size: 0.95rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            cursor: pointer;
            box-shadow: 0 4px 15px var(--primary-glow);
            transition: all 0.15s ease;
        }

        .cart-btn-primary:active {
            transform: scale(0.98);
        }

        .cart-btn-secondary {
            flex: 1;
            height: 48px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .cart-btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Empty Cart State */
        .cart-empty-state {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            text-align: center;
            color: var(--text-subtle);
        }

        /* ---------------------------------------------------- */
        /* CASHIER MODE: BILL INSPECTOR & SETTLEMENT            */
        /* ---------------------------------------------------- */
        .cashier-orders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1rem;
        }

        .cashier-order-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .cashier-order-card:hover, .cashier-order-card.selected {
            border-color: var(--primary);
            background: rgba(245, 158, 11, 0.08);
            transform: translateY(-2px);
        }

        /* ---------------------------------------------------- */
        /* MODALS, POPUPS, PIN PAD                              */
        /* ---------------------------------------------------- */
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
            max-width: 550px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
            overflow: hidden;
        }

        .pos-modal-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .pos-modal-body {
            padding: 1.25rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        /* Table Grid */
        .table-selection-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 0.75rem;
        }

        .table-select-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 0.85rem 0.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.15s ease;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 96px;
        }

        .table-select-card:hover, .table-select-card.active {
            background: rgba(245, 158, 11, 0.15);
            border-color: var(--primary);
        }

        .table-select-card.status-available {
            border-color: rgba(16, 185, 129, 0.4);
        }
        .table-select-card.status-available:hover {
            background: rgba(16, 185, 129, 0.15);
            border-color: #10b981;
        }

        .table-select-card.status-occupied {
            border-color: rgba(245, 158, 11, 0.6);
            background: rgba(245, 158, 11, 0.06);
        }
        .table-select-card.status-occupied:hover {
            background: rgba(245, 158, 11, 0.2);
            border-color: #f59e0b;
        }

        .table-select-card.status-preparing {
            border-color: rgba(139, 92, 246, 0.6);
            background: rgba(139, 92, 246, 0.06);
        }

        .table-select-card.status-ready {
            border-color: rgba(6, 182, 212, 0.6);
            background: rgba(6, 182, 212, 0.06);
        }

        .table-select-card.status-cleaning {
            border-color: rgba(148, 163, 184, 0.4);
            opacity: 0.8;
        }

        .table-status-pill {
            display: inline-block;
            font-size: 0.62rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .table-timer-badge {
            font-size: 0.65rem;
            color: var(--primary);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 2px;
            margin-top: 2px;
        }

        /* Quick Cash Calculator */
        .cash-preset-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
        }

        .cash-preset-btn {
            padding: 10px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            text-align: center;
        }

        .cash-preset-btn:hover {
            background: rgba(245, 158, 11, 0.15);
            border-color: var(--primary);
        }

        /* Lock Screen Keypad */
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
            width: 310px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.15rem;
        }

        .pin-display {
            display: flex;
            gap: 0.85rem;
            margin-bottom: 0.25rem;
        }

        .pin-dot {
            width: 15px;
            height: 15px;
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
            gap: 0.75rem;
            width: 100%;
        }

        .pin-key {
            height: 56px;
            border-radius: 12px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            font-size: 1.35rem;
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

        /* Toast notifications */
        .pos-toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 12px 18px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            z-index: 200;
            animation: slide-up 0.3s ease;
        }

        @keyframes slide-up {
            from { transform: translateY(100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Form Controls */
        .pos-input {
            width: 100%;
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 9px 12px;
            color: var(--text-main);
            font-size: 0.88rem;
            outline: none;
        }

        .pos-input:focus {
            border-color: var(--primary);
        }

        /* Responsive Breakpoints */
        @media (max-width: 900px) {
            .order-taking-layout {
                flex-direction: column;
            }
            .cart-pane {
                width: 100%;
                height: 45vh;
                border-left: none;
                border-top: 1px solid var(--border-color);
            }
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

            <!-- Employee Profile Badge & Modal Trigger -->
            <div class="pos-user-badge" @click="openSetPinModal()">
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
        <!-- ========================================== -->
        <!-- MODE 1: WAITER MODE (TOUCHSCREEN ORDERING)  -->
        <!-- ========================================== -->
        <div class="order-taking-layout" x-show="activeMode === 'waiter'">
            <!-- Left: Catalog & Product Grid -->
            <div class="catalog-pane">
                <!-- Top Filter / Search Bar -->
                <div class="catalog-top-bar">
                    <div class="search-box-wrap">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" 
                               class="catalog-search-input" 
                               x-model="searchQuery" 
                               placeholder="{{ __('Search menu...') }}">
                    </div>

                    <!-- Table Chip Button -->
                    <div class="table-context-chip" @click="openTableModal()">
                        <i class="fa-solid fa-table-cells"></i>
                        <span x-text="selectedTable ? selectedTable.table_number : '{{ __('Quick Sale / Counter') }}'"></span>
                    </div>

                    <!-- Guest Count -->
                    <div style="display: flex; align-items: center; gap: 4px; background: rgba(0,0,0,0.4); border: 1px solid var(--border-color); border-radius: 8px; padding: 2px 6px;">
                        <i class="fa-solid fa-user-group" style="font-size: 0.75rem; color: var(--text-muted); margin-right: 2px;"></i>
                        <button type="button" class="pos-tool-btn" style="width: 24px; height: 24px; font-size: 0.7rem;" @click="guestCount = Math.max(1, guestCount - 1)">-</button>
                        <span style="font-size: 0.8rem; font-weight: 700; min-width: 16px; text-align: center;" x-text="guestCount"></span>
                        <button type="button" class="pos-tool-btn" style="width: 24px; height: 24px; font-size: 0.7rem;" @click="guestCount++">+</button>
                    </div>
                </div>

                <!-- Categories Scrollbar -->
                <div class="categories-bar">
                    <button type="button" 
                            class="cat-chip" 
                            :class="{ 'active': activeCategory === 'all' }"
                            @click="activeCategory = 'all'">
                        <i class="fa-solid fa-layer-group"></i>
                        <span>{{ __('All Dishes') }}</span>
                    </button>
                    <button type="button" 
                            class="cat-chip" 
                            :class="{ 'active': activeCategory === 'favorites' }"
                            @click="activeCategory = 'favorites'">
                        <i class="fa-solid fa-star" style="color: #fbbf24;"></i>
                        <span>{{ __('Favorites') }}</span>
                    </button>
                    <template x-for="cat in menu.categories" :key="cat.id">
                        <button type="button" 
                                class="cat-chip" 
                                :class="{ 'active': activeCategory === cat.id }"
                                @click="activeCategory = cat.id">
                            <span x-text="cat.name"></span>
                            <span style="font-size: 0.65rem; opacity: 0.6;" x-text="'(' + cat.products_count + ')'"></span>
                        </button>
                    </template>
                </div>

                <!-- Products Grid Scroll -->
                <div class="products-grid-scroll">
                    <div class="products-grid">
                        <template x-for="prod in filteredProducts" :key="prod.id">
                            <div class="dish-card" @click="handleProductClick(prod)">
                                <div class="dish-img-wrap">
                                    <img :src="prod.image" :alt="prod.name" loading="lazy">
                                    <template x-if="prod.variations && prod.variations.length > 0">
                                        <span class="dish-variant-badge" style="position: absolute; top: 8px; right: 8px;">
                                            <span x-text="prod.variations.length + ' {{ __('Sizes') }}'"></span>
                                        </span>
                                    </template>
                                </div>
                                <div class="dish-body">
                                    <div class="dish-title" x-text="prod.name"></div>
                                    <div class="dish-price-row">
                                        <span class="dish-price" x-text="formatCurrency(prod.price)"></span>
                                        <span class="pos-tool-btn" style="width: 28px; height: 28px; font-size: 0.8rem; background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: none;">
                                            <i class="fa-solid fa-plus"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Right: Order Cart -->
            <div class="cart-pane">
                <!-- Cart Header -->
                <div class="cart-header">
                    <div class="cart-type-selector">
                        <button type="button" class="cart-type-btn" :class="{ 'active': orderType === 'dine_in' }" @click="orderType = 'dine_in'">
                            {{ __('Dine In') }}
                        </button>
                        <button type="button" class="cart-type-btn" :class="{ 'active': orderType === 'takeaway' }" @click="orderType = 'takeaway'">
                            {{ __('Takeaway') }}
                        </button>
                        <button type="button" class="cart-type-btn" :class="{ 'active': orderType === 'delivery' }" @click="orderType = 'delivery'">
                            {{ __('Delivery') }}
                        </button>
                    </div>

                    <button type="button" class="pos-tool-btn" style="width: 32px; height: 32px; font-size: 0.8rem; color: var(--danger);" @click="clearCart()" title="{{ __('Clear Cart') }}">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </div>

                <!-- Cart Items List -->
                <div class="cart-items-list">
                    <template x-if="cart.length === 0">
                        <div class="cart-empty-state">
                            <i class="fa-solid fa-basket-shopping" style="font-size: 2.5rem; margin-bottom: 0.75rem; opacity: 0.4;"></i>
                            <div style="font-weight: 600; font-size: 0.95rem;">{{ __('Cart is empty') }}</div>
                            <div style="font-size: 0.78rem; margin-top: 4px;">{{ __('Add dishes from the menu to start an order') }}</div>
                        </div>
                    </template>

                    <template x-for="(item, index) in cart" :key="index">
                        <div class="cart-item-card">
                            <div class="cart-item-top">
                                <div>
                                    <div class="cart-item-name" x-text="item.product_name"></div>
                                    <template x-if="item.variation_name">
                                        <div class="cart-item-variant" x-text="item.variation_name"></div>
                                    </template>
                                    <template x-for="(mod, mIdx) in (item.modifiers || [])" :key="mIdx">
                                        <span class="cart-item-mod-pill" x-text="mod.name"></span>
                                    </template>
                                    <template x-if="item.notes">
                                        <div style="font-size: 0.7rem; color: var(--text-subtle); margin-top: 2px;" x-text="'📝 ' + item.notes"></div>
                                    </template>
                                </div>
                                <button type="button" style="background: none; border: none; color: var(--text-subtle); cursor: pointer; padding: 2px;" @click="removeItem(index)">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>

                            <div class="cart-item-bottom">
                                <div class="cart-qty-stepper">
                                    <button type="button" class="cart-qty-btn" @click="changeQty(index, -1)">-</button>
                                    <span class="cart-qty-val" x-text="item.quantity"></span>
                                    <button type="button" class="cart-qty-btn" @click="changeQty(index, 1)">+</button>
                                </div>
                                <span class="cart-item-price" x-text="formatCurrency(item.unit_price * item.quantity)"></span>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Financials Summary -->
                <div class="cart-summary" x-show="cart.length > 0">
                    <div class="summary-row">
                        <span>{{ __('Subtotal') }}</span>
                        <span x-text="formatCurrency(cartSubtotal)"></span>
                    </div>
                    <div class="summary-row" x-show="discountAmount > 0">
                        <span style="color: var(--warning);">{{ __('Discount') }}</span>
                        <span style="color: var(--warning);" x-text="'-' + formatCurrency(discountAmount)"></span>
                    </div>
                    <div class="summary-row" x-show="taxAmount > 0">
                        <span>{{ __('Tax') }}</span>
                        <span x-text="formatCurrency(taxAmount)"></span>
                    </div>
                    <div class="summary-row" x-show="serviceFee > 0">
                        <span>{{ __('Service Charge') }}</span>
                        <span x-text="formatCurrency(serviceFee)"></span>
                    </div>
                    <div class="summary-row total-row">
                        <span>{{ __('Total') }}</span>
                        <span class="total-amount-highlight" x-text="formatCurrency(cartTotal)"></span>
                    </div>
                </div>

                <!-- Order Action Buttons -->
                <div class="cart-actions" x-show="cart.length > 0">
                    <button type="button" class="cart-btn-secondary" @click="holdCurrentOrder()" title="{{ __('Hold Order') }}">
                        <i class="fa-solid fa-pause"></i>
                        <span>{{ __('Hold') }}</span>
                    </button>
                    <button type="button" class="cart-btn-secondary" @click="openDiscountModal()" title="{{ __('Add Discount') }}">
                        <i class="fa-solid fa-tag"></i>
                    </button>
                    <button type="button" class="cart-btn-primary" @click="submitOrderToKitchen()">
                        <i class="fa-solid fa-bell-concierge"></i>
                        <span>{{ __('Send to Kitchen') }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODE 2: CASHIER MODE (BILLS & PAYMENTS)     -->
        <!-- ========================================== -->
        <div class="pos-mode-pane" x-show="activeMode === 'cashier'" x-cloak>
            <div style="display: flex; gap: 1.5rem; height: 100%;">
                <!-- Left: Open Orders Grid -->
                <div style="flex: 1; display: flex; flex-direction: column; gap: 1rem; overflow-y: auto;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h2 style="font-family: var(--font-display); font-size: 1.35rem; font-weight: 700;">
                            {{ __('Open Unpaid Orders') }}
                        </h2>
                        <button type="button" class="pos-tool-btn" @click="fetchCashierOrders()">
                            <i class="fa-solid fa-rotate"></i>
                        </button>
                    </div>

                    <div class="cashier-orders-grid">
                        <template x-for="ord in cashierOrders" :key="ord.id">
                            <div class="cashier-order-card" 
                                 :class="{ 'selected': selectedCashierOrder && selectedCashierOrder.id === ord.id }"
                                 @click="selectOrderForCashier(ord)">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="font-weight: 800; color: var(--primary);" x-text="ord.order_number"></span>
                                    <span class="status-pill" style="font-size: 0.65rem;" x-text="ord.status"></span>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">
                                    <span style="color: #fff; font-weight: 600;" x-text="ord.table_number || 'Counter'"></span> • 
                                    <span x-text="ord.items ? ord.items.length + ' items' : ''"></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 0.4rem;">
                                    <span style="font-size: 0.75rem; color: var(--text-subtle);" x-text="ord.waiter ? ord.waiter.name : ''"></span>
                                    <span style="font-size: 1.1rem; font-weight: 800; color: #fff; font-family: var(--font-display);" x-text="formatCurrency(ord.total_amount)"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Right: Bill Inspector / Settlement Panel -->
                <div class="cart-pane" style="width: 420px; border-left: 1px solid var(--border-color); border-radius: 14px;" x-show="selectedCashierOrder">
                    <div class="cart-header">
                        <div>
                            <div style="font-weight: 700; font-size: 0.95rem;" x-text="selectedCashierOrder?.order_number"></div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);" x-text="'Table: ' + (selectedCashierOrder?.table_number || 'Counter')"></div>
                        </div>
                        <button type="button" class="pos-tool-btn" style="width: 32px; height: 32px;" @click="selectedCashierOrder = null">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <div class="cart-items-list">
                        <template x-for="item in (selectedCashierOrder?.items || [])" :key="item.id">
                            <div class="cart-item-card">
                                <div class="cart-item-top">
                                    <div class="cart-item-name" x-text="item.product_name"></div>
                                    <span class="cart-item-price" x-text="formatCurrency(item.subtotal)"></span>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);" x-text="item.quantity + ' × ' + formatCurrency(item.unit_price)"></div>
                            </div>
                        </template>
                    </div>

                    <div class="cart-summary">
                        <div class="summary-row total-row">
                            <span>{{ __('Total to Pay') }}</span>
                            <span class="total-amount-highlight" x-text="formatCurrency(selectedCashierOrder?.total_amount || 0)"></span>
                        </div>
                    </div>

                    <!-- Payment Mode Action Buttons -->
                    <div style="padding: 1rem; display: flex; flex-direction: column; gap: 0.65rem; background: var(--bg-card); border-top: 1px solid var(--border-color);">
                        <div style="display: flex; gap: 0.5rem;">
                            <button type="button" class="cart-btn-primary" style="flex: 1;" @click="openCashPaymentModal()">
                                <i class="fa-solid fa-money-bill-wave"></i>
                                <span>{{ __('Cash Payment') }}</span>
                            </button>
                            <button type="button" class="cart-btn-secondary" style="flex: 1;" @click="processCardPayment()">
                                <i class="fa-solid fa-credit-card"></i>
                                <span>{{ __('Card Payment') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODE 3: MANAGER MODE (OVERVIEW & DEVICES)   -->
        <!-- ========================================== -->
        <div class="pos-mode-pane" x-show="activeMode === 'manager'" x-cloak>
            <div style="width: 100%; max-width: 1100px; margin: 0 auto; display: flex; flex-direction: column; gap: 1.5rem;">
                <!-- KPI Bar -->
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem;">
                        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">{{ __('Today\'s Sales') }}</div>
                        <div style="font-size: 1.6rem; font-weight: 800; font-family: var(--font-display); color: var(--primary); margin-top: 4px;" x-text="formatCurrency(managerStats.todaySales)"></div>
                    </div>
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem;">
                        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">{{ __('Active Orders') }}</div>
                        <div style="font-size: 1.6rem; font-weight: 800; font-family: var(--font-display); color: #fff; margin-top: 4px;" x-text="cashierOrders.length"></div>
                    </div>
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem;">
                        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">{{ __('Occupied Tables') }}</div>
                        <div style="font-size: 1.6rem; font-weight: 800; font-family: var(--font-display); color: #34d399; margin-top: 4px;" x-text="managerStats.occupiedTables"></div>
                    </div>
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem;">
                        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">{{ __('Connected Devices') }}</div>
                        <div style="font-size: 1.6rem; font-weight: 800; font-family: var(--font-display); color: #60a5fa; margin-top: 4px;" x-text="devices.length"></div>
                    </div>
                </div>

                <!-- Device Management Card -->
                <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden;">
                    <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="font-family: var(--font-display); font-size: 1.15rem; font-weight: 700;">{{ __('Device Management') }}</h3>
                        <button type="button" class="pos-mode-btn active" @click="openRegisterDeviceModal()">
                            <i class="fa-solid fa-plus"></i>
                            <span>{{ __('Register Device') }}</span>
                        </button>
                    </div>

                    <table class="device-table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--border-color); text-align: left;">
                                <th style="padding: 10px 14px; color: var(--text-muted); font-size: 0.75rem;">{{ __('Device Name') }}</th>
                                <th style="padding: 10px 14px; color: var(--text-muted); font-size: 0.75rem;">{{ __('Device Type') }}</th>
                                <th style="padding: 10px 14px; color: var(--text-muted); font-size: 0.75rem;">{{ __('Pairing Code') }}</th>
                                <th style="padding: 10px 14px; color: var(--text-muted); font-size: 0.75rem;">{{ __('Status') }}</th>
                                <th style="padding: 10px 14px; color: var(--text-muted); font-size: 0.75rem; text-align: right;">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="dev in devices" :key="dev.id">
                                <tr style="border-bottom: 1px solid var(--border-color-light);">
                                    <td style="padding: 12px 14px;">
                                        <div style="font-weight: 600; color: #fff;" x-text="dev.name"></div>
                                        <div style="font-size: 0.7rem; color: var(--text-subtle);" x-text="dev.uuid"></div>
                                    </td>
                                    <td style="padding: 12px 14px; text-transform: capitalize;" x-text="dev.device_type"></td>
                                    <td style="padding: 12px 14px;">
                                        <code style="background: rgba(255,255,255,0.06); padding: 3px 6px; border-radius: 4px; color: var(--primary); font-weight: 700;" x-text="dev.pairing_code || 'PAIRED'"></code>
                                    </td>
                                    <td style="padding: 12px 14px;">
                                        <span class="status-pill" :class="{ 'offline': !dev.is_active }">
                                            <span class="status-dot"></span>
                                            <span x-text="dev.is_active ? '{{ __('Active') }}' : '{{ __('Inactive') }}'"></span>
                                        </span>
                                    </td>
                                    <td style="padding: 12px 14px; text-align: right;">
                                        <button type="button" class="pos-tool-btn" style="width: 32px; height: 32px; font-size: 0.8rem;" @click="regenerateCode(dev.id)" title="{{ __('Regenerate Code') }}">
                                            <i class="fa-solid fa-rotate"></i>
                                        </button>
                                        <button type="button" class="pos-tool-btn" style="width: 32px; height: 32px; font-size: 0.8rem; color: var(--danger);" @click="revokeDevice(dev.id)" title="{{ __('Revoke Device') }}">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODALS                                      -->
        <!-- ========================================== -->

        <!-- 1. Product Customizer Modal (Variants & Modifiers) -->
        <div class="pos-modal-overlay" x-show="customizingProduct" x-cloak>
            <div class="pos-modal-card">
                <div class="pos-modal-header">
                    <div>
                        <h3 style="font-family: var(--font-display); font-size: 1.15rem; font-weight: 700;" x-text="customizingProduct?.name"></h3>
                        <div style="font-size: 0.8rem; color: var(--primary); font-weight: 700;" x-text="formatCurrency(calculateCustomizerPrice())"></div>
                    </div>
                    <button type="button" class="pos-tool-btn" style="width: 34px; height: 34px;" @click="customizingProduct = null">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="pos-modal-body">
                    <!-- Variations -->
                    <template x-if="customizingProduct?.variations && customizingProduct.variations.length > 0">
                        <div>
                            <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; display: block; margin-bottom: 6px;">{{ __('Select Size / Variation') }}</label>
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                <template x-for="v in customizingProduct.variations" :key="v.id">
                                    <div class="cat-chip" 
                                         :class="{ 'active': selectedVariation && selectedVariation.id === v.id }"
                                         @click="selectedVariation = v">
                                        <span x-text="v.name"></span>
                                        <span style="font-weight: 700;" x-text="'(' + formatCurrency(v.price) + ')'"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- Modifiers / Extras -->
                    <div>
                        <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; display: block; margin-bottom: 6px;">{{ __('Extras & Modifiers') }}</label>
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <template x-for="m in (customizingProduct?.modifiers || [])" :key="m.id">
                                <div class="cat-chip"
                                     :class="{ 'active': isModifierSelected(m) }"
                                     @click="toggleModifier(m)">
                                    <span x-text="m.name"></span>
                                    <span style="font-size: 0.7rem; opacity: 0.8;" x-text="m.price > 0 ? '+' + formatCurrency(m.price) : ''"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; display: block; margin-bottom: 6px;">{{ __('Special Instructions / Notes') }}</label>
                        <input type="text" class="pos-input" x-model="customizerNotes" placeholder="e.g. Well done, extra spicy...">
                    </div>

                    <button type="button" class="cart-btn-primary" style="width: 100%; margin-top: 0.5rem;" @click="addCustomizedProductToCart()">
                        <span>{{ __('Add to Order') }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 2. Table Selection & Floor Plan Modal -->
        <div class="pos-modal-overlay" x-show="showTableModal" x-cloak>
            <div class="pos-modal-card" style="max-width: 680px; max-height: 90vh;">
                <div class="pos-modal-header">
                    <div>
                        <h3 style="font-family: var(--font-display); font-size: 1.15rem; font-weight: 700;">{{ __('Dining Areas & Tables') }}</h3>
                        <div style="font-size: 0.72rem; color: var(--text-muted);">{{ __('Select a table or manage occupied tables') }}</div>
                    </div>
                    <button type="button" class="pos-tool-btn" style="width: 34px; height: 34px;" @click="showTableModal = false">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="pos-modal-body" style="display: flex; flex-direction: column; gap: 0.85rem;">
                    <!-- Section Tabs -->
                    <div style="display: flex; gap: 0.5rem; overflow-x: auto; padding-bottom: 4px;">
                        <button type="button" class="pos-category-chip" :class="{ 'active': activeTableSection === 'all' }" @click="activeTableSection = 'all'">
                            {{ __('All Sections') }}
                        </button>
                        <template x-for="sec in tableSections" :key="sec">
                            <button type="button" class="pos-category-chip" :class="{ 'active': activeTableSection === sec }" @click="activeTableSection = sec" x-text="sec"></button>
                        </template>
                    </div>

                    <!-- Table Selection Grid -->
                    <div class="table-selection-grid">
                        <div class="table-select-card" :class="{ 'active': !selectedTable }" @click="selectTable(null)">
                            <i class="fa-solid fa-store" style="font-size: 1.2rem; margin-bottom: 4px; color: var(--primary);"></i>
                            <div style="font-size: 0.8rem; font-weight: 700;">{{ __('Counter') }}</div>
                            <span class="table-status-pill" style="background: rgba(255,255,255,0.06); color: var(--text-muted);">{{ __('Quick') }}</span>
                        </div>
                        <template x-for="t in filteredTables" :key="t.id">
                            <div class="table-select-card"
                                 :class="'status-' + t.status + (selectedTable && selectedTable.id === t.id ? ' active' : '')"
                                 @click="handleTableCardClick(t)">
                                <div style="display: flex; align-items: center; justify-content: space-between; width: 100%; margin-bottom: 2px;">
                                    <span class="table-status-pill"
                                          :style="getTableStatusStyle(t.status)"
                                          x-text="t.status"></span>
                                    <template x-if="t.order && t.order.source === 'qr_menu'">
                                        <i class="fa-solid fa-qrcode" style="font-size: 0.7rem; color: #10b981;" title="QR Menu Order"></i>
                                    </template>
                                </div>
                                <div style="font-size: 0.92rem; font-weight: 800; color: #fff;" x-text="t.table_number"></div>
                                <div style="font-size: 0.65rem; color: var(--text-subtle);" x-text="t.capacity + ' seats'"></div>
                                <template x-if="t.occupancy_minutes !== null">
                                    <div class="table-timer-badge">
                                        <i class="fa-regular fa-clock" style="font-size: 0.6rem;"></i>
                                        <span x-text="t.occupancy_minutes + 'm'"></span>
                                    </div>
                                </template>
                                <template x-if="t.order">
                                    <div style="font-size: 0.68rem; font-weight: 700; color: var(--text-main); margin-top: 2px;" x-text="formatCurrency(t.order.total_amount)"></div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2.1 Table Operations Detail Modal -->
        <div class="pos-modal-overlay" x-show="showTableActionModal" x-cloak>
            <div class="pos-modal-card" style="max-width: 440px;" @click.away="showTableActionModal = false">
                <div class="pos-modal-header">
                    <div>
                        <h3 style="font-family: var(--font-display); font-size: 1.15rem; font-weight: 700;" x-text="'Table ' + (selectedTableForActions?.table_number || '')"></h3>
                        <div style="font-size: 0.72rem; color: var(--text-muted);" x-text="'Status: ' + (selectedTableForActions?.status || 'available')"></div>
                    </div>
                    <button type="button" class="pos-tool-btn" style="width: 34px; height: 34px;" @click="showTableActionModal = false">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="pos-modal-body" style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <template x-if="selectedTableForActions?.order">
                        <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.75rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                <span style="font-size: 0.8rem; font-weight: 700; color: var(--primary);" x-text="selectedTableForActions.order.order_number"></span>
                                <span class="table-status-pill" :style="getTableStatusStyle(selectedTableForActions.order.status)" x-text="selectedTableForActions.order.status"></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--text-muted);">
                                <span x-text="'Waiter: ' + (selectedTableForActions.order.waiter_name || 'Unassigned')"></span>
                                <span style="font-weight: 700; color: #fff;" x-text="formatCurrency(selectedTableForActions.order.total_amount)"></span>
                            </div>
                            <template x-if="selectedTableForActions.order.source === 'qr_menu'">
                                <div style="margin-top: 4px; font-size: 0.7rem; color: #10b981; display: flex; align-items: center; gap: 4px;">
                                    <i class="fa-solid fa-qrcode"></i>
                                    <span>{{ __('Placed by Guest via QR Menu') }}</span>
                                </div>
                            </template>
                        </div>
                    </template>

                    <!-- Table Action Buttons -->
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <button type="button" class="cart-btn-primary" @click="selectTableFromActionModal()">
                            <i class="fa-solid fa-cart-plus"></i>
                            <span>{{ __('Select for Current Order') }}</span>
                        </button>
                        <template x-if="selectedTableForActions?.order">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                                <button type="button" class="cart-btn-secondary" @click="openTransferModal()">
                                    <i class="fa-solid fa-arrows-turn-to-dots"></i>
                                    <span>{{ __('Transfer Table') }}</span>
                                </button>
                                <button type="button" class="cart-btn-secondary" @click="openMergeModal()">
                                    <i class="fa-solid fa-object-group"></i>
                                    <span>{{ __('Merge Orders') }}</span>
                                </button>
                                <button type="button" class="cart-btn-secondary" @click="openSplitModal()">
                                    <i class="fa-solid fa-scissors"></i>
                                    <span>{{ __('Split Order') }}</span>
                                </button>
                                <button type="button" class="cart-btn-secondary" @click="openAssignWaiterModal()">
                                    <i class="fa-solid fa-user-tag"></i>
                                    <span>{{ __('Assign Waiter') }}</span>
                                </button>
                            </div>
                        </template>

                        <!-- Direct Status Overrides -->
                        <div style="display: flex; gap: 0.5rem; margin-top: 0.25rem;">
                            <button type="button" class="cart-btn-secondary" style="flex: 1; font-size: 0.75rem;" @click="changeTableStatusDirect(selectedTableForActions, 'cleaning')">
                                <i class="fa-solid fa-broom"></i>
                                <span>{{ __('Set to Cleaning') }}</span>
                            </button>
                            <button type="button" class="cart-btn-secondary" style="flex: 1; font-size: 0.75rem;" @click="changeTableStatusDirect(selectedTableForActions, 'available')">
                                <i class="fa-solid fa-circle-check"></i>
                                <span>{{ __('Set to Available') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2.2 Transfer Table Modal -->
        <div class="pos-modal-overlay" x-show="showTransferModal" x-cloak>
            <div class="pos-modal-card" style="max-width: 440px;" @click.away="showTransferModal = false">
                <div class="pos-modal-header">
                    <h3 style="font-family: var(--font-display); font-size: 1.15rem; font-weight: 700;">{{ __('Transfer Table') }}</h3>
                    <button type="button" class="pos-tool-btn" style="width: 34px; height: 34px;" @click="showTransferModal = false">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="pos-modal-body" style="display: flex; flex-direction: column; gap: 0.85rem;">
                    <div>
                        <label style="font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 6px;">{{ __('Destination Table') }}</label>
                        <select class="pos-input" x-model="destinationTableId">
                            <option value="">{{ __('Select Available Table') }}</option>
                            <template x-for="t in tables.filter(t => t.id !== selectedTableForActions?.id && (t.status === 'available' || t.status === 'cleaning'))" :key="t.id">
                                <option :value="t.id" x-text="t.table_number + ' (' + t.capacity + ' seats)'"></option>
                            </template>
                        </select>
                    </div>
                    <button type="button" class="cart-btn-primary" :disabled="!destinationTableId" @click="executeTransferTable()">
                        <span>{{ __('Confirm Transfer') }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 2.3 Merge Orders Modal -->
        <div class="pos-modal-overlay" x-show="showMergeModal" x-cloak>
            <div class="pos-modal-card" style="max-width: 440px;" @click.away="showMergeModal = false">
                <div class="pos-modal-header">
                    <h3 style="font-family: var(--font-display); font-size: 1.15rem; font-weight: 700;">{{ __('Merge Orders') }}</h3>
                    <button type="button" class="pos-tool-btn" style="width: 34px; height: 34px;" @click="showMergeModal = false">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="pos-modal-body" style="display: flex; flex-direction: column; gap: 0.85rem;">
                    <div>
                        <label style="font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 6px;">{{ __('Merge with Order') }}</label>
                        <select class="pos-input" x-model="targetMergeOrderId">
                            <option value="">{{ __('Select Target Order') }}</option>
                            <template x-for="t in tables.filter(t => t.order && t.order.id !== selectedTableForActions?.order?.id)" :key="t.order.id">
                                <option :value="t.order.id" x-text="t.order.order_number + ' (Table ' + t.table_number + ' - ' + formatCurrency(t.order.total_amount) + ')'"></option>
                            </template>
                        </select>
                    </div>
                    <button type="button" class="cart-btn-primary" :disabled="!targetMergeOrderId" @click="executeMergeOrders()">
                        <span>{{ __('Confirm Merge') }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 2.4 Split Order Modal -->
        <div class="pos-modal-overlay" x-show="showSplitModal" x-cloak>
            <div class="pos-modal-card" style="max-width: 500px;" @click.away="showSplitModal = false">
                <div class="pos-modal-header">
                    <h3 style="font-family: var(--font-display); font-size: 1.15rem; font-weight: 700;">{{ __('Split Order') }}</h3>
                    <button type="button" class="pos-tool-btn" style="width: 34px; height: 34px;" @click="showSplitModal = false">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="pos-modal-body" style="display: flex; flex-direction: column; gap: 0.85rem;">
                    <div style="font-size: 0.8rem; color: var(--text-muted);">{{ __('Select quantities to move to the new split bill:') }}</div>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem; max-height: 260px; overflow-y: auto;">
                        <template x-for="item in splitItems" :key="item.order_item_id">
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 6px 10px; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px;">
                                <div>
                                    <div style="font-size: 0.82rem; font-weight: 700;" x-text="item.name"></div>
                                    <div style="font-size: 0.7rem; color: var(--text-muted);" x-text="'Max: ' + item.max_quantity + ' × ' + formatCurrency(item.unit_price)"></div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <button type="button" class="pos-tool-btn" style="width: 28px; height: 28px;" @click="item.quantity = Math.max(0, item.quantity - 1)">-</button>
                                    <span style="font-size: 0.85rem; font-weight: 700; min-width: 20px; text-align: center;" x-text="item.quantity"></span>
                                    <button type="button" class="pos-tool-btn" style="width: 28px; height: 28px;" @click="item.quantity = Math.min(item.max_quantity, item.quantity + 1)">+</button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div>
                        <label style="font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 6px;">{{ __('Assign to New Table (Optional)') }}</label>
                        <select class="pos-input" x-model="splitTargetTableId">
                            <option value="">{{ __('Same Table / Sub-Bill') }}</option>
                            <template x-for="t in tables.filter(t => t.status === 'available')" :key="t.id">
                                <option :value="t.id" x-text="t.table_number + ' (' + t.capacity + ' seats)'"></option>
                            </template>
                        </select>
                    </div>

                    <button type="button" class="cart-btn-primary" @click="executeSplitOrder()">
                        <span>{{ __('Create Split Bill') }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 2.5 Assign Waiter Modal -->
        <div class="pos-modal-overlay" x-show="showAssignWaiterModal" x-cloak>
            <div class="pos-modal-card" style="max-width: 440px;" @click.away="showAssignWaiterModal = false">
                <div class="pos-modal-header">
                    <h3 style="font-family: var(--font-display); font-size: 1.15rem; font-weight: 700;">{{ __('Assign Waiter') }}</h3>
                    <button type="button" class="pos-tool-btn" style="width: 34px; height: 34px;" @click="showAssignWaiterModal = false">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="pos-modal-body" style="display: flex; flex-direction: column; gap: 0.85rem;">
                    <div>
                        <label style="font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 6px;">{{ __('Select Waiter') }}</label>
                        <select class="pos-input" x-model="assignedWaiterId">
                            <option value="">{{ __('Select Waiter') }}</option>
                            <template x-for="emp in employees" :key="emp.id">
                                <option :value="emp.id" x-text="emp.name + ' (' + emp.role + ')'"></option>
                            </template>
                        </select>
                    </div>
                    <button type="button" class="cart-btn-primary" :disabled="!assignedWaiterId" @click="executeAssignWaiter()">
                        <span>{{ __('Confirm Assignment') }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 3. Cash Payment / Calculator Modal -->
        <div class="pos-modal-overlay" x-show="showCashModal" x-cloak>
            <div class="pos-modal-card" style="max-width: 420px;">
                <div class="pos-modal-header">
                    <h3 style="font-family: var(--font-display); font-size: 1.15rem; font-weight: 700;">{{ __('Cash Payment') }}</h3>
                    <button type="button" class="pos-tool-btn" style="width: 34px; height: 34px;" @click="showCashModal = false">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="pos-modal-body">
                    <div style="text-align: center; margin-bottom: 0.5rem;">
                        <span style="font-size: 0.8rem; color: var(--text-muted);">{{ __('Total Due') }}</span>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #fff; font-family: var(--font-display);" x-text="formatCurrency(selectedCashierOrder?.total_amount || 0)"></div>
                    </div>

                    <div>
                        <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; display: block; margin-bottom: 6px;">{{ __('Amount Received') }}</label>
                        <input type="number" class="pos-input" x-model.number="cashReceived" style="font-size: 1.4rem; font-weight: 700; text-align: center;">
                    </div>

                    <!-- Presets -->
                    <div class="cash-preset-grid">
                        <button type="button" class="cash-preset-btn" @click="cashReceived = Number(selectedCashierOrder?.total_amount || 0)">{{ __('Exact') }}</button>
                        <button type="button" class="cash-preset-btn" @click="cashReceived = 5000">5,000</button>
                        <button type="button" class="cash-preset-btn" @click="cashReceived = 10000">10,000</button>
                        <button type="button" class="cash-preset-btn" @click="cashReceived = 20000">20,000</button>
                        <button type="button" class="cash-preset-btn" @click="cashReceived = 50000">50,000</button>
                        <button type="button" class="cash-preset-btn" @click="cashReceived = 0">{{ __('Clear') }}</button>
                    </div>

                    <!-- Change Calculation -->
                    <div style="background: rgba(0,0,0,0.3); border-radius: 10px; padding: 12px; display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.9rem; font-weight: 600;">{{ __('Change Due') }}:</span>
                        <span style="font-size: 1.35rem; font-weight: 800; color: #10b981; font-family: var(--font-display);" x-text="formatCurrency(Math.max(0, cashReceived - (selectedCashierOrder?.total_amount || 0)))"></span>
                    </div>

                    <button type="button" class="cart-btn-primary" style="width: 100%; height: 48px;" @click="finalizeCashPayment()">
                        <i class="fa-solid fa-check"></i>
                        <span>{{ __('Confirm Payment') }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 4. Add Discount Modal -->
        <div class="pos-modal-overlay" x-show="showDiscountModal" x-cloak>
            <div class="pos-modal-card" style="max-width: 400px;">
                <div class="pos-modal-header">
                    <h3 style="font-family: var(--font-display); font-size: 1.15rem; font-weight: 700;">{{ __('Add Discount') }}</h3>
                    <button type="button" class="pos-tool-btn" style="width: 34px; height: 34px;" @click="showDiscountModal = false">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="pos-modal-body">
                    <div>
                        <label style="font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 6px;">{{ __('Discount Amount') }}</label>
                        <input type="number" class="pos-input" x-model.number="tempDiscountAmount" placeholder="0.00">
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 6px;">{{ __('Discount Reason') }}</label>
                        <input type="text" class="pos-input" x-model="discountReason" placeholder="e.g. VIP, Happy Hour, Management">
                    </div>
                    <button type="button" class="cart-btn-primary" style="width: 100%;" @click="applyDiscount()">
                        <span>{{ __('Apply') }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 5. Quick Employee Switcher Modal -->
        <div class="pos-modal-overlay" x-show="showEmployeeSwitcher" x-cloak>
            <div class="pos-modal-card">
                <div class="pos-modal-header">
                    <h3 style="font-family: var(--font-display); font-size: 1.15rem; font-weight: 700;">{{ __('Select Employee') }}</h3>
                    <button type="button" class="pos-tool-btn" style="width: 34px; height: 34px;" @click="showEmployeeSwitcher = false">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="pos-modal-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 1rem;">
                        <template x-for="emp in employees" :key="emp.id">
                            <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 12px; padding: 1rem; text-align: center; cursor: pointer;" 
                                 @click="selectEmployeeForSwitch(emp)">
                                <div class="pos-user-avatar" style="width: 48px; height: 48px; font-size: 1.2rem; margin: 0 auto 0.5rem;">
                                    <span x-text="emp.display_name.charAt(0).toUpperCase()"></span>
                                </div>
                                <div style="font-size: 0.85rem; font-weight: 600; color: #fff;" x-text="emp.display_name"></div>
                                <div style="font-size: 0.65rem; color: var(--primary); text-transform: uppercase; font-weight: 700; margin-top: 2px;" x-text="emp.role"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- 6. PIN Change Modal -->
        <div class="pos-modal-overlay" x-show="showPinModal" x-cloak>
            <div class="pos-modal-card" style="max-width: 380px;">
                <div class="pos-modal-header">
                    <h3 style="font-family: var(--font-display); font-size: 1.15rem; font-weight: 700;">{{ __('Change PIN') }}</h3>
                    <button type="button" class="pos-tool-btn" style="width: 34px; height: 34px;" @click="showPinModal = false">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="pos-modal-body">
                    <label style="font-size: 0.8rem; color: var(--text-muted);">{{ __('Set New 4-Digit PIN') }}</label>
                    <input type="password" maxlength="6" class="pos-input" x-model="newPin" placeholder="••••" style="text-align: center; font-size: 1.5rem; letter-spacing: 6px;">
                    <button type="button" class="cart-btn-primary" style="width: 100%;" @click="submitNewPin()">
                        <span>{{ __('Save PIN') }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 7. PIN Screen Overlay -->
        <div class="pos-lock-screen" x-show="isLocked || switchingTargetUser" x-cloak>
            <div class="pin-pad-container">
                <div style="text-align: center;">
                    <div class="pos-user-avatar" style="width: 52px; height: 52px; font-size: 1.3rem; margin: 0 auto 0.75rem;">
                        <span x-text="(switchingTargetUser ? switchingTargetUser.display_name : currentUser.display_name).charAt(0).toUpperCase()"></span>
                    </div>
                    <h3 style="font-family: var(--font-display); font-size: 1.25rem; font-weight: 700;" x-text="switchingTargetUser ? switchingTargetUser.display_name : currentUser.display_name"></h3>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">{{ __('Enter 4-digit PIN to unlock') }}</p>
                    <div x-show="pinError" style="color: var(--danger); font-size: 0.75rem; margin-top: 4px; font-weight: 600;" x-text="pinError"></div>
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

        <!-- Toast Alert -->
        <div class="pos-toast" x-show="toastMessage" x-cloak>
            <i class="fa-solid fa-circle-check" style="color: #10b981;"></i>
            <span x-text="toastMessage"></span>
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
                toastMessage: '',
                bootstrapData: @json($bootstrapData),
                currentUser: @json($bootstrapData['user']),
                menu: @json($bootstrapData['menu'] ?? ['categories' => [], 'products' => [], 'favorites' => []]),
                tables: @json($bootstrapData['tables']['tables'] ?? []),
                tableSections: @json($bootstrapData['tables']['sections'] ?? ['Main Hall']),
                activeTableSection: 'all',
                selectedTable: null,
                selectedTableForActions: null,
                guestCount: 1,
                orderType: 'dine_in',
                searchQuery: '',
                activeCategory: 'all',

                // Cart state
                cart: [],
                discountAmount: 0,
                discountReason: '',
                tempDiscountAmount: 0,

                // Modals
                customizingProduct: null,
                selectedVariation: null,
                selectedModifiers: [],
                customizerNotes: '',
                showTableModal: false,
                showTableActionModal: false,
                showTransferModal: false,
                destinationTableId: null,
                showMergeModal: false,
                targetMergeOrderId: null,
                showSplitModal: false,
                splitTargetTableId: null,
                splitItems: [],
                showAssignWaiterModal: false,
                assignedWaiterId: null,
                showDiscountModal: false,
                showCashModal: false,
                cashReceived: 0,
                showEmployeeSwitcher: false,
                showPinModal: false,
                switchingTargetUser: null,
                newPin: '',

                // Cashier & Manager state
                cashierOrders: [],
                selectedCashierOrder: null,
                devices: [],
                employees: [],
                managerStats: {
                    todaySales: 0,
                    occupiedTables: 0
                },

                initPos() {
                    window.addEventListener('online', () => this.isOnline = true);
                    window.addEventListener('offline', () => this.isOnline = false);

                    this.loadTables();
                    this.loadDevices();
                    this.fetchEmployees();
                    this.fetchCashierOrders();

                    const autoLockSec = this.bootstrapData.settings?.auto_lock_seconds || 120;
                    if (autoLockSec > 0) {
                        this.setupAutoLock(autoLockSec);
                    }
                },

                formatCurrency(amount) {
                    const num = Number(amount || 0);
                    const symbol = this.bootstrapData.vendor?.currency_symbol || '֏';
                    return num.toLocaleString() + ' ' + symbol;
                },

                setMode(mode) {
                    this.activeMode = mode;
                    if (mode === 'cashier') {
                        this.fetchCashierOrders();
                    } else if (mode === 'manager') {
                        this.loadDevices();
                        this.refreshManagerStats();
                    }
                },

                switchBranch(locationId) {
                    this.activeLocationId = locationId;
                    this.fetchBootstrap();
                    this.loadTables();
                    this.fetchCashierOrders();
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
                            if (data.data.menu) {
                                this.menu = data.data.menu;
                            }
                        }
                    } catch (e) {
                        console.error('POS bootstrap sync failed:', e);
                    }
                },

                async loadTables() {
                    try {
                        const res = await fetch(`/pos/api/tables?location_id=${this.activeLocationId || ''}`, {
                            headers: { 'Accept': 'application/json' }
                        });
                        const data = await res.json();
                        if (data.success && data.data) {
                            this.tables = data.data.tables || [];
                            this.tableSections = data.data.sections || ['Main Hall'];
                        }
                    } catch (e) {
                        console.error('Failed to load tables:', e);
                    }
                },

                async fetchCashierOrders() {
                    try {
                        const res = await fetch(`/pos/api/orders?location_id=${this.activeLocationId || ''}`, {
                            headers: { 'Accept': 'application/json' }
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.cashierOrders = data.data;
                            this.refreshManagerStats();
                        }
                    } catch (e) {}
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
                    } catch (e) {}
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
                    } catch (e) {}
                },

                refreshManagerStats() {
                    let total = 0;
                    this.cashierOrders.forEach(o => {
                        total += Number(o.total_amount || 0);
                    });
                    this.managerStats.todaySales = total;
                    const tablesSet = new Set(this.cashierOrders.map(o => o.table_number).filter(t => t && t !== 'Counter'));
                    this.managerStats.occupiedTables = tablesSet.size;
                },

                // Product Catalog filter
                get filteredProducts() {
                    let list = this.menu.products || [];
                    if (this.activeCategory === 'favorites') {
                        list = this.menu.favorites || list.filter(p => p.is_featured);
                    } else if (this.activeCategory !== 'all') {
                        list = list.filter(p => p.category_id == this.activeCategory);
                    }
                    if (this.searchQuery.trim() !== '') {
                        const q = this.searchQuery.toLowerCase();
                        list = list.filter(p => p.name.toLowerCase().includes(q));
                    }
                    return list;
                },

                // Product Cart interaction
                handleProductClick(prod) {
                    if (prod.variations && prod.variations.length > 0) {
                        this.openCustomizer(prod);
                    } else {
                        this.addToCartDirect(prod);
                    }
                },

                addToCartDirect(prod) {
                    const existingIndex = this.cart.findIndex(i => i.product_id === prod.id && !i.variation_id && (!i.modifiers || i.modifiers.length === 0));
                    if (existingIndex > -1) {
                        this.cart[existingIndex].quantity++;
                    } else {
                        this.cart.push({
                            product_id: prod.id,
                            product_name: prod.name,
                            variation_id: null,
                            variation_name: null,
                            unit_price: Number(prod.price),
                            quantity: 1,
                            notes: '',
                            modifiers: [],
                            kitchen_station: prod.kitchen_station || 'kitchen'
                        });
                    }
                    this.showToast('{{ __('Added to cart') }}');
                },

                openCustomizer(prod) {
                    this.customizingProduct = prod;
                    this.selectedVariation = prod.variations && prod.variations.length > 0 ? prod.variations[0] : null;
                    this.selectedModifiers = [];
                    this.customizerNotes = '';
                },

                calculateCustomizerPrice() {
                    if (!this.customizingProduct) return 0;
                    let base = this.selectedVariation ? Number(this.selectedVariation.price) : Number(this.customizingProduct.price);
                    this.selectedModifiers.forEach(m => base += Number(m.price || 0));
                    return base;
                },

                isModifierSelected(m) {
                    return this.selectedModifiers.some(mod => mod.id === m.id);
                },

                toggleModifier(m) {
                    const idx = this.selectedModifiers.findIndex(mod => mod.id === m.id);
                    if (idx > -1) {
                        this.selectedModifiers.splice(idx, 1);
                    } else {
                        this.selectedModifiers.push(m);
                    }
                },

                addCustomizedProductToCart() {
                    const unitPrice = this.calculateCustomizerPrice();
                    this.cart.push({
                        product_id: this.customizingProduct.id,
                        product_name: this.customizingProduct.name,
                        variation_id: this.selectedVariation?.id || null,
                        variation_name: this.selectedVariation?.name || null,
                        unit_price: unitPrice,
                        quantity: 1,
                        notes: this.customizerNotes,
                        modifiers: [...this.selectedModifiers],
                        kitchen_station: this.customizingProduct.kitchen_station || 'kitchen'
                    });
                    this.customizingProduct = null;
                    this.showToast('{{ __('Added to cart') }}');
                },

                changeQty(index, delta) {
                    if (this.cart[index]) {
                        this.cart[index].quantity += delta;
                        if (this.cart[index].quantity <= 0) {
                            this.cart.splice(index, 1);
                        }
                    }
                },

                removeItem(index) {
                    this.cart.splice(index, 1);
                },

                clearCart() {
                    this.cart = [];
                    this.discountAmount = 0;
                    this.discountReason = '';
                },

                // Cart Financial Calculations
                get cartSubtotal() {
                    return this.cart.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
                },

                get taxAmount() {
                    const rate = Number(this.bootstrapData.settings?.tax_rate || 0);
                    if (rate <= 0 || this.bootstrapData.settings?.tax_inclusive) return 0;
                    return (this.cartSubtotal * rate) / 100;
                },

                get serviceFee() {
                    const rate = Number(this.bootstrapData.settings?.service_charge_percent || 0);
                    if (rate <= 0) return 0;
                    return (this.cartSubtotal * rate) / 100;
                },

                get cartTotal() {
                    return Math.max(0, this.cartSubtotal - this.discountAmount + this.taxAmount + this.serviceFee);
                },

                // Table Selection & Floor Plan Operations
                get filteredTables() {
                    if (this.activeTableSection === 'all') {
                        return this.tables;
                    }
                    return this.tables.filter(t => t.section === this.activeTableSection || t.dining_area === this.activeTableSection);
                },

                getTableStatusStyle(status) {
                    switch (status) {
                        case 'available': return 'background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3);';
                        case 'occupied': return 'background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3);';
                        case 'preparing': return 'background: rgba(139, 92, 246, 0.15); color: #8b5cf6; border: 1px solid rgba(139, 92, 246, 0.3);';
                        case 'ready': return 'background: rgba(6, 182, 212, 0.15); color: #06b6d4; border: 1px solid rgba(6, 182, 212, 0.3);';
                        case 'awaiting_payment': return 'background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3);';
                        case 'cleaning': return 'background: rgba(148, 163, 184, 0.15); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.3);';
                        case 'reserved': return 'background: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3);';
                        default: return 'background: rgba(255, 255, 255, 0.05); color: var(--text-muted);';
                    }
                },

                openTableModal() {
                    this.loadTables();
                    this.showTableModal = true;
                },

                handleTableCardClick(table) {
                    if (table.status === 'occupied' || table.order) {
                        this.selectedTableForActions = table;
                        this.showTableActionModal = true;
                    } else {
                        this.selectTable(table);
                    }
                },

                selectTable(table) {
                    this.selectedTable = table;
                    this.showTableModal = false;
                },

                selectTableFromActionModal() {
                    this.selectedTable = this.selectedTableForActions;
                    this.showTableActionModal = false;
                    this.showTableModal = false;
                },

                openTransferModal() {
                    this.destinationTableId = '';
                    this.showTableActionModal = false;
                    this.showTransferModal = true;
                },

                async executeTransferTable() {
                    if (!this.selectedTableForActions?.order?.id || !this.destinationTableId) return;
                    try {
                        const res = await fetch(`/pos/api/orders/${this.selectedTableForActions.order.id}/transfer-table`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                destination_table_id: this.destinationTableId,
                                lock_version: this.selectedTableForActions.order.lock_version
                            })
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.showTransferModal = false;
                            this.showTableModal = false;
                            this.showToast('{{ __('Order Transferred Successfully') }}');
                            await this.loadTables();
                        } else {
                            alert(data.message || 'Transfer failed');
                        }
                    } catch (e) {
                        alert('Error during table transfer');
                    }
                },

                openMergeModal() {
                    this.targetMergeOrderId = '';
                    this.showTableActionModal = false;
                    this.showMergeModal = true;
                },

                async executeMergeOrders() {
                    if (!this.selectedTableForActions?.order?.id || !this.targetMergeOrderId) return;
                    try {
                        const res = await fetch('/pos/api/orders/merge', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                source_order_id: this.selectedTableForActions.order.id,
                                target_order_id: this.targetMergeOrderId
                            })
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.showMergeModal = false;
                            this.showTableModal = false;
                            this.showToast('{{ __('Orders Merged Successfully') }}');
                            await this.loadTables();
                        } else {
                            alert(data.message || 'Merge failed');
                        }
                    } catch (e) {
                        alert('Error during order merge');
                    }
                },

                async openSplitModal() {
                    if (!this.selectedTableForActions?.order?.id) return;
                    try {
                        const res = await fetch(`/pos/api/orders/${this.selectedTableForActions.order.id}`, {
                            headers: { 'Accept': 'application/json' }
                        });
                        const data = await res.json();
                        if (data.success && data.order?.items) {
                            this.splitItems = data.order.items.map(i => ({
                                order_item_id: i.id,
                                name: i.product_name,
                                unit_price: i.unit_price,
                                max_quantity: i.quantity,
                                quantity: 0
                            }));
                            this.splitTargetTableId = '';
                            this.showTableActionModal = false;
                            this.showSplitModal = true;
                        }
                    } catch (e) {
                        alert('Error loading order for split');
                    }
                },

                async executeSplitOrder() {
                    const toSplit = this.splitItems.filter(i => i.quantity > 0).map(i => ({
                        order_item_id: i.order_item_id,
                        quantity: i.quantity
                    }));
                    if (toSplit.length === 0) {
                        alert('{{ __('Please select at least one item to split.') }}');
                        return;
                    }
                    try {
                        const res = await fetch(`/pos/api/orders/${this.selectedTableForActions.order.id}/split`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                items: toSplit,
                                target_table_id: this.splitTargetTableId || null
                            })
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.showSplitModal = false;
                            this.showTableModal = false;
                            this.showToast('{{ __('Order Split Successfully') }}');
                            await this.loadTables();
                        } else {
                            alert(data.message || 'Split failed');
                        }
                    } catch (e) {
                        alert('Error during order split');
                    }
                },

                openAssignWaiterModal() {
                    this.assignedWaiterId = this.selectedTableForActions?.order?.waiter_id || '';
                    this.showTableActionModal = false;
                    this.showAssignWaiterModal = true;
                },

                async executeAssignWaiter() {
                    if (!this.selectedTableForActions?.order?.id || !this.assignedWaiterId) return;
                    try {
                        const res = await fetch(`/pos/api/orders/${this.selectedTableForActions.order.id}/assign-waiter`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                waiter_id: this.assignedWaiterId
                            })
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.showAssignWaiterModal = false;
                            this.showTableModal = false;
                            this.showToast('{{ __('Waiter Assigned Successfully') }}');
                            await this.loadTables();
                        } else {
                            alert(data.message || 'Assign waiter failed');
                        }
                    } catch (e) {
                        alert('Error during waiter assignment');
                    }
                },

                async changeTableStatusDirect(table, status) {
                    if (!table) return;
                    try {
                        const res = await fetch(`/pos/api/tables/${table.id}/status`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                status: status,
                                lock_version: table.lock_version
                            })
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.showTableActionModal = false;
                            this.showToast('{{ __('Table status updated') }}');
                            await this.loadTables();
                        } else {
                            alert(data.message || 'Status update failed');
                        }
                    } catch (e) {
                        alert('Error updating table status');
                    }
                },

                // Discount Modal
                openDiscountModal() {
                    this.tempDiscountAmount = this.discountAmount;
                    this.showDiscountModal = true;
                },

                applyDiscount() {
                    this.discountAmount = Math.max(0, Number(this.tempDiscountAmount || 0));
                    this.showDiscountModal = false;
                    this.showToast('{{ __('Discount applied') }}');
                },

                // Order Submission to Kitchen
                async submitOrderToKitchen() {
                    if (this.cart.length === 0) return;

                    const payload = {
                        location_id: this.activeLocationId,
                        type: this.orderType,
                        table_number: this.selectedTable ? this.selectedTable.table_number : 'Counter',
                        restaurant_table_id: this.selectedTable ? this.selectedTable.id : null,
                        guest_count: this.guestCount,
                        discount_amount: this.discountAmount,
                        discount_reason: this.discountReason,
                        tax_amount: this.taxAmount,
                        items: this.cart.map(i => ({
                            product_id: i.product_id,
                            quantity: i.quantity,
                            variation_id: i.variation_id,
                            variation_name: i.variation_name,
                            notes: i.notes,
                            modifiers: i.modifiers,
                            kitchen_station: i.kitchen_station
                        }))
                    };

                    try {
                        const res = await fetch('/pos/api/orders', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(payload)
                        });

                        const data = await res.json();
                        if (data.success) {
                            this.clearCart();
                            this.showToast('{{ __('Order Sent to Kitchen!') }}');
                            this.fetchCashierOrders();
                            await this.loadTables();
                        } else {
                            alert(data.message || 'Error submitting order');
                        }
                    } catch (e) {
                        console.error('Submit order failed:', e);
                        alert('Order failed to submit');
                    }
                },

                holdCurrentOrder() {
                    if (this.cart.length === 0) return;
                    this.submitOrderToKitchen(); // Saves order to active orders queue
                },

                // Cashier selection & payment
                selectOrderForCashier(order) {
                    this.selectedCashierOrder = order;
                },

                openCashPaymentModal() {
                    if (!this.selectedCashierOrder) return;
                    this.cashReceived = Number(this.selectedCashierOrder.total_amount);
                    this.showCashModal = true;
                },

                async finalizeCashPayment() {
                    if (!this.selectedCashierOrder) return;
                    try {
                        const res = await fetch(`/pos/api/orders/${this.selectedCashierOrder.id}/status`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({ status: 'completed', reason: 'Paid in Cash' })
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.showCashModal = false;
                            this.selectedCashierOrder = null;
                            this.showToast('{{ __('Payment Confirmed!') }}');
                            this.fetchCashierOrders();
                        }
                    } catch (e) {
                        console.error(e);
                    }
                },

                async processCardPayment() {
                    if (!this.selectedCashierOrder) return;
                    if (!confirm('{{ __('Confirm Card Payment?') }}')) return;
                    try {
                        const res = await fetch(`/pos/api/orders/${this.selectedCashierOrder.id}/status`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({ status: 'completed', reason: 'Paid with Card' })
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.selectedCashierOrder = null;
                            this.showToast('{{ __('Payment Confirmed!') }}');
                            this.fetchCashierOrders();
                        }
                    } catch (e) {
                        console.error(e);
                    }
                },

                // Employee switcher & PIN pad
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
                            this.showToast('{{ __('PIN code updated successfully.') }}');
                        }
                    } catch (e) {
                        console.error(e);
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
                    this.switchingTargetUser = null;
                },

                async submitPinAction() {
                    if (this.switchingTargetUser) {
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
                                this.showToast('{{ __('Switched employee successfully.') }}');
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
                                this.showToast('{{ __('Terminal unlocked.') }}');
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

                showToast(msg) {
                    this.toastMessage = msg;
                    setTimeout(() => { this.toastMessage = ''; }, 3000);
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
