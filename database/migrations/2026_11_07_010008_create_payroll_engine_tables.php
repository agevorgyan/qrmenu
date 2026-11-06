<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_payroll_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('country_code', 10)->default('AM');
            $table->decimal('income_tax_rate', 6, 4)->default(0.2000); // 20%
            $table->decimal('pension_rate', 6, 4)->default(0.0500);    // 5%
            $table->decimal('pension_ceiling_gross', 14, 2)->default(1125000.00);
            $table->jsonb('stamp_fee_tiers')->nullable();
            $table->decimal('overtime_rate_multiplier', 4, 2)->default(1.50);
            $table->decimal('night_rate_multiplier', 4, 2)->default(0.30);
            $table->decimal('holiday_rate_multiplier', 4, 2)->default(2.00);
            $table->integer('standard_monthly_hours')->default(168);
            $table->integer('standard_weekly_hours')->default(40);
            $table->date('effective_from')->useCurrent();
            $table->date('effective_until')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'effective_from'], 'uq_hr_payroll_settings_vendor_date');
        });

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('name');
            $table->string('cycle_type', 50)->default('monthly'); // monthly, semi_monthly, weekly, custom
            $table->date('start_date');
            $table->date('end_date');
            $table->date('cutoff_date');
            $table->string('currency', 10)->default('AMD');

            $table->decimal('total_gross', 14, 2)->default(0.00);
            $table->decimal('total_net', 14, 2)->default(0.00);
            $table->decimal('total_tax', 14, 2)->default(0.00);
            $table->decimal('total_deductions', 14, 2)->default(0.00);
            $table->decimal('total_paid', 14, 2)->default(0.00);

            $table->string('status', 50)->default('draft'); // draft, calculating, pending_approval, approved, paid, locked, cancelled
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('locked_at')->nullable();

            $table->jsonb('snapshot_rules')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'start_date', 'end_date', 'status']);
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->decimal('base_salary', 14, 2)->default(0.00);
            $table->decimal('worked_days', 5, 2)->default(0.00);
            $table->decimal('worked_hours', 8, 2)->default(0.00);

            $table->decimal('regular_pay', 14, 2)->default(0.00);
            $table->decimal('overtime_hours', 8, 2)->default(0.00);
            $table->decimal('overtime_pay', 14, 2)->default(0.00);
            $table->decimal('night_hours', 8, 2)->default(0.00);
            $table->decimal('night_pay', 14, 2)->default(0.00);
            $table->decimal('holiday_pay', 14, 2)->default(0.00);
            $table->decimal('bonuses_total', 14, 2)->default(0.00);
            $table->decimal('allowances_total', 14, 2)->default(0.00);

            $table->decimal('gross_salary', 14, 2)->default(0.00);

            // Taxes & Statutory Deductions
            $table->decimal('income_tax', 14, 2)->default(0.00);
            $table->decimal('pension_fee', 14, 2)->default(0.00);
            $table->decimal('stamp_fee', 14, 2)->default(0.00);
            $table->decimal('other_deductions', 14, 2)->default(0.00);
            $table->decimal('advance_deduction', 14, 2)->default(0.00);
            $table->decimal('total_deductions', 14, 2)->default(0.00);

            $table->decimal('net_salary', 14, 2)->default(0.00);
            $table->decimal('paid_amount', 14, 2)->default(0.00);
            $table->string('payment_status', 50)->default('unpaid'); // unpaid, partially_paid, paid

            $table->jsonb('calculation_breakdown')->nullable();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'user_id']);
            $table->index(['user_id', 'payment_status']);
            $table->index(['vendor_id', 'payment_status']);
        });

        Schema::create('payroll_item_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_item_id')->constrained('payroll_items')->cascadeOnDelete();
            $table->string('type', 50); // earning, deduction, tax, employer_contribution
            $table->string('code', 100);
            $table->string('name');
            $table->decimal('rate', 8, 4)->nullable();
            $table->decimal('amount', 14, 2);
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->index(['payroll_item_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_item_components');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('hr_payroll_settings');
    }
};
