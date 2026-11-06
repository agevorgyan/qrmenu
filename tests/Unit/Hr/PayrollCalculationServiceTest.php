<?php

namespace Tests\Unit\Hr;

use App\Models\HrPayrollSetting;
use App\Models\Vendor;
use App\Services\HrPayrollService;
use Tests\TestCase;

class PayrollCalculationServiceTest extends TestCase
{
    protected HrPayrollService $payrollService;

    protected Vendor $vendor;

    protected HrPayrollSetting $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->payrollService = new HrPayrollService;

        $this->vendor = new Vendor(['id' => 999, 'name' => 'Test Kitchen']);

        $this->settings = new HrPayrollSetting([
            'vendor_id' => 999,
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

    public function test_standard_monthly_gross_to_net_calculation(): void
    {
        // 300,000 AMD Base Monthly Salary, fully worked 21 days
        $calc = $this->payrollService->calculateGrossToNet(
            settings: $this->settings,
            inputs: [
                'base_salary' => 300000.00,
                'compensation_type' => 'monthly',
                'worked_days' => 21,
                'standard_days' => 21,
                'regular_hours' => 168,
                'overtime_hours' => 0,
                'night_hours' => 0,
                'bonuses' => 0,
                'has_pension' => true,
            ]
        );

        // Gross = 300,000
        $this->assertEquals(300000.00, $calc['gross_salary']);

        // Income tax = 20% of 300,000 = 60,000
        $this->assertEquals(60000.00, $calc['income_tax']);

        // Pension = 5% of 300,000 = 15,000
        $this->assertEquals(15000.00, $calc['pension_fee']);

        // Military stamp duty for 300,000 AMD bracket (200,001 - 500,000) = 5,500
        $this->assertEquals(5500.00, $calc['stamp_fee']);

        // Total deductions = 60,000 + 15,000 + 5,500 = 80,500
        $this->assertEquals(80500.00, $calc['total_deductions']);

        // Net salary = 300,000 - 80,500 = 219,500
        $this->assertEquals(219500.00, $calc['net_salary']);
    }

    public function test_hourly_rate_and_overtime_premium_calculation(): void
    {
        // 2,000 AMD / hour. Worked 160 regular hours + 10 overtime hours
        $calc = $this->payrollService->calculateGrossToNet(
            settings: $this->settings,
            inputs: [
                'hourly_rate' => 2000.00,
                'compensation_type' => 'hourly',
                'regular_hours' => 160,
                'overtime_hours' => 10,
                'night_hours' => 0,
                'has_pension' => true,
            ]
        );

        // Regular pay = 160 * 2000 = 320,000
        $this->assertEquals(320000.00, $calc['regular_pay']);

        // Overtime pay = 10 * 2000 * 1.5 = 30,000
        $this->assertEquals(30000.00, $calc['overtime_pay']);

        // Gross = 320,000 + 30,000 = 350,000
        $this->assertEquals(350000.00, $calc['gross_salary']);

        // Income Tax = 20% of 350,000 = 70,000
        $this->assertEquals(70000.00, $calc['income_tax']);

        // Pension = 5% of 350,000 = 17,500
        $this->assertEquals(17500.00, $calc['pension_fee']);

        // Stamp Duty (200k - 500k) = 5,500
        $this->assertEquals(5500.00, $calc['stamp_fee']);

        // Net = 350,000 - (70,000 + 17,500 + 5,500) = 257,000
        $this->assertEquals(257000.00, $calc['net_salary']);
    }

    public function test_night_shift_premium_calculation(): void
    {
        // Base monthly 250,000 AMD (approx 1,488.10/h). 20 hours of night shifts (30% premium)
        $hourlyEquivalent = round(250000.00 / 168, 2);
        $expectedNightPremium = round(20 * $hourlyEquivalent * 0.30, 2);

        $calc = $this->payrollService->calculateGrossToNet(
            settings: $this->settings,
            inputs: [
                'base_salary' => 250000.00,
                'compensation_type' => 'monthly',
                'worked_days' => 21,
                'standard_days' => 21,
                'regular_hours' => 168,
                'night_hours' => 20,
                'has_pension' => false, // Employee opted out / born before 1974
            ]
        );

        $this->assertEquals($expectedNightPremium, $calc['night_pay']);
        $this->assertEquals(0.00, $calc['pension_fee']);
        $this->assertGreaterThan(250000.00, $calc['gross_salary']);
    }

    public function test_pension_statutory_ceiling_enforcement(): void
    {
        // High earner: 1,500,000 AMD Gross
        // Pension 5% should cap at 1,125,000 AMD => max pension is 56,250 AMD
        $calc = $this->payrollService->calculateGrossToNet(
            settings: $this->settings,
            inputs: [
                'base_salary' => 1500000.00,
                'compensation_type' => 'monthly',
                'worked_days' => 21,
                'standard_days' => 21,
                'has_pension' => true,
            ]
        );

        $this->assertEquals(1500000.00, $calc['gross_salary']);
        $this->assertEquals(300000.00, $calc['income_tax']); // 20% of 1.5M

        // Pension must be capped at 5% of 1,125,000 = 56,250
        $this->assertEquals(56250.00, $calc['pension_fee']);

        // Stamp duty for > 1,000,000 AMD bracket = 15,000 AMD
        $this->assertEquals(15000.00, $calc['stamp_fee']);
    }

    public function test_advance_deduction_recovery_and_cap(): void
    {
        // Salary 200,000 AMD. Employee took an advance of 50,000 AMD.
        $calc = $this->payrollService->calculateGrossToNet(
            settings: $this->settings,
            inputs: [
                'base_salary' => 200000.00,
                'compensation_type' => 'monthly',
                'worked_days' => 21,
                'standard_days' => 21,
                'has_pension' => true,
                'available_advance' => 50000.00,
            ]
        );

        // Gross = 200,000
        // Income Tax = 40,000
        // Pension = 10,000
        // Stamp duty (100k-200k) = 3,000
        // Net before advance = 200,000 - 53,000 = 147,000
        // Advance deduction = 50,000
        // Final Net = 147,000 - 50,000 = 97,000
        $this->assertEquals(50000.00, $calc['advance_deduction']);
        $this->assertEquals(97000.00, $calc['net_salary']);
    }
}
