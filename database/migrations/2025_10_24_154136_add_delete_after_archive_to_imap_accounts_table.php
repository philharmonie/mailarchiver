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
            $table->boolean('delete_after_archive')->default(false)->after('is_active')
                ->comment('Delete emails from server after successful archival (opt-in)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('imap_accounts', function (Blueprint $table) {
            $table->dropColumn('delete_after_archive');
        });
    }
};
