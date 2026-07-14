<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_imap_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('host', 255);
            $table->unsignedSmallInteger('port')->default(993);
            $table->string('encryption', 16)->default('ssl');
            $table->string('username', 255);
            $table->text('password');
            $table->string('default_folder', 255)->default('INBOX');
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_imap_accounts');
    }
};
