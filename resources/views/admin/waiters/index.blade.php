@extends('layouts.app')

@section('title', __('Waiters & Staff Management') . ' - ' . $vendor->name)

@section('content')
<div style="margin-bottom: 2rem;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
        <div style="min-width: 0; flex: 1;">
            <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.35rem;">
                <a href="{{ route('admin.team.index') }}" style="color: var(--text-muted); text-decoration: none;">{{ __('Team') }}</a>
                <i class="fa-solid fa-chevron-right" style="font-size: 0.65rem;"></i>
                <span style="color: var(--primary); font-weight: 700;">{{ __('Waiters Management') }}</span>
            </div>
            <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                <span style="background: rgba(245, 158, 11, 0.15); color: var(--primary); width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-user-tie"></i>
                </span>
                <span>{{ __('Waiters & Staff Management') }}</span>
            </h1>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0;">
                {{ __('Manage customer-facing waiter profiles, photos, assigned tables, and digital selection preferences.') }}
            </p>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <a href="{{ route('admin.tips_ratings.reports') }}" class="btn btn-secondary" style="font-size: 0.88rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px; text-decoration: none; padding: 0.6rem 1rem;">
                <i class="fa-solid fa-chart-line" style="color: var(--primary);"></i>
                <span>{{ __('Tips & Ratings Reports') }}</span>
            </a>
            @can('waiters.manage')
            <button class="btn btn-primary" onclick="openAddWaiterModal()" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.3); padding: 0.6rem 1.15rem;">
                <i class="fa-solid fa-user-plus"></i>
                <span>{{ __('Add New Waiter') }}</span>
            </button>
            @endcan
        </div>
    </div>

    <!-- Metric KPI Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(59, 130, 246, 0.12); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                <i class="fa-solid fa-users"></i>
            </div>
            <div>
                <div style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">{{ __('Total Waiters') }}</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-main);">{{ $totalWaiters }}</div>
            </div>
        </div>

        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(16, 185, 129, 0.12); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                <i class="fa-solid fa-user-check"></i>
            </div>
            <div>
                <div style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">{{ __('Active on Menu') }}</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: #10b981;">{{ $activeWaitersCount }}</div>
            </div>
        </div>

        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(245, 158, 11, 0.12); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                <i class="fa-solid fa-sliders"></i>
            </div>
            <div>
                <div style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">{{ __('Customer Selection') }}</div>
                <div style="font-size: 1.1rem; font-weight: 800; color: {{ $settings->allow_waiter_selection ? '#10b981' : 'var(--text-muted)' }};">
                    {{ $settings->allow_waiter_selection ? __('Enabled') : __('Disabled') }}
                </div>
            </div>
        </div>

        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(139, 92, 246, 0.12); color: #8b5cf6; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                <i class="fa-solid fa-hand-holding-dollar"></i>
            </div>
            <div>
                <div style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">{{ __('Attribution Scope') }}</div>
                <div style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); text-transform: capitalize;">
                    {{ __($settings->waiter_attribution_scope ?? 'both') }}
                </div>
            </div>
        </div>
    </div>

    <!-- Section Navigation Tabs -->
    <div style="display: flex; gap: 0.75rem; border-bottom: 2px solid var(--border-color); margin-bottom: 1.5rem; overflow-x: auto; padding-bottom: 0.25rem;">
        <button type="button" id="tabBtnWaiters" onclick="switchSection('waiters')" class="tab-link active" style="background: none; border: none; font-size: 0.95rem; font-weight: 700; padding: 0.6rem 1.2rem; cursor: pointer; color: var(--primary); border-bottom: 3px solid var(--primary); display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s ease;">
            <i class="fa-solid fa-user-group"></i>
            <span>{{ __('Waiters Roster') }} ({{ $totalWaiters }})</span>
        </button>
        <button type="button" id="tabBtnSettings" onclick="switchSection('settings')" class="tab-link" style="background: none; border: none; font-size: 0.95rem; font-weight: 600; padding: 0.6rem 1.2rem; cursor: pointer; color: var(--text-muted); border-bottom: 3px solid transparent; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s ease;">
            <i class="fa-solid fa-gear"></i>
            <span>{{ __('Customer Selection Settings') }}</span>
        </button>
    </div>

    <!-- TAB 1: WAITERS ROSTER -->
    <div id="sectionWaiters">
        <!-- Search & Filter Bar -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
            <form method="GET" action="{{ route('admin.waiters.index') }}" style="display: flex; flex-wrap: wrap; gap: 0.85rem; align-items: center;">
                <div style="flex: 1; min-width: 220px; position: relative;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.85rem;"></i>
                    <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('Search by waiter name, display name, employee ID...') }}" style="width: 100%; box-sizing: border-box; padding: 0.65rem 1rem 0.65rem 2.35rem; background: var(--bg-body); border: 1.5px solid var(--border-color); border-radius: 10px; color: var(--text-main); font-size: 0.88rem;">
                </div>

                <div style="min-width: 150px;">
                    <select name="status" onchange="this.form.submit()" style="width: 100%; padding: 0.65rem 0.85rem; background: var(--bg-body); border: 1.5px solid var(--border-color); border-radius: 10px; color: var(--text-main); font-size: 0.88rem; font-weight: 600;">
                        <option value="all" {{ $status === 'all' ? 'selected' : '' }}>{{ __('All Statuses') }}</option>
                        <option value="active" {{ $status === 'active' ? 'selected' : '' }}>{{ __('Active Only') }}</option>
                        <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>{{ __('Inactive Only') }}</option>
                    </select>
                </div>

                @if($locations->count() > 1)
                <div style="min-width: 160px;">
                    <select name="location_id" onchange="this.form.submit()" style="width: 100%; padding: 0.65rem 0.85rem; background: var(--bg-body); border: 1.5px solid var(--border-color); border-radius: 10px; color: var(--text-main); font-size: 0.88rem; font-weight: 600;">
                        <option value="">{{ __('All Branches') }}</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" {{ $locationId === $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-secondary" style="border-radius: 10px; padding: 0.65rem 1rem; font-size: 0.88rem; font-weight: 600;">
                        <i class="fa-solid fa-filter"></i> {{ __('Filter') }}
                    </button>
                    @if($search || $status !== 'all' || $locationId)
                    <a href="{{ route('admin.waiters.index') }}" class="btn btn-secondary" style="border-radius: 10px; padding: 0.65rem 0.85rem; font-size: 0.88rem;" title="{{ __('Reset filters') }}">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Waiters List Table / Card -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 0; overflow: hidden; box-shadow: var(--shadow-card);">
            <div class="responsive-table-wrapper">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                    <thead>
                        <tr style="background: var(--bg-body); border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em;">
                            <th style="padding: 1rem 1.25rem;">{{ __('Order') }}</th>
                            <th style="padding: 1rem 1.25rem;">{{ __('Waiter / Profile') }}</th>
                            <th style="padding: 1rem 1.25rem;">{{ __('Assigned Tables') }}</th>
                            <th style="padding: 1rem 1.25rem;">{{ __('Status') }}</th>
                            <th style="padding: 1rem 1.25rem;">{{ __('Rating & Tips') }}</th>
                            <th style="padding: 1rem 1.25rem; text-align: right;">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($waiters as $waiter)
                            <tr style="border-bottom: 1px solid var(--border-color); color: var(--text-main); transition: background 0.15s ease;">
                                <!-- Sort Order -->
                                <td style="padding: 1rem 1.25rem; width: 60px;">
                                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 8px; background: var(--bg-body); border: 1px solid var(--border-color); font-size: 0.82rem; font-weight: 700; color: var(--text-muted);">
                                        {{ $waiter->sort_order }}
                                    </span>
                                </td>

                                <!-- Profile Photo & Names -->
                                <td style="padding: 1rem 1.25rem;">
                                    <div style="display: flex; align-items: center; gap: 0.85rem;">
                                        <!-- Photo Avatar -->
                                        <div style="position: relative; width: 44px; height: 44px; border-radius: 50%; overflow: hidden; flex-shrink: 0; background: rgba(245, 158, 11, 0.12); border: 2px solid {{ $waiter->is_active ? 'var(--primary)' : 'var(--border-color)' }}; display: flex; align-items: center; justify-content: center;">
                                            @if(!empty($waiter->avatar_url))
                                                <img src="{{ asset($waiter->avatar_url) }}" alt="{{ $waiter->display_name ?: $waiter->name }}" style="width: 100%; height: 100%; object-fit: cover;" loading="lazy">
                                            @else
                                                <span style="font-weight: 800; font-size: 1.1rem; color: var(--primary);">
                                                    {{ mb_substr($waiter->display_name ?: $waiter->name, 0, 1) }}
                                                </span>
                                            @endif
                                        </div>

                                        <div style="min-width: 0;">
                                            <div style="font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 0.4rem;">
                                                <span>{{ $waiter->display_name ?: $waiter->name }}</span>
                                                @if(!empty($waiter->display_name) && $waiter->display_name !== $waiter->name)
                                                    <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500;">({{ $waiter->name }})</span>
                                                @endif
                                            </div>

                                            @if(!empty($waiter->short_description))
                                                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem; max-width: 260px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                    {{ $waiter->short_description }}
                                                </div>
                                            @endif

                                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.25rem;">
                                                @if(!empty($waiter->employee_id))
                                                    <span style="background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-muted); padding: 0.1rem 0.4rem; border-radius: 6px; font-size: 0.72rem; font-weight: 600;">
                                                        <i class="fa-solid fa-id-badge" style="font-size: 0.68rem;"></i> {{ $waiter->employee_id }}
                                                    </span>
                                                @endif
                                                @if($waiter->location)
                                                    <span style="font-size: 0.72rem; color: var(--text-muted);">
                                                        <i class="fa-solid fa-location-dot" style="color: var(--primary);"></i> {{ $waiter->location->name }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Assigned Tables -->
                                <td style="padding: 1rem 1.25rem;">
                                    @if($waiter->assignedTables->count() > 0)
                                        <div style="display: flex; flex-wrap: wrap; gap: 0.35rem; max-width: 220px;">
                                            @foreach($waiter->assignedTables->take(4) as $tbl)
                                                <span style="background: rgba(59, 130, 246, 0.12); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.25); border-radius: 6px; padding: 0.15rem 0.45rem; font-size: 0.75rem; font-weight: 700;">
                                                    {{ $tbl->table_number }}
                                                </span>
                                            @endforeach
                                            @if($waiter->assignedTables->count() > 4)
                                                <span style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 6px; padding: 0.15rem 0.45rem; font-size: 0.72rem; color: var(--text-muted);">
                                                    +{{ $waiter->assignedTables->count() - 4 }}
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <span style="color: var(--text-muted); font-size: 0.8rem; font-style: italic;">
                                            {{ __('All Tables (General)') }}
                                        </span>
                                    @endif
                                </td>

                                <!-- Status Toggle -->
                                <td style="padding: 1rem 1.25rem;">
                                    @can('waiters.manage')
                                    <form method="POST" action="{{ route('admin.waiters.toggle_status', $waiter) }}" style="display: inline-block;">
                                        @csrf
                                        <button type="submit" style="background: none; border: none; padding: 0; cursor: pointer; display: inline-flex; align-items: center; gap: 0.45rem;">
                                            @if($waiter->is_active)
                                                <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); padding: 0.25rem 0.65rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                                                    <i class="fa-solid fa-circle-check"></i> {{ __('Active') }}
                                                </span>
                                            @else
                                                <span style="background: rgba(107, 114, 128, 0.15); color: #6b7280; border: 1px solid rgba(107, 114, 128, 0.3); padding: 0.25rem 0.65rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                                                    <i class="fa-solid fa-circle-pause"></i> {{ __('Inactive') }}
                                                </span>
                                            @endif
                                        </button>
                                    </form>
                                    @else
                                        @if($waiter->is_active)
                                            <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); padding: 0.25rem 0.65rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700;">{{ __('Active') }}</span>
                                        @else
                                            <span style="background: rgba(107, 114, 128, 0.15); color: #6b7280; border: 1px solid rgba(107, 114, 128, 0.3); padding: 0.25rem 0.65rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700;">{{ __('Inactive') }}</span>
                                        @endif
                                    @endcan
                                </td>

                                <!-- Rating & Tips Stats -->
                                <td style="padding: 1rem 1.25rem;">
                                    <div style="display: flex; flex-direction: column; gap: 0.2rem;">
                                        <div style="display: flex; align-items: center; gap: 0.35rem; font-size: 0.85rem; font-weight: 700; color: #f59e0b;">
                                            <i class="fa-solid fa-star"></i>
                                            <span>{{ number_format((float) ($waiter->ratings_avg_rating ?? 5.0), 1) }}</span>
                                            <span style="font-size: 0.72rem; color: var(--text-muted); font-weight: 500;">({{ $waiter->ratings_count ?? 0 }})</span>
                                        </div>
                                        <div style="font-size: 0.78rem; color: var(--text-muted);">
                                            {{ __('Tips:') }} <strong>{{ number_format((float) ($waiter->tips_sum_amount ?? 0)) }} {{ $vendor->currency }}</strong>
                                        </div>
                                    </div>
                                </td>

                                <!-- Actions -->
                                <td style="padding: 1rem 1.25rem; text-align: right; white-space: nowrap;">
                                    <div style="display: inline-flex; align-items: center; gap: 0.4rem;">
                                        <!-- Customer Preview Button -->
                                        <button type="button" class="btn btn-secondary" onclick="previewCustomerCard({{ json_encode([
                                            'name' => $waiter->display_name ?: $waiter->name,
                                            'short_description' => $waiter->short_description,
                                            'avatar_url' => $waiter->avatar_url ? asset($waiter->avatar_url) : null,
                                            'initial' => mb_substr($waiter->display_name ?: $waiter->name, 0, 1),
                                            'rating' => number_format((float) ($waiter->ratings_avg_rating ?? 5.0), 1)
                                        ]) }})" style="padding: 0.45rem 0.65rem; font-size: 0.8rem; border-radius: 8px;" title="{{ __('Preview customer view') }}">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>

                                        @can('waiters.manage')
                                        <!-- Edit Button -->
                                        <button type="button" class="btn btn-secondary" onclick="openEditWaiterModal({{ json_encode([
                                            'id' => $waiter->id,
                                            'name' => $waiter->name,
                                            'display_name' => $waiter->display_name,
                                            'short_description' => $waiter->short_description,
                                            'employee_id' => $waiter->employee_id,
                                            'role' => $waiter->role,
                                            'location_id' => $waiter->location_id,
                                            'is_active' => $waiter->is_active,
                                            'sort_order' => $waiter->sort_order,
                                            'phone' => $waiter->phone,
                                            'has_photo' => !empty($waiter->avatar_url),
                                            'photo_url' => !empty($waiter->avatar_url) ? asset($waiter->avatar_url) : null,
                                            'table_ids' => $waiter->assignedTables->pluck('id')->all(),
                                        ]) }})" style="padding: 0.45rem 0.65rem; font-size: 0.8rem; border-radius: 8px;" title="{{ __('Edit waiter profile') }}">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>

                                        <!-- Delete / Deactivate Button -->
                                        <form method="POST" action="{{ route('admin.waiters.destroy', $waiter) }}" style="display: inline-block;" onsubmit="return confirm('{{ __('Are you sure you want to remove this waiter profile?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-secondary" style="padding: 0.45rem 0.65rem; font-size: 0.8rem; border-radius: 8px; color: #ef4444;" title="{{ __('Delete or deactivate waiter') }}">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 4rem 1rem; color: var(--text-muted);">
                                    <div style="font-size: 2.5rem; margin-bottom: 0.75rem; color: var(--border-color);">
                                        <i class="fa-solid fa-user-slash"></i>
                                    </div>
                                    <div style="font-size: 1.1rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem;">
                                        {{ __('No waiters found') }}
                                    </div>
                                    <p style="font-size: 0.88rem; max-width: 400px; margin: 0 auto 1.5rem;">
                                        {{ __('Add your service team members so customers can recognize and select them for tipping and service ratings.') }}
                                    </p>
                                    @can('waiters.manage')
                                    <button class="btn btn-primary" onclick="openAddWaiterModal()" style="font-size: 0.88rem; font-weight: 700; border-radius: 12px;">
                                        <i class="fa-solid fa-user-plus"></i> {{ __('Add Your First Waiter') }}
                                    </button>
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($waiters->hasPages())
                <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--border-color);">
                    {{ $waiters->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- TAB 2: CUSTOMER SELECTION SETTINGS -->
    <div id="sectionSettings" style="display: none;">
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.25rem, 3vw, 2rem); box-shadow: var(--shadow-card);">
            <form method="POST" action="{{ route('admin.waiters.settings.update') }}">
                @csrf
                <div style="margin-bottom: 2rem;">
                    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0 0 0.5rem; display: flex; align-items: center; gap: 0.6rem;">
                        <i class="fa-solid fa-sliders" style="color: var(--primary);"></i>
                        <span>{{ __('Customer Waiter Selection Configuration') }}</span>
                    </h3>
                    <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0;">
                        {{ __('Configure how and when customers can select their serving waiter on digital QR menus and checkout flows.') }}
                    </p>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                    <!-- Master Toggle -->
                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
                        <label style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer;">
                            <input type="checkbox" name="allow_waiter_selection" value="1" {{ $settings->allow_waiter_selection ? 'checked' : '' }} style="width: 20px; height: 20px; margin-top: 0.15rem; accent-color: var(--primary);">
                            <div>
                                <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                                    {{ __('Enable Waiter Selection') }}
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
                                    {{ __('When enabled, customers can select a serving waiter from visual avatar cards.') }}
                                </div>
                            </div>
                        </label>
                    </div>

                    <!-- Required vs Optional -->
                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
                        <label style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer;">
                            <input type="checkbox" name="waiter_selection_required" value="1" {{ $settings->waiter_selection_required ? 'checked' : '' }} style="width: 20px; height: 20px; margin-top: 0.15rem; accent-color: var(--primary);">
                            <div>
                                <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                                    {{ __('Require Waiter Selection') }}
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
                                    {{ __('If unchecked, customers will have a clear "Skip / Not sure" option.') }}
                                </div>
                            </div>
                        </label>
                    </div>

                    <!-- Flow Stage -->
                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
                        <label style="font-weight: 700; color: var(--text-main); font-size: 0.92rem; display: block; margin-bottom: 0.5rem;">
                            {{ __('Selection Stage in Customer Journey') }}
                        </label>
                        <select name="waiter_selection_stage" style="width: 100%; padding: 0.65rem 0.85rem; background: var(--bg-card); border: 1.5px solid var(--border-color); border-radius: 10px; color: var(--text-main); font-size: 0.88rem; font-weight: 600;">
                            <option value="all" {{ ($settings->waiter_selection_stage ?? 'all') === 'all' ? 'selected' : '' }}>{{ __('All Stages (Order checkout, Bill, & Ratings)') }}</option>
                            <option value="order" {{ ($settings->waiter_selection_stage ?? '') === 'order' ? 'selected' : '' }}>{{ __('Order Checkout Only') }}</option>
                            <option value="bill" {{ ($settings->waiter_selection_stage ?? '') === 'bill' ? 'selected' : '' }}>{{ __('Bill & Tips Only') }}</option>
                            <option value="rating" {{ ($settings->waiter_selection_stage ?? '') === 'rating' ? 'selected' : '' }}>{{ __('Post-Service Rating Only') }}</option>
                        </select>
                    </div>

                    <!-- Attribution Scope -->
                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
                        <label style="font-weight: 700; color: var(--text-main); font-size: 0.92rem; display: block; margin-bottom: 0.5rem;">
                            {{ __('Attribution Scope') }}
                        </label>
                        <select name="waiter_attribution_scope" style="width: 100%; padding: 0.65rem 0.85rem; background: var(--bg-card); border: 1.5px solid var(--border-color); border-radius: 10px; color: var(--text-main); font-size: 0.88rem; font-weight: 600;">
                            <option value="both" {{ ($settings->waiter_attribution_scope ?? 'both') === 'both' ? 'selected' : '' }}>{{ __('Both Tips & Ratings') }}</option>
                            <option value="tips" {{ ($settings->waiter_attribution_scope ?? '') === 'tips' ? 'selected' : '' }}>{{ __('Tips Attribution Only') }}</option>
                            <option value="ratings" {{ ($settings->waiter_attribution_scope ?? '') === 'ratings' ? 'selected' : '' }}>{{ __('Ratings Attribution Only') }}</option>
                            <option value="none" {{ ($settings->waiter_attribution_scope ?? '') === 'none' ? 'selected' : '' }}>{{ __('Informational Only (No Financial/Score Attribution)') }}</option>
                        </select>
                    </div>

                    <!-- Display Photos -->
                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
                        <label style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer;">
                            <input type="checkbox" name="show_waiter_photos" value="1" {{ ($settings->show_waiter_photos ?? true) ? 'checked' : '' }} style="width: 20px; height: 20px; margin-top: 0.15rem; accent-color: var(--primary);">
                            <div>
                                <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                                    {{ __('Display Waiter Profile Photos') }}
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
                                    {{ __('Display circular profile avatars on mobile selection cards for instant visual recognition.') }}
                                </div>
                            </div>
                        </label>
                    </div>

                    <!-- Display Descriptions -->
                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
                        <label style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer;">
                            <input type="checkbox" name="show_waiter_descriptions" value="1" {{ ($settings->show_waiter_descriptions ?? true) ? 'checked' : '' }} style="width: 20px; height: 20px; margin-top: 0.15rem; accent-color: var(--primary);">
                            <div>
                                <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                                    {{ __('Display Short Public Descriptions') }}
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
                                    {{ __('Show short waiter introduction notes (e.g. "Wine specialist", "Shift supervisor").') }}
                                </div>
                            </div>
                        </label>
                    </div>

                    <!-- Table-based Filtering -->
                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
                        <label style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer;">
                            <input type="checkbox" name="filter_waiters_by_table" value="1" {{ ($settings->filter_waiters_by_table ?? false) ? 'checked' : '' }} style="width: 20px; height: 20px; margin-top: 0.15rem; accent-color: var(--primary);">
                            <div>
                                <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                                    {{ __('Filter by Table Assignments') }}
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
                                    {{ __('Only present waiters assigned to the current table QR (falls back to all active if table has no assigned waiters).') }}
                                </div>
                            </div>
                        </label>
                    </div>

                    <!-- Sort Order -->
                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
                        <label style="font-weight: 700; color: var(--text-main); font-size: 0.92rem; display: block; margin-bottom: 0.5rem;">
                            {{ __('Display Sort Order') }}
                        </label>
                        <select name="waiter_sort_by" style="width: 100%; padding: 0.65rem 0.85rem; background: var(--bg-card); border: 1.5px solid var(--border-color); border-radius: 10px; color: var(--text-main); font-size: 0.88rem; font-weight: 600;">
                            <option value="sort_order" {{ ($settings->waiter_sort_by ?? 'sort_order') === 'sort_order' ? 'selected' : '' }}>{{ __('Custom Sort Order Number') }}</option>
                            <option value="name" {{ ($settings->waiter_sort_by ?? '') === 'name' ? 'selected' : '' }}>{{ __('Alphabetical (A-Z)') }}</option>
                        </select>
                    </div>
                </div>

                @can('waiters.manage')
                <div style="margin-top: 2rem; display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary" style="font-size: 0.95rem; font-weight: 700; border-radius: 12px; padding: 0.75rem 2rem; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.3);">
                        <i class="fa-solid fa-floppy-disk"></i> {{ __('Save Customer Selection Settings') }}
                    </button>
                </div>
                @endcan
            </form>
        </div>
    </div>
</div>

<!-- MODAL: ADD / EDIT WAITER -->
<div id="waiterFormModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card modal-box-responsive" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; width: 100%; max-width: min(600px, 94vw); max-height: 90vh; overflow-y: auto; box-sizing: border-box; padding: clamp(1.25rem, 3vw, 2rem); box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 id="modalTitle" style="font-family: 'Outfit', sans-serif; font-size: 1.3rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-user-tie" style="color: var(--primary);"></i>
                <span>{{ __('Add New Waiter') }}</span>
            </h3>
            <button type="button" onclick="closeWaiterModal()" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer; padding: 0.25rem;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="waiterForm" method="POST" action="{{ route('admin.waiters.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" id="formMethod" name="_method" value="POST">

            <!-- Profile Photo Upload & Live Preview -->
            <div style="margin-bottom: 1.5rem; text-align: center;">
                <div style="position: relative; width: 96px; height: 96px; border-radius: 50%; overflow: hidden; margin: 0 auto 0.75rem; border: 3px solid var(--primary); background: var(--bg-body); display: flex; align-items: center; justify-content: center;">
                    <img id="avatarPreview" src="" alt="Avatar preview" style="display: none; width: 100%; height: 100%; object-fit: cover;">
                    <div id="avatarPlaceholder" style="font-size: 2.25rem; color: var(--text-muted);">
                        <i class="fa-solid fa-user"></i>
                    </div>
                </div>

                <div style="display: flex; justify-content: center; gap: 0.5rem; flex-wrap: wrap;">
                    <label class="btn btn-secondary" style="font-size: 0.8rem; font-weight: 600; border-radius: 8px; cursor: pointer; padding: 0.45rem 0.85rem;">
                        <i class="fa-solid fa-camera"></i> {{ __('Choose Photo') }}
                        <input type="file" name="photo" id="photoInput" accept="image/jpeg,image/png,image/webp" style="display: none;" onchange="handlePhotoSelect(event)">
                    </label>
                    <button type="button" id="removePhotoBtn" onclick="markPhotoRemoval()" class="btn btn-secondary" style="display: none; font-size: 0.8rem; font-weight: 600; border-radius: 8px; color: #ef4444; padding: 0.45rem 0.85rem;">
                        <i class="fa-solid fa-trash-can"></i> {{ __('Remove Photo') }}
                    </button>
                </div>
                <input type="hidden" name="remove_photo" id="removePhotoInput" value="0">
                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.35rem;">
                    {{ __('JPG, PNG or WebP up to 5MB. Metadata is sanitized automatically.') }}
                </div>
            </div>

            <!-- Waiter Fields -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                        {{ __('Full Name') }} <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="name" id="waiterName" required placeholder="{{ __('e.g. Armen Harutyunyan') }}" style="width: 100%; box-sizing: border-box; padding: 0.65rem 0.85rem; background: var(--bg-body); border: 1.5px solid var(--border-color); border-radius: 10px; color: var(--text-main); font-size: 0.9rem;">
                </div>

                <div>
                    <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                        {{ __('Customer Display Name') }}
                    </label>
                    <input type="text" name="display_name" id="waiterDisplayName" placeholder="{{ __('e.g. Armen') }}" style="width: 100%; box-sizing: border-box; padding: 0.65rem 0.85rem; background: var(--bg-body); border: 1.5px solid var(--border-color); border-radius: 10px; color: var(--text-main); font-size: 0.9rem;">
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                    {{ __('Short Public Description') }}
                </label>
                <input type="text" name="short_description" id="waiterDescription" maxlength="500" placeholder="{{ __('e.g. Wine specialist • Floor Manager • 4 years at Bistro Alpha') }}" style="width: 100%; box-sizing: border-box; padding: 0.65rem 0.85rem; background: var(--bg-body); border: 1.5px solid var(--border-color); border-radius: 10px; color: var(--text-main); font-size: 0.88rem;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                        {{ __('Internal Employee ID') }}
                    </label>
                    <input type="text" name="employee_id" id="waiterEmployeeId" placeholder="{{ __('e.g. EMP-104') }}" style="width: 100%; box-sizing: border-box; padding: 0.65rem 0.85rem; background: var(--bg-body); border: 1.5px solid var(--border-color); border-radius: 10px; color: var(--text-main); font-size: 0.88rem;">
                    <span style="font-size: 0.72rem; color: var(--text-muted);">{{ __('Confidential. Never shown to guests.') }}</span>
                </div>

                <div>
                    <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                        {{ __('Display Sort Order') }}
                    </label>
                    <input type="number" name="sort_order" id="waiterSortOrder" value="0" min="0" max="9999" style="width: 100%; box-sizing: border-box; padding: 0.65rem 0.85rem; background: var(--bg-body); border: 1.5px solid var(--border-color); border-radius: 10px; color: var(--text-main); font-size: 0.88rem;">
                </div>
            </div>

            @if($locations->count() > 1)
            <div style="margin-bottom: 1rem;">
                <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                    {{ __('Branch / Location') }}
                </label>
                <select name="location_id" id="waiterLocationId" style="width: 100%; padding: 0.65rem 0.85rem; background: var(--bg-body); border: 1.5px solid var(--border-color); border-radius: 10px; color: var(--text-main); font-size: 0.88rem;">
                    <option value="">{{ __('All Branches (General)') }}</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <!-- Table Assignments -->
            @if($tables->count() > 0)
            <div style="margin-bottom: 1.25rem;">
                <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.4rem;">
                    {{ __('Assigned Tables') }}
                </label>
                <div style="background: var(--bg-body); border: 1.5px solid var(--border-color); border-radius: 12px; padding: 0.75rem; max-height: 140px; overflow-y: auto; display: flex; flex-wrap: wrap; gap: 0.45rem;">
                    @foreach($tables as $tableItem)
                        <label style="display: inline-flex; align-items: center; gap: 0.35rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.3rem 0.6rem; font-size: 0.8rem; font-weight: 600; cursor: pointer;">
                            <input type="checkbox" name="assigned_tables[]" value="{{ $tableItem->id }}" class="table-checkbox" id="tblCheck_{{ $tableItem->id }}" style="accent-color: var(--primary);">
                            <span>{{ $tableItem->table_number }}</span>
                        </label>
                    @endforeach
                </div>
                <span style="font-size: 0.72rem; color: var(--text-muted);">{{ __('Leave unchecked to assign to all tables.') }}</span>
            </div>
            @endif

            <!-- Active Status Toggle -->
            <div style="margin-bottom: 1.5rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.85rem 1rem;">
                <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer;">
                    <input type="checkbox" name="is_active" id="waiterIsActive" value="1" checked style="width: 18px; height: 18px; accent-color: var(--primary);">
                    <div>
                        <div style="font-weight: 700; color: var(--text-main); font-size: 0.88rem;">{{ __('Active on Customer Menu') }}</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">{{ __('Inactive waiters will not appear on customer mobile selections.') }}</div>
                    </div>
                </label>
            </div>

            <!-- Optional Credentials for staff dashboard login -->
            <div style="border-top: 1px dashed var(--border-color); padding-top: 1rem; margin-bottom: 1.5rem;">
                <button type="button" onclick="toggleCredentialsSection()" style="background: none; border: none; font-size: 0.82rem; font-weight: 700; color: var(--primary); cursor: pointer; display: flex; align-items: center; gap: 0.4rem; padding: 0;">
                    <i id="credIcon" class="fa-solid fa-chevron-right" style="font-size: 0.72rem;"></i>
                    <span>{{ __('Staff Dashboard Login Credentials (Optional)') }}</span>
                </button>

                <div id="credentialsSection" style="display: none; margin-top: 1rem;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 0.35rem;">
                                {{ __('Email Address') }}
                            </label>
                            <input type="email" name="email" id="waiterEmail" placeholder="waiter@restaurant.com" style="width: 100%; box-sizing: border-box; padding: 0.6rem 0.85rem; background: var(--bg-body); border: 1.5px solid var(--border-color); border-radius: 10px; color: var(--text-main); font-size: 0.85rem;">
                        </div>
                        <div>
                            <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 0.35rem;">
                                {{ __('Password') }}
                            </label>
                            <input type="password" name="password" id="waiterPassword" placeholder="{{ __('Minimum 6 characters') }}" style="width: 100%; box-sizing: border-box; padding: 0.6rem 0.85rem; background: var(--bg-body); border: 1.5px solid var(--border-color); border-radius: 10px; color: var(--text-main); font-size: 0.85rem;">
                        </div>
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="closeWaiterModal()" class="btn btn-secondary" style="border-radius: 12px; font-weight: 600; padding: 0.65rem 1.25rem;">
                    {{ __('Cancel') }}
                </button>
                <button type="submit" class="btn btn-primary" style="border-radius: 12px; font-weight: 700; padding: 0.65rem 1.5rem; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.3);">
                    {{ __('Save Waiter') }}
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: CUSTOMER PREVIEW -->
<div id="previewModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 24px; width: 100%; max-width: 380px; box-sizing: border-box; padding: 1.5rem; box-shadow: 0 25px 60px rgba(0,0,0,0.5); text-align: center;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <div style="font-size: 0.85rem; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 0.05em;">
                <i class="fa-solid fa-mobile-screen"></i> {{ __('Customer Mobile View') }}
            </div>
            <button type="button" onclick="closePreviewModal()" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Simulated Mobile Waiter Card (Selected state) -->
        <div style="background: var(--bg-body); border: 2px solid var(--primary); border-radius: 20px; padding: 1.25rem; position: relative; box-shadow: 0 8px 24px rgba(245, 158, 11, 0.15);">
            <div style="position: absolute; top: 0.75rem; right: 0.75rem; width: 24px; height: 24px; border-radius: 50%; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.75rem;">
                <i class="fa-solid fa-check"></i>
            </div>

            <div id="prevAvatarWrap" style="width: 72px; height: 72px; border-radius: 50%; overflow: hidden; margin: 0 auto 0.75rem; border: 2.5px solid var(--primary); display: flex; align-items: center; justify-content: center; background: rgba(245, 158, 11, 0.15);">
                <img id="prevImg" src="" alt="" style="display: none; width: 100%; height: 100%; object-fit: cover;">
                <span id="prevInit" style="font-size: 1.8rem; font-weight: 800; color: var(--primary);"></span>
            </div>

            <div id="prevName" style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem;"></div>

            <div id="prevDesc" style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 0.75rem; min-height: 1.2rem;"></div>

            <div style="display: inline-flex; align-items: center; gap: 0.35rem; background: rgba(245, 158, 11, 0.15); color: #f59e0b; padding: 0.25rem 0.65rem; border-radius: 10px; font-size: 0.8rem; font-weight: 700;">
                <i class="fa-solid fa-star"></i>
                <span id="prevRating">5.0</span>
                <span style="color: var(--text-muted); font-size: 0.72rem;">{{ __('Average Service') }}</span>
            </div>
        </div>

        <p style="font-size: 0.78rem; color: var(--text-muted); margin: 1.25rem 0 0;">
            {{ __('This visual card is presented to guests during order checkout, bill payment, and rating review.') }}
        </p>
    </div>
</div>

<script>
function switchSection(section) {
    const secWaiters = document.getElementById('sectionWaiters');
    const secSettings = document.getElementById('sectionSettings');
    const tabWaiters = document.getElementById('tabBtnWaiters');
    const tabSettings = document.getElementById('tabBtnSettings');

    if (section === 'waiters') {
        secWaiters.style.display = 'block';
        secSettings.style.display = 'none';
        tabWaiters.style.color = 'var(--primary)';
        tabWaiters.style.borderBottomColor = 'var(--primary)';
        tabSettings.style.color = 'var(--text-muted)';
        tabSettings.style.borderBottomColor = 'transparent';
    } else {
        secWaiters.style.display = 'none';
        secSettings.style.display = 'block';
        tabSettings.style.color = 'var(--primary)';
        tabSettings.style.borderBottomColor = 'var(--primary)';
        tabWaiters.style.color = 'var(--text-muted)';
        tabWaiters.style.borderBottomColor = 'transparent';
    }
}

function openAddWaiterModal() {
    const form = document.getElementById('waiterForm');
    form.action = "{{ route('admin.waiters.store') }}";
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-user-plus" style="color: var(--primary);"></i> <span>{{ __("Add New Waiter") }}</span>';
    
    // Clear fields
    document.getElementById('waiterName').value = '';
    document.getElementById('waiterDisplayName').value = '';
    document.getElementById('waiterDescription').value = '';
    document.getElementById('waiterEmployeeId').value = '';
    document.getElementById('waiterSortOrder').value = '0';
    document.getElementById('waiterIsActive').checked = true;
    if (document.getElementById('waiterLocationId')) {
        document.getElementById('waiterLocationId').value = '';
    }
    document.getElementById('waiterEmail').value = '';
    document.getElementById('waiterPassword').value = '';
    document.getElementById('removePhotoInput').value = '0';
    document.getElementById('removePhotoBtn').style.display = 'none';
    
    // Reset avatar preview
    document.getElementById('avatarPreview').style.display = 'none';
    document.getElementById('avatarPlaceholder').style.display = 'block';

    // Uncheck table checkboxes
    document.querySelectorAll('.table-checkbox').forEach(cb => cb.checked = false);

    document.getElementById('waiterFormModal').style.display = 'flex';
}

function openEditWaiterModal(data) {
    const form = document.getElementById('waiterForm');
    form.action = `/admin/waiters/${data.id}`;
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square" style="color: var(--primary);"></i> <span>{{ __("Edit Waiter Profile") }}</span>';

    document.getElementById('waiterName').value = data.name || '';
    document.getElementById('waiterDisplayName').value = data.display_name || '';
    document.getElementById('waiterDescription').value = data.short_description || '';
    document.getElementById('waiterEmployeeId').value = data.employee_id || '';
    document.getElementById('waiterSortOrder').value = data.sort_order || 0;
    document.getElementById('waiterIsActive').checked = !!data.is_active;

    if (document.getElementById('waiterLocationId')) {
        document.getElementById('waiterLocationId').value = data.location_id || '';
    }
    document.getElementById('waiterEmail').value = '';
    document.getElementById('waiterPassword').value = '';
    document.getElementById('removePhotoInput').value = '0';

    if (data.has_photo && data.photo_url) {
        document.getElementById('avatarPreview').src = data.photo_url;
        document.getElementById('avatarPreview').style.display = 'block';
        document.getElementById('avatarPlaceholder').style.display = 'none';
        document.getElementById('removePhotoBtn').style.display = 'inline-flex';
    } else {
        document.getElementById('avatarPreview').style.display = 'none';
        document.getElementById('avatarPlaceholder').style.display = 'block';
        document.getElementById('removePhotoBtn').style.display = 'none';
    }

    // Set table checkboxes
    const assignedIds = data.table_ids || [];
    document.querySelectorAll('.table-checkbox').forEach(cb => {
        cb.checked = assignedIds.includes(parseInt(cb.value));
    });

    document.getElementById('waiterFormModal').style.display = 'flex';
}

function closeWaiterModal() {
    document.getElementById('waiterFormModal').style.display = 'none';
}

function handlePhotoSelect(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarPreview').src = e.target.result;
            document.getElementById('avatarPreview').style.display = 'block';
            document.getElementById('avatarPlaceholder').style.display = 'none';
            document.getElementById('removePhotoBtn').style.display = 'inline-flex';
            document.getElementById('removePhotoInput').value = '0';
        };
        reader.readAsDataURL(file);
    }
}

