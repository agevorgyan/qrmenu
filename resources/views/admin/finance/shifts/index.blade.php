@extends('layouts.app')

@section('title', __('Cash Shifts') . ' - ' . config('app.name'))

@section('content')
<div x-data="{ openModalOpen: false }" style="display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Header -->
    <div class="card" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fa-solid fa-business-time" style="color: #6366f1;"></i>
                    {{ __('Cash Register Shifts & Tills') }}
                </h1>
                <p style="margin: 0.25rem 0 0; color: var(--text-muted); font-size: 0.85rem;">
                    {{ __('Monitor active register sessions, blind cash counting reconciliations, and over/short variances.') }}
                </p>
            </div>

            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <button type="button" @click="openModalOpen = true" class="btn btn-primary" style="gap: 0.5rem;">
                    <i class="fa-solid fa-play"></i>
                    <span>{{ __('Open New Shift') }}</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Shifts Table -->
    <div class="card" style="padding: 1.5rem;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Shift #') }}</th>
                        <th>{{ __('Register / Branch') }}</th>
                        <th>{{ __('Cashier') }}</th>
                        <th>{{ __('Opened At') }}</th>
                        <th>{{ __('Closed At') }}</th>
                        <th style="text-align: right;">{{ __('Opening Float') }}</th>
                        <th style="text-align: right;">{{ __('Expected') }}</th>
                        <th style="text-align: right;">{{ __('Actual Count') }}</th>
                        <th style="text-align: right;">{{ __('Discrepancy') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th style="text-align: right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shifts as $shift)
                        <tr>
                            <td style="font-weight: 800; font-family: monospace;">#{{ $shift->id }}</td>
                            <td>
                                <div style="font-weight: 700;">{{ $shift->cashRegister?->name ?? 'Default Register' }}</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $shift->location?->name ?? '—' }}</div>
                            </td>
                            <td>{{ $shift->user?->name ?? '—' }}</td>
                            <td style="font-size: 0.85rem;">{{ $shift->opened_at?->format('Y-m-d H:i') }}</td>
                            <td style="font-size: 0.85rem;">{{ $shift->closed_at ? $shift->closed_at->format('Y-m-d H:i') : '—' }}</td>
                            <td style="text-align: right; font-weight: 600;">{{ number_format($shift->opening_cash, 2) }}</td>
                            <td style="text-align: right; font-weight: 600;">{{ number_format($shift->expected_cash, 2) }}</td>
                            <td style="text-align: right; font-weight: 700;">{{ $shift->actual_cash !== null ? number_format($shift->actual_cash, 2) : '—' }}</td>
                            <td style="text-align: right; font-weight: 800;">
                                @if($shift->status === 'closed')
                                    @if(abs($shift->cash_discrepancy) < 0.01)
                                        <span style="color: #10b981;">0.00</span>
                                    @elseif($shift->cash_discrepancy > 0)
                                        <span style="color: #10b981;">+{{ number_format($shift->cash_discrepancy, 2) }}</span>
                                    @else
                                        <span style="color: #ef4444;">{{ number_format($shift->cash_discrepancy, 2) }}</span>
                                    @endif
                                @else
                                    <span style="color: var(--text-muted);">—</span>
                                @endif
                            </td>
                            <td>
                                @if($shift->status === 'open')
                                    <span class="badge badge-emerald">{{ __('Open') }}</span>
                                @else
                                    <span class="badge badge-secondary">{{ __('Closed') }}</span>
                                    @if($shift->manager_approved_by)
                                        <span class="badge badge-emerald" title="{{ __('Approved by Manager') }}"><i class="fa-solid fa-check"></i></span>
                                    @endif
                                @endif
                            </td>
                            <td style="text-align: right;">
                                <a href="{{ route('admin.finance.shifts.show', $shift) }}" class="btn btn-xs btn-secondary">
                                    {{ __('Details & Z-Report') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">
                                {{ __('No cash shifts recorded.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top: 1rem;">
            {{ $shifts->links() }}
        </div>
    </div>

    <!-- Open Shift Modal -->
    <div x-show="openModalOpen" x-cloak style="position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);">
        <div @click.away="openModalOpen = false" class="card" style="width: 100%; max-width: 480px; padding: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                    {{ __('Open Cash Register Shift') }}
                </h3>
                <button type="button" @click="openModalOpen = false" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--text-muted);">&times;</button>
            </div>

            <form method="POST" action="{{ route('admin.finance.shifts.open') }}" style="display: flex; flex-direction: column; gap: 1rem;">
                @csrf

                <div>
                    <label class="form-label">{{ __('Select Cash Register') }} *</label>
                    <select name="cash_register_id" class="form-control" required>
                        @foreach($registers as $reg)
                            @if(!$reg->hasActiveShift())
                                <option value="{{ $reg->id }}">{{ $reg->name }} ({{ $reg->code }}) — {{ $reg->location?->name }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label">{{ __('Initial Cash Float') }} *</label>
                    <input type="number" step="0.01" min="0" name="opening_float" class="form-control" placeholder="e.g. 50000" required>
                    <span style="font-size: 0.75rem; color: var(--text-muted);">{{ __('Physical cash placed in drawer before opening.') }}</span>
                </div>

                <div>
                    <label class="form-label">{{ __('Opening Notes') }}</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Morning opening shift"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem;">
                    <button type="button" @click="openModalOpen = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Open Shift') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

