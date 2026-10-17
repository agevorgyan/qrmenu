@extends('layouts.app')

@section('title', __('Interactive Table Floor Plan') . ' - ' . $vendor->name)

@section('content')
<div style="max-width: 1350px; margin: 0 auto; width: 100%;">
    <!-- Top Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <span style="width: 42px; height: 42px; border-radius: 12px; background: rgba(59, 130, 246, 0.15); color: #3b82f6; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    <i class="fa-solid fa-map-location-dot"></i>
                </span>
                <div>
                    <h1 style="font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; margin: 0; color: var(--text-main); font-family: 'Outfit', sans-serif;">
                        {{ __('Interactive Table Floor Plan') }}
                    </h1>
                    <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0.2rem 0 0 0;">
                        {{ __('Real-time visual map of halls and tables') }} ({{ $location?->name ?? __('Main Branch') }})
                    </p>
                </div>
            </div>
        </div>

        <!-- Controls: Save Layout & Add Table -->
        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <button type="button" onclick="addNewTableModal()" class="btn btn-secondary" style="border-radius: 12px; font-weight: 600; font-size: 0.88rem; display: flex; align-items: center; gap: 0.45rem;">
                <i class="fa-solid fa-plus" style="color: var(--primary);"></i> <span>{{ __('Add Table') }}</span>
            </button>
            <button type="button" onclick="saveFloorPlanChanges()" id="btnSaveFloorPlan" class="btn btn-primary" style="border-radius: 12px; font-weight: 700; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.3);">
                <i class="fa-solid fa-floppy-disk"></i> <span>{{ __('Save Layout') }}</span>
            </button>
        </div>
    </div>

    <!-- Quick Stat Pills Bar -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <!-- Total Tables -->
        <div class="card" style="padding: 1rem 1.25rem; display: flex; align-items: center; gap: 1rem; border-radius: 16px;">
            <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(99, 102, 241, 0.12); color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                <i class="fa-solid fa-chair"></i>
            </div>
            <div>
                <div style="font-size: 0.78rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">{{ __('Total Tables') }}</div>
                <div style="font-size: 1.45rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit';">{{ $totalTables }}</div>
            </div>
        </div>

        <!-- Available Tables -->
        <div class="card" style="padding: 1rem 1.25rem; display: flex; align-items: center; gap: 1rem; border-radius: 16px;">
            <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(16, 185, 129, 0.12); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div>
                <div style="font-size: 0.78rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">{{ __('Available Tables') }}</div>
                <div style="font-size: 1.45rem; font-weight: 800; color: #10b981; font-family: 'Outfit';">{{ $availableCount }}</div>
            </div>
        </div>

        <!-- Occupied Tables -->
        <div class="card" style="padding: 1rem 1.25rem; display: flex; align-items: center; gap: 1rem; border-radius: 16px;">
            <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(239, 68, 68, 0.12); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                <i class="fa-solid fa-fire"></i>
            </div>
            <div>
                <div style="font-size: 0.78rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">{{ __('Occupied / Active') }}</div>
                <div style="font-size: 1.45rem; font-weight: 800; color: #ef4444; font-family: 'Outfit';">{{ $occupiedCount }}</div>
            </div>
        </div>

        <!-- Waiter Calls -->
        <div class="card" style="padding: 1rem 1.25rem; display: flex; align-items: center; gap: 1rem; border-radius: 16px;">
            <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                <i class="fa-solid fa-bell"></i>
            </div>
            <div>
                <div style="font-size: 0.78rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">{{ __('Waiter Calls') }}</div>
                <div style="font-size: 1.45rem; font-weight: 800; color: #f59e0b; font-family: 'Outfit';">{{ $waiterCallCount }}</div>
            </div>
        </div>
    </div>

    <!-- Halls Filter Tabs -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem;">
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
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

        <!-- Legend -->
        <div style="display: flex; gap: 1rem; align-items: center; font-size: 0.8rem; color: var(--text-muted); flex-wrap: wrap;">
            <span style="display: inline-flex; align-items: center; gap: 0.35rem;">
                <span style="width: 10px; height: 10px; border-radius: 50%; background: #10b981;"></span> {{ __('Available') }}
            </span>
            <span style="display: inline-flex; align-items: center; gap: 0.35rem;">
                <span style="width: 10px; height: 10px; border-radius: 50%; background: #ef4444;"></span> {{ __('Occupied (Order)') }}
            </span>
            <span style="display: inline-flex; align-items: center; gap: 0.35rem;">
                <span style="width: 10px; height: 10px; border-radius: 50%; background: #f59e0b;"></span> {{ __('Call / Bill') }}
            </span>
        </div>
    </div>

    <!-- Interactive Floor Canvas Card -->
    <div class="card floor-canvas-wrapper" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; min-height: 580px; position: relative; overflow-x: auto; box-shadow: var(--shadow-card);">
        <div id="floorCanvas" style="min-width: 900px; min-height: 520px; position: relative; background-image: radial-gradient(var(--border-color) 1px, transparent 1px); background-size: 24px 24px;">
            @foreach($tables as $table)
                @php
                    $isOccupied = ($table['status'] ?? '') === 'occupied';
                    $hasCall = !empty($table['has_waiter_call']);
                    $shape = $table['shape'] ?? 'square';
                    $hallId = $table['hall_id'] ?? 'main';
                    $order = $table['order'] ?? null;
                    $call = $table['waiter_call'] ?? null;
                    $x = (int) ($table['x'] ?? 50);
                    $y = (int) ($table['y'] ?? 50);
                @endphp

                <div class="floor-table-node {{ $shape }} {{ $isOccupied ? 'is-occupied' : 'is-available' }} {{ $hasCall ? 'has-call' : '' }}"
                     id="table-node-{{ $table['id'] }}"
                     data-id="{{ $table['id'] }}"
                     data-number="{{ $table['number'] }}"
                     data-hall="{{ $hallId }}"
                     data-shape="{{ $shape }}"
                     data-capacity="{{ $table['capacity'] ?? 4 }}"
                     data-status="{{ $table['status'] }}"
                     data-order='@json($order)'
                     data-call='@json($call)'
                     style="left: {{ $x }}px; top: {{ $y }}px;"
                     onclick="openTableDetails({{ json_encode($table) }})">

                    @if($hasCall)
                        <div class="table-call-badge" title="{{ __('Waiter Call') }}">
                            <i class="fa-solid fa-bell"></i>
                        </div>
                    @endif

                    <div class="table-inner">
                        <div class="table-number">#{{ $table['number'] }}</div>
                        <div class="table-capacity">
                            <i class="fa-solid fa-user-group"></i> {{ $table['capacity'] ?? 4 }}
                        </div>

                        @if($isOccupied && $order)
                            <div class="table-order-mini">
                                <div class="table-order-num">{{ $order['order_number'] }}</div>
                                <div class="table-order-amt">{{ number_format($order['total_amount']) }} {{ $vendor->currency }}</div>
                                <div class="table-order-time">⏱️ {{ $order['elapsed_minutes'] }}m</div>
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

