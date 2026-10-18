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
            $table->jsonb('reservation_notification_settings')->nullable()->after('telegram_settings');
            $table->jsonb('superadmin_notification_restrictions')->nullable()->after('reservation_notification_settings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn([
                'reservation_notification_settings',
                'superadmin_notification_restrictions',
            ]);
        });
    }
};
