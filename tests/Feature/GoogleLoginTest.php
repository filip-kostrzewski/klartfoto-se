<?php

namespace Tests\Feature;

use App\CompanyRole;
use App\Models\User;
use App\Notifications\SwedishVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
            'email_verified' => true,
            'hd' => 'glans.se',
        ]));

        $this->get('/auth/google/callback')
            ->assertRedirect(route('assignments.index'))
            ->assertSessionHas('toast', 'Välkommen! Ditt konto är skapat.');

        $user = User::query()->where('email', 'anna@glans.se')->first();
        $this->assertNotNull($user);
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-user-1', $user->google_id);
        $this->assertSame('Glans Städ AB', $user->name);
        $this->assertSame(CompanyRole::Owner, $user->role);
        $this->assertSame('Glans Städ AB', $user->company?->name);
        $this->assertSame('2026-10-29 23:59:59', $user->trial_ends_at?->timezone('Europe/Stockholm')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-29 23:59:59', $user->company?->trial_ends_at?->timezone('Europe/Stockholm')->format('Y-m-d H:i:s'));
    }

    public function test_google_callback_links_an_existing_account_without_resetting_the_trial(): void
    {
        config([
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'secret',
        ]);
        $existing = User::factory()->unverified()->create([
            'email' => 'anna@gmail.com',
            'name' => 'Glans Städ AB',
            'trial_ends_at' => '2026-01-15 08:00:00',
        ]);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-user-1',
            'name' => 'Annat namn',
            'email' => 'anna@gmail.com',
            'email_verified' => true,
        ]));

        $this->get('/auth/google/callback')
            ->assertRedirect(route('assignments.index'))
            ->assertSessionMissing('toast');

        $existing->refresh();
        $this->assertAuthenticatedAs($existing);
        $this->assertSame('google-user-1', $existing->google_id);
        $this->assertSame('Glans Städ AB', $existing->name);
        $this->assertSame('2026-01-15 08:00:00', $existing->trial_ends_at?->format('Y-m-d H:i:s'));
        $this->assertNotNull($existing->email_verified_at);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_callback_does_not_link_an_existing_account_without_authoritative_email(): void
    {
        config([
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'secret',
        ]);
        $existing = User::factory()->unverified()->create(['email' => 'anna@example.org']);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-user-1',
            'email' => 'anna@example.org',
            'email_verified' => true,
        ]));

        $this->get('/auth/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertNull($existing->fresh()->google_id);
        $this->assertNull($existing->fresh()->email_verified_at);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_callback_does_not_replace_an_existing_google_link(): void
    {
        config([
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'secret',
        ]);
        $existing = User::factory()->create([
            'email' => 'anna@glans.se',
            'google_id' => 'google-user-original',
        ]);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-user-other',
            'email' => 'anna@glans.se',
            'email_verified' => true,
            'hd' => 'glans.se',
        ]));

        $this->get('/auth/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame('google-user-original', $existing->fresh()->google_id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_callback_does_not_verify_a_linked_account_after_its_google_email_changes(): void
    {
        config([
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'secret',
        ]);
        $existing = User::factory()->unverified()->create([
            'email' => 'original@glans.se',
            'google_id' => 'google-user-1',
        ]);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-user-1',
            'email' => 'changed@glans.se',
            'email_verified' => true,
            'hd' => 'glans.se',
        ]));

        $this->get('/auth/google/callback')->assertRedirect(route('assignments.index'));

        $this->assertAuthenticatedAs($existing);
        $this->assertSame('original@glans.se', $existing->fresh()->email);
        $this->assertNull($existing->fresh()->email_verified_at);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_callback_sends_email_verification_for_a_new_non_authoritative_email(): void
    {
        Notification::fake();
        config([
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'secret',
        ]);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-user-1',
            'name' => 'Anna Andersson',
            'email' => 'anna@example.org',
            'email_verified' => false,
        ]));

        $this->get('/auth/google/callback')
            ->assertRedirect(route('assignments.index'))
            ->assertSessionHas('toast', 'Välkommen! Ditt konto är skapat.');

        $user = User::query()->where('email', 'anna@example.org')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, SwedishVerifyEmail::class);
        $this->assertDatabaseCount('users', 1);
    }
}
