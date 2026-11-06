<?php

namespace App\Services;

use App\Models\EmployeeAdvance;
use App\Models\HrPayrollSetting;
use App\Models\PayrollItem;
use App\Models\PayrollItemComponent;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class HrPayrollService
{
    /**
     * Resolve effective payroll settings for a vendor at a given date.
     */
    public function getEffectiveSettings(Vendor $vendor, ?Carbon $date = null): HrPayrollSetting
    {
        $targetDate = ($date ?? Carbon::now())->toDateString();

        $setting = HrPayrollSetting::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('effective_from', '<=', $targetDate)
            ->where(function ($q) use ($targetDate) {
                $q->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $targetDate);
            })
            ->latest('effective_from')
            ->first();

        if ($setting) {
            return $setting;
        }

        // Fallback default Armenian settings
        return new HrPayrollSetting([
            'vendor_id' => $vendor->id,
            'country_code' => 'AM',
            'income_tax_rate' => 0.2000,
            'pension_rate' => 0.0500,
            'pension_ceiling_gross' => 1125000.00,
            'overtime_rate_multiplier' => 1.50,
            'night_rate_multiplier' => 0.30,
            'holiday_rate_multiplier' => 2.00,
            'standard_monthly_hours' => 168,
            'standard_weekly_hours' => 40,
            'stamp_fee_tiers' => [
                ['min' => 0, 'max' => 100000, 'fee' => 1500],
                ['min' => 100001, 'max' => 200000, 'fee' => 3000],
                ['min' => 200001, 'max' => 500000, 'fee' => 5500],
                ['min' => 500001, 'max' => 1000000, 'fee' => 8500],
                ['min' => 1000001, 'max' => null, 'fee' => 15000],
            ],
            'effective_from' => '2026-01-01',
        ]);
    }

    /**
     * Compute gross-to-net salary for an employee in a pay period.
     *
     * @param array{
     *     base_salary?: float,
     *     hourly_rate?: float,
     *     compensation_type?: string,
     *     worked_days?: float,
     *     standard_days?: int,
     *     worked_hours?: float,
     *     regular_hours?: float,
     *     overtime_hours?: float,
     *     night_hours?: float,
     *     holiday_hours?: float,
     *     bonuses?: float,
     *     allowances?: float,
     *     other_deductions?: float,
     *     available_advance?: float,
     *     has_pension?: bool
     * } $inputs
     * @return array{
     *     base_salary: float,
     *     worked_days: float,
     *     worked_hours: float,
     *     regular_pay: float,
     *     overtime_pay: float,
     *     night_pay: float,
     *     holiday_pay: float,
     *     bonuses_total: float,
     *     allowances_total: float,
     *     gross_salary: float,
     *     income_tax: float,
     *     pension_fee: float,
     *     stamp_fee: float,
     *     other_deductions: float,
     *     advance_deduction: float,
     *     total_deductions: float,
     *     net_salary: float,
     *     components: array<int, array{type: string, code: string, name: string, rate: ?float, amount: float, meta?: array}>
     * }
     */
    public function calculateGrossToNet(HrPayrollSetting $settings, array $inputs): array
    {
        return $this->calculateEmployeeSalary($settings, $inputs);
    }

    public function calculateEmployeeSalary(
        HrPayrollSetting $settings,
        array $inputs
    ): array {
        $compType = $inputs['compensation_type'] ?? 'monthly';
        $baseSalary = round((float) ($inputs['base_salary'] ?? 0.00), 2);
        $hourlyRate = round((float) ($inputs['hourly_rate'] ?? 0.00), 2);
        $standardDays = max(1, (int) ($inputs['standard_days'] ?? 21));
        $workedDays = max(0.00, round((float) ($inputs['worked_days'] ?? $standardDays), 2));

        $workedHours = max(0.00, round((float) ($inputs['worked_hours'] ?? 0.00), 2));
        $regularHours = max(0.00, round((float) ($inputs['regular_hours'] ?? $workedHours), 2));
        $overtimeHours = max(0.00, round((float) ($inputs['overtime_hours'] ?? 0.00), 2));
        $nightHours = max(0.00, round((float) ($inputs['night_hours'] ?? 0.00), 2));
        $holidayHours = max(0.00, round((float) ($inputs['holiday_hours'] ?? 0.00), 2));

        $bonuses = max(0.00, round((float) ($inputs['bonuses'] ?? 0.00), 2));
        $allowances = max(0.00, round((float) ($inputs['allowances'] ?? 0.00), 2));
        $otherDeductions = max(0.00, round((float) ($inputs['other_deductions'] ?? 0.00), 2));
        $availableAdvance = max(0.00, round((float) ($inputs['available_advance'] ?? 0.00), 2));
        $hasPension = (bool) ($inputs['has_pension'] ?? true);

        // Derive effective hourly rate if compensation is monthly
        $standardMonthlyHours = max(1, (int) $settings->standard_monthly_hours);
        $effectiveHourlyRate = $hourlyRate > 0 ? $hourlyRate : round($baseSalary / $standardMonthlyHours, 2);

        // 1. Regular Base Pay
        $regularPay = match ($compType) {
            'hourly' => round($regularHours * $hourlyRate, 2),
            'daily' => round($workedDays * ($baseSalary > 0 ? $baseSalary : round($effectiveHourlyRate * 8, 2)), 2),
            'shift' => round($workedDays * $baseSalary, 2),
            default => ($workedDays >= $standardDays)
                ? $baseSalary
                : round(($baseSalary / $standardDays) * $workedDays, 2),
        };

        // 2. Overtime Pay (rate * overtime_multiplier * hours)
        $overtimeMultiplier = (float) $settings->overtime_rate_multiplier;
        $overtimePay = round($overtimeHours * ($effectiveHourlyRate * $overtimeMultiplier), 2);

        // 3. Night Shift Premium (rate * night_multiplier * hours)
        $nightMultiplier = (float) $settings->night_rate_multiplier;
        $nightPay = round($nightHours * ($effectiveHourlyRate * $nightMultiplier), 2);

        // 4. Holiday Pay (rate * holiday_multiplier * hours)
        $holidayMultiplier = (float) $settings->holiday_rate_multiplier;
        $holidayPay = round($holidayHours * ($effectiveHourlyRate * $holidayMultiplier), 2);

        // 5. Total Gross Salary
        $grossSalary = round($regularPay + $overtimePay + $nightPay + $holidayPay + $bonuses + $allowances, 2);

        // 6. Income Tax (Flat rate, e.g. 20%)
        $incomeTaxRate = (float) $settings->income_tax_rate;
        $incomeTax = round($grossSalary * $incomeTaxRate, 2);

        // 7. Compulsory Pension Contribution (e.g. 5% capped at max ceiling)
        $pensionRate = (float) $settings->pension_rate;
        $pensionCeiling = (float) $settings->pension_ceiling_gross;
        $pensionSubjectGross = min($grossSalary, $pensionCeiling);
        $pensionFee = $hasPension ? round($pensionSubjectGross * $pensionRate, 2) : 0.00;

        // 8. Military Stamp Fee (Tiered)
        $stampFee = $this->calculateStampFee($grossSalary, $settings->getResolvedStampFeeTiers());

        // 9. Statutory Deductions
        $statutoryDeductions = round($incomeTax + $pensionFee + $stampFee, 2);

        // 10. Net Before Advances
        $netBeforeAdvance = max(0.00, round($grossSalary - $statutoryDeductions - $otherDeductions, 2));

        // 11. Advance Deduction (cannot reduce net below 0)
        $advanceDeduction = min($availableAdvance, $netBeforeAdvance);

        // 12. Final Net Salary
        $netSalary = max(0.00, round($netBeforeAdvance - $advanceDeduction, 2));
        $totalDeductions = round($statutoryDeductions + $otherDeductions + $advanceDeduction, 2);

        // Build itemized components list
        $components = [
            [
                'type' => 'earning',
                'code' => 'base_pay',
                'name' => 'Հիմնական աշխատավարձ (Base Pay)',
                'rate' => null,
                'amount' => $regularPay,
            ],
        ];

        if ($overtimePay > 0) {
            $components[] = [
                'type' => 'earning',
                'code' => 'overtime',
                'name' => "Արտաժամյա աշխատանք ({$overtimeHours} ժամ x {$overtimeMultiplier})",
                'rate' => $overtimeMultiplier,
                'amount' => $overtimePay,
            ];
        }

        if ($nightPay > 0) {
            $components[] = [
                'type' => 'earning',
                'code' => 'night_shift',
                'name' => "Գիշերային հավելավճար ({$nightHours} ժամ x +{$nightMultiplier})",
                'rate' => $nightMultiplier,
                'amount' => $nightPay,
            ];
        }

        if ($holidayPay > 0) {
            $components[] = [
                'type' => 'earning',
                'code' => 'holiday',
                'name' => "Տոնական / Հանգստյան օրեր ({$holidayHours} ժամ x {$holidayMultiplier})",
                'rate' => $holidayMultiplier,
                'amount' => $holidayPay,
            ];
        }

        if ($bonuses > 0) {
            $components[] = [
                'type' => 'earning',
                'code' => 'bonus',
                'name' => 'Պարգևավճար / Բոնուսներ',
                'rate' => null,
                'amount' => $bonuses,
            ];
        }

        if ($allowances > 0) {
            $components[] = [
                'type' => 'earning',
                'code' => 'allowance',
                'name' => 'Հավելավճարներ / Փոխհատուցում',
                'rate' => null,
                'amount' => $allowances,
            ];
        }

        // Tax components
        $components[] = [
            'type' => 'tax',
            'code' => 'income_tax',
            'name' => 'Եկամտային հարկ (Income Tax 20%)',
            'rate' => $incomeTaxRate,
            'amount' => $incomeTax,
        ];

        if ($pensionFee > 0) {
            $components[] = [
                'type' => 'tax',
                'code' => 'pension',
                'name' => 'Կուտակային կենսաթոշակ (Pension 5%)',
                'rate' => $pensionRate,
                'amount' => $pensionFee,
            ];
        }

        if ($stampFee > 0) {
            $components[] = [
                'type' => 'tax',
                'code' => 'stamp_fee',
                'name' => 'Դրոշմանիշային վճար (Military Stamp Duty)',
                'rate' => null,
                'amount' => $stampFee,
            ];
        }

        if ($otherDeductions > 0) {
            $components[] = [
                'type' => 'deduction',
                'code' => 'other_deduction',
                'name' => 'Այլ պահումներ / Տուգանքներ',
                'rate' => null,
                'amount' => $otherDeductions,
            ];
        }

        if ($advanceDeduction > 0) {
            $components[] = [
                'type' => 'deduction',
                'code' => 'advance_recovery',
                'name' => 'Կանխավճարի մարում (Advance Recovery)',
                'rate' => null,
                'amount' => $advanceDeduction,
            ];
        }

        return [
            'base_salary' => $baseSalary,
            'worked_days' => $workedDays,
            'worked_hours' => $workedHours,
            'regular_pay' => $regularPay,
            'overtime_pay' => $overtimePay,
            'night_pay' => $nightPay,
            'holiday_pay' => $holidayPay,
            'bonuses_total' => $bonuses,
            'allowances_total' => $allowances,
            'gross_salary' => $grossSalary,
            'income_tax' => $incomeTax,
            'pension_fee' => $pensionFee,
            'stamp_fee' => $stampFee,
            'other_deductions' => $otherDeductions,
            'advance_deduction' => $advanceDeduction,
            'total_deductions' => $totalDeductions,
            'net_salary' => $netSalary,
            'components' => $components,
        ];
    }

    /**
     * Calculate military stamp duty from tiered brackets.
     */
    public function calculateStampFee(float $grossSalary, array $tiers): float
    {
        if ($grossSalary <= 0) {
            return 0.00;
        }

        foreach ($tiers as $tier) {
            $min = (float) $tier['min'];
            $max = isset($tier['max']) && $tier['max'] !== null ? (float) $tier['max'] : null;
            $fee = (float) $tier['fee'];

            if ($max !== null) {
                if ($grossSalary >= $min && $grossSalary <= $max) {
                    return $fee;
                }
            } else {
                if ($grossSalary >= $min) {
                    return $fee;
                }
            }
        }

        return 1500.00;
    }

    /**
     * Calculate entire payroll period for a vendor.
     */
    public function runPeriodCalculation(PayrollPeriod $period): PayrollPeriod
    {
        if ($period->isLocked()) {
            throw new InvalidArgumentException('Cannot recalculate a locked payroll period.');
        }

        $vendor = $period->vendor;
        $settings = $this->getEffectiveSettings($vendor, Carbon::parse($period->cutoff_date));

        return DB::transaction(function () use ($period, $vendor, $settings) {
            $period->update(['status' => 'calculating']);

            // Fetch active employees with profiles
            $employees = User::withoutGlobalScopes()
                ->where('vendor_id', $vendor->id)
                ->where('is_active', true)
                ->with(['employeeProfile', 'timesheets' => function ($q) use ($period) {
                    $q->where('period_start', '>=', $period->start_date)
                        ->where('period_end', '<=', $period->end_date);
                }])
                ->get();

            $totalGross = 0.00;
            $totalNet = 0.00;
            $totalTax = 0.00;
            $totalDeductions = 0.00;

            foreach ($employees as $employee) {
                $profile = $employee->employeeProfile;
                if (! $profile || $profile->employment_status === 'terminated') {
                    continue;
                }

                // Aggregate approved timesheets or default
                $timesheet = $employee->timesheets->first();
                $workedHours = $timesheet ? (float) $timesheet->worked_hours : 0.00;
                $regularHours = $timesheet ? (float) $timesheet->regular_hours : 0.00;
                $overtimeHours = $timesheet ? (float) $timesheet->overtime_hours : 0.00;
                $nightHours = $timesheet ? (float) $timesheet->night_hours : 0.00;
                $holidayHours = $timesheet ? (float) $timesheet->holiday_hours : 0.00;

                // Outstanding advances
                $advanceBalance = (float) EmployeeAdvance::withoutGlobalScopes()
                    ->where('vendor_id', $vendor->id)
                    ->where('user_id', $employee->id)
                    ->where('status', 'disbursed')
                    ->where('remaining_amount', '>', 0)
                    ->sum('remaining_amount');

                $calc = $this->calculateEmployeeSalary($settings, [
                    'base_salary' => (float) $profile->base_salary,
                    'hourly_rate' => (float) $profile->hourly_rate,
                    'compensation_type' => $profile->compensation_type,
                    'worked_days' => $timesheet ? round($workedHours / 8, 2) : 21,
                    'standard_days' => 21,
                    'worked_hours' => $workedHours,
                    'regular_hours' => $regularHours,
                    'overtime_hours' => $overtimeHours,
                    'night_hours' => $nightHours,
                    'holiday_hours' => $holidayHours,
                    'available_advance' => $advanceBalance,
                ]);

                // Create or update PayrollItem
                $item = PayrollItem::withoutGlobalScopes()->updateOrCreate(
                    [
                        'payroll_period_id' => $period->id,
                        'user_id' => $employee->id,
                    ],
                    [
                        'vendor_id' => $vendor->id,
                        'base_salary' => $calc['base_salary'],
                        'worked_days' => $calc['worked_days'],
                        'worked_hours' => $calc['worked_hours'],
                        'regular_pay' => $calc['regular_pay'],
                        'overtime_hours' => $overtimeHours,
                        'overtime_pay' => $calc['overtime_pay'],
                        'night_hours' => $nightHours,
                        'night_pay' => $calc['night_pay'],
                        'holiday_pay' => $calc['holiday_pay'],
                        'bonuses_total' => $calc['bonuses_total'],
                        'allowances_total' => $calc['allowances_total'],
                        'gross_salary' => $calc['gross_salary'],
                        'income_tax' => $calc['income_tax'],
                        'pension_fee' => $calc['pension_fee'],
                        'stamp_fee' => $calc['stamp_fee'],
                        'other_deductions' => $calc['other_deductions'],
                        'advance_deduction' => $calc['advance_deduction'],
                        'total_deductions' => $calc['total_deductions'],
                        'net_salary' => $calc['net_salary'],
                        'payment_status' => 'unpaid',
                        'calculation_breakdown' => $calc,
                    ]
                );

                // Re-sync item components
                $item->components()->delete();
                foreach ($calc['components'] as $comp) {
                    PayrollItemComponent::create([
                        'payroll_item_id' => $item->id,
                        'type' => $comp['type'],
                        'code' => $comp['code'],
                        'name' => $comp['name'],
                        'rate' => $comp['rate'],
                        'amount' => $comp['amount'],
                        'meta' => $comp['meta'] ?? null,
                    ]);
                }

                $totalGross += $calc['gross_salary'];
                $totalNet += $calc['net_salary'];
                $totalTax += ($calc['income_tax'] + $calc['pension_fee'] + $calc['stamp_fee']);
                $totalDeductions += $calc['total_deductions'];
            }

            $period->update([
                'total_gross' => round($totalGross, 2),
                'total_net' => round($totalNet, 2),
                'total_tax' => round($totalTax, 2),
                'total_deductions' => round($totalDeductions, 2),
                'status' => 'pending_approval',
                'snapshot_rules' => $settings->toArray(),
            ]);

            return $period->fresh(['items.user', 'items.components']);
        });
    }

    /**
     * Lock and finalize an approved payroll period.
     */
    public function lockPeriod(PayrollPeriod $period, User $actor): PayrollPeriod
    {
        if ($period->isLocked()) {
            return $period;
        }

        return DB::transaction(function () use ($period, $actor) {
            // Apply advance deductions against employee advances
            foreach ($period->items as $item) {
                if ($item->advance_deduction > 0) {
                    $this->settleAdvanceRecovery($item);
                }
            }

            $period->update([
                'status' => 'locked',
                'locked_by' => $actor->id,
                'locked_at' => Carbon::now(),
            ]);

            return $period->fresh();
        });
    }

    /**
     * Deduct recovered advances and update advance records.
     */
    protected function settleAdvanceRecovery(PayrollItem $item): void
    {
        $remainingToRecover = (float) $item->advance_deduction;

        $advances = EmployeeAdvance::withoutGlobalScopes()
            ->where('vendor_id', $item->vendor_id)
            ->where('user_id', $item->user_id)
            ->where('status', 'disbursed')
            ->where('remaining_amount', '>', 0)
            ->orderBy('requested_date', 'asc')
            ->get();

        foreach ($advances as $advance) {
            if ($remainingToRecover <= 0) {
                break;
            }

            $advRemaining = (float) $advance->remaining_amount;
            $deduct = min($remainingToRecover, $advRemaining);

            $newRemaining = round($advRemaining - $deduct, 2);
            $newRecovered = round((float) $advance->recovered_amount + $deduct, 2);

            $advance->update([
                'remaining_amount' => $newRemaining,
                'recovered_amount' => $newRecovered,
                'status' => $newRemaining <= 0 ? 'recovered' : 'disbursed',
                'payroll_period_id' => $item->payroll_period_id,
            ]);

            $remainingToRecover = round($remainingToRecover - $deduct, 2);
        }
    }
}
