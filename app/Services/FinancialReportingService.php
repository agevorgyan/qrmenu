<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\CashRegister;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\Order;
use App\Models\PosShift;
use App\Models\SupplierInvoice;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialReportingService
{
    /**
     * Generate comprehensive Profit and Loss (Income Statement) from posted Journal Entries.
     * Single source of truth: double-entry general ledger.
     */
    public function getProfitAndLoss(Vendor $vendor, ?int $locationId, Carbon $startDate, Carbon $endDate): array
    {
        $baseCurrency = $vendor->currency ?? 'AMD';

        // Query lines for Revenue and Expense accounts
        $lines = $this->getPostedLinesQuery($vendor->id, $locationId, $startDate, $endDate)
            ->whereIn('chart_of_accounts.type', ['revenue', 'expense'])
            ->select([
                'chart_of_accounts.id as account_id',
                'chart_of_accounts.code',
                'chart_of_accounts.name',
                'chart_of_accounts.type',
                'chart_of_accounts.subtype',
                'journal_entry_lines.type as line_type',
                DB::raw('SUM(journal_entry_lines.amount_base_currency) as total_amount'),
            ])
            ->groupBy(
                'chart_of_accounts.id',
                'chart_of_accounts.code',
                'chart_of_accounts.name',
                'chart_of_accounts.type',
                'chart_of_accounts.subtype',
                'journal_entry_lines.type'
            )
            ->get();

        // Group into accounts with net balances
        $accounts = [];
        foreach ($lines as $row) {
            $code = $row->code;
            if (! isset($accounts[$code])) {
                $accounts[$code] = [
                    'account_id' => $row->account_id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'type' => $row->type,
                    'subtype' => $row->subtype,
                    'debit' => 0.0,
                    'credit' => 0.0,
                    'net_amount' => 0.0,
                ];
            }

            if ($row->line_type === 'debit') {
                $accounts[$code]['debit'] = round((float) $row->total_amount, 2);
            } else {
                $accounts[$code]['credit'] = round((float) $row->total_amount, 2);
            }

            // For Revenue: Credit - Debit (Credits are normal, Debits are contra/reversals)
            // For Expense: Debit - Credit (Debits are normal, Credits are returns/reversals)
            if ($row->type === 'revenue') {
                $accounts[$code]['net_amount'] = round($accounts[$code]['credit'] - $accounts[$code]['debit'], 2);
            } else {
                $accounts[$code]['net_amount'] = round($accounts[$code]['debit'] - $accounts[$code]['credit'], 2);
            }
        }

        // Categorize Revenue
        $grossRevenueAccounts = [];
        $contraRevenueAccounts = [];
        $grossRevenue = 0.0;
        $totalContra = 0.0;

        foreach ($accounts as $acc) {
            if ($acc['type'] === 'revenue') {
                // Contra accounts (Discounts 4030, Refunds 4040)
                if (in_array($acc['code'], ['4030', '4040'], true) || $acc['net_amount'] < 0) {
                    $contraRevenueAccounts[] = $acc;
                    $totalContra += abs($acc['net_amount']);
                } else {
                    $grossRevenueAccounts[] = $acc;
                    $grossRevenue += $acc['net_amount'];
                }
            }
        }

        $netRevenue = round($grossRevenue - $totalContra, 2);

        // Categorize Cost of Goods Sold (5010-5090)
        $cogsAccounts = [];
        $totalCogs = 0.0;

        // Categorize Operating Expenses (5100-5890)
        $opexAccounts = [];
        $totalOpex = 0.0;

        // Categorize Taxes & Non-Operating (5900+)
        $taxAccounts = [];
        $totalTax = 0.0;

        foreach ($accounts as $acc) {
            if ($acc['type'] === 'expense') {
                $codeNum = (int) $acc['code'];
                if ($codeNum >= 5000 && $codeNum < 5100) {
                    $cogsAccounts[] = $acc;
                    $totalCogs += $acc['net_amount'];
                } elseif ($codeNum >= 5900) {
                    $taxAccounts[] = $acc;
                    $totalTax += $acc['net_amount'];
                } else {
                    $opexAccounts[] = $acc;
                    $totalOpex += $acc['net_amount'];
                }
            }
        }

        $grossProfit = round($netRevenue - $totalCogs, 2);
        $grossMarginPct = $netRevenue > 0 ? round(($grossProfit / $netRevenue) * 100, 2) : 0.0;

        $operatingProfit = round($grossProfit - $totalOpex, 2);
        $operatingMarginPct = $netRevenue > 0 ? round(($operatingProfit / $netRevenue) * 100, 2) : 0.0;

        $netProfit = round($operatingProfit - $totalTax, 2);
        $netMarginPct = $netRevenue > 0 ? round(($netProfit / $netRevenue) * 100, 2) : 0.0;

        return [
            'vendor_id' => $vendor->id,
            'location_id' => $locationId,
            'currency' => $baseCurrency,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'revenue' => [
                'gross_accounts' => $grossRevenueAccounts,
                'gross_revenue' => round($grossRevenue, 2),
                'contra_accounts' => $contraRevenueAccounts,
                'total_contra' => round($totalContra, 2),
                'net_revenue' => $netRevenue,
            ],
            'cogs' => [
                'accounts' => $cogsAccounts,
                'total_cogs' => round($totalCogs, 2),
            ],
            'gross_profit' => $grossProfit,
            'gross_margin_pct' => $grossMarginPct,
            'operating_expenses' => [
                'accounts' => $opexAccounts,
                'total_opex' => round($totalOpex, 2),
            ],
            'operating_profit' => $operatingProfit,
            'operating_margin_pct' => $operatingMarginPct,
            'taxes' => [
                'accounts' => $taxAccounts,
                'total_tax' => round($totalTax, 2),
            ],
            'net_profit' => $netProfit,
            'net_margin_pct' => $netMarginPct,
        ];
    }

    /**
     * Generate Balance Sheet (Statement of Financial Position) as of a specific date.
     * Assets = Liabilities + Equity
     */
    public function getBalanceSheet(Vendor $vendor, ?int $locationId, Carbon $asOfDate): array
    {
        $baseCurrency = $vendor->currency ?? 'AMD';

        // Query all posted journal entries up to $asOfDate
        $lines = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.vendor_id', $vendor->id)
            ->where('journal_entries.status', 'posted')
            ->where('journal_entries.entry_date', '<=', $asOfDate->toDateString())
            ->when($locationId, function ($q) use ($locationId) {
                $q->where(function ($sub) use ($locationId) {
                    $sub->where('journal_entry_lines.location_id', $locationId)
                        ->orWhere('journal_entries.location_id', $locationId);
                });
            })
            ->select([
                'chart_of_accounts.id as account_id',
                'chart_of_accounts.code',
                'chart_of_accounts.name',
                'chart_of_accounts.type',
                'chart_of_accounts.subtype',
                'journal_entry_lines.type as line_type',
                DB::raw('SUM(journal_entry_lines.amount_base_currency) as total_amount'),
            ])
            ->groupBy(
                'chart_of_accounts.id',
                'chart_of_accounts.code',
                'chart_of_accounts.name',
                'chart_of_accounts.type',
                'chart_of_accounts.subtype',
                'journal_entry_lines.type'
            )
            ->get();

        $accounts = [];
        $cumulativeRevenue = 0.0;
        $cumulativeExpense = 0.0;

        foreach ($lines as $row) {
            $code = $row->code;
            if (! isset($accounts[$code])) {
                $accounts[$code] = [
                    'account_id' => $row->account_id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'type' => $row->type,
                    'subtype' => $row->subtype,
                    'debit' => 0.0,
                    'credit' => 0.0,
                    'balance' => 0.0,
                ];
            }

            if ($row->line_type === 'debit') {
                $accounts[$code]['debit'] = round((float) $row->total_amount, 2);
            } else {
                $accounts[$code]['credit'] = round((float) $row->total_amount, 2);
            }

            if ($row->type === 'asset') {
                $accounts[$code]['balance'] = round($accounts[$code]['debit'] - $accounts[$code]['credit'], 2);
            } elseif (in_array($row->type, ['liability', 'equity'], true)) {
                $accounts[$code]['balance'] = round($accounts[$code]['credit'] - $accounts[$code]['debit'], 2);
            } elseif ($row->type === 'revenue') {
                $accounts[$code]['balance'] = round($accounts[$code]['credit'] - $accounts[$code]['debit'], 2);
                $cumulativeRevenue += $accounts[$code]['balance'];
            } elseif ($row->type === 'expense') {
                $accounts[$code]['balance'] = round($accounts[$code]['debit'] - $accounts[$code]['credit'], 2);
                $cumulativeExpense += $accounts[$code]['balance'];
            }
        }

        // Current period retained net income
        $currentPeriodNetIncome = round($cumulativeRevenue - $cumulativeExpense, 2);

        // Assets
        $currentAssets = [];
        $nonCurrentAssets = [];
        $totalCurrentAssets = 0.0;
        $totalNonCurrentAssets = 0.0;

        foreach ($accounts as $acc) {
            if ($acc['type'] === 'asset') {
                $codeNum = (int) $acc['code'];
                if ($codeNum < 1500) {
                    $currentAssets[] = $acc;
                    $totalCurrentAssets += $acc['balance'];
                } else {
                    $nonCurrentAssets[] = $acc;
                    $totalNonCurrentAssets += $acc['balance'];
                }
            }
        }

        $totalAssets = round($totalCurrentAssets + $totalNonCurrentAssets, 2);

        // Liabilities
        $currentLiabilities = [];
        $longTermLiabilities = [];
        $totalCurrentLiabilities = 0.0;
        $totalLongTermLiabilities = 0.0;

        foreach ($accounts as $acc) {
            if ($acc['type'] === 'liability') {
                $codeNum = (int) $acc['code'];
                if ($codeNum < 2500) {
                    $currentLiabilities[] = $acc;
                    $totalCurrentLiabilities += $acc['balance'];
                } else {
                    $longTermLiabilities[] = $acc;
                    $totalLongTermLiabilities += $acc['balance'];
                }
            }
        }

        $totalLiabilities = round($totalCurrentLiabilities + $totalLongTermLiabilities, 2);

        // Equity
        $equityAccounts = [];
        $totalEquityWithoutRetained = 0.0;

        foreach ($accounts as $acc) {
            if ($acc['type'] === 'equity') {
                $equityAccounts[] = $acc;
                $totalEquityWithoutRetained += $acc['balance'];
            }
        }

        $totalEquity = round($totalEquityWithoutRetained + $currentPeriodNetIncome, 2);
        $totalLiabilitiesAndEquity = round($totalLiabilities + $totalEquity, 2);
        $discrepancy = round($totalAssets - $totalLiabilitiesAndEquity, 2);

        return [
            'vendor_id' => $vendor->id,
            'location_id' => $locationId,
            'currency' => $baseCurrency,
            'as_of_date' => $asOfDate->toDateString(),
            'assets' => [
                'current' => $currentAssets,
                'total_current' => round($totalCurrentAssets, 2),
                'non_current' => $nonCurrentAssets,
                'total_non_current' => round($totalNonCurrentAssets, 2),
                'total_assets' => $totalAssets,
            ],
            'liabilities' => [
                'current' => $currentLiabilities,
                'total_current' => round($totalCurrentLiabilities, 2),
                'long_term' => $longTermLiabilities,
                'total_long_term' => round($totalLongTermLiabilities, 2),
                'total_liabilities' => $totalLiabilities,
            ],
            'equity' => [
                'accounts' => $equityAccounts,
                'retained_earnings_current_period' => $currentPeriodNetIncome,
                'total_equity' => $totalEquity,
            ],
            'total_liabilities_and_equity' => $totalLiabilitiesAndEquity,
            'is_balanced' => abs($discrepancy) < 0.01,
            'discrepancy' => $discrepancy,
        ];
    }

    /**
     * Generate Cash Flow Statement (Direct method based on cash & bank ledger entries).
     */
    public function getCashFlowStatement(Vendor $vendor, ?int $locationId, Carbon $startDate, Carbon $endDate): array
    {
        $baseCurrency = $vendor->currency ?? 'AMD';

        // Get cash and bank account IDs (1010-1050)
        $cashAccountIds = ChartOfAccount::where('vendor_id', $vendor->id)
            ->where(function ($q) {
                $q->whereBetween('code', ['1010', '1050'])
                    ->orWhereIn('subtype', ['cash', 'petty_cash', 'bank', 'cash_in_transit']);
            })
            ->pluck('id')
            ->all();

        // 1. Beginning balance: net cash in accounts as of ($startDate - 1 day)
        $dayBefore = $startDate->copy()->subDay();
        $beginningCash = $this->calculateCashBalanceAtDate($vendor->id, $cashAccountIds, $locationId, $dayBefore);

        // 2. Query all cash-affecting journal entries during the period
        $cashLines = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.vendor_id', $vendor->id)
            ->where('journal_entries.status', 'posted')
            ->whereIn('journal_entry_lines.account_id', $cashAccountIds)
            ->whereBetween('journal_entries.entry_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->when($locationId, function ($q) use ($locationId) {
                $q->where(function ($sub) use ($locationId) {
                    $sub->where('journal_entry_lines.location_id', $locationId)
                        ->orWhere('journal_entries.location_id', $locationId);
                });
            })
            ->select([
                'journal_entries.source_type',
                'journal_entry_lines.type as line_type',
                'journal_entry_lines.amount_base_currency',
            ])
            ->get();

        // Categorize cash movements
        $customerReceipts = 0.0;
        $supplierPayments = 0.0;
        $operatingExpensePayments = 0.0;
        $payrollPayments = 0.0;
        $customerRefunds = 0.0;
        $investingCapex = 0.0;
        $financingInflow = 0.0;
        $financingOutflow = 0.0;
        $otherCashInflow = 0.0;
        $otherCashOutflow = 0.0;

        foreach ($cashLines as $line) {
            $amount = (float) $line->amount_base_currency;
            $isDebit = $line->line_type === 'debit'; // Inflow to cash/bank
            $sourceType = $line->source_type ?? 'other';

            switch ($sourceType) {
                case 'pos_payment':
                case 'order_payment':
                    if ($isDebit) {
                        $customerReceipts += $amount;
                    } else {
                        $customerRefunds += $amount;
                    }
                    break;

                case 'order_refund':
                    if (! $isDebit) {
                        $customerRefunds += $amount;
                    }
                    break;

                case 'supplier_payment':
                    if (! $isDebit) {
                        $supplierPayments += $amount;
                    }
                    break;

                case 'expense_payment':
                    if (! $isDebit) {
                        $operatingExpensePayments += $amount;
                    }
                    break;

                case 'salary_payment':
                case 'employee_advance':
                    if (! $isDebit) {
                        $payrollPayments += $amount;
                    }
                    break;

                case 'fixed_asset_purchase':
                    if (! $isDebit) {
                        $investingCapex += $amount;
                    }
                    break;

                case 'capital_contribution':
                case 'bank_loan':
                    if ($isDebit) {
                        $financingInflow += $amount;
                    } else {
                        $financingOutflow += $amount;
                    }
                    break;

                default:
                    // Safe drops / cash transfers between internal cash accounts cancel out (asset to asset)
                    if ($sourceType === 'cash_transfer' || $sourceType === 'safe_drop') {
                        // Net effect on total cash is 0
                    } else {
                        if ($isDebit) {
                            $otherCashInflow += $amount;
                        } else {
                            $otherCashOutflow += $amount;
                        }
                    }
                    break;
            }
        }

        $operatingInflows = round($customerReceipts + $otherCashInflow, 2);
        $operatingOutflows = round($supplierPayments + $operatingExpensePayments + $payrollPayments + $customerRefunds + $otherCashOutflow, 2);
        $netOperatingCash = round($operatingInflows - $operatingOutflows, 2);

        $netInvestingCash = round(-$investingCapex, 2);
        $netFinancingCash = round($financingInflow - $financingOutflow, 2);

        $netCashChange = round($netOperatingCash + $netInvestingCash + $netFinancingCash, 2);
        $endingCash = round($beginningCash + $netCashChange, 2);

        return [
            'vendor_id' => $vendor->id,
            'location_id' => $locationId,
            'currency' => $baseCurrency,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'beginning_cash_balance' => round($beginningCash, 2),
            'operating_activities' => [
                'customer_receipts' => round($customerReceipts, 2),
                'other_inflows' => round($otherCashInflow, 2),
                'supplier_payments' => round(-$supplierPayments, 2),
                'operating_expense_payments' => round(-$operatingExpensePayments, 2),
                'payroll_payments' => round(-$payrollPayments, 2),
                'customer_refunds' => round(-$customerRefunds, 2),
                'other_outflows' => round(-$otherCashOutflow, 2),
                'net_operating_cash' => $netOperatingCash,
            ],
            'investing_activities' => [
                'capital_expenditures' => round(-$investingCapex, 2),
                'net_investing_cash' => $netInvestingCash,
            ],
            'financing_activities' => [
                'capital_and_loans_received' => round($financingInflow, 2),
                'loan_repayments_and_dividends' => round(-$financingOutflow, 2),
                'net_financing_cash' => $netFinancingCash,
            ],
            'net_cash_change' => $netCashChange,
            'ending_cash_balance' => $endingCash,
        ];
    }

    /**
     * Generate Trial Balance as of a specific date.
     * Verifies total debit equals total credit across all ledger accounts.
     */
    public function getTrialBalance(Vendor $vendor, ?int $locationId, Carbon $asOfDate): array
    {
        $baseCurrency = $vendor->currency ?? 'AMD';

        $lines = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.vendor_id', $vendor->id)
            ->where('journal_entries.status', 'posted')
            ->where('journal_entries.entry_date', '<=', $asOfDate->toDateString())
            ->when($locationId, function ($q) use ($locationId) {
                $q->where(function ($sub) use ($locationId) {
                    $sub->where('journal_entry_lines.location_id', $locationId)
                        ->orWhere('journal_entries.location_id', $locationId);
                });
            })
            ->select([
                'chart_of_accounts.id as account_id',
                'chart_of_accounts.code',
                'chart_of_accounts.name',
                'chart_of_accounts.type',
                'chart_of_accounts.subtype',
                'journal_entry_lines.type as line_type',
                DB::raw('SUM(journal_entry_lines.amount_base_currency) as total_amount'),
            ])
            ->groupBy(
                'chart_of_accounts.id',
                'chart_of_accounts.code',
                'chart_of_accounts.name',
                'chart_of_accounts.type',
                'chart_of_accounts.subtype',
                'journal_entry_lines.type'
            )
            ->get();

        $accounts = [];
        foreach ($lines as $row) {
            $code = $row->code;
            if (! isset($accounts[$code])) {
                $accounts[$code] = [
                    'account_id' => $row->account_id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'type' => $row->type,
                    'subtype' => $row->subtype,
                    'debit_total' => 0.0,
                    'credit_total' => 0.0,
                    'net_debit' => 0.0,
                    'net_credit' => 0.0,
                ];
            }

            if ($row->line_type === 'debit') {
                $accounts[$code]['debit_total'] = round((float) $row->total_amount, 2);
            } else {
                $accounts[$code]['credit_total'] = round((float) $row->total_amount, 2);
            }
        }

        $totalDebits = 0.0;
        $totalCredits = 0.0;
        $formattedRows = [];

        ksort($accounts);

        foreach ($accounts as $acc) {
            $net = round($acc['debit_total'] - $acc['credit_total'], 2);

            if ($net > 0) {
                $acc['net_debit'] = $net;
                $acc['net_credit'] = 0.0;
                $totalDebits += $net;
            } elseif ($net < 0) {
                $acc['net_debit'] = 0.0;
                $acc['net_credit'] = abs($net);
                $totalCredits += abs($net);
            } else {
                $acc['net_debit'] = 0.0;
                $acc['net_credit'] = 0.0;
            }

            $formattedRows[] = $acc;
        }

        $totalDebits = round($totalDebits, 2);
        $totalCredits = round($totalCredits, 2);
        $diff = round(abs($totalDebits - $totalCredits), 2);

        return [
            'vendor_id' => $vendor->id,
            'location_id' => $locationId,
            'currency' => $baseCurrency,
            'as_of_date' => $asOfDate->toDateString(),
            'rows' => $formattedRows,
            'total_debit' => $totalDebits,
            'total_credit' => $totalCredits,
            'is_balanced' => $diff < 0.01,
            'difference' => $diff,
        ];
    }

    /**
     * Sales Analytics report broken down by Branch, Payment Method, Order Type, and Trends.
     */
    public function getSalesAnalytics(Vendor $vendor, ?int $locationId, Carbon $startDate, Carbon $endDate): array
    {
        $baseCurrency = $vendor->currency ?? 'AMD';

        // Query orders in period
        $ordersQuery = Order::where('vendor_id', $vendor->id)
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->whereBetween('created_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId));

        $totalOrders = (clone $ordersQuery)->count();
        $totalGrossSales = (float) (clone $ordersQuery)->sum('subtotal_amount');
        $totalDiscounts = (float) (clone $ordersQuery)->sum('discount_amount');
        $totalTax = (float) (clone $ordersQuery)->sum('tax_amount');
        $totalNetSales = (float) (clone $ordersQuery)->sum('total_amount');

        // Breakdown by Order Type
        $byOrderType = (clone $ordersQuery)
            ->select('order_type', DB::raw('COUNT(*) as order_count'), DB::raw('SUM(total_amount) as total_revenue'))
            ->groupBy('order_type')
            ->get()
            ->map(fn ($r) => [
                'order_type' => $r->order_type ?? 'dine_in',
                'order_count' => (int) $r->order_count,
                'total_revenue' => round((float) $r->total_revenue, 2),
            ]);

        // Breakdown by Payment Method from pos_payments
        $paymentsQuery = DB::table('pos_payments')
            ->where('vendor_id', $vendor->id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId));

        $byPaymentMethod = (clone $paymentsQuery)
            ->select('payment_method', DB::raw('COUNT(*) as payment_count'), DB::raw('SUM(amount) as total_amount'))
            ->groupBy('payment_method')
            ->get()
            ->map(fn ($r) => [
                'payment_method' => $r->payment_method,
                'payment_count' => (int) $r->payment_count,
                'total_amount' => round((float) $r->total_amount, 2),
            ]);

        // Daily trend
        $dailyTrends = (clone $ordersQuery)
            ->select(DB::raw('DATE(created_at) as sale_date'), DB::raw('COUNT(*) as order_count'), DB::raw('SUM(total_amount) as total_sales'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'))
            ->get()
            ->map(fn ($r) => [
                'date' => $r->sale_date,
                'order_count' => (int) $r->order_count,
                'total_sales' => round((float) $r->total_sales, 2),
            ]);

        return [
            'vendor_id' => $vendor->id,
            'location_id' => $locationId,
            'currency' => $baseCurrency,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'summary' => [
                'total_orders' => $totalOrders,
                'gross_sales' => round($totalGrossSales, 2),
                'discounts' => round($totalDiscounts, 2),
                'tax' => round($totalTax, 2),
                'net_sales' => round($totalNetSales, 2),
                'average_order_value' => $totalOrders > 0 ? round($totalNetSales / $totalOrders, 2) : 0.0,
            ],
            'by_order_type' => $byOrderType,
            'by_payment_method' => $byPaymentMethod,
            'daily_trends' => $dailyTrends,
        ];
    }

    /**
     * Generate Enterprise Financial Dashboard Summary with Period-over-Period KPI Deltas.
     */
    public function getDashboardSummary(
        Vendor $vendor,
        ?int $locationId,
        Carbon $startDate,
        Carbon $endDate,
        Carbon $prevStartDate,
        Carbon $prevEndDate
    ): array {
        $baseCurrency = $vendor->currency ?? 'AMD';

        // Current period P&L
        $currentPnl = $this->getProfitAndLoss($vendor, $locationId, $startDate, $endDate);
        // Previous period P&L
        $prevPnl = $this->getProfitAndLoss($vendor, $locationId, $prevStartDate, $prevEndDate);

        // Current period cash collections by payment method
        $payments = DB::table('pos_payments')
            ->where('vendor_id', $vendor->id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->select('payment_method', DB::raw('SUM(amount) as total_amount'))
            ->groupBy('payment_method')
            ->pluck('total_amount', 'payment_method');

        $cashReceived = round((float) ($payments['cash'] ?? 0.0), 2);
        $cardPayments = round((float) ($payments['card'] ?? 0.0), 2);
        $onlinePayments = round((float) ($payments['online'] ?? 0.0) + (float) ($payments['qr'] ?? 0.0), 2);

        // Live Cash in Tills / Drawer Balances
        $registersLiveCash = (float) CashRegister::where('vendor_id', $vendor->id)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->where('is_active', true)
            ->sum('current_balance');

        // Live Corporate Bank Balances
        $bankBalances = (float) BankAccount::where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->sum('current_balance');

        // Outstanding Accounts Payable (Unpaid Supplier Invoices + Approved Unpaid Expenses)
        $unpaidSupplierInvoices = (float) SupplierInvoice::where('vendor_id', $vendor->id)
            ->whereIn('payment_status', ['unpaid', 'partially_paid'])
            ->selectRaw('SUM(total_amount - paid_amount) as outstanding')
            ->value('outstanding') ?? 0.0;

        $unpaidExpenses = (float) Expense::where('vendor_id', $vendor->id)
            ->where('status', 'approved')
            ->whereIn('payment_status', ['unpaid', 'partially_paid'])
            ->selectRaw('SUM(total_amount - paid_amount) as outstanding')
            ->value('outstanding') ?? 0.0;

        $totalAccountsPayable = round($unpaidSupplierInvoices + $unpaidExpenses, 2);

        // Total refunds in period
        $totalRefunds = (float) DB::table('order_refunds')
            ->where('vendor_id', $vendor->id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->sum('amount');

        // Cash Variances (Over/Short) from closed shifts
        $shiftVariances = (float) PosShift::where('vendor_id', $vendor->id)
            ->where('status', 'closed')
            ->whereBetween('closed_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->sum('cash_discrepancy');

        // Unreconciled Bank Transactions
        $unreconciledTransactionsCount = BankTransaction::where('vendor_id', $vendor->id)
            ->where('reconciliation_status', 'unreconciled')
            ->count();

        $unreconciledTransactionsSum = (float) BankTransaction::where('vendor_id', $vendor->id)
            ->where('reconciliation_status', 'unreconciled')
            ->sum('amount');

        // Compute percentage deltas
        $grossSalesDelta = $this->calculatePercentageDelta(
            $currentPnl['revenue']['gross_revenue'],
            $prevPnl['revenue']['gross_revenue']
        );

        $netRevenueDelta = $this->calculatePercentageDelta(
            $currentPnl['revenue']['net_revenue'],
            $prevPnl['revenue']['net_revenue']
        );

        $opexDelta = $this->calculatePercentageDelta(
            $currentPnl['operating_expenses']['total_opex'],
            $prevPnl['operating_expenses']['total_opex']
        );

        $profitDelta = $this->calculatePercentageDelta(
            $currentPnl['operating_profit'],
            $prevPnl['operating_profit']
        );

        return [
            'vendor_id' => $vendor->id,
            'location_id' => $locationId,
            'currency' => $baseCurrency,
            'current_period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'previous_period' => [
                'start' => $prevStartDate->toDateString(),
                'end' => $prevEndDate->toDateString(),
            ],
            'kpis' => [
                'gross_sales' => [
                    'value' => $currentPnl['revenue']['gross_revenue'],
                    'previous' => $prevPnl['revenue']['gross_revenue'],
                    'delta_pct' => $grossSalesDelta,
                ],
                'net_revenue' => [
                    'value' => $currentPnl['revenue']['net_revenue'],
                    'previous' => $prevPnl['revenue']['net_revenue'],
                    'delta_pct' => $netRevenueDelta,
                ],
                'total_expenses' => [
                    'value' => $currentPnl['operating_expenses']['total_opex'],
                    'previous' => $prevPnl['operating_expenses']['total_opex'],
                    'delta_pct' => $opexDelta,
                ],
                'operating_profit' => [
                    'value' => $currentPnl['operating_profit'],
                    'previous' => $prevPnl['operating_profit'],
                    'delta_pct' => $profitDelta,
                ],
                'cash_received' => $cashReceived,
                'card_payments' => $cardPayments,
                'online_payments' => $onlinePayments,
                'live_registers_cash' => round($registersLiveCash, 2),
                'live_bank_balance' => round($bankBalances, 2),
                'accounts_payable' => $totalAccountsPayable,
                'total_refunds' => round($totalRefunds, 2),
                'cash_variance' => round($shiftVariances, 2),
                'unreconciled_transactions' => [
                    'count' => $unreconciledTransactionsCount,
                    'amount' => round($unreconciledTransactionsSum, 2),
                ],
            ],
        ];
    }

    /**
     * Helper to query posted journal entry lines joined with accounts and entries.
     */
    protected function getPostedLinesQuery(int $vendorId, ?int $locationId, Carbon $startDate, Carbon $endDate)
    {
        return DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.vendor_id', $vendorId)
            ->where('journal_entries.status', 'posted')
            ->whereBetween('journal_entries.entry_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->when($locationId, function ($q) use ($locationId) {
                $q->where(function ($sub) use ($locationId) {
                    $sub->where('journal_entry_lines.location_id', $locationId)
                        ->orWhere('journal_entries.location_id', $locationId);
                });
            });
    }

    /**
     * Helper to compute cash balance as of a given date.
     */
    protected function calculateCashBalanceAtDate(int $vendorId, array $cashAccountIds, ?int $locationId, Carbon $asOfDate): float
    {
        if (empty($cashAccountIds)) {
            return 0.0;
        }

        $lines = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.vendor_id', $vendorId)
            ->where('journal_entries.status', 'posted')
            ->whereIn('journal_entry_lines.account_id', $cashAccountIds)
            ->where('journal_entries.entry_date', '<=', $asOfDate->toDateString())
            ->when($locationId, function ($q) use ($locationId) {
                $q->where(function ($sub) use ($locationId) {
                    $sub->where('journal_entry_lines.location_id', $locationId)
                        ->orWhere('journal_entries.location_id', $locationId);
                });
            })
            ->select([
                'journal_entry_lines.type',
                DB::raw('SUM(journal_entry_lines.amount_base_currency) as total'),
            ])
            ->groupBy('journal_entry_lines.type')
            ->pluck('total', 'type');

        $debit = (float) ($lines['debit'] ?? 0.0);
        $credit = (float) ($lines['credit'] ?? 0.0);

        return round($debit - $credit, 2);
    }

    /**
     * Compute percentage delta between current and previous values.
     */
    protected function calculatePercentageDelta(float $current, float $previous): float
    {
        if (abs($previous) < 0.01) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / abs($previous)) * 100, 2);
    }
}
