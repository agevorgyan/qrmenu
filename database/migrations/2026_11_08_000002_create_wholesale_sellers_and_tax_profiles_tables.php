<?php

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
        if (! Schema::hasTable('wholesale_sellers')) {
            Schema::create('wholesale_sellers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->string('name', 200);
                $table->string('legal_name', 255);
                $table->string('tin', 20); // 8-digit Armenian Taxpayer Identification Number
                $table->string('vat_number', 30)->nullable(); // e.g. 08293072/1
                $table->string('tax_regime', 40)->default('vat_payer'); // vat_payer, turnover_tax, micro_business
                $table->text('address');
                $table->text('default_supply_location')->nullable();
                $table->unsignedBigInteger('default_bank_account_id')->nullable();
                $table->string('pek_username', 100)->nullable(); // Used in PEK XML <User> tag
                $table->string('phone', 50)->nullable();
                $table->string('email', 120)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['vendor_id', 'tin'], 'uq_wholesale_seller_vendor_tin');
                $table->index(['vendor_id', 'is_active'], 'idx_wholesale_seller_status');
                $table->index(['vendor_id', 'tax_regime'], 'idx_wholesale_seller_regime');
            });
        }

        if (! Schema::hasTable('wholesale_seller_tax_profiles')) {
            Schema::create('wholesale_seller_tax_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seller_id')->constrained('wholesale_sellers')->cascadeOnDelete();
                $table->string('tax_regime', 40); // vat_payer, turnover_tax, micro_business
                $table->boolean('is_vat_payer')->default(true);
                $table->decimal('vat_rate', 5, 2)->default(20.00);
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->string('document_series', 10)->default('A');
                $table->jsonb('settings')->nullable();
                $table->timestamps();

                $table->index(['seller_id', 'effective_from'], 'idx_seller_tax_effective');
            });
        }

        if (! Schema::hasTable('wholesale_seller_locations')) {
            Schema::create('wholesale_seller_locations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seller_id')->constrained('wholesale_sellers')->cascadeOnDelete();
                $table->string('name', 150);
                $table->text('address');
                $table->string('city', 100)->nullable();
                $table->string('region', 100)->nullable();
                $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->index(['seller_id', 'is_default'], 'idx_seller_location_default');
            });
        }

        if (! Schema::hasTable('wholesale_seller_bank_accounts')) {
            Schema::create('wholesale_seller_bank_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seller_id')->constrained('wholesale_sellers')->cascadeOnDelete();
                $table->string('bank_name', 150);
                $table->string('account_number', 50);
                $table->string('iban', 50)->nullable();
                $table->string('currency', 10)->default('AMD');
                $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->index(['seller_id', 'is_default'], 'idx_seller_bank_default');
            });
        }

        // Add foreign key constraint to default_bank_account_id after table creation
        Schema::table('wholesale_sellers', function (Blueprint $table) {
            $table->foreign('default_bank_account_id')
                ->references('id')
                ->on('wholesale_seller_bank_accounts')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('wholesale_sellers')) {
            Schema::table('wholesale_sellers', function (Blueprint $table) {
                $table->dropForeign(['default_bank_account_id']);
            });
        }

        Schema::dropIfExists('wholesale_seller_bank_accounts');
        Schema::dropIfExists('wholesale_seller_locations');
        Schema::dropIfExists('wholesale_seller_tax_profiles');
        Schema::dropIfExists('wholesale_sellers');
    }
};

