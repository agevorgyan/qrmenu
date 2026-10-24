@extends('layouts.app')

@section('title', __('Interactive Table Floor Plan') . ' — ' . $vendor->name)

@section('content')
<div style="max-width: 1440px; margin: 0 auto; width: 100%; box-sizing: border-box;">
    <!-- Top Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span style="width: 46px; height: 46px; border-radius: 14px; background: rgba(59, 130, 246, 0.15); color: #3b82f6; display: inline-flex; align-items: center; justify-content: center; font-size: 1.35rem; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);">
                    <i class="fa-solid fa-map-location-dot"></i>
                </span>
                <div>
                    <div style="display: flex; align-items: center; gap: 0.6rem;">
                        <h1 style="font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; margin: 0; color: var(--text-main); font-family: 'Outfit', sans-serif;">
                            {{ __('Interactive Table Floor Plan') }}
                        </h1>
                        <span id="liveIndicator" style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.72rem; font-weight: 800; color: #10b981; background: rgba(16, 185, 129, 0.12); padding: 0.2rem 0.55rem; border-radius: 9999px; letter-spacing: 0.05em;">
                            <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981; animation: livePulse 2s infinite;"></span>
                            LIVE
                        </span>
                    </div>
                    <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0.2rem 0 0 0;">
                        {{ __('Real-time visual map of halls and tables') }} &bull; {{ $location?->name ?? __('Main Branch') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Controls Bar -->
        <div style="display: flex; gap: 0.65rem; align-items: center; flex-wrap: wrap;">
            <!-- Mode Switcher -->
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.25rem; display: inline-flex; gap: 0.25rem;">
                <button type="button" id="btnModeService" onclick="setMode('service')" class="mode-btn active" title="{{ __('Service Mode: inspect live orders and reservations') }}">
                    <i class="fa-solid fa-bell-concierge"></i> <span>{{ __('Service') }}</span>
                </button>
                <button type="button" id="btnModeEdit" onclick="setMode('edit')" class="mode-btn" title="{{ __('Editor Mode: drag tables and manage layout') }}">
                    <i class="fa-solid fa-pen-ruler"></i> <span>{{ __('Edit Layout') }}</span>
                </button>
            </div>

            <!-- Manage Halls Button -->
            <button type="button" onclick="openHallsModal()" class="btn btn-secondary" style="border-radius: 12px; font-weight: 700; font-size: 0.88rem; display: flex; align-items: center; gap: 0.45rem;">
                <i class="fa-solid fa-door-open" style="color: #6366f1;"></i> <span>{{ __('Halls') }}</span>
            </button>

            <!-- Combine Tables Button -->
            <button type="button" onclick="openCombineTablesModal()" class="btn btn-secondary" style="border-radius: 12px; font-weight: 700; font-size: 0.88rem; display: flex; align-items: center; gap: 0.45rem;">
                <i class="fa-solid fa-link" style="color: #9333ea;"></i> <span>{{ __('Combine Tables') }}</span>
            </button>

            <!-- Add Table Button -->
            <button type="button" onclick="openAddTableModal()" class="btn btn-secondary" style="border-radius: 12px; font-weight: 700; font-size: 0.88rem; display: flex; align-items: center; gap: 0.45rem;">
                <i class="fa-solid fa-plus" style="color: var(--primary);"></i> <span>{{ __('Add Table') }}</span>
            </button>

            <!-- Save Layout Button -->
            <button type="button" onclick="saveFloorPlanChanges()" id="btnSaveFloorPlan" class="btn btn-primary" style="border-radius: 12px; font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.3);">
                <i class="fa-solid fa-floppy-disk"></i> <span>{{ __('Save Layout') }}</span>
            </button>
        </div>
    </div>

    <!-- Quick Stat Pills Bar -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <!-- Total Tables -->
        <div class="card stat-pill-card" onclick="filterStatus('all')">
            <div class="stat-icon" style="background: rgba(99, 102, 241, 0.12); color: #6366f1;">
                <i class="fa-solid fa-chair"></i>
            </div>
            <div>
                <div class="stat-title">{{ __('Total Tables') }}</div>
                <div class="stat-val" id="kpiTotalTables" style="color: var(--text-main);">{{ $totalTables }}</div>
            </div>
        </div>

        <!-- Available Tables -->
        <div class="card stat-pill-card" onclick="filterStatus('available')">
            <div class="stat-icon" style="background: rgba(16, 185, 129, 0.12); color: #10b981;">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div>
                <div class="stat-title">{{ __('Available') }}</div>
                <div class="stat-val" id="kpiAvailableCount" style="color: #10b981;">{{ $availableCount }}</div>
            </div>
        </div>

        <!-- Occupied Tables (Orders & Seated) -->
        <div class="card stat-pill-card" onclick="filterStatus('occupied')">
            <div class="stat-icon" style="background: rgba(239, 68, 68, 0.12); color: #ef4444;">
                <i class="fa-solid fa-fire"></i>
            </div>
            <div>
                <div class="stat-title">{{ __('Occupied / Seated') }}</div>
                <div class="stat-val" id="kpiOccupiedCount" style="color: #ef4444;">{{ $occupiedCount }}</div>
            </div>
        </div>

        <!-- Reserved Tables (Today's Bookings) -->
        <div class="card stat-pill-card" onclick="filterStatus('reserved')">
            <div class="stat-icon" style="background: rgba(168, 85, 247, 0.15); color: #a855f7;">
                <i class="fa-solid fa-calendar-check"></i>
            </div>
            <div>
                <div class="stat-title">{{ __('Reserved Today') }}</div>
                <div class="stat-val" id="kpiReservedCount" style="color: #a855f7;">{{ $reservedCount }}</div>
            </div>
        </div>

        <!-- Waiter Calls -->
        <div class="card stat-pill-card" onclick="filterStatus('call')">
            <div class="stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                <i class="fa-solid fa-bell"></i>
            </div>
            <div>
                <div class="stat-title">{{ __('Waiter Calls') }}</div>
                <div class="stat-val" id="kpiWaiterCallCount" style="color: #f59e0b;">{{ $waiterCallCount }}</div>
            </div>
        </div>
    </div>

    <!-- Halls Filter Tabs & Toolbar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem;">
        <!-- Hall Tabs -->
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;" id="hallTabsContainer">
            <button type="button" class="hall-tab-btn active" data-hall="all" onclick="filterHall('all')">
                <i class="fa-solid fa-border-all"></i> {{ __('All Halls') }}
            </button>
            @foreach($halls as $hall)
                <button type="button" class="hall-tab-btn" data-hall="{{ $hall['id'] }}" onclick="filterHall('{{ $hall['id'] }}')">
                    <span style="width: 10px; height: 10px; border-radius: 50%; background: {{ $hall['color'] ?? '#3b82f6' }}; display: inline-block;"></span>
                    {{ $hall['name'] }}
                </button>
            @endforeach
        </div>

        <!-- Canvas Tools & Zoom Controls -->
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <!-- Snap to Grid Toggle -->
            <button type="button" id="btnSnapToggle" onclick="toggleSnap()" class="btn btn-secondary" style="padding: 0.45rem 0.75rem; font-size: 0.8rem; border-radius: 10px;" title="{{ __('Snap to 20px grid') }}">
                <i class="fa-solid fa-magnet"></i> <span id="snapLabel">{{ __('Snap: ON') }}</span>
            </button>

            <!-- Zoom Controls -->
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 10px; padding: 0.2rem 0.4rem; display: flex; align-items: center; gap: 0.35rem;">
                <button type="button" onclick="adjustZoom(-0.1)" class="zoom-btn" title="{{ __('Zoom Out') }}">
                    <i class="fa-solid fa-minus"></i>
                </button>
                <span id="zoomLevelText" style="font-size: 0.8rem; font-weight: 700; min-width: 44px; text-align: center; color: var(--text-main);">100%</span>
                <button type="button" onclick="adjustZoom(0.1)" class="zoom-btn" title="{{ __('Zoom In') }}">
                    <i class="fa-solid fa-plus"></i>
                </button>
                <button type="button" onclick="resetZoom()" class="zoom-btn" title="{{ __('Reset Zoom') }}" style="margin-left: 0.25rem;">
                    <i class="fa-solid fa-rotate-left"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Interactive Floor Canvas Card -->
    <div class="card floor-canvas-wrapper" id="canvasWrapper" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; min-height: 640px; position: relative; overflow: auto; box-shadow: var(--shadow-card);">
        <div id="floorCanvas" style="min-width: 1100px; min-height: 600px; position: relative; background-image: radial-gradient(var(--border-color) 1.5px, transparent 1.5px); background-size: 20px 20px; transform-origin: top left; transition: transform 0.15s ease-out;">
            @foreach($tables as $table)
                @php
                    $isOccupied = ($table['status'] ?? '') === 'occupied';
                    $isReserved = ($table['status'] ?? '') === 'reserved';
                    $hasCall = !empty($table['has_waiter_call']);
                    $isMerged = !empty($table['is_merged']);
                    $shape = $table['shape'] ?? 'square';
                    $hallId = $table['hall_id'] ?? 'main';
                    $order = $table['order'] ?? null;
                    $reservation = $table['reservation'] ?? null;
                    $call = $table['waiter_call'] ?? null;
                    $x = (int) ($table['x'] ?? 50);
                    $y = (int) ($table['y'] ?? 50);
                @endphp

                <div class="floor-table-node {{ $shape }} status-{{ $table['status'] }} {{ $hasCall ? 'has-call' : '' }} {{ $isMerged ? 'is-merged' : '' }}"
                     id="table-node-{{ $table['id'] }}"
                     data-id="{{ $table['id'] }}"
                     data-number="{{ $table['number'] }}"
                     data-hall="{{ $hallId }}"
                     data-shape="{{ $shape }}"
                     data-capacity="{{ $table['capacity'] }}"
                     data-status="{{ $table['status'] }}"
                     data-table='@json($table)'
                     style="left: {{ $x }}px; top: {{ $y }}px;"
                     onclick="handleTableClick({{ json_encode($table) }})">

                    <!-- Call Badge -->
                    @if($hasCall)
                        <div class="table-call-badge" title="{{ __('Active Waiter Call') }}">
                            <i class="fa-solid fa-bell"></i>
                        </div>
                    @endif

                    <!-- Merged Badge -->
                    @if($isMerged)
                        <div class="table-merged-badge" title="{{ __('Combined Table') }}: #{{ $table['merged_with_number'] }} ({{ $table['effective_capacity'] }} {{ __('seats') }})">
                            <i class="fa-solid fa-link"></i> {{ $table['effective_capacity'] }}
                        </div>
                    @endif

                    <!-- Visual Chair Silhouettes for Realism -->
                    <div class="table-chairs-layer shape-{{ $shape }}">
                        <span class="chair chair-top"></span>
                        <span class="chair chair-bottom"></span>
                        @if($shape !== 'round')
                            <span class="chair chair-left"></span>
                            <span class="chair chair-right"></span>
                        @endif
                        @if($shape === 'rectangle')
                            <span class="chair chair-top-2"></span>
                            <span class="chair chair-bottom-2"></span>
                        @endif
                    </div>

                    <div class="table-inner">
                        <div class="table-number">#{{ $table['number'] }}</div>
                        <div class="table-capacity">
                            <i class="fa-solid fa-user-group"></i> {{ $table['effective_capacity'] }} {{ __('seats') }}
                        </div>

                        <!-- In-place Live Operational State Display -->
                        @if($isOccupied && $order)
                            <div class="table-order-mini">
                                <div class="table-order-num">{{ $order['order_number'] }}</div>
                                <div class="table-order-amt">{{ number_format($order['total_amount']) }} {{ $vendor->currency }}</div>
                                <div class="table-order-time"><i class="fa-regular fa-clock"></i> {{ $order['elapsed_minutes'] }}m</div>
                            </div>
                        @elseif($isOccupied && $reservation && $reservation['status'] === 'seated')
                            <div class="table-order-mini">
                                <div class="table-order-num" style="color: #ef4444;">{{ __('Seated Guest') }}</div>
                                <div class="table-guest-name">{{ Str::limit($reservation['customer_name'], 12) }}</div>
                                <div class="table-order-time">{{ $reservation['time'] }}</div>
                            </div>
                        @elseif($isReserved && $reservation)
                            <div class="table-reservation-mini">
                                <div class="table-res-time"><i class="fa-regular fa-calendar"></i> {{ $reservation['time'] }}</div>
                                <div class="table-res-name">{{ Str::limit($reservation['customer_name'], 12) }}</div>
                                <div class="table-res-guests">{{ $reservation['guest_count'] }} {{ __('guests') }}</div>
                            </div>
                        @else
                            <div class="table-status-label">{{ __('Available') }}</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<!-- ================= MODALS ================= -->

<!-- 1. Table Details / Service Modal -->
<div id="tableDetailsModal" class="modal-backdrop" style="display: none;">
    <div class="modal-card" style="max-width: 480px;">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span id="modalTableIcon" class="modal-icon-badge">
                    <i class="fa-solid fa-chair"></i>
                </span>
                <div>
                    <h3 id="modalTableTitle" style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit';"></h3>
                    <div id="modalTableSubtitle" style="font-size: 0.82rem; color: var(--text-muted);"></div>
                </div>
            </div>
            <button type="button" onclick="closeTableDetailsModal()" class="btn-close-modal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body" id="modalTableBody">
            <!-- Dynamic Injection -->
        </div>

        <div class="modal-footer" style="justify-content: space-between;">
            <div id="modalTableSecondaryActions" style="display: flex; gap: 0.45rem;">
                <!-- Edit & Delete Buttons -->
            </div>
            <div style="display: flex; gap: 0.5rem;" id="modalTablePrimaryActions">
                <button type="button" onclick="closeTableDetailsModal()" class="btn btn-secondary">
                    {{ __('Close') }}
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 2. Add New Table Modal -->
<div id="addTableModal" class="modal-backdrop" style="display: none;">
    <div class="modal-card" style="max-width: 460px;">
        <div class="modal-header">
            <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit';">
                {{ __('Add New Table') }}
            </h3>
            <button type="button" onclick="closeAddTableModal()" class="btn-close-modal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
            <div>
                <label class="form-label">{{ __('Table Number') }} *</label>
                <input type="text" id="newTableNumber" placeholder="{{ __('e.g. 14 or VIP-2') }}" class="form-control" required>
            </div>

            <div>
                <label class="form-label">{{ __('Display Name / Label') }}</label>
                <input type="text" id="newTableName" placeholder="{{ __('e.g. Window Booth') }}" class="form-control">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div>
                    <label class="form-label">{{ __('Capacity') }} ({{ __('seats') }}) *</label>
                    <input type="number" id="newTableCapacity" value="4" min="1" max="50" class="form-control" required>
                </div>
                <div>
                    <label class="form-label">{{ __('Min Capacity') }} *</label>
                    <input type="number" id="newTableMinCapacity" value="1" min="1" max="50" class="form-control" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div>
                    <label class="form-label">{{ __('Hall / Section') }} *</label>
                    <select id="newTableHall" class="form-control">
                        @foreach($halls as $hall)
                            <option value="{{ $hall['name'] }}">{{ $hall['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">{{ __('Shape') }} *</label>
                    <select id="newTableShape" class="form-control">
                        <option value="square">{{ __('Square') }}</option>
                        <option value="round">{{ __('Round') }}</option>
                        <option value="rectangle">{{ __('Rectangle') }}</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" onclick="closeAddTableModal()" class="btn btn-secondary">{{ __('Cancel') }}</button>
            <button type="button" onclick="submitCreateNewTable()" id="btnSubmitNewTable" class="btn btn-primary" style="font-weight: 700;">
                <i class="fa-solid fa-plus"></i> {{ __('Create Table') }}
            </button>
        </div>
    </div>
</div>

<!-- 3. Edit Table Modal -->
<div id="editTableModal" class="modal-backdrop" style="display: none;">
    <div class="modal-card" style="max-width: 460px;">
        <div class="modal-header">
            <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit';">
                {{ __('Edit Table') }}
            </h3>
            <button type="button" onclick="closeEditTableModal()" class="btn-close-modal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <input type="hidden" id="editTableId">

        <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
            <div>
                <label class="form-label">{{ __('Table Number') }} *</label>
                <input type="text" id="editTableNumber" class="form-control" required>
            </div>

            <div>
                <label class="form-label">{{ __('Display Name / Label') }}</label>
                <input type="text" id="editTableName" class="form-control">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div>
                    <label class="form-label">{{ __('Capacity') }} ({{ __('seats') }}) *</label>
                    <input type="number" id="editTableCapacity" min="1" max="50" class="form-control" required>
                </div>
                <div>
                    <label class="form-label">{{ __('Min Capacity') }} *</label>
                    <input type="number" id="editTableMinCapacity" min="1" max="50" class="form-control" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div>
                    <label class="form-label">{{ __('Hall / Section') }} *</label>
                    <select id="editTableHall" class="form-control">
                        @foreach($halls as $hall)
                            <option value="{{ $hall['name'] }}">{{ $hall['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">{{ __('Shape') }} *</label>
                    <select id="editTableShape" class="form-control">
                        <option value="square">{{ __('Square') }}</option>
                        <option value="round">{{ __('Round') }}</option>
                        <option value="rectangle">{{ __('Rectangle') }}</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; border-radius: 12px; background: var(--bg-body); border: 1px solid var(--border-color);">
                <span style="font-size: 0.88rem; font-weight: 700; color: var(--text-main);">{{ __('Active for Booking') }}</span>
                <input type="checkbox" id="editTableIsActive" style="width: 18px; height: 18px; accent-color: var(--primary);">
            </div>
        </div>

        <div class="modal-footer" style="justify-content: space-between;">
            <button type="button" onclick="confirmDeleteCurrentTable()" class="btn btn-secondary" style="color: #ef4444;">
                <i class="fa-solid fa-trash-can"></i> {{ __('Delete') }}
            </button>
            <div style="display: flex; gap: 0.5rem;">
                <button type="button" onclick="closeEditTableModal()" class="btn btn-secondary">{{ __('Cancel') }}</button>
                <button type="button" onclick="submitUpdateTable()" id="btnSubmitEditTable" class="btn btn-primary" style="font-weight: 700;">
                    {{ __('Save Changes') }}
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 4. Manage Halls Modal -->
<div id="hallsModal" class="modal-backdrop" style="display: none;">
    <div class="modal-card" style="max-width: 520px;">
        <div class="modal-header">
            <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit';">
                {{ __('Manage Halls & Dining Areas') }}
            </h3>
            <button type="button" onclick="closeHallsModal()" class="btn-close-modal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body">
            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0 0 1rem 0;">
                {{ __('Add, rename, or reorder restaurant halls and assign unique identification colors.') }}
            </p>

            <div id="hallsListContainer" style="display: flex; flex-direction: column; gap: 0.65rem; margin-bottom: 1.25rem;">
                <!-- Injected by JS -->
            </div>

            <!-- Add New Hall Input Group -->
            <div style="background: var(--bg-body); border: 1px dashed var(--border-color); border-radius: 14px; padding: 0.85rem; display: flex; gap: 0.6rem; align-items: center;">
                <input type="color" id="newHallColorInput" value="#3b82f6" style="width: 38px; height: 38px; border: none; border-radius: 8px; cursor: pointer; background: transparent; padding: 0;">
                <input type="text" id="newHallNameInput" placeholder="{{ __('New Hall Name (e.g. Roof Bar, Patio)') }}" class="form-control" style="flex: 1;">
                <button type="button" onclick="addNewHallToManageList()" class="btn btn-secondary" style="font-weight: 700;">
                    <i class="fa-solid fa-plus"></i> {{ __('Add') }}
                </button>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" onclick="closeHallsModal()" class="btn btn-secondary">{{ __('Cancel') }}</button>
            <button type="button" onclick="submitSaveHalls()" id="btnSubmitHalls" class="btn btn-primary" style="font-weight: 700;">
                {{ __('Save Halls') }}
            </button>
        </div>
    </div>
</div>

<!-- 5. Combine Tables Modal -->
<div id="combineTablesModal" class="modal-backdrop" style="display: none;">
    <div class="modal-card" style="max-width: 480px;">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 0.6rem;">
                <span style="width: 36px; height: 36px; border-radius: 10px; background: rgba(147, 51, 234, 0.15); color: #9333ea; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                    <i class="fa-solid fa-link"></i>
                </span>
                <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit';">
                    {{ __('Combine Tables for Large Parties') }}
                </h3>
            </div>
            <button type="button" onclick="closeCombineTablesModal()" class="btn-close-modal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">
                {{ __('Temporarily join two tables to accommodate large bookings (e.g. 10+ guests). The reservation system will automatically calculate combined capacity and prevent double-booking.') }}
            </p>

            <div>
                <label class="form-label">{{ __('First Table') }} *</label>
                <select id="combineTable1" class="form-control" onchange="updateCombinePreview()">
                    <!-- Populated by JS -->
                </select>
            </div>

            <div>
                <label class="form-label">{{ __('Second Table to Join With') }} *</label>
                <select id="combineTable2" class="form-control" onchange="updateCombinePreview()">
                    <!-- Populated by JS -->
                </select>
            </div>

            <!-- Preview Card -->
            <div id="combinePreviewCard" style="background: rgba(147, 51, 234, 0.08); border: 1px solid rgba(147, 51, 234, 0.25); border-radius: 14px; padding: 1rem; text-align: center;">
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">
                    {{ __('Combined Capacity Preview') }}
                </div>
                <div id="combinePreviewText" style="font-size: 1.35rem; font-weight: 800; color: #9333ea; font-family: 'Outfit'; margin-top: 0.25rem;">
                    —
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" onclick="closeCombineTablesModal()" class="btn btn-secondary">{{ __('Cancel') }}</button>
            <button type="button" onclick="submitCombineTables()" id="btnSubmitCombine" class="btn btn-primary" style="font-weight: 700; background: #9333ea; border-color: #9333ea;">
                <i class="fa-solid fa-link"></i> {{ __('Combine Tables') }}
            </button>
        </div>
    </div>
</div>

<style>
/* Mode Switcher Buttons */
.mode-btn {
    border: none;
    background: transparent;
    color: var(--text-muted);
    font-size: 0.82rem;
    font-weight: 700;
    padding: 0.45rem 0.85rem;
    border-radius: 10px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    transition: all 0.2s ease;
}
.mode-btn:hover {
    color: var(--text-main);
}
.mode-btn.active {
    background: var(--primary);
    color: #fff;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
}

/* Stat Pills Card */
.stat-pill-card {
    padding: 0.9rem 1.15rem;
    display: flex;
    align-items: center;
    gap: 0.9rem;
    border-radius: 16px;
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.stat-pill-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-card);
}
.stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}
.stat-title {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.stat-val {
    font-size: 1.45rem;
    font-weight: 800;
    font-family: 'Outfit', sans-serif;
    line-height: 1.1;
    margin-top: 0.15rem;
}

/* Hall Filter Tabs */
.hall-tab-btn {
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-muted);
    font-size: 0.85rem;
    font-weight: 700;
    padding: 0.45rem 0.95rem;
    border-radius: 12px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    transition: all 0.2s ease;
}
.hall-tab-btn:hover {
    color: var(--primary);
    border-color: var(--primary);
}
.hall-tab-btn.active {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

/* Zoom Controls */
.zoom-btn {
    border: none;
    background: transparent;
    color: var(--text-muted);
    width: 26px;
    height: 26px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
}
.zoom-btn:hover {
    background: rgba(0,0,0,0.06);
    color: var(--text-main);
}

/* Table Node Rendering */
.floor-table-node {
    position: absolute;
    width: 120px;
    height: 120px;
    background: var(--bg-card);
    border: 2.5px solid var(--border-color);
    border-radius: 18px;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    box-shadow: 0 6px 18px rgba(0,0,0,0.08);
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.2s ease;
    user-select: none;
    z-index: 10;
}
.floor-table-node:hover {
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 10px 28px rgba(0,0,0,0.16);
    z-index: 20;
}

/* Shapes */
.floor-table-node.square {
    border-radius: 18px;
}
.floor-table-node.round {
    border-radius: 50%;
}
.floor-table-node.rectangle {
    width: 180px;
    height: 110px;
    border-radius: 16px;
}

/* Table Chairs Layer */
.table-chairs-layer {
    position: absolute;
    inset: -9px;
    pointer-events: none;
}
.chair {
    position: absolute;
    width: 24px;
    height: 8px;
    border-radius: 4px;
    background: var(--border-color);
    opacity: 0.85;
}
.chair-top {
    top: 0;
    left: 50%;
    transform: translateX(-50%);
}
.chair-bottom {
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
}
.chair-left {
    top: 50%;
    left: 0;
    transform: translateY(-50%) rotate(90deg);
}
.chair-right {
    top: 50%;
    right: 0;
    transform: translateY(-50%) rotate(90deg);
}
.chair-top-2 {
    top: 0;
    left: 28%;
    transform: translateX(-50%);
}
.chair-bottom-2 {
    bottom: 0;
    left: 72%;
    transform: translateX(-50%);
}

/* Operational States */
/* 1. Available */
.floor-table-node.status-available {
    border-color: #10b981;
}
.floor-table-node.status-available .table-inner {
    color: #10b981;
}
.floor-table-node.status-available .table-status-label {
    font-size: 0.68rem;
    font-weight: 800;
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
    margin-top: 0.25rem;
}

/* 2. Occupied (Order or Seated) */
.floor-table-node.status-occupied {
    border-color: #ef4444;
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.08), var(--bg-card));
    box-shadow: 0 6px 20px rgba(239, 68, 68, 0.2);
}
.floor-table-node.status-occupied .table-number {
    color: #ef4444;
}

/* 3. Reserved */
.floor-table-node.status-reserved {
    border-color: #a855f7;
    background: linear-gradient(135deg, rgba(168, 85, 247, 0.08), var(--bg-card));
    box-shadow: 0 6px 20px rgba(168, 85, 247, 0.2);
}
.floor-table-node.status-reserved .table-number {
    color: #a855f7;
}

/* 4. Combined / Merged */
.floor-table-node.is-merged {
    border-style: dashed;
}
.table-merged-badge {
    position: absolute;
    bottom: -8px;
    right: -8px;
    background: #9333ea;
    color: #fff;
    border-radius: 9999px;
    padding: 0.15rem 0.45rem;
    font-size: 0.68rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    box-shadow: 0 2px 8px rgba(147, 51, 234, 0.4);
    z-index: 15;
}

/* 5. Waiter Call Pulse */
.floor-table-node.has-call {
    border-color: #f59e0b !important;
    animation: tablePulse 1.5s infinite;
}
@keyframes tablePulse {
    0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.6); }
    70% { box-shadow: 0 0 0 12px rgba(245, 158, 11, 0); }
    100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
}
.table-call-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #f59e0b;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    box-shadow: 0 4px 10px rgba(245, 158, 11, 0.4);
    z-index: 15;
    animation: bounce 1s infinite alternate;
}

/* Table Interior Content */
.table-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.45rem;
    width: 100%;
}
.table-number {
    font-family: 'Outfit', sans-serif;
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--text-main);
    line-height: 1.1;
}
.table-capacity {
    font-size: 0.7rem;
    color: var(--text-muted);
    font-weight: 600;
    margin-top: 0.1rem;
}
.table-order-mini, .table-reservation-mini {
    margin-top: 0.3rem;
    font-size: 0.68rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    line-height: 1.25;
}
.table-order-num, .table-res-time {
    font-weight: 700;
    color: var(--text-main);
}
.table-order-amt {
    font-weight: 800;
    color: #10b981;
}
.table-order-time {
    color: #ef4444;
    font-weight: 700;
    font-size: 0.65rem;
}
.table-res-name {
    font-weight: 700;
    color: #a855f7;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100px;
}
.table-res-guests {
    font-size: 0.62rem;
    color: var(--text-muted);
}
.table-guest-name {
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--text-main);
}

