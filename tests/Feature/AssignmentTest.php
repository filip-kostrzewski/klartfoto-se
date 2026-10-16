<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\User;
use App\PhotoPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_login_before_creating_a_job(): void
    {
        $this->get('/uppdrag')->assertRedirect(route('login'));
        $this->post('/uppdrag', [])->assertRedirect(route('login'));
        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_company_creates_a_job_and_gets_a_share_link(): void
    {
        $user = User::factory()->create(['name' => 'Glans Städ AB']);

        $response = $this->actingAs($user)->post('/uppdrag', [
            'customer_name' => 'Anna Andersson',
            'customer_email' => 'anna@example.test',
            'address' => 'Storgatan 1, 111 22 Stockholm',
            'performed_on' => '2026-10-01',
        ]);

        $assignment = Assignment::query()->first();
        $this->assertNotNull($assignment);
        $this->assertSame($user->id, $assignment->user_id);
        $this->assertSame($user->company_id, $assignment->company_id);
        $this->assertSame('Anna Andersson', $assignment->customer_name);
        $this->assertSame('anna@example.test', $assignment->customer_email);
        $this->assertSame('Storgatan 1, 111 22 Stockholm', $assignment->address);
        $this->assertSame('2026-10-01', $assignment->performed_on->toDateString());
        $this->assertSame(48, strlen($assignment->public_token));
        $this->assertNotSame((string) $assignment->id, $assignment->public_token);

        $response->assertRedirect(route('assignments.show', $assignment))
            ->assertSessionHas('toast', 'Uppdraget är sparat.');
        $this->actingAs($user)->get(route('assignments.show', $assignment))
            ->assertOk()
            ->assertSee('Uppdraget är sparat.', false)
            ->assertSee(route('reports.show', ['token' => $assignment->public_token]), false)
            ->assertSee('Anna Andersson', false);
    }

    public function test_new_draft_can_be_created_without_photos_and_first_upload_publishes_its_initial_version(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/uppdrag', [
            'customer_name' => 'Erik Eriksson',
            'address' => 'Parkvägen 2, 111 22 Stockholm',
            'performed_on' => '2026-10-16',
        ])->assertRedirect();

        $assignment = Assignment::query()->sole();
        $this->assertSame(0, $assignment->photos()->count());
        $this->assertSame(0, $assignment->places()->count());
        $this->assertSame(0, $assignment->reportVersions()->count());
        $initialToken = $assignment->public_token;

        $this->actingAs($user)->post(route('assignments.photos.store', $assignment, false), [
            'place_name' => 'Hall',
            'before_photos' => [UploadedFile::fake()->image('hall.jpg', 40, 40)],
        ], ['Accept' => 'application/json'])->assertOk();

        $version = $assignment->reportVersions()->sole();
        $this->assertSame(1, $version->version_number);
        $this->assertSame($initialToken, $version->token);
        $this->assertSame($initialToken, $assignment->fresh()->public_token);
        $this->assertSame('Hall', $version->snapshot['places'][0]['name']);
    }

    public function test_photo_upload_form_is_available_after_saving_a_draft(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/uppdrag/nytt')
            ->assertOk()
            ->assertDontSee('name="before_photos[]"', false)
            ->assertDontSee('name="after_photos[]"', false);

        $this->actingAs($user)->post('/uppdrag', [
            'customer_name' => 'Erik Eriksson',
            'address' => 'Parkvägen 2, 111 22 Stockholm',
            'performed_on' => '2026-10-16',
        ])->assertRedirect();

        $assignment = Assignment::query()->sole();

        $this->actingAs($user)->get(route('assignments.show', $assignment, false))
            ->assertOk()
            ->assertSee('accept="image/*,.heic,.heif"', false)
            ->assertSee('class="photo-picker-input"', false)
            ->assertSee('class="photo-picker-trigger"', false)
            ->assertSee('capture', false)
            ->assertSee('multiple', false)
            ->assertSee('name="before_photos[]"', false)
            ->assertSee('name="after_photos[]"', false)
            ->assertSee('data-photo-picker', false)
            ->assertSee('data-photo-count', false)
            ->assertSee('data-photo-list', false);
    }

    public function test_job_and_report_list_before_photos_ahead_of_after_photos(): void
    {
        $user = User::factory()->create();
        $assignment = Assignment::factory()->for($user)->create();
        $assignment->photos()->create([
            'phase' => PhotoPhase::After,
            'path' => 'photos/efter.jpg',
            'sort_order' => 1,
        ]);
        $assignment->photos()->create([
            'phase' => PhotoPhase::Before,
            'path' => 'photos/fore.jpg',
            'sort_order' => 1,
        ]);

        $job = $this->actingAs($user)->get(route('assignments.show', $assignment))->assertOk()->getContent();
        $report = $this->get('/r/'.$assignment->public_token)->assertOk()->getContent();

        foreach ([$job, $report] as $html) {
            $before = strpos($html, '<figcaption>Före</figcaption>');
            $after = strpos($html, '<figcaption>Efter</figcaption>');
            $this->assertNotFalse($before);
            $this->assertNotFalse($after);
            $this->assertLessThan($after, $before);
        }
    }

    public function test_incomplete_job_is_rejected_in_swedish(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/uppdrag/nytt')->post('/uppdrag', [])
            ->assertRedirect('/uppdrag/nytt')
            ->assertSessionHasErrors(['customer_name', 'address', 'performed_on']);

        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_another_company_cannot_open_the_job(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create();

        $this->actingAs($other)->get(route('assignments.show', $assignment))->assertNotFound();
    }

    public function test_expired_trial_cannot_create_another_job(): void
    {
        $user = User::factory()->expiredTrial()->create();

        $this->actingAs($user)->from('/uppdrag/nytt')->post('/uppdrag', [
            'customer_name' => 'Anna Andersson',
            'address' => 'Storgatan 1, 111 22 Stockholm',
            'performed_on' => '2026-10-01',
        ])->assertRedirect('/uppdrag/nytt')
            ->assertSessionHasErrors('trial');

        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_owner_and_member_can_delete_a_draft_photo_without_rewriting_the_public_report(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $member = User::factory()->memberOf($owner->company)->create();
        $other = User::factory()->create();

        $this->actingAs($owner)->post('/uppdrag', [
            'customer_name' => 'Anna Andersson',
            'address' => 'Storgatan 1, 111 22 Stockholm',
            'performed_on' => '2026-10-01',
            'before_photos' => [UploadedFile::fake()->image('fore.jpg', 40, 40)],
            'after_photos' => [UploadedFile::fake()->image('efter.jpg', 40, 40)],
        ])->assertRedirect();

        $assignment = Assignment::query()->firstOrFail();
        $before = $assignment->photos()->where('phase', PhotoPhase::Before)->firstOrFail();
        $after = $assignment->photos()->where('phase', PhotoPhase::After)->firstOrFail();
        $reportAssets = $assignment->reportVersions()->sole()->photoAssets()->get()->keyBy('source_photo_id');
        Storage::disk('local')->assertExists($before->path);

        $this->post('/logout')->assertRedirect('/login');

        $this->delete(route('assignments.photos.destroy', [$assignment, $before]))
            ->assertRedirect(route('login'));
        $this->assertModelExists($before);

        $this->actingAs($member)->get(route('assignments.show', $assignment))
            ->assertOk()
            ->assertSee('Ta bort', false)
            ->assertSee('Vill du ta bort den här bilden?', false)
            ->assertSee('aria-label="Ta bort förebilden"', false)
            ->assertSee('aria-label="Ta bort efterbilden"', false);

        $this->get('/r/'.$assignment->public_token)
            ->assertOk()
            ->assertDontSee('Ta bort', false);

        $this->actingAs($other)->delete(route('assignments.photos.destroy', [$assignment, $before]))
            ->assertNotFound();
        $this->assertModelExists($before);

        $foreign = Assignment::factory()->for($owner)->create();
        $this->actingAs($member)->delete(route('assignments.photos.destroy', [$foreign, $before]))
            ->assertNotFound();
        $this->assertModelExists($before);

        $this->actingAs($member)->delete(route('assignments.photos.destroy', [$assignment, $before]))
            ->assertRedirect(route('assignments.show', $assignment))
            ->assertSessionHas('toast', 'Bilden är borttagen.');

        Storage::disk('local')->assertExists($before->path);
        $this->assertModelMissing($before);
        $this->assertModelExists($after);

        $this->actingAs($owner)->get(route('assignments.show', $assignment))
            ->assertOk()
            ->assertSee('Bilden är borttagen.', false)
            ->assertDontSee('/foton/'.$before->id, false)
            ->assertSee('Ta bort', false);

        $this->get('/r/'.$assignment->public_token)
            ->assertOk()
            ->assertSee('/version-foton/'.$reportAssets->get($before->id)->id, false)
            ->assertSee('/version-foton/'.$reportAssets->get($after->id)->id, false)
            ->assertDontSee('Ta bort', false);
    }
}
