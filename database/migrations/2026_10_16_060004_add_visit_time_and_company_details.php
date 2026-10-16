<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('assignments', 'performed_time')) {
            Schema::table('assignments', function (Blueprint $table): void {
                $table->time('performed_time')->nullable();
            });
        }

        if (! Schema::hasColumn('companies', 'phone')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->string('phone', 40)->nullable();
            });
        }

        if (! Schema::hasColumn('companies', 'organization_number')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->string('organization_number', 32)->nullable();
            });
        }
    }

    public function down(): void
    {
        // These fields may predate this compatibility migration on deployed databases.
    }
};
