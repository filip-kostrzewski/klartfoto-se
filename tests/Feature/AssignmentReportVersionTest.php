<?php

namespace Tests\Feature;

use App\Actions\CreateAssignmentReportVersion;
use App\Actions\StoreCompanyLogo;
use App\CompanyPlan;
use App\Models\Assignment;
use App\Models\AssignmentPhoto;
use App\Models\AssignmentReportVersionAsset;
use App\Models\User;
use App\PhotoPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssignmentReportVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_photo_pairs_keep_their_slots_after_delete_and_reorder_and_sent_version_keeps_photo_bytes(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();

        $this->actingAs($owner)->post(route('assignments.store', absolute: false), [
            'customer_name' => 'Anna Andersson',
            'customer_email' => 'anna@example.test',
            'address' => 'Storgatan 1',
            'performed_on' => '2026-10-15',
            'place_name' => 'Kök',
            'before_photos' => [
                UploadedFile::fake()->image('before-one.jpg', 40, 40),
                UploadedFile::fake()->image('before-two.jpg', 40, 40),
            ],
            'after_photos' => [
                UploadedFile::fake()->image('after-one.jpg', 40, 40),
                UploadedFile::fake()->image('after-two.jpg', 40, 40),
            ],
        ])->assertRedirect();

        $assignment = Assignment::query()->sole();
        $version = $assignment->reportVersions()->sole();
        $place = $assignment->places()->with('photoPairs.photos')->sole();
        $pairs = $place->photoPairs;
        $firstBefore = $pairs[0]->photos->firstWhere('phase', PhotoPhase::Before);
        $firstAfter = $pairs[0]->photos->firstWhere('phase', PhotoPhase::After);
        $secondBefore = $pairs[1]->photos->firstWhere('phase', PhotoPhase::Before);
        $secondAfter = $pairs[1]->photos->firstWhere('phase', PhotoPhase::After);
        $this->assertInstanceOf(AssignmentPhoto::class, $firstBefore);
        $this->assertInstanceOf(AssignmentPhoto::class, $firstAfter);
        $this->assertInstanceOf(AssignmentPhoto::class, $secondBefore);
        $this->assertInstanceOf(AssignmentPhoto::class, $secondAfter);
        $firstBeforeBytes = Storage::disk('local')->get($firstBefore->path);
        $firstBeforeAsset = $version->photoAssets()->where('source_photo_id', $firstBefore->id)->sole();
        $originalSnapshot = $version->snapshot;

        $this->actingAs($owner)->post(route('assignments.share', $assignment, false))
            ->assertRedirect();

        $this->actingAs($owner)->delete(route('assignments.photos.destroy', [$assignment, $firstBefore], false))
            ->assertRedirect();
        Storage::disk('local')->assertExists($firstBefore->path);

        $this->actingAs($owner)->put(route('assignments.places.pairs.reorder', [$assignment, $place], false), [
            'pair_ids' => [$pairs[1]->id, $pairs[0]->id],
        ])->assertRedirect();

        $currentAssignment = $assignment->fresh()->load('places.photoPairs.photos');
        $currentPairs = $currentAssignment->photoPairs();
        $this->assertSame($secondBefore->id, $currentPairs[0]['before']->id);
        $this->assertSame($secondAfter->id, $currentPairs[0]['after']->id);
        $this->assertNull($currentPairs[1]['before']);
        $this->assertSame($firstAfter->id, $currentPairs[1]['after']->id);
        $this->assertSame($originalSnapshot, $version->fresh()->snapshot);

        $existingPhotoIds = $assignment->photos()->pluck('id')->all();
        $this->actingAs($owner)->post(route('assignments.photos.store', $assignment, false), [
            'place_name' => 'Kök',
            'before_photos' => [UploadedFile::fake()->image('draft-only.jpg', 40, 40)],
        ])->assertRedirect();
        $draftOnlyPhoto = $assignment->fresh()->photos()->whereNotIn('id', $existingPhotoIds)->sole();

        $this->get(route('reports.photos.show', [
            'token' => $version->token,
            'photo' => $draftOnlyPhoto->id,
        ], false))->assertNotFound();
        $this->get(route('reports.photos.show', [
            'token' => $version->token,
            'photo' => $firstBefore->id,
        ], false))->assertOk();

        $this->get(route('reports.show', ['token' => $version->token], false))
            ->assertOk()
            ->assertSee(route('reports.version-assets.show', ['token' => $version->token, 'asset' => $firstBeforeAsset->id], false), false);

        $versionPhotoResponse = $this->get(route(
            'reports.version-assets.show',
            ['token' => $version->token, 'asset' => $firstBeforeAsset->id],
            false,
        ))->assertOk();

        $this->assertSame($firstBeforeBytes, $versionPhotoResponse->baseResponse->getFile()->getContent());
    }

    public function test_saved_customer_properties_are_tenant_scoped_and_new_visit_starts_with_fresh_report_state(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $customer = $owner->company->customers()->create([
            'name' => 'Anna Andersson',
            'email' => 'anna@example.test',
        ]);
        $firstProperty = $owner->company->customerProperties()->create([
            'customer_id' => $customer->id,
            'label' => 'Hem',
            'address' => 'Storgatan 1',
        ]);
        $secondProperty = $owner->company->customerProperties()->create([
            'customer_id' => $customer->id,
            'label' => 'Sommarhus',
            'address' => 'Skogsvägen 8',
        ]);
        $foreignCustomer = $otherOwner->company->customers()->create([
            'name' => 'Other tenant',
            'email' => 'other@example.test',
        ]);
        $foreignProperty = $otherOwner->company->customerProperties()->create([
            'customer_id' => $foreignCustomer->id,
            'address' => 'Other street 4',
        ]);

        $this->actingAs($owner)->get(route('assignments.create', absolute: false))
            ->assertOk()
            ->assertSee('Anna Andersson', false)
            ->assertDontSee('Other tenant', false);

        $this->actingAs($owner)->post(route('assignments.store', absolute: false), [
            'customer_id' => $customer->id,
            'customer_property_id' => $firstProperty->id,
            'customer_name' => 'Anna Andersson',
            'customer_email' => 'anna@example.test',
            'address' => 'Storgatan 1',
            'performed_on' => '2026-10-15',
            'place_name' => 'Hall',
            'before_photos' => [UploadedFile::fake()->image('before.jpg', 40, 40)],
        ])->assertRedirect();
        $firstVisit = Assignment::query()->sole();

        $this->actingAs($owner)->post(route('assignments.store', absolute: false), [
            'customer_id' => $customer->id,
            'customer_property_id' => $secondProperty->id,
            'customer_name' => 'Anna Andersson',
            'customer_email' => 'anna@example.test',
            'address' => 'Skogsvägen 8',
            'performed_on' => '2026-10-16',
            'place_name' => 'Kök',
        ])->assertRedirect();
        $secondVisit = Assignment::query()->whereKeyNot($firstVisit->id)->sole();

        $this->assertSame($customer->id, $secondVisit->customer_id);
        $this->assertSame($secondProperty->id, $secondVisit->customer_property_id);
        $this->assertSame('Skogsvägen 8', $secondVisit->address);
        $this->assertSame('2026-10-16', $secondVisit->performed_on->toDateString());
        $this->assertSame('draft', $secondVisit->status);
        $this->assertSame(0, $secondVisit->photos()->count());
        $this->assertNotSame($firstVisit->public_token, $secondVisit->public_token);
        $this->assertSame(0, $secondVisit->reportVersions()->count());
        $this->assertSame(1, $firstVisit->reportVersions()->count());

        $this->actingAs($owner)->from(route('assignments.create', absolute: false))->post(route('assignments.store', absolute: false), [
            'customer_id' => $foreignCustomer->id,
            'customer_property_id' => $foreignProperty->id,
            'customer_name' => 'Other tenant',
            'customer_email' => 'other@example.test',
            'address' => 'Other street 4',
            'performed_on' => '2026-10-16',
        ])->assertRedirect(route('assignments.create', absolute: false))
            ->assertSessionHasErrors(['customer_id', 'customer_property_id']);

        $this->assertDatabaseCount('assignments', 2);
        $this->assertSame('Skogsvägen 8', $secondVisit->customerProperty()->value('address'));
    }

    public function test_editing_a_saved_customer_creates_a_new_immutable_version_without_rewriting_the_old_report(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();

        $this->actingAs($owner)->post(route('assignments.store', absolute: false), [
            'customer_name' => 'Anna Andersson',
            'customer_email' => 'anna@example.test',
            'address' => 'Storgatan 1',
            'performed_on' => '2026-10-15',
            'place_name' => 'Badrum',
            'after_photos' => [UploadedFile::fake()->image('after.jpg', 40, 40)],
        ])->assertRedirect();

        $assignment = Assignment::query()->sole();
        $oldVersion = $assignment->reportVersions()->sole();
        $oldSnapshot = $oldVersion->snapshot;

        $this->actingAs($owner)->post(route('assignments.share', $assignment, false))->assertRedirect();
        $this->actingAs($owner)->put(route('assignments.update', $assignment, false), [
            'customer_id' => $assignment->customer_id,
            'customer_property_id' => $assignment->customer_property_id,
            'customer_name' => 'Anna Andersson Updated',
            'customer_email' => 'anna@example.test',
            'address' => 'Storgatan 2',
            'performed_on' => '2026-10-16',
        ])->assertRedirect(route('assignments.show', $assignment, false));

        $this->assertSame($oldSnapshot, $oldVersion->fresh()->snapshot);
        $this->assertSame('Anna Andersson Updated', $assignment->fresh()->customer_name);
        $this->assertSame('Anna Andersson Updated', $assignment->customer()->value('name'));
        $this->assertSame('Storgatan 2', $assignment->customerProperty()->value('address'));
        $this->assertDatabaseHas('assignment_report_versions', [
            'id' => $oldVersion->id,
            'token' => $oldVersion->token,
            'snapshot_hash' => $oldVersion->snapshot_hash,
        ]);

        $this->actingAs($owner)->post(route('assignments.versions.store', $assignment, false))
            ->assertRedirect(route('assignments.show', $assignment, false));
        $newVersion = $assignment->reportVersions()->firstOrFail();

        $this->assertSame(2, $newVersion->version_number);
        $this->assertNotSame($oldVersion->token, $newVersion->token);
        $this->assertSame('Anna Andersson Updated', $newVersion->snapshot['customer_name']);
        $this->assertSame('Storgatan 2', $newVersion->snapshot['address']);
        $this->assertSame($oldVersion->token, $oldVersion->fresh()->token);
        $this->assertSame($oldSnapshot, $oldVersion->fresh()->snapshot);

        $this->get(route('reports.show', ['token' => $oldVersion->token], false))
            ->assertOk()
            ->assertSee('Anna Andersson', false)
            ->assertDontSee('Anna Andersson Updated', false);
        $this->get(route('reports.show', ['token' => $newVersion->token], false))
            ->assertOk()
            ->assertSee('Anna Andersson Updated', false)
            ->assertSee('Storgatan 2', false);
    }

    public function test_new_version_hash_matches_its_canonical_snapshot_shape(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('assignments.store', absolute: false), [
            'customer_name' => 'Anna Andersson',
            'address' => 'Storgatan 1',
            'performed_on' => '2026-10-15',
            'place_name' => 'Kök',
            'before_photos' => [UploadedFile::fake()->image('before.jpg', 40, 40)],
            'after_photos' => [UploadedFile::fake()->image('after.jpg', 40, 40)],
        ])->assertRedirect();

        $assignment = Assignment::query()->sole();
        $version = $assignment->reportVersions()->sole();
        $snapshot = app(CreateAssignmentReportVersion::class)->snapshotData($assignment->fresh());
        $canonicalJson = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->assertSame(hash('sha256', $canonicalJson), $version->snapshot_hash);
        $this->assertFalse(app(CreateAssignmentReportVersion::class)->hasUnpublishedDraftChanges($assignment, $version));
    }

    public function test_legacy_snapshot_key_order_does_not_look_like_a_draft_change(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('assignments.store', absolute: false), [
            'customer_name' => 'Anna Andersson',
            'address' => 'Storgatan 1',
            'performed_on' => '2026-10-15',
            'place_name' => 'Kök',
            'before_photos' => [UploadedFile::fake()->image('before.jpg', 40, 40)],
            'after_photos' => [UploadedFile::fake()->image('after.jpg', 40, 40)],
        ])->assertRedirect();

        $assignment = Assignment::query()->sole();
        $version = $assignment->reportVersions()->sole();
        $version->forceFill(['snapshot' => $this->reverseSnapshotObjectKeys($version->snapshot)])->save();

        $this->assertFalse(app(CreateAssignmentReportVersion::class)->hasUnpublishedDraftChanges($assignment, $version));
    }

    public function test_public_version_keeps_photo_and_logo_bytes_when_sources_and_team_entitlement_change(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $company = $owner->company;
        $company->forceFill([
            'plan' => CompanyPlan::Team,
            'trial_ends_at' => now()->subDay(),
            'paid_until' => now()->addMonth(),
        ])->save();

        $originalLogoPath = 'companies/'.$company->id.'/logos/original.svg';
        Storage::disk('local')->put($originalLogoPath, '<svg xmlns="http://www.w3.org/2000/svg"><text>Original logo</text></svg>');
        $company->forceFill(['logo_path' => $originalLogoPath])->save();
        $originalLogoBytes = Storage::disk('local')->get($originalLogoPath);

        $assignment = Assignment::factory()->for($owner)->create([
            'customer_name' => 'Anna Andersson',
            'address' => 'Storgatan 1',
        ]);
        $photoPath = 'assignments/'.$assignment->id.'/original.jpg';
        $originalPhotoBytes = 'immutable report photo bytes';
        Storage::disk('local')->put($photoPath, $originalPhotoBytes);
        $photo = $assignment->photos()->create([
            'phase' => PhotoPhase::Before,
            'path' => $photoPath,
            'sort_order' => 1,
        ]);

        $version = app(CreateAssignmentReportVersion::class)->create($assignment, $owner, preserveCurrentToken: true);
        $photoAsset = $version->photoAssets()->where('source_photo_id', $photo->id)->sole();
        $logoAsset = $version->assets()->where('type', AssignmentReportVersionAsset::TYPE_LOGO)->sole();

        $this->actingAs($owner)
            ->delete(route('assignments.photos.destroy', [$assignment, $photo], false))
            ->assertRedirect();

        app(StoreCompanyLogo::class)->handle(
            $company->fresh(),
            UploadedFile::fake()->createWithContent(
                'replacement.svg',
                '<svg xmlns="http://www.w3.org/2000/svg"><text>Replacement logo</text></svg>',
            ),
        );
        $company->fresh()->forceFill([
            'plan' => CompanyPlan::Start,
            'paid_until' => now()->subDay(),
        ])->save();

        $this->assertDatabaseMissing('assignment_photos', ['id' => $photo->id]);
        Storage::disk('local')->assertExists($photoPath);
        Storage::disk('local')->assertExists($originalLogoPath);

        $this->get(route('reports.show', ['token' => $version->token], false))
            ->assertOk()
            ->assertSee(route('reports.logo', ['token' => $version->token], false), false);

        $photoResponse = $this->get(route('reports.photos.show', [
            'token' => $version->token,
            'photo' => $photo->id,
        ], false))->assertOk();
        $logoResponse = $this->get(route('reports.logo', ['token' => $version->token], false))->assertOk();

        $this->assertSame($originalPhotoBytes, $photoResponse->baseResponse->getFile()->getContent());
        $this->assertSame($originalLogoBytes, $logoResponse->baseResponse->getFile()->getContent());
        $this->assertNotSame($logoAsset->path, $company->fresh()->logo_path);
    }

    /**
     * @param  array<string|int, mixed>  $value
     * @return array<string|int, mixed>
     */
    private function reverseSnapshotObjectKeys(array $value): array
    {
        if (array_is_list($value)) {
            foreach ($value as $index => $item) {
                if (is_array($item)) {
                    $value[$index] = $this->reverseSnapshotObjectKeys($item);
                }
            }

            return $value;
        }

        $value = array_reverse($value, preserve_keys: true);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->reverseSnapshotObjectKeys($item);
            }
        }

        return $value;
    }
}
