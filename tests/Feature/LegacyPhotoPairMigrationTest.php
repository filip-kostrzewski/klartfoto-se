<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LegacyPhotoPairMigrationTest extends TestCase
{
    public function test_legacy_pair_rows_and_photo_membership_are_bridged_safely(): void
    {
        $originalDefaultConnection = config('database.default');
        $baseConnection = config('database.connections.'.$originalDefaultConnection);

        if (($baseConnection['driver'] ?? null) !== 'mysql'
            || ($baseConnection['database'] ?? null) !== 'klartfoto_test') {
            $this->markTestSkipped('Run migration compatibility tests against the dedicated MySQL klartfoto_test database.');
        }

        $connectionName = 'legacy_pair_compatibility';
        $prefix = 'lpc'.Str::lower(Str::random(8)).'_';
        config([
            'database.connections.'.$connectionName => [...$baseConnection, 'prefix' => $prefix],
            'database.default' => $connectionName,
        ]);
        DB::purge($connectionName);
        DB::purge();

        try {
            $this->createLegacySchema();
            $this->seedLegacyRows();

            $placesAndPairsMigration = require base_path('database/migrations/2026_10_16_060003_create_assignment_places_and_photo_pairs_tables.php');
            $photoPairForeignKeyMigration = require base_path('database/migrations/2026_10_16_060004_add_photo_pair_to_assignment_photos_table.php');
            $placesAndPairsMigration->up();
            $photoPairForeignKeyMigration->up();

            $placeIds = DB::table('assignment_places')->pluck('id', 'name');
            $bathroomPlaceId = (int) $placeIds['Badrum'];
            $kitchenPlaceId = (int) $placeIds['Kök'];
            $otherPlaceId = (int) $placeIds['Övrigt'];

            $this->assertSame($bathroomPlaceId, (int) DB::table('assignment_photo_pairs')->where('id', 10)->value('assignment_place_id'));
            $this->assertSame('Badrum', DB::table('assignment_photo_pairs')->where('id', 10)->value('label'));
            $this->assertSame('Legacy public note', DB::table('assignment_photo_pairs')->where('id', 10)->value('customer_comment'));
            $this->assertSame(5, (int) DB::table('assignment_photo_pairs')->where('id', 10)->value('sort_order'));
            $this->assertSame($kitchenPlaceId, (int) DB::table('assignment_photo_pairs')->where('id', 11)->value('assignment_place_id'));

            $photoPairs = DB::table('assignment_photos')->pluck('assignment_photo_pair_id', 'id');
            $this->assertSame(10, (int) $photoPairs[101]);
            $this->assertSame(10, (int) $photoPairs[102]);
            $this->assertNotNull($photoPairs[103]);
            $this->assertSame($photoPairs[103], $photoPairs[104]);
            $this->assertSame($otherPlaceId, (int) DB::table('assignment_photo_pairs')->where('id', $photoPairs[103])->value('assignment_place_id'));

            $newPairId = DB::table('assignment_photo_pairs')->insertGetId([
                'assignment_place_id' => $bathroomPlaceId,
                'label' => 'Ny yta',
                'sort_order' => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $newPair = DB::table('assignment_photo_pairs')->where('id', $newPairId)->first();
            $this->assertNull($newPair->assignment_id);
            $this->assertSame(0, (int) $newPair->position);
            $this->assertNull($newPair->room_name);
            $this->assertNull($newPair->note);
        } finally {
            $this->dropCompatibilityTables($connectionName);
            DB::purge($connectionName);
            config(['database.default' => $originalDefaultConnection]);
            DB::purge();
        }
    }

    private function createLegacySchema(): void
    {
        Schema::create('assignments', function (Blueprint $table): void {
            $table->id();
        });

        Schema::create('assignment_photo_pairs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('room_name', 80)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('assignment_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignment_photo_pair_id')->nullable()
                ->constrained('assignment_photo_pairs')->nullOnDelete();
            $table->string('phase', 12);
            $table->unsignedSmallInteger('sort_order')->default(0);
        });
    }

    private function seedLegacyRows(): void
    {
        DB::table('assignments')->insert(['id' => 1]);
        DB::table('assignment_photo_pairs')->insert([
            [
                'id' => 10,
                'assignment_id' => 1,
                'position' => 5,
                'room_name' => 'Badrum',
                'note' => 'Legacy public note',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 11,
                'assignment_id' => 1,
                'position' => 8,
                'room_name' => 'Kök',
                'note' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        DB::table('assignment_photos')->insert([
            ['id' => 101, 'assignment_id' => 1, 'assignment_photo_pair_id' => 10, 'phase' => 'before', 'sort_order' => 1],
            ['id' => 102, 'assignment_id' => 1, 'assignment_photo_pair_id' => 10, 'phase' => 'after', 'sort_order' => 1],
            ['id' => 103, 'assignment_id' => 1, 'assignment_photo_pair_id' => null, 'phase' => 'before', 'sort_order' => 100],
            ['id' => 104, 'assignment_id' => 1, 'assignment_photo_pair_id' => null, 'phase' => 'after', 'sort_order' => 100],
        ]);
    }

    private function dropCompatibilityTables(string $connectionName): void
    {
        $schema = Schema::connection($connectionName);

        foreach (['assignment_photos', 'assignment_photo_pairs', 'assignment_places', 'assignments'] as $table) {
            $schema->dropIfExists($table);
        }
    }
}
