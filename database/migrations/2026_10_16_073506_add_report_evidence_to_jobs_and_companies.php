<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        if (! Schema::hasColumn('assignments', 'performed_time')) {
            Schema::table('assignments', function (Blueprint $table): void {
                $table->time('performed_time')->nullable();
            });
        }

        if (! Schema::hasTable('assignment_photo_pairs')) {
            Schema::create('assignment_photo_pairs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('assignment_id')->nullable()->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('position')->default(0);
                $table->string('room_name', 80)->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('assignment_photos', 'assignment_photo_pair_id')) {
            Schema::table('assignment_photos', function (Blueprint $table): void {
                $table->foreignId('assignment_photo_pair_id')
                    ->nullable()
                    ->constrained('assignment_photo_pairs')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // The migration may have been superseded by normalized places and report snapshots.
    }
};
