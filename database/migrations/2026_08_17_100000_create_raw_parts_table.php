<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The bulk of an archived mail is its attachments, base64-encoded inside
 * `raw_email` - and the archive used to keep them a second time, decoded, as
 * attachment files. 13.000 mails cost 1.9 GB in the column and 1.9 GB on disk
 * for the same bytes.
 *
 * A raw part is one of those base64 runs, lifted out of the mail and stored
 * once. What stays in `raw_email` is a marker naming the part by hash, so the
 * original mail can be put back together byte for byte - which it has to be:
 * the GoBD hash is taken over the mail as it arrived.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_parts', function (Blueprint $table) {
            $table->id();

            // SHA-256 over the encoded bytes exactly as they sit in the mail,
            // which is what makes two mails carrying the same attachment share
            // one part.
            $table->string('hash', 64)->unique();

            $table->unsignedBigInteger('size_bytes');
            $table->string('storage_path');
            $table->string('storage_disk')->default('local');
            $table->boolean('is_compressed')->default(false);

            // How many mails point at this part. The file goes when it hits 0.
            $table->unsignedInteger('reference_count')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_parts');
    }
};
