<?php

namespace Database\Seeders;

use App\Models\HrDepartment;
use App\Models\HrPayrollSetting;
use App\Models\HrPosition;
use App\Models\LeaveType;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class HrDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $vendors = Vendor::all();

        foreach ($vendors as $vendor) {
            self::seedDefaultsForVendor($vendor);
        }
    }

    public static function seedDefaultsForVendor(Vendor $vendor): void
    {
        // 1. Leave Types
        $defaultLeaves = [
            ['name' => 'Ամենամյա արձակուրդ', 'code' => 'annual', 'is_paid' => true, 'default_days_per_year' => 20, 'requires_approval' => true],
            ['name' => 'Անաշխատունակություն (Sick Leave)', 'code' => 'sick', 'is_paid' => true, 'default_days_per_year' => 14, 'requires_approval' => true, 'requires_attachment' => true],
            ['name' => 'Անվճար արձակուրդ', 'code' => 'unpaid', 'is_paid' => false, 'default_days_per_year' => 30, 'requires_approval' => true],
            ['name' => 'Ընտանեկան / Անձնական', 'code' => 'personal', 'is_paid' => true, 'default_days_per_year' => 5, 'requires_approval' => true],
            ['name' => 'Մայրության / Հայրության', 'code' => 'maternity', 'is_paid' => true, 'default_days_per_year' => 140, 'requires_approval' => true, 'requires_attachment' => true],
        ];

        foreach ($defaultLeaves as $leave) {
            LeaveType::firstOrCreate(
                [
                    'vendor_id' => $vendor->id,
                    'code' => $leave['code'],
                ],
                [
                    'name' => $leave['name'],
                    'is_paid' => $leave['is_paid'],
                    'default_days_per_year' => $leave['default_days_per_year'],
                    'requires_approval' => $leave['requires_approval'],
                    'requires_attachment' => $leave['requires_attachment'] ?? false,
                    'is_active' => true,
                ]
            );
        }

        // 2. Payroll Settings (Armenian standard defaults)
        HrPayrollSetting::firstOrCreate(
            [
                'vendor_id' => $vendor->id,
                'effective_from' => '2026-01-01',
            ],
            [
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
            ]
        );

        // 3. Departments
        $kitchen = HrDepartment::firstOrCreate(['vendor_id' => $vendor->id, 'name' => 'Խոհանոց'], ['description' => 'Խոհարարական բաժին', 'sort_order' => 1]);
        $hall = HrDepartment::firstOrCreate(['vendor_id' => $vendor->id, 'name' => 'Սրահ'], ['description' => 'Սպասարկման սրահ', 'sort_order' => 2]);
        $bar = HrDepartment::firstOrCreate(['vendor_id' => $vendor->id, 'name' => 'Բար'], ['description' => 'Խմիչքների և բարի բաժին', 'sort_order' => 3]);
        $delivery = HrDepartment::firstOrCreate(['vendor_id' => $vendor->id, 'name' => 'Առաքում'], ['description' => 'Առաքման ծառայություն', 'sort_order' => 4]);
        $admin = HrDepartment::firstOrCreate(['vendor_id' => $vendor->id, 'name' => 'Վարչական'], ['description' => 'Կառավարում և հաշվապահություն', 'sort_order' => 5]);

        // 4. Positions
        HrPosition::firstOrCreate(['vendor_id' => $vendor->id, 'title' => 'Գլխավոր Խոհարար'], ['department_id' => $kitchen->id, 'default_pay_type' => 'monthly', 'default_pay_rate' => 350000.00]);
        HrPosition::firstOrCreate(['vendor_id' => $vendor->id, 'title' => 'Խոհարար'], ['department_id' => $kitchen->id, 'default_pay_type' => 'monthly', 'default_pay_rate' => 220000.00]);
        HrPosition::firstOrCreate(['vendor_id' => $vendor->id, 'title' => 'Մատուցող'], ['department_id' => $hall->id, 'default_pay_type' => 'hourly', 'default_pay_rate' => 1200.00]);
        HrPosition::firstOrCreate(['vendor_id' => $vendor->id, 'title' => 'Բարիստա'], ['department_id' => $bar->id, 'default_pay_type' => 'monthly', 'default_pay_rate' => 180000.00]);
        HrPosition::firstOrCreate(['vendor_id' => $vendor->id, 'title' => 'Առաքիչ'], ['department_id' => $delivery->id, 'default_pay_type' => 'shift', 'default_pay_rate' => 10000.00]);
        HrPosition::firstOrCreate(['vendor_id' => $vendor->id, 'title' => 'Մենեջեր'], ['department_id' => $admin->id, 'default_pay_type' => 'monthly', 'default_pay_rate' => 300000.00]);
    }
}
