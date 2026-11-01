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
                                    @if($membership && ! $membership->all_locations && $membership->locations->isNotEmpty())
                                        <span style="background: var(--bg-body); border: 1px solid var(--border-color); padding: 0.25rem 0.6rem; border-radius: 6px; font-size: 0.78rem; font-weight: 600;">
                                            <i class="fa-solid fa-location-dot" style="color: var(--primary);"></i>
                                            {{ $membership->locations->pluck('name')->join(', ') }}
                                        </span>
                                    @else
                                        <span style="background: rgba(59, 130, 246, 0.12); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.25); padding: 0.25rem 0.6rem; border-radius: 6px; font-size: 0.78rem; font-weight: 700;">
                                            <i class="fa-solid fa-globe"></i> {{ __('All Branches') }}
                                        </span>
                                    @endif
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
                                        <button type="button" @click="editUserRoles({{ $member->id }}, '{{ addslashes($member->name) }}', {{ json_encode($memberRoles->pluck('id')->filter()->values()) }})" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.78rem; border-radius: 8px;" title="{{ __('Assign Roles') }}">
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
                <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; flex-direction: column; justify-content: space-between; gap: 1rem; box-shadow: var(--shadow-sm);">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.5rem;">
                            <div>
                                <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0;">
                                    {{ $role->name }}
                                </h3>
                                <code style="font-size: 0.75rem; color: var(--text-muted); background: var(--bg-body); padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 0.25rem;">
                                    {{ $role->slug }}
                                </code>
                            </div>
                            @if($role->is_system)
                                <span style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6; border: 1px solid rgba(139, 92, 246, 0.3); font-size: 0.72rem; font-weight: 800; padding: 2px 8px; border-radius: 6px; white-space: nowrap;">
                                    {{ __('System') }}
                                </span>
                            @else
                                <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); font-size: 0.72rem; font-weight: 800; padding: 2px 8px; border-radius: 6px; white-space: nowrap;">
                                    {{ __('Custom') }}
                                </span>
                            @endif
                        </div>
                        <p style="color: var(--text-muted); font-size: 0.82rem; margin: 0; line-height: 1.4;">
                            {{ $role->description ?: __('Standard operational restaurant role template.') }}
                        </p>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 0.85rem; font-size: 0.8rem; color: var(--text-muted);">
                        <div>
                            <span><strong style="color: var(--text-main);">{{ $role->permissions->count() }}</strong> {{ __('permissions') }}</span> •
                            <span><strong style="color: var(--text-main);">{{ $role->memberships_count }}</strong> {{ __('users') }}</span>
                        </div>
                        <div style="display: inline-flex; gap: 0.35rem;">
                            <button type="button" @click="duplicateRole({{ $role->id }}, '{{ addslashes($role->name) }}')" class="btn btn-secondary" style="padding: 0.3rem 0.55rem; font-size: 0.76rem; border-radius: 6px;" title="{{ __('Duplicate Role') }}">
                                <i class="fa-solid fa-copy"></i>
                            </button>
                            @if(! $role->is_system && ! in_array($role->slug, ['owner', 'vendor_owner']))
                                <button type="button" @click="editCustomRole({{ json_encode($role) }})" class="btn btn-secondary" style="padding: 0.3rem 0.55rem; font-size: 0.76rem; border-radius: 6px;" title="{{ __('Edit Role') }}">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <button type="button" @click="deleteCustomRole({{ $role->id }}, '{{ addslashes($role->name) }}')" class="btn btn-secondary" style="padding: 0.3rem 0.55rem; font-size: 0.76rem; border-radius: 6px; color: #ef4444;" title="{{ __('Delete Role') }}">
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
                @foreach($permissionCatalog as $moduleName => $modulePermissions)
                    <div class="permission-module-card" x-data="{ expanded: true }" style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden;">
                        <div @click="expanded = !expanded" style="padding: 0.85rem 1.25rem; display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none;">
                            <div style="display: flex; align-items: center; gap: 0.65rem;">
                                <i class="fa-solid" :class="expanded ? 'fa-chevron-down' : 'fa-chevron-right'" style="color: var(--text-muted); font-size: 0.75rem;"></i>
                                <span style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 0.95rem; color: var(--text-main); text-transform: uppercase; letter-spacing: 0.04em;">
                                    {{ ucfirst(str_replace('_', ' ', $moduleName)) }}
                                </span>
                                <span style="font-size: 0.72rem; padding: 2px 7px; border-radius: 999px; background: rgba(255,255,255,0.08); color: var(--text-muted);">
                                    {{ count($modulePermissions) }}
                                </span>
                            </div>
                        </div>

                        <div x-show="expanded" style="border-top: 1px solid var(--border-color); padding: 0.75rem 1.25rem; display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 0.75rem;">
                            @foreach($modulePermissions as $perm)
                                <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 10px; padding: 0.75rem; display: flex; flex-direction: column; justify-content: space-between; gap: 0.5rem;"
                                     x-show="matchesSearch('{{ $perm['key'] }}', '{{ addslashes($perm['description']) }}')">
                                    <div>
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem;">
                                            <code style="font-weight: 800; font-size: 0.78rem; color: var(--primary);">{{ $perm['key'] }}</code>
                                            @if($perm['is_sensitive'])
                                                <span style="background: rgba(239, 68, 68, 0.15); color: #ef4444; font-size: 0.68rem; font-weight: 800; padding: 1px 6px; border-radius: 4px;">{{ __('SENSITIVE') }}</span>
                                            @endif
                                        </div>
                                        <div style="color: var(--text-muted); font-size: 0.78rem; margin-top: 0.35rem; line-height: 1.35;">
                                            {{ $perm['description'] }}
                                        </div>
                                    </div>
                                    <div style="font-size: 0.7rem; color: var(--text-muted); display: flex; justify-content: space-between; align-items: center; border-top: 1px solid rgba(255,255,255,0.04); padding-top: 0.4rem;">
                                        <span>{{ __('Risk') }}: <strong style="color: var(--text-main);">{{ ucfirst($perm['risk_level']) }}</strong></span>
                                        <span>{{ ucfirst($perm['action']) }}</span>
                                    </div>
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
                                <td style="padding: 0.85rem 1.25rem; white-space: nowrap; font-size: 0.78rem; color: var(--text-muted);">
                                    {{ $log->created_at->format('Y-m-d H:i:s') }}
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

    <!-- MODAL: EFFECTIVE PERMISSIONS INSPECTOR -->
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
        showPermissionsModal: false,
        inspectedUser: {},
        inspectedEffectivePerms: [],

        activeTabStyle: 'background: var(--primary); color: #000; font-weight: 800; font-size: 0.88rem; border-radius: 10px; border: none; padding: 0.55rem 1rem; display: inline-flex; align-items: center; cursor: pointer; white-space: nowrap;',
        inactiveTabStyle: 'background: transparent; color: var(--text-muted); font-weight: 600; font-size: 0.88rem; border-radius: 10px; border: none; padding: 0.55rem 1rem; display: inline-flex; align-items: center; cursor: pointer; white-space: nowrap;',

        matchesSearch(key, desc) {
            if (!this.matrixSearch) return true;
            const q = this.matrixSearch.toLowerCase();
            return key.toLowerCase().includes(q) || desc.toLowerCase().includes(q);
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

        openInviteModal() {
            const modal = document.getElementById('newMemberModal');
            if (modal) modal.style.display = 'flex';
        },

        openCreateRoleModal() {
            const name = prompt('{{ __('Enter new custom role name (e.g. Sommelier Lead):') }}');
            if (!name) return;

            fetch('{{ route('admin.access_control.roles.store') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ name: name, permissions: [] })
            }).then(r => r.json()).then(d => {
                if (d.success) {
                    window.location.reload();
                } else {
                    alert(d.message || 'Error creating role');
                }
            });
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