/* Modals Architecture */
.modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.65);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(6px);
    padding: 1rem;
}
.modal-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    width: 100%;
    box-shadow: 0 25px 50px rgba(0,0,0,0.35);
    overflow: hidden;
    animation: modalPop 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes modalPop {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}
.modal-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-body {
    padding: 1.25rem 1.5rem;
    max-height: 75vh;
    overflow-y: auto;
}
.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
    background: var(--bg-body);
    display: flex;
    gap: 0.6rem;
    justify-content: flex-end;
}
.modal-icon-badge {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}
.btn-close-modal {
    background: none;
    border: none;
    font-size: 1.25rem;
    color: var(--text-muted);
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 6px;
}
.btn-close-modal:hover {
    color: var(--text-main);
}

@keyframes livePulse {
    0% { transform: scale(0.95); opacity: 0.8; }
    50% { transform: scale(1.15); opacity: 1; }
    100% { transform: scale(0.95); opacity: 0.8; }
}
</style>

<script>
// Master Data
let floorTables = @json($tables);
let floorHalls = @json($halls);
let currentMode = 'service'; // 'service' or 'edit'
let isDragging = false;
let currentDragNode = null;
let dragOffsetX = 0;
let dragOffsetY = 0;
let hasDragMoved = false;
let currentZoom = 1.0;
let snapGrid = true;
let activeHallFilter = 'all';
let activeStatusFilter = 'all';
let activeSelectedTable = null;

