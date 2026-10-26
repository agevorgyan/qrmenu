@extends('layouts.app')

@section('title', 'Subscription Plans - SuperAdmin')

@section('styles')
<style>
    .plan-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        padding: 1.75rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: var(--shadow-card);
        position: relative;
        overflow: hidden;
        transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
    }
    .plan-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 16px 36px rgba(0, 0, 0, 0.15);
    }
    .plan-card-basic { border-top: 5px solid #64748b; }
    .plan-card-pro { border-top: 5px solid #f59e0b; }
    .plan-card-business { border-top: 5px solid #06b6d4; }
    .plan-card-custom { border-top: 5px solid #a855f7; }

    .feature-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.82rem;
        color: var(--text-muted);
        margin-bottom: 0.45rem;
    }
    .feature-icon-check {
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: rgba(16, 185, 129, 0.15);
        color: #10b981;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.65rem;
        flex-shrink: 0;
    }
</style>
@endsection

@section('content')
<!-- Page Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
            <span class="badge badge-amber">
                <i class="fa-solid fa-box-archive"></i> {{ __('Plan Management') }}
            </span>
            <span style="font-size: 0.78rem; color: var(--text-muted);">{{ __('Total') }}: {{ $plans->count() }}</span>
        </div>
        <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
            {{ __('Subscription Plans') }}
        </h1>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('createPlanModal').style.display='flex'" style="border-radius: 12px; font-size: 0.88rem; padding: 0.65rem 1.25rem;">
        <i class="fa-solid fa-plus"></i> {{ __('Add New Plan') }}
    </button>
</div>

<!-- Plans Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
    @foreach($plans as $plan)
        @php
            $slugLower = strtolower($plan->slug);
            $cardClass = in_array($slugLower, ['basic', 'pro', 'business', 'custom']) ? 'plan-card-'.$slugLower : 'plan-card-pro';
        @endphp
        <div class="plan-card {{ $cardClass }}">
            <div>
                <!-- Plan Top Bar -->
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.85rem;">
                    <div>
                        <h3 style="font-size: 1.3rem; font-weight: 800; font-family: 'Outfit'; color: var(--text-main); margin-bottom: 0.15rem;">
                            {{ $plan->name }}
                        </h3>
                        <span style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">
                            SLUG: {{ $plan->slug }}
                        </span>
                    </div>

                    <form action="{{ route('superadmin.plans.toggle', $plan->id) }}" method="POST" style="margin: 0;">
                        @csrf
                        <button type="submit" class="badge {{ $plan->is_active ? 'badge-emerald' : 'badge-rose' }}" style="cursor: pointer; border-radius: 8px;">
                            <i class="fa-solid fa-circle" style="font-size: 0.45rem;"></i> {{ $plan->is_active ? __('Active') : __('Disabled') }}
                        </button>
                    </form>
                </div>

                <!-- Price & Duration -->
                <div style="background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.85rem 1rem; margin-bottom: 1rem;">
                    <div style="font-size: 1.65rem; font-weight: 800; font-family: 'Outfit'; color: var(--primary);">
                        {{ $plan->formatted_price }}
                    </div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <span><i class="fa-solid fa-calendar-days"></i> {{ $plan->duration_days }} {{ __('days') }}</span>
                        <span>•</span>
                        <span><i class="fa-solid fa-clock"></i> {{ __('Trial: :days days', ['days' => $plan->trial_days]) }}</span>
                    </div>
                </div>

                <!-- Description -->
                <p style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 1.25rem; line-height: 1.45; min-height: 2.5rem;">
                    {{ $plan->description ?: __('Standard pricing plan with all essential QRMenu features.') }}
                </p>

                <div style="border-top: 1px solid var(--border-color); padding-top: 1rem; margin-bottom: 1.25rem;">
                    <h4 style="font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.65rem;">
                        {{ __('Included Features') }} ({{ count($plan->features ?? []) }})
                    </h4>
                    <div style="display: flex; flex-direction: column;">
                        @forelse($plan->features ?? [] as $feat)
                            <div class="feature-item">
                                <div class="feature-icon-check"><i class="fa-solid fa-check"></i></div>
                                <span>{{ $feat }}</span>
                            </div>
                        @empty
                            <div style="font-size: 0.78rem; color: var(--text-muted);">{{ __('No features listed') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Card Bottom Bar -->
            <div style="border-top: 1px solid var(--border-color); padding-top: 1rem;">
                <div style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 0.85rem; display: flex; align-items: center; justify-content: space-between;">
                    <span>🏢 {{ __('Active Partners') }}</span>
                    <strong style="color: var(--text-main); font-family: 'Outfit'; font-size: 0.95rem;">{{ $plan->vendors_count }}</strong>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn btn-secondary" style="flex: 1; font-size: 0.82rem; justify-content: center; border-radius: 10px;" onclick="openEditModal({{ json_encode($plan) }})">
                        <i class="fa-solid fa-pen-to-square"></i> {{ __('Edit Plan') }}
                    </button>
                    @if($plan->vendors_count == 0)
                        <form action="{{ route('superadmin.plans.destroy', $plan->id) }}" method="POST" onsubmit="return confirm('{{ __('Are you sure you want to delete this plan?') }}');" style="margin: 0;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-secondary" style="color: #ef4444; border-radius: 10px; padding: 0.55rem 0.75rem;" title="{{ __('Delete') }}">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

<!-- Modern Create Plan Modal -->
<div id="createPlanModal" class="modern-modal-overlay" style="display: none;">
    <div class="modern-modal-box">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 0.85rem; border-bottom: 1px solid var(--border-color);">
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                    <i class="fa-solid fa-box-archive"></i>
                </div>
                <h3 style="font-family: 'Outfit'; font-weight: 800; font-size: 1.25rem; color: var(--text-main); margin: 0;">
                    Ավելացնել Նոր Փաթեթ
                </h3>
            </div>
            <button onclick="document.getElementById('createPlanModal').style.display='none'" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:1.25rem;">✕</button>
        </div>

        <form action="{{ route('superadmin.plans.store') }}" method="POST">
            @csrf
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Փաթեթի Անվանում *</label>
                    <input type="text" name="name" class="form-input" required placeholder="օր․ Ultra Business">
                </div>
                <div>
                    <label class="form-label">Գին (֏ / ամիս) *</label>
                    <input type="number" name="price" step="100" min="0" class="form-input" required placeholder="0 = Պայմանագրային">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Վճարման պարբերություն</label>
                    <select name="billing_interval" class="form-select">
                        <option value="monthly">Ամսական (Monthly)</option>
                        <option value="yearly">Տարեկան (Yearly)</option>
                        <option value="custom">Անհատական (Custom)</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Տևողություն (օրեր)</label>
                    <input type="number" name="duration_days" value="30" min="1" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Փորձնական (օրեր)</label>
                    <input type="number" name="trial_days" value="14" min="0" class="form-input" required>
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label class="form-label">Նկարագրություն</label>
                <input type="text" name="description" class="form-input" placeholder="Փաթեթի համառոտ նկարագրություն...">
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label class="form-label">Ֆունկցիոնալության ցանկ (յուրաքանչյուրը նոր տողում)</label>
                <textarea name="features" rows="5" class="form-textarea" placeholder="QR մենյու&#10;Օնլայն պատվերներ&#10;Սեղանի համարով պատվիրում"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('createPlanModal').style.display='none'">Չեղարկել</button>
                <button type="submit" class="btn btn-primary">Պահպանել Փաթեթը</button>
            </div>
        </form>
    </div>
</div>

<!-- Modern Edit Plan Modal -->
<div id="editPlanModal" class="modern-modal-overlay" style="display: none;">
    <div class="modern-modal-box">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 0.85rem; border-bottom: 1px solid var(--border-color);">
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(99, 102, 241, 0.15); color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                    <i class="fa-solid fa-pen-to-square"></i>
                </div>
                <h3 style="font-family: 'Outfit'; font-weight: 800; font-size: 1.25rem; color: var(--text-main); margin: 0;">
                    Խմբագրել Փաթեթը
                </h3>
            </div>
            <button onclick="document.getElementById('editPlanModal').style.display='none'" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:1.25rem;">✕</button>
        </div>

        <form id="editPlanForm" method="POST">
            @csrf
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Փաթեթի Անվանում *</label>
                    <input type="text" id="edit_name" name="name" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Գին (֏ / ամիս) *</label>
                    <input type="number" id="edit_price" name="price" step="100" min="0" class="form-input" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Վճարման պարբերություն</label>
                    <select id="edit_billing_interval" name="billing_interval" class="form-select">
                        <option value="monthly">Ամսական (Monthly)</option>
                        <option value="yearly">Տարեկան (Yearly)</option>
                        <option value="custom">Անհատական (Custom)</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Տևողություն (օրեր)</label>
                    <input type="number" id="edit_duration_days" name="duration_days" min="1" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Փորձնական (օրեր)</label>
                    <input type="number" id="edit_trial_days" name="trial_days" min="0" class="form-input" required>
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label class="form-label">Նկարագրություն</label>
                <input type="text" id="edit_description" name="description" class="form-input">
            </div>

            <div style="margin-bottom: 1.25rem;">
                <label class="form-label">Ֆունկցիոնալության ցանկ (յուրաքանչյուրը նոր տողում)</label>
                <textarea id="edit_features" name="features" rows="5" class="form-textarea"></textarea>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--text-main); cursor: pointer;">
                    <input type="checkbox" id="edit_is_active" name="is_active" value="1">
                    <span>Ակտիվ փաթեթ (հասանելի է գրանցումների համար)</span>
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('editPlanModal').style.display='none'">Չեղարկել</button>
                <button type="submit" class="btn btn-primary">Թարմացնել Փաթեթը</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditModal(plan) {
        document.getElementById('editPlanForm').action = '/superadmin/plans/' + plan.id;
        document.getElementById('edit_name').value = plan.name;
        document.getElementById('edit_price').value = plan.price;
        document.getElementById('edit_billing_interval').value = plan.billing_interval || 'monthly';
        document.getElementById('edit_duration_days').value = plan.duration_days || 30;
        document.getElementById('edit_trial_days').value = plan.trial_days || 14;
        document.getElementById('edit_description').value = plan.description || '';
        document.getElementById('edit_features').value = (plan.features || []).join('\n');
        document.getElementById('edit_is_active').checked = plan.is_active;

        document.getElementById('editPlanModal').style.display = 'flex';
    }
</script>
@endsection
