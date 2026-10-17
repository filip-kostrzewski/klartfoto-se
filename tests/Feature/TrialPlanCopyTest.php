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
            ->assertSee('Gratis till 15 november 2026. Inget kort behövs. Därefter har ni ingen aktiv åtkomst till nya uppdrag. Ni kan fortfarande öppna befintliga rapporter.', false);

        $this->actingAs($owner)->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('Under provperioden har ni allt i Team: upp till 10 användare och egen logotyp.', false)
            ->assertSee('Efter provperioden: välj Team för att behålla 1 användare och logotypen. Med Start kan bara 1 användare logga in och logotypen visas inte.', false)
            ->assertDontSee('Feedbackpilot – Team gratis till 31 december 2026', false)
            ->assertSee('Under provperioden har ni allt i Team, inklusive egen logotyp.', false);

        $this->actingAs($owner)->get(route('settings.billing'))
            ->assertOk()
            ->assertSee('Gratis till 15 november 2026. Inget kort behövs. Därefter har ni ingen aktiv åtkomst till nya uppdrag. Ni kan fortfarande öppna befintliga rapporter.', false);

        $owner->company->forceFill(['trial_ends_at' => now()->subDay()])->save();

        $this->actingAs($owner)->get(route('assignments.index'))
            ->assertOk()
            ->assertSee('Ingen aktiv åtkomst till nya uppdrag. Ni kan fortfarande öppna befintliga rapporter.', false)
            ->assertDontSee('Under provperioden har ni allt i Team', false);

        $assignment = Assignment::factory()->for($owner)->create();

        $this->actingAs($owner)->get(route('assignments.edit', $assignment))
            ->assertOk()
            ->assertSee('Åtkomsten är pausad. Ni kan fortfarande öppna befintliga rapporter.', false);
    }
}
