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
        Schema::create('assignment_report_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignment_report_version_id')
                ->constrained('assignment_report_versions', indexName: 'arr_version_fk')
                ->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('response_key', 36);
            $table->unsignedSmallInteger('place_index')->nullable();
            $table->unsignedSmallInteger('pair_index')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['assignment_report_version_id', 'response_key'], 'arr_version_key_unique');
            $table->index(['assignment_id', 'created_at'], 'arr_assignment_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_report_responses');
    }
};
