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
        Schema::create('spam_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('form');
            $table->json('payload');
            $table->string('reason');
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spam_submissions');
    }
};
