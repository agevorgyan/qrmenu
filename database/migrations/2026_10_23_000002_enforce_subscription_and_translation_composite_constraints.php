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
            'subscriptions' => ['columns' => ['id', 'vendor_id'], 'name' => 'subscriptions_id_vendor_id_unique'],
            'content_translations' => ['columns' => ['id', 'vendor_id'], 'name' => 'content_translations_id_vendor_id_unique'],
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
            'subscription_payments' => [
                [
                    'columns' => ['subscription_id', 'vendor_id'],
                    'name' => 'sub_payments_sub_vendor_fk',
                    'ref_columns' => ['id', 'vendor_id'],
                    'on' => 'subscriptions',
                    'action' => 'null',
                ],
            ],
            'translation_histories' => [
                [
                    'columns' => ['content_translation_id', 'vendor_id'],
                    'name' => 'trans_histories_ct_vendor_fk',
                    'ref_columns' => ['id', 'vendor_id'],
                    'on' => 'content_translations',
                    'action' => 'cascade',
                ],
            ],
        ];

        foreach ($fks as $table => $tableFks) {
            if (Schema::hasTable($table)) {
                foreach ($tableFks as $fk) {
                    try {
                        Schema::table($table, function (Blueprint $t) use ($fk) {
                            $foreign = $t->foreign($fk['columns'], $fk['name'])
                                ->references($fk['ref_columns'])
                                ->on($fk['on']);

                            if ($fk['action'] === 'cascade') {
                                $foreign->cascadeOnDelete();
                            } elseif ($fk['action'] === 'null') {
                                $foreign->nullOnDelete();
                            }
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
            'subscription_payments' => ['sub_payments_sub_vendor_fk'],
            'translation_histories' => ['trans_histories_ct_vendor_fk'],
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
            'subscriptions' => 'subscriptions_id_vendor_id_unique',
            'content_translations' => 'content_translations_id_vendor_id_unique',
        ];

        foreach ($uniques as $table => $uniqueName) {
            if (Schema::hasTable($table)) {
                try {
                    Schema::table($table, function (Blueprint $t) use ($uniqueName) {
                        $t->dropUnique($uniqueName);
                    });
                } catch (Throwable $e) {
                    // Unique constraint may not exist
                }
            }
        }
    }
};
