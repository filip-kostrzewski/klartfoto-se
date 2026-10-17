<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('feedback_pilot');
            $table->string('requested_interval', 16)->nullable()->after('requested_plan');
            $table->string('billing_interval', 16)->nullable()->after('paid_until');
            $table->timestamp('trial_ending_notified_at')->nullable();
            $table->timestamp('trial_ended_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->boolean('feedback_pilot')->default(false);
            $table->dropColumn(['requested_interval', 'billing_interval', 'trial_ending_notified_at', 'trial_ended_notified_at']);
        });
    }
};
