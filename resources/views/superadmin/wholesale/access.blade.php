@extends('layouts.app')

@section('title', __('Wholesale POS Access Management') . ' - SuperAdmin')

@section('content')
<div class="content-wrapper" x-data="{
    grantModalOpen: false,
    logsModalOpen: false,
    selectedVendor: null,
    modalVendorName: '',
    modalActionUrl: '',
    modalExpiresAt: '',
    modalReason: '',
    logsVendorName: '',
    logsLoading: false,
    logsData: [],
    openGrantModal(vendorId, vendorName, currentExpiresAt, currentReason) {
        this.selectedVendor = vendorId;
        this.modalVendorName = vendorName;
        this.modalActionUrl = '{{ url('superadmin/wholesale/tenants') }}/' + vendorId + '/grant';
        this.modalExpiresAt = currentExpiresAt || '';
        this.modalReason = currentReason || '';
        this.grantModalOpen = true;
    },
    async openLogsModal(vendorId, vendorName) {
        this.logsVendorName = vendorName;
        this.logsModalOpen = true;
        this.logsLoading = true;
        this.logsData = [];
        try {
            const resp = await fetch('{{ url('superadmin/wholesale/tenants') }}/' + vendorId + '/logs', {
                headers: { 'Accept': 'application/json' }
            });
            const json = await resp.json();
            this.logsData = json.logs || [];
        } catch (e) {
            console.error(e);
        } finally {
            this.logsLoading = false;
        }
    }
}">

    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 12px; background: rgba(99, 102, 241, 0.15); color: #6366f1; font-size: 1.25rem;">
                    <i class="fa-solid fa-boxes-packing"></i>
                </span>
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    {{ __('B2B Wholesale POS — Tenant Access Management') }}
                </h1>
            </div>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0; max-width: 800px;">
                {{ __('Wholesale POS is a Super Admin-controlled, restricted feature. It is disabled by default for all tenants and can only be enabled by authorized platform administrators.') }}
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem; align-items: center;">
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.6rem 1.2rem; display: flex; align-items: center; gap: 0.6rem;">
                <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">{{ __('Active Entitlements') }}:</span>
                <span style="font-size: 1.15rem; font-weight: 800; color: #10b981;">{{ $stats['active_wholesale'] }}</span>
                <span style="color: var(--text-muted);">/ {{ $stats['total_tenants'] }}</span>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
        <div style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 12px; padding: 1rem 1.25rem; color: #10b981; font-weight: 600; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('warning'))
        <div style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 12px; padding: 1rem 1.25rem; color: #f59e0b; font-weight: 600; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span>{{ session('warning') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 12px; padding: 1rem 1.25rem; color: #ef4444; font-weight: 600; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-circle-xmark"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Search & Filter Panel -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; margin-bottom: 1.5rem;">
        <form method="GET" action="{{ route('superadmin.wholesale.tenants.index') }}" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 250px; position: relative;">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('Search restaurant by name, legal name, TIN...') }}"
                       style="width: 100%; padding: 0.65rem 1rem 0.65rem 2.5rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
            </div>

            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <a href="{{ route('superadmin.wholesale.tenants.index', ['search' => $search]) }}" 
                   style="padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.82rem; font-weight: 600; text-decoration: none; border: 1px solid var(--border-color); {{ empty($status) ? 'background: #6366f1; color: #fff; border-color: #6366f1;' : 'background: var(--bg-main); color: var(--text-muted);' }}">
                    {{ __('All Tenants') }}
                </a>
                <a href="{{ route('superadmin.wholesale.tenants.index', ['search' => $search, 'status' => 'active']) }}" 
                   style="padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.82rem; font-weight: 600; text-decoration: none; border: 1px solid var(--border-color); {{ $status === 'active' ? 'background: #10b981; color: #fff; border-color: #10b981;' : 'background: var(--bg-main); color: var(--text-muted);' }}">
                    {{ __('Active Entitlement') }}
                </a>
                <a href="{{ route('superadmin.wholesale.tenants.index', ['search' => $search, 'status' => 'disabled']) }}" 
                   style="padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.82rem; font-weight: 600; text-decoration: none; border: 1px solid var(--border-color); {{ $status === 'disabled' ? 'background: #ef4444; color: #fff; border-color: #ef4444;' : 'background: var(--bg-main); color: var(--text-muted);' }}">
                    {{ __('Disabled / Expired') }}
                </a>
            </div>

            <button type="submit" style="padding: 0.65rem 1.25rem; border-radius: 10px; background: #6366f1; color: #fff; font-weight: 700; border: none; cursor: pointer;">
                {{ __('Filter') }}
            </button>
        </form>
    </div>

    <!-- Tenants Table -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.88rem;">
            <thead>
                <tr style="background: var(--bg-main); border-bottom: 1px solid var(--border-color); color: var(--text-muted); text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.05em;">
                    <th style="padding: 1rem 1.25rem;">{{ __('Restaurant') }}</th>
                    <th style="padding: 1rem 1rem;">{{ __('Wholesale POS Access') }}</th>
                    <th style="padding: 1rem 1rem;">{{ __('Expiration Date') }}</th>
                    <th style="padding: 1rem 1rem;">{{ __('Granted By & Date') }}</th>
                    <th style="padding: 1rem 1rem;">{{ __('Internal Note / Reason') }}</th>
                    <th style="padding: 1rem 1.25rem; text-align: right;">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tenants as $vendor)
                    @php
                        $access = $vendor->moduleAccess->firstWhere('module_key', 'wholesale_pos');
                        $isEntitled = $access && $access->isValid();
                        $isExpired = $access && $access->expires_at && now()->greaterThanOrEqualTo($access->expires_at);
                        $isRevoked = $access && $access->revoked_at !== null;
                    @endphp
                    <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.15s ease;">
                        <td style="padding: 1rem 1.25rem;">
                            <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">{{ $vendor->name }}</div>
                            <div style="font-size: 0.78rem; color: var(--text-muted);">
                                {{ $vendor->legal_name ?: $vendor->slug }} • {{ $vendor->tax_id ? __('TIN').': '.$vendor->tax_id : __('No TIN') }}
                            </div>
                        </td>

                        <td style="padding: 1rem 1rem;">
                            @if($isEntitled)
                                <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.3rem 0.75rem; border-radius: 9999px; background: rgba(16, 185, 129, 0.12); color: #10b981; font-weight: 700; font-size: 0.75rem; border: 1px solid rgba(16, 185, 129, 0.3);">
                                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span>
                                    {{ __('ACTIVE') }}
                                </span>
                            @elseif($isRevoked)
                                <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.3rem 0.75rem; border-radius: 9999px; background: rgba(239, 68, 68, 0.12); color: #ef4444; font-weight: 700; font-size: 0.75rem; border: 1px solid rgba(239, 68, 68, 0.3);">
                                    {{ __('REVOKED') }}
                                </span>
                            @elseif($isExpired)
                                <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.3rem 0.75rem; border-radius: 9999px; background: rgba(245, 158, 11, 0.12); color: #f59e0b; font-weight: 700; font-size: 0.75rem; border: 1px solid rgba(245, 158, 11, 0.3);">
                                    {{ __('EXPIRED') }}
                                </span>
                            @elseif($access && !$access->is_enabled)
                                <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.3rem 0.75rem; border-radius: 9999px; background: rgba(245, 158, 11, 0.12); color: #f59e0b; font-weight: 700; font-size: 0.75rem; border: 1px solid rgba(245, 158, 11, 0.3);">
                                    {{ __('SUSPENDED') }}
                                </span>
                            @else
                                <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.3rem 0.75rem; border-radius: 9999px; background: rgba(148, 163, 184, 0.12); color: var(--text-muted); font-weight: 600; font-size: 0.75rem; border: 1px solid rgba(148, 163, 184, 0.3);">
                                    {{ __('NOT GRANTED') }}
                                </span>
                            @endif
                        </td>

                        <td style="padding: 1rem 1rem; color: var(--text-main);">
                            @if($access && $access->expires_at)
                                <div style="font-weight: 600; {{ $isExpired ? 'color: #ef4444;' : '' }}">
                                    {{ $access->expires_at->format('Y-m-d') }}
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                    {{ $access->expires_at->diffForHumans() }}
                                </div>
                            @else
                                <span style="color: var(--text-muted);">{{ __('No Expiration (Indefinite)') }}</span>
                            @endif
                        </td>

                        <td style="padding: 1rem 1rem;">
                            @if($access && $access->enabled_at)
                                <div style="font-weight: 600; color: var(--text-main);">
                                    {{ $access->grantedByUser?->name ?? __('System') }}
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                    {{ $access->enabled_at->format('Y-m-d H:i') }}
                                </div>
                            @else
                                <span style="color: var(--text-muted);">&mdash;</span>
                            @endif
                        </td>

                        <td style="padding: 1rem 1rem; max-width: 200px;">
                            <div style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $access?->reason }}">
                                {{ $access?->reason ?? '&mdash;' }}
                            </div>
                        </td>

                        <td style="padding: 1rem 1.25rem; text-align: right;">
                            <div style="display: inline-flex; gap: 0.4rem; align-items: center;">
                                <!-- Grant / Configure Button -->
                                <button type="button" 
                                        @click="openGrantModal({{ $vendor->id }}, '{{ addslashes($vendor->name) }}', '{{ $access?->expires_at?->format('Y-m-d') }}', '{{ addslashes($access?->reason ?? '') }}')"
                                        style="padding: 0.4rem 0.75rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; background: #6366f1; color: #fff; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem;"
                                        title="{{ $isEntitled ? __('Edit / Renew Access') : __('Grant Access') }}">
                                    <i class="fa-solid {{ $isEntitled ? 'fa-pen-to-square' : 'fa-check' }}"></i>
                                    <span>{{ $isEntitled ? __('Configure') : __('Grant') }}</span>
                                </button>

                                @if($isEntitled)
                                    <!-- Suspend Form -->
                                    <form method="POST" action="{{ route('superadmin.wholesale.tenants.suspend', $vendor) }}" onsubmit="return confirm('{{ __('Are you sure you want to suspend Wholesale POS access for :name?', ['name' => $vendor->name]) }}');" style="display: inline;">
                                        @csrf
                                        <button type="submit" 
                                                style="padding: 0.4rem 0.65rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); cursor: pointer;"
                                                title="{{ __('Temporarily Suspend Access') }}">
                                            <i class="fa-solid fa-pause"></i>
                                        </button>
                                    </form>

                                    <!-- Revoke Form -->
                                    <form method="POST" action="{{ route('superadmin.wholesale.tenants.revoke', $vendor) }}" onsubmit="return confirm('{{ __('Are you sure you want to permanently revoke Wholesale POS access for :name?', ['name' => $vendor->name]) }}');" style="display: inline;">
                                        @csrf
                                        <button type="submit" 
                                                style="padding: 0.4rem 0.65rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); cursor: pointer;"
                                                title="{{ __('Permanently Revoke Access') }}">
                                            <i class="fa-solid fa-ban"></i>
                                        </button>
                                    </form>
                                @endif

                                <!-- Audit Logs Button -->
                                <button type="button" 
                                        @click="openLogsModal({{ $vendor->id }}, '{{ addslashes($vendor->name) }}')"
                                        style="padding: 0.4rem 0.65rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; background: var(--bg-main); color: var(--text-muted); border: 1px solid var(--border-color); cursor: pointer;"
                                        title="{{ __('View Audit Logs') }}">
                                    <i class="fa-solid fa-clock-rotate-left"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                            <i class="fa-solid fa-store" style="font-size: 2rem; margin-bottom: 0.75rem; opacity: 0.5;"></i>
                            <div>{{ __('No restaurants found matching the filter.') }}</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Pagination -->
        @if($tenants->hasPages())
            <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--border-color); background: var(--bg-main);">
                {{ $tenants->links() }}
            </div>
        @endif
    </div>

    <!-- Grant / Configure Modal -->
    <div x-show="grantModalOpen" 
         x-transition.opacity 
         style="position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(4px); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 1.5rem;"
         @keydown.escape.window="grantModalOpen = false"
         style="display: none;">
        
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; width: 100%; max-width: 520px; box-shadow: 0 20px 50px rgba(0,0,0,0.4); overflow: hidden;"
             @click.away="grantModalOpen = false">
            
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-main);">
                        {{ __('Grant Wholesale POS Access') }}
                    </h3>
                    <div style="font-size: 0.85rem; color: #6366f1; font-weight: 600; margin-top: 0.2rem;" x-text="modalVendorName"></div>
                </div>
                <button type="button" @click="grantModalOpen = false" style="background: none; border: none; color: var(--text-muted); font-size: 1.2rem; cursor: pointer;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form :action="modalActionUrl" method="POST" style="padding: 1.5rem;">
                @csrf
                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                        {{ __('Expiration Date (Optional)') }}
                    </label>
                    <input type="date" name="expires_at" x-model="modalExpiresAt"
                           style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
                    <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; display: block;">
                        {{ __('Leave empty for permanent, non-expiring access.') }}
                    </span>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                        {{ __('Reason / Administrative Note') }}
                    </label>
                    <textarea name="reason" rows="3" x-model="modalReason" placeholder="{{ __('e.g. Enterprise Wholesale Distribution Tier agreement signed.') }}"
                              style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem; resize: vertical;"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" @click="grantModalOpen = false" 
                            style="padding: 0.65rem 1.25rem; border-radius: 10px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-weight: 600; cursor: pointer;">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit" 
                            style="padding: 0.65rem 1.5rem; border-radius: 10px; background: #6366f1; border: none; color: #fff; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>{{ __('Authorize & Enable Access') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Audit Logs Modal -->
    <div x-show="logsModalOpen" 
         x-transition.opacity 
         style="position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(4px); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 1.5rem;"
         @keydown.escape.window="logsModalOpen = false"
         style="display: none;">
        
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; width: 100%; max-width: 650px; max-height: 85vh; display: flex; flex-direction: column; box-shadow: 0 20px 50px rgba(0,0,0,0.4); overflow: hidden;"
             @click.away="logsModalOpen = false">
            
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
                <div>
                    <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-main);">
                        {{ __('Wholesale Access Audit History') }}
                    </h3>
                    <div style="font-size: 0.85rem; color: #6366f1; font-weight: 600; margin-top: 0.2rem;" x-text="logsVendorName"></div>
                </div>
                <button type="button" @click="logsModalOpen = false" style="background: none; border: none; color: var(--text-muted); font-size: 1.2rem; cursor: pointer;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div style="padding: 1.5rem; overflow-y: auto; flex: 1;">
                <template x-if="logsLoading">
                    <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                        <i class="fa-solid fa-circle-notch fa-spin" style="font-size: 1.8rem;"></i>
                        <div style="margin-top: 0.75rem;">{{ __('Loading audit trail...') }}</div>
                    </div>
                </template>

                <template x-if="!logsLoading && logsData.length === 0">
                    <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                        <i class="fa-solid fa-file-shield" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                        <div>{{ __('No access state changes recorded yet for this restaurant.') }}</div>
                    </div>
                </template>

                <template x-if="!logsLoading && logsData.length > 0">
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <template x-for="log in logsData" :key="log.id">
                            <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 12px; padding: 1rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                                    <span style="font-weight: 800; font-size: 0.82rem; text-transform: uppercase;"
                                          :style="log.action === 'granted' ? 'color: #10b981;' : (log.action === 'revoked' ? 'color: #ef4444;' : 'color: #f59e0b;')"
                                          x-text="log.action"></span>
                                    <span style="font-size: 0.75rem; color: var(--text-muted);" x-text="new Date(log.created_at).toLocaleString()"></span>
                                </div>
                                <div style="font-size: 0.85rem; color: var(--text-main); margin-bottom: 0.25rem;">
                                    <strong>{{ __('Actor') }}:</strong> <span x-text="log.acted_by_user ? log.acted_by_user.name : '{{ __('System') }}'"></span>
                                </div>
                                <div style="font-size: 0.82rem; color: var(--text-muted);" x-show="log.reason">
                                    <strong>{{ __('Reason') }}:</strong> <span x-text="log.reason"></span>
                                </div>
                                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.4rem;">
                                    {{ __('IP Address:') }} <span x-text="log.ip_address || '{{ __('N/A') }}'"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); background: var(--bg-main); display: flex; justify-content: flex-end; flex-shrink: 0;">
                <button type="button" @click="logsModalOpen = false" 
                        style="padding: 0.5rem 1.25rem; border-radius: 8px; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); font-weight: 600; cursor: pointer;">
                    {{ __('Close') }}
                </button>
            </div>
        </div>
    </div>

</div>
@endsection

