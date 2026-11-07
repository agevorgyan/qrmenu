@extends('layouts.app')

@section('title', __('Trial Balance') . ' - ' . config('app.name'))

@section('styles')
<style>
    @media print {
        .no-print {
            display: none !important;
        }
        .printable-statement {
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            background: #ffffff !important;
            color: #000000 !important;
            width: 100% !important;
            max-width: 100% !important;
        }
    }
</style>
@endsection

@section('content')
<div style="max-width: 960px; margin: 0 auto; display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Header -->
    <div class="card no-print" style="padding: 1.5rem;">
        <div class="page-header-flex" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <a href="{{ route('admin.finance.reports.index') }}" style="color: var(--text-muted); font-size: 0.85rem; text-decoration: none; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.5rem;">
                    <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Reports') }}
                </a>
                <h1 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fa-solid fa-book-journal-whills" style="color: #8b5cf6;"></i>
                    {{ __('Trial Balance Report') }}
                </h1>
                <p style="margin: 0.25rem 0 0; color: var(--text-muted); font-size: 0.85rem;">
                    {{ __('As of:') }} {{ $trialBalance['as_of_date'] }} | {{ __('Currency:') }} {{ $trialBalance['currency'] }}
                </p>
            </div>

            <div class="page-header-actions" style="display: flex; align-items: center; gap: 0.75rem;">
                <a href="{{ route('admin.finance.reports.export', ['type' => 'trial-balance', 'start_date' => $asOfDate->toDateString(), 'end_date' => $asOfDate->toDateString(), 'location_id' => $locationId]) }}" class="btn btn-secondary">
                    <i class="fa-solid fa-download"></i> {{ __('Export CSV') }}
                </a>
                <button type="button" onclick="window.print()" class="btn btn-primary">
                    <i class="fa-solid fa-print"></i> {{ __('Print Trial Balance') }}
                </button>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="{{ route('admin.finance.reports.trial_balance') }}" class="filter-bar-flex" style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-top: 1.25rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
            <select name="location_id" class="form-control" style="width: auto;">
                <option value="">{{ __('All Branches / Consolidated') }}</option>
                @foreach($locations as $loc)
                    <option value="{{ $loc->id }}" {{ $locationId == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                @endforeach
            </select>

            <input type="date" name="as_of_date" class="form-control" style="width: auto;" value="{{ $asOfDate->toDateString() }}">
            <button type="submit" class="btn btn-secondary">{{ __('Update Date') }}</button>
        </form>
    </div>

    <!-- Trial Balance Paper Card -->
    <div class="card printable-statement" style="padding: 2.5rem; background: var(--card-bg);">
        <div class="statement-header" style="text-align: center; border-bottom: 2px solid var(--border-color); padding-bottom: 1.5rem; margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--text-main);">{{ auth()->user()?->vendor?->name }}</h2>
            <div class="statement-title" style="font-size: 1.15rem; font-weight: 700; color: var(--text-muted); margin-top: 0.25rem;">{{ __('TRIAL BALANCE') }}</div>
            <div class="statement-subtitle" style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('As of') }} {{ $asOfDate->format('F d, Y') }} | {{ __('Currency:') }} {{ $trialBalance['currency'] }}
            </div>
            <div class="print-only" style="font-size: 0.75rem; color: #666; margin-top: 0.35rem;">
                {{ __('Generated on:') }} {{ now()->format('Y-m-d H:i') }} | {{ __('Accountant:') }} {{ auth()->user()?->name }}
            </div>
        </div>

        <div class="table-responsive">
            <table class="table" style="min-width: 800px;">
                <thead>
                    <tr>
                        <th style="width: 120px;">{{ __('Account Code') }}</th>
                        <th style="min-width: 250px;">{{ __('Account Title') }}</th>
                        <th style="width: 140px;">{{ __('Account Classification') }}</th>
                        <th style="min-width: 140px; text-align: right;">{{ __('Debit Balance (Dr)') }}</th>
                        <th style="min-width: 140px; text-align: right;">{{ __('Credit Balance (Cr)') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($trialBalance['rows'] as $row)
                        <tr>
                            <td style="font-family: monospace; font-weight: 800;">{{ $row['code'] }}</td>
                            <td style="font-weight: 600;">{{ __($row['name']) }}</td>
                            <td><span class="badge badge-secondary">{{ __(ucfirst($row['type'])) }}</span></td>
                            <td style="text-align: right; font-family: monospace; font-weight: 700; color: #10b981;">
                                {{ $row['net_debit'] > 0 ? number_format($row['net_debit'], 2) : '—' }}
                            </td>
                            <td style="text-align: right; font-family: monospace; font-weight: 700; color: #3b82f6;">
                                {{ $row['net_credit'] > 0 ? number_format($row['net_credit'], 2) : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">
                                {{ __('No posted journal entries found up to this date.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr style="border-top: 2px solid var(--border-color); font-weight: 900; font-size: 1.15rem;">
                        <td colspan="3" style="text-align: right; text-transform: uppercase;">{{ __('TOTAL TRIAL BALANCE:') }}</td>
                        <td style="text-align: right; font-family: monospace; color: #10b981;">
                            {{ number_format($trialBalance['total_debit'], 2) }}
                        </td>
                        <td style="text-align: right; font-family: monospace; color: #3b82f6;">
                            {{ number_format($trialBalance['total_credit'], 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div style="margin-top: 1.5rem; padding: 1rem; border-radius: 8px; background: {{ $trialBalance['is_balanced'] ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)' }}; border: 1px solid {{ $trialBalance['is_balanced'] ? '#10b981' : '#ef4444' }}; display: flex; align-items: center; justify-content: space-between;">
            <div style="font-weight: 800; color: {{ $trialBalance['is_balanced'] ? '#10b981' : '#ef4444' }}; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid {{ $trialBalance['is_balanced'] ? 'fa-circle-check' : 'fa-triangle-exclamation' }}"></i>
                {{ $trialBalance['is_balanced'] ? __('Trial Balance strictly balanced: Total Debit = Total Credit') : __('Out of balance!') }}
            </div>
            <div style="font-size: 0.85rem; color: var(--text-muted);">
                {{ __('Difference:') }} {{ number_format($trialBalance['difference'], 2) }} {{ $trialBalance['currency'] }}
            </div>
        </div>

        <!-- Signatures Block for Print -->
        <div class="print-signatures statement-signatures">
            <div class="sig-col">
                <div class="sig-line"></div>
                <div class="sig-label">{{ __('Prepared by (Accountant):') }} {{ auth()->user()?->name }}</div>
            </div>
            <div class="sig-col">
                <div class="sig-line"></div>
                <div class="sig-label">{{ __('Approved by (Director / General Manager)') }}</div>
            </div>
        </div>
    </div>
</div>
@endsection

