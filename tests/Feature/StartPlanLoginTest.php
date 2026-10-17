<?php

namespace Tests\Feature;

use App\CompanyPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StartPlanLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_owner_can_stay_signed_in_on_start_and_the_logo_file_remains(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->memberOf($owner->company)->create();
        $owner->company->forceFill([
            'plan' => CompanyPlan::Start,
            'trial_ends_at' => now()->subDay(),
            'paid_until' => now()->addMonth(),
            'logo_path' => 'companies/logo-stays.svg',
        ])->save();

        $this->actingAs($member)->get(route('assignments.index'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'email' => 'Med Start kan bara 1 användare logga in. Befintliga rapporter kan öppnas av kontoägaren.',
            ]);
        $this->assertGuest();
        $this->assertModelExists($member);
        $this->assertSame('companies/logo-stays.svg', $owner->company->fresh()->logo_path);

        $this->actingAs($owner)->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('Efter provperioden: välj Team för att behålla 2 användare och logotypen. Med Start kan bara 1 användare logga in och logotypen visas inte.', false)
            ->assertSee('Egen logotyp ingår i Team.', false)
            ->assertDontSee('companies/logo-stays.svg', false);
    }

    public function test_a_team_member_can_sign_in_during_the_trial_and_on_a_paid_team_plan(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->memberOf($owner->company)->create();

        $this->actingAs($member)->get(route('assignments.index'))->assertOk();

        $owner->company->forceFill([
            'plan' => CompanyPlan::Team,
            'trial_ends_at' => now()->subDay(),
            'paid_until' => now()->addMonth(),
        ])->save();

        $this->actingAs($member)->get(route('assignments.index'))->assertOk();
    }
}
