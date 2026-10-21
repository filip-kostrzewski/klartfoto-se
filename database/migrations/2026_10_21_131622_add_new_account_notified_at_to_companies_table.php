<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->timestamp('new_account_notified_at')->nullable();
        });

        DB::table('companies')
            ->whereNull('new_account_notified_at')
            ->whereExists(function (Builder $query): void {
                $query->select(DB::raw(1))
                    ->from('users')
                    ->whereColumn('users.company_id', 'companies.id')
                    ->where('users.role', 'owner')
                    ->whereNotNull('users.email_verified_at');
            })
            ->update(['new_account_notified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('new_account_notified_at');
        });
    }
};
