<?php

namespace Tests\Feature;

use App\CompanyRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_routes_explain_in_swedish_when_the_keys_are_missing(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
        ]);

        $this->get('/login')->assertOk()->assertDontSee('Fortsätt med Google', false);
        $this->get('/register')->assertOk()->assertDontSee('Fortsätt med Google', false);

        $this->get('/auth/google/redirect')->assertRedirect(route('login'));
        $this->get('/login')->assertSee('Google-inloggning är inte aktiverad. Logga in med e-post och lösenord.', false);

        $this->get('/auth/google/callback')->assertRedirect(route('login'));
    }

    public function test_google_button_stays_hidden_when_only_one_key_is_set(): void
    {
        config([
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => null,
        ]);

        $this->get('/login')->assertOk()->assertDontSee('Fortsätt med Google', false);
        $this->get('/auth/google/redirect')->assertRedirect(route('login'));
    }

    public function test_google_button_is_shown_when_both_keys_are_configured(): void
    {
        config([
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'secret',
        ]);

        $this->get('/login')->assertOk()->assertSee('Fortsätt med Google', false);
        $this->get('/register')->assertOk()
            ->assertSee('Fortsätt med Google', false)
            ->assertSee('href="/auth/google/redirect"', false);
    }

    public function test_google_callback_creates_a_trial_account(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        config([
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'secret',
        ]);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-user-1',
            'name' => 'Glans Städ AB',
            'email' => 'anna@glans.se',
        ]));

        $this->get('/auth/google/callback')->assertRedirect(route('assignments.index'));

        $user = User::query()->where('email', 'anna@glans.se')->first();
        $this->assertNotNull($user);
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-user-1', $user->google_id);
        $this->assertSame('Glans Städ AB', $user->name);
        $this->assertSame(CompanyRole::Owner, $user->role);
        $this->assertSame('Glans Städ AB', $user->company?->name);
        $this->assertSame('2026-11-14 12:00:00', $user->trial_ends_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-14 12:00:00', $user->company?->trial_ends_at?->format('Y-m-d H:i:s'));
    }

    public function test_google_callback_links_an_existing_account_without_resetting_the_trial(): void
    {
        config([
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'secret',
        ]);
        $existing = User::factory()->create([
            'email' => 'anna@glans.se',
            'name' => 'Glans Städ AB',
            'trial_ends_at' => '2026-01-15 08:00:00',
        ]);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-user-1',
            'name' => 'Annat namn',
            'email' => 'anna@glans.se',
        ]));

        $this->get('/auth/google/callback')->assertRedirect(route('assignments.index'));

        $existing->refresh();
        $this->assertAuthenticatedAs($existing);
        $this->assertSame('google-user-1', $existing->google_id);
        $this->assertSame('Glans Städ AB', $existing->name);
        $this->assertSame('2026-01-15 08:00:00', $existing->trial_ends_at?->format('Y-m-d H:i:s'));
        $this->assertDatabaseCount('users', 1);
    }
}
