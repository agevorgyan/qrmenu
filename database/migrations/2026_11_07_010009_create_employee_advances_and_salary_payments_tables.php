<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('requested_date');
            $table->date('disbursement_date')->nullable();
            $table->string('payment_method', 50)->default('cash'); // cash, bank_transfer, other
            $table->decimal('recovered_amount', 14, 2)->default(0.00);
            $table->decimal('remaining_amount', 14, 2);
            $table->string('status', 50)->default('pending'); // pending, approved, disbursed, recovered, cancelled
            $table->text('reason');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('payroll_period_id')->nullable()->constrained('payroll_periods')->nullOnDelete();
            $table->timestamps();

            $table->index(['vendor_id', 'user_id', 'status']);
        });

        Schema::create('salary_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignId('payroll_item_id')->constrained('payroll_items')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('payment_number', 100);
            $table->date('payment_date');
            $table->decimal('amount', 14, 2);
            $table->string('payment_method', 50)->default('bank_transfer'); // bank_transfer, cash, other
            $table->string('reference_number')->nullable();
            $table->string('proof_document_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 50)->default('completed'); // completed, voided
            $table->timestamps();

            $table->unique(['vendor_id', 'payment_number']);
            $table->index(['payroll_item_id']);
            $table->index(['vendor_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_payments');
        Schema::dropIfExists('employee_advances');
    }
};
