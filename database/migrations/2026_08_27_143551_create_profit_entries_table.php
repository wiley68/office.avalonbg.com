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
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profit_type_id')->constrained('profit_types')->restrictOnDelete();
            $table->date('date');
            $table->string('document_number', 64)->nullable();
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'profit_type_id']);
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
