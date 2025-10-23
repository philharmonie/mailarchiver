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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Polymorphic relation to auditable model (Email, Attachment, etc.)
            $table->morphs('auditable');

            // User who performed the action (nullable for system actions)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Action details
            $table->string('action')->index()->comment('viewed, exported, modified, deleted, etc.');
            $table->text('description')->nullable();

            // Request information
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            // Additional metadata (JSON)
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['auditable_type', 'auditable_id', 'action']);
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
