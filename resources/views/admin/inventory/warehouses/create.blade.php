@extends('layouts.app')

@section('title', __('Create Warehouse') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="max-width: 760px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0;">
                {{ __('New Warehouse / Storage Facility') }}
            </h1>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0;">
                {{ __('Configure a physical storage room, cold facility, or kitchen dispensary.') }}
            </p>
        </div>
        <a href="{{ route('admin.inventory.warehouses.index') }}" class="btn btn-secondary" style="border-radius: 12px; font-weight: 600;">
            &larr; {{ __('Back to Warehouses') }}
        </a>
    </div>

    @if($errors->any())
        <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; color: #ef4444;">
            <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.88rem;">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.inventory.warehouses.store') }}" class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.5rem, 3vw, 2rem); box-shadow: var(--shadow-card);">
        @csrf

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem; margin-bottom: 1.25rem;">
            <div>
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.4rem;">
                    {{ __('Warehouse Name') }} <span style="color: #ef4444;">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="{{ __('e.g. Main Kitchen Cold Room') }}" class="form-input" style="width: 100%; border-radius: 10px;">
            </div>

            <div>
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.4rem;">
                    {{ __('Internal Code') }}
                </label>
                <input type="text" name="code" value="{{ old('code') }}" placeholder="WH-COLD-01" class="form-input" style="width: 100%; border-radius: 10px;">
            </div>

            <div>
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.4rem;">
                    {{ __('Storage Type') }} <span style="color: #ef4444;">*</span>
                </label>
                <select name="type" required class="form-select" style="width: 100%; border-radius: 10px;">
                    <option value="main" {{ old('type') === 'main' ? 'selected' : '' }}>{{ __('Main Central Warehouse') }}</option>
                    <option value="kitchen" {{ old('type') === 'kitchen' ? 'selected' : '' }}>{{ __('Kitchen Dispensary') }}</option>
                    <option value="cold_room" {{ old('type') === 'cold_room' ? 'selected' : '' }}>{{ __('Cold Storage (Refrigerated)') }}</option>
                    <option value="freezer" {{ old('type') === 'freezer' ? 'selected' : '' }}>{{ __('Deep Freezer') }}</option>
                    <option value="production" {{ old('type') === 'production' ? 'selected' : '' }}>{{ __('Production Line') }}</option>
                    <option value="finished_goods" {{ old('type') === 'finished_goods' ? 'selected' : '' }}>{{ __('Finished Goods') }}</option>
                    <option value="bar" {{ old('type') === 'bar' ? 'selected' : '' }}>{{ __('Bar / Beverage Station') }}</option>
                </select>
            </div>

            <div>
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.4rem;">
                    {{ __('Associated Branch (Location)') }}
                </label>
                <select name="location_id" class="form-select" style="width: 100%; border-radius: 10px;">
                    <option value="">-- {{ __('All Branches / Central') }} --</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ (string) old('location_id') === (string) $loc->id ? 'selected' : '' }}>
                            {{ $loc->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.4rem;">
                    {{ __('Responsible Staff / Manager') }}
                </label>
                <select name="responsible_user_id" class="form-select" style="width: 100%; border-radius: 10px;">
                    <option value="">-- {{ __('None Assigned') }} --</option>
                    @foreach($staffMembers as $staff)
                        <option value="{{ $staff->id }}" {{ (string) old('responsible_user_id') === (string) $staff->id ? 'selected' : '' }}>
                            {{ $staff->name }} ({{ $staff->role }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.4rem;">
                    {{ __('Physical Address / Notes') }}
                </label>
                <input type="text" name="address" value="{{ old('address') }}" placeholder="{{ __('Basement floor, Room B2') }}" class="form-input" style="width: 100%; border-radius: 10px;">
            </div>

            <div>
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.4rem;">
                    {{ __('Min Temperature (°C)') }}
                </label>
                <input type="number" step="0.1" name="temperature_min" value="{{ old('temperature_min') }}" placeholder="-18" class="form-input" style="width: 100%; border-radius: 10px;">
            </div>

            <div>
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.4rem;">
                    {{ __('Max Temperature (°C)') }}
                </label>
                <input type="number" step="0.1" name="temperature_max" value="{{ old('temperature_max') }}" placeholder="4" class="form-input" style="width: 100%; border-radius: 10px;">
            </div>
        </div>

        <div style="display: flex; gap: 2rem; margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.9rem; font-weight: 700; color: var(--text-main);">
                <input type="checkbox" name="is_default" value="1" {{ old('is_default') ? 'checked' : '' }}>
                <span>{{ __('Default warehouse for purchases and orders') }}</span>
            </label>

            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.9rem; font-weight: 700; color: var(--text-main);">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                <span>{{ __('Active facility') }}</span>
            </label>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem;">
            <a href="{{ route('admin.inventory.warehouses.index') }}" class="btn btn-secondary" style="border-radius: 12px; font-weight: 600;">
                {{ __('Cancel') }}
            </a>
            <button type="submit" class="btn btn-primary" style="border-radius: 12px; font-weight: 700; padding: 0.65rem 1.75rem;">
                <i class="fa-solid fa-check"></i> {{ __('Save Warehouse') }}
            </button>
        </div>
    </form>
</div>
@endsection

