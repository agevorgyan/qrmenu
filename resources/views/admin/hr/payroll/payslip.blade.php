<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Payslip') }} - {{ $item->user?->name }} - {{ $item->period?->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #0f172a;
            --card: #1e293b;
            --border: #334155;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --primary: #22c55e;
        }
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text-main);
            margin: 0;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .payslip-container {
            width: 100%;
            max-width: 800px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.4);
        }
        .btn-print {
            background: var(--primary);
            color: #ffffff;
            border: none;
            padding: 0.65rem 1.5rem;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        th, td {
            padding: 0.75rem 0.5rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        th {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.05em;
        }
        .text-right {
            text-align: right;
        }
        @media print {
            body {
                background: #ffffff !important;
                color: #000000 !important;
                padding: 0;
            }
            .payslip-container {
                box-shadow: none !important;
                border: none !important;
                background: #ffffff !important;
                color: #000000 !important;
                padding: 0;
            }
            .btn-print, .no-print {
                display: none !important;
            }
            th, td {
                color: #000000 !important;
                border-bottom: 1px solid #cbd5e1 !important;
            }
            .stat-box {
                background: #f8fafc !important;
                border: 1px solid #cbd5e1 !important;
            }
        }
    </style>
</head>
<body>

    <div class="no-print" style="width: 100%; max-width: 800px; display: flex; justify-content: space-between; align-items: center;">
        <a href="javascript:history.back()" style="color: var(--text-muted); text-decoration: none; font-weight: 700;">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Back') }}
        </a>
        <button type="button" onclick="window.print()" class="btn-print">
            <i class="fa-solid fa-print"></i> {{ __('Print / Save as PDF') }}
        </button>
    </div>

    <div class="payslip-container">
        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid var(--border); padding-bottom: 1.5rem;">
            <div>
                <h1 style="font-size: 1.6rem; font-weight: 900; margin: 0; color: var(--text-main);">{{ $vendor->name }}</h1>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.35rem;">
                    {{ __('Official Salary & Tax Statement (Payslip)') }}
                </div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 1.15rem; font-weight: 800; color: var(--primary);">{{ $item->period?->name }}</div>
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">
                    {{ \Carbon\Carbon::parse($item->period?->start_date)->format('d.m.Y') }} - {{ \Carbon\Carbon::parse($item->period?->end_date)->format('d.m.Y') }}
                </div>
            </div>
        </div>

        <!-- Employee Info Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin: 1.5rem 0; padding: 1rem; background: rgba(255,255,255,0.02); border-radius: 12px; border: 1px solid var(--border);" class="stat-box">
            <div>
                <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Employee Name') }}</div>
                <div style="font-size: 1rem; font-weight: 800; color: var(--text-main); margin-top: 2px;">{{ $item->user?->name }}</div>
            </div>
            <div>
                <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Employee Code') }}</div>
                <div style="font-size: 1rem; font-weight: 800; font-family: monospace; color: var(--text-main); margin-top: 2px;">
                    {{ $item->user?->employeeProfile?->employee_code ?? ('EMP-' . str_pad($item->user_id, 4, '0', STR_PAD_LEFT)) }}
                </div>
            </div>
            <div>
                <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Department & Position') }}</div>
                <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-main); margin-top: 2px;">
                    {{ $item->user?->employeeProfile?->department?->name ?? '—' }} • {{ $item->user?->employeeProfile?->position?->title ?? 'Staff' }}
                </div>
            </div>
            <div>
                <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Hours Worked') }}</div>
                <div style="font-size: 1rem; font-weight: 800; color: var(--text-main); margin-top: 2px;">
                    {{ (float) $item->regular_hours }}h (OT: {{ (float) $item->overtime_hours }}h)
                </div>
            </div>
        </div>

        <!-- Breakdown: Earnings vs Deductions Side by Side -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
            <!-- Earnings -->
            <div>
                <h3 style="font-size: 0.9rem; text-transform: uppercase; font-weight: 800; color: var(--primary); margin: 0; padding-bottom: 0.5rem; border-bottom: 2px solid var(--primary);">
                    {{ __('Earnings') }}
                </h3>
                <table>
                    <tbody>
                        <tr>
                            <td>{{ __('Base Pay') }}</td>
                            <td class="text-right" style="font-weight: 700;">{{ number_format((float) $item->base_pay, 2) }} AMD</td>
                        </tr>
                        @if((float) $item->overtime_pay > 0)
                            <tr>
                                <td>{{ __('Overtime Pay (1.5x)') }}</td>
                                <td class="text-right" style="font-weight: 700; color: #ef4444;">{{ number_format((float) $item->overtime_pay, 2) }} AMD</td>
                            </tr>
                        @endif
                        @if((float) $item->night_pay > 0)
                            <tr>
                                <td>{{ __('Night Shift Premium (30%)') }}</td>
                                <td class="text-right" style="font-weight: 700; color: #8b5cf6;">{{ number_format((float) $item->night_pay, 2) }} AMD</td>
                            </tr>
                        @endif
                        @if((float) $item->holiday_pay > 0)
                            <tr>
                                <td>{{ __('Holiday / Weekend Pay') }}</td>
                                <td class="text-right" style="font-weight: 700;">{{ number_format((float) $item->holiday_pay, 2) }} AMD</td>
                            </tr>
                        @endif
                        @if((float) $item->bonuses > 0)
                            <tr>
                                <td>{{ __('Bonuses & Tips') }}</td>
                                <td class="text-right" style="font-weight: 700;">{{ number_format((float) $item->bonuses, 2) }} AMD</td>
                            </tr>
                        @endif
                        <tr style="border-top: 2px solid var(--border);">
                            <td style="font-weight: 900;">{{ __('Gross Total') }}</td>
                            <td class="text-right" style="font-weight: 900; font-size: 1.05rem;">{{ number_format((float) $item->gross_pay, 2) }} AMD</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Deductions -->
            <div>
                <h3 style="font-size: 0.9rem; text-transform: uppercase; font-weight: 800; color: #ef4444; margin: 0; padding-bottom: 0.5rem; border-bottom: 2px solid #ef4444;">
                    {{ __('Statutory & Other Deductions') }}
                </h3>
                <table>
                    <tbody>
                        <tr>
                            <td>{{ __('Income Tax (20%)') }}</td>
                            <td class="text-right" style="font-weight: 700; color: #ef4444;">-{{ number_format((float) $item->income_tax, 2) }} AMD</td>
                        </tr>
                        <tr>
                            <td>{{ __('Pension Contribution (5%)') }}</td>
                            <td class="text-right" style="font-weight: 700; color: #f97316;">-{{ number_format((float) $item->pension_contribution, 2) }} AMD</td>
                        </tr>
                        <tr>
                            <td>{{ __('Military Stamp Duty') }}</td>
                            <td class="text-right" style="font-weight: 700; color: #8b5cf6;">-{{ number_format((float) $item->military_stamp_duty, 2) }} AMD</td>
                        </tr>
                        @if((float) $item->advance_deductions > 0)
                            <tr>
                                <td>{{ __('Salary Advance Recovery') }}</td>
                                <td class="text-right" style="font-weight: 700; color: #d97706;">-{{ number_format((float) $item->advance_deductions, 2) }} AMD</td>
                            </tr>
                        @endif
                        <tr style="border-top: 2px solid var(--border);">
                            <td style="font-weight: 900;">{{ __('Total Deductions') }}</td>
                            <td class="text-right" style="font-weight: 900; color: #ef4444; font-size: 1.05rem;">-{{ number_format((float) $item->total_deductions, 2) }} AMD</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Net Payable Banner -->
        <div style="margin-top: 2rem; padding: 1.5rem; background: rgba(34, 197, 94, 0.12); border: 2px solid var(--primary); border-radius: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;" class="stat-box">
            <div>
                <div style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase; color: var(--primary); letter-spacing: 0.05em;">
                    {{ __('Net Salary Payable') }}
                </div>
                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">
                    {{ __('Calculated in accordance with RA Tax Code regulations') }}
                </div>
            </div>
            <div style="font-size: 2.2rem; font-weight: 900; color: var(--primary);">
                {{ number_format((float) $item->net_pay, 2) }} <span style="font-size: 1.1rem;">AMD</span>
            </div>
        </div>

        <!-- Signature Lines -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; margin-top: 3.5rem; padding-top: 1.5rem;">
            <div>
                <div style="border-top: 1px dashed var(--border); padding-top: 0.5rem; font-size: 0.85rem; color: var(--text-muted); text-align: center;">
                    {{ __('Employer / Accountant Signature') }}
                </div>
            </div>
            <div>
                <div style="border-top: 1px dashed var(--border); padding-top: 0.5rem; font-size: 0.85rem; color: var(--text-muted); text-align: center;">
                    {{ __('Employee Signature & Date') }}
                </div>
            </div>
        </div>
    </div>

</body>
</html>

