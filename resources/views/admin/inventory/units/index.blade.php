@extends('layouts.app')

@section('title', __('Units & Conversions') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div style="min-width: 0; flex: 1;">
        <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
            <span style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                <i class="fa-solid fa-scale-balanced"></i>
            </span>
            <span>{{ __('Units of Measurement & Conversions') }}</span>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0;">
            {{ __('Metric mass and volume standards, custom packaging units, and item-specific conversion rules.') }}
        </p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('newConversionModal').style.display='flex'" style="border-radius: 12px; font-weight: 600;">
            <i class="fa-solid fa-calculator"></i> {{ __('Add Conversion Rule') }}
        </button>
        <button type="button" class="btn btn-primary" onclick="document.getElementById('newUnitModal').style.display='flex'" style="border-radius: 12px; font-weight: 700;">
            <i class="fa-solid fa-plus"></i> {{ __('Add Custom Unit') }}
        </button>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Units Table -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card);">
        <h3 style="font-size: 1.05rem; font-weight: 800; color: var(--text-main); margin: 0 0 1rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-ruler-combined" style="color: #8b5cf6;"></i>
            <span>{{ __('Measurement Units') }}</span>
        </h3>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color); color: var(--text-muted); text-align: left;">
                        <th style="padding: 0.6rem 0.5rem;">{{ __('Code') }}</th>
                        <th style="padding: 0.6rem 0.5rem;">{{ __('Name') }}</th>
                        <th style="padding: 0.6rem 0.5rem;">{{ __('Type') }}</th>
                        <th style="padding: 0.6rem 0.5rem; text-align: right;">{{ __('Base Factor') }}</th>
                        <th style="padding: 0.6rem 0.5rem; text-align: center;">{{ __('Scope') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($units as $u)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-main); font-family: monospace;">
                                {{ $u->code }}
                            </td>
                            <td style="padding: 0.65rem 0.5rem; color: var(--text-main);">
                                {{ $u->name }}
                            </td>
                            <td style="padding: 0.65rem 0.5rem;">
                                <span style="font-size: 0.72rem; font-weight: 700; padding: 2px 6px; border-radius: 6px; background: rgba(0,0,0,0.05); text-transform: uppercase;">
                                    {{ $u->type }}
                                </span>
                            </td>
                            <td style="padding: 0.65rem 0.5rem; text-align: right; font-family: monospace; color: var(--text-muted);">
                                {{ rtrim(rtrim(number_format((float) $u->base_factor, 6), '0'), '.') }}
                            </td>
                            <td style="padding: 0.65rem 0.5rem; text-align: center;">
                                @if($u->is_system)
                                    <span style="font-size: 0.7rem; color: #10b981; font-weight: 700; background: rgba(16, 185, 129, 0.1); padding: 2px 6px; border-radius: 4px;">{{ __('System') }}</span>
                                @else
                                    <span style="font-size: 0.7rem; color: #f59e0b; font-weight: 700; background: rgba(245, 158, 11, 0.1); padding: 2px 6px; border-radius: 4px;">{{ __('Custom') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Conversions Table -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card);">
        <h3 style="font-size: 1.05rem; font-weight: 800; color: var(--text-main); margin: 0 0 1rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-arrows-split-up-and-left" style="color: #06b6d4;"></i>
            <span>{{ __('Conversion Rules') }}</span>
        </h3>

        @if($conversions->isEmpty())
            <div style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted);">
                <i class="fa-solid fa-calculator" style="font-size: 2rem; color: var(--text-muted); margin-bottom: 0.5rem;"></i>
                <p style="margin: 0;">{{ __('No custom conversion rules registered yet.') }}</p>
                <p style="font-size: 0.78rem; margin-top: 0.25rem;">{{ __('Standard metric conversions (kg <-> g, l <-> ml) calculate automatically.') }}</p>
            </div>
        @else
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-color); color: var(--text-muted); text-align: left;">
                            <th style="padding: 0.6rem 0.5rem;">{{ __('Scope / Item') }}</th>
                            <th style="padding: 0.6rem 0.5rem;">{{ __('Rule') }}</th>
                            <th style="padding: 0.6rem 0.5rem; text-align: right;">{{ __('Factor') }}</th>
                            <th style="padding: 0.6rem 0.5rem; text-align: right;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($conversions as $conv)
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 0.65rem 0.5rem; color: var(--text-main); font-weight: 600;">
                                    {{ $conv->ingredient?->name ?: __('Global') }}
                                </td>
                                <td style="padding: 0.65rem 0.5rem; font-family: monospace;">
                                    1 {{ $conv->fromUnit?->code }} = {{ rtrim(rtrim(number_format((float) $conv->factor, 4), '0'), '.') }} {{ $conv->toUnit?->code }}
                                </td>
                                <td style="padding: 0.65rem 0.5rem; text-align: right; font-family: monospace; font-weight: 700;">
                                    {{ rtrim(rtrim(number_format((float) $conv->factor, 4), '0'), '.') }}
                                </td>
                                <td style="padding: 0.65rem 0.5rem; text-align: right;">
                                    @if($conv->vendor_id)
                                        <form method="POST" action="{{ route('admin.inventory.units.conversions.destroy', $conv) }}" onsubmit="return confirm('{{ __('Delete conversion rule?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" style="background: none; border: none; color: #ef4444; cursor: pointer;">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($conversions->hasPages())
                <div style="margin-top: 1rem;">
                    {{ $conversions->links() }}
                </div>
            @endif
        @endif
    </div>