// ================= MODE SWITCHING =================
function setMode(mode) {
    currentMode = mode;
    document.getElementById('btnModeService').classList.toggle('active', mode === 'service');
    document.getElementById('btnModeEdit').classList.toggle('active', mode === 'edit');

    document.querySelectorAll('.floor-table-node').forEach(node => {
        node.style.cursor = mode === 'edit' ? 'grab' : 'pointer';
    });
}

// ================= HALLS & STATUS FILTERS =================
function filterHall(hallId) {
    activeHallFilter = hallId;
    document.querySelectorAll('.hall-tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.hall === hallId);
    });
    applyNodeVisibility();
}

function filterStatus(status) {
    activeStatusFilter = status;
    applyNodeVisibility();
}

function applyNodeVisibility() {
    document.querySelectorAll('.floor-table-node').forEach(node => {
        const tableHall = node.dataset.hall;
        const tableStatus = node.dataset.status;
        const hasCall = node.classList.contains('has-call');

        let matchHall = (activeHallFilter === 'all' || tableHall === activeHallFilter);
        let matchStatus = true;

        if (activeStatusFilter === 'call') {
            matchStatus = hasCall;
        } else if (activeStatusFilter !== 'all') {
            matchStatus = (tableStatus === activeStatusFilter);
        }

        node.style.display = (matchHall && matchStatus) ? 'flex' : 'none';
    });
}

