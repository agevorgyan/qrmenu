@extends('layouts.app')

@section('title', __('Stock Overview & Balances') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div style="min-width: 0; flex: 1;">
        <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
            <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                <i class="fa-solid fa-cubes-stacked"></i>
            </span>
            <span>{{ __('Stock Overview & Warehouse Balances') }}</span>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0;">
            {{ __('View on-hand, reserved, and available stock across facilities, and execute real-time movements.') }}
        </p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <a href="{{ route('admin.inventory.stocks.movements') }}" class="btn btn-secondary" style="border-radius: 12px; font-weight: 600;">
            <i class="fa-solid fa-clock-rotate-left"></i> {{ __('Audit Ledger') }}
        </a>
        <button type="button" class="btn btn-primary" onclick="openStockModal('receipt')" style="border-radius: 12px; font-weight: 700;">
            <i class="fa-solid fa-plus-minus"></i> {{ __('Record Movement') }}
        </button>
    </div>
</div>

<!-- Search & Filters -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
    <form method="GET" action="{{ route('admin.inventory.stocks.overview') }}" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 2; min-width: 200px;">
            <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">{{ __('Search') }}</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search item name, SKU...') }}" class="form-input" style="width: 100%; border-radius: 10px;">
        </div>

        <div style="flex: 1; min-width: 160px;">
            <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">{{ __('Warehouse') }}</label>
            <select name="warehouse_id" class="form-select" style="width: 100%; border-radius: 10px;">
                <option value="">{{ __('All Warehouses') }}</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ (string) request('warehouse_id') === (string) $wh->id ? 'selected' : '' }}>
                        {{ $wh->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div style="flex: 1; min-width: 160px;">
            <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">{{ __('Category') }}</label>
            <select name="category_id" class="form-select" style="width: 100%; border-radius: 10px;">
                <option value="">{{ __('All Categories') }}</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ (string) request('category_id') === (string) $cat->id ? 'selected' : '' }}>
                        {{ $cat->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div style="display: flex; align-items: center; gap: 0.5rem; padding-bottom: 0.5rem;">
            <label style="display: flex; align-items: center; gap: 0.4rem; cursor: pointer; font-size: 0.88rem; font-weight: 600; color: var(--text-main);">
                <input type="checkbox" name="low_stock" value="1" {{ request('low_stock') ? 'checked' : '' }} onchange="this.form.submit()">
                <span>{{ __('Low Stock') }}</span>
            </label>
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 700; padding: 0.55rem 1.25rem;">
                <i class="fa-solid fa-filter"></i> {{ __('Filter') }}
            </button>
            @if(request()->hasAny(['search', 'warehouse_id', 'category_id', 'low_stock']))
                <a href="{{ route('admin.inventory.stocks.overview') }}" class="btn btn-secondary" style="border-radius: 10px; padding: 0.55rem 1rem;">
                    {{ __('Reset') }}
                </a>
            @endif
        </div>
    </form>
</div>

<!-- Balances Table -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-card);">
    @if($balances->isEmpty())
        <div style="text-align: center; padding: 3.5rem 1rem; color: var(--text-muted);">
            <i class="fa-solid fa-cubes-stacked" style="font-size: 2.5rem; color: var(--text-muted); margin-bottom: 0.75rem;"></i>
            <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.25rem;">{{ __('No stock balances found') }}</h3>
            <p style="margin: 0;">{{ __('Record your first goods receipt or stock transfer to populate warehouse balances.') }}</p>
        </div>
    @else
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.02); color: var(--text-muted); text-align: left;">
                        <th style="padding: 0.85rem 1rem;">{{ __('Item / SKU') }}</th>
                        <th style="padding: 0.85rem 1rem;">{{ __('Warehouse') }}</th>
                        <th style="padding: 0.85rem 1rem; text-align: right;">{{ __('On Hand') }}</th>
                        <th style="padding: 0.85rem 1rem; text-align: right;">{{ __('Reserved') }}</th>
                        <th style="padding: 0.85rem 1rem; text-align: right;">{{ __('Available') }}</th>
                        <th style="padding: 0.85rem 1rem; text-align: right;">{{ __('Unit Cost') }}</th>
                        <th style="padding: 0.85rem 1rem; text-align: right;">{{ __('Total Value') }}</th>
                        <th style="padding: 0.85rem 1rem; text-align: right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($balances as $bal)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 0.85rem 1rem;">
                                <div style="font-weight: 700; color: var(--text-main);">
                                    {{ $bal->ingredient?->name }}
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">
                                    {{ $bal->ingredient?->sku ?: '-' }} &bull; {{ $bal->ingredient?->category?->name ?: __('General') }}
                                </div>
                            </td>
                            <td style="padding: 0.85rem 1rem;">
                                <span style="font-weight: 600; color: var(--text-main);">{{ $bal->warehouse?->name }}</span>
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align: right; font-weight: 700; color: var(--text-main);">
                                {{ number_format((float) $bal->quantity_on_hand, 2) }} {{ $bal->ingredient?->unit?->code }}
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align: right; color: var(--text-muted);">
                                {{ number_format((float) $bal->quantity_reserved, 2) }} {{ $bal->ingredient?->unit?->code }}
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align: right; font-weight: 800; color: {{ $bal->available_quantity <= 0 ? '#ef4444' : '#10b981' }};">
                                {{ number_format($bal->available_quantity, 2) }} {{ $bal->ingredient?->unit?->code }}
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align: right; font-family: monospace; color: var(--text-muted);">
                                {{ number_format((float) $bal->unit_cost, 2) }} {{ Auth::user()->vendor->currency }}
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align: right; font-weight: 800; color: var(--text-main);">
                                {{ number_format((float) $bal->total_value, 2) }} {{ Auth::user()->vendor->currency }}
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align: right;">
                                <button type="button" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.78rem;" onclick="openQuickAdjust({{ $bal->warehouse_id }}, {{ $bal->ingredient_id }}, {{ (float) $bal->quantity_on_hand }}, '{{ addslashes($bal->ingredient?->name) }}')">
                                    {{ __('Adjust') }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($balances->hasPages())
            <div style="padding: 1rem; border-top: 1px solid var(--border-color);">
                {{ $balances->links() }}
            </div>
        @endif
    @endif
</div>

<!-- Modal: Record Stock Movement (Receipt, Issue, Transfer, Adjustment) -->
<div id="stockMovementModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card" style="background: var(--bg-card); border-radius: 20px; max-width: 620px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 1.5rem; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0;" id="movementModalTitle">
                {{ __('Record Stock Movement') }}
            </h3>
            <button type="button" onclick="document.getElementById('stockMovementModal').style.display='none'" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">&times;</button>
        </div>

        <form method="POST" action="{{ route('admin.inventory.stocks.transactions.store') }}">
            @csrf

            <!-- Movement Type Selector -->
            <div style="margin-bottom: 1.25rem;">
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.4rem;">{{ __('Movement Type') }} <span style="color: #ef4444;">*</span></label>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem;">
                    <label style="display: flex; flex-direction: column; align-items: center; gap: 0.25rem; padding: 0.65rem 0.5rem; border: 1px solid var(--border-color); border-radius: 10px; cursor: pointer; text-align: center;">
                        <input type="radio" name="type" value="receipt" checked onchange="toggleMovementFields('receipt')">
                        <span style="font-size: 0.78rem; font-weight: 700; color: #10b981;">{{ __('Receipt') }}</span>
                    </label>
                    <label style="display: flex; flex-direction: column; align-items: center; gap: 0.25rem; padding: 0.65rem 0.5rem; border: 1px solid var(--border-color); border-radius: 10px; cursor: pointer; text-align: center;">
                        <input type="radio" name="type" value="issue" onchange="toggleMovementFields('issue')">
                        <span style="font-size: 0.78rem; font-weight: 700; color: #ef4444;">{{ __('Issue / Use') }}</span>
                    </label>
                    <label style="display: flex; flex-direction: column; align-items: center; gap: 0.25rem; padding: 0.65rem 0.5rem; border: 1px solid var(--border-color); border-radius: 10px; cursor: pointer; text-align: center;">
                        <input type="radio" name="type" value="transfer" onchange="toggleMovementFields('transfer')">
                        <span style="font-size: 0.78rem; font-weight: 700; color: #3b82f6;">{{ __('Transfer') }}</span>
                    </label>
                    <label style="display: flex; flex-direction: column; align-items: center; gap: 0.25rem; padding: 0.65rem 0.5rem; border: 1px solid var(--border-color); border-radius: 10px; cursor: pointer; text-align: center;">
                        <input type="radio" name="type" value="adjustment" onchange="toggleMovementFields('adjustment')">
                        <span style="font-size: 0.78rem; font-weight: 700; color: #f59e0b;">{{ __('Adjustment') }}</span>
                    </label>
                </div>
            </div>

            <!-- Warehouse Source -->
            <div style="margin-bottom: 1rem;">
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;" id="warehouseLabel">
                    {{ __('Warehouse') }} <span style="color: #ef4444;">*</span>
                </label>
                <select name="warehouse_id" required class="form-select" id="warehouseSelect" style="width: 100%; border-radius: 10px;">
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->type }})</option>
                    @endforeach
                </select>
            </div>

            <!-- Target Warehouse (For Transfer Only) -->
            <div style="margin-bottom: 1rem; display: none;" id="targetWarehouseGroup">
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                    {{ __('Target Warehouse (Destination)') }} <span style="color: #ef4444;">*</span>
                </label>
                <select name="target_warehouse_id" class="form-select" style="width: 100%; border-radius: 10px;">
                    <option value="">-- {{ __('Select Destination Warehouse') }} --</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->type }})</option>
                    @endforeach
                </select>
            </div>

            <!-- Standard Items Section (Receipt, Issue, Transfer) -->
            <div id="standardItemsSection">
                <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                    <div>
                        <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">{{ __('Item / Ingredient') }}</label>
                        <select name="items[0][ingredient_id]" class="form-select" style="width: 100%; border-radius: 10px;">
                            @foreach($ingredients as $ing)
                                <option value="{{ $ing->id }}">{{ $ing->name }} ({{ $ing->unit?->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">{{ __('Quantity') }}</label>
                        <input type="number" step="0.0001" name="items[0][quantity]" value="1" class="form-input" style="width: 100%; border-radius: 10px;">
                    </div>
                    <div>
                        <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">{{ __('Unit') }}</label>
                        <select name="items[0][unit_id]" class="form-select" style="width: 100%; border-radius: 10px;">
                            @foreach($units as $u)
                                <option value="{{ $u->id }}">{{ $u->code }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 1rem;" id="unitCostGroup">
                    <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                        {{ __('Unit Cost (Optional for Receipt, auto-calculated for Issue)') }}
                    </label>
                    <input type="number" step="0.0001" name="items[0][unit_cost]" placeholder="{{ __('Leave blank to use current unit cost') }}" class="form-input" style="width: 100%; border-radius: 10px;">
                </div>
            </div>

            <!-- Adjustment Specific Section -->
            <div id="adjustmentSection" style="display: none; margin-bottom: 1rem;">
                <div style="margin-bottom: 1rem;">
                    <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">{{ __('Ingredient') }}</label>
                    <select name="ingredient_id" id="adjIngredientSelect" class="form-select" style="width: 100%; border-radius: 10px;">
                        @foreach($ingredients as $ing)
                            <option value="{{ $ing->id }}">{{ $ing->name }} ({{ $ing->unit?->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                        {{ __('Actual Physical Counted Quantity') }} <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="number" step="0.0001" name="actual_quantity" id="adjActualQty" class="form-input" style="width: 100%; border-radius: 10px;">
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">{{ __('Reason for Discrepancy') }}</label>
                    <input type="text" name="reason" placeholder="{{ __('e.g. Periodic inventory count audit') }}" class="form-input" style="width: 100%; border-radius: 10px;">
                </div>
            </div>

            <!-- Notes -->
            <div style="margin-bottom: 1.5rem;">
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">{{ __('Notes / Reference') }}</label>
                <textarea name="notes" rows="2" placeholder="{{ __('e.g. Supplier Invoice #1024 or Kitchen Spoilage') }}" class="form-input" style="width: 100%; border-radius: 10px;"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="document.getElementById('stockMovementModal').style.display='none'" class="btn btn-secondary" style="border-radius: 10px;">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 700;">{{ __('Execute Transaction') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
function openStockModal(type) {
    document.getElementById('stockMovementModal').style.display = 'flex';
    const radios = document.getElementsByName('type');
    for (let r of radios) {
        if (r.value === type) r.checked = true;
    }
    toggleMovementFields(type);
}

function openQuickAdjust(warehouseId, ingredientId, currentQty, ingredientName) {
    openStockModal('adjustment');
    document.getElementById('warehouseSelect').value = warehouseId;
    document.getElementById('adjIngredientSelect').value = ingredientId;
    document.getElementById('adjActualQty').value = currentQty;
}

function toggleMovementFields(type) {
    const targetGroup = document.getElementById('targetWarehouseGroup');
    const standardSection = document.getElementById('standardItemsSection');
    const adjustmentSection = document.getElementById('adjustmentSection');
    const unitCostGroup = document.getElementById('unitCostGroup');
    const whLabel = document.getElementById('warehouseLabel');

    if (type === 'transfer') {
        targetGroup.style.display = 'block';
        standardSection.style.display = 'block';
        adjustmentSection.style.display = 'none';
        unitCostGroup.style.display = 'none';
        whLabel.innerText = "{{ __('Source Warehouse') }} *";
    } else if (type === 'adjustment') {
        targetGroup.style.display = 'none';
        standardSection.style.display = 'none';
        adjustmentSection.style.display = 'block';
        whLabel.innerText = "{{ __('Warehouse') }} *";
    } else {
        targetGroup.style.display = 'none';
        standardSection.style.display = 'block';
        adjustmentSection.style.display = 'none';
        unitCostGroup.style.display = type === 'receipt' ? 'block' : 'none';
        whLabel.innerText = "{{ __('Warehouse') }} *";
    }
}
</script>
@endsection

