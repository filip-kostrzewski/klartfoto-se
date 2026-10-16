<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentPhoto;
use App\Models\User;
use App\PhotoPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssignmentPhotoPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_preview_draft_photos_after_access_expires(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create();
        $photo = $this->createPhoto($assignment, 'private draft image bytes');
        $owner->company->forceFill([
            'trial_ends_at' => now()->subDay(),
            'paid_until' => now()->subDay(),
        ])->save();

        $response = $this->actingAs($owner)
            ->get(route('assignments.photos.preview', [$assignment, $photo], false))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
    }

    public function test_draft_photo_preview_is_authenticated_and_tenant_scoped(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create();
        $photo = $this->createPhoto($assignment, 'tenant private bytes');

        $url = route('assignments.photos.preview', [$assignment, $photo], false);
        $this->get($url)->assertRedirect(route('login'));

        $this->actingAs($otherOwner)->get($url)->assertNotFound();

        $otherAssignment = Assignment::factory()->for($otherOwner)->create();
        $this->actingAs($owner)
            ->get(route('assignments.photos.preview', [$assignment, $this->createPhoto($otherAssignment, 'other bytes')], false))
            ->assertNotFound();
    }

    private function createPhoto(Assignment $assignment, string $contents): AssignmentPhoto
    {
        $path = 'assignments/'.$assignment->id.'/draft.jpg';
        Storage::disk('local')->put($path, $contents);

        return $assignment->photos()->create([
            'phase' => PhotoPhase::Before,
            'path' => $path,
            'sort_order' => 1,
        ]);
    }
}
