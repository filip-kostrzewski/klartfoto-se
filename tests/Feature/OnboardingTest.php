<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\User;
use App\OnboardingChecklist;
use App\PhotoPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_checklist_sits_above_the_job_list_and_tracks_real_progress(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['name' => 'Anna Andersson']);

        $empty = $this->actingAs($owner)->get('/uppdrag')->assertOk();
        $empty->assertSee('Kom igång', false)
            ->assertSee('Skapa första uppdraget', false)
            ->assertSee('Ladda upp bilder', false)
            ->assertSee('Dela rapportlänken', false)
            ->assertSee('Bjud in en teammedlem', false)
            ->assertSee('href="/uppdrag/nytt"', false)
            ->assertSee('href="/installningar#medlemmar"', false)
            ->assertSee('Dölj', false)
            ->assertDontSee('>Klar<', false)
            ->assertDontSee('data-copy-report', false);
        $this->assertLessThan(
            strpos($empty->getContent(), 'class="dashboard-empty-state"'),
            strpos($empty->getContent(), 'Kom igång'),
        );

        $this->actingAs($owner)->post('/uppdrag', [
            'customer_name' => 'Anna Andersson',
            'address' => 'Storgatan 1, 111 22 Stockholm',
            'performed_on' => '2026-10-01',
        ])->assertRedirect();

        $assignment = Assignment::query()->firstOrFail();
        $withJob = $this->actingAs($owner)->get('/uppdrag')->assertOk();
        $withJob->assertSee('Skapa första uppdraget', false)
            ->assertSee('Klar', false)
            ->assertSee('href="'.route('assignments.show', $assignment, false).'"', false)
            ->assertSee('data-copy-report="'.route('reports.show', ['token' => $assignment->public_token]).'"', false)
            ->assertSee('name="checklist"', false);
        $this->assertLessThan(
            strpos($withJob->getContent(), 'class="job-list"'),
            strpos($withJob->getContent(), 'Kom igång'),
        );
        $this->assertLessThan(
            strpos($withJob->getContent(), 'class="job-list"'),
            strpos($withJob->getContent(), 'Nytt uppdrag'),
        );

        $this->actingAs($owner)->post(route('assignments.photos.store', $assignment), [
            'before_photos' => [UploadedFile::fake()->image('fore.jpg', 40, 40)],
        ])->assertRedirect(route('assignments.show', $assignment))
            ->assertSessionHas('toast', 'Bilderna är uppladdade.');

        $this->actingAs($owner)->get('/uppdrag')
            ->assertOk()
            ->assertSeeInOrder(['Ladda upp bilder', 'Klar'], false)
            ->assertSee('Dela rapportlänken', false);

        $this->actingAs($owner)->post(route('assignments.share', $assignment), [
            'checklist' => '1',
        ])->assertRedirect('/uppdrag')
            ->assertSessionHas('toast', 'Länken är kopierad.');
        $this->assertNotNull($assignment->fresh()->link_copied_at);

        $this->actingAs($owner)->get('/uppdrag')
            ->assertOk()
            ->assertSeeInOrder(['Dela rapportlänken', 'Klar'], false)
            ->assertSee('Bjud in en teammedlem', false);

        $owner->company->invitations()->create([
            'email' => 'kollega@glans.se',
            'token' => str_repeat('a', 48),
            'invited_by' => $owner->id,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($owner)->get('/uppdrag')
            ->assertOk()
            ->assertDontSee('<h2>Kom igång</h2>', false)
            ->assertDontSee('Dölj', false)
            ->assertDontSee('Visa Kom igång', false);
    }

    public function test_finishing_the_last_step_shows_every_step_done_before_the_list_hides(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->memberOf($owner->company)->create();
        $assignment = Assignment::factory()->for($owner)->create();
        $assignment->photos()->create([
            'phase' => PhotoPhase::Before,
            'path' => 'photos/fore.jpg',
            'sort_order' => 1,
        ]);
        $owner->company->invitations()->create([
            'email' => 'kollega@glans.se',
            'token' => str_repeat('b', 48),
            'invited_by' => $owner->id,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($owner)->from('/uppdrag')->post(route('assignments.share', $assignment), [
            'checklist' => '1',
        ])->assertRedirect('/uppdrag')
            ->assertSessionHas('toast', 'Klart! Du är igång.');

        $done = $this->actingAs($owner)->get('/uppdrag')->assertOk();
        $done->assertSee('Klart! Du är igång.', false)
            ->assertSee('data-onboarding-done', false)
            ->assertSee('data-onboarding-close', false)
            ->assertSeeInOrder(['Skapa första uppdraget', 'Klar'], false)
            ->assertSeeInOrder(['Ladda upp bilder', 'Klar'], false)
            ->assertSeeInOrder(['Dela rapportlänken', 'Klar'], false)
            ->assertSeeInOrder(['Bjud in en teammedlem', 'Klar'], false);

        $this->actingAs($member)->get('/uppdrag')
            ->assertOk()
            ->assertDontSee('<h2>Kom igång</h2>', false);

        $this->actingAs($owner)->get('/uppdrag')
            ->assertOk()
            ->assertDontSee('<h2>Kom igång</h2>', false)
            ->assertDontSee('Klart! Du är igång.', false);
    }

    public function test_an_invitation_that_finishes_the_checklist_returns_to_the_done_list(): void
    {
        $owner = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create();
        $assignment->photos()->create([
            'phase' => PhotoPhase::Before,
            'path' => 'photos/fore.jpg',
            'sort_order' => 1,
        ]);
        $assignment->forceFill(['link_copied_at' => now()])->save();

        $this->actingAs($owner)->post('/installningar/inbjudningar', [
            'email' => 'kollega@glans.se',
        ])->assertRedirect('/uppdrag')
            ->assertSessionHas('toast', 'Klart! Du är igång.');

        $this->assertDatabaseHas('company_invitations', ['email' => 'kollega@glans.se']);
    }

    public function test_the_share_step_copies_the_latest_report_link(): void
    {
        $owner = User::factory()->create();
        $older = Assignment::factory()->for($owner)->create([
            'customer_name' => 'Äldre kund',
            'performed_on' => '2026-10-01',
        ]);
        $newer = Assignment::factory()->for($owner)->create([
            'customer_name' => 'Senaste kund',
            'performed_on' => '2026-10-15',
        ]);

        $this->actingAs($owner)->get('/uppdrag')
            ->assertOk()
            ->assertSee('data-copy-report="'.route('reports.show', ['token' => $newer->public_token]).'"', false)
            ->assertDontSee(route('reports.show', ['token' => $older->public_token]), false);

        $this->actingAs($owner)->from('/uppdrag')->post(route('assignments.share', $newer), [
            'checklist' => '1',
        ])->assertRedirect('/uppdrag')
            ->assertSessionHas('toast', 'Länken är kopierad.');

        $this->assertNotNull($newer->fresh()->link_copied_at);
        $this->assertNull($older->fresh()->link_copied_at);

        $this->actingAs($owner)->get('/uppdrag')
            ->assertOk()
            ->assertSee('Länken är kopierad.', false)
            ->assertSeeInOrder(['Dela rapportlänken', 'Klar'], false);
    }

    public function test_viewing_a_report_does_not_finish_the_share_step(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->memberOf($owner->company)->create();
        $assignment = Assignment::factory()->for($owner)->create();
        $assignment->photos()->create([
            'phase' => PhotoPhase::Before,
            'path' => 'photos/fore.jpg',
            'sort_order' => 1,
        ]);

        $this->get('/r/'.$assignment->public_token)->assertOk();
        $this->assertNull($assignment->fresh()->link_copied_at);
        $this->assertNull($assignment->fresh()->report_shared_at);

        $this->actingAs($member)->get('/uppdrag')
            ->assertOk()
            ->assertSee('<h2>Kom igång</h2>', false)
            ->assertSee('data-copy-report="'.route('reports.show', ['token' => $assignment->public_token]).'"', false)
            ->assertDontSee('Visa Kom igång', false);
    }

    public function test_a_sent_report_finishes_the_share_step_without_marking_the_link_copied(): void
    {
        $owner = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create();
        $assignment->photos()->create([
            'phase' => PhotoPhase::Before,
            'path' => 'photos/fore.jpg',
            'sort_order' => 1,
        ]);
        $assignment->forceFill(['report_shared_at' => now()])->save();

        $steps = app(OnboardingChecklist::class)->steps(
            $owner,
            $owner->company,
            $owner->company->assignments()->withCount('photos')->get(),
        );

        $this->assertTrue($steps[2]['done']);
        $this->assertNotNull($assignment->fresh()->report_shared_at);
        $this->assertNull($assignment->fresh()->link_copied_at);
    }

    public function test_the_checklist_can_be_dismissed_before_every_step_is_done(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->memberOf($owner->company)->create();

        $this->actingAs($member)->get('/uppdrag')
            ->assertOk()
            ->assertSee('Bjud in en teammedlem', false)
            ->assertDontSee('href="/installningar#medlemmar"', false);

        $this->actingAs($owner)->post('/uppdrag/kom-igang/dolj')->assertRedirect('/uppdrag');
        $this->assertNotNull($owner->fresh()->onboarding_dismissed_at);
        $this->assertNull($member->fresh()->onboarding_dismissed_at);

        $this->actingAs($owner)->get('/uppdrag')
            ->assertOk()
            ->assertDontSee('<h2>Kom igång</h2>', false)
            ->assertSee('Visa Kom igång', false);

        $this->actingAs($member)->get('/uppdrag')
            ->assertOk()
            ->assertSee('<h2>Kom igång</h2>', false)
            ->assertDontSee('Visa Kom igång', false);

        $this->actingAs($owner)->post('/uppdrag/kom-igang/visa')->assertRedirect('/uppdrag');
        $this->assertNull($owner->fresh()->onboarding_dismissed_at);

        $this->actingAs($owner)->get('/uppdrag')
            ->assertOk()
            ->assertSee('<h2>Kom igång</h2>', false)
            ->assertDontSee('Visa Kom igång', false);
    }

    public function test_another_company_cannot_mark_a_report_as_shared(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create();

        $this->post(route('assignments.share', $assignment))->assertRedirect(route('login'));
        $this->actingAs($other)->post(route('assignments.share', $assignment))->assertNotFound();
        $this->assertNull($assignment->fresh()->link_copied_at);
    }

    public function test_register_page_asks_for_the_owners_name(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Ditt namn', false)
            ->assertSee('name="name"', false);
    }
}
