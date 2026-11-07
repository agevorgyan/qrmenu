<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wholesale_tax_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('wholesale_sellers')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('wholesale_customers')->restrictOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('wholesale_sales')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('wholesale_orders')->nullOnDelete();

            $table->string('document_type', 30); // TAX_INVOICE, ACCOUNTING_DOCUMENT
            $table->string('series', 10)->default('A');
            $table->string('document_number', 30);
            $table->date('issue_date');
            $table->date('supply_date');
            $table->string('status', 20)->default('DRAFT'); // DRAFT, GENERATED, EXPORTED, CANCELLED

            $table->boolean('is_adjustment')->default(false);
            $table->string('parent_document_number', 50)->nullable();
            $table->date('parent_document_date')->nullable();

            $table->string('currency', 3)->default('AMD');
            $table->decimal('taxable_amount', 15, 2)->default(0.00);
            $table->decimal('vat_amount', 15, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2)->default(0.00);

            $table->json('seller_snapshot');
            $table->json('customer_snapshot');
            $table->longText('xml_content')->nullable();
            $table->boolean('is_xsd_valid')->default(false);
            $table->json('xsd_validation_errors')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['vendor_id', 'series', 'document_number']);
            $table->index(['vendor_id', 'document_type']);
            $table->index(['vendor_id', 'status']);
        });

        Schema::create('wholesale_tax_document_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_document_id')->constrained('wholesale_tax_documents')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('wholesale_products')->nullOnDelete();

            $table->unsignedInteger('line_number')->default(1);
            $table->text('description');
            $table->string('classifier_code', 20); // ATGAA
            $table->string('unit', 20)->default('հատ');
            $table->decimal('quantity', 12, 4);
            $table->decimal('unit_price', 15, 4);
            $table->decimal('taxable_price', 15, 2);
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->decimal('vat_amount', 15, 2)->nullable();
            $table->string('deal_type', 50)->nullable(); // e.g. SPECIAL_TAX_SYSTEM
            $table->decimal('total_price', 15, 2);
            $table->string('good_parent_id', 50)->nullable();
            $table->timestamps();

            $table->index(['tax_document_id', 'line_number']);
        });

        Schema::create('wholesale_pek_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('export_batch_number', 50)->unique();
            $table->unsignedInteger('document_count')->default(1);
            $table->string('document_type', 30)->default('TAX_INVOICE'); // TAX_INVOICE, ACCOUNTING_DOCUMENT, MIXED
            $table->string('storage_path');
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->string('status', 20)->default('SUCCESS');
            $table->json('document_ids');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vendor_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wholesale_pek_exports');
        Schema::dropIfExists('wholesale_tax_document_items');
        Schema::dropIfExists('wholesale_tax_documents');
    }
};
