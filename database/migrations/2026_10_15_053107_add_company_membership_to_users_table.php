<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member')->after('company_id');
        });

        $timestamp = now();

        foreach (DB::table('users')->orderBy('id')->get() as $user) {
            $companyId = DB::table('companies')->insertGetId([
                'name' => $user->name,
                'plan' => 'start',
                'trial_ends_at' => $user->trial_ends_at,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            DB::table('users')->where('id', $user->id)->update([
                'company_id' => $companyId,
                'role' => 'owner',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn('role');
        });
    }
};
