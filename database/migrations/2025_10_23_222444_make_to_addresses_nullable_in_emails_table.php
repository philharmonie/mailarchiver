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
            // Make address fields nullable to handle edge cases (BCC-only emails, malformed emails)
            $table->string('from_address')->nullable()->change();
            $table->json('to_addresses')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->string('from_address')->nullable(false)->change();
            $table->json('to_addresses')->nullable(false)->change();
        });
    }
};