<!-- Table Details / Actions Modal -->
<div id="tableDetailsModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(6px); padding: 1rem;">
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; max-width: 440px; width: 100%; box-shadow: 0 25px 50px rgba(0,0,0,0.3); overflow: hidden;">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <span id="modalTableIcon" style="width: 38px; height: 38px; border-radius: 10px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                    <i class="fa-solid fa-chair"></i>
                </span>
                <div>
                    <h3 id="modalTableTitle" style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit';">{{ __('Table') }} #</h3>
                    <div id="modalTableSubtitle" style="font-size: 0.8rem; color: var(--text-muted);">{{ __('Status') }}</div>
                </div>
            </div>
            <button type="button" onclick="closeTableDetailsModal()" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div style="padding: 1.25rem 1.5rem;" id="modalTableBody">
            <!-- Injected by JS -->
        </div>

        <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); background: var(--bg-body); display: flex; gap: 0.6rem; justify-content: flex-end;">
            <button type="button" onclick="closeTableDetailsModal()" class="btn btn-secondary" style="border-radius: 12px; font-size: 0.85rem;">
                {{ __('Close') }}
            </button>
            <div id="modalTableActionBtns" style="display: flex; gap: 0.5rem;">
                <!-- Actions injected by JS -->
            </div>
        </div>
    </div>
</div>

<!-- Add Table Modal -->
<div id="addTableModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(6px); padding: 1rem;">
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; max-width: 400px; width: 100%; box-shadow: 0 25px 50px rgba(0,0,0,0.3); overflow: hidden;">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit';">{{ __('Add New Table') }}</h3>
            <button type="button" onclick="closeAddTableModal()" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div style="padding: 1.25rem 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Table Number') }}</label>
                <input type="text" id="newTableNumber" placeholder="{{ __('e.g. 13 or VIP-2') }}" class="form-control" style="width: 100%; padding: 0.65rem 1rem; border-radius: 12px;">
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Hall') }}</label>
                <select id="newTableHall" class="form-control" style="width: 100%; padding: 0.65rem 1rem; border-radius: 12px;">
                    @foreach($halls as $hall)
                        <option value="{{ $hall['id'] }}">{{ $hall['name'] }}</option>
                    @endforeach
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Capacity') }}</label>
                    <input type="number" id="newTableCapacity" value="4" min="1" max="20" class="form-control" style="width: 100%; padding: 0.65rem 1rem; border-radius: 12px;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Shape') }}</label>
                    <select id="newTableShape" class="form-control" style="width: 100%; padding: 0.65rem 1rem; border-radius: 12px;">
                        <option value="square">{{ __('Square') }}</option>
                        <option value="round">{{ __('Round') }}</option>
                    </select>
                </div>
            </div>
        </div>

        <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); background: var(--bg-body); display: flex; gap: 0.6rem; justify-content: flex-end;">
            <button type="button" onclick="closeAddTableModal()" class="btn btn-secondary" style="border-radius: 12px;">{{ __('Cancel') }}</button>
            <button type="button" onclick="createNewTable()" class="btn btn-primary" style="border-radius: 12px; font-weight: 700;">{{ __('Add') }}</button>
        </div>
    </div>
