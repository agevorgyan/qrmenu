<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['vendor_id', 'name']);
            $table->index(['vendor_id', 'is_active']);
        });

        Schema::create('hr_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('default_pay_type', 50)->default('monthly'); // monthly, hourly, daily, shift
            $table->decimal('default_pay_rate', 14, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['vendor_id', 'title']);
            $table->index(['vendor_id', 'department_id']);
            $table->index(['vendor_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_positions');
        Schema::dropIfExists('hr_departments');
    }
};
