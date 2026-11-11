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
        Schema::create('profit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('profit_type_id');
            $table->date('date');
            $table->string('document_number', 64)->nullable();
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->index(['group_id', 'date']);
            $table->index(['group_id', 'profit_type_id']);
            $table->foreign(['profit_type_id', 'group_id'])->references(['id', 'group_id'])->on('profit_types')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profit_entries');
    }
};
