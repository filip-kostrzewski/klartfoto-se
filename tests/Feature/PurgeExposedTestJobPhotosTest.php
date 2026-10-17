<?php

namespace Tests\Feature;

use App\Actions\PurgeExposedTestJobPhotos;
use App\Models\Assignment;
use App\Models\AssignmentPhoto;
use App\Models\AssignmentReportVersion;
use App\Models\AssignmentReportVersionAsset;
use App\Models\User;
use App\PhotoPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurgeExposedTestJobPhotosTest extends TestCase
{
    use RefreshDatabase;

    private const LEAKED_TOKEN = 'Ua07wvqfWlrI7QzEU1u2JsvBUUuIsQAwU1HPgoTor0bZGgNw';

    private const OLDER_TOKEN = 'OlderVersionToken0000000000000000000000000001';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('s3');
    }

    public function test_only_the_matching_test_job_loses_share_links_and_stored_photos(): void
    {
        [$assignment, $assetId] = $this->exposedJob();
        $control = $this->controlJob();
        $logoPath = 'companies/'.$assignment->company_id.'/logo.svg';
        Storage::disk('local')->put($logoPath, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        Storage::disk('public')->put('assignments/21/live.jpg', 'public-copy');
        Storage::disk('s3')->put('assignments/21/thumbs/live.jpg', 'thumb');
        Storage::disk('local')->put('assignments/21/thumbs/live.jpg', 'thumb');

        $version = AssignmentReportVersion::query()->where('token', self::LEAKED_TOKEN)->firstOrFail();
        $version->assets()->create([
            'type' => AssignmentReportVersionAsset::TYPE_LOGO,
            'path' => $logoPath,
        ]);
        Cache::store('file')->put('assignment-report-pdf:'.$version->id.':'.$version->snapshot_hash, 'cached-pdf', 60);

        Schema::table('assignment_photos', function ($table): void {
            $table->softDeletes();
        });
        $softDeletedId = DB::table('assignment_photos')->insertGetId([
            'assignment_id' => PurgeExposedTestJobPhotos::ASSIGNMENT_ID,
            'phase' => PhotoPhase::After->value,
            'path' => 'assignments/21/removed.jpg',
            'sort_order' => 2,
            'deleted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Storage::disk('local')->put('assignments/21/removed.jpg', 'soft-deleted-bytes');

        app(PurgeExposedTestJobPhotos::class)->handle();

        $this->get('/r/'.self::LEAKED_TOKEN)->assertNotFound();
        $this->get('/r/'.self::OLDER_TOKEN)->assertNotFound();
        $this->get('/r/'.self::LEAKED_TOKEN.'/version-foton/'.$assetId)->assertNotFound();
        $this->get('/r/'.self::LEAKED_TOKEN.'/foton/'.$assignment->photos()->firstOrFail()->id)->assertNotFound();

        $this->assertDatabaseMissing('assignment_report_versions', ['token' => self::LEAKED_TOKEN]);
        $this->assertDatabaseMissing('assignment_report_versions', ['token' => self::OLDER_TOKEN]);
        $this->assertDatabaseMissing('assignment_photos', ['id' => $softDeletedId]);
        $this->assertNotSame(self::LEAKED_TOKEN, $assignment->fresh()->public_token);
        $this->assertDatabaseHas('assignment_photos', [
            'assignment_id' => PurgeExposedTestJobPhotos::ASSIGNMENT_ID,
            'path' => 'assignments/21/live.jpg',
        ]);

        Storage::disk('local')->assertMissing('assignments/21/live.jpg');
        Storage::disk('local')->assertMissing('assignments/21/removed.jpg');
        Storage::disk('local')->assertMissing('assignments/21/thumbs/live.jpg');
        Storage::disk('public')->assertMissing('assignments/21/live.jpg');
        Storage::disk('s3')->assertMissing('assignments/21/thumbs/live.jpg');
        Storage::disk('local')->assertExists($logoPath);
        Storage::disk('local')->assertExists('assignments/'.$control->id.'/keep.jpg');
        $this->assertSame($control->public_token, $control->fresh()->public_token);
        $this->assertDatabaseHas('assignment_report_versions', ['token' => $control->public_token]);
        $this->assertNull(Cache::store('file')->get('assignment-report-pdf:'.$version->id.':'.$version->snapshot_hash));

        $stableToken = $assignment->fresh()->public_token;
        app(PurgeExposedTestJobPhotos::class)->handle();
        $this->assertSame($stableToken, $assignment->fresh()->public_token);
        $this->get('/r/'.self::LEAKED_TOKEN)->assertNotFound();
        Storage::disk('local')->assertExists('assignments/'.$control->id.'/keep.jpg');
    }

    public function test_a_different_company_using_assignment_id_21_is_not_purged(): void
    {
        $owner = User::factory()->create(['email' => 'someone-else@example.test']);
        $owner->company->forceFill(['name' => PurgeExposedTestJobPhotos::COMPANY_NAME])->save();
        $assignment = $this->assignmentFor($owner, self::LEAKED_TOKEN);
        Storage::disk('local')->put('assignments/21/live.jpg', 'keep');
        AssignmentReportVersion::factory()->create([
            'assignment_id' => $assignment->id,
            'token' => self::LEAKED_TOKEN,
            'snapshot' => ['assets' => []],
            'snapshot_hash' => hash('sha256', '[]'),
        ]);

        app(PurgeExposedTestJobPhotos::class)->handle();

        $this->assertSame(self::LEAKED_TOKEN, $assignment->fresh()->public_token);
        Storage::disk('local')->assertExists('assignments/21/live.jpg');
        $this->assertDatabaseHas('assignment_report_versions', ['token' => self::LEAKED_TOKEN]);
    }

    /**
     * @return array{0: Assignment, 1: int}
     */
    private function exposedJob(): array
    {
        $owner = User::factory()->create(['email' => PurgeExposedTestJobPhotos::OWNER_EMAIL]);
        $owner->company->forceFill(['name' => PurgeExposedTestJobPhotos::COMPANY_NAME])->save();
        $assignment = $this->assignmentFor($owner, self::LEAKED_TOKEN);
        $assignment->photos()->create([
            'phase' => PhotoPhase::Before,
            'path' => 'assignments/21/live.jpg',
            'sort_order' => 1,
        ]);
        Storage::disk('local')->put('assignments/21/live.jpg', 'private-bytes');

        $snapshot = [
            'company_name' => PurgeExposedTestJobPhotos::COMPANY_NAME,
            'customer_name' => PurgeExposedTestJobPhotos::CUSTOMER_NAME,
            'address' => 'Testgatan 2, Märsta',
            'performed_on' => '2026-10-17',
            'places' => [],
            'assets' => [[
                'type' => AssignmentReportVersionAsset::TYPE_PHOTO,
                'path' => 'assignments/21/from-snapshot.jpg',
            ]],
        ];
        Storage::disk('local')->put('assignments/21/from-snapshot.jpg', 'snapshot-bytes');
        $version = AssignmentReportVersion::factory()->create([
            'assignment_id' => $assignment->id,
            'version_number' => 3,
            'token' => self::LEAKED_TOKEN,
            'snapshot' => $snapshot,
            'snapshot_hash' => hash('sha256', json_encode($snapshot)),
        ]);
        $asset = $version->assets()->create([
            'type' => AssignmentReportVersionAsset::TYPE_PHOTO,
            'source_photo_id' => $assignment->photos()->firstOrFail()->id,
            'phase' => PhotoPhase::Before->value,
            'path' => 'assignments/21/live.jpg',
        ]);
        AssignmentReportVersion::factory()->create([
            'assignment_id' => $assignment->id,
            'version_number' => 1,
            'token' => self::OLDER_TOKEN,
            'snapshot' => ['assets' => []],
            'snapshot_hash' => hash('sha256', '[]'),
        ]);

        return [$assignment, $asset->id];
    }

    private function controlJob(): Assignment
    {
        $owner = User::factory()->create();
        $assignment = Assignment::factory()->create([
            'user_id' => $owner->id,
            'company_id' => $owner->company_id,
            'customer_name' => 'Annan kund',
            'address' => 'Annan väg 1',
            'performed_on' => '2026-10-01',
            'public_token' => Str::random(48),
        ]);
        Storage::disk('local')->put('assignments/'.$assignment->id.'/keep.jpg', 'keep');
        AssignmentReportVersion::factory()->create([
            'assignment_id' => $assignment->id,
            'token' => $assignment->public_token,
            'snapshot' => [
                'company_name' => 'Annan',
                'customer_name' => 'Annan kund',
                'address' => 'Annan väg 1',
                'performed_on' => '2026-10-01',
                'places' => [],
                'assets' => [],
            ],
        ]);

        return $assignment;
    }

    private function assignmentFor(User $owner, string $token): Assignment
    {
        $assignment = Assignment::factory()->make([
            'user_id' => $owner->id,
            'customer_name' => PurgeExposedTestJobPhotos::CUSTOMER_NAME,
            'address' => 'Testgatan 2, Märsta',
            'performed_on' => '2026-10-17',
            'public_token' => $token,
        ]);
        $assignment->company_id = $owner->company_id;
        $assignment->id = PurgeExposedTestJobPhotos::ASSIGNMENT_ID;
        $assignment->save();

        return $assignment;
    }
}
