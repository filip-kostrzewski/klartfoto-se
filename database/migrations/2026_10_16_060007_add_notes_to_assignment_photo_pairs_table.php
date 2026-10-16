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
        if (! Schema::hasColumn('assignment_photo_pairs', 'customer_comment')) {
            Schema::table('assignment_photo_pairs', function (Blueprint $table): void {
                $table->text('customer_comment')->nullable();
            });
        }

        if (! Schema::hasColumn('assignment_photo_pairs', 'internal_note')) {
            Schema::table('assignment_photo_pairs', function (Blueprint $table): void {
                $table->text('internal_note')->nullable();
            });
        }

        DB::table('assignment_report_versions')->orderBy('id')->chunkById(100, function ($versions): void {
            foreach ($versions as $version) {
                $snapshot = json_decode($version->snapshot, true, flags: JSON_THROW_ON_ERROR);
                $places = [];

                foreach ($snapshot['places'] ?? [] as $place) {
                    $pairs = [];

                    foreach ($place['pairs'] ?? [] as $pair) {
                        $pairs[] = [
                            'id' => isset($pair['id']) ? (int) $pair['id'] : null,
                            'label' => $pair['label'] ?? null,
                            'customer_comment' => $pair['customer_comment'] ?? null,
                            'sort_order' => (int) ($pair['sort_order'] ?? 0),
                            'before' => isset($pair['before']) ? (int) $pair['before'] : null,
                            'after' => isset($pair['after']) ? (int) $pair['after'] : null,
                        ];
                    }

                    $places[] = [
                        'id' => isset($place['id']) ? (int) $place['id'] : null,
                        'name' => (string) ($place['name'] ?? 'Övrigt'),
                        'sort_order' => (int) ($place['sort_order'] ?? 0),
                        'pairs' => $pairs,
                    ];
                }

                $assets = [];
                foreach ($snapshot['assets'] ?? [] as $asset) {
                    $assets[] = [
                        'type' => (string) ($asset['type'] ?? 'photo'),
                        'source_photo_id' => isset($asset['source_photo_id']) ? (int) $asset['source_photo_id'] : null,
                        'phase' => $asset['phase'] ?? null,
                        'place_name' => $asset['place_name'] ?? null,
                        'place_sort_order' => isset($asset['place_sort_order']) ? (int) $asset['place_sort_order'] : null,
                        'pair_sort_order' => isset($asset['pair_sort_order']) ? (int) $asset['pair_sort_order'] : null,
                        'path' => (string) ($asset['path'] ?? ''),
                    ];
                }

                $normalizedSnapshot = [
                    'schema_version' => (int) ($snapshot['schema_version'] ?? 1),
                    'company_name' => (string) ($snapshot['company_name'] ?? ''),
                    'company_logo_path' => $snapshot['company_logo_path'] ?? null,
                    'customer_name' => $snapshot['customer_name'] ?? null,
                    'address' => $snapshot['address'] ?? null,
                    'performed_on' => $snapshot['performed_on'] ?? null,
                    'places' => $places,
                    'assets' => $assets,
                ];

                foreach ([
                    'performed_time',
                    'performed_end_time',
                    'performed_time_label',
                    'company_phone',
                    'company_organization_number',
                    'report_created_at',
                ] as $key) {
                    if (array_key_exists($key, $snapshot)) {
                        $normalizedSnapshot[$key] = $snapshot[$key];
                    }
                }
                $snapshotJson = json_encode(
                    $normalizedSnapshot,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                );

                DB::table('assignment_report_versions')->where('id', $version->id)->update([
                    'snapshot' => $snapshotJson,
                    'snapshot_hash' => hash('sha256', $snapshotJson),
                    'updated_at' => Carbon::now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // The normalized pair schema keeps these columns regardless of migration history.
    }
};
