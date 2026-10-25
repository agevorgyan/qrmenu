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
        if (Schema::hasTable('event_inquiries') && ! Schema::hasColumn('event_inquiries', 'customer_id')) {
            Schema::table('event_inquiries', function (Blueprint $table) {
                $table->unsignedBigInteger('customer_id')->nullable()->after('location_id')->index();
                $table->foreign('customer_id', 'ev_inq_customer_id_fk')->references('id')->on('customers')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('event_inquiries') && Schema::hasColumn('event_inquiries', 'customer_id')) {
            Schema::table('event_inquiries', function (Blueprint $table) {
                $table->dropForeign('ev_inq_customer_id_fk');
                $table->dropColumn('customer_id');
            });
        }
    }
};
