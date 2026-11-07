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
        Schema::table('journal_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('journal_entries', 'previous_hash')) {
                $table->string('previous_hash', 64)->nullable()->after('posted_at');
            }
            if (! Schema::hasColumn('journal_entries', 'entry_hash')) {
                $table->string('entry_hash', 64)->nullable()->after('previous_hash');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            if (Schema::hasColumn('journal_entries', 'entry_hash')) {
                $table->dropColumn('entry_hash');
            }
            if (Schema::hasColumn('journal_entries', 'previous_hash')) {
                $table->dropColumn('previous_hash');
            }
        });
    }
};
