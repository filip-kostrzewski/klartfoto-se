<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialPlanCopyTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_copy_says_what_happens_next_and_that_team_features_are_included(): void
    {
        $this->travelTo('2026-10-16 12:00:00');
        $owner = User::factory()->create();
        $owner->company->forceFill([
            'trial_ends_at' => '2026-11-15 23:59:59',
            'paid_until' => null,
        ])->save();

        $this->actingAs($owner)->get(route('assignments.index'))
            ->assertOk()
            ->assertSee('Gratis till 15 november 2026. Inget kort behövs. <a href="/installningar/abonnemang">Välj plan</a> innan dess så fortsätter du utan avbrott.', false);

        $this->actingAs($owner)->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('Under provperioden har du allt i Team: upp till 10 användare och egen logotyp.', false)
            ->assertSee('Efter provperioden: välj Team för att behålla 1 användare och logotypen. Med Start kan bara 1 användare logga in och logotypen visas inte.', false)
            ->assertSee('Under provperioden har du allt i Team, inklusive egen logotyp.', false);

        $this->actingAs($owner)->get(route('settings.billing'))
            ->assertOk()
            ->assertSee('Gratis till 15 november 2026. Inget kort behövs. Välj plan innan dess så fortsätter du utan avbrott.', false)
            ->assertSee('390 kr/mån', false)
            ->assertSee('790 kr/mån', false)
            ->assertSee('eller 7 900 kr/år', false)
            ->assertSee('name="interval" value="year"', false)
            ->assertSee('value="team" required checked', false);

        $owner->company->forceFill(['trial_ends_at' => now()->subDay()])->save();

        $this->actingAs($owner)->get(route('assignments.index'))
            ->assertOk()
            ->assertSee('Ingen aktiv åtkomst till nya uppdrag. Du kan fortfarande öppna befintliga rapporter. <a href="/installningar/abonnemang">Aktivera en plan</a>', false)
            ->assertDontSee('Under provperioden har du allt i Team', false);

        $assignment = Assignment::factory()->for($owner)->create();

        $this->actingAs($owner)->get(route('assignments.edit', $assignment))
            ->assertOk()
            ->assertSee('Åtkomsten är pausad. Du kan fortfarande öppna befintliga rapporter.', false);
    }
}
