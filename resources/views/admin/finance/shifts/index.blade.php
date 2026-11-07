@extends('layouts.app')

@section('title', __('Cash Shifts') . ' - ' . config('app.name'))

@section('content')
<div x-data="{ openModalOpen: false }" style="max-width: 1400px; margin: 0 auto; width: 100%; display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Page Header -->
    <div class="card" style="padding: 1.5rem; margin-bottom: 0;">
        <div class="page-header-flex" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(99, 102, 241, 0.12); color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                    <i class="fa-solid fa-business-time"></i>
                </div>
                <div>
                    <h1 style="font-size: clamp(1.25rem, 2.5vw, 1.65rem); font-weight: 800; margin: 0; color: var(--text-main); line-height: 1.2;">
                        {{ __('Cash Register Shifts & Tills') }}
                    </h1>
                    <p style="margin: 0.35rem 0 0; color: var(--text-muted); font-size: 0.875rem;">
                        {{ __('Monitor active register sessions, blind cash counting reconciliations, and over/short variances.') }}
                    </p>
                </div>
            </div>

            <div class="page-header-actions" style="display: flex; align-items: center; gap: 0.75rem;">
                <button type="button" @click="openModalOpen = true" class="btn btn-primary" style="padding: 0.65rem 1.25rem;">
                    <i class="fa-solid fa-play"></i>
                    <span>{{ __('Open New Shift') }}</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Shifts Table Card -->
    <div class="card card-flush" style="border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-card);">
        <div class="table-responsive" style="margin-bottom: 0; border: none; border-radius: 0;">
            <table class="table" style="min-width: 1120px;">
                <thead>
                    <tr>
                        <th style="width: 90px;">{{ __('Shift #') }}</th>
                        <th style="min-width: 180px;">{{ __('Register / Branch') }}</th>
                        <th style="min-width: 160px;">{{ __('Cashier') }}</th>
                        <th style="min-width: 140px;">{{ __('Opened At') }}</th>
                        <th style="min-width: 140px;">{{ __('Closed At') }}</th>
                        <th style="min-width: 130px; text-align: right;">{{ __('Opening Float') }}</th>
                        <th style="min-width: 130px; text-align: right;">{{ __('Expected') }}</th>
                        <th style="min-width: 130px; text-align: right;">{{ __('Actual Count') }}</th>
                        <th style="min-width: 120px; text-align: right;">{{ __('Discrepancy') }}</th>
                        <th style="width: 120px; text-align: center;">{{ __('Status') }}</th>
                        <th style="min-width: 130px; text-align: right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shifts as $shift)
                        <tr>
                            <td>
                                <span style="font-weight: 800; font-family: monospace; font-size: 0.95rem; background: rgba(99, 102, 241, 0.1); color: #6366f1; padding: 0.2rem 0.5rem; border-radius: 6px;">
                                    #{{ $shift->id }}
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.65rem;">
                                    <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(245, 158, 11, 0.1); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; flex-shrink: 0;">
                                        <i class="fa-solid fa-cash-register"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight: 700; color: var(--text-main); font-size: 0.9rem;">
                                            {{ $shift->cashRegister?->name ?? 'Default Register' }}
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.25rem;">
                                            <i class="fa-solid fa-location-dot" style="font-size: 0.7rem; color: #06b6d4;"></i>
                                            <span>{{ $shift->location?->name ?? '—' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.45rem;">
                                    <div style="width: 24px; height: 24px; border-radius: 50%; background: rgba(59, 130, 246, 0.12); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700;">
                                        {{ mb_substr($shift->user?->name ?? '?', 0, 1) }}
                                    </div>
                                    <span style="font-weight: 600; font-size: 0.875rem;">{{ $shift->user?->name ?? '—' }}</span>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 0.825rem; color: var(--text-main); font-weight: 500;">
                                    {{ $shift->opened_at?->format('Y-m-d') }}
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">
                                    {{ $shift->opened_at?->format('H:i') }}
                                </div>
                            </td>
                            <td>
                                @if($shift->closed_at)
                                    <div style="font-size: 0.825rem; color: var(--text-main); font-weight: 500;">
                                        {{ $shift->closed_at->format('Y-m-d') }}
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">
                                        {{ $shift->closed_at->format('H:i') }}
                                    </div>
                                @else
                                    <span style="color: var(--text-muted); font-size: 0.85rem;">—</span>
                                @endif
                            </td>
                            <td style="text-align: right; font-weight: 600; font-size: 0.9rem; color: var(--text-muted);">
                                {{ number_format($shift->opening_float ?? $shift->opening_cash ?? 0, 2) }}
                            </td>
                            <td style="text-align: right; font-weight: 700; font-size: 0.9rem; color: var(--text-main);">
                                {{ number_format($shift->expected_cash, 2) }}
                            </td>
                            <td style="text-align: right; font-weight: 700; font-size: 0.9rem;">
                                @if(($shift->counted_cash ?? $shift->actual_cash) !== null)
                                    <span style="color: #3b82f6;">{{ number_format($shift->counted_cash ?? $shift->actual_cash, 2) }}</span>
                                @else
                                    <span style="color: var(--text-muted);">—</span>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                @if($shift->status === 'closed')
                                    @php $disc = (float)($shift->discrepancy ?? $shift->cash_discrepancy ?? 0); @endphp
                                    @if(abs($disc) < 0.01)
                                        <span class="badge badge-emerald" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;">
                                            <i class="fa-solid fa-check"></i> 0.00
                                        </span>
                                    @elseif($disc > 0)
                                        <span class="badge badge-emerald" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;">
                                            +{{ number_format($disc, 2) }}
                                        </span>
                                    @else
                                        <span class="badge badge-rose" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;">
                                            {{ number_format($disc, 2) }}
                                        </span>
                                    @endif
                                @else
                                    <span style="color: var(--text-muted); font-size: 0.85rem;">—</span>
                                @endif
                            </td>
                            <td style="text-align: center;">
                                @if($shift->status === 'open')
                                    <span class="badge badge-emerald" style="display: inline-flex; align-items: center; gap: 0.35rem;">
                                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981; display: inline-block;"></span>
                                        {{ __('Open') }}
                                    </span>
                                @else
                                    <div style="display: flex; align-items: center; justify-content: center; gap: 0.3rem;">
                                        <span class="badge badge-secondary">{{ __('Closed') }}</span>
                                        @if($shift->manager_approved_by)
                                            <span class="badge badge-emerald" title="{{ __('Approved by Manager') }}" style="padding: 0.2rem 0.35rem;">
                                                <i class="fa-solid fa-check"></i>
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                <a href="{{ route('admin.finance.shifts.show', $shift) }}" class="btn btn-sm btn-secondary" style="padding: 0.4rem 0.75rem;">
                                    <i class="fa-solid fa-receipt"></i>
                                    <span>{{ __('Details') }}</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" style="text-align: center; color: var(--text-muted); padding: 3.5rem 1.5rem;">
                                <div style="width: 54px; height: 54px; border-radius: 16px; background: rgba(99, 102, 241, 0.08); color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 1rem;">
                                    <i class="fa-solid fa-business-time"></i>
                                </div>
                                <div style="font-weight: 700; font-size: 1rem; color: var(--text-main); margin-bottom: 0.25rem;">
                                    {{ __('No cash shifts recorded.') }}
                                </div>
                                <p style="font-size: 0.85rem; margin: 0 0 1rem; color: var(--text-muted);">
                                    {{ __('Open a shift to start recording register transactions and blind cash counts.') }}
                                </p>
                                <button type="button" @click="openModalOpen = true" class="btn btn-sm btn-primary">
                                    <i class="fa-solid fa-play"></i> {{ __('Open New Shift') }}
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($shifts->hasPages())
            <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); background: var(--bg-card);">
                {{ $shifts->links() }}
            </div>
        @endif
    </div>

    <!-- Open Shift Modal -->
    <div x-show="openModalOpen" x-cloak class="modern-modal-overlay">
        <div @click.away="openModalOpen = false" class="modern-modal-box" style="max-width: 520px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(99, 102, 241, 0.12); color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
                        <i class="fa-solid fa-play"></i>
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                            {{ __('Open Cash Register Shift') }}
                        </h3>
                        <p style="margin: 0.15rem 0 0; font-size: 0.8rem; color: var(--text-muted);">
                            {{ __('Assign employee and specify opening cash float') }}
                        </p>
                    </div>
                </div>
                <button type="button" @click="openModalOpen = false" class="btn-icon btn-secondary" style="border: none;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('admin.finance.shifts.open') }}" style="display: flex; flex-direction: column; gap: 1.15rem;">
                @csrf

                <div>
                    <label class="form-label">{{ __('Cash Register') }} *</label>
                    <select name="cash_register_id" class="form-control" required>
                        <option value="">{{ __('Select Cash Register') }}</option>
                        @foreach($registers as $reg)
                            <option value="{{ $reg->id }}">{{ $reg->name }} ({{ $reg->code }}) - {{ $reg->location?->name ?? 'Default Location' }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label">{{ __('Cashier / Shift Owner') }} *</label>
                    <select name="user_id" class="form-control" required>
                        @foreach($users ?? [] as $u)
                            <option value="{{ $u->id }}" {{ auth()->id() === $u->id ? 'selected' : '' }}>
                                {{ $u->name }} ({{ $u->role }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Opening Float Amount') }} *</label>
                        <input type="number" step="0.01" min="0" name="opening_cash" class="form-control" placeholder="10000.00" value="10000.00" required>
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
                    <button type="button" @click="openModalOpen = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem;">{{ __('Start Shift') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
