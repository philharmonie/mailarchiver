<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 255 characters is what `$table->string()` happens to default to, not a
     * length a subject line has. MySQL in strict mode refuses the row that
     * exceeds it rather than trimming it, so those mails were not archived at
     * all - and the next sync tried them again.
     *
     * 512 is as far as this can go while the column stays indexed: `subject`
     * carries an index of its own and one with `received_at`, and InnoDB
     * allows a key of 3072 bytes, which is 768 characters of utf8mb4 before
     * the second column of the pair is counted.
     *
     * The column keeps its indexes across a MODIFY - MySQL rebuilds them.
     */
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->string('subject', 512)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Only possible while no archived subject is longer than 255, which stops
     * being true as soon as one arrives - by design, since that is the whole
     * point of the column being wider.
     */
    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->string('subject', 255)->change();
        });
    }
};
