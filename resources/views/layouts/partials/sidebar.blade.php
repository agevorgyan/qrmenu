<!-- Sidebar Navigation Component -->
<aside class="sidebar" 
       :class="{ 'mobile-open': mobileOpen }"
       role="dialog" 
       aria-modal="true" 
       aria-label="{{ __('Navigation Menu') }}"
       x-data="{
           reservationsOpen: {{ (request()->routeIs('admin.reservations.*') || request()->routeIs('admin.event_inquiries.*') || request()->routeIs('admin.settings.reservations*')) ? 'true' : 'false' }},
           tablesOpen: {{ (request()->routeIs('admin.floor_plan.*') || request()->routeIs('admin.tables.*') || request()->routeIs('admin.qr.*')) ? 'true' : 'false' }},
           aiOpen: {{ (request()->routeIs('admin.ai.*') || request()->routeIs('admin.settings.ai*')) ? 'true' : 'false' }},
           settingsOpen: {{ (request()->routeIs('admin.settings.*') || request()->routeIs('admin.profile')) ? 'true' : 'false' }}
       }"
       @keydown.window.escape="if (mobileOpen) mobileOpen = false">

    <!-- Sidebar Brand & Identity Context -->
    <div class="sidebar-brand">
        @if(Auth::user()?->isSuperAdmin())
            @php
                $sysLogo = \App\Models\SystemSetting::getLogoDark();
                $sysName = \App\Models\SystemSetting::getSiteName();
            @endphp
            <div style="display: flex; align-items: center; gap: 0.75rem; width: 100%; min-width: 0;">
                @if($sysLogo)
                    <img src="{{ $sysLogo }}" alt="{{ $sysName }}" style="max-height: 38px; max-width: 140px; object-fit: contain;">
                @else
                    <div style="width: 42px; height: 42px; border-radius: 12px; background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); display: flex; align-items: center; justify-content: center; color: #ffffff; font-size: 1.2rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4); flex-shrink: 0;">
                        <i class="fa-solid fa-crown"></i>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <h1 style="font-size: 1.15rem; font-weight: 800; margin: 0; background: linear-gradient(135deg, #6366f1 0%, #ec4899 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $sysName }}</h1>
                        <div style="font-size: 0.68rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">SuperAdmin Console</div>
                    </div>
                @endif
            </div>
        @else
            @php 
                $currVendor = Auth::user()?->vendor;
                $activeLocation = session('active_location_id') 
                    ? $currVendor?->locations->firstWhere('id', session('active_location_id')) 
                    : $currVendor?->locations->first();
            @endphp
            <div style="display: flex; align-items: center; gap: 0.85rem; width: 100%; min-width: 0;">
                @if($currVendor?->logo)
                    <img src="{{ $currVendor->logo }}" alt="{{ $currVendor->name }}" style="width: 42px; height: 42px; border-radius: 12px; object-fit: cover; border: 2px solid var(--border-color); flex-shrink: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                @else
                    <div style="width: 42px; height: 42px; border-radius: 12px; background: var(--primary-gradient); display: flex; align-items: center; justify-content: center; color: #ffffff; font-size: 1.2rem; font-weight: 800; flex-shrink: 0; box-shadow: 0 4px 14px var(--primary-glow);">
                        <i class="fa-solid fa-utensils"></i>
                    </div>
                @endif
                <div style="flex: 1; min-width: 0;">
                    <a href="{{ route('admin.dashboard') }}" style="text-decoration: none; display: block; min-width: 0;">
                        <div style="font-size: 1.05rem; font-weight: 800; margin: 0; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $currVendor?->name ?? 'QRMenu Admin' }}">
                            {{ $currVendor?->name ?? 'QRMenu Admin' }}
                        </div>
                    </a>
                    <div style="display: flex; align-items: center; gap: 0.35rem; margin-top: 0.15rem;">
                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981; box-shadow: 0 0 6px #10b981; flex-shrink: 0;"></span>
                        <span style="font-size: 0.7rem; font-weight: 600; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $activeLocation?->name ?? __('Active Branch') }}">
                            {{ $activeLocation?->name ?? __('Active Branch') }}
                        </span>
                    </div>
                </div>
            </div>
        @endif

        <button type="button" class="sidebar-close-btn" @click="mobileOpen = false" aria-label="{{ __('Close menu') }}">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <!-- Mobile Burger Menu Quick Controls: Language, Theme & 2FA -->
    <div class="sidebar-quick-panel">
        <div class="sidebar-quick-row">
            <!-- Theme Switcher -->
            <button type="button" class="sidebar-quick-action-btn" @click="toggleTheme()" title="Toggle Light / Dark Mode">
                <i class="fa-solid" :class="currentTheme === 'dark' ? 'fa-sun' : 'fa-moon'" style="color: var(--primary); font-size: 0.95rem;"></i>
                <span x-text="currentTheme === 'dark' ? '{{ __('Light') }}' : '{{ __('Dark') }}'"></span>
            </button>

            <!-- Security & 2FA Profile -->
            <a href="{{ Auth::user()?->isSuperAdmin() ? route('superadmin.settings.index') . '#security-section' : route('admin.profile') }}" class="sidebar-quick-action-btn" title="{{ __('Security & 2FA') }}">
                <i class="fa-solid fa-shield-halved" style="color: {{ Auth::user()?->hasTwoFactorEnabled() ? '#10b981' : '#f59e0b' }}; font-size: 0.95rem;"></i>
                <span>{{ __('2FA & Profile') }}</span>
            </a>
        </div>

        <!-- Language Switcher Segmented Pills -->
        <div class="sidebar-lang-selector">
            <a href="{{ route('lang.switch', 'hy') }}" class="sidebar-lang-pill {{ app()->getLocale() === 'hy' ? 'active' : '' }}">
                <span>🇦🇲</span> <span>ՀԱՅ</span>
            </a>
            <a href="{{ route('lang.switch', 'ru') }}" class="sidebar-lang-pill {{ app()->getLocale() === 'ru' ? 'active' : '' }}">
                <span>🇷🇺</span> <span>РУС</span>
            </a>
            <a href="{{ route('lang.switch', 'en') }}" class="sidebar-lang-pill {{ app()->getLocale() === 'en' ? 'active' : '' }}">
                <span>🇬🇧</span> <span>ENG</span>
            </a>
        </div>
    </div>

    <!-- Main Navigation Menu -->
    <nav class="sidebar-menu" role="navigation" aria-label="{{ __('Sidebar Navigation') }}">
        @if(Auth::user()?->isSuperAdmin())
            <!-- Super Admin Core Menu -->
            <div class="menu-category">{{ __('Super Admin') }}</div>
            <a href="{{ route('superadmin.dashboard') }}" class="nav-item {{ request()->routeIs('superadmin.dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-line" style="color: #6366f1;"></i> <span>{{ __('Dashboard') }}</span>
            </a>
            <a href="{{ route('superadmin.vendors.index') }}" class="nav-item {{ request()->routeIs('superadmin.vendors.*') ? 'active' : '' }}">
                <i class="fa-solid fa-store" style="color: #10b981;"></i> <span>{{ __('Vendor Directory') }}</span>
            </a>
            <a href="{{ route('superadmin.plans.index') }}" class="nav-item {{ request()->routeIs('superadmin.plans.*') ? 'active' : '' }}">
                <i class="fa-solid fa-box-archive" style="color: #f59e0b;"></i> <span>{{ __('Plans') }}</span>
            </a>
            <a href="{{ route('superadmin.subscriptions.index') }}" class="nav-item {{ request()->routeIs('superadmin.subscriptions.*') ? 'active' : '' }}">
                <i class="fa-solid fa-credit-card" style="color: #ec4899;"></i> <span>{{ __('Vendor Subscriptions') }}</span>
            </a>
            <a href="{{ route('superadmin.settings.index') }}" class="nav-item {{ request()->routeIs('superadmin.settings.*') ? 'active' : '' }}">
                <i class="fa-solid fa-sliders" style="color: #06b6d4;"></i> <span>{{ __('Settings & Landing') }}</span>
            </a>
            <a href="{{ route('superadmin.languages.index') }}" class="nav-item {{ request()->routeIs('superadmin.languages.*') ? 'active' : '' }}">
                <i class="fa-solid fa-language" style="color: #a855f7;"></i> <span>{{ __('Languages & i18n') }}</span>
            </a>

            <div class="menu-category">{{ __('Email Management') }}</div>
            <a href="{{ route('superadmin.emails.overview') }}" class="nav-item {{ request()->routeIs('superadmin.emails.overview') ? 'active' : '' }}">
                <i class="fa-solid fa-gauge-high" style="color: #3b82f6;"></i> <span>{{ __('Overview') }}</span>
            </a>
            <a href="{{ route('superadmin.emails.senders.index') }}" class="nav-item {{ request()->routeIs('superadmin.emails.senders.*') ? 'active' : '' }}">
                <i class="fa-solid fa-id-card-clip" style="color: #10b981;"></i> <span>{{ __('Sender Identities') }}</span>
            </a>
            <a href="{{ route('superadmin.emails.connections.index') }}" class="nav-item {{ request()->routeIs('superadmin.emails.connections.*') ? 'active' : '' }}">
                <i class="fa-solid fa-server" style="color: #f59e0b;"></i> <span>{{ __('SMTP Connections') }}</span>
            </a>
            <a href="{{ route('superadmin.emails.routing.index') }}" class="nav-item {{ request()->routeIs('superadmin.emails.routing.*') ? 'active' : '' }}">
                <i class="fa-solid fa-route" style="color: #8b5cf6;"></i> <span>{{ __('Email Routing') }}</span>
            </a>
            <a href="{{ route('superadmin.emails.templates.index') }}" class="nav-item {{ request()->routeIs('superadmin.emails.templates.*') ? 'active' : '' }}">
                <i class="fa-solid fa-envelope-open-text" style="color: #ec4899;"></i> <span>{{ __('Templates') }}</span>
            </a>
            <a href="{{ route('superadmin.emails.logs.index') }}" class="nav-item {{ request()->routeIs('superadmin.emails.logs.*') ? 'active' : '' }}">
                <i class="fa-solid fa-list-check" style="color: #06b6d4;"></i> <span>{{ __('Email Logs') }}</span>
            </a>
            <a href="{{ route('superadmin.emails.queue.index') }}" class="nav-item {{ request()->routeIs('superadmin.emails.queue.*') ? 'active' : '' }}">
                <i class="fa-solid fa-clock-rotate-left" style="color: #eab308;"></i> <span>{{ __('Queue Monitor') }}</span>
            </a>
            <a href="{{ route('superadmin.emails.failed.index') }}" class="nav-item {{ request()->routeIs('superadmin.emails.failed.*') ? 'active' : '' }}">
                <i class="fa-solid fa-triangle-exclamation" style="color: #ef4444;"></i> <span>{{ __('Failed Emails') }}</span>
            </a>
            <a href="{{ route('superadmin.emails.reports.index') }}" class="nav-item {{ request()->routeIs('superadmin.emails.reports.*') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-pie" style="color: #6366f1;"></i> <span>{{ __('Reports') }}</span>
            </a>
            <a href="{{ route('superadmin.emails.settings.index') }}" class="nav-item {{ request()->routeIs('superadmin.emails.settings.*') ? 'active' : '' }}">
                <i class="fa-solid fa-gear" style="color: #94a3b8;"></i> <span>{{ __('Email Settings') }}</span>
            </a>
        @else
            @php 
                $v = Auth::user()?->vendor;
            @endphp

            <!-- ========================================== -->
            <!-- A. OVERVIEW                                -->
            <!-- ========================================== -->
            <div class="menu-category">{{ __('Overview') }}</div>
            <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" title="{{ __('Dashboard') }}">
                <i class="fa-solid fa-gauge-high" style="color: #f59e0b;"></i>
                <span>{{ __('Dashboard') }}</span>
            </a>

            <!-- ========================================== -->
            <!-- B. DAILY OPERATIONS                        -->
            <!-- ========================================== -->
            <div class="menu-category">{{ __('Daily Operations') }}</div>

            @can('orders.view')
                <a href="{{ route('admin.orders.index') }}" 
                   class="nav-item {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}" 
                   style="justify-content: space-between;"
                   title="{{ __('Orders') }}">
                    <span style="display: flex; align-items: center; gap: 0.75rem; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <i class="fa-solid fa-bell-concierge" style="color: #ef4444;"></i>
                        <span>{{ __('Orders') }}</span>
                    </span>
                    @if($v && !$v->hasFeature('orders'))
                        <span class="nav-lock-badge">🔒 PRO</span>
                    @endif
                </a>
            @endcan

            <!-- Reservations & Event Inquiries (Collapsible Group) -->
            <div class="nav-group">
                <button type="button" 
                        class="nav-group-header {{ (request()->routeIs('admin.reservations.*') || request()->routeIs('admin.event_inquiries.*') || request()->routeIs('admin.settings.reservations*')) ? 'active' : '' }}"
                        @click="reservationsOpen = !reservationsOpen"
                        aria-expanded="reservationsOpen"
                        title="{{ __('Reservations') }}">
                    <span style="display: flex; align-items: center; gap: 0.75rem; min-width: 0;">
                        <i class="fa-solid fa-calendar-check" style="color: #ec4899;"></i>
                        <span>{{ __('Reservations') }}</span>
                    </span>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        @if($v && !$v->hasFeature('reservations'))
                            <span class="nav-lock-badge">🔒 PRO</span>
                        @endif
                        <i class="fa-solid fa-chevron-down nav-chevron" :class="reservationsOpen ? 'rotate-180' : ''"></i>
                    </div>
                </button>

                <div class="nav-group-children" x-show="reservationsOpen" x-transition.opacity.duration.200ms style="display: {{ (request()->routeIs('admin.reservations.*') || request()->routeIs('admin.event_inquiries.*') || request()->routeIs('admin.settings.reservations*')) ? 'flex' : 'none' }};">
                    <a href="{{ route('admin.reservations.index') }}" class="nav-sub-item {{ request()->routeIs('admin.reservations.index') || request()->routeIs('admin.reservations.show') ? 'active' : '' }}" title="{{ __('Table Reservations') }}">
                        <span style="display: flex; align-items: center; gap: 0.6rem;">
                            <i class="fa-solid fa-chair" style="font-size: 0.85rem; color: #ec4899;"></i>
                            <span>{{ __('Table Reservations') }}</span>
                        </span>
                    </a>

                    <a href="{{ route('admin.event_inquiries.index') }}" class="nav-sub-item {{ request()->routeIs('admin.event_inquiries.*') ? 'active' : '' }}" title="{{ __('Event Planning Inquiries') }}">
                        <span style="display: flex; align-items: center; gap: 0.6rem;">
                            <i class="fa-solid fa-champagne-glasses" style="font-size: 0.85rem; color: #a855f7;"></i>
                            <span>{{ __('Event Planning Inquiries') }}</span>
                        </span>
                    </a>
                </div>
            </div>

            <!-- Dining Room & Tables (Collapsible Group) -->
            <div class="nav-group">
                <button type="button" 
                        class="nav-group-header {{ (request()->routeIs('admin.floor_plan.*') || request()->routeIs('admin.tables.*') || request()->routeIs('admin.qr.*')) ? 'active' : '' }}"
                        @click="tablesOpen = !tablesOpen"
                        aria-expanded="tablesOpen"
                        title="{{ __('Dining Room & Tables') }}">
                    <span style="display: flex; align-items: center; gap: 0.75rem; min-width: 0;">
                        <i class="fa-solid fa-chair" style="color: #06b6d4;"></i>
                        <span>{{ __('Dining Room & Tables') }}</span>
                    </span>
                    <i class="fa-solid fa-chevron-down nav-chevron" :class="tablesOpen ? 'rotate-180' : ''"></i>
                </button>

                <div class="nav-group-children" x-show="tablesOpen" x-transition.opacity.duration.200ms style="display: {{ (request()->routeIs('admin.floor_plan.*') || request()->routeIs('admin.tables.*') || request()->routeIs('admin.qr.*')) ? 'flex' : 'none' }};">
                    @can('locations.view')
                        <a href="{{ route('admin.floor_plan.index') }}" class="nav-sub-item {{ request()->routeIs('admin.floor_plan.*') ? 'active' : '' }}" title="{{ __('Table Map') }}">
                            <span style="display: flex; align-items: center; gap: 0.6rem;">
                                <i class="fa-solid fa-map-location-dot" style="font-size: 0.85rem; color: #3b82f6;"></i>
                                <span>{{ __('Table Map') }}</span>
                            </span>
                            @if($v && !$v->hasFeature('orders'))
                                <span class="nav-lock-badge">🔒 PRO</span>
                            @endif
                        </a>
                    @endcan

                    <a href="{{ route('admin.tables.index') }}" class="nav-sub-item {{ request()->routeIs('admin.tables.*') ? 'active' : '' }}" title="{{ __('Table Management') }}">
                        <span style="display: flex; align-items: center; gap: 0.6rem;">
                            <i class="fa-solid fa-table-cells-large" style="font-size: 0.85rem; color: #06b6d4;"></i>
                            <span>{{ __('Table Management') }}</span>
                        </span>
                        @if($v && !$v->hasFeature('reservations'))
                            <span class="nav-lock-badge">🔒 PRO</span>
                        @endif
                    </a>

                    @can('settings.view')
                        <a href="{{ route('admin.qr.index') }}" class="nav-sub-item {{ request()->routeIs('admin.qr.*') ? 'active' : '' }}" title="{{ __('Table QR Codes') }}">
                            <span style="display: flex; align-items: center; gap: 0.6rem;">
                                <i class="fa-solid fa-qrcode" style="font-size: 0.85rem; color: #10b981;"></i>
                                <span>{{ __('Table QR Codes') }}</span>
                            </span>
                        </a>
                    @endcan
                </div>
            </div>

            @can('menu.view')
                <a href="{{ route('admin.menu.index') }}" class="nav-item {{ request()->routeIs('admin.menu.*') ? 'active' : '' }}" title="{{ __('Menu') }}">
                    <i class="fa-solid fa-utensils" style="color: #10b981;"></i>
                    <span>{{ __('Menu') }}</span>
                </a>
            @endcan

            <!-- ========================================== -->
            <!-- C. CUSTOMER EXPERIENCE & GROWTH            -->
            <!-- ========================================== -->
            <div class="menu-category">{{ __('Customer Experience & Growth') }}</div>

            @can('settings.view')
                <a href="{{ route('admin.branding.index') }}" class="nav-item {{ request()->routeIs('admin.branding.*') ? 'active' : '' }}" title="{{ __('Menu Design') }}">
                    <i class="fa-solid fa-palette" style="color: #ec4899;"></i>
                    <span>{{ __('Menu Design') }}</span>
                </a>
            @endcan

            @can('menu.view')
                <a href="{{ route('admin.translations.index') }}" class="nav-item {{ request()->routeIs('admin.translations.*') ? 'active' : '' }}" title="{{ __('Languages & Translations') }}">
                    <i class="fa-solid fa-language" style="color: #6366f1;"></i>
                    <span>{{ __('Languages & Translations') }}</span>
                </a>
            @endcan

            @can('ai.view')
                <!-- AI Assistant (Collapsible Group) -->
                <div class="nav-group">
                    <button type="button" 
                            class="nav-group-header {{ (request()->routeIs('admin.ai.*') || request()->routeIs('admin.settings.ai*')) ? 'active' : '' }}"
                            @click="aiOpen = !aiOpen"
                            aria-expanded="aiOpen"
                            title="{{ __('AI Assistant') }}">
                        <span style="display: flex; align-items: center; gap: 0.75rem; min-width: 0;">
                            <i class="fa-solid fa-wand-magic-sparkles" style="color: #8b5cf6;"></i>
                            <span>{{ __('AI Assistant') }}</span>
                        </span>
                        <span style="display: flex; align-items: center; gap: 0.35rem;">
                            <span class="nav-ai-badge">AI</span>
                            <i class="fa-solid fa-chevron-down nav-chevron" :class="aiOpen ? 'rotate-180' : ''"></i>
                        </span>
                    </button>

                    <div class="nav-group-children" x-show="aiOpen" x-transition.opacity.duration.200ms style="display: {{ (request()->routeIs('admin.ai.*') || request()->routeIs('admin.settings.ai*')) ? 'flex' : 'none' }};">
                        <a href="{{ route('admin.ai.import') }}" class="nav-sub-item {{ request()->routeIs('admin.ai.*') ? 'active' : '' }}" title="{{ __('AI Menu & Translate') }}">
                            <span style="display: flex; align-items: center; gap: 0.6rem;">
                                <i class="fa-solid fa-file-import" style="font-size: 0.85rem; color: #8b5cf6;"></i>
                                <span>{{ __('AI Menu & Translate') }}</span>
                            </span>
                            @if($v && !$v->hasFeature('ai.menu_builder'))
                                <span class="nav-lock-badge">🔒 BIZ</span>
                            @endif
                        </a>

                        <a href="{{ route('admin.settings.ai') }}" class="nav-sub-item {{ request()->routeIs('admin.settings.ai*') ? 'active' : '' }}" title="{{ __('AI Waiter & Recommendations') }}">
                            <span style="display: flex; align-items: center; gap: 0.6rem;">
                                <i class="fa-solid fa-robot" style="font-size: 0.85rem; color: #ec4899;"></i>
                                <span>{{ __('AI Waiter & Recommendations') }}</span>
                            </span>
                            @if($v && !$v->hasFeature('ai.waiter'))
                                <span class="nav-lock-badge">🔒 PRO</span>
                            @endif
                        </a>
                    </div>
                </div>
            @endcan

            @can('customers.view')
                <a href="{{ route('admin.customers.index') }}" 
                   class="nav-item {{ request()->routeIs('admin.customers.*') ? 'active' : '' }}" 
                   style="justify-content: space-between;"
                   title="{{ __('Customers & CRM') }}">
                    <span style="display: flex; align-items: center; gap: 0.75rem; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <i class="fa-solid fa-users-gear" style="color: #3b82f6;"></i>
                        <span>{{ __('Customers & CRM') }}</span>
                    </span>
                    @if($v && !$v->hasFeature('customers'))
                        <span class="nav-lock-badge">🔒 PRO</span>
                    @endif
                </a>
            @endcan

            @can('reports.view')
                <a href="{{ route('admin.analytics.index') }}" class="nav-item {{ request()->routeIs('admin.analytics.*') ? 'active' : '' }}" title="{{ __('Analytics & Reports') }}">
                    <i class="fa-solid fa-chart-pie" style="color: #f97316;"></i>
                    <span>{{ __('Analytics & Reports') }}</span>
                </a>
            @endcan

            <!-- ========================================== -->
            <!-- D. BUSINESS MANAGEMENT                     -->
            <!-- ========================================== -->
            <div class="menu-category">{{ __('Business Management') }}</div>

            @can('locations.view')
                <a href="{{ route('admin.locations.index') }}" 
                   class="nav-item {{ request()->routeIs('admin.locations.*') ? 'active' : '' }}" 
                   style="justify-content: space-between;"
                   title="{{ __('Branches') }}">
                    <span style="display: flex; align-items: center; gap: 0.75rem; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <i class="fa-solid fa-location-dot" style="color: #14b8a6;"></i>
                        <span>{{ __('Branches') }}</span>
                    </span>
                    @if($v && !$v->hasFeature('locations'))
                        <span class="nav-lock-badge-biz">🔒 BIZ</span>
                    @endif
                </a>
            @endcan

            @can('team.view')
                <a href="{{ route('admin.team.index') }}" 
                   class="nav-item {{ request()->routeIs('admin.team.*') ? 'active' : '' }}" 
                   style="justify-content: space-between;"
                   title="{{ __('Team & Employees') }}">
                    <span style="display: flex; align-items: center; gap: 0.75rem; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <i class="fa-solid fa-users" style="color: #0ea5e9;"></i>
                        <span>{{ __('Team & Employees') }}</span>
                    </span>
                    @if($v && !$v->hasFeature('team'))
                        <span class="nav-lock-badge-biz">🔒 BIZ</span>
                    @endif
                </a>
            @endcan

            <!-- ========================================== -->
            <!-- E. ACCOUNT & SYSTEM MANAGEMENT             -->
            <!-- ========================================== -->
            <div class="menu-category">{{ __('Account & System Management') }}</div>

            @can('settings.view')
                <a href="{{ route('admin.settings.index') }}" 
                   class="nav-item {{ (request()->routeIs('admin.settings.index') || request()->routeIs('admin.settings.reservations*')) ? 'active' : '' }}" 
                   title="{{ __('Settings') }}">
                    <i class="fa-solid fa-sliders" style="color: #94a3b8;"></i>
                    <span>{{ __('Settings') }}</span>
                </a>
            @endcan

            @can('billing.view')
                <a href="{{ route('admin.subscription') }}" class="nav-item {{ request()->routeIs('admin.subscription*') ? 'active' : '' }}" title="{{ __('Subscription & Billing') }}">
                    <i class="fa-solid fa-file-invoice-dollar" style="color: #f59e0b;"></i>
                    <span>{{ __('Subscription & Billing') }}</span>
                </a>
            @endcan

            <!-- External Live Storefront Action Button -->
            @if(Auth::user()?->vendor)
                <div style="padding: 0.85rem 0.65rem 0.25rem;">
                    <a href="{{ Auth::user()->vendor->getStorefrontUrl() }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary sidebar-storefront-btn" title="{{ __('Live Storefront') }}">
                        <i class="fa-solid fa-arrow-up-right-from-square" style="color: var(--primary);"></i>
                        <span>{{ __('Live Storefront') }}</span>
                    </a>
                </div>
            @endif
        @endif
    </nav>

    <!-- Sidebar Help & Support Desk Widget -->
    @if(Auth::user()?->isSuperAdmin())
        <div class="sidebar-widget" style="text-align: left; padding: 0.85rem 1rem; margin: 0.75rem 0.85rem 1.25rem;">
            <div style="font-size: 0.7rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: flex; align-items: center; gap: 0.4rem;">
                <i class="fa-solid fa-server" style="color: #6366f1;"></i> Core Platform
            </div>
            <div style="font-size: 0.82rem; font-weight: 700; color: var(--text-main);">Laravel 13 & Reverb</div>
            <div style="font-size: 0.72rem; color: #10b981; margin-top: 0.25rem; display: flex; align-items: center; gap: 0.35rem;">
                <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span>
                <span>WebSocket: Port 8080 Active</span>
            </div>
        </div>
    @else
        <div class="sidebar-widget">
            <div style="font-weight: 700; font-size: 0.8rem; color: var(--text-main); margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.45rem;">
                <i class="fa-solid fa-headset" style="color: var(--primary);"></i>
                <span>{{ __('Help & Support') }}</span>
            </div>
            <p>{{ __('Need help or custom menu translation?') }}</p>
            <a href="mailto:{{ \App\Models\SystemSetting::get('contact_email') ?: 'support@elab.am' }}" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.35rem 0.75rem; border-radius: 8px; width: 100%; text-decoration: none; justify-content: center;">
                {{ __('Get Support') }}
            </a>
        </div>
    @endif
</aside>
