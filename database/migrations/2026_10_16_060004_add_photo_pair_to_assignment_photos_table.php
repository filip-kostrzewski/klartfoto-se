<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('assignment_photos', 'assignment_photo_pair_id')) {
            Schema::table('assignment_photos', function (Blueprint $table): void {
                $table->foreignId('assignment_photo_pair_id')
                    ->nullable()
                    ->constrained('assignment_photo_pairs')
                    ->nullOnDelete();
            });
        }

        DB::table('assignments')->orderBy('id')->chunkById(100, function ($assignments): void {
            foreach ($assignments as $assignment) {
                $beforePhotos = DB::table('assignment_photos')
                    ->where('assignment_id', $assignment->id)
                    ->whereNull('assignment_photo_pair_id')
                    ->where('phase', 'before')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get(['id']);
                $afterPhotos = DB::table('assignment_photos')
                    ->where('assignment_id', $assignment->id)
                    ->whereNull('assignment_photo_pair_id')
                    ->where('phase', 'after')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get(['id']);

                if ($beforePhotos->isEmpty() && $afterPhotos->isEmpty()) {
                    continue;
                }

                $placeId = DB::table('assignment_places')
                    ->where('assignment_id', $assignment->id)
                    ->where('name', 'Övrigt')
                    ->value('id');

                if ($placeId === null) {
                    $placeId = DB::table('assignment_places')->insertGetId([
                        'assignment_id' => $assignment->id,
                        'name' => 'Övrigt',
                        'sort_order' => (int) DB::table('assignment_places')
                            ->where('assignment_id', $assignment->id)
                            ->max('sort_order') + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $pairSortOrder = (int) DB::table('assignment_photo_pairs')
                    ->where('assignment_place_id', $placeId)
                    ->max('sort_order');

                for ($index = 0; $index < max($beforePhotos->count(), $afterPhotos->count()); $index++) {
                    $pairSortOrder++;
                    $pairId = DB::table('assignment_photo_pairs')->insertGetId([
                        'assignment_place_id' => $placeId,
                        'label' => null,
                        'customer_comment' => null,
                        'internal_note' => null,
                        'sort_order' => $pairSortOrder,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    foreach ([$beforePhotos->get($index), $afterPhotos->get($index)] as $photo) {
                        if ($photo !== null) {
                            DB::table('assignment_photos')->where('id', $photo->id)->whereNull('assignment_photo_pair_id')->update([
                                'assignment_photo_pair_id' => $pairId,
                            ]);
                        }
                    }
                }
            }
        });
    }

    public function down(): void
    {
        // Published legacy memberships must remain intact if this migration is rolled back.
    }
};
