<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('phone', 40)->nullable();
            $table->string('organization_number', 32)->nullable();
        });

        Schema::table('assignments', function (Blueprint $table): void {
            $table->time('performed_time')->nullable();
        });

        Schema::create('assignment_photo_pairs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('room_name', 80)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::table('assignment_photos', function (Blueprint $table): void {
            $table->foreignId('assignment_photo_pair_id')
                ->nullable()
                ->constrained('assignment_photo_pairs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assignment_photos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assignment_photo_pair_id');
        });

        Schema::dropIfExists('assignment_photo_pairs');

        Schema::table('assignments', function (Blueprint $table): void {
            $table->dropColumn('performed_time');
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['phone', 'organization_number']);
        });
    }
};
