<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('email', 254)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'name']);
            $table->index(['company_id', 'email']);
        });

        Schema::create('customer_properties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('label', 120)->nullable();
            $table->string('address', 255);
            $table->timestamps();

            $table->index(['company_id', 'customer_id']);
            $table->index(['customer_id', 'address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_properties');
        Schema::dropIfExists('customers');
    }
};
