<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('contract_number', 100);
            $table->string('contract_type', 50)->default('indefinite'); // indefinite, fixed_term, service, internship
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('probation_end_date')->nullable();
            $table->decimal('wage_amount', 14, 2)->default(0.00);
            $table->string('wage_type', 50)->default('monthly'); // monthly, hourly, daily, shift
            $table->integer('working_hours_per_week')->default(40);
            $table->string('file_path')->nullable();
            $table->string('status', 50)->default('active'); // draft, active, expiring, expired, terminated
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['vendor_id', 'contract_number']);
            $table->index(['vendor_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('category', 50)->default('contract'); // contract, id, medical, decree, other
            $table->string('file_path');
            $table->bigInteger('file_size_bytes')->default(0);
            $table->string('mime_type', 100)->default('application/pdf');
            $table->date('expires_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vendor_id', 'user_id']);
            $table->index(['user_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employment_contracts');
    }
};
