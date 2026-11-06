@extends('layouts.app')

@section('content')
<div class="content-wrapper">

    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-crown" style="color: #f59e0b;"></i>
                <span>{{ __('Membership Tiers Management') }}</span>
            </h1>
            <p style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('Configure progression thresholds, bonus point multipliers, cashback perks, and bill discounts across tiers.') }}
            </p>
        </div>
        <div>
            <a href="{{ route('admin.loyalty.index') }}" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left mr-1"></i> {{ __('Overview') }}
            </a>
        </div>
    </div>

    @if(session('success'))
        <div style="padding: 1rem; border-radius: 12px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; font-size: 0.875rem; margin-bottom: 1.5rem;">
            <i class="fa-solid fa-circle-check mr-1"></i> {{ session('success') }}
        </div>
    @endif

    <!-- Tiers Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; align-items: start;">
        @foreach($tiers as $tier)
            <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
                
                <!-- Tier Header Banner -->
                <div style="background: linear-gradient(135deg, {{ $tier->color_hex }}22 0%, {{ $tier->color_hex }}55 100%); border-bottom: 2px solid {{ $tier->color_hex }}; padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <span style="width: 38px; height: 38px; border-radius: 10px; background: {{ $tier->color_hex }}; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                            <i class="fa-solid fa-crown"></i>
                        </span>
                        <div>
                            <h3 style="font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0;">{{ $tier->name }}</h3>
                            <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); font-weight: 600;">
                                Level {{ $loop->iteration }} Tier
                            </span>
                        </div>
                    </div>

                    <span class="badge" style="background: {{ $tier->color_hex }}; color: #fff; font-weight: 700; padding: 0.3rem 0.6rem; border-radius: 12px; font-size: 0.8rem;">
                        {{ $tier->points_multiplier }}x pts
                    </span>
                </div>

                <!-- Tier Edit Form -->
                <form method="POST" action="{{ route('admin.loyalty.tiers.update', $tier) }}" style="padding: 1.5rem;">
                    @csrf
                    @method('PUT')

                    <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                        
                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
                            <div>
                                <label class="form-label" style="font-size: 0.8rem;">{{ __('Tier Name') }}</label>
                                <input type="text" name="name" value="{{ old('name', $tier->name) }}" class="form-control form-control-sm" required>
                            </div>
                            <div>
                                <label class="form-label" style="font-size: 0.8rem;">{{ __('Color Hex') }}</label>
                                <div style="display: flex; gap: 0.4rem; align-items: center;">
                                    <input type="color" value="{{ $tier->color_hex }}" onchange="document.getElementById('hexInput_{{ $tier->id }}').value = this.value" style="width: 36px; height: 36px; padding: 0; border: none; background: none; cursor: pointer; border-radius: 6px;">
                                    <input type="text" id="hexInput_{{ $tier->id }}" name="color_hex" value="{{ old('color_hex', $tier->color_hex) }}" class="form-control form-control-sm" required style="font-family: monospace;">
                                </div>
                            </div>
                        </div>

                        <!-- Thresholds -->
                        <div style="background: var(--bg-main); padding: 1rem; border-radius: 12px; border: 1px solid var(--border-color);">
                            <span style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.75rem;">
                                {{ __('Qualification Criteria (Annual / Rolling)') }}
                            </span>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                <div>
                                    <label class="form-label" style="font-size: 0.75rem;">{{ __('Min Earned Points') }}</label>
                                    <input type="number" name="min_points" value="{{ old('min_points', $tier->min_points) }}" class="form-control form-control-sm" required min="0">
                                </div>
                                <div>
                                    <label class="form-label" style="font-size: 0.75rem;">{{ __('Or Min Spend (') }}{{ $program->currency }}{{ __(')') }}</label>
                                    <input type="number" step="0.01" name="min_spend" value="{{ old('min_spend', $tier->min_spend) }}" class="form-control form-control-sm" required min="0">
                                </div>
                            </div>
                        </div>

                        <!-- Member Perks & Multipliers -->
                        <div style="background: var(--bg-main); padding: 1rem; border-radius: 12px; border: 1px solid var(--border-color);">
                            <span style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.75rem;">
                                {{ __('Exclusive Member Perks') }}
                            </span>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                                <div>
                                    <label class="form-label" style="font-size: 0.75rem;">{{ __('Point Multiplier') }}</label>
                                    <input type="number" step="0.01" name="points_multiplier" value="{{ old('points_multiplier', $tier->points_multiplier) }}" class="form-control form-control-sm" required min="1.0">
                                </div>
                                <div>
                                    <label class="form-label" style="font-size: 0.75rem;">{{ __('Cashback %') }}</label>
                                    <input type="number" step="0.1" name="cashback_percent" value="{{ old('cashback_percent', $tier->cashback_percent) }}" class="form-control form-control-sm" required min="0" max="100">
                                </div>
                            </div>

                            <div>
                                <label class="form-label" style="font-size: 0.75rem;">{{ __('Automatic Order Discount %') }}</label>
                                <input type="number" step="0.1" name="order_discount_percent" value="{{ old('order_discount_percent', $tier->order_discount_percent) }}" class="form-control form-control-sm" required min="0" max="100">
                            </div>
                        </div>

                        <div>
                            <label class="form-label" style="font-size: 0.8rem;">{{ __('Custom Perks / Public Description') }}</label>
                            <textarea name="perks_description" rows="2" class="form-control form-control-sm" placeholder="e.g. Complimentary table reservation, priority seating, free dessert on birthday">{{ old('perks_description', $tier->perks_description) }}</textarea>
                        </div>

                        <div style="padding-top: 0.5rem;">
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                <i class="fa-solid fa-save mr-1"></i> {{ __('Save') }} {{ $tier->name }} {{ __('Settings') }}
                            </button>
                        </div>

                    </div>
                </form>

            </div>
        @endforeach
    </div>

</div>
@endsection