// ================= ZOOM & CANVAS =================
function adjustZoom(delta) {
    currentZoom = Math.min(1.8, Math.max(0.5, currentZoom + delta));
    applyZoom();
}

function resetZoom() {
    currentZoom = 1.0;
    applyZoom();
}

function applyZoom() {
    const canvas = document.getElementById('floorCanvas');
    canvas.style.transform = `scale(${currentZoom})`;
    document.getElementById('zoomLevelText').textContent = Math.round(currentZoom * 100) + '%';
}

function toggleSnap() {
    snapGrid = !snapGrid;
    document.getElementById('snapLabel').textContent = snapGrid ? '{{ __('Snap: ON') }}' : '{{ __('Snap: OFF') }}';
}

// ================= DRAG AND DROP =================
function initDragAndDrop() {
    const canvas = document.getElementById('floorCanvas');

    document.querySelectorAll('.floor-table-node').forEach(node => {
        node.onmousedown = (e) => {
            if (e.button !== 0) return;
            if (currentMode !== 'edit') return;

            isDragging = true;
            hasDragMoved = false;
            currentDragNode = node;
            const rect = node.getBoundingClientRect();
            dragOffsetX = (e.clientX - rect.left) / currentZoom;
            dragOffsetY = (e.clientY - rect.top) / currentZoom;
            node.style.cursor = 'grabbing';
            node.style.zIndex = 999;
        };
    });

    window.onmousemove = (e) => {
        if (!isDragging || !currentDragNode) return;
        hasDragMoved = true;
        const canvasRect = canvas.getBoundingClientRect();

        let newX = (e.clientX - canvasRect.left) / currentZoom - dragOffsetX;
        let newY = (e.clientY - canvasRect.top) / currentZoom - dragOffsetY;

        // Apply 20px grid snapping
        if (snapGrid) {
            newX = Math.round(newX / 20) * 20;
            newY = Math.round(newY / 20) * 20;
        }

        // Clamp inside canvas bounds
        newX = Math.max(10, Math.min(canvas.offsetWidth - currentDragNode.offsetWidth - 10, newX));
        newY = Math.max(10, Math.min(canvas.offsetHeight - currentDragNode.offsetHeight - 10, newY));

        currentDragNode.style.left = newX + 'px';
        currentDragNode.style.top = newY + 'px';

        const tableId = currentDragNode.dataset.id;
        const table = floorTables.find(t => String(t.id) === String(tableId));
        if (table) {
            table.x = Math.round(newX);
            table.y = Math.round(newY);
        }
    };

    window.onmouseup = () => {
        if (isDragging && currentDragNode) {
            currentDragNode.style.zIndex = 10;
            currentDragNode.style.cursor = currentMode === 'edit' ? 'grab' : 'pointer';
        }
        isDragging = false;
        currentDragNode = null;
    };
}

