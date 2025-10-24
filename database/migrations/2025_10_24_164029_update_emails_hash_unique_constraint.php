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
        Schema::table('emails', function (Blueprint $table) {
            // Drop the unique constraint on hash alone
            $table->dropUnique(['hash']);

            // Add composite unique constraint on hash and bcc_map_type
            // This allows the same email (hash) to exist twice: once as sender, once as recipient
            $table->unique(['hash', 'bcc_map_type'], 'emails_hash_bcc_map_type_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            // Drop the composite unique constraint
            $table->dropUnique('emails_hash_bcc_map_type_unique');

            // Restore the original unique constraint on hash
            $table->unique('hash');
        });
    }
};
