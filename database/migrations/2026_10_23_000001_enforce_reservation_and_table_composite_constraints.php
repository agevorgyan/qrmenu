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
        // 1. Composite Unique Constraints on Parent Tables (Prerequisite for Composite Foreign Keys)
        $uniques = [
            'restaurant_tables' => ['columns' => ['id', 'vendor_id'], 'name' => 'restaurant_tables_id_vendor_id_unique'],
            'reservations' => ['columns' => ['id', 'vendor_id'], 'name' => 'reservations_id_vendor_id_unique'],
        ];

        foreach ($uniques as $table => $cfg) {
            if (Schema::hasTable($table)) {
                try {
                    Schema::table($table, function (Blueprint $t) use ($cfg) {
                        $t->unique($cfg['columns'], $cfg['name']);
                    });
                } catch (Throwable $e) {
                    // Unique constraint may already exist
                }
            }
        }

        // 2. Composite Foreign Keys on Child Tables (Cross-Tenant Relationship Enforcement)
        $fks = [
            'restaurant_tables' => [
                [
                    'columns' => ['location_id', 'vendor_id'],
                    'name' => 'restaurant_tables_loc_vendor_fk',
                    'ref_columns' => ['id', 'vendor_id'],
                    'on' => 'locations',
                ],
            ],
            'reservations' => [
                [
                    'columns' => ['location_id', 'vendor_id'],
                    'name' => 'reservations_loc_vendor_fk',
                    'ref_columns' => ['id', 'vendor_id'],
                    'on' => 'locations',
                ],
                [
                    'columns' => ['restaurant_table_id', 'vendor_id'],
                    'name' => 'reservations_table_vendor_fk',
                    'ref_columns' => ['id', 'vendor_id'],
                    'on' => 'restaurant_tables',
                ],
            ],
            'reservation_pre_orders' => [
                [
                    'columns' => ['reservation_id', 'vendor_id'],
                    'name' => 'res_preorders_res_vendor_fk',
                    'ref_columns' => ['id', 'vendor_id'],
                    'on' => 'reservations',
                ],
            ],
        ];

        foreach ($fks as $table => $tableFks) {
            if (Schema::hasTable($table)) {
                foreach ($tableFks as $fk) {
                    try {
                        Schema::table($table, function (Blueprint $t) use ($fk) {
                            $t->foreign($fk['columns'], $fk['name'])
                                ->references($fk['ref_columns'])
                                ->on($fk['on'])
                                ->cascadeOnDelete();
                        });
                    } catch (Throwable $e) {
                        // FK constraint may already exist
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Drop Composite Foreign Keys
        $fks = [
            'reservation_pre_orders' => ['res_preorders_res_vendor_fk'],
            'reservations' => ['reservations_loc_vendor_fk', 'reservations_table_vendor_fk'],
            'restaurant_tables' => ['restaurant_tables_loc_vendor_fk'],
        ];

        foreach ($fks as $table => $foreignKeys) {
            if (Schema::hasTable($table)) {
                foreach ($foreignKeys as $fkName) {
                    try {
                        Schema::table($table, function (Blueprint $t) use ($fkName) {
                            $t->dropForeign($fkName);
                        });
                    } catch (Throwable $e) {
                        // Constraint may not exist
                    }
                }
            }
        }

        // 2. Drop Composite Unique Constraints
        $uniques = [
            'reservations' => 'reservations_id_vendor_id_unique',
            'restaurant_tables' => 'restaurant_tables_id_vendor_id_unique',
        ];

        foreach ($uniques as $table => $uniqueName) {
            if (Schema::hasTable($table)) {
                try {
                    Schema::table($table, function (Blueprint $t) use ($uniqueName) {
                        $t->dropUnique($uniqueName);
                    });
                } catch (Throwable $e) {
                    // Unique index may not exist
                }
            }
        }
    }
};
