<div class="superadmin-hero" style="margin-bottom: 1.5rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.4rem;">
            <span style="background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%); color: #fff; padding: 0.2rem 0.65rem; border-radius: 9999px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">
                <i class="fa-solid fa-envelope-shield"></i> {{ __('Email Infrastructure') }}
            </span>
            <span style="font-size: 0.75rem; color: #10b981; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                <span style="width: 7px; height: 7px; border-radius: 50%; background: #10b981; box-shadow: 0 0 8px #10b981;"></span>
                {{ __('Production Transport Active') }}
            </span>
        </div>
        <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.85rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.35rem;">
            {{ $title ?? __('Email Management & Deliverability HQ') }}
        </h1>
        <p style="color: var(--text-muted); font-size: 0.88rem; max-width: 650px;">
            {{ $subtitle ?? __('Centralized configuration, sender identities, dynamic routing, delivery logs, queue monitoring, and reporting.') }}
        </p>
    </div>

    <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
        <a href="{{ route('superadmin.emails.overview') }}" class="btn btn-secondary" style="border-radius: 12px; font-size: 0.82rem; padding: 0.55rem 1rem;">
            <i class="fa-solid fa-arrows-rotate"></i> {{ __('Refresh') }}
        </a>
        <a href="{{ route('superadmin.emails.connections.index') }}" class="btn btn-primary" style="border-radius: 12px; font-size: 0.82rem; padding: 0.55rem 1rem;">
            <i class="fa-solid fa-plug"></i> {{ __('SMTP Connections') }}
        </a>
    </div>
</div>

