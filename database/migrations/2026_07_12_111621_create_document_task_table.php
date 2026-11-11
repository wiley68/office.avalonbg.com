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
        Schema::create('document_task', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('document_id');
            $table->foreignId('task_id');
            $table->timestamps();

            $table->unique(['document_id', 'task_id']);
            $table->foreign(['document_id', 'group_id'])->references(['id', 'group_id'])->on('documents')->cascadeOnDelete();
            $table->foreign(['task_id', 'group_id'])->references(['id', 'group_id'])->on('tasks')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_task');
    }
};
