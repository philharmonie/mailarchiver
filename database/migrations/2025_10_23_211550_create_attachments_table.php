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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();

            // Foreign key to emails
            $table->foreignId('email_id')->constrained()->cascadeOnDelete();

            // File information
            $table->string('filename')->index();
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');

            // GoBD compliance
            $table->string('hash', 64)->comment('SHA-256 hash for integrity verification');

            // Storage
            $table->string('storage_path');
            $table->string('storage_disk')->default('local');

            // Metadata
            $table->string('content_id')->nullable()->comment('Content-ID for inline images');
            $table->boolean('is_inline')->default(false);

            $table->timestamps();

            // Indexes
            $table->index(['email_id', 'filename']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