<!-- Sticky / Horizontal Scrollable Sub-Navigation Tabs -->
<div style="display: flex; gap: 0.4rem; overflow-x: auto; padding-bottom: 0.5rem; margin-bottom: 1.75rem; border-bottom: 1px solid var(--border-color); -webkit-overflow-scrolling: touch;">
    <a href="{{ route('superadmin.emails.overview') }}" 
       style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.6rem 1rem; border-radius: 12px; font-size: 0.83rem; font-weight: 700; text-decoration: none; white-space: nowrap; transition: all 0.15s ease; {{ request()->routeIs('superadmin.emails.overview') ? 'background: var(--primary); color: #ffffff; box-shadow: 0 4px 12px var(--primary-glow);' : 'background: var(--input-bg); color: var(--text-muted); border: 1px solid var(--border-color);' }}">
        <i class="fa-solid fa-gauge-high"></i> {{ __('Overview') }}
    </a>
    <a href="{{ route('superadmin.emails.senders.index') }}" 
       style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.6rem 1rem; border-radius: 12px; font-size: 0.83rem; font-weight: 700; text-decoration: none; white-space: nowrap; transition: all 0.15s ease; {{ request()->routeIs('superadmin.emails.senders.*') ? 'background: var(--primary); color: #ffffff; box-shadow: 0 4px 12px var(--primary-glow);' : 'background: var(--input-bg); color: var(--text-muted); border: 1px solid var(--border-color);' }}">
        <i class="fa-solid fa-id-card-clip"></i> {{ __('Sender Identities') }}
    </a>
    <a href="{{ route('superadmin.emails.connections.index') }}" 
       style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.6rem 1rem; border-radius: 12px; font-size: 0.83rem; font-weight: 700; text-decoration: none; white-space: nowrap; transition: all 0.15s ease; {{ request()->routeIs('superadmin.emails.connections.*') ? 'background: var(--primary); color: #ffffff; box-shadow: 0 4px 12px var(--primary-glow);' : 'background: var(--input-bg); color: var(--text-muted); border: 1px solid var(--border-color);' }}">
        <i class="fa-solid fa-server"></i> {{ __('SMTP Connections') }}
    </a>
    <a href="{{ route('superadmin.emails.routing.index') }}" 
       style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.6rem 1rem; border-radius: 12px; font-size: 0.83rem; font-weight: 700; text-decoration: none; white-space: nowrap; transition: all 0.15s ease; {{ request()->routeIs('superadmin.emails.routing.*') ? 'background: var(--primary); color: #ffffff; box-shadow: 0 4px 12px var(--primary-glow);' : 'background: var(--input-bg); color: var(--text-muted); border: 1px solid var(--border-color);' }}">
        <i class="fa-solid fa-route"></i> {{ __('Routing Rules') }}
    </a>
    <a href="{{ route('superadmin.emails.templates.index') }}" 
       style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.6rem 1rem; border-radius: 12px; font-size: 0.83rem; font-weight: 700; text-decoration: none; white-space: nowrap; transition: all 0.15s ease; {{ request()->routeIs('superadmin.emails.templates.*') ? 'background: var(--primary); color: #ffffff; box-shadow: 0 4px 12px var(--primary-glow);' : 'background: var(--input-bg); color: var(--text-muted); border: 1px solid var(--border-color);' }}">
        <i class="fa-solid fa-envelope-open-text"></i> {{ __('Templates') }}
    </a>
    <a href="{{ route('superadmin.emails.logs.index') }}" 
       style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.6rem 1rem; border-radius: 12px; font-size: 0.83rem; font-weight: 700; text-decoration: none; white-space: nowrap; transition: all 0.15s ease; {{ request()->routeIs('superadmin.emails.logs.*') ? 'background: var(--primary); color: #ffffff; box-shadow: 0 4px 12px var(--primary-glow);' : 'background: var(--input-bg); color: var(--text-muted); border: 1px solid var(--border-color);' }}">
        <i class="fa-solid fa-list-check"></i> {{ __('Logs & Audits') }}
    </a>
    <a href="{{ route('superadmin.emails.queue.index') }}" 
       style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.6rem 1rem; border-radius: 12px; font-size: 0.83rem; font-weight: 700; text-decoration: none; white-space: nowrap; transition: all 0.15s ease; {{ request()->routeIs('superadmin.emails.queue.*') ? 'background: var(--primary); color: #ffffff; box-shadow: 0 4px 12px var(--primary-glow);' : 'background: var(--input-bg); color: var(--text-muted); border: 1px solid var(--border-color);' }}">
        <i class="fa-solid fa-clock-rotate-left"></i> {{ __('Queue Monitor') }}
    </a>
    <a href="{{ route('superadmin.emails.failed.index') }}" 
       style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.6rem 1rem; border-radius: 12px; font-size: 0.83rem; font-weight: 700; text-decoration: none; white-space: nowrap; transition: all 0.15s ease; {{ request()->routeIs('superadmin.emails.failed.*') ? 'background: var(--primary); color: #ffffff; box-shadow: 0 4px 12px var(--primary-glow);' : 'background: var(--input-bg); color: var(--text-muted); border: 1px solid var(--border-color);' }}">
        <i class="fa-solid fa-triangle-exclamation"></i> {{ __('Failed Emails') }}
    </a>
    <a href="{{ route('superadmin.emails.reports.index') }}" 
       style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.6rem 1rem; border-radius: 12px; font-size: 0.83rem; font-weight: 700; text-decoration: none; white-space: nowrap; transition: all 0.15s ease; {{ request()->routeIs('superadmin.emails.reports.*') ? 'background: var(--primary); color: #ffffff; box-shadow: 0 4px 12px var(--primary-glow);' : 'background: var(--input-bg); color: var(--text-muted); border: 1px solid var(--border-color);' }}">
        <i class="fa-solid fa-chart-pie"></i> {{ __('Reports') }}
    </a>
    <a href="{{ route('superadmin.emails.settings.index') }}" 
       style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.6rem 1rem; border-radius: 12px; font-size: 0.83rem; font-weight: 700; text-decoration: none; white-space: nowrap; transition: all 0.15s ease; {{ request()->routeIs('superadmin.emails.settings.*') ? 'background: var(--primary); color: #ffffff; box-shadow: 0 4px 12px var(--primary-glow);' : 'background: var(--input-bg); color: var(--text-muted); border: 1px solid var(--border-color);' }}">
        <i class="fa-solid fa-gear"></i> {{ __('Settings') }}
    </a>
</div>
