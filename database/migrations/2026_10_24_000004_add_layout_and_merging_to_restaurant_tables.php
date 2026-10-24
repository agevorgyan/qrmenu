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
        Schema::table('restaurant_tables', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurant_tables', 'shape')) {
                $table->string('shape', 20)->default('square')->after('section');
            }
            if (! Schema::hasColumn('restaurant_tables', 'x')) {
                $table->integer('x')->default(50)->after('shape');
            }
            if (! Schema::hasColumn('restaurant_tables', 'y')) {
                $table->integer('y')->default(50)->after('x');
            }
            if (! Schema::hasColumn('restaurant_tables', 'width')) {
                $table->integer('width')->nullable()->after('y');
            }
            if (! Schema::hasColumn('restaurant_tables', 'height')) {
                $table->integer('height')->nullable()->after('width');
            }
            if (! Schema::hasColumn('restaurant_tables', 'merged_with_id')) {
                $table->foreignId('merged_with_id')->nullable()->after('height')->constrained('restaurant_tables')->nullOnDelete();
            }
            if (! Schema::hasColumn('restaurant_tables', 'merged_table_ids')) {
                $table->json('merged_table_ids')->nullable()->after('merged_with_id');
            }
            if (! Schema::hasColumn('restaurant_tables', 'combined_capacity')) {
                $table->unsignedSmallInteger('combined_capacity')->nullable()->after('merged_table_ids');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            if (Schema::hasColumn('restaurant_tables', 'merged_with_id')) {
                $table->dropForeign(['merged_with_id']);
                $table->dropColumn('merged_with_id');
            }
            $colsToDrop = ['shape', 'x', 'y', 'width', 'height', 'merged_table_ids', 'combined_capacity'];
            foreach ($colsToDrop as $col) {
                if (Schema::hasColumn('restaurant_tables', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
