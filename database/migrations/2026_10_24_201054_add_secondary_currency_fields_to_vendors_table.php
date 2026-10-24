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
        Schema::table('vendors', function (Blueprint $table) {
            if (! Schema::hasColumn('vendors', 'secondary_currency_enabled')) {
                $table->boolean('secondary_currency_enabled')->default(false)->after('currency');
            }
            if (! Schema::hasColumn('vendors', 'secondary_currency')) {
                $table->string('secondary_currency', 10)->nullable()->after('secondary_currency_enabled');
            }
            if (! Schema::hasColumn('vendors', 'secondary_currency_rate_mode')) {
                $table->string('secondary_currency_rate_mode', 20)->default('manual')->after('secondary_currency');
            }
            if (! Schema::hasColumn('vendors', 'secondary_currency_exchange_rate')) {
                $table->decimal('secondary_currency_exchange_rate', 16, 6)->nullable()->after('secondary_currency_rate_mode');
            }
            if (! Schema::hasColumn('vendors', 'secondary_currency_rate_provider')) {
                $table->string('secondary_currency_rate_provider', 50)->nullable()->after('secondary_currency_exchange_rate');
            }
            if (! Schema::hasColumn('vendors', 'secondary_currency_rate_updated_at')) {
                $table->timestamp('secondary_currency_rate_updated_at')->nullable()->after('secondary_currency_rate_provider');
            }
            if (! Schema::hasColumn('vendors', 'secondary_currency_decimal_places')) {
                $table->smallInteger('secondary_currency_decimal_places')->default(2)->after('secondary_currency_rate_updated_at');
            }
            if (! Schema::hasColumn('vendors', 'secondary_currency_prefix')) {
                $table->string('secondary_currency_prefix', 10)->default('≈')->after('secondary_currency_decimal_places');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $columnsToDrop = [];
            $columns = [
                'secondary_currency_prefix',
                'secondary_currency_decimal_places',
                'secondary_currency_rate_updated_at',
                'secondary_currency_rate_provider',
                'secondary_currency_exchange_rate',
                'secondary_currency_rate_mode',
                'secondary_currency',
                'secondary_currency_enabled',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('vendors', $column)) {
                    $columnsToDrop[] = $column;
                }
            }

            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
