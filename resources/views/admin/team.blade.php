@extends('layouts.app')

@section('title', __('Team & Roles') . ' - ' . $vendor->name)

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div style="min-width: 0; flex: 1;">
        <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
            <span style="background: rgba(245, 158, 11, 0.15); color: var(--primary); width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                <i class="fa-solid fa-users"></i>
            </span>
            <span>{{ __('Team & Role Management') }}</span>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0; word-break: break-word;">
            {{ __('Invite managers and staff members with branch-specific permissions and assigned roles') }}
        </p>
    </div>
    @can('team.manage')
    <button class="btn btn-primary" onclick="document.getElementById('newMemberModal').style.display='flex'" style="font-size: 0.88rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; border-radius: 12px; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.3);">
        <i class="fa-solid fa-user-plus"></i> {{ __('Invite Member') }}
    </button>
    @endcan
</div>

<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 0; overflow: hidden; box-shadow: var(--shadow-card);">
    <div class="responsive-table-wrapper">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
            <thead>
                <tr style="background: var(--bg-body); border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em;">
                    <th style="padding: 1rem 1.25rem;">{{ __('Name & Email') }}</th>
                    <th style="padding: 1rem 1.25rem;">{{ __('Role') }}</th>
                    <th style="padding: 1rem 1.25rem;">{{ __('Assigned Branch') }}</th>
                    <th style="padding: 1rem 1.25rem;">{{ __('Phone') }}</th>
                    <th style="padding: 1rem 1.25rem; text-align: right;">{{ __('Joined') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($team as $member)
                    <tr style="border-bottom: 1px solid var(--border-color); color: var(--text-main); transition: background 0.15s ease;">
                        <td style="padding: 1rem 1.25rem;">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(245, 158, 11, 0.15); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.95rem; flex-shrink: 0;">
                                    {{ mb_substr($member->name, 0, 1) }}
                                </div>
                                <div style="min-width: 0;">
                                    <div class="truncate-text" style="font-weight: 700; color: var(--text-main); max-width: 180px;">{{ $member->name }}</div>
                                    <div class="truncate-text" style="font-size: 0.78rem; color: var(--text-muted); max-width: 180px;">{{ $member->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td style="padding: 1rem 1.25rem; white-space: nowrap;">
                            @if($member->role == 'vendor_owner')
                                <span style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6; border: 1px solid rgba(139, 92, 246, 0.3); padding: 0.25rem 0.65rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                                    <i class="fa-solid fa-crown"></i> {{ __('Owner') }}
                                </span>
                            @elseif($member->role == 'manager')
                                <span style="background: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); padding: 0.25rem 0.65rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                                    <i class="fa-solid fa-user-tie"></i> {{ __('Manager') }}
                                </span>
                            @elseif($member->role == 'chef')
                                <span style="background: rgba(234, 88, 12, 0.15); color: #ea580c; border: 1px solid rgba(234, 88, 12, 0.3); padding: 0.25rem 0.65rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                                    <i class="fa-solid fa-fire-burner"></i> {{ __('Chef') }}
                                </span>
                            @elseif($member->role == 'cashier')
                                <span style="background: rgba(14, 165, 233, 0.15); color: #0ea5e9; border: 1px solid rgba(14, 165, 233, 0.3); padding: 0.25rem 0.65rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                                    <i class="fa-solid fa-cash-register"></i> {{ __('Cashier') }}
                                </span>
                            @else
                                <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); padding: 0.25rem 0.65rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                                    <i class="fa-solid fa-kitchen-set"></i> {{ __('Staff') }}
                                </span>
                            @endif
                        </td>
                        <td style="padding: 1rem 1.25rem; color: var(--text-main); white-space: nowrap;">
                            <span style="background: var(--bg-body); border: 1px solid var(--border-color); padding: 0.25rem 0.6rem; border-radius: 8px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.35rem;">
                                <i class="fa-solid fa-location-dot" style="color: var(--primary);"></i> {{ $member->location?->name ?? __('All Branches') }}
                            </span>
                        </td>
                        <td style="padding: 1rem 1.25rem; color: var(--text-muted); font-size: 0.88rem; font-weight: 600; white-space: nowrap;">
                            {{ $member->phone ?? '—' }}
                        </td>
                        <td style="padding: 1rem 1.25rem; color: var(--text-muted); font-size: 0.82rem; text-align: right; white-space: nowrap;">
                            {{ $member->created_at->format('M d, Y') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                            {{ __('No team members registered yet.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Invite Member -->
<div id="newMemberModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 100; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card modal-box-responsive" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; width: 100%; max-width: min(500px, 94vw); max-height: 90vh; overflow-y: auto; box-sizing: border-box; padding: clamp(1.25rem, 3vw, 1.85rem); box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.35rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.85rem;">
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.6rem;">
                <span style="color: var(--primary);"><i class="fa-solid fa-user-plus"></i></span>
                <span>{{ __('Invite Team Member') }}</span>
            </h3>
            <button onclick="document.getElementById('newMemberModal').style.display='none'" style="background: none; border: none; color: var(--text-muted); font-size: 1.25rem; cursor: pointer; padding: 0.25rem;">✕</button>
        </div>

        <form action="{{ route('admin.team.store') }}" method="POST">
            @csrf
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Full Name') }} <span style="color: #ef4444;">*</span></label>
                <input type="text" name="name" required placeholder="e.g. John Doe" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none; font-weight: 600;">
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Email Address') }} <span style="color: #ef4444;">*</span></label>
                <input type="email" name="email" required placeholder="john@example.com" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none; font-weight: 600;">
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr)); gap: 0.85rem; margin-bottom: 1rem;">
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Role') }} <span style="color: #ef4444;">*</span></label>
                    <select name="role" required style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none; font-weight: 600;">
                        <option value="waiter">{{ __('Waiter') }}</option>
                        <option value="cashier">{{ __('Cashier') }}</option>
                        <option value="chef">{{ __('Chef') }}</option>
                        <option value="manager">{{ __('Branch Manager') }}</option>
                        <option value="staff">{{ __('Staff Member') }}</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Branch') }}</label>
                    <select name="location_id" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none; font-weight: 600;">
                        <option value="">{{ __('All Branches') }}</option>
                        @foreach($locations as $l)
                            <option value="{{ $l->id }}">{{ $l->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">{{ __('Temporary Password') }} <span style="color: #ef4444;">*</span></label>
                <input type="password" name="password" required value="password" style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.95rem; outline: none; font-weight: 600;">
                <span style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                    {{ __('Members can change their password after signing in.') }}
                </span>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; flex-wrap: wrap;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('newMemberModal').style.display='none'" style="border-radius: 12px; font-weight: 600;">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary" style="border-radius: 12px; font-weight: 700; padding: 0.7rem 1.6rem;">{{ __('Invite Member') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
