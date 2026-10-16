<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $placeForeignKeyName = $this->placeForeignKeyName();

        if (! Schema::hasTable('assignment_places')) {
            Schema::create('assignment_places', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['assignment_id', 'name'], 'assignment_places_assignment_name_unique');
                $table->index(['assignment_id', 'sort_order'], 'assignment_places_order_index');
            });
        }

        if (! Schema::hasTable('assignment_photo_pairs')) {
            Schema::create('assignment_photo_pairs', function (Blueprint $table) use ($placeForeignKeyName): void {
                $table->id();
                $table->foreignId('assignment_place_id')
                    ->constrained('assignment_places', indexName: $placeForeignKeyName)
                    ->cascadeOnDelete();
                $table->string('label', 120)->nullable();
                $table->text('customer_comment')->nullable();
                $table->text('internal_note')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['assignment_place_id', 'sort_order'], 'photo_pairs_place_order_unique');
            });

            return;
        }

        $addedPlaceId = ! Schema::hasColumn('assignment_photo_pairs', 'assignment_place_id');
        Schema::table('assignment_photo_pairs', function (Blueprint $table) use ($addedPlaceId): void {
            if ($addedPlaceId) {
                $table->unsignedBigInteger('assignment_place_id')->nullable()->after('id');
            }

            if (! Schema::hasColumn('assignment_photo_pairs', 'label')) {
                $table->string('label', 120)->nullable();
            }

            if (! Schema::hasColumn('assignment_photo_pairs', 'customer_comment')) {
                $table->text('customer_comment')->nullable();
            }

            if (! Schema::hasColumn('assignment_photo_pairs', 'internal_note')) {
                $table->text('internal_note')->nullable();
            }

            if (! Schema::hasColumn('assignment_photo_pairs', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(0);
            }
        });

        $this->bridgeLegacyPairRows();
        $this->relaxLegacyColumns();
        $this->ensureCanonicalConstraints($addedPlaceId, $placeForeignKeyName);
    }

    public function down(): void
    {
        // This migration may bridge a published legacy pair table; never drop its data on rollback.
    }

    private function bridgeLegacyPairRows(): void
    {
        $hasLegacyAssignment = Schema::hasColumn('assignment_photo_pairs', 'assignment_id');
        $hasLegacyRoomName = Schema::hasColumn('assignment_photo_pairs', 'room_name');
        $hasLegacyPosition = Schema::hasColumn('assignment_photo_pairs', 'position');
        $hasLegacyNote = Schema::hasColumn('assignment_photo_pairs', 'note');

        DB::table('assignment_photo_pairs')->orderBy('id')->chunkById(100, function ($pairs) use (
            $hasLegacyAssignment,
            $hasLegacyRoomName,
            $hasLegacyPosition,
            $hasLegacyNote,
        ): void {
            foreach ($pairs as $pair) {
                $assignmentId = $hasLegacyAssignment ? ($pair->assignment_id ?? null) : null;

                if ($assignmentId === null) {
                    $assignmentId = DB::table('assignment_photos')
                        ->where('assignment_photo_pair_id', $pair->id)
                        ->value('assignment_id');
                }

                if ($assignmentId === null) {
                    if ($pair->assignment_place_id === null) {
                        throw new RuntimeException('Cannot map a legacy photo pair to an assignment.');
                    }

                    continue;
                }

                $legacyName = $hasLegacyRoomName ? trim((string) ($pair->room_name ?? '')) : '';
                $placeName = $legacyName !== '' ? $legacyName : 'Övrigt';
                if ($pair->assignment_place_id !== null) {
                    $canonicalPlace = DB::table('assignment_places')
                        ->where('id', $pair->assignment_place_id)
                        ->where('assignment_id', $assignmentId)
                        ->first();

                    if ($canonicalPlace === null) {
                        throw new RuntimeException('A photo pair references a place outside its assignment.');
                    }

                    $placeId = (int) $canonicalPlace->id;
                } else {
                    $place = DB::table('assignment_places')
                        ->where('assignment_id', $assignmentId)
                        ->where('name', $placeName)
                        ->first();

                    if ($place === null) {
                        $placeId = DB::table('assignment_places')->insertGetId([
                            'assignment_id' => $assignmentId,
                            'name' => $placeName,
                            'sort_order' => (int) DB::table('assignment_places')
                                ->where('assignment_id', $assignmentId)
                                ->max('sort_order') + 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        $placeId = (int) $place->id;
                    }
                }

                $sortOrder = $pair->assignment_place_id !== null
                    ? max(0, (int) ($pair->sort_order ?? 0))
                    : ($hasLegacyPosition ? max(0, (int) $pair->position) : max(0, (int) ($pair->sort_order ?? 0)));
                $orderInUse = DB::table('assignment_photo_pairs')
                    ->where('assignment_place_id', $placeId)
                    ->where('sort_order', $sortOrder)
                    ->where('id', '!=', $pair->id)
                    ->exists();

                if ($orderInUse) {
                    $sortOrder = (int) DB::table('assignment_photo_pairs')
                        ->where('assignment_place_id', $placeId)
                        ->max('sort_order') + 1;
                }

                $label = $pair->label ?? ($legacyName !== '' ? $legacyName : null);
                $comment = $pair->customer_comment
                    ?? ($hasLegacyNote ? $this->filledText($pair->note ?? null) : null);

                DB::table('assignment_photo_pairs')->where('id', $pair->id)->update([
                    'assignment_place_id' => $pair->assignment_place_id ?? $placeId,
                    'label' => $label,
                    'customer_comment' => $comment,
                    'sort_order' => $sortOrder,
                    'updated_at' => now(),
                ]);
            }
        });
    }

    private function relaxLegacyColumns(): void
    {
        if (Schema::hasColumn('assignment_photo_pairs', 'assignment_id')) {
            Schema::table('assignment_photo_pairs', function (Blueprint $table): void {
                $table->unsignedBigInteger('assignment_id')->nullable()->change();
            });
        }

        if (Schema::hasColumn('assignment_photo_pairs', 'position')) {
            Schema::table('assignment_photo_pairs', function (Blueprint $table): void {
                $table->unsignedSmallInteger('position')->default(0)->change();
            });
        }
    }

    private function ensureCanonicalConstraints(bool $addedPlaceId, string $placeForeignKeyName): void
    {
        if ($addedPlaceId || ! Schema::hasForeignKey('assignment_photo_pairs', $placeForeignKeyName)) {
            Schema::table('assignment_photo_pairs', function (Blueprint $table): void {
                $table->foreign('assignment_place_id', $this->placeForeignKeyName())
                    ->references('id')
                    ->on('assignment_places')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasIndex('assignment_photo_pairs', ['assignment_place_id', 'sort_order'], 'unique')) {
            Schema::table('assignment_photo_pairs', function (Blueprint $table): void {
                $table->unique(['assignment_place_id', 'sort_order'], 'photo_pairs_place_order_unique');
            });
        }
    }

    private function placeForeignKeyName(): string
    {
        return 'pp_place_fk_'.substr(hash('sha256', Schema::getConnection()->getTablePrefix()), 0, 12);
    }

    private function filledText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
};
