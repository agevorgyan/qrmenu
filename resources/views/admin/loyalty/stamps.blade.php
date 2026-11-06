@extends('layouts.app')

@section('content')
<div class="content-wrapper">

    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-stamp" style="color: #06b6d4;"></i>
                <span>{{ __('Digital Stamp Cards') }}</span>
            </h1>
            <p style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('Create punch-card style digital rewards (e.g. Buy 9 Coffees, Get the 10th Free).') }}
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="{{ route('admin.loyalty.index') }}" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left mr-1"></i> {{ __('Overview') }}
            </a>
            <button type="button" class="btn btn-primary" onclick="openCreateStampModal()">
                <i class="fa-solid fa-plus mr-1"></i> {{ __('New Stamp Card Program') }}
            </button>
        </div>
    </div>

    @if(session('success'))
        <div style="padding: 1rem; border-radius: 12px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; font-size: 0.875rem; margin-bottom: 1.5rem;">
            <i class="fa-solid fa-circle-check mr-1"></i> {{ session('success') }}
        </div>
    @endif

    <!-- Programs Grid -->
    @if($programs->isEmpty())
        <div class="card" style="padding: 3rem 1.5rem; text-align: center; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px;">
            <i class="fa-solid fa-stamp" style="font-size: 3rem; color: var(--text-muted); opacity: 0.3; margin-bottom: 1rem; display: block;"></i>
            <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.5rem;">{{ __('No Stamp Card Programs Yet') }}</h3>
            <p style="color: var(--text-muted); font-size: 0.875rem; max-width: 450px; margin: 0 auto 1.5rem auto;">
                {{ __('Digital punch cards are an effortless way to drive repeat visits for fast-casual food, coffee, and daily dining.') }}
            </p>
            <button type="button" class="btn btn-primary" onclick="openCreateStampModal()">
                <i class="fa-solid fa-plus mr-1"></i> {{ __('Create Your First Stamp Card') }}
            </button>
        </div>
    @else
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 1.5rem;">
            @foreach($programs as $prog)
                <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1.5rem; display: flex; flex-direction: column;">
                    
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                        <div>
                            <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-main); margin: 0 0 0.25rem 0;">{{ $prog->name }}</h3>
                            <p style="font-size: 0.825rem; color: var(--text-muted); margin: 0;">{{ $prog->description ?: __('Buy eligible items to earn punches.') }}</p>
                        </div>
                        <form method="POST" action="{{ route('admin.loyalty.stamps.toggle', $prog) }}">
                            @csrf
                            <button type="submit" style="background: none; border: none; cursor: pointer;">
                                @if($prog->is_active)
                                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #34d399; font-weight: 600; padding: 0.3rem 0.6rem; border-radius: 20px;">
                                        <i class="fa-solid fa-check mr-1"></i> {{ __('Active') }}
                                    </span>
                                @else
                                    <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #f87171; font-weight: 600; padding: 0.3rem 0.6rem; border-radius: 20px;">
                                        <i class="fa-solid fa-ban mr-1"></i> {{ __('Disabled') }}
                                    </span>
                                @endif
                            </button>
                        </form>
                    </div>

                    <!-- Visual Stamp Card Demonstration -->
                    <div style="background: var(--bg-main); border: 1px dashed var(--border-color); border-radius: 14px; padding: 1.25rem; margin-bottom: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.75rem;">
                            <span>{{ __('Card Layout') }}</span>
                            <span style="color: #06b6d4;">{{ $prog->stamps_required }} {{ __('Stamps Target') }}</span>
                        </div>

                        <!-- Render sample slots -->
                        <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.5rem; justify-items: center;">
                            @for($i = 1; $i <= $prog->stamps_required; $i++)
                                <div style="width: 44px; height: 44px; border-radius: 50%; border: 2px dashed {{ $i === $prog->stamps_required ? '#10b981' : '#06b6d4' }}; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 800; color: {{ $i === $prog->stamps_required ? '#10b981' : 'var(--text-muted)' }}; background: {{ $i === $prog->stamps_required ? 'rgba(16, 185, 129, 0.1)' : 'transparent' }};">
                                    @if($i === $prog->stamps_required)
                                        <i class="fa-solid fa-gift"></i>
                                    @else
                                        <i class="fa-solid fa-stamp" style="font-size: 0.75rem; opacity: 0.4;"></i>
                                    @endif
                                </div>
                            @endfor
                        </div>
                    </div>

                    <!-- Reward & Qualification Info -->
                    <div style="display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.825rem; margin-bottom: 1.25rem; flex: 1;">
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-muted);">{{ __('Completion Reward:') }}</span>
                            <span style="font-weight: 700; color: #10b981;">
                                {{ $prog->reward_description }}
                                @if($prog->rewardProduct)
                                    ({{ $prog->rewardProduct->name }})
                                @endif
                            </span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-muted);">{{ __('Min Order Spend:') }}</span>
                            <span style="font-weight: 600; color: var(--text-main);">
                                {{ $prog->min_order_spend > 0 ? number_format($prog->min_order_spend, 2) . ' ' . $vendor->currency : __('No minimum') }}
                            </span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-muted);">{{ __('Validity Window:') }}</span>
                            <span style="font-weight: 600; color: var(--text-main);">
                                {{ $prog->card_validity_days ? $prog->card_validity_days . ' ' . __('days') : __('Unlimited') }}
                            </span>
                        </div>
                    </div>

                    <!-- Card Actions -->
                    <div style="border-top: 1px solid var(--border-color); padding-top: 1rem; display: flex; justify-content: flex-end;">
                        <form method="POST" action="{{ route('admin.loyalty.stamps.destroy', $prog) }}" onsubmit="return confirm('{{ __('Delete this stamp card program?') }}')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 8px; padding: 0.35rem 0.75rem;">
                                <i class="fa-solid fa-trash mr-1"></i> {{ __('Delete Program') }}
                            </button>
                        </form>
                    </div>

                </div>
            @endforeach
        </div>
    @endif

    <!-- Create Stamp Card Program Modal -->
    <div id="createStampModal" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(4px); align-items: center; justify-content: center;">
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; width: 100%; max-width: 550px; padding: 2rem; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin: 0;">
                    <i class="fa-solid fa-stamp text-primary mr-1"></i> {{ __('New Digital Stamp Program') }}
                </h3>
                <button type="button" onclick="closeCreateStampModal()" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('admin.loyalty.stamps.store') }}">
                @csrf
                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <div>
                        <label class="form-label">{{ __('Program Name') }}</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Coffee Club 10-Stamp Card" required>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Description') }}</label>
                        <textarea name="description" rows="2" class="form-control" placeholder="{{ __('Optional customer instructions...') }}"></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label class="form-label">{{ __('Stamps Required (2 - 30)') }}</label>
                            <input type="number" name="stamps_required" value="10" min="2" max="30" class="form-control" required>
                        </div>
                        <div>
                            <label class="form-label">{{ __('Min Spend Per Stamp') }}</label>
                            <input type="number" step="0.01" name="min_order_spend" class="form-control" placeholder="0.00">
                        </div>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Reward Summary Description') }}</label>
                        <input type="text" name="reward_description" class="form-control" placeholder="e.g. Free Any Size Hot Beverage" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label class="form-label">{{ __('Reward Menu Item (Optional)') }}</label>
                            <select name="reward_product_id" class="form-control">
                                <option value="">-- {{ __('Select Item') }} --</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">{{ __('Card Validity (Days)') }}</label>
                            <input type="number" name="card_validity_days" class="form-control" placeholder="{{ __('e.g. 90 (blank for never)') }}">
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1rem;">
                        <button type="button" onclick="closeCreateStampModal()" class="btn btn-secondary">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-save mr-1"></i> {{ __('Create Stamp Program') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function openCreateStampModal() {
    document.getElementById('createStampModal').style.display = 'flex';
}
function closeCreateStampModal() {
    document.getElementById('createStampModal').style.display = 'none';
}
</script>
@endsection

