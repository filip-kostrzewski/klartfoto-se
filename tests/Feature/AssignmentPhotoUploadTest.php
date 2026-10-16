<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentPhotoPair;
use App\Models\User;
use App\PhotoPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssignmentPhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_later_photo_fills_the_selected_pair_and_same_bytes_in_distinct_slots_are_kept(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create();
        $pair = $this->createPair($assignment, 'Bathroom');
        $image = UploadedFile::fake()->image('same.jpg', 40, 40);
        $beforeUploadId = Str::uuid()->toString();
        $afterUploadId = Str::uuid()->toString();
        $url = route('assignments.photos.store', $assignment, false);
        $this->actingAs($owner);

        $firstUpload = $this->post($url, [
            'assignment_photo_pair_id' => $pair->id,
            'before_photos' => [$image],
            'before_upload_ids' => [$beforeUploadId],
        ], ['Accept' => 'application/json'])->assertOk()->assertJson([
            'uploaded' => 1,
            'duplicates' => 0,
        ]);

        $before = $pair->photos()->where('phase', PhotoPhase::Before)->sole();
        $firstUpload->assertJsonPath('uploads.0.uploadId', $beforeUploadId)
            ->assertJsonPath('uploads.0.photoId', $before->id)
            ->assertJsonPath('uploads.0.pairId', $pair->id)
            ->assertJsonPath('uploads.0.phase', PhotoPhase::Before->value)
            ->assertJsonPath('uploads.0.result', 'uploaded');

        $this->post($url, [
            'assignment_photo_pair_id' => $pair->id,
            'after_photos' => [$image],
            'after_upload_ids' => [$afterUploadId],
        ], ['Accept' => 'application/json'])->assertOk()->assertJson([
            'uploaded' => 1,
            'duplicates' => 0,
        ]);

        $this->assertSame(2, $pair->photos()->count());
        $this->assertSame($before->id, $pair->photos()->where('phase', PhotoPhase::Before)->sole()->id);
        $after = $pair->photos()->where('phase', PhotoPhase::After)->sole();
        $this->assertSame($pair->id, $after->assignment_photo_pair_id);
        $this->assertSame($afterUploadId, $after->client_upload_id);

        $duplicateRetry = $this->post($url, [
            'assignment_photo_pair_id' => $pair->id,
            'after_photos' => [$image],
            'after_upload_ids' => [$afterUploadId],
        ], ['Accept' => 'application/json'])->assertOk()->assertJson([
            'uploaded' => 0,
            'duplicates' => 1,
        ]);

        $duplicateRetry->assertJsonPath('uploads.0.uploadId', $afterUploadId)
            ->assertJsonPath('uploads.0.photoId', $after->id)
            ->assertJsonPath('uploads.0.pairId', $pair->id)
            ->assertJsonPath('uploads.0.phase', PhotoPhase::After->value)
            ->assertJsonPath('uploads.0.result', 'duplicate');
        $this->assertSame(2, $pair->photos()->count());
        $this->assertSame(1, AssignmentPhotoPair::query()->count());
    }

    public function test_single_file_mapping_supports_explicit_pair_fill_and_duplicate_retry(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create();
        $image = UploadedFile::fake()->image('before.jpg', 40, 40);
        $beforeUploadId = Str::uuid()->toString();
        $afterUploadId = Str::uuid()->toString();
        $url = route('assignments.photos.store', $assignment, false);
        $this->actingAs($owner);

        $beforeResponse = $this->post($url, [
            'place_name' => 'Bathroom',
            'before_photos' => [$image],
            'before_upload_ids' => [$beforeUploadId],
        ], ['Accept' => 'application/json'])->assertOk();
        $beforeMapping = $beforeResponse->json('uploads.0');
        $this->assertSame($beforeUploadId, $beforeMapping['uploadId']);
        $this->assertSame('uploaded', $beforeMapping['result']);

        $pair = AssignmentPhotoPair::query()->findOrFail($beforeMapping['pairId']);
        $before = $pair->photos()->where('phase', PhotoPhase::Before)->sole();
        $this->assertSame($before->id, $beforeMapping['photoId']);

        $afterResponse = $this->post($url, [
            'assignment_photo_pair_id' => $pair->id,
            'after_photos' => [$image],
            'after_upload_ids' => [$afterUploadId],
        ], ['Accept' => 'application/json'])->assertOk();
        $afterMapping = $afterResponse->json('uploads.0');
        $this->assertSame($pair->id, $afterMapping['pairId']);
        $this->assertSame('after', $afterMapping['phase']);
        $this->assertSame('uploaded', $afterMapping['result']);

        $retryResponse = $this->post($url, [
            'assignment_photo_pair_id' => $pair->id,
            'before_photos' => [$image],
            'before_upload_ids' => [$beforeUploadId],
        ], ['Accept' => 'application/json'])->assertOk();
        $retryMapping = $retryResponse->json('uploads.0');

        $this->assertSame($beforeMapping['photoId'], $retryMapping['photoId']);
        $this->assertSame($beforeMapping['pairId'], $retryMapping['pairId']);
        $this->assertSame('duplicate', $retryMapping['result']);
        $this->assertSame(2, $pair->photos()->count());
    }

    public function test_upload_retry_key_is_bound_to_its_payload_phase_pair_and_assignment(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create();
        $pair = $this->createPair($assignment, 'Kitchen');
        $foreignPair = $this->createPair(Assignment::factory()->for($otherOwner)->create(), 'Kitchen');
        $uploadId = Str::uuid()->toString();
        $image = UploadedFile::fake()->image('first.jpg', 40, 40);
        $url = route('assignments.photos.store', $assignment, false);
        $this->actingAs($owner);

        $this->post($url, [
            'assignment_photo_pair_id' => $pair->id,
            'before_photos' => [$image],
            'before_upload_ids' => [$uploadId],
        ], ['Accept' => 'application/json'])->assertOk();

        $this->post($url, [
            'assignment_photo_pair_id' => $foreignPair->id,
            'after_photos' => [UploadedFile::fake()->image('foreign.jpg', 40, 40)],
            'after_upload_ids' => [Str::uuid()->toString()],
        ], ['Accept' => 'application/json'])->assertNotFound();

        $this->post($url, [
            'assignment_photo_pair_id' => $pair->id,
            'before_photos' => [UploadedFile::fake()->image('changed.jpg', 80, 40)],
            'before_upload_ids' => [$uploadId],
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->post($url, [
            'assignment_photo_pair_id' => $pair->id,
            'after_photos' => [UploadedFile::fake()->image('changed-phase.jpg', 40, 40)],
            'after_upload_ids' => [$uploadId],
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->post($url, [
            'assignment_photo_pair_id' => $pair->id,
            'before_photos' => [UploadedFile::fake()->image('second.jpg', 40, 40)],
            'before_upload_ids' => [Str::uuid()->toString()],
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->assertSame(1, $pair->photos()->count());
        $this->assertSame(0, $foreignPair->photos()->count());
    }

    public function test_expired_account_cannot_upload_even_when_it_can_still_view_the_assignment(): void
    {
        Storage::fake('local');
        $owner = User::factory()->expiredTrial()->create();
        $assignment = Assignment::factory()->for($owner)->create();

        $this->actingAs($owner)->get(route('assignments.show', $assignment))->assertOk();

        $this->post(route('assignments.photos.store', $assignment, false), [
            'before_photos' => [UploadedFile::fake()->image('blocked.jpg', 40, 40)],
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->assertDatabaseCount('assignment_photos', 0);
    }

    private function createPair(Assignment $assignment, string $placeName): AssignmentPhotoPair
    {
        $place = $assignment->places()->create([
            'name' => $placeName,
            'sort_order' => (int) $assignment->places()->max('sort_order') + 1,
        ]);

        return $place->photoPairs()->create(['sort_order' => 1]);
    }
}
