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
        Schema::table('assignment_photos', function (Blueprint $table): void {
            $table->uuid('client_upload_id')->nullable();
            $table->char('upload_sha256', 64)->nullable();
            $table->unique(['assignment_id', 'client_upload_id'], 'as_photo_assignment_upload_unique');
            $table->unique(['assignment_photo_pair_id', 'phase'], 'as_photo_pair_phase_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assignment_photos', function (Blueprint $table): void {
            $table->dropUnique('as_photo_pair_phase_unique');
            $table->dropUnique('as_photo_assignment_upload_unique');
            $table->dropColumn(['client_upload_id', 'upload_sha256']);
        });
    }
};
