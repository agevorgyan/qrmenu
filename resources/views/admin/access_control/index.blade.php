@extends('layouts.app')

@section('title', __('Team & Access Control') . ' - ' . $vendor->name)

@section('content')
<div x-data="accessControlApp()" x-cloak style="display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="min-width: 0; flex: 1;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span style="background: rgba(245, 158, 11, 0.15); color: var(--primary); width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-shield-halved"></i>
                </span>
                <div>
                    <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.35rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0;">
                        {{ __('Team & Access Control') }}
                    </h1>
                    <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0.25rem 0 0;">
                        {{ __('Enterprise Role-Based (RBAC), Attribute-Based (ABAC) and Location Access Management') }}
                    </p>
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <template x-if="currentTab === 'users'">
                <button type="button" @click="openInviteModal()" class="btn btn-primary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.3);">
                    <i class="fa-solid fa-user-plus"></i> {{ __('Invite Employee') }}
                </button>
            </template>
            <template x-if="currentTab === 'roles'">
                <button type="button" @click="openCreateRoleModal()" class="btn btn-primary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.3);">
                    <i class="fa-solid fa-plus-circle"></i> {{ __('Create Custom Role') }}
                </button>
            </template>
            <template x-if="currentTab === 'audit'">
                <a href="{{ route('admin.access_control.audit_logs.export', request()->query()) }}" class="btn btn-secondary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px;">
                    <i class="fa-solid fa-file-export"></i> {{ __('Export Audit Log (CSV)') }}
                </a>
            </template>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div style="display: flex; gap: 0.5rem; border-bottom: 1px solid var(--border-color); overflow-x: auto; padding-bottom: 2px;">
        <button type="button" @click="currentTab = 'users'" :style="currentTab === 'users' ? activeTabStyle : inactiveTabStyle">
            <i class="fa-solid fa-users"></i> {{ __('Team Members') }}
            <span style="font-size: 0.72rem; padding: 2px 7px; border-radius: 999px; background: rgba(255,255,255,0.08); margin-left: 0.35rem;">{{ $team->count() }}</span>
        </button>

        <button type="button" @click="currentTab = 'roles'" :style="currentTab === 'roles' ? activeTabStyle : inactiveTabStyle">
            <i class="fa-solid fa-id-badge"></i> {{ __('Roles & Templates') }}
            <span style="font-size: 0.72rem; padding: 2px 7px; border-radius: 999px; background: rgba(255,255,255,0.08); margin-left: 0.35rem;">{{ $roles->count() }}</span>
        </button>

        <button type="button" @click="currentTab = 'matrix'" :style="currentTab === 'matrix' ? activeTabStyle : inactiveTabStyle">
            <i class="fa-solid fa-table-cells"></i> {{ __('Permissions Matrix') }}
        </button>

        <button type="button" @click="currentTab = 'scopes'" :style="currentTab === 'scopes' ? activeTabStyle : inactiveTabStyle">
            <i class="fa-solid fa-location-dot"></i> {{ __('Location Scopes') }}
        </button>

        <button type="button" @click="currentTab = 'audit'" :style="currentTab === 'audit' ? activeTabStyle : inactiveTabStyle">
            <i class="fa-solid fa-clock-rotate-left"></i> {{ __('Access Audit Logs') }}
        </button>
    </div>

    <!-- TAB 1: USERS & TEAM MEMBERS -->
    <div x-show="currentTab === 'users'" x-transition style="display: flex; flex-direction: column; gap: 1rem;">
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 0; overflow: hidden; box-shadow: var(--shadow-card);">
            <div class="responsive-table-wrapper">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.88rem;">
                    <thead>
                        <tr style="background: var(--bg-body); border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
                            <th style="padding: 1rem 1.25rem;">{{ __('Employee') }}</th>
                            <th style="padding: 1rem 1.25rem;">{{ __('Status') }}</th>
                            <th style="padding: 1rem 1.25rem;">{{ __('Assigned Roles') }}</th>
                            <th style="padding: 1rem 1.25rem;">{{ __('Location Access') }}</th>
                            <th style="padding: 1rem 1.25rem;">{{ __('Workspace') }}</th>
                            <th style="padding: 1rem 1.25rem; text-align: right;">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($team as $member)
                            @php
                                $membership = $member->memberships->where('vendor_id', $vendor->id)->first();
                                $memberStatus = $membership?->status ?? ($member->is_active ? 'active' : 'inactive');
                                $memberRoles = $membership && $membership->roles->isNotEmpty() ? $membership->roles : collect([ (object)['name' => ucfirst(str_replace('_', ' ', $member->role)), 'slug' => $member->role] ]);
                                $userRoleIds = $membership && $membership->roles->isNotEmpty()
                                    ? $membership->roles->pluck('id')->all()
                                    : ($roles->firstWhere('slug', $member->role)?->id ? [$roles->firstWhere('slug', $member->role)->id] : []);
                            @endphp
                            <tr style="border-bottom: 1px solid var(--border-color); color: var(--text-main);">
                                <td style="padding: 0.9rem 1.25rem;">
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(245, 158, 11, 0.15); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.95rem; flex-shrink: 0;">
                                            {{ mb_substr($member->name, 0, 1) }}
                                        </div>
                                        <div style="min-width: 0;">
                                            <div style="font-weight: 700; color: var(--text-main);">{{ $member->name }}</div>
                                            <div style="font-size: 0.78rem; color: var(--text-muted);">{{ $member->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 0.9rem 1.25rem; white-space: nowrap;">
                                    @if($memberStatus === 'active')
                                        <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); padding: 0.2rem 0.55rem; border-radius: 8px; font-size: 0.74rem; font-weight: 700;">
                                            <i class="fa-solid fa-circle-check"></i> {{ __('Active') }}
                                        </span>
                                    @elseif($memberStatus === 'suspended')
                                        <span style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); padding: 0.2rem 0.55rem; border-radius: 8px; font-size: 0.74rem; font-weight: 700;">
                                            <i class="fa-solid fa-ban"></i> {{ __('Suspended') }}
                                        </span>
                                    @else
                                        <span style="background: rgba(148, 163, 184, 0.15); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.3); padding: 0.2rem 0.55rem; border-radius: 8px; font-size: 0.74rem; font-weight: 700;">
                                            <i class="fa-solid fa-user-xmark"></i> {{ __('Inactive') }}
                                        </span>
                                    @endif
                                </td>
                                <td style="padding: 0.9rem 1.25rem;">
                                    <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                                        @foreach($memberRoles as $r)
                                            <span style="background: rgba(245, 158, 11, 0.12); color: var(--primary); border: 1px solid rgba(245, 158, 11, 0.25); padding: 0.2rem 0.55rem; border-radius: 6px; font-size: 0.74rem; font-weight: 700; white-space: nowrap;">
                                                {{ $r->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td style="padding: 0.9rem 1.25rem; white-space: nowrap;">
                                    <button type="button" @click="editUserLocations({{ $member->id }}, {{ Js::from($member->name) }}, {{ $membership ? ($membership->all_locations ? 'true' : 'false') : 'true' }}, {{ Js::from($membership ? $membership->locations->pluck('id')->all() : []) }})" style="background: none; border: none; padding: 0; cursor: pointer; text-align: left;" title="{{ __('Configure Location Access') }}">
                                        @if($membership && ! $membership->all_locations && $membership->locations->isNotEmpty())
                                            <span style="background: var(--bg-body); border: 1px solid var(--border-color); padding: 0.25rem 0.6rem; border-radius: 6px; font-size: 0.78rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.35rem;">
                                                <i class="fa-solid fa-location-dot" style="color: var(--primary);"></i>
                                                {{ $membership->locations->pluck('name')->join(', ') }}
                                                <i class="fa-solid fa-pen" style="font-size: 0.65rem; color: var(--text-muted); margin-left: 0.2rem;"></i>
                                            </span>
                                        @else
                                            <span style="background: rgba(59, 130, 246, 0.12); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.25); padding: 0.25rem 0.6rem; border-radius: 6px; font-size: 0.78rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                                                <i class="fa-solid fa-globe"></i> {{ __('All Branches') }}
                                                <i class="fa-solid fa-pen" style="font-size: 0.65rem; color: var(--text-muted); margin-left: 0.2rem;"></i>
                                            </span>
                                        @endif
                                    </button>
                                </td>
                                <td style="padding: 0.9rem 1.25rem; white-space: nowrap;">
                                    <span style="color: var(--text-muted); font-size: 0.8rem; font-weight: 600;">
                                        {{ ucfirst($member->pos_mode ?: 'Default') }}
                                    </span>
                                </td>
                                <td style="padding: 0.9rem 1.25rem; text-align: right; white-space: nowrap;">
                                    <div style="display: inline-flex; align-items: center; gap: 0.4rem;">
                                        <button type="button" @click="inspectUser({{ $member->id }})" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.78rem; border-radius: 8px;" title="{{ __('Effective Permissions') }}">
                                            <i class="fa-solid fa-list-check"></i> {{ __('Permissions') }}
                                        </button>
                                        <button type="button" @click="editUserRoles({{ $member->id }}, {{ Js::from($member->name) }}, {{ Js::from($userRoleIds) }})" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.78rem; border-radius: 8px;" title="{{ __('Assign Roles') }}">
                                            <i class="fa-solid fa-user-gear"></i>
                                        </button>
                                        <button type="button" @click="revokeSessions({{ $member->id }})" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.78rem; border-radius: 8px; color: #ef4444;" title="{{ __('Terminate Sessions') }}">
                                            <i class="fa-solid fa-arrow-right-from-bracket"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                                    {{ __('No team members registered yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 2: ROLES & TEMPLATES -->
    <div x-show="currentTab === 'roles'" x-transition style="display: flex; flex-direction: column; gap: 1rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1rem;">
            @foreach($roles as $role)
                <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; flex-direction: column; justify-content: space-between; gap: 1rem; position: relative;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                            <div>
                                <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0;">
                                    {{ $role->name }}
                                </h3>
                                <code style="font-size: 0.75rem; color: var(--text-muted);">{{ $role->slug }}</code>
                            </div>
                            @if($role->is_system)
                                <span style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6; border: 1px solid rgba(139, 92, 246, 0.3); padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.72rem; font-weight: 700;">
                                    <i class="fa-solid fa-lock"></i> {{ __('System') }}
                                </span>
                            @else
                                <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.72rem; font-weight: 700;">
                                    {{ __('Custom') }}
                                </span>
                            @endif
                        </div>
                        <p style="font-size: 0.82rem; color: var(--text-muted); margin: 0; line-height: 1.45;">
                            {{ $role->description ?: __('Standard operational restaurant role template.') }}
                        </p>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 0.85rem; font-size: 0.8rem; color: var(--text-muted);">
                        <div>
                            <span><strong style="color: var(--text-main);">{{ $role->permissions->count() }}</strong> {{ __('permissions') }}</span> •
                            <span><strong style="color: var(--text-main);">{{ $role->memberships_count }}</strong> {{ __('users') }}</span>
                        </div>
                        <div style="display: inline-flex; gap: 0.35rem;">
                            <button type="button" @click="duplicateRole({{ $role->id }}, {{ Js::from($role->name) }})" class="btn btn-secondary" style="padding: 0.3rem 0.55rem; font-size: 0.76rem; border-radius: 6px;" title="{{ __('Duplicate Role') }}">
                                <i class="fa-solid fa-copy"></i>
                            </button>
                            @if(! $role->is_system && ! in_array($role->slug, ['owner', 'vendor_owner']))
                                <button type="button" @click="editCustomRole({{ Js::from($role) }})" class="btn btn-secondary" style="padding: 0.3rem 0.55rem; font-size: 0.76rem; border-radius: 6px;" title="{{ __('Edit Role') }}">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <button type="button" @click="deleteCustomRole({{ $role->id }}, {{ Js::from($role->name) }})" class="btn btn-secondary" style="padding: 0.3rem 0.55rem; font-size: 0.76rem; border-radius: 6px; color: #ef4444;" title="{{ __('Delete Role') }}">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- TAB 3: PERMISSIONS MATRIX -->
    <div x-show="currentTab === 'matrix'" x-transition style="display: flex; flex-direction: column; gap: 1.5rem;">
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
                <div style="flex: 1; max-width: 400px; min-width: 240px;">
                    <input type="text" x-model="matrixSearch" placeholder="{{ __('Search 21 modules & permissions...') }}" class="form-control" style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 10px; color: var(--text-main); padding: 0.55rem 0.85rem; font-size: 0.88rem; width: 100%;">
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted); display: flex; gap: 1rem;">
                    <span><i class="fa-solid fa-shield" style="color: #10b981;"></i> {{ __('Low Risk') }}</span>
                    <span><i class="fa-solid fa-shield-halved" style="color: #f59e0b;"></i> {{ __('Medium') }}</span>
                    <span><i class="fa-solid fa-triangle-exclamation" style="color: #ef4444;"></i> {{ __('High') }}</span>
                    <span><i class="fa-solid fa-skull" style="color: #dc2626;"></i> {{ __('Critical') }}</span>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 1rem;">
                @foreach($permissionCatalog as $mod => $perms)
                    <div x-data="{ expanded: true }" style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden;">
                        <button type="button" @click="expanded = !expanded" style="width: 100%; display: flex; justify-content: space-between; align-items: center; padding: 0.85rem 1.25rem; background: transparent; border: none; color: var(--text-main); cursor: pointer; text-align: left;">
                            <div style="display: flex; align-items: center; gap: 0.6rem;">
                                <i class="fa-solid fa-folder" style="color: var(--primary);"></i>
                                <span style="font-weight: 800; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.04em;">{{ ucfirst($mod) }}</span>
                                <span style="font-size: 0.75rem; background: var(--bg-card); color: var(--text-muted); padding: 2px 7px; border-radius: 999px;">{{ count($perms) }}</span>
                            </div>
                            <i class="fa-solid fa-chevron-down" :style="expanded ? 'transform: rotate(180deg)' : ''" style="transition: transform 0.2s; font-size: 0.8rem; color: var(--text-muted);"></i>
                        </button>

                        <div x-show="expanded" style="padding: 0 1.25rem 1rem; display: flex; flex-direction: column; gap: 0.5rem;">
                            @foreach($perms as $p)
                                <div x-show="matchesSearch('{{ $p['key'] }}', '{{ addslashes($p['description']) }}')" style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0.75rem; background: var(--bg-card); border-radius: 8px; border: 1px solid var(--border-color);">
                                    <div style="min-width: 0; flex: 1;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <code style="font-size: 0.8rem; font-weight: 700; color: var(--primary);">{{ $p['key'] }}</code>
                                            @if($p['is_sensitive'])
                                                <span style="background: rgba(220, 38, 38, 0.15); color: #dc2626; border: 1px solid rgba(220, 38, 38, 0.3); font-size: 0.68rem; font-weight: 800; padding: 1px 5px; border-radius: 4px;">{{ __('SENSITIVE') }}</span>
                                            @endif
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem;">{{ $p['description'] }}</div>
                                    </div>
                                    <span style="font-size: 0.72rem; font-weight: 700; padding: 2px 6px; border-radius: 4px;
                                        @if($p['risk_level'] === 'critical') background: rgba(220, 38, 38, 0.15); color: #dc2626;
                                        @elseif($p['risk_level'] === 'high') background: rgba(239, 68, 68, 0.15); color: #ef4444;
                                        @elseif($p['risk_level'] === 'medium') background: rgba(245, 158, 11, 0.15); color: #f59e0b;
                                        @else background: rgba(16, 185, 129, 0.15); color: #10b981; @endif">
                                        {{ ucfirst($p['risk_level']) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- TAB 4: ACCESS SCOPES & LOCATIONS -->
    <div x-show="currentTab === 'scopes'" x-transition style="display: flex; flex-direction: column; gap: 1rem;">
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem;">
            <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0 0 0.5rem;">
                {{ __('Branch & Location Access Isolation') }}
            </h2>
            <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0 0 1.5rem;">
                {{ __('Configure whether staff members have unrestricted access across all branches or strictly isolated access to designated locations.') }}
            </p>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
                @foreach($locations as $loc)
                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; padding: 1rem; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-main);">{{ $loc->name }}</div>
                            <div style="font-size: 0.78rem; color: var(--text-muted);">{{ $loc->address ?: __('No address specified') }}</div>
                        </div>
                        <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-size: 0.75rem; font-weight: 700; padding: 2px 8px; border-radius: 6px;">
                            {{ $loc->table_count }} {{ __('tables') }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- TAB 5: ACCESS AUDIT LOGS -->
    <div x-show="currentTab === 'audit'" x-transition style="display: flex; flex-direction: column; gap: 1rem;">
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 0; overflow: hidden; box-shadow: var(--shadow-card);">
            <div class="responsive-table-wrapper">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.84rem;">
                    <thead>
                        <tr style="background: var(--bg-body); border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
                            <th style="padding: 1rem 1.25rem;">{{ __('Timestamp') }}</th>
                            <th style="padding: 1rem 1.25rem;">{{ __('Actor') }}</th>
                            <th style="padding: 1rem 1.25rem;">{{ __('Action') }}</th>
                            <th style="padding: 1rem 1.25rem;">{{ __('Target') }}</th>
                            <th style="padding: 1rem 1.25rem;">{{ __('Details') }}</th>
                            <th style="padding: 1rem 1.25rem;">{{ __('IP Address') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($auditLogs as $log)
                            <tr style="border-bottom: 1px solid var(--border-color); color: var(--text-main);">
                                <td style="padding: 0.85rem 1.25rem; white-space: nowrap; color: var(--text-muted);">
                                    {{ $log->created_at->format('M d, Y H:i:s') }}
                                </td>
                                <td style="padding: 0.85rem 1.25rem; white-space: nowrap;">
                                    <div style="font-weight: 700; color: var(--text-main);">{{ $log->actor?->name ?? 'System' }}</div>
                                    <div style="font-size: 0.74rem; color: var(--text-muted);">{{ $log->actor?->email }}</div>
                                </td>
                                <td style="padding: 0.85rem 1.25rem; white-space: nowrap;">
                                    <code style="font-weight: 800; font-size: 0.78rem; color: var(--primary);">{{ $log->action }}</code>
                                </td>
                                <td style="padding: 0.85rem 1.25rem; white-space: nowrap;">
                                    {{ $log->targetUser?->name ?? ($log->resource_type . ' #' . $log->resource_id) }}
                                </td>
                                <td style="padding: 0.85rem 1.25rem; font-size: 0.75rem; color: var(--text-muted); max-width: 320px; word-break: break-all;">
                                    @if($log->new_values)
                                        <code>{{ json_encode($log->new_values) }}</code>
                                    @endif
                                </td>
                                <td style="padding: 0.85rem 1.25rem; font-size: 0.78rem; color: var(--text-muted); white-space: nowrap;">
                                    {{ $log->ip_address ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                                    {{ __('No access audit events recorded yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($auditLogs->hasPages())
                <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--border-color);">
                    {{ $auditLogs->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- MODAL 1: ASSIGN USER ROLES -->
    <div x-show="showUserRolesModal"
         x-cloak
         style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 110; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div @click.away="showUserRolesModal = false"
             class="card modal-box-responsive"
             style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; width: 100%; max-width: min(520px, 94vw); max-height: 90vh; overflow-y: auto; padding: 1.75rem; box-shadow: 0 25px 60px rgba(0,0,0,0.6);">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.85rem;">
                <div style="display: flex; align-items: center; gap: 0.65rem;">
                    <span style="color: var(--primary); width: 36px; height: 36px; border-radius: 10px; background: rgba(245, 158, 11, 0.15); display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                        <i class="fa-solid fa-user-gear"></i>
                    </span>
                    <div>
                        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0;">{{ __('Assign Roles') }}</h3>
                        <div style="font-size: 0.82rem; color: var(--text-muted); font-weight: 600;" x-text="roleModalUserName"></div>
                    </div>
                </div>
                <button type="button" @click="showUserRolesModal = false" style="background: none; border: none; color: var(--text-muted); font-size: 1.25rem; cursor: pointer; padding: 0.25rem;">✕</button>
            </div>

            <div style="margin-bottom: 1.5rem; display: flex; flex-direction: column; gap: 0.6rem; max-height: 340px; overflow-y: auto; padding-right: 0.25rem;">
                @foreach($roles as $r)
                    <label style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.75rem 0.85rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; cursor: pointer; transition: all 0.15s ease;">
                        <input type="checkbox" :value="{{ $r->id }}" x-model="selectedRoleIds" style="margin-top: 0.2rem; accent-color: var(--primary); width: 18px; height: 18px; cursor: pointer;">
                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; justify-content: space-between;">
                                <span style="font-weight: 700; font-size: 0.92rem; color: var(--text-main);">{{ $r->name }}</span>
                                @if($r->is_system)
                                    <span style="background: rgba(139, 92, 246, 0.15); color: #a78bfa; border: 1px solid rgba(139, 92, 246, 0.3); font-size: 0.7rem; font-weight: 700; padding: 1px 7px; border-radius: 6px;">{{ __('System') }}</span>
                                @else
                                    <span style="background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); font-size: 0.7rem; font-weight: 700; padding: 1px 7px; border-radius: 6px;">{{ __('Custom') }}</span>
                                @endif
                            </div>
                            <p style="font-size: 0.78rem; color: var(--text-muted); margin: 0.2rem 0 0; line-height: 1.4;">{{ $r->description }}</p>
                        </div>
                    </label>
                @endforeach
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; align-items: center; border-top: 1px solid var(--border-color); padding-top: 1rem;">
                <button type="button" @click="showUserRolesModal = false" class="btn btn-secondary" style="border-radius: 10px; font-weight: 600;">{{ __('Cancel') }}</button>
                <button type="button" @click="saveUserRoles()" class="btn btn-primary" :disabled="isSavingRoles" style="border-radius: 10px; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <span x-show="!isSavingRoles"><i class="fa-solid fa-check"></i> {{ __('Save Roles') }}</span>
                    <span x-show="isSavingRoles"><i class="fa-solid fa-spinner fa-spin"></i> {{ __('Saving...') }}</span>
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL 2: USER LOCATION SCOPING -->
    <div x-show="showLocationModal"
         x-cloak
         style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 110; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div @click.away="showLocationModal = false"
             class="card modal-box-responsive"
             style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; width: 100%; max-width: min(500px, 94vw); max-height: 90vh; overflow-y: auto; padding: 1.75rem; box-shadow: 0 25px 60px rgba(0,0,0,0.6);">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.85rem;">
                <div style="display: flex; align-items: center; gap: 0.65rem;">
                    <span style="color: #3b82f6; width: 36px; height: 36px; border-radius: 10px; background: rgba(59, 130, 246, 0.15); display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                        <i class="fa-solid fa-location-dot"></i>
                    </span>
                    <div>
                        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0;">{{ __('Location Access') }}</h3>
                        <div style="font-size: 0.82rem; color: var(--text-muted); font-weight: 600;" x-text="locModalUserName"></div>
                    </div>
                </div>
                <button type="button" @click="showLocationModal = false" style="background: none; border: none; color: var(--text-muted); font-size: 1.25rem; cursor: pointer; padding: 0.25rem;">✕</button>
            </div>

            <div style="margin-bottom: 1rem; padding: 0.85rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px;">
                <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer;">
                    <div>
                        <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-main);">{{ __('All Branches') }}</div>
                        <div style="font-size: 0.78rem; color: var(--text-muted);">{{ __('Employee can access orders and POS across all locations.') }}</div>
                    </div>
                    <input type="checkbox" x-model="locModalAll" style="width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer;">
                </label>
            </div>

            <div x-show="!locModalAll" x-transition style="margin-bottom: 1.5rem; display: flex; flex-direction: column; gap: 0.5rem; max-height: 240px; overflow-y: auto;">
                <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.25rem;">{{ __('Select Authorized Branches:') }}</div>
                @foreach($locations as $loc)
                    <label style="display: flex; align-items: center; gap: 0.75rem; padding: 0.65rem 0.85rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 10px; cursor: pointer;">
                        <input type="checkbox" :value="{{ $loc->id }}" x-model="locModalIds" style="width: 16px; height: 16px; accent-color: var(--primary); cursor: pointer;">
                        <div>
                            <div style="font-weight: 700; font-size: 0.88rem; color: var(--text-main);">{{ $loc->name }}</div>
                            <div style="font-size: 0.74rem; color: var(--text-muted);">{{ $loc->address ?: __('No address specified') }}</div>
                        </div>
                    </label>
                @endforeach
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; align-items: center; border-top: 1px solid var(--border-color); padding-top: 1rem;">
                <button type="button" @click="showLocationModal = false" class="btn btn-secondary" style="border-radius: 10px; font-weight: 600;">{{ __('Cancel') }}</button>
                <button type="button" @click="saveUserLocations()" class="btn btn-primary" :disabled="isSavingLoc" style="border-radius: 10px; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <span x-show="!isSavingLoc"><i class="fa-solid fa-check"></i> {{ __('Save Locations') }}</span>
                    <span x-show="isSavingLoc"><i class="fa-solid fa-spinner fa-spin"></i> {{ __('Saving...') }}</span>
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL 3: CREATE & EDIT CUSTOM ROLE -->
    <div x-show="showRoleModal"
         x-cloak
         style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 110; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div @click.away="showRoleModal = false"
             class="card modal-box-responsive"
             style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; width: 100%; max-width: min(650px, 94vw); max-height: 90vh; overflow-y: auto; padding: 1.75rem; box-shadow: 0 25px 60px rgba(0,0,0,0.6);">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.85rem;">
                <div style="display: flex; align-items: center; gap: 0.65rem;">
                    <span style="color: var(--primary); width: 36px; height: 36px; border-radius: 10px; background: rgba(245, 158, 11, 0.15); display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                        <i class="fa-solid fa-id-badge"></i>
                    </span>
                    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0;" x-text="roleFormMode === 'create' ? '{{ __('Create Custom Role') }}' : '{{ __('Edit Role') }}: ' + roleFormName"></h3>
                </div>
                <button type="button" @click="showRoleModal = false" style="background: none; border: none; color: var(--text-muted); font-size: 1.25rem; cursor: pointer; padding: 0.25rem;">✕</button>
            </div>

            <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem;">{{ __('Role Name') }} <span style="color: #ef4444;">*</span></label>
                    <input type="text" x-model="roleFormName" placeholder="e.g. Sommelier Lead" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none; font-weight: 600;">
                </div>

                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem;">{{ __('Description') }}</label>
                    <textarea x-model="roleFormDescription" rows="2" placeholder="Brief summary of duties and permissions..." style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.88rem; outline: none; resize: vertical;"></textarea>
                </div>

                <div x-show="roleFormMode === 'edit'" style="padding: 0.65rem 0.85rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 10px;">
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer;">
                        <span style="font-weight: 700; font-size: 0.88rem; color: var(--text-main);">{{ __('Active') }}</span>
                        <input type="checkbox" x-model="roleFormIsActive" style="width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer;">
                    </label>
                </div>

                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                        <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-muted);">{{ __('Permissions') }} (<span x-text="roleFormPermissions.length"></span> {{ __('selected') }})</label>
                        <input type="text" x-model="rolePermSearch" placeholder="{{ __('Search 21 modules & permissions...') }}" style="padding: 0.35rem 0.65rem; font-size: 0.75rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-main); outline: none;">
                    </div>
                    <div style="max-height: 250px; overflow-y: auto; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.75rem; display: flex; flex-direction: column; gap: 0.85rem;">
                        @foreach($permissionCatalog as $mod => $perms)
                            <div>
                                <div style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: var(--primary); margin-bottom: 0.35rem; letter-spacing: 0.05em;">{{ ucfirst($mod) }}</div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 0.4rem;">
                                    @foreach($perms as $p)
                                        <label style="display: flex; align-items: flex-start; gap: 0.5rem; padding: 0.35rem 0.5rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 6px; cursor: pointer;"
                                               x-show="!rolePermSearch || '{{ strtolower($p['key'] . ' ' . $p['description']) }}'.includes(rolePermSearch.toLowerCase())">
                                            <input type="checkbox" value="{{ $p['key'] }}" x-model="roleFormPermissions" style="margin-top: 0.15rem; width: 15px; height: 15px; accent-color: var(--primary); cursor: pointer;">
                                            <div style="min-width: 0;">
                                                <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-main);">{{ $p['action'] }}</div>
                                                <div style="font-size: 0.7rem; color: var(--text-muted); font-family: monospace;">{{ $p['key'] }}</div>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; align-items: center; border-top: 1px solid var(--border-color); padding-top: 1rem;">
                <button type="button" @click="showRoleModal = false" class="btn btn-secondary" style="border-radius: 10px; font-weight: 600;">{{ __('Cancel') }}</button>
                <button type="button" @click="saveRoleForm()" class="btn btn-primary" :disabled="isSavingRole" style="border-radius: 10px; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <span x-show="!isSavingRole"><i class="fa-solid fa-check"></i> {{ __('Save Role') }}</span>
                    <span x-show="isSavingRole"><i class="fa-solid fa-spinner fa-spin"></i> {{ __('Saving...') }}</span>
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL 4: INVITE TEAM MEMBER -->
    <div id="newMemberModal"
         x-show="showInviteModal"
         x-cloak
         style="position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 110; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div @click.away="showInviteModal = false" class="card modal-box-responsive" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; width: 100%; max-width: min(500px, 94vw); max-height: 90vh; overflow-y: auto; box-sizing: border-box; padding: clamp(1.25rem, 3vw, 1.85rem); box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.35rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.85rem;">
                <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.6rem;">
                    <span style="color: var(--primary);"><i class="fa-solid fa-user-plus"></i></span>
                    <span>{{ __('Invite Employee') }}</span>
                </h3>
                <button type="button" @click="showInviteModal = false" style="background: none; border: none; color: var(--text-muted); font-size: 1.25rem; cursor: pointer; padding: 0.25rem;">✕</button>
            </div>

            <form action="{{ route('admin.team.store') }}" method="POST">
                @csrf
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Full Name') }} <span style="color: #ef4444;">*</span></label>
                    <input type="text" name="name" required placeholder="e.g. John Doe" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none; font-weight: 600;">
                </div>

                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Email Address') }} <span style="color: #ef4444;">*</span></label>
                    <input type="email" name="email" required placeholder="john@example.com" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none; font-weight: 600;">
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr)); gap: 0.85rem; margin-bottom: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Role') }} <span style="color: #ef4444;">*</span></label>
                        <select name="role" required style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none; font-weight: 600;">
                            <option value="waiter">{{ __('Waiter') }}</option>
                            <option value="cashier">{{ __('Cashier') }}</option>
                            <option value="chef">{{ __('Chef') }}</option>
                            <option value="manager">{{ __('Branch Manager') }}</option>
                            <option value="staff">{{ __('Staff Member') }}</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Branch') }}</label>
                        <select name="location_id" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none; font-weight: 600;">
                            <option value="">{{ __('All Branches') }}</option>
                            @foreach($locations as $l)
                                <option value="{{ $l->id }}">{{ $l->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Temporary Password') }} <span style="color: #ef4444;">*</span></label>
                    <input type="password" name="password" required value="password" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none; font-weight: 600;">
                    <span style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                        {{ __('Members can change their password after signing in.') }}
                    </span>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary" @click="showInviteModal = false" style="border-radius: 12px; font-weight: 600;">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 12px; font-weight: 700; padding: 0.7rem 1.6rem;">{{ __('Invite Member') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 5: EFFECTIVE PERMISSIONS INSPECTOR -->
    <div x-show="showPermissionsModal" style="position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 1rem;" x-cloak>
        <div class="card" @click.outside="showPermissionsModal = false" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; width: 100%; max-width: 640px; max-height: 85vh; overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 0.85rem;">
                <div>
                    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0;" x-text="inspectedUser.name"></h3>
                    <div style="font-size: 0.8rem; color: var(--text-muted);" x-text="inspectedUser.email"></div>
                </div>
                <button type="button" @click="showPermissionsModal = false" style="background: none; border: none; color: var(--text-muted); font-size: 1.2rem; cursor: pointer;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                <h4 style="font-size: 0.88rem; font-weight: 800; color: var(--text-main); margin: 0;">{{ __('Active Assigned Roles') }}</h4>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <template x-for="role in (inspectedUser.roles || [])" :key="role">
                        <span style="background: rgba(245, 158, 11, 0.15); color: var(--primary); font-size: 0.8rem; font-weight: 700; padding: 3px 10px; border-radius: 8px;" x-text="role"></span>
                    </template>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h4 style="font-size: 0.88rem; font-weight: 800; color: var(--text-main); margin: 0;">{{ __('Effective Permissions') }}</h4>
                    <span style="font-size: 0.78rem; color: var(--text-muted);" x-text="`${(inspectedEffectivePerms || []).length} abilities granted`"></span>
                </div>
                <div style="max-height: 240px; overflow-y: auto; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 10px; padding: 0.75rem; display: flex; flex-wrap: wrap; gap: 0.35rem;">
                    <template x-for="perm in inspectedEffectivePerms" :key="perm">
                        <code style="font-size: 0.75rem; color: var(--text-main); background: var(--bg-card); border: 1px solid var(--border-color); padding: 2px 6px; border-radius: 4px;" x-text="perm"></code>
                    </template>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end;">
                <button type="button" @click="showPermissionsModal = false" class="btn btn-secondary" style="border-radius: 10px;">{{ __('Close') }}</button>
            </div>
        </div>
    </div>
</div>

<script>
function accessControlApp() {
    return {
        currentTab: '{{ $tab }}',
        matrixSearch: '',
        
        // Modals state
        showPermissionsModal: false,
        showUserRolesModal: false,
        showLocationModal: false,
        showRoleModal: false,
        showInviteModal: false,

        // Permissions Inspector state
        inspectedUser: {},
        inspectedEffectivePerms: [],

        // User Roles Modal state
        roleModalUserId: null,
        roleModalUserName: '',
        selectedRoleIds: [],
        isSavingRoles: false,

        // Location Modal state
        locModalUserId: null,
        locModalUserName: '',
        locModalAll: true,
        locModalIds: [],
        isSavingLoc: false,

        // Role Form state
        roleFormMode: 'create', // 'create' or 'edit'
        roleFormId: null,
        roleFormName: '',
        roleFormDescription: '',
        roleFormIsActive: true,
        roleFormPermissions: [],
        rolePermSearch: '',
        isSavingRole: false,

        activeTabStyle: 'background: var(--primary); color: #000; font-weight: 800; font-size: 0.88rem; border-radius: 10px; border: none; padding: 0.55rem 1rem; display: inline-flex; align-items: center; cursor: pointer; white-space: nowrap;',
        inactiveTabStyle: 'background: transparent; color: var(--text-muted); font-weight: 600; font-size: 0.88rem; border-radius: 10px; border: none; padding: 0.55rem 1rem; display: inline-flex; align-items: center; cursor: pointer; white-space: nowrap;',

        matchesSearch(key, desc) {
            if (!this.matrixSearch) return true;
            const q = this.matrixSearch.toLowerCase();
            return key.toLowerCase().includes(q) || desc.toLowerCase().includes(q);
        },

        editUserRoles(userId, userName, currentRoleIds) {
            this.roleModalUserId = userId;
            this.roleModalUserName = userName;
            this.selectedRoleIds = (currentRoleIds || []).map(id => Number(id));
            this.showUserRolesModal = true;
        },

        async saveUserRoles() {
            if (!this.roleModalUserId) return;
            this.isSavingRoles = true;

            try {
                const res = await fetch(`{{ url('admin/access-control/users') }}/${this.roleModalUserId}/roles`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        role_ids: this.selectedRoleIds.map(id => Number(id))
                    })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    let errMsg = data.message || 'Error updating roles';
                    if (data.errors) {
                        errMsg = Object.values(data.errors).flat().join('\n');
                    }
                    alert(errMsg);
                }
            } catch (e) {
                console.error('Role update error', e);
                alert('Failed to update roles.');
            } finally {
                this.isSavingRoles = false;
            }
        },

        editUserLocations(userId, userName, allLocations, assignedLocationIds) {
            this.locModalUserId = userId;
            this.locModalUserName = userName;
            this.locModalAll = !!allLocations;
            this.locModalIds = (assignedLocationIds || []).map(id => Number(id));
            this.showLocationModal = true;
        },

        async saveUserLocations() {
            if (!this.locModalUserId) return;
            this.isSavingLoc = true;

            try {
                const res = await fetch(`{{ url('admin/access-control/users') }}/${this.locModalUserId}/locations`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        all_locations: this.locModalAll ? 1 : 0,
                        location_ids: this.locModalAll ? [] : this.locModalIds.map(id => Number(id))
                    })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    let errMsg = data.message || 'Error updating locations';
                    if (data.errors) {
                        errMsg = Object.values(data.errors).flat().join('\n');
                    }
                    alert(errMsg);
                }
            } catch (e) {
                console.error('Location update error', e);
                alert('Failed to update locations.');
            } finally {
                this.isSavingLoc = false;
            }
        },

        openCreateRoleModal() {
            this.roleFormMode = 'create';
            this.roleFormId = null;
            this.roleFormName = '';
            this.roleFormDescription = '';
            this.roleFormIsActive = true;
            this.roleFormPermissions = [];
            this.rolePermSearch = '';
            this.showRoleModal = true;
        },

        editCustomRole(role) {
            this.roleFormMode = 'edit';
            this.roleFormId = role.id;
            this.roleFormName = role.name;
            this.roleFormDescription = role.description || '';
            this.roleFormIsActive = !!role.is_active;
            this.roleFormPermissions = (role.permissions || []).map(p => p.key);
            this.rolePermSearch = '';
            this.showRoleModal = true;
        },

        async saveRoleForm() {
            if (!this.roleFormName.trim()) {
                alert('{{ __('Role Name') }} is required');
                return;
            }
            this.isSavingRole = true;

            const url = this.roleFormMode === 'create'
                ? '{{ route('admin.access_control.roles.store') }}'
                : `{{ url('admin/access-control/roles') }}/${this.roleFormId}`;
            const method = this.roleFormMode === 'create' ? 'POST' : 'PUT';

            try {
                const res = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        name: this.roleFormName,
                        description: this.roleFormDescription,
                        is_active: this.roleFormIsActive ? 1 : 0,
                        permissions: this.roleFormPermissions
                    })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    let errMsg = data.message || 'Failed to save role';
                    if (data.errors) {
                        errMsg = Object.values(data.errors).flat().join('\n');
                    }
                    alert(errMsg);
                }
            } catch (e) {
                console.error('Role form save error', e);
                alert('Failed to save role.');
            } finally {
                this.isSavingRole = false;
            }
        },

        openInviteModal() {
            this.showInviteModal = true;
            const modal = document.getElementById('newMemberModal');
            if (modal) modal.style.display = 'flex';
        },

        async inspectUser(userId) {
            try {
                const res = await fetch(`{{ url('admin/access-control/users') }}/${userId}/effective-permissions`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.inspectedUser = data.user;
                    this.inspectedEffectivePerms = data.effective_permissions;
                    this.showPermissionsModal = true;
                }
            } catch (e) {
                console.error('Inspection failed', e);
            }
        },

        async revokeSessions(userId) {
            if (!confirm('{{ __('Are you sure you want to terminate all active sessions for this employee?') }}')) return;

            try {
                const res = await fetch(`{{ url('admin/access-control/users') }}/${userId}/revoke-sessions`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                if (data.success) {
                    alert(data.message);
                }
            } catch (e) {
                console.error('Session revoke error', e);
            }
        },

        async duplicateRole(roleId, roleName) {
            const newName = prompt('{{ __('Enter duplicated role name:') }}', `${roleName} (Copy)`);
            if (!newName) return;

            const res = await fetch(`{{ url('admin/access-control/roles') }}/${roleId}/duplicate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ name: newName })
            });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.message || 'Duplication error');
            }
        },

        async deleteCustomRole(roleId, roleName) {
            if (!confirm(`{{ __('Delete custom role') }} "${roleName}"?`)) return;

            const res = await fetch(`{{ url('admin/access-control/roles') }}/${roleId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.message || 'Deletion error');
            }
        }
    };
}
</script>
@endsection
