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
        Schema::table('agent_conversations', function (Blueprint $table) {
            $table->string('context', 32)->default('orchestrator')->after('user_id');
            $table->index(
                ['user_id', 'context', 'updated_at'],
                'agent_conversations_user_context_updated_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_conversations', function (Blueprint $table) {
            $table->dropIndex('agent_conversations_user_context_updated_idx');
            $table->dropColumn('context');
        });
    }
};