// ================= TABLE INTERACTION =================
function handleTableClick(table) {
    if (hasDragMoved && currentMode === 'edit') return;
    openTableDetails(table);
}

function openTableDetails(table) {
    activeSelectedTable = table;
    const modal = document.getElementById('tableDetailsModal');
    const title = document.getElementById('modalTableTitle');
    const subtitle = document.getElementById('modalTableSubtitle');
    const body = document.getElementById('modalTableBody');
    const icon = document.getElementById('modalTableIcon');
    const secActions = document.getElementById('modalTableSecondaryActions');
    const priActions = document.getElementById('modalTablePrimaryActions');

    title.textContent = `{{ __('Table') }} #${table.number} ${table.name ? '(' + table.name + ')' : ''}`;

    const hall = floorHalls.find(h => h.id === table.hall_id) || { name: table.section || '{{ __('Hall') }}' };
    const shapeLabel = table.shape === 'round' ? '{{ __('Round') }}' : (table.shape === 'rectangle' ? '{{ __('Rectangle') }}' : '{{ __('Square') }}');

    let mergedInfo = '';
    if (table.is_merged) {
        mergedInfo = `
            <div style="background: rgba(147, 51, 234, 0.1); border: 1px solid rgba(147, 51, 234, 0.3); border-radius: 12px; padding: 0.75rem 1rem; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 0.5rem; color: #9333ea; font-size: 0.85rem; font-weight: 700;">
                    <i class="fa-solid fa-link"></i>
                    <span>{{ __('Combined with Table') }} #${table.merged_with_number} ({{ __('Total') }} ${table.effective_capacity} {{ __('seats') }})</span>
                </div>
                <button type="button" onclick="submitUncombineTable(${table.id})" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.6rem; color: #ef4444;">
                    {{ __('Unlink') }}
                </button>
            </div>
        `;
    }

    let callNotice = '';
    if (table.has_waiter_call && table.waiter_call) {
        callNotice = `
            <div style="background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 12px; padding: 0.75rem 1rem; margin-bottom: 1rem; color: #f59e0b; display: flex; align-items: center; gap: 0.65rem;">
                <i class="fa-solid fa-bell fa-bounce" style="font-size: 1.1rem;"></i>
                <div>
                    <strong>{{ __('Active Waiter Call') }}:</strong> ${table.waiter_call.type_label} (${table.waiter_call.created_at_time})
                </div>
            </div>
        `;
    }

    if (table.status === 'occupied' && table.order) {
        icon.style.background = 'rgba(239, 68, 68, 0.15)';
        icon.style.color = '#ef4444';
        subtitle.innerHTML = `<span style="color: #ef4444; font-weight: 700;">{{ __('Occupied with Order') }}</span> &bull; ${hall.name} &bull; ${shapeLabel}`;

        body.innerHTML = `
            ${callNotice}
            ${mergedInfo}
            <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem; margin-bottom: 1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <span style="font-size: 0.85rem; color: var(--text-muted);">{{ __('Order Number') }}</span>
                    <strong style="color: var(--text-main); font-family: 'Outfit';">${table.order.order_number}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <span style="font-size: 0.85rem; color: var(--text-muted);">{{ __('Total Amount') }}</span>
                    <strong style="color: #10b981; font-size: 1.1rem; font-family: 'Outfit';">${Number(table.order.total_amount).toLocaleString()} {{ $vendor->currency }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <span style="font-size: 0.85rem; color: var(--text-muted);">{{ __('Duration') }}</span>
                    <span style="color: #ef4444; font-weight: 700; font-size: 0.88rem;">⏱️ ${table.order.elapsed_minutes} {{ __('min') }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.85rem; color: var(--text-muted);">{{ __('Guest Name') }}</span>
                    <span style="font-weight: 700; color: var(--text-main);">${table.order.customer_name}</span>
                </div>
            </div>
        `;

        priActions.innerHTML = `
            <a href="{{ route('admin.orders.index') }}" class="btn btn-primary" style="font-size: 0.85rem; text-decoration: none;">
                <i class="fa-solid fa-bell-concierge"></i> {{ __('View in Orders') }}
            </a>
            <button type="button" onclick="closeTableDetailsModal()" class="btn btn-secondary">{{ __('Close') }}</button>
        `;
    } else if (table.status === 'occupied' && table.reservation && table.reservation.status === 'seated') {
        icon.style.background = 'rgba(239, 68, 68, 0.15)';
        icon.style.color = '#ef4444';
        subtitle.innerHTML = `<span style="color: #ef4444; font-weight: 700;">{{ __('Seated Guest') }}</span> &bull; ${hall.name}`;

        body.innerHTML = `
            ${callNotice}
            ${mergedInfo}
            <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem; margin-bottom: 1rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span style="font-size: 0.85rem; color: var(--text-muted);">{{ __('Customer Name') }}</span>
                    <strong style="color: var(--text-main);">${table.reservation.customer_name}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span style="font-size: 0.85rem; color: var(--text-muted);">{{ __('Phone') }}</span>
                    <span>${table.reservation.customer_phone || '—'}</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="font-size: 0.85rem; color: var(--text-muted);">{{ __('Seated Time') }}</span>
                    <strong style="color: #ef4444;">${table.reservation.time}</strong>
                </div>
            </div>
        `;

        priActions.innerHTML = `
            <a href="{{ route('admin.reservations.index') }}" class="btn btn-primary" style="font-size: 0.85rem; text-decoration: none;">
                <i class="fa-solid fa-calendar-check"></i> {{ __('Reservations') }}
            </a>
            <button type="button" onclick="closeTableDetailsModal()" class="btn btn-secondary">{{ __('Close') }}</button>
        `;
    } else if (table.status === 'reserved' && table.reservation) {
        icon.style.background = 'rgba(168, 85, 247, 0.15)';
        icon.style.color = '#a855f7';
        subtitle.innerHTML = `<span style="color: #a855f7; font-weight: 700;">{{ __('Reserved Today') }}</span> &bull; ${hall.name}`;

        body.innerHTML = `
            ${callNotice}
            ${mergedInfo}
            <div style="background: rgba(168, 85, 247, 0.08); border: 1px solid rgba(168, 85, 247, 0.25); border-radius: 14px; padding: 1rem; margin-bottom: 1rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span style="font-size: 0.85rem; color: var(--text-muted);">{{ __('Booking Time') }}</span>
                    <strong style="color: #a855f7; font-size: 1.05rem;"><i class="fa-regular fa-clock"></i> ${table.reservation.time}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span style="font-size: 0.85rem; color: var(--text-muted);">{{ __('Guest Name') }}</span>
                    <strong style="color: var(--text-main);">${table.reservation.customer_name}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span style="font-size: 0.85rem; color: var(--text-muted);">{{ __('Party Size') }}</span>
                    <strong>${table.reservation.guest_count} {{ __('guests') }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="font-size: 0.85rem; color: var(--text-muted);">{{ __('Phone') }}</span>
                    <span>${table.reservation.customer_phone || '—'}</span>
                </div>
            </div>
        `;

        priActions.innerHTML = `
            <a href="{{ route('admin.reservations.index') }}" class="btn btn-primary" style="font-size: 0.85rem; text-decoration: none;">
                <i class="fa-solid fa-calendar-check"></i> {{ __('View Reservation') }}
            </a>
            <button type="button" onclick="closeTableDetailsModal()" class="btn btn-secondary">{{ __('Close') }}</button>
        `;
    } else {
        // Available
        icon.style.background = 'rgba(16, 185, 129, 0.15)';
        icon.style.color = '#10b981';
        subtitle.innerHTML = `<span style="color: #10b981; font-weight: 700;">{{ __('Available') }}</span> &bull; ${hall.name} &bull; ${shapeLabel}`;

        body.innerHTML = `
            ${callNotice}
            ${mergedInfo}
            <div style="text-align: center; padding: 1.5rem 0;">
                <div style="font-size: 2.2rem; margin-bottom: 0.5rem;">🟢</div>
                <h4 style="margin: 0; color: var(--text-main); font-weight: 800;">{{ __('Table is Free & Ready') }}</h4>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0.35rem 0 0 0;">
                    {{ __('Capacity') }}: ${table.effective_capacity} {{ __('seats') }} &bull; ${hall.name}
                </p>
            </div>
        `;

        priActions.innerHTML = `
            <button type="button" onclick="closeTableDetailsModal()" class="btn btn-secondary">{{ __('Close') }}</button>
        `;
    }

    // Secondary Actions (Edit table, Combine)
    secActions.innerHTML = `
        <button type="button" onclick="openEditTableModal(${table.id})" class="btn btn-secondary" style="font-size: 0.8rem; font-weight: 700;">
            <i class="fa-solid fa-pen-to-square"></i> {{ __('Edit') }}
        </button>
    `;

    modal.style.display = 'flex';
}