</div>

<style>
.hall-tab-btn {
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-muted);
    font-size: 0.85rem;
    font-weight: 700;
    padding: 0.5rem 1rem;
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

.floor-table-node {
    position: absolute;
    width: 120px;
    height: 120px;
    background: var(--bg-card);
    border: 2px solid var(--border-color);
    border-radius: 16px;
    cursor: grab;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
    user-select: none;
    z-index: 10;
}
.floor-table-node:active {
    cursor: grabbing;
}
.floor-table-node.round {
    border-radius: 50%;
}
.floor-table-node:hover {
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
}

/* Status: Available */
.floor-table-node.is-available {
    border-color: #10b981;
}
.floor-table-node.is-available .table-inner {
    color: #10b981;
}
.floor-table-node.is-available .table-status-label {
    font-size: 0.68rem;
    font-weight: 800;
    background: rgba(16, 185, 129, 0.12);
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
    margin-top: 0.25rem;
}

/* Status: Occupied */
.floor-table-node.is-occupied {
    border-color: #ef4444;
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.06), var(--bg-card));
    box-shadow: 0 4px 16px rgba(239, 68, 68, 0.15);
}
.floor-table-node.is-occupied .table-number {
    color: #ef4444;
}

/* Status: Waiter Call */
.floor-table-node.has-call {
    border-color: #f59e0b !important;
    animation: tablePulse 1.5s infinite;
}
@keyframes tablePulse {
    0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.5); }
    70% { box-shadow: 0 0 0 10px rgba(245, 158, 11, 0); }
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
    animation: ring 1s infinite alternate;
}

.table-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.5rem;
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
    margin-top: 0.15rem;
}
.table-order-mini {
    margin-top: 0.35rem;
    font-size: 0.68rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    line-height: 1.25;
}
.table-order-num {
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
</style>

<script>
let floorTables = @json($tables);
let floorHalls = @json($halls);
let isDragging = false;
let currentDragNode = null;
let dragOffsetX = 0;
let dragOffsetY = 0;

function filterHall(hallId) {
    document.querySelectorAll('.hall-tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.hall === hallId);
    });

    document.querySelectorAll('.floor-table-node').forEach(node => {
        if (hallId === 'all' || node.dataset.hall === hallId) {
            node.style.display = 'flex';
        } else {
            node.style.display = 'none';
        }
    });
}

function initDragAndDrop() {
    const canvas = document.getElementById('floorCanvas');

    document.querySelectorAll('.floor-table-node').forEach(node => {
        node.addEventListener('mousedown', (e) => {
            if (e.button !== 0) return;
            isDragging = true;
            currentDragNode = node;
            const rect = node.getBoundingClientRect();
            dragOffsetX = e.clientX - rect.left;
            dragOffsetY = e.clientY - rect.top;
            node.style.zIndex = 99;
        });
    });

    window.addEventListener('mousemove', (e) => {
        if (!isDragging || !currentDragNode) return;
        const canvasRect = canvas.getBoundingClientRect();

        let newX = e.clientX - canvasRect.left - dragOffsetX;
        let newY = e.clientY - canvasRect.top - dragOffsetY;

        // Keep inside canvas bounds
        newX = Math.max(10, Math.min(canvasRect.width - 130, newX));
        newY = Math.max(10, Math.min(canvasRect.height - 130, newY));

        currentDragNode.style.left = newX + 'px';
        currentDragNode.style.top = newY + 'px';

        const tableId = currentDragNode.dataset.id;
        const table = floorTables.find(t => String(t.id) === String(tableId));
        if (table) {
            table.x = Math.round(newX);
            table.y = Math.round(newY);
        }
    });

    window.addEventListener('mouseup', () => {
        if (isDragging && currentDragNode) {
            currentDragNode.style.zIndex = 10;
        }
        isDragging = false;
        currentDragNode = null;
    });
}

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
        if (data.success) {
            alert(data.message || '{{ __('Floor plan layout saved successfully.') }}');
        } else {
            alert('{{ __('Error saving layout.') }}');
        }
    } catch (e) {
        alert('{{ __('Network error.') }}');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> {{ __('Save Layout') }}';
    }
}

