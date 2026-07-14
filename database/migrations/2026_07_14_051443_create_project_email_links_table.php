<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_email_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('folder', 255);
            $table->unsignedBigInteger('imap_uid');
            $table->unsignedBigInteger('uidvalidity');
            $table->string('subject', 500)->nullable();
            $table->string('from_name', 255)->nullable();
            $table->string('from_address', 255)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'folder', 'imap_uid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_email_links');
    }
};
