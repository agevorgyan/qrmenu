<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create restaurant_tables table
        if (! Schema::hasTable('restaurant_tables')) {
            Schema::create('restaurant_tables', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();

                $table->string('table_number', 50);
                $table->string('name', 100)->nullable();
                $table->unsignedSmallInteger('capacity')->default(4);
                $table->unsignedSmallInteger('min_capacity')->default(1);
                $table->string('section', 100)->nullable(); // e.g. "Main Hall", "Terrace", "VIP Room"
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);

                $table->timestamps();
                $table->softDeletes();

                $table->index(['vendor_id', 'is_active']);
                $table->index(['vendor_id', 'location_id']);
                $table->index(['vendor_id', 'section']);
            });

            // PostgreSQL unique partial index on vendor, location, table_number for active/non-deleted rows
            DB::statement('CREATE UNIQUE INDEX restaurant_tables_vendor_loc_num_unique ON restaurant_tables (vendor_id, COALESCE(location_id, 0), table_number) WHERE deleted_at IS NULL');
        }

        // 2. Add duration_minutes and restaurant_table_id to reservations
        Schema::table('reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('reservations', 'duration_minutes')) {
                $table->unsignedSmallInteger('duration_minutes')->default(90)->after('reservation_datetime');
            }
            if (! Schema::hasColumn('reservations', 'restaurant_table_id')) {
                $table->foreignId('restaurant_table_id')->nullable()->after('table_number')->constrained('restaurant_tables')->nullOnDelete();
            }
        });

        // 3. Add reservation_settings to vendors table
        Schema::table('vendors', function (Blueprint $table) {
            if (! Schema::hasColumn('vendors', 'reservation_settings')) {
                $table->jsonb('reservation_settings')->nullable()->after('reservation_notification_settings');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (Schema::hasColumn('vendors', 'reservation_settings')) {
                $table->dropColumn('reservation_settings');
            }
        });

        Schema::table('reservations', function (Blueprint $table) {
            if (Schema::hasColumn('reservations', 'restaurant_table_id')) {
                $table->dropForeign(['restaurant_table_id']);
                $table->dropColumn('restaurant_table_id');
            }
            if (Schema::hasColumn('reservations', 'duration_minutes')) {
                $table->dropColumn('duration_minutes');
            }
        });

        Schema::dropIfExists('restaurant_tables');
    }
};
