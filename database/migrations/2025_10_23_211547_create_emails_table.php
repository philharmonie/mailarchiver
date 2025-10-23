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
        Schema::create('emails', function (Blueprint $table) {
            $table->id();

            // Email identifiers
            $table->string('message_id')->unique()->index();
            $table->string('in_reply_to')->nullable()->index();
            $table->json('references')->nullable();

            // Sender information
            $table->string('from_address')->index();
            $table->string('from_name')->nullable();

            // Recipients
            $table->json('to_addresses');
            $table->json('cc_addresses')->nullable();
            $table->json('bcc_addresses')->nullable();

            // Content
            $table->string('subject')->index();
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();

            // Metadata
            $table->json('headers');
            $table->timestamp('received_at')->index();
            $table->unsignedBigInteger('size_bytes')->index();

            // GoBD compliance fields
            $table->string('hash', 64)->unique()->comment('SHA-256 hash for immutability');
            $table->boolean('is_verified')->default(true)->comment('Hash verification status');
            $table->longText('raw_email')->comment('Complete raw email for compliance');

            // Optional flags
            $table->boolean('has_attachments')->default(false)->index();
            $table->boolean('is_archived')->default(false)->index();

            $table->timestamps();

            // Indexes for common queries
            $table->index(['from_address', 'received_at']);
            $table->index(['subject', 'received_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};
