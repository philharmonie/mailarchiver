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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('user')->after('email')->comment('admin or user');
            $table->foreignId('imap_account_id')->nullable()->after('role')->constrained()->nullOnDelete()->comment('IMAP account for regular users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['imap_account_id']);
            $table->dropColumn(['role', 'imap_account_id']);
        });
    }
};
