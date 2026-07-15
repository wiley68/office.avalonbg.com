<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_email_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_email_link_id')->constrained()->cascadeOnDelete();
            $table->string('imap_part', 64);
            $table->foreignId('document_id')->constrained();
            $table->timestamps();

            $table->unique(['project_email_link_id', 'imap_part']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_email_attachments');
    }
};