function markPhotoRemoval() {
    document.getElementById('avatarPreview').style.display = 'none';
    document.getElementById('avatarPlaceholder').style.display = 'block';
    document.getElementById('removePhotoBtn').style.display = 'none';
    document.getElementById('photoInput').value = '';
    document.getElementById('removePhotoInput').value = '1';
}

function toggleCredentialsSection() {
    const sec = document.getElementById('credentialsSection');
    const icon = document.getElementById('credIcon');
    if (sec.style.display === 'none') {
        sec.style.display = 'block';
        icon.className = 'fa-solid fa-chevron-down';
    } else {
        sec.style.display = 'none';
        icon.className = 'fa-solid fa-chevron-right';
    }
}

function previewCustomerCard(data) {
    document.getElementById('prevName').textContent = data.name;
    document.getElementById('prevDesc').textContent = data.short_description || '{{ __("Dedicated Server") }}';
    document.getElementById('prevRating').textContent = data.rating;

    if (data.avatar_url) {
        document.getElementById('prevImg').src = data.avatar_url;
        document.getElementById('prevImg').style.display = 'block';
        document.getElementById('prevInit').style.display = 'none';
    } else {
        document.getElementById('prevImg').style.display = 'none';
        document.getElementById('prevInit').textContent = data.initial;
        document.getElementById('prevInit').style.display = 'block';
    }

    document.getElementById('previewModal').style.display = 'flex';
}

function closePreviewModal() {
    document.getElementById('previewModal').style.display = 'none';
}

// Close modals when clicking outside
window.addEventListener('click', function(e) {
    const waiterModal = document.getElementById('waiterFormModal');
    const prevModal = document.getElementById('previewModal');
    if (e.target === waiterModal) closeWaiterModal();
    if (e.target === prevModal) closePreviewModal();
});
</script>
@endsection
