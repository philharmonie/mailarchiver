<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * See the raw_parts table.
 *
 * `emails.raw_parts` lists the parts a mail was split into - null for mail
 * that was never split, an empty list for mail that was looked at and had
 * nothing worth lifting out. The markers in raw_email name the parts too, so
 * this column is for reference counting and for telling those two states
 * apart, not for reassembly.
 *
 * An attachment with `raw_part_hash` has no file of its own: its bytes are the
 * ones inside that part, and they are decoded on the way out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->json('raw_parts')->nullable()->after('raw_email');
        });

        Schema::table('attachments', function (Blueprint $table) {
            $table->string('raw_part_hash', 64)->nullable()->after('storage_disk')->index();
            $table->string('raw_part_encoding', 20)->nullable()->after('raw_part_hash');
            $table->string('storage_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropColumn('raw_parts');
        });

        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn(['raw_part_hash', 'raw_part_encoding']);
        });
    }
};
