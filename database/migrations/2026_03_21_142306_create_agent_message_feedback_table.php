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
        Schema::create('agent_message_feedback', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 36);
            $table->foreignId('user_id');
            $table->string('feedback', 10);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->unique(['message_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_message_feedback');
    }
};
