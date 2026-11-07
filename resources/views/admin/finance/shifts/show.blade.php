@extends('layouts.app')

@section('title', __('Shift #') . $shift->id . ' - ' . config('app.name'))

@section('content')
<div x-data="{ closeModalOpen: false, movementModalOpen: false }" style="display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Breadcrumb & Header -->
    <div class="card" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <a href="{{ route('admin.finance.shifts.index') }}" style="color: var(--text-muted); font-size: 0.85rem; text-decoration: none; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.5rem;">
                    <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Cash Shifts') }}
                </a>
                <h1 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fa-solid fa-business-time" style="color: #6366f1;"></i>
                    {{ __('Cash Shift #') }}{{ $shift->id }}
                    @if($shift->status === 'open')
                        <span class="badge badge-emerald">{{ __('Open Session') }}</span>
                    @else
                        <span class="badge badge-secondary">{{ __('Closed Session') }}</span>
                    @endif
                </h1>
                <p style="margin: 0.25rem 0 0; color: var(--text-muted); font-size: 0.85rem;">
                    {{ __('Register') }}: <strong>{{ $shift->cashRegister?->name ?? 'Default Register' }}</strong> ({{ $shift->location?->name }}) | {{ __('Cashier') }}: <strong>{{ $shift->user?->name }}</strong>
                </p>
            </div>

            <div style="display: flex; align-items: center; gap: 0.75rem;">
                @if($shift->status === 'open')
                    <button type="button" @click="movementModalOpen = true" class="btn btn-secondary">
                        <i class="fa-solid fa-money-bill-transfer"></i> {{ __('Cash Movement / Drop') }}
                    </button>
                    <button type="button" @click="closeModalOpen = true" class="btn btn-primary">
                        <i class="fa-solid fa-lock"></i> {{ __('Blind Cash Count & Close') }}
                    </button>
                @else
                    <button type="button" onclick="window.print()" class="btn btn-secondary">
                        <i class="fa-solid fa-print"></i> {{ __('Print Z-Report') }}
                    </button>
                    @if(!$shift->manager_approved_by && abs($shift->cash_discrepancy) >= 0.01)
                        @can('finance.shifts.approve')
                            <form method="POST" action="{{ route('admin.finance.shifts.approve', $shift) }}" onsubmit="return confirm('Approve this shift variance and post discrepancy to General Ledger?')">
                                @csrf
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa-solid fa-check-double"></i> {{ __('Manager Variance Sign-off') }}
                                </button>
                            </form>
                        @endcan
                    @endif
                @endif
            </div>
        </div>
    </div>

    <!-- Shift Financial Reconciliation Formula Grid -->
    <div class="card" style="padding: 1.5rem;">
        <h3 style="margin-top: 0; font-size: 1.15rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-calculator" style="color: #10b981;"></i>
            {{ __('Shift Cash Reconciliation Breakdown') }}
        </h3>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">
            {{ __('Formula: Opening Float + Cash Payments + Cash In - Cash Refunds - Cash Out - Safe Drops = Expected Drawer Cash.') }}
        </p>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;">
            <!-- Opening Float -->
            <div style="padding: 1rem; border-radius: 10px; background: var(--bg-surface); border: 1px solid var(--border-color);">
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700;">{{ __('Opening Float') }}</div>
                <div style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-top: 0.25rem;">
                    {{ number_format($expectedBreakdown['opening_float'], 2) }}
                </div>
            </div>

            <!-- Cash Sales -->
            <div style="padding: 1rem; border-radius: 10px; background: var(--bg-surface); border: 1px solid var(--border-color);">
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700;">{{ __('(+) Cash Sales') }}</div>
                <div style="font-size: 1.25rem; font-weight: 800; color: #10b981; margin-top: 0.25rem;">
                    +{{ number_format($expectedBreakdown['cash_payments'], 2) }}
                </div>
            </div>

            <!-- Cash In -->
            <div style="padding: 1rem; border-radius: 10px; background: var(--bg-surface); border: 1px solid var(--border-color);">
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700;">{{ __('(+) Paid In') }}</div>
                <div style="font-size: 1.25rem; font-weight: 800; color: #10b981; margin-top: 0.25rem;">
                    +{{ number_format($expectedBreakdown['cash_in'], 2) }}
                </div>
            </div>

            <!-- Cash Refunds -->
            <div style="padding: 1rem; border-radius: 10px; background: var(--bg-surface); border: 1px solid var(--border-color);">
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700;">{{ __('(-) Cash Refunds') }}</div>
                <div style="font-size: 1.25rem; font-weight: 800; color: #ef4444; margin-top: 0.25rem;">
                    -{{ number_format($expectedBreakdown['cash_refunds'], 2) }}
                </div>
            </div>

            <!-- Cash Out -->
            <div style="padding: 1rem; border-radius: 10px; background: var(--bg-surface); border: 1px solid var(--border-color);">
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700;">{{ __('(-) Cash Payouts') }}</div>
                <div style="font-size: 1.25rem; font-weight: 800; color: #ef4444; margin-top: 0.25rem;">
                    -{{ number_format($expectedBreakdown['cash_out'], 2) }}
                </div>
            </div>

            <!-- Safe Drops -->
            <div style="padding: 1rem; border-radius: 10px; background: var(--bg-surface); border: 1px solid var(--border-color);">
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700;">{{ __('(-) Safe Drops') }}</div>
                <div style="font-size: 1.25rem; font-weight: 800; color: #f59e0b; margin-top: 0.25rem;">
                    -{{ number_format($expectedBreakdown['safe_drops'], 2) }}
                </div>
            </div>

            <!-- Expected Cash -->
            <div style="padding: 1rem; border-radius: 10px; background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.3);">
                <div style="font-size: 0.75rem; color: #6366f1; font-weight: 800;">{{ __('(=) EXPECTED CASH') }}</div>
                <div style="font-size: 1.35rem; font-weight: 800; color: #6366f1; margin-top: 0.25rem;">
                    {{ number_format($expectedBreakdown['expected_cash'], 2) }}
                </div>
            </div>

            <!-- Actual Counted Cash -->
            <div style="padding: 1rem; border-radius: 10px; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.3);">
                <div style="font-size: 0.75rem; color: #10b981; font-weight: 800;">{{ __('ACTUAL COUNTED') }}</div>
                <div style="font-size: 1.35rem; font-weight: 800; color: #10b981; margin-top: 0.25rem;">
                    {{ $shift->actual_cash !== null ? number_format($shift->actual_cash, 2) : __('Pending Count') }}
                </div>
            </div>

            <!-- Discrepancy -->
            <div style="padding: 1rem; border-radius: 10px; background: {{ abs($shift->cash_discrepancy) < 0.01 ? 'rgba(16, 185, 129, 0.08)' : 'rgba(239, 68, 68, 0.08)' }}; border: 1px solid {{ abs($shift->cash_discrepancy) < 0.01 ? '#10b981' : '#ef4444' }};">
                <div style="font-size: 0.75rem; color: {{ abs($shift->cash_discrepancy) < 0.01 ? '#10b981' : '#ef4444' }}; font-weight: 800;">{{ __('VARIANCE (OVER/SHORT)') }}</div>
                <div style="font-size: 1.35rem; font-weight: 800; color: {{ abs($shift->cash_discrepancy) < 0.01 ? '#10b981' : '#ef4444' }}; margin-top: 0.25rem;">
                    @if($shift->status === 'closed')
                        {{ $shift->cash_discrepancy >= 0 ? '+' : '' }}{{ number_format($shift->cash_discrepancy, 2) }}
                    @else
                        —
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Cash Movements Log -->
    <div class="card" style="padding: 1.5rem;">
        <h3 style="margin-top: 0; font-size: 1.15rem; font-weight: 800; color: var(--text-main);">
            <i class="fa-solid fa-list-check" style="color: #6366f1; margin-right: 0.5rem;"></i>
            {{ __('Shift Cash Movements Log') }}
        </h3>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Timestamp') }}</th>
                        <th>{{ __('Movement Type') }}</th>
                        <th style="text-align: right;">{{ __('Amount') }}</th>
                        <th>{{ __('Reason / Memo') }}</th>
                        <th>{{ __('Authorized By') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shift->cashMovements as $mov)
                        <tr>
                            <td>{{ $mov->created_at->format('Y-m-d H:i:s') }}</td>
                            <td>
                                @if($mov->type === 'cash_in')
                                    <span class="badge badge-emerald">{{ __('Cash In') }}</span>
                                @elseif($mov->type === 'cash_out')
                                    <span class="badge badge-rose">{{ __('Cash Out') }}</span>
                                @else
                                    <span class="badge badge-amber">{{ __('Safe Drop') }}</span>
                                @endif
                            </td>
                            <td style="text-align: right; font-weight: 800; color: {{ $mov->type === 'cash_in' ? '#10b981' : '#ef4444' }};">
                                {{ $mov->type === 'cash_in' ? '+' : '-' }}{{ number_format($mov->amount, 2) }}
                            </td>
                            <td>{{ $mov->reason }}</td>
                            <td>{{ $mov->user?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                                {{ __('No manual cash movements or safe drops recorded during this shift.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Official Z-Report (If Closed) -->
    @if($zReport)
        <div class="card" id="printable-z-report" style="padding: 2rem; max-width: 600px; margin: 0 auto; border: 2px dashed var(--border-color); font-family: monospace;">
            <div style="text-align: center; border-bottom: 1px dashed var(--border-color); padding-bottom: 1rem; margin-bottom: 1rem;">
                <h2 style="margin: 0; font-size: 1.3rem; font-weight: 800;">*** OFFICIAL Z-REPORT ***</h2>
                <div style="font-size: 0.9rem;">{{ $shift->vendor?->name }}</div>
                <div style="font-size: 0.8rem;">{{ $shift->location?->name }}</div>
                <div style="font-size: 0.8rem;">Z-REPORT REF: {{ $zReport['report_number'] }}</div>
            </div>

            <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                <span>SHIFT ID:</span> <strong>#{{ $shift->id }}</strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                <span>CASH REGISTER:</span> <strong>{{ $shift->cashRegister?->name }} ({{ $shift->cashRegister?->code }})</strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                <span>CASHIER:</span> <strong>{{ $shift->user?->name }}</strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                <span>OPENED AT:</span> <strong>{{ $shift->opened_at->format('Y-m-d H:i') }}</strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                <span>CLOSED AT:</span> <strong>{{ $shift->closed_at->format('Y-m-d H:i') }}</strong>
            </div>

            <div style="border-top: 1px dashed var(--border-color); padding-top: 0.75rem; margin-bottom: 1rem;">
                <div style="font-weight: 800; margin-bottom: 0.5rem;">CASH TILL RECONCILIATION ({{ $zReport['currency'] }}):</div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Opening Float:</span> <span>{{ number_format($zReport['cash_reconciliation']['opening_float'], 2) }}</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Cash Sales:</span> <span>+{{ number_format($zReport['cash_reconciliation']['cash_payments'], 2) }}</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Cash Paid In:</span> <span>+{{ number_format($zReport['cash_reconciliation']['cash_in'], 2) }}</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Cash Paid Out:</span> <span>-{{ number_format($zReport['cash_reconciliation']['cash_out'], 2) }}</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Safe Drops:</span> <span>-{{ number_format($zReport['cash_reconciliation']['safe_drops'], 2) }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-weight: 800; border-top: 1px solid var(--border-color); margin-top: 0.4rem; padding-top: 0.4rem;">
                    <span>EXPECTED CASH:</span> <span>{{ number_format($zReport['cash_reconciliation']['expected_cash'], 2) }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-weight: 800;">
                    <span>ACTUAL BLIND COUNT:</span> <span>{{ number_format($zReport['cash_reconciliation']['actual_cash'], 2) }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-weight: 800; color: {{ abs($zReport['cash_reconciliation']['discrepancy']) < 0.01 ? '#10b981' : '#ef4444' }};">
                    <span>CASH OVER / SHORT:</span> <span>{{ number_format($zReport['cash_reconciliation']['discrepancy'], 2) }}</span>
                </div>
            </div>

            <div style="border-top: 1px dashed var(--border-color); padding-top: 0.75rem;">
                <div style="font-weight: 800; margin-bottom: 0.5rem;">NON-CASH REVENUE SETTLEMENTS:</div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Card Payments:</span> <span>{{ number_format($zReport['payment_breakdown']['card_payments'], 2) }}</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Online / QR Payments:</span> <span>{{ number_format($zReport['payment_breakdown']['online_payments'], 2) }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-weight: 800; border-top: 1px solid var(--border-color); margin-top: 0.4rem; padding-top: 0.4rem;">
                    <span>TOTAL SHIFT SETTLEMENTS:</span> <span>{{ number_format($zReport['payment_breakdown']['total_revenue_settled'], 2) }} {{ $zReport['currency'] }}</span>
                </div>
            </div>

            <div style="text-align: center; margin-top: 1.5rem; border-top: 1px dashed var(--border-color); padding-top: 1rem; font-size: 0.75rem;">
                *** END OF Z-REPORT ***
            </div>
        </div>
    @endif

    <!-- Blind Cash Count & Close Modal -->
    <div x-show="closeModalOpen" x-cloak style="position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);">
        <div @click.away="closeModalOpen = false" class="card" style="width: 100%; max-width: 480px; padding: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                    {{ __('Blind Cash Count & Shift Close') }}
                </h3>
                <button type="button" @click="closeModalOpen = false" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--text-muted);">&times;</button>
            </div>

            <form method="POST" action="{{ route('admin.finance.shifts.close', $shift) }}" style="display: flex; flex-direction: column; gap: 1rem;">
                @csrf

                <div style="padding: 0.75rem; border-radius: 8px; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2); font-size: 0.85rem; color: var(--text-main);">
                    <i class="fa-solid fa-triangle-exclamation" style="color: #f59e0b; margin-right: 0.4rem;"></i>
                    {{ __('Enter the exact physical cash amount counted in the drawer. The system will compare it against transactions to compute any cash discrepancy.') }}
                </div>

                <div>
                    <label class="form-label">{{ __('Actual Physical Cash Counted') }} *</label>
                    <input type="number" step="0.01" min="0" name="actual_cash" class="form-control" placeholder="0.00" required autofocus>
                </div>

                <div>
                    <label class="form-label">{{ __('Closing Notes / Reason for Discrepancy') }}</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. End of dinner service count"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem;">
                    <button type="button" @click="closeModalOpen = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Submit Count & Close Shift') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Movement / Safe Drop Modal -->
    <div x-show="movementModalOpen" x-cloak style="position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);">
        <div @click.away="movementModalOpen = false" class="card" style="width: 100%; max-width: 480px; padding: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                    {{ __('Record Cash Movement / Drop') }}
                </h3>
                <button type="button" @click="movementModalOpen = false" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--text-muted);">&times;</button>
            </div>

            <form method="POST" action="{{ route('admin.finance.shifts.movement', $shift) }}" style="display: flex; flex-direction: column; gap: 1rem;">
                @csrf

                <div>
                    <label class="form-label">{{ __('Movement Type') }} *</label>
                    <select name="type" class="form-control" required>
                        <option value="safe_drop">{{ __('Safe Drop (Transfer excess till cash to safe)') }}</option>
                        <option value="cash_in">{{ __('Cash In (Deposit petty cash / float refill)') }}</option>
                        <option value="cash_out">{{ __('Cash Out (Emergency expense / cash payout)') }}</option>
                    </select>
                </div>

                <div>
                    <label class="form-label">{{ __('Amount') }} *</label>
                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="0.00" required>
                </div>

                <div>
                    <label class="form-label">{{ __('Reason / Justification') }} *</label>
                    <input type="text" name="reason" class="form-control" placeholder="e.g. Mid-day excess cash skim to safe" required>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem;">
                    <button type="button" @click="movementModalOpen = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Save Movement') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

