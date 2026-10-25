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
        Schema::table('ai_waiter_sessions', function (Blueprint $table) {
            $table->string('entry_context', 32)->default('general_menu')->after('table_number');
            $table->json('conversation_state')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_waiter_sessions', function (Blueprint $table) {
            $table->dropColumn(['entry_context', 'conversation_state']);
        });
    }
};