function closeTableDetailsModal() {
    document.getElementById('tableDetailsModal').style.display = 'none';
}

// ================= ADD TABLE =================
function openAddTableModal() {
    document.getElementById('addTableModal').style.display = 'flex';
}

function closeAddTableModal() {
    document.getElementById('addTableModal').style.display = 'none';
}

async function submitCreateNewTable() {
    const num = document.getElementById('newTableNumber').value.trim();
    if (!num) {
        alert('{{ __('Please enter a table number.') }}');
        return;
    }

    const name = document.getElementById('newTableName').value.trim();
    const capacity = parseInt(document.getElementById('newTableCapacity').value) || 4;
    const minCapacity = parseInt(document.getElementById('newTableMinCapacity').value) || 1;
    const section = document.getElementById('newTableHall').value;
    const shape = document.getElementById('newTableShape').value;

    const btn = document.getElementById('btnSubmitNewTable');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> {{ __('Creating...') }}';

    try {
        const res = await fetch('{{ route("admin.floor_plan.tables.store") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                table_number: num,
                name: name,
                capacity: capacity,
                min_capacity: minCapacity,
                section: section,
                shape: shape,
                x: 60,
                y: 60,
                is_active: true,
            }),
        });

        const data = await res.json();
        if (data.status === 'success') {
            closeAddTableModal();
            window.location.reload();
        } else {
            alert(data.message || '{{ __('Error creating table.') }}');
        }
    } catch (e) {
        alert('{{ __('Network error.') }}');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-plus"></i> {{ __('Create Table') }}';
    }
}

