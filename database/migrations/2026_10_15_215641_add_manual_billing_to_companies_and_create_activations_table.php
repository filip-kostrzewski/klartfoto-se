<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->timestamp('paid_until')->nullable()->index();
            $table->string('requested_plan')->nullable();
            $table->timestamp('requested_at')->nullable()->index();
            $table->text('billing_details')->nullable();
        });

        Schema::create('manual_billing_activations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('plan');
            $table->timestamp('starts_at');
            $table->timestamp('paid_until');
            $table->string('invoice_reference', 120)->unique();
            $table->string('activated_by', 120);
            $table->timestamp('activated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_billing_activations');

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropIndex(['paid_until']);
            $table->dropIndex(['requested_at']);
            $table->dropColumn(['paid_until', 'requested_plan', 'requested_at', 'billing_details']);
        });
    }
};
