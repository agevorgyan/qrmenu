@extends('layouts.app')

@section('title', __('Cash Registers') . ' - ' . config('app.name'))

@section('content')
<div x-data="{ addModalOpen: false }" style="max-width: 1400px; margin: 0 auto; width: 100%; display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Page Header -->
    <div class="card" style="padding: 1.5rem; margin-bottom: 0;">
        <div class="page-header-flex" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(245, 158, 11, 0.12); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                    <i class="fa-solid fa-cash-register"></i>
                </div>
                <div>
                    <h1 style="font-size: clamp(1.25rem, 2.5vw, 1.65rem); font-weight: 800; margin: 0; color: var(--text-main); line-height: 1.2;">
                        {{ __('Cash Registers & Hardware Drawers') }}
                    </h1>
                    <p style="margin: 0.35rem 0 0; color: var(--text-muted); font-size: 0.875rem;">
                        {{ __('Manage physical POS terminals, default cash floats, user assignments, and drawer kick controls.') }}
                    </p>
                </div>
            </div>

            <div class="page-header-actions" style="display: flex; align-items: center; gap: 0.75rem;">
                <button type="button" @click="addModalOpen = true" class="btn btn-primary" style="padding: 0.65rem 1.25rem;">
                    <i class="fa-solid fa-plus"></i>
                    <span>{{ __('Add Cash Register') }}</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Registers Table Card -->
    <div class="card card-flush" style="border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-card);">
        <div class="table-responsive" style="margin-bottom: 0; border: none; border-radius: 0;">
            <table class="table" style="min-width: 980px;">
                <thead>
                    <tr>
                        <th style="min-width: 180px;">{{ __('Code / Name') }}</th>
                        <th style="min-width: 160px;">{{ __('Branch') }}</th>
                        <th style="min-width: 160px;">{{ __('Assigned Staff') }}</th>
                        <th style="min-width: 180px;">{{ __('Active Shift') }}</th>
                        <th style="min-width: 140px; text-align: right;">{{ __('Default Float') }}</th>
                        <th style="min-width: 150px; text-align: right;">{{ __('Live Till Balance') }}</th>
                        <th style="width: 100px; text-align: center;">{{ __('Status') }}</th>
                        <th style="min-width: 170px; text-align: right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($registers as $reg)
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(245, 158, 11, 0.1); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0;">
                                        <i class="fa-solid fa-calculator"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight: 800; color: var(--text-main); font-size: 0.95rem;">{{ $reg->name }}</div>
                                        <div style="font-size: 0.75rem; font-family: monospace; color: var(--text-muted); letter-spacing: 0.05em;">{{ $reg->code }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.4rem; color: var(--text-main); font-size: 0.875rem;">
                                    <i class="fa-solid fa-location-dot" style="color: #06b6d4; font-size: 0.8rem;"></i>
                                    <span>{{ $reg->location?->name ?? '—' }}</span>
                                </div>
                            </td>
                            <td>
                                @if($reg->assignedUser)
                                    <div style="display: flex; align-items: center; gap: 0.45rem;">
                                        <div style="width: 24px; height: 24px; border-radius: 50%; background: rgba(99, 102, 241, 0.15); color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700;">
                                            {{ mb_substr($reg->assignedUser->name, 0, 1) }}
                                        </div>
                                        <span style="font-weight: 600; color: var(--text-main);">{{ $reg->assignedUser->name }}</span>
                                    </div>
                                @else
                                    <span style="color: var(--text-muted); font-size: 0.85rem;">{{ __('Any Cashier') }}</span>
                                @endif
                            </td>
                            <td>
                                @if($reg->activeShift)
                                    <a href="{{ route('admin.finance.shifts.show', $reg->activeShift) }}" class="badge badge-emerald" style="text-decoration: none; padding: 0.35rem 0.65rem;">
                                        <i class="fa-solid fa-lock-open" style="font-size: 0.7rem;"></i>
                                        <span>{{ __('Shift #') }}{{ $reg->activeShift->id }}</span>
                                        <span style="font-weight: 400; opacity: 0.85;">({{ $reg->activeShift->user?->name }})</span>
                                    </a>
                                @else
                                    <span class="badge badge-secondary" style="padding: 0.35rem 0.65rem;">
                                        <i class="fa-solid fa-lock" style="font-size: 0.7rem;"></i>
                                        <span>{{ __('No Active Shift') }}</span>
                                    </span>
                                @endif
                            </td>
                            <td style="text-align: right; font-weight: 600; font-size: 0.9rem; color: var(--text-muted);">
                                {{ number_format($reg->opening_float, 2) }} {{ $reg->currency }}
                            </td>
                            <td style="text-align: right;">
                                <span style="font-weight: 800; font-size: 1.05rem; color: #10b981;">
                                    {{ number_format($reg->current_balance, 2) }} {{ $reg->currency }}
                                </span>
                            </td>
                            <td style="text-align: center;">
                                @if($reg->isActive())
                                    <span class="badge badge-emerald">{{ __('Active') }}</span>
                                @else
                                    <span class="badge badge-rose">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.45rem;">
                                    <form method="POST" action="{{ route('admin.finance.registers.kick-drawer', $reg) }}" onsubmit="return confirm('{{ __('Trigger drawer kick instruction?') }}')">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-secondary" title="{{ __('Trigger Drawer Kick') }}">
                                            <i class="fa-solid fa-bolt" style="color: #f59e0b;"></i>
                                            <span>{{ __('Kick') }}</span>
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.finance.registers.toggle', $reg) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-xs {{ $reg->isActive() ? 'btn-rose' : 'btn-emerald' }}">
                                            <i class="fa-solid {{ $reg->isActive() ? 'fa-ban' : 'fa-check' }}"></i>
                                            <span>{{ $reg->isActive() ? __('Deactivate') : __('Activate') }}</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 3.5rem 1.5rem;">
                                <div style="width: 54px; height: 54px; border-radius: 16px; background: rgba(245, 158, 11, 0.08); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 1rem;">
                                    <i class="fa-solid fa-cash-register"></i>
                                </div>
                                <div style="font-weight: 700; font-size: 1rem; color: var(--text-main); margin-bottom: 0.25rem;">
                                    {{ __('No cash registers defined.') }}
                                </div>
                                <p style="font-size: 0.85rem; margin: 0 0 1rem; color: var(--text-muted);">
                                    {{ __('Click Add Cash Register to create one.') }}
                                </p>
                                <button type="button" @click="addModalOpen = true" class="btn btn-sm btn-primary">
                                    <i class="fa-solid fa-plus"></i> {{ __('Add Cash Register') }}
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($registers->hasPages())
            <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); background: var(--bg-card);">
                {{ $registers->links() }}
            </div>
        @endif
    </div>

    <!-- Add Cash Register Modal -->
    <div x-show="addModalOpen" x-cloak class="modern-modal-overlay">
        <div @click.away="addModalOpen = false" class="modern-modal-box" style="max-width: 540px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(245, 158, 11, 0.12); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
                        <i class="fa-solid fa-cash-register"></i>
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                            {{ __('Create New Cash Register') }}
                        </h3>
                        <p style="margin: 0.15rem 0 0; font-size: 0.8rem; color: var(--text-muted);">
                            {{ __('Configure register device identifier and assigned staff') }}
                        </p>
                    </div>
                </div>
                <button type="button" @click="addModalOpen = false" class="btn-icon btn-secondary" style="border: none;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('admin.finance.registers.store') }}" style="display: flex; flex-direction: column; gap: 1.15rem;">
                @csrf

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Register Code') }} *</label>
                        <input type="text" name="code" class="form-control" placeholder="e.g. REG-MAIN-01" required style="font-family: monospace;">
                    </div>

                    <div>
                        <label class="form-label">{{ __('Register Name') }} *</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Main Bar POS 1" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Branch / Location') }}</label>
                        <select name="location_id" class="form-control">
                            <option value="">{{ __('All Locations') }}</option>
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Assigned Cashier / Staff') }}</label>
                        <select name="assigned_user_id" class="form-control">
                            <option value="">{{ __('Any Cashier') }}</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->role }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Default Opening Float') }} *</label>
                        <input type="number" step="0.01" min="0" name="opening_float" class="form-control" placeholder="10000.00" value="10000.00" required>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Currency') }} *</label>
                        <select name="currency" class="form-control" required>
                            <option value="AMD">AMD (֏) — Armenian Dram</option>
                            <option value="USD">USD ($) — US Dollar</option>
                            <option value="EUR">EUR (€) — Euro</option>
                            <option value="RUB">RUB (₽) — Russian Ruble</option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                    <button type="button" @click="addModalOpen = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem;">{{ __('Save Cash Register') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
