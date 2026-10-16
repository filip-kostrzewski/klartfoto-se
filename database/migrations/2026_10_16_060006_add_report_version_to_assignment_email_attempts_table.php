<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignment_email_attempts', function (Blueprint $table): void {
            $table->foreignId('assignment_report_version_id')->nullable()->after('assignment_id')
                ->constrained('assignment_report_versions', indexName: 'aea_report_version_fk')
                ->nullOnDelete();
        });

        DB::table('assignment_email_attempts')->orderBy('id')->chunkById(100, function ($attempts): void {
            foreach ($attempts as $attempt) {
                $versionId = DB::table('assignment_report_versions')
                    ->where('assignment_id', $attempt->assignment_id)
                    ->where('version_number', 1)
                    ->value('id');

                if ($versionId !== null) {
                    DB::table('assignment_email_attempts')->where('id', $attempt->id)->update([
                        'assignment_report_version_id' => $versionId,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('assignment_email_attempts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assignment_report_version_id');
        });
    }
};