// ================= EDIT TABLE =================
function openEditTableModal(tableId) {
    closeTableDetailsModal();
    const table = floorTables.find(t => Number(t.id) === Number(tableId));
    if (!table) return;

    document.getElementById('editTableId').value = table.id;
    document.getElementById('editTableNumber').value = table.number;
    document.getElementById('editTableName').value = table.name || '';
    document.getElementById('editTableCapacity').value = table.capacity;
    document.getElementById('editTableMinCapacity').value = table.min_capacity || 1;
    document.getElementById('editTableHall').value = table.section || (floorHalls[0]?.name ?? '{{ __('Main Hall') }}');
    document.getElementById('editTableShape').value = table.shape || 'square';
    document.getElementById('editTableIsActive').checked = Boolean(table.is_active);

    document.getElementById('editTableModal').style.display = 'flex';
}

function closeEditTableModal() {
    document.getElementById('editTableModal').style.display = 'none';
}

async function submitUpdateTable() {
    const tableId = document.getElementById('editTableId').value;
    const num = document.getElementById('editTableNumber').value.trim();
    if (!num) {
        alert('{{ __('Please enter a table number.') }}');
        return;
    }

    const name = document.getElementById('editTableName').value.trim();
    const capacity = parseInt(document.getElementById('editTableCapacity').value) || 4;
    const minCapacity = parseInt(document.getElementById('editTableMinCapacity').value) || 1;
    const section = document.getElementById('editTableHall').value;
    const shape = document.getElementById('editTableShape').value;
    const isActive = document.getElementById('editTableIsActive').checked;

    const btn = document.getElementById('btnSubmitEditTable');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> {{ __('Saving...') }}';

    try {
        const res = await fetch(`{{ url('/admin/floor-plan/tables') }}/${tableId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                table_number: num,
                name: name,
                capacity: capacity,
                min_capacity: minCapacity,
                section: section,
                shape: shape,
                is_active: isActive,
            }),
        });

        const data = await res.json();
        if (data.status === 'success') {
            closeEditTableModal();
            window.location.reload();
        } else {
            alert(data.message || '{{ __('Error updating table.') }}');
        }
    } catch (e) {
        alert('{{ __('Network error.') }}');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '{{ __('Save Changes') }}';
    }
}

async function confirmDeleteCurrentTable() {
    const tableId = document.getElementById('editTableId').value;
    if (!confirm('{{ __('Are you sure you want to delete this table?') }}')) return;

    try {
        const res = await fetch(`{{ url('/admin/floor-plan/tables') }}/${tableId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
        });

        const data = await res.json();
        if (data.status === 'success') {
            closeEditTableModal();
            window.location.reload();
        } else {
            alert(data.message || '{{ __('Cannot delete table.') }}');
        }
    } catch (e) {
        alert('{{ __('Network error.') }}');
    }
}

