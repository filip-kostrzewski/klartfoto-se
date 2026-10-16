<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_report_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version_number');
            $table->string('token', 48)->unique();
            $table->string('status', 24)->default('ready');
            $table->json('snapshot');
            $table->char('snapshot_hash', 64);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('first_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['assignment_id', 'version_number']);
            $table->index(['assignment_id', 'status']);
        });

        Schema::create('assignment_report_version_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assignment_report_version_id')
                ->constrained('assignment_report_versions', indexName: 'arva_version_fk')
                ->cascadeOnDelete();
            $table->string('type', 12);
            $table->unsignedBigInteger('source_photo_id')->nullable();
            $table->string('phase', 12)->nullable();
            $table->string('place_name', 120)->nullable();
            $table->unsignedSmallInteger('place_sort_order')->nullable();
            $table->unsignedSmallInteger('pair_sort_order')->nullable();
            $table->string('path');
            $table->timestamps();

            $table->index(['assignment_report_version_id', 'type'], 'arva_version_type_index');
            $table->index(['path', 'type'], 'arva_path_type_index');
            $table->unique(['assignment_report_version_id', 'source_photo_id', 'type'], 'arva_version_source_type_unique');
        });

        Schema::table('assignments', function (Blueprint $table): void {
            $table->string('status', 24)->default('draft')->after('performed_on');
            $table->index(['company_id', 'status', 'performed_on']);
        });

        DB::table('assignments')->orderBy('id')->chunkById(100, function ($assignments): void {
            foreach ($assignments as $assignment) {
                $company = $assignment->company_id === null
                    ? null
                    : DB::table('companies')->where('id', $assignment->company_id)->first();
                $trialActive = $company !== null
                    && $company->trial_ends_at !== null
                    && Carbon::parse($company->trial_ends_at)->isFuture();
                $manualPlanActive = $company !== null
                    && $company->paid_until !== null
                    && Carbon::parse($company->paid_until)->isFuture();
                $stripePlanActive = $company !== null && DB::table('subscriptions')
                    ->where('company_id', $company->id)
                    ->where('type', 'default')
                    ->where('stripe_status', 'active')
                    ->where(function ($query): void {
                        $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
                    })
                    ->exists();
                $allowsTeamFeatures = $trialActive
                    || ($company?->plan === 'team' && ($manualPlanActive || $stripePlanActive));
                $logoPath = $allowsTeamFeatures ? $company?->logo_path : null;
                $performedTime = filled($assignment->performed_time ?? null)
                    ? substr((string) $assignment->performed_time, 0, 5)
                    : null;
                $performedEndTime = filled($assignment->performed_end_time ?? null)
                    ? substr((string) $assignment->performed_end_time, 0, 5)
                    : null;
                $performedTimeLabel = $performedTime !== null && $performedEndTime !== null
                    ? $performedTime.'–'.$performedEndTime
                    : ($performedTime ?? $performedEndTime);
                $places = [];
                $photoRows = [];
                $placeRows = DB::table('assignment_places')
                    ->where('assignment_id', $assignment->id)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get();

                foreach ($placeRows as $place) {
                    $pairs = [];
                    $pairRows = DB::table('assignment_photo_pairs')
                        ->where('assignment_place_id', $place->id)
                        ->orderBy('sort_order')
                        ->orderBy('id')
                        ->get();

                    foreach ($pairRows as $pair) {
                        $photos = DB::table('assignment_photos')
                            ->where('assignment_photo_pair_id', $pair->id)
                            ->orderBy('id')
                            ->get();
                        $before = $photos->firstWhere('phase', 'before');
                        $after = $photos->firstWhere('phase', 'after');

                        $pairs[] = [
                            'id' => (int) $pair->id,
                            'label' => $pair->label,
                            'customer_comment' => $pair->customer_comment ?? null,
                            'sort_order' => (int) $pair->sort_order,
                            'before' => $before === null ? null : (int) $before->id,
                            'after' => $after === null ? null : (int) $after->id,
                        ];

                        foreach ($photos as $photo) {
                            $photoRows[] = [
                                'type' => 'photo',
                                'source_photo_id' => (int) $photo->id,
                                'phase' => $photo->phase,
                                'place_name' => $place->name,
                                'place_sort_order' => (int) $place->sort_order,
                                'pair_sort_order' => (int) $pair->sort_order,
                                'path' => $photo->path,
                            ];
                        }
                    }

                    $places[] = [
                        'id' => (int) $place->id,
                        'name' => $place->name,
                        'sort_order' => (int) $place->sort_order,
                        'pairs' => $pairs,
                    ];
                }

                $snapshot = [
                    'schema_version' => 1,
                    'company_name' => $company?->name ?? '',
                    'company_logo_path' => $logoPath,
                    'customer_name' => $assignment->customer_name,
                    'address' => $assignment->address,
                    'performed_on' => $assignment->performed_on,
                    'performed_time' => $performedTime,
                    'performed_end_time' => $performedEndTime,
                    'performed_time_label' => $performedTimeLabel,
                    'company_phone' => filled($company?->phone) ? trim((string) $company->phone) : null,
                    'company_organization_number' => filled($company?->organization_number)
                        ? trim((string) $company->organization_number)
                        : null,
                    'report_created_at' => $assignment->created_at === null
                        ? null
                        : Carbon::parse($assignment->created_at)->toDateTimeString(),
                    'places' => $places,
                    'assets' => $photoRows,
                ];
                if (filled($logoPath)) {
                    $photoRows[] = [
                        'type' => 'logo',
                        'source_photo_id' => null,
                        'phase' => null,
                        'place_name' => null,
                        'place_sort_order' => null,
                        'pair_sort_order' => null,
                        'path' => $logoPath,
                    ];
                }

                $snapshot['assets'] = $photoRows;
                $snapshotJson = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $createdAt = $assignment->created_at ?? now();
                $sentAt = $assignment->report_shared_at ?? $assignment->link_copied_at;
                $versionId = DB::table('assignment_report_versions')->insertGetId([
                    'assignment_id' => $assignment->id,
                    'version_number' => 1,
                    'token' => $assignment->public_token,
                    'status' => $sentAt === null ? 'ready' : 'sent',
                    'snapshot' => $snapshotJson,
                    'snapshot_hash' => hash('sha256', $snapshotJson),
                    'created_by_user_id' => $assignment->user_id,
                    'first_sent_at' => $sentAt,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                foreach ($photoRows as $asset) {
                    DB::table('assignment_report_version_assets')->insert([
                        'assignment_report_version_id' => $versionId,
                        ...$asset,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                }

                $status = $sentAt !== null
                    ? 'sent'
                    : (DB::table('assignment_photos')->where('assignment_id', $assignment->id)->exists() ? 'ready' : 'draft');

                DB::table('assignments')->where('id', $assignment->id)->update(['status' => $status]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table): void {
            $table->dropIndex('assignments_company_id_status_performed_on_index');
            $table->dropColumn('status');
        });

        Schema::dropIfExists('assignment_report_version_assets');
        Schema::dropIfExists('assignment_report_versions');
    }
};
