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
        Schema::table('imap_accounts', function (Blueprint $table) {
            $table->string('sync_interval')->nullable()->after('delete_after_archive')
                ->comment('Auto-sync interval: null (manual), every_15_minutes, hourly, every_6_hours, daily, weekly');
            $table->timestamp('last_sync_at')->nullable()->after('sync_interval')
                ->comment('Last automatic sync timestamp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('imap_accounts', function (Blueprint $table) {
            $table->dropColumn(['sync_interval', 'last_sync_at']);
        });
    }
};
