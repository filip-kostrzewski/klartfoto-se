<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_start_screen_links_the_first_steps(): void
    {
        $user = User::factory()->create(['name' => 'Anna Andersson']);

        $this->actingAs($user)->get('/uppdrag')
            ->assertOk()
            ->assertSee('Kom igång', false)
            ->assertSee('href="/uppdrag/nytt"', false)
            ->assertSee('Skapa första uppdraget', false)
            ->assertSee('href="/installningar#logotyp"', false)
            ->assertSee('Ladda upp logotyp', false)
            ->assertSee('href="/installningar#medlemmar"', false)
            ->assertSee('Bjud in teamet', false);

        $this->actingAs($user)->get('/installningar')
            ->assertOk()
            ->assertSee('Anna Andersson', false)
            ->assertSee('Ägare', false);
    }

    public function test_checklist_steps_can_be_completed_and_hide_after_the_first_job(): void
    {
        $user = User::factory()->create();
        $company = $user->company;
        $this->assertNotNull($company);
        $company->forceFill(['logo_path' => 'companies/1/logo.png'])->save();
        $company->invitations()->create([
            'email' => 'kollega@glans.se',
            'token' => str_repeat('a', 48),
            'invited_by' => $user->id,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($user)->get('/uppdrag')
            ->assertOk()
            ->assertSee('Klar', false);

        Assignment::factory()->for($user)->create();

        $this->actingAs($user)->get('/uppdrag')
            ->assertOk()
            ->assertDontSee('Kom igång', false)
            ->assertDontSee('Skapa första uppdraget', false);
    }

    public function test_register_page_asks_for_the_owners_name(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Ditt namn', false)
            ->assertSee('name="name"', false);
    }
}
