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
            $table->foreignId('group_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('project_email_link_id');
            $table->string('imap_part', 64);
            $table->foreignId('document_id');
            $table->timestamps();

            $table->unique(['project_email_link_id', 'imap_part']);
            $table->foreign(['project_email_link_id', 'group_id'], 'email_attachment_link_group_foreign')
                ->references(['id', 'group_id'])->on('project_email_links')->cascadeOnDelete();
            $table->foreign(['document_id', 'group_id'])->references(['id', 'group_id'])->on('documents')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_email_attachments');
    }
};
