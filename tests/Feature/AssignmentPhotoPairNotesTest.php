<?php

namespace Tests\Feature;

use App\Actions\CreateAssignmentReportVersion;
use App\Models\Assignment;
use App\Models\AssignmentPhoto;
use App\Models\AssignmentPhotoPair;
use App\Models\AssignmentPlace;
use App\Models\AssignmentReportVersion;
use App\Models\User;
use App\PhotoPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AssignmentPhotoPairNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_comment_is_versioned_and_internal_note_never_leaks_to_report_or_email(): void
    {
        Storage::fake('local');
        $this->useArrayMailer();
        $owner = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create([
            'customer_name' => 'Anna Andersson',
            'address' => 'Storgatan 1',
            'performed_on' => '2026-10-15',
        ]);
        $place = $assignment->places()->create(['name' => 'Kök', 'sort_order' => 1]);
        $pair = $place->photoPairs()->create(['sort_order' => 1]);
        $before = $this->createPhoto($assignment, $pair, PhotoPhase::Before, 'before.jpg', 'before bytes');
        $after = $this->createPhoto($assignment, $pair, PhotoPhase::After, 'after.jpg', 'after bytes');
        $versions = app(CreateAssignmentReportVersion::class);
        $firstVersion = $versions->create($assignment, $owner, preserveCurrentToken: true);
        $firstSnapshotHash = $firstVersion->snapshot_hash;

        $customerComment = 'Bänken rengjordes noggrant.';
        $internalNote = 'INTERNAL_ONLY - kunden hade redan en spricka.';
        $this->actingAs($owner)
            ->put(route('assignments.places.pairs.notes.update', [$assignment, $place, $pair], false), [
                'label' => '  Bänk  ',
                'customer_comment' => $customerComment,
                'internal_note' => $internalNote,
            ])
            ->assertRedirect();

        $pair->refresh();
        $this->assertSame('Bänk', $pair->label);
        $this->assertSame($customerComment, $pair->customer_comment);
        $this->assertSame($internalNote, $pair->internal_note);
        $this->assertSame('draft', $assignment->fresh()->status);
        $this->assertSame($firstSnapshotHash, $firstVersion->fresh()->snapshot_hash);
        $this->assertNull($firstVersion->fresh()->snapshot['places'][0]['pairs'][0]['label']);
        $this->assertNull($firstVersion->fresh()->snapshot['places'][0]['pairs'][0]['customer_comment']);
        $this->assertTrue($versions->hasUnpublishedDraftChanges($assignment->fresh(), $firstVersion->fresh()));

        $this->get(route('reports.show', ['token' => $firstVersion->token], false))
            ->assertOk()
            ->assertDontSee($internalNote, false)
            ->assertDontSee($customerComment, false);

        $this->actingAs($owner)
            ->post(route('assignments.versions.store', $assignment, false))
            ->assertRedirect(route('assignments.show', $assignment, false));
        $newVersion = $assignment->reportVersions()->firstOrFail();
        $this->assertSame(2, $newVersion->version_number);
        $this->assertSame('Bänk', $newVersion->snapshot['places'][0]['pairs'][0]['label']);
        $this->assertSame($customerComment, $newVersion->snapshot['places'][0]['pairs'][0]['customer_comment']);
        $this->assertArrayNotHasKey('internal_note', $newVersion->snapshot['places'][0]['pairs'][0]);
        $this->assertStringNotContainsString($internalNote, json_encode($newVersion->snapshot, JSON_THROW_ON_ERROR));

        $this->get(route('reports.show', ['token' => $newVersion->token], false))
            ->assertOk()
            ->assertSee($customerComment, false)
            ->assertDontSee($internalNote, false);

        $this->actingAs($owner)
            ->from(route('assignments.show', $assignment, false))
            ->post(route('assignments.email', $assignment, false), [
                'recipient_email' => 'anna@example.test',
                'message' => 'Rapporten är klar.',
            ])
            ->assertRedirect(route('assignments.show', $assignment, false))
            ->assertSessionHas('status');

        $email = Mail::mailer()->getSymfonyTransport()->messages()->first()->getOriginalMessage();
        $this->assertStringContainsString($newVersion->token, $email->getHtmlBody());
        $this->assertStringNotContainsString($internalNote, $email->getHtmlBody());
        $this->assertStringNotContainsString($internalNote, $email->getTextBody());
        $this->assertSame('before bytes', $this->versionPhotoBytes($newVersion, $before));
        $this->assertSame('after bytes', $this->versionPhotoBytes($newVersion, $after));
    }

    public function test_note_update_is_tenant_scoped_validated_and_respects_expired_access(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        [$assignment, $place, $pair] = $this->assignmentWithPair($owner);
        [, $foreignPlace, $foreignPair] = $this->assignmentWithPair($otherOwner);

        $this->actingAs($owner)
            ->put(route('assignments.places.pairs.notes.update', [$assignment, $foreignPlace, $foreignPair], false), [
                'customer_comment' => 'Foreign comment',
                'internal_note' => 'Foreign internal note',
            ])
            ->assertNotFound();

        $this->assertNull($foreignPair->fresh()->customer_comment);
        $this->assertNull($foreignPair->fresh()->internal_note);

        $this->actingAs($owner)
            ->from(route('assignments.show', $assignment, false))
            ->put(route('assignments.places.pairs.notes.update', [$assignment, $place, $pair], false), [
                'customer_comment' => str_repeat('c', 1001),
                'internal_note' => str_repeat('i', 2001),
            ])
            ->assertRedirect(route('assignments.show', $assignment, false))
            ->assertSessionHasErrors(['customer_comment', 'internal_note']);

        $this->assertNull($pair->fresh()->customer_comment);
        $this->assertNull($pair->fresh()->internal_note);

        $owner->company->forceFill([
            'trial_ends_at' => now()->subDay(),
            'paid_until' => now()->subDay(),
        ])->save();

        $this->actingAs($owner)
            ->from(route('assignments.show', $assignment, false))
            ->put(route('assignments.places.pairs.notes.update', [$assignment, $place, $pair], false), [
                'customer_comment' => 'Should not be saved',
                'internal_note' => 'Still private',
            ])
            ->assertRedirect(route('assignments.show', $assignment, false))
            ->assertSessionHasErrors('trial');

        $this->assertNull($pair->fresh()->customer_comment);
        $this->assertNull($pair->fresh()->internal_note);
    }

    /**
     * @return array{Assignment, AssignmentPlace, AssignmentPhotoPair}
     */
    private function assignmentWithPair(User $owner): array
    {
        $assignment = Assignment::factory()->for($owner)->create();
        $place = $assignment->places()->create(['name' => 'Kök', 'sort_order' => 1]);
        $pair = $place->photoPairs()->create(['label' => 'Bänk', 'sort_order' => 1]);

        return [$assignment, $place, $pair];
    }

    private function createPhoto(
        Assignment $assignment,
        AssignmentPhotoPair $pair,
        PhotoPhase $phase,
        string $filename,
        string $contents,
    ): AssignmentPhoto {
        $path = 'assignments/'.$assignment->id.'/'.$filename;
        Storage::disk('local')->put($path, $contents);

        return $assignment->photos()->create([
            'assignment_photo_pair_id' => $pair->id,
            'phase' => $phase,
            'path' => $path,
            'sort_order' => 1,
        ]);
    }

    private function versionPhotoBytes(AssignmentReportVersion $version, AssignmentPhoto $photo): string
    {
        $asset = $version->photoAssets()->where('source_photo_id', $photo->id)->sole();

        return Storage::disk('local')->get($asset->path);
    }

    private function useArrayMailer(): void
    {
        config([
            'app.url' => 'https://klartfoto.test',
            'mail.default' => 'array',
            'mail.from.address' => 'noreply@klartfoto.se',
            'mail.from.name' => 'Klartfoto',
        ]);
        URL::forceRootUrl('https://klartfoto.test');
        URL::forceScheme('https');
        Mail::purge();
    }
}