function openTableDetails(table) {
    if (isDragging) return;

    const modal = document.getElementById('tableDetailsModal');
    const title = document.getElementById('modalTableTitle');
    const subtitle = document.getElementById('modalTableSubtitle');
    const body = document.getElementById('modalTableBody');
    const icon = document.getElementById('modalTableIcon');
    const actionBtns = document.getElementById('modalTableActionBtns');

    title.textContent = `{{ __('Table') }} #${table.number}`;

    const hall = floorHalls.find(h => h.id === table.hall_id);
    const hallName = hall ? hall.name : '{{ __('Hall') }}';

    if (table.status === 'occupied' && table.order) {
        icon.style.background = 'rgba(239, 68, 68, 0.15)';
        icon.style.color = '#ef4444';
        subtitle.innerHTML = `<span style="color: #ef4444; font-weight: 700;">{{ __('Occupied') }}</span> &bull; ${hallName}`;

        let callNotice = '';
        if (table.has_waiter_call && table.waiter_call) {
            callNotice = `
                <div style="background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 12px; padding: 0.75rem 1rem; margin-bottom: 1rem; color: #f59e0b; display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fa-solid fa-bell fa-bounce"></i>
                    <div>
                        <strong>{{ __('Active Call:') }}</strong> ${table.waiter_call.type_label} (${table.waiter_call.created_at_time})
                    </div>
                </div>
            `;
        }

        body.innerHTML = `
            ${callNotice}
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
                    <span style="font-weight: 600; color: var(--text-main);">${table.order.customer_name || '{{ __('Guest') }}'}</span>
                </div>
            </div>
        `;

        actionBtns.innerHTML = `
            <a href="{{ route('admin.orders.index') }}" class="btn btn-primary" style="border-radius: 12px; font-size: 0.85rem; text-decoration: none;">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> {{ __('View Order') }}
            </a>
        `;
    } else {
        icon.style.background = 'rgba(16, 185, 129, 0.15)';
        icon.style.color = '#10b981';
        subtitle.innerHTML = `<span style="color: #10b981; font-weight: 700;">{{ __('Available') }}</span> &bull; ${hallName}`;

        body.innerHTML = `
            <div style="text-align: center; padding: 1.5rem 0;">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🟢</div>
                <h4 style="margin: 0; color: var(--text-main); font-weight: 800;">{{ __('Table is Available') }}</h4>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0.35rem 0 0 0;">
                    {{ __('Table status updates automatically when new orders arrive.') }}
                </p>
            </div>
        `;
        actionBtns.innerHTML = '';
    }

    modal.style.display = 'flex';
}

function closeTableDetailsModal() {
    document.getElementById('tableDetailsModal').style.display = 'none';
}

function addNewTableModal() {
    document.getElementById('addTableModal').style.display = 'flex';
}

function closeAddTableModal() {
    document.getElementById('addTableModal').style.display = 'none';
}

function createNewTable() {
    const num = document.getElementById('newTableNumber').value.trim();
    if (!num) {
        alert('{{ __('Please enter a table number.') }}');
        return;
    }

    const hallId = document.getElementById('newTableHall').value;
    const capacity = parseInt(document.getElementById('newTableCapacity').value) || 4;
    const shape = document.getElementById('newTableShape').value;

    const newId = Date.now();
    const newTable = {
        id: newId,
        number: num,
        hall_id: hallId,
        capacity: capacity,
        shape: shape,
        x: 60,
        y: 60,
        status: 'available',
        order: null,
        has_waiter_call: false,
    };

    floorTables.push(newTable);

    // Create DOM element
    const canvas = document.getElementById('floorCanvas');
    const node = document.createElement('div');
    node.className = `floor-table-node ${shape} is-available`;
    node.id = `table-node-${newId}`;
    node.dataset.id = newId;
    node.dataset.number = num;
    node.dataset.hall = hallId;
    node.dataset.shape = shape;
    node.dataset.capacity = capacity;
    node.dataset.status = 'available';
    node.style.left = '60px';
    node.style.top = '60px';
    node.onclick = () => openTableDetails(newTable);

    node.innerHTML = `
        <div class="table-inner">
            <div class="table-number">#${num}</div>
            <div class="table-capacity"><i class="fa-solid fa-user-group"></i> ${capacity}</div>
            <div class="table-status-label">{{ __('Available') }}</div>
        </div>
    `;

    canvas.appendChild(node);
    initDragAndDrop();
    closeAddTableModal();
}

document.addEventListener('DOMContentLoaded', () => {
    initDragAndDrop();
});
</script>
@endsection
