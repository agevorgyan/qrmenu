@extends('layouts.app')

@section('content')
<div class="content-wrapper">

    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-gift" style="color: #ec4899;"></i>
                <span>{{ __('Rewards Catalog') }}</span>
            </h1>
            <p style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('Manage redeemable menu items, order vouchers, and tier-exclusive perks for loyal customers.') }}
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="{{ route('admin.loyalty.index') }}" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left mr-1"></i> {{ __('Overview') }}
            </a>
            <button type="button" class="btn btn-primary" onclick="openCreateRewardModal()">
                <i class="fa-solid fa-plus mr-1"></i> {{ __('New Reward') }}
            </button>
        </div>
    </div>

    @if(session('success'))
        <div style="padding: 1rem; border-radius: 12px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; font-size: 0.875rem; margin-bottom: 1.5rem;">
            <i class="fa-solid fa-circle-check mr-1"></i> {{ session('success') }}
        </div>
    @endif

    <!-- Rewards Grid -->
    @if($rewards->isEmpty())
        <div class="card" style="padding: 3rem 1.5rem; text-align: center; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px;">
            <i class="fa-solid fa-gift" style="font-size: 3rem; color: var(--text-muted); opacity: 0.3; margin-bottom: 1rem; display: block;"></i>
            <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.5rem;">{{ __('No Rewards Created Yet') }}</h3>
            <p style="color: var(--text-muted); font-size: 0.875rem; max-width: 450px; margin: 0 auto 1.5rem auto;">
                {{ __('Offer your customers free drinks, desserts, or discounts they can unlock by redeeming points.') }}
            </p>
            <button type="button" class="btn btn-primary" onclick="openCreateRewardModal()">
                <i class="fa-solid fa-plus mr-1"></i> {{ __('Create Your First Reward') }}
            </button>
        </div>
    @else
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem;">
            @foreach($rewards as $reward)
                <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; display: flex; flex-direction: column;">
                    
                    <!-- Reward Image / Header -->
                    <div style="height: 160px; background: {{ $reward->image_url ? 'url('.$reward->image_url.') center/cover no-repeat' : 'linear-gradient(135deg, #4f46e5 0%, #ec4899 100%)' }}; position: relative; display: flex; align-items: flex-end; padding: 1rem;">
                        <div style="position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.1) 100%);"></div>
                        
                        <div style="position: relative; z-index: 2; width: 100%; display: flex; justify-content: space-between; align-items: flex-end;">
                            <span class="badge" style="background: #6366f1; color: #fff; font-weight: 700; font-size: 0.95rem; padding: 0.4rem 0.8rem; border-radius: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.3);">
                                <i class="fa-solid fa-coins mr-1"></i> {{ number_format($reward->points_cost) }} pts
                            </span>

                            @if($reward->requiredTier)
                                <span class="badge" style="background: {{ $reward->requiredTier->color_hex }}; color: #fff; font-weight: 700; font-size: 0.75rem; padding: 0.25rem 0.6rem; border-radius: 12px;">
                                    <i class="fa-solid fa-crown mr-1"></i> {{ $reward->requiredTier->name }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div style="padding: 1.25rem; flex: 1; display: flex; flex-direction: column;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                            <h4 style="font-size: 1.1rem; font-weight: 700; color: var(--text-main); margin: 0;">
                                {{ $reward->title }}
                            </h4>
                            <span class="badge" style="background: rgba(99, 102, 241, 0.1); color: #818cf8; text-transform: capitalize; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 6px;">
                                {{ str_replace('_', ' ', $reward->reward_type->value) }}
                            </span>
                        </div>

                        <p style="font-size: 0.825rem; color: var(--text-muted); margin-bottom: 1rem; flex: 1; line-height: 1.5;">
                            {{ $reward->description ?: __('No detailed description provided.') }}
                        </p>

                        <!-- Stock & Limits Metadata -->
                        <div style="display: flex; gap: 1rem; margin-bottom: 1.25rem; font-size: 0.75rem; color: var(--text-muted); background: var(--bg-main); padding: 0.6rem 0.8rem; border-radius: 10px; border: 1px solid var(--border-color);">
                            <div>
                                <span style="display: block; font-weight: 600; color: var(--text-main);">
                                    {{ $reward->stock_quantity !== null ? number_format($reward->stock_quantity) : '∞' }}
                                </span>
                                <span>{{ __('Stock Remaining') }}</span>
                            </div>
                            <div style="border-left: 1px solid var(--border-color); padding-left: 1rem;">
                                <span style="display: block; font-weight: 600; color: var(--text-main);">
                                    {{ $reward->max_per_customer ? $reward->max_per_customer . ' / cust' : __('Unlimited') }}
                                </span>
                                <span>{{ __('Customer Limit') }}</span>
                            </div>
                        </div>

                        <!-- Card Footer / Actions -->
                        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 1rem; margin-top: auto;">
                            <form method="POST" action="{{ route('admin.loyalty.rewards.toggle', $reward) }}">
                                @csrf
                                <button type="submit" style="background: none; border: none; cursor: pointer;">
                                    @if($reward->is_active)
                                        <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #34d399; font-weight: 600; padding: 0.3rem 0.6rem; border-radius: 20px;">
                                            <i class="fa-solid fa-circle-check mr-1"></i> {{ __('Active') }}
                                        </span>
                                    @else
                                        <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #f87171; font-weight: 600; padding: 0.3rem 0.6rem; border-radius: 20px;">
                                            <i class="fa-solid fa-ban mr-1"></i> {{ __('Inactive') }}
                                        </span>
                                    @endif
                                </button>
                            </form>

                            <form method="POST" action="{{ route('admin.loyalty.rewards.destroy', $reward) }}" onsubmit="return confirm('{{ __('Are you sure you want to delete this reward?') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 8px; padding: 0.35rem 0.75rem;">
                                    <i class="fa-solid fa-trash mr-1"></i> {{ __('Delete') }}
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            @endforeach
        </div>

        <div style="margin-top: 2rem;">
            {{ $rewards->links() }}
        </div>
    @endif

    <!-- Create Reward Modal -->
    <div id="createRewardModal" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(4px); align-items: center; justify-content: center;">
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; width: 100%; max-width: 600px; padding: 2rem; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin: 0;">
                    <i class="fa-solid fa-gift text-primary mr-1"></i> {{ __('Create New Reward') }}
                </h3>
                <button type="button" onclick="closeCreateRewardModal()" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('admin.loyalty.rewards.store') }}" enctype="multipart/form-data">
                @csrf
                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <div>
                        <label class="form-label">{{ __('Reward Title') }}</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Free Cappuccino or $10 Off Entire Bill" required>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Description') }}</label>
                        <textarea name="description" rows="2" class="form-control" placeholder="{{ __('Short customer-facing instructions or details...') }}"></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label class="form-label">{{ __('Reward Type') }}</label>
                            <select name="reward_type" class="form-control" required id="rewardTypeSelect" onchange="toggleRewardTypeFields()">
                                <option value="free_product">{{ __('Free Menu Product') }}</option>
                                <option value="fixed_discount">{{ __('Fixed Discount Amount') }}</option>
                                <option value="percentage_discount">{{ __('Percentage Discount') }}</option>
                                <option value="voucher">{{ __('Staff Redeemable Voucher') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">{{ __('Points Cost') }}</label>
                            <input type="number" name="points_cost" class="form-control" placeholder="e.g. 500" required min="0">
                        </div>
                    </div>

                    <div id="productSelectWrapper">
                        <label class="form-label">{{ __('Linked Menu Product') }}</label>
                        <select name="product_id" class="form-control">
                            <option value="">-- {{ __('Select Product') }} --</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }} ({{ number_format($product->price, 2) }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div id="discountValueWrapper" style="display: none;">
                        <label class="form-label">{{ __('Discount Amount or %') }}</label>
                        <input type="number" step="0.01" name="discount_value" class="form-control" placeholder="e.g. 10.00">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label class="form-label">{{ __('Required Tier (Optional)') }}</label>
                            <select name="required_tier_id" class="form-control">
                                <option value="">{{ __('Available to All Tiers') }}</option>
                                @foreach($tiers as $tier)
                                    <option value="{{ $tier->id }}">{{ $tier->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">{{ __('Initial Stock Cap (Optional)') }}</label>
                            <input type="number" name="stock_quantity" class="form-control" placeholder="{{ __('Leave blank for unlimited') }}">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label class="form-label">{{ __('Max Per Customer') }}</label>
                            <input type="number" name="max_per_customer" class="form-control" placeholder="{{ __('e.g. 1 (optional)') }}">
                        </div>
                        <div>
                            <label class="form-label">{{ __('Reward Image') }}</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1rem;">
                        <button type="button" onclick="closeCreateRewardModal()" class="btn btn-secondary">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-save mr-1"></i> {{ __('Save Reward') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function openCreateRewardModal() {
    document.getElementById('createRewardModal').style.display = 'flex';
}
function closeCreateRewardModal() {
    document.getElementById('createRewardModal').style.display = 'none';
}
function toggleRewardTypeFields() {
    const type = document.getElementById('rewardTypeSelect').value;
    const prodWrap = document.getElementById('productSelectWrapper');
    const discWrap = document.getElementById('discountValueWrapper');
    
    if (type === 'free_product') {
        prodWrap.style.display = 'block';
        discWrap.style.display = 'none';
    } else {
        prodWrap.style.display = 'none';
        discWrap.style.display = 'block';
    }
}
</script>
@endsection