</div>

<!-- Modal: New Unit -->
<div id="newUnitModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card" style="background: var(--bg-card); border-radius: 20px; max-width: 480px; width: 100%; padding: 1.5rem; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0;">{{ __('Add Custom Unit') }}</h3>
            <button type="button" onclick="document.getElementById('newUnitModal').style.display='none'" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">&times;</button>
        </div>

        <form method="POST" action="{{ route('admin.inventory.units.store') }}">
            @csrf
            <div style="margin-bottom: 1rem;">
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">{{ __('Unit Code (Symbol)') }} <span style="color: #ef4444;">*</span></label>
                <input type="text" name="code" required placeholder="e.g. can, tray, sack" class="form-input" style="width: 100%; border-radius: 10px;">
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">{{ __('Unit Name') }} <span style="color: #ef4444;">*</span></label>
                <input type="text" name="name" required placeholder="e.g. Aluminum Can" class="form-input" style="width: 100%; border-radius: 10px;">
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">{{ __('Unit Dimension / Type') }} <span style="color: #ef4444;">*</span></label>
                <select name="type" required class="form-select" style="width: 100%; border-radius: 10px;">
                    <option value="piece">{{ __('Piece / Quantity') }}</option>
                    <option value="packaging">{{ __('Packaging') }}</option>
                    <option value="mass">{{ __('Mass / Weight') }}</option>
                    <option value="volume">{{ __('Volume / Liquid') }}</option>
                </select>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                <button type="button" onclick="document.getElementById('newUnitModal').style.display='none'" class="btn btn-secondary" style="border-radius: 10px;">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 700;">{{ __('Save Unit') }}</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: New Conversion Rule -->
<div id="newConversionModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card" style="background: var(--bg-card); border-radius: 20px; max-width: 520px; width: 100%; padding: 1.5rem; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0;">{{ __('Add Unit Conversion Rule') }}</h3>
            <button type="button" onclick="document.getElementById('newConversionModal').style.display='none'" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">&times;</button>
        </div>

        <form method="POST" action="{{ route('admin.inventory.units.conversions.store') }}">
            @csrf
            <div style="margin-bottom: 1rem;">
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                    {{ __('Specific Ingredient (Optional)') }}
                </label>
                <select name="ingredient_id" class="form-select" style="width: 100%; border-radius: 10px;">
                    <option value="">-- {{ __('Global Rule (Applies to all items)') }} --</option>
                    @foreach($ingredients as $ing)
                        <option value="{{ $ing->id }}">{{ $ing->name }} ({{ $ing->unit?->code }})</option>
                    @endforeach
                </select>
                <span style="font-size: 0.72rem; color: var(--text-muted);">{{ __('Leave empty for universal rules (e.g. 1 box = 12 pieces). Select item for density-dependent rules.') }}</span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">{{ __('From Unit') }} <span style="color: #ef4444;">*</span></label>
                    <select name="from_unit_id" required class="form-select" style="width: 100%; border-radius: 10px;">
                        @foreach($units as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">{{ __('To Unit') }} <span style="color: #ef4444;">*</span></label>
                    <select name="to_unit_id" required class="form-select" style="width: 100%; border-radius: 10px;">
                        @foreach($units as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->code }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                    {{ __('Conversion Multiplier (Factor)') }} <span style="color: #ef4444;">*</span>
                </label>
                <input type="number" step="0.000001" name="factor" required placeholder="e.g. 12 (if 1 Box = 12 Pieces)" class="form-input" style="width: 100%; border-radius: 10px;">
                <span style="font-size: 0.72rem; color: var(--text-muted);">{{ __('Formula: To_Quantity = From_Quantity * Factor') }}</span>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="document.getElementById('newConversionModal').style.display='none'" class="btn btn-secondary" style="border-radius: 10px;">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 700;">{{ __('Save Conversion') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

