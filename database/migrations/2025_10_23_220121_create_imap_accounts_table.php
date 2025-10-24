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
        Schema::create('imap_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Friendly name for the account');
            $table->string('host');
            $table->integer('port')->default(993);
            $table->string('encryption')->default('ssl');
            $table->boolean('validate_cert')->default(true);
            $table->string('username');
            $table->text('password')->comment('Encrypted password');
            $table->string('folder')->default('INBOX');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_fetch_at')->nullable();
            $table->unsignedInteger('total_emails')->default(0);
            $table->unsignedBigInteger('total_size_bytes')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imap_accounts');
    }
};
