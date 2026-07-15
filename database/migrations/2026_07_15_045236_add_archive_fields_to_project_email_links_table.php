<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_email_links', function (Blueprint $table) {
            $table->longText('body_text')->nullable()->after('sent_at');
            $table->longText('body_html')->nullable()->after('body_text');
            $table->string('conversation_key', 255)->nullable()->after('body_html');
            $table->timestamp('archived_at')->nullable()->after('last_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('project_email_links', function (Blueprint $table) {
            $table->dropColumn([
                'body_text',
                'body_html',
                'conversation_key',
                'archived_at',
            ]);
        });
    }
};
