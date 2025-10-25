<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL requires raw SQL to change TEXT to BLOB
        if (config('database.default') === 'mysql') {
            DB::statement('ALTER TABLE emails MODIFY raw_email LONGBLOB');
        } else {
            // SQLite and other databases
            Schema::table('emails', function (Blueprint $table) {
                $table->binary('raw_email')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement('ALTER TABLE emails MODIFY raw_email LONGTEXT');
        } else {
            Schema::table('emails', function (Blueprint $table) {
                $table->text('raw_email')->change();
            });
        }
    }
};
