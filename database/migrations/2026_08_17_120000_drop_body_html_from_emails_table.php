<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The HTML body has not been written since Email::deriveBodyHtml() landed: it
 * is in `raw_email`, which is kept byte-exact anyway, and it is read once -
 * when somebody opens a single mail. Nothing searches it; search runs on
 * body_text, which stays.
 *
 * What the column still holds is the copy made before that, 385 MB of it on
 * the live archive. Dropping it loses nothing that cannot be derived again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropColumn('body_html');
        });
    }

    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            // Comes back empty. What stood here is derived from raw_email now,
            // and re-deriving 13.000 mails is not a schema change's job.
            $table->longText('body_html')->nullable()->after('body_text');
        });
    }
};