// ================= COMBINE & UNCOMBINE TABLES =================
function openCombineTablesModal() {
    const sel1 = document.getElementById('combineTable1');
    const sel2 = document.getElementById('combineTable2');
    sel1.innerHTML = '';
    sel2.innerHTML = '';

    floorTables.forEach((t, i) => {
        const opt1 = document.createElement('option');
        opt1.value = t.id;
        opt1.textContent = `#${t.number} (${t.capacity} {{ __('seats') }} - ${t.section})`;
        sel1.appendChild(opt1);

        const opt2 = document.createElement('option');
        opt2.value = t.id;
        opt2.textContent = `#${t.number} (${t.capacity} {{ __('seats') }} - ${t.section})`;
        if (i === 1) opt2.selected = true;
        sel2.appendChild(opt2);
    });

    updateCombinePreview();
    document.getElementById('combineTablesModal').style.display = 'flex';
}

function closeCombineTablesModal() {
    document.getElementById('combineTablesModal').style.display = 'none';
}

function updateCombinePreview() {
    const id1 = document.getElementById('combineTable1').value;
    const id2 = document.getElementById('combineTable2').value;

    const t1 = floorTables.find(t => String(t.id) === String(id1));
    const t2 = floorTables.find(t => String(t.id) === String(id2));

    if (t1 && t2 && id1 !== id2) {
        const combined = t1.capacity + t2.capacity;
        document.getElementById('combinePreviewText').textContent = `#${t1.number} (${t1.capacity}) + #${t2.number} (${t2.capacity}) = ${combined} {{ __('seats') }}`;
    } else {
        document.getElementById('combinePreviewText').textContent = '{{ __('Please choose two different tables') }}';
    }
}

async function submitCombineTables() {
    const id1 = document.getElementById('combineTable1').value;
    const id2 = document.getElementById('combineTable2').value;

    if (id1 === id2) {
        alert('{{ __('Please select two different tables.') }}');
        return;
    }

    const btn = document.getElementById('btnSubmitCombine');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> {{ __('Combining...') }}';

    try {
        const res = await fetch('{{ route("admin.floor_plan.combine") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                table_id_1: parseInt(id1),
                table_id_2: parseInt(id2),
            }),
        });

        const data = await res.json();
        if (data.status === 'success') {
            closeCombineTablesModal();
            window.location.reload();
        } else {
            alert(data.message || '{{ __('Error combining tables.') }}');
        }
    } catch (e) {
        alert('{{ __('Network error.') }}');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-link"></i> {{ __('Combine Tables') }}';
    }
}

async function submitUncombineTable(tableId) {
    if (!confirm('{{ __('Separate these joined tables back into individual tables?') }}')) return;

    try {
        const res = await fetch(`{{ url('/admin/floor-plan/tables') }}/${tableId}/uncombine`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
        });

        const data = await res.json();
        if (data.status === 'success') {
            closeTableDetailsModal();
            window.location.reload();
        } else {
            alert(data.message || '{{ __('Error uncombining table.') }}');
        }
    } catch (e) {
        alert('{{ __('Network error.') }}');
    }
}

// ================= HALLS MANAGEMENT =================
let editableHalls = [];

function openHallsModal() {
    editableHalls = JSON.parse(JSON.stringify(floorHalls));
    renderHallsList();
    document.getElementById('hallsModal').style.display = 'flex';
}

function closeHallsModal() {
    document.getElementById('hallsModal').style.display = 'none';
}

function renderHallsList() {
    const container = document.getElementById('hallsListContainer');
    container.innerHTML = '';

    editableHalls.forEach((h, index) => {
        const row = document.createElement('div');
        row.style = 'display: flex; gap: 0.6rem; align-items: center; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.6rem 0.85rem;';
        row.innerHTML = `
            <input type="color" value="${h.color || '#3b82f6'}" onchange="updateHallColor(${index}, this.value)" style="width: 32px; height: 32px; border: none; border-radius: 6px; cursor: pointer; background: transparent; padding: 0;">
            <input type="text" value="${h.name}" oninput="updateHallName(${index}, this.value)" class="form-control" style="flex: 1; padding: 0.45rem 0.75rem; font-size: 0.88rem;">
            ${editableHalls.length > 1 ? `
                <button type="button" onclick="deleteHallFromList(${index})" style="background: none; border: none; color: #ef4444; cursor: pointer; padding: 0.35rem; font-size: 0.9rem;" title="{{ __('Delete Hall') }}">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            ` : ''}
        `;
        container.appendChild(row);
    });
}

function updateHallColor(index, color) {
    editableHalls[index].color = color;
}

function updateHallName(index, name) {
    editableHalls[index].name = name;
}

function deleteHallFromList(index) {
    if (editableHalls.length <= 1) {
        alert('{{ __('At least one hall must exist.') }}');
        return;
    }
    editableHalls.splice(index, 1);
    renderHallsList();
}

function addNewHallToManageList() {
    const input = document.getElementById('newHallNameInput');
    const colorInput = document.getElementById('newHallColorInput');
    const name = input.value.trim();
    if (!name) return;

    editableHalls.push({
        id: name.toLowerCase().replace(/[^a-z0-9]+/g, '-'),
        name: name,
        color: colorInput.value || '#3b82f6',
    });

    input.value = '';
    renderHallsList();
}

async function submitSaveHalls() {
    const btn = document.getElementById('btnSubmitHalls');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> {{ __('Saving...') }}';

    try {
        const res = await fetch('{{ route("admin.floor_plan.halls.save") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                halls: editableHalls,
            }),
        });

        const data = await res.json();
        if (data.status === 'success') {
            closeHallsModal();
            window.location.reload();
        } else {
            alert(data.message || '{{ __('Error saving halls.') }}');
        }
    } catch (e) {
        alert('{{ __('Network error.') }}');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '{{ __('Save Halls') }}';
    }
}

// ================= SAVE LAYOUT COORDINATES =================
async function saveFloorPlanChanges() {
    const btn = document.getElementById('btnSaveFloorPlan');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> {{ __('Saving...') }}';

    try {
        const res = await fetch('{{ route("admin.floor_plan.save") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                halls: floorHalls,
                tables: floorTables,
            }),
        });

        const data = await res.json();
        if (data.status === 'success' || data.success) {
            btn.innerHTML = '<i class="fa-solid fa-check"></i> {{ __('Saved!') }}';
            setTimeout(() => {
                btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> {{ __('Save Layout') }}';
                btn.disabled = false;
            }, 1800);
        } else {
            alert('{{ __('Error saving layout.') }}');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> {{ __('Save Layout') }}';
        }
    } catch (e) {
        alert('{{ __('Network error.') }}');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> {{ __('Save Layout') }}';
    }
}

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', () => {
    initDragAndDrop();
    applyZoom();
});
</script>
@endsection
