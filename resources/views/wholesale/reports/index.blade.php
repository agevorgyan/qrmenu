@extends('layouts.app')

@section('title', __('Wholesale Analytics & Tax Reports'))

@section('content')
<div class="content-wrapper">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6366f1;">
                    {{ __('Wholesale Subsystem') }}
                </span>
            </div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
                {{ __('Wholesale Analytics & Tax Reports') }}
            </h1>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
                {{ __('Commercial distribution insights, ATGAA classification sales, tax regime breakdowns, and accounts receivable aging.') }}
            </p>
        </div>

        <!-- Filter Presets -->
        <form method="GET" action="{{ route('admin.wholesale.reports.index') }}" style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
            <select name="preset" onchange="this.form.submit()" style="padding: 0.6rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); font-size: 0.85rem; font-weight: 600;">
                <option value="today" {{ $preset === 'today' ? 'selected' : '' }}>{{ __('Today') }}</option>
                <option value="this_week" {{ $preset === 'this_week' ? 'selected' : '' }}>{{ __('This Week') }}</option>
                <option value="this_month" {{ $preset === 'this_month' ? 'selected' : '' }}>{{ __('This Month') }}</option>
                <option value="this_quarter" {{ $preset === 'this_quarter' ? 'selected' : '' }}>{{ __('This Quarter') }}</option>
                <option value="all_time" {{ $preset === 'all_time' ? 'selected' : '' }}>{{ __('All Time') }}</option>
            </select>
        </form>
    </div>

    <!-- Executive KPI Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
        <!-- Total Revenue -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">{{ __('Total Wholesale Revenue') }}</span>
                <i class="fa-solid fa-chart-line" style="color: #6366f1;"></i>
            </div>
            <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-main);">
                {{ number_format((float)$summary['total_revenue'], 2) }} AMD
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ $summary['sales_count'] }} {{ __('sales issued') }}
            </div>
        </div>

        <!-- VAT Collected -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">{{ __('VAT Collected (20%)') }}</span>
                <i class="fa-solid fa-receipt" style="color: #10b981;"></i>
            </div>
            <div style="font-size: 1.5rem; font-weight: 800; color: #10b981;">
                {{ number_format((float)$summary['total_vat'], 2) }} AMD
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('Base:') }} {{ number_format((float)$summary['total_taxable'], 2) }} AMD
            </div>
        </div>

        <!-- Outstanding A/R -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">{{ __('Accounts Receivable') }}</span>
                <i class="fa-solid fa-hand-holding-dollar" style="color: #f59e0b;"></i>
            </div>
            <div style="font-size: 1.5rem; font-weight: 800; color: #f59e0b;">
                {{ number_format((float)$summary['outstanding_ar'], 2) }} AMD
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('Paid:') }} {{ number_format((float)$summary['total_paid'], 2) }} AMD
            </div>
        </div>

        <!-- Deliveries -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">{{ __('Deliveries Completed') }}</span>
                <i class="fa-solid fa-truck" style="color: #3b82f6;"></i>
            </div>
            <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-main);">
                {{ $summary['completed_deliveries'] }} / {{ $summary['total_deliveries'] }}
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('Returns:') }} {{ $summary['total_returns_count'] }} ({{ number_format((float)$summary['total_returns_amount'], 2) }} AMD)
            </div>
        </div>
    </div>

    <!-- Tax Regime Summary Cards -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 2rem;">
        <h2 style="font-size: 1.15rem; font-weight: 700; margin-top: 0; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-scale-balanced" style="color: #6366f1;"></i>
            {{ __('Tax Regime & e-Invoicing Breakdown (Հարկային համակարգեր)') }}
        </h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
            <!-- VAT System -->
            <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.25rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <span style="font-weight: 700; color: #2563eb;">{{ __('ԱԱՀ Համակարգ (VAT Payer 20%)') }}</span>
                    <span style="font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 6px; background: rgba(37, 99, 235, 0.1); color: #2563eb;">
                        {{ $taxSummary['vat']['count'] }} {{ __('Invoices') }}
                    </span>
                </div>
                <div style="font-size: 1.35rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem;">
                    {{ number_format((float)$taxSummary['vat']['total'], 2) }} AMD
                </div>
                <div style="font-size: 0.85rem; color: var(--text-muted);">
                    {{ __('Taxable Base:') }} {{ number_format((float)$taxSummary['vat']['taxable'], 2) }} AMD &bull;
                    {{ __('VAT:') }} <strong style="color: #10b981;">{{ number_format((float)$taxSummary['vat']['vat_amount'], 2) }} AMD</strong>
                </div>
            </div>

            <!-- Turnover Tax System -->
            <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.25rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <span style="font-weight: 700; color: #d97706;">{{ __('Շրջանառության հարկ (Turnover Tax / Non-VAT)') }}</span>
                    <span style="font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 6px; background: rgba(217, 119, 6, 0.1); color: #d97706;">
                        {{ $taxSummary['turnover']['count'] }} {{ __('Acc Docs') }}
                    </span>
                </div>
                <div style="font-size: 1.35rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem;">
                    {{ number_format((float)$taxSummary['turnover']['total'], 2) }} AMD
                </div>
                <div style="font-size: 0.85rem; color: var(--text-muted);">
                    {{ __('Special Tax System &bull; DealType: SPECIAL_TAX_SYSTEM') }}
                </div>
            </div>
        </div>
    </div>

    <!-- ATGAA Customs Classification Sales Breakdown -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; margin-bottom: 2rem;">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 style="font-size: 1.15rem; font-weight: 700; margin: 0;">
                    <i class="fa-solid fa-tags" style="color: #6366f1;"></i>
                    {{ __('ATGAA / FEACN Classification Sales (ԱՏԳ ԱԱ Դասակարգիչ)') }}
                </h2>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.2rem;">
                    {{ __('Sales grouped by official 4-digit and 8-digit Eurasian Customs Union commodity codes.') }}
                </div>
            </div>
            <a href="{{ route('admin.wholesale.reports.export', ['type' => 'atgaa', 'from_date' => $fromDate, 'to_date' => $toDate]) }}" 
               style="padding: 0.4rem 0.85rem; border-radius: 8px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-weight: 600; font-size: 0.8rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;">
                <i class="fa-solid fa-file-csv"></i> CSV
            </a>
        </div>
        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
            <thead>
                <tr style="border-bottom: 1px solid var(--border-color); background: var(--bg-main); text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                    <th style="padding: 0.85rem 1.25rem;">{{ __('ATGAA Code') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Distinct Products') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Total Units Sold') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Taxable Base') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('VAT (20%)') }}</th>
                    <th style="padding: 0.85rem 1.25rem; text-align: right;">{{ __('Total Gross Sales') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($salesByAtgaa as $atg)
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 1rem 1.25rem; font-family: monospace; font-weight: 800; color: #6366f1;">
                            {{ $atg->atgaa_code }}
                        </td>
                        <td style="padding: 1rem;">{{ $atg->products_count }} {{ __('products') }}</td>
                        <td style="padding: 1rem; font-weight: 600;">{{ (float)$atg->total_units_sold }}</td>
                        <td style="padding: 1rem;">{{ number_format((float)$atg->total_base, 2) }} AMD</td>
                        <td style="padding: 1rem;">{{ number_format((float)$atg->total_vat, 2) }} AMD</td>
                        <td style="padding: 1rem 1.25rem; text-align: right; font-weight: 800;">
                            {{ number_format((float)$atg->total_gross, 2) }} AMD
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding: 2.5rem; text-align: center; color: var(--text-muted);">
                            {{ __('No ATGAA sales recorded in this period.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Sales by Seller & Customer Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <!-- Sales by Seller -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden;">
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h2 style="font-size: 1.1rem; font-weight: 700; margin: 0;">
                    <i class="fa-solid fa-building-flag" style="color: #6366f1;"></i> {{ __('Sales by Seller Company') }}
                </h2>
                <a href="{{ route('admin.wholesale.reports.export', ['type' => 'sellers', 'from_date' => $fromDate, 'to_date' => $toDate]) }}" 
                   style="padding: 0.35rem 0.75rem; border-radius: 6px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-weight: 600; font-size: 0.75rem; text-decoration: none;">
                    CSV
                </a>
            </div>
            <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color); background: var(--bg-main); text-align: left; font-size: 0.7rem; text-transform: uppercase; color: var(--text-muted);">
                        <th style="padding: 0.75rem 1rem;">{{ __('Seller') }}</th>
                        <th style="padding: 0.75rem 0.75rem;">{{ __('TIN') }}</th>
                        <th style="padding: 0.75rem 0.75rem;">{{ __('Sales') }}</th>
                        <th style="padding: 0.75rem 1rem; text-align: right;">{{ __('Total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($salesBySeller as $s)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 0.85rem 1rem; font-weight: 600;">{{ $s->seller_name }}</td>
                            <td style="padding: 0.85rem 0.75rem; font-family: monospace;">{{ $s->tin }}</td>
                            <td style="padding: 0.85rem 0.75rem;">{{ $s->sales_count }}</td>
                            <td style="padding: 0.85rem 1rem; text-align: right; font-weight: 700;">{{ number_format((float)$s->grand_total, 2) }} AMD</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="padding: 2rem; text-align: center; color: var(--text-muted);">{{ __('No seller records') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Sales by Customer -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden;">
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h2 style="font-size: 1.1rem; font-weight: 700; margin: 0;">
                    <i class="fa-solid fa-users" style="color: #10b981;"></i> {{ __('Sales by Corporate Customer') }}
                </h2>
                <a href="{{ route('admin.wholesale.reports.export', ['type' => 'customers', 'from_date' => $fromDate, 'to_date' => $toDate]) }}" 
                   style="padding: 0.35rem 0.75rem; border-radius: 6px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-weight: 600; font-size: 0.75rem; text-decoration: none;">
                    CSV
                </a>
            </div>
            <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color); background: var(--bg-main); text-align: left; font-size: 0.7rem; text-transform: uppercase; color: var(--text-muted);">
                        <th style="padding: 0.75rem 1rem;">{{ __('Customer') }}</th>
                        <th style="padding: 0.75rem 0.75rem;">{{ __('TIN') }}</th>
                        <th style="padding: 0.75rem 0.75rem;">{{ __('Balance') }}</th>
                        <th style="padding: 0.75rem 1rem; text-align: right;">{{ __('Purchased') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($salesByCustomer as $c)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 0.85rem 1rem; font-weight: 600;">{{ $c->customer_name }}</td>
                            <td style="padding: 0.85rem 0.75rem; font-family: monospace;">{{ $c->tin }}</td>
                            <td style="padding: 0.85rem 0.75rem; color: #f59e0b; font-weight: 600;">{{ number_format((float)$c->current_balance, 2) }}</td>
                            <td style="padding: 0.85rem 1rem; text-align: right; font-weight: 700;">{{ number_format((float)$c->total_purchased, 2) }} AMD</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="padding: 2rem; text-align: center; color: var(--text-muted);">{{ __('No customer records') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

