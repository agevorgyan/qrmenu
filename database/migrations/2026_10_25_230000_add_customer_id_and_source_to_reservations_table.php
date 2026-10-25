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
        Schema::table('reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('reservations', 'customer_id')) {
                $table->foreignId('customer_id')
                    ->nullable()
                    ->after('location_id')
                    ->constrained('customers')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('reservations', 'source')) {
                $table->string('source', 50)->default('online')->nullable()->after('status');
            }

            $table->string('customer_email')->nullable()->change();

            $table->index(['vendor_id', 'customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (Schema::hasColumn('reservations', 'customer_id')) {
                $table->dropForeign(['customer_id']);
                $table->dropIndex(['vendor_id', 'customer_id']);
                $table->dropColumn('customer_id');
            }

            if (Schema::hasColumn('reservations', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
