@extends('layouts.app')

@section('title', __('Location Management') . ' - ' . $vendor->name)

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div style="min-width: 0; flex: 1;">
        <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
            <span style="background: rgba(245, 158, 11, 0.15); color: var(--primary); width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                <i class="fa-solid fa-location-dot"></i>
            </span>
            <span>{{ __('Location Management') }}</span>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0; word-break: break-word;">
            {{ __('Manage all your branches from a single administrative account with shared menus and dedicated branch settings') }}
        </p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('newLocationModal').style.display='flex'" style="font-size: 0.88rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; border-radius: 12px; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.3);">
        <i class="fa-solid fa-plus"></i> {{ __('Add Branch') }}
    </button>
</div>

<div class="grid-2" style="gap: 1.5rem;">
    @foreach($locations as $loc)
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.25rem, 3vw, 1.65rem); box-shadow: var(--shadow-card); position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between;">
            <div style="position: absolute; top: 0; left: 0; width: 5px; height: 100%; background: linear-gradient(to bottom, var(--primary), #ea580c);"></div>

            <div>
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
                    <div style="min-width: 0; flex: 1;">
                        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0; word-break: break-word;">
                            {{ $loc->name }}
                        </h3>
                        <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.35rem; display: flex; align-items: center; gap: 0.4rem; word-break: break-word;">
                            <i class="fa-solid fa-map-pin" style="color: var(--primary); flex-shrink: 0;"></i>
                            <span>{{ $loc->address ?? __('Address not specified') }}</span>
                        </div>
                    </div>
                    <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); padding: 0.3rem 0.65rem; border-radius: 8px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem; flex-shrink: 0;">
                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span>
                        {{ __('ACTIVE') }}
                    </span>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 140px), 1fr)); gap: 0.75rem; margin-bottom: 1.25rem; font-size: 0.85rem; background: var(--bg-body); border: 1px solid var(--border-color); padding: 1rem; border-radius: 14px; color: var(--text-main);">
                    <div>
                        <span style="color: var(--text-muted); display: block; font-size: 0.72rem; text-transform: uppercase; font-weight: 700;">{{ __('Phone') }}</span>
                        <strong style="word-break: break-word;">{{ $loc->phone ?? '—' }}</strong>
                    </div>
                    <div>
                        <span style="color: var(--text-muted); display: block; font-size: 0.72rem; text-transform: uppercase; font-weight: 700;">WhatsApp</span>
                        <strong style="word-break: break-word;">{{ $loc->whatsapp_number ?? '—' }}</strong>
                    </div>
                    <div>
                        <span style="color: var(--text-muted); display: block; font-size: 0.72rem; text-transform: uppercase; font-weight: 700;">{{ __('Tables') }}</span>
                        <strong>{{ $loc->table_count }} {{ __('tables') }}</strong>
                    </div>
                    <div>
                        <span style="color: var(--text-muted); display: block; font-size: 0.72rem; text-transform: uppercase; font-weight: 700;">{{ __('Min. Order') }}</span>
                        <strong style="color: #10b981; font-family: 'Outfit', sans-serif;">{{ number_format($loc->minimum_order_amount) }} {{ $vendor->currency }}</strong>
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.6rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
                <a href="{{ route('client.menu', ['vendor_slug' => $vendor->slug, 'location_slug' => $loc->slug]) }}" target="_blank" class="btn btn-secondary" style="font-size: 0.82rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.45rem; border-radius: 10px;">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> {{ __('View Branch Menu') }}
                </a>
            </div>
        </div>
    @endforeach
</div>

<!-- Modal Create Location -->
<div id="newLocationModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 100; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card modal-box-responsive" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; width: 100%; max-width: min(520px, 94vw); max-height: 90vh; overflow-y: auto; box-sizing: border-box; padding: clamp(1.25rem, 3vw, 1.85rem); box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.35rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.85rem;">
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.6rem;">
                <span style="color: var(--primary);"><i class="fa-solid fa-location-dot"></i></span>
                <span>{{ __('Add New Branch') }}</span>
            </h3>
            <button onclick="document.getElementById('newLocationModal').style.display='none'" style="background: none; border: none; color: var(--text-muted); font-size: 1.25rem; cursor: pointer; padding: 0.25rem;">✕</button>
        </div>

        <form action="{{ route('admin.locations.store') }}" method="POST">
            @csrf
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Branch Name') }} <span style="color: #ef4444;">*</span></label>
                <input type="text" name="name" required placeholder="e.g. Downtown Branch or Dilijan Resort" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none; font-weight: 600;">
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Address') }}</label>
                <input type="text" name="address" placeholder="e.g. 15 Myasnikyan Ave, Yerevan" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none; font-weight: 600;">
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr)); gap: 0.85rem; margin-bottom: 1rem;">
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Phone Number') }}</label>
                    <input type="text" name="phone" placeholder="+374 10 123456" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none; font-weight: 600;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('WhatsApp Order Number') }}</label>
                    <input type="text" name="whatsapp_number" placeholder="+374 91 123456" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none; font-weight: 600;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr)); gap: 0.85rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Table Count') }} <span style="color: #ef4444;">*</span></label>
                    <input type="number" name="table_count" value="20" required min="1" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none; font-weight: 600;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Minimum Order (֏)') }}</label>
                    <input type="number" name="minimum_order_amount" value="0" min="0" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none; font-weight: 600;">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; flex-wrap: wrap;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('newLocationModal').style.display='none'" style="border-radius: 12px; font-weight: 600;">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary" style="border-radius: 12px; font-weight: 700; padding: 0.7rem 1.6rem;">{{ __('Create Branch') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
