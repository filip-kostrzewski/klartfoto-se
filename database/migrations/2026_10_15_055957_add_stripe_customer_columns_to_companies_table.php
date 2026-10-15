<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $mysql = Schema::getConnection()->getDriverName() === 'mysql';

        Schema::table('companies', function (Blueprint $table) use ($mysql): void {
            $stripeId = $table->string('stripe_id')->nullable();

            if ($mysql) {
                $stripeId->collation('utf8mb4_bin');
            }

            $stripeId->index();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropIndex(['stripe_id']);
            $table->dropColumn(['stripe_id', 'pm_type', 'pm_last_four']);
        });
    }
};
