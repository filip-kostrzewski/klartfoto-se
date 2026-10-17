<?php

namespace Tests\Feature;

use App\Actions\CreateAssignmentReportVersion;
use App\Models\Assignment;
use App\Models\User;
use App\PhotoPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_archived_job_stays_out_of_the_active_list_and_its_share_link_still_returns_200(): void
    {
        $owner = User::factory()->create();
        $owner->company->forceFill([
            'trial_ends_at' => now()->subDay(),
            'paid_until' => now()->subDay(),
        ])->save();
        $assignment = Assignment::factory()->for($owner)->create([
            'customer_name' => 'Archiv Kund',
            'address' => 'Arkivgatan 4',
        ]);
        $token = $assignment->public_token;
        $assignment->photos()->create([
            'phase' => PhotoPhase::Before,
            'path' => 'assignments/'.$assignment->id.'/before.jpg',
            'sort_order' => 1,
        ]);
        app(CreateAssignmentReportVersion::class)->create($assignment->fresh(), $owner, preserveCurrentToken: true);
        Assignment::factory()->for($owner)->create([
            'customer_name' => 'Aktiv Kund',
        ]);
        $notice = 'Rapportlänken fungerar fortfarande – kunden kan se bilderna. Radera uppdraget om länken ska sluta fungera.';

        $this->actingAs($owner)
            ->get(route('assignments.show', $assignment))
            ->assertOk()
            ->assertSee('Arkivera uppdraget?', false)
            ->assertSee('Archiv Kund', false)
            ->assertSee('Arkivgatan 4', false)
            ->assertSee($notice, false);

        $this->actingAs($owner)
            ->post(route('assignments.archive', $assignment))
            ->assertRedirect(route('assignments.show', $assignment))
            ->assertSessionHas('toast', 'Uppdraget är arkiverat.');

        $assignment->refresh();
        $this->assertNotNull($assignment->archived_at);
        $this->assertSame($token, $assignment->public_token);

        $this->get('/r/'.$token)
            ->assertOk()
            ->assertSee('Archiv Kund', false);

        $this->actingAs($owner)
            ->get(route('assignments.index'))
            ->assertOk()
            ->assertSee('Aktiv Kund', false)
            ->assertDontSee('Archiv Kund', false)
            ->assertViewHas('readyToSendCount', 0);

        $this->actingAs($owner)
            ->get(route('assignments.index', ['arkiv' => 'arkiverade']))
            ->assertOk()
            ->assertSee('Archiv Kund', false)
            ->assertSee($notice, false)
            ->assertDontSee('Aktiv Kund', false);

        $this->actingAs($owner)
            ->get(route('assignments.show', $assignment))
            ->assertOk()
            ->assertSee($notice, false)
            ->assertSee('Återställ', false);

        $this->actingAs($owner)
            ->post(route('assignments.restore', $assignment))
            ->assertRedirect(route('assignments.show', $assignment))
            ->assertSessionHas('toast', 'Uppdraget är återställt.');

        $this->assertNull($assignment->fresh()->archived_at);
        $this->actingAs($owner)
            ->get(route('assignments.index'))
            ->assertOk()
            ->assertSee('Archiv Kund', false);
        $this->get('/r/'.$token)->assertOk();
    }

    public function test_another_company_cannot_archive_or_restore_a_job(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create();

        $this->actingAs($other)
            ->post(route('assignments.archive', $assignment))
            ->assertNotFound();
        $this->assertNull($assignment->fresh()->archived_at);

        $assignment->forceFill(['archived_at' => now()])->save();

        $this->actingAs($other)
            ->post(route('assignments.restore', $assignment))
            ->assertNotFound();
        $this->assertNotNull($assignment->fresh()->archived_at);
    }

    public function test_member_without_job_access_cannot_archive_or_restore(): void
    {
        $owner = User::factory()->create();
        $owner->company->forceFill([
            'trial_ends_at' => now()->subDay(),
            'paid_until' => null,
        ])->save();
        $member = User::factory()->memberOf($owner->company)->create();
        $assignment = Assignment::factory()->for($owner)->create();

        $this->actingAs($member)
            ->post(route('assignments.archive', $assignment))
            ->assertForbidden();
        $this->assertNull($assignment->fresh()->archived_at);

        $assignment->forceFill(['archived_at' => now()])->save();

        $this->actingAs($member)
            ->get(route('assignments.show', $assignment))
            ->assertOk()
            ->assertSee('Rapportlänken fungerar fortfarande – kunden kan se bilderna. Radera uppdraget om länken ska sluta fungera.', false)
            ->assertDontSee('Återställ', false);

        $this->actingAs($member)
            ->post(route('assignments.restore', $assignment))
            ->assertForbidden();
        $this->assertNotNull($assignment->fresh()->archived_at);
    }

    public function test_member_who_can_manage_jobs_can_archive(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->memberOf($owner->company)->create();
        $assignment = Assignment::factory()->for($owner)->create([
            'customer_name' => 'Teamkund',
        ]);

        $this->actingAs($member)
            ->post(route('assignments.archive', $assignment))
            ->assertRedirect(route('assignments.show', $assignment));

        $this->assertNotNull($assignment->fresh()->archived_at);
        $this->actingAs($member)
            ->get(route('assignments.index', ['arkiv' => 'arkiverade']))
            ->assertOk()
            ->assertSee('Teamkund', false)
            ->assertSee('Rapportlänken fungerar fortfarande – kunden kan se bilderna. Radera uppdraget om länken ska sluta fungera.', false);
    }

    public function test_guest_archive_redirects_to_login(): void
    {
        $owner = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create();

        $this->post(route('assignments.archive', $assignment))
            ->assertRedirect(route('login'));
        $this->assertNull($assignment->fresh()->archived_at);
    }

    public function test_empty_archive_list_does_not_invite_a_first_job(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->get(route('assignments.index', ['arkiv' => 'arkiverade']))
            ->assertOk()
            ->assertSee('Inga arkiverade uppdrag', false)
            ->assertDontSee('Skapa ditt första uppdrag', false);
    }

    public function test_active_list_points_to_the_archive_when_every_job_is_archived(): void
    {
        $owner = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create([
            'customer_name' => 'Endast arkiv',
        ]);
        $assignment->forceFill(['archived_at' => now()])->save();

        $this->actingAs($owner)
            ->get(route('assignments.index'))
            ->assertOk()
            ->assertSee('Inga aktiva uppdrag', false)
            ->assertDontSee('Skapa ditt första uppdrag', false)
            ->assertDontSee('Endast arkiv', false);
    }

    public function test_unknown_archive_filter_is_rejected(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->from(route('assignments.index'))
            ->get(route('assignments.index', ['arkiv' => 'borttaget']))
            ->assertRedirect(route('assignments.index'))
            ->assertSessionHasErrors('arkiv');
    }
}
