<?php

namespace Tests\Feature;

use App\Actions\CreateAssignmentReportVersion;
use App\Models\Assignment;
use App\Models\User;
use App\PhotoPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeleteAssignmentPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_photo_removes_it_from_storage_and_every_share_link(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('s3');
        $owner = User::factory()->create();

        $this->actingAs($owner)->post('/uppdrag', [
            'customer_name' => 'Anna Andersson',
            'address' => 'Storgatan 1, 111 22 Stockholm',
            'performed_on' => '2026-10-01',
            'before_photos' => [UploadedFile::fake()->image('fore.jpg', 40, 40)],
            'after_photos' => [UploadedFile::fake()->image('efter.jpg', 40, 40)],
        ])->assertRedirect();

        $assignment = Assignment::query()->sole();
        $before = $assignment->photos()->where('phase', PhotoPhase::Before)->firstOrFail();
        $after = $assignment->photos()->where('phase', PhotoPhase::After)->firstOrFail();
        $firstVersion = $assignment->reportVersions()->sole();
        $olderToken = $firstVersion->token;
        $firstBeforeAssetId = $firstVersion->photoAssets()->where('source_photo_id', $before->id)->sole()->id;
        $firstAfterAssetId = $firstVersion->photoAssets()->where('source_photo_id', $after->id)->sole()->id;
        $firstCacheKey = 'assignment-report-pdf:'.$firstVersion->id.':'.$firstVersion->snapshot_hash;

        $this->actingAs($owner)->post(route('assignments.versions.store', $assignment))
            ->assertRedirect(route('assignments.show', $assignment));

        $secondVersion = $assignment->reportVersions()->orderByDesc('version_number')->firstOrFail();
        $latestToken = $secondVersion->token;
        $secondBeforeAssetId = $secondVersion->photoAssets()->where('source_photo_id', $before->id)->sole()->id;
        $secondAfterAssetId = $secondVersion->photoAssets()->where('source_photo_id', $after->id)->sole()->id;
        $secondCacheKey = 'assignment-report-pdf:'.$secondVersion->id.':'.$secondVersion->snapshot_hash;
        $this->assertNotSame($olderToken, $latestToken);
        $this->assertSame($latestToken, $assignment->fresh()->public_token);

        Storage::disk('public')->put($before->path, 'public-copy');
        Storage::disk('s3')->put($before->path, 'remote-copy');
        Storage::disk('public')->put($after->path, 'keep-after');
        Storage::disk('s3')->put('assignments/'.$assignment->id.'/unrelated.jpg', 'keep-unrelated');
        $logoPath = 'companies/'.$assignment->company_id.'/logo.svg';
        Storage::disk('local')->put($logoPath, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        Cache::store('file')->put($firstCacheKey, '%PDF-1.4 older', 60);
        Cache::store('file')->put($secondCacheKey, '%PDF-1.4 latest', 60);
        Cache::store('file')->put('assignment-report-pdf:control', '%PDF-1.4 control', 60);

        $otherOwner = User::factory()->create();
        $otherAssignment = Assignment::factory()->for($otherOwner)->create();
        $otherPath = 'assignments/'.$otherAssignment->id.'/keep.jpg';
        Storage::disk('local')->put($otherPath, 'other-company');
        $otherPhoto = $otherAssignment->photos()->create([
            'phase' => PhotoPhase::Before,
            'path' => $otherPath,
            'sort_order' => 1,
        ]);
        $otherVersion = app(CreateAssignmentReportVersion::class)->create($otherAssignment, $otherOwner, preserveCurrentToken: true);
        $otherAssetId = $otherVersion->photoAssets()->where('source_photo_id', $otherPhoto->id)->sole()->id;

        $this->actingAs($owner)->delete(route('assignments.photos.destroy', [$assignment, $before]))
            ->assertRedirect(route('assignments.show', $assignment))
            ->assertSessionHas('toast', 'Bilden är borttagen.');

        $this->assertModelMissing($before);
        $this->assertModelExists($after);
        $this->assertDatabaseMissing('assignment_report_version_assets', ['source_photo_id' => $before->id]);
        $this->assertDatabaseHas('assignment_report_version_assets', [
            'id' => $firstAfterAssetId,
            'source_photo_id' => $after->id,
        ]);
        $this->assertDatabaseHas('assignment_report_version_assets', [
            'id' => $secondAfterAssetId,
            'source_photo_id' => $after->id,
        ]);
        $this->assertSame($olderToken, $firstVersion->fresh()->token);
        $this->assertSame($latestToken, $secondVersion->fresh()->token);
        $this->assertSame($latestToken, $assignment->fresh()->public_token);

        Storage::disk('local')->assertMissing($before->path);
        Storage::disk('public')->assertMissing($before->path);
        Storage::disk('s3')->assertMissing($before->path);
        Storage::disk('local')->assertExists($after->path);
        Storage::disk('public')->assertExists($after->path);
        Storage::disk('s3')->assertExists('assignments/'.$assignment->id.'/unrelated.jpg');
        Storage::disk('local')->assertExists($logoPath);
        Storage::disk('local')->assertExists($otherPath);
        $this->assertNull(Cache::store('file')->get($firstCacheKey));
        $this->assertNull(Cache::store('file')->get($secondCacheKey));
        $this->assertSame('%PDF-1.4 control', Cache::store('file')->get('assignment-report-pdf:control'));

        foreach ([
            [$olderToken, $firstBeforeAssetId, $firstAfterAssetId, $firstVersion],
            [$latestToken, $secondBeforeAssetId, $secondAfterAssetId, $secondVersion],
        ] as [$token, $deletedAssetId, $keptAssetId, $version]) {
            $freshVersion = $version->fresh();
            $snapshotJson = json_encode($freshVersion->snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $this->assertStringNotContainsString($before->path, $snapshotJson);
            $this->assertSame(hash('sha256', $snapshotJson), $freshVersion->snapshot_hash);
            $this->get('/r/'.$token)
                ->assertOk()
                ->assertSee('Anna Andersson', false)
                ->assertDontSee('/version-foton/'.$deletedAssetId, false)
                ->assertSee('/version-foton/'.$keptAssetId, false)
                ->assertDontSee($before->path, false);
            $this->get('/r/'.$token.'/version-foton/'.$deletedAssetId)->assertNotFound();
            $this->get('/r/'.$token.'/foton/'.$before->id)->assertNotFound();
            $this->get('/r/'.$token.'/version-foton/'.$keptAssetId)->assertOk();
        }

        $this->get('/r/'.$otherVersion->token)
            ->assertOk()
            ->assertSee('/version-foton/'.$otherAssetId, false);
        $this->get('/r/'.$otherVersion->token.'/version-foton/'.$otherAssetId)->assertOk();
    }

    public function test_deleting_an_unpublished_photo_leaves_the_existing_share_link_and_its_file(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('s3');
        $owner = User::factory()->create();

        $this->actingAs($owner)->post('/uppdrag', [
            'customer_name' => 'Anna Andersson',
            'address' => 'Storgatan 1, 111 22 Stockholm',
            'performed_on' => '2026-10-01',
            'before_photos' => [UploadedFile::fake()->image('fore.jpg', 40, 40)],
        ])->assertRedirect();

        $assignment = Assignment::query()->sole();
        $published = $assignment->photos()->sole();
        $version = $assignment->reportVersions()->sole();
        $publishedHash = $version->snapshot_hash;
        $token = $version->token;
        $assetId = $version->photoAssets()->sole()->id;

        $this->actingAs($owner)->post(route('assignments.photos.store', $assignment), [
            'place_name' => 'Hall',
            'after_photos' => [UploadedFile::fake()->image('extra.jpg', 40, 40)],
        ])->assertRedirect();

        $draft = $assignment->photos()->whereKeyNot($published->id)->sole();
        Storage::disk('local')->assertExists($draft->path);
        Storage::disk('public')->put($published->path, 'published-copy');

        $this->actingAs($owner)->delete(route('assignments.photos.destroy', [$assignment, $draft]))
            ->assertRedirect(route('assignments.show', $assignment));

        $this->assertModelMissing($draft);
        $this->assertModelExists($published);
        Storage::disk('local')->assertMissing($draft->path);
        Storage::disk('local')->assertExists($published->path);
        Storage::disk('public')->assertExists($published->path);
        $this->assertSame($publishedHash, $version->fresh()->snapshot_hash);
        $this->assertSame($token, $version->fresh()->token);

        $this->get('/r/'.$token)
            ->assertOk()
            ->assertSee('/version-foton/'.$assetId, false);
        $this->get('/r/'.$token.'/version-foton/'.$assetId)->assertOk();
    }
}
