<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table): void {
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->index(['company_id', 'assigned_to_user_id', 'status'], 'assignments_company_assigned_status_idx');
            $table->index(['company_id', 'reviewer_user_id', 'status'], 'assignments_company_reviewer_status_idx');
        });

        Schema::table('assignment_photos', function (Blueprint $table): void {
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('assignment_report_versions', function (Blueprint $table): void {
            $table->foreignId('first_sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        DB::table('assignments')->update(['assigned_to_user_id' => DB::raw('user_id')]);

        DB::table('assignment_report_versions')->orderBy('id')->chunkById(100, function ($versions): void {
            foreach ($versions as $version) {
                $firstSentAttempt = DB::table('assignment_email_attempts')
                    ->where('assignment_report_version_id', $version->id)
                    ->where('status', 'sent')
                    ->orderBy('id')
                    ->first(['user_id', 'sent_at']);

                DB::table('assignment_report_versions')->where('id', $version->id)->update([
                    'first_sent_at' => $firstSentAttempt?->sent_at,
                    'first_sent_by_user_id' => $firstSentAttempt?->user_id,
                    'status' => $firstSentAttempt === null ? 'ready' : 'sent',
                ]);
            }
        });

        DB::table('assignments')->orderBy('id')->chunkById(100, function ($assignments): void {
            foreach ($assignments as $assignment) {
                $firstSentAttempt = DB::table('assignment_email_attempts')
                    ->where('assignment_id', $assignment->id)
                    ->where('status', 'sent')
                    ->orderBy('id')
                    ->first(['sent_at']);
                $hasPhotos = DB::table('assignment_photos')->where('assignment_id', $assignment->id)->exists();
                $latestVersion = DB::table('assignment_report_versions')
                    ->where('assignment_id', $assignment->id)
                    ->orderByDesc('version_number')
                    ->first(['id', 'status']);
                $latestVersionWasSent = $latestVersion !== null
                    && DB::table('assignment_email_attempts')
                        ->where('assignment_report_version_id', $latestVersion->id)
                        ->where('status', 'sent')
                        ->exists();
                $status = $assignment->status === 'draft'
                    ? 'draft'
                    : ($latestVersionWasSent ? 'sent' : ($hasPhotos ? 'ready' : 'draft'));

                DB::table('assignments')->where('id', $assignment->id)->update([
                    'report_shared_at' => $firstSentAttempt?->sent_at,
                    'status' => $status,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('assignment_report_versions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('first_sent_by_user_id');
        });

        Schema::table('assignment_photos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('uploaded_by_user_id');
        });

        Schema::table('assignments', function (Blueprint $table): void {
            $table->dropIndex('assignments_company_assigned_status_idx');
            $table->dropIndex('assignments_company_reviewer_status_idx');
            $table->dropConstrainedForeignId('assigned_to_user_id');
            $table->dropConstrainedForeignId('reviewer_user_id');
            $table->dropConstrainedForeignId('reviewed_by_user_id');
            $table->dropColumn('reviewed_at');
        });
    }
};
