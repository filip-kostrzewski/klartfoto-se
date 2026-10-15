<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SwedishResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_trial_account_and_signs_the_company_in(): void
    {
        $this->travelTo('2026-10-15 12:00:00');

        $response = $this->post('/register', [
            'company_name' => 'Glans Städ AB',
            'email' => 'anna@glans.se',
            'password' => 'hemligt-losen',
            'password_confirmation' => 'hemligt-losen',
            'privacy' => '1',
        ]);

        $response->assertRedirect(route('assignments.index'));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'anna@glans.se')->first();
        $this->assertNotNull($user);
        $this->assertSame('Glans Städ AB', $user->name);
        $this->assertTrue(Hash::check('hemligt-losen', $user->password));
        $this->assertSame('2026-11-14 12:00:00', $user->trial_ends_at?->format('Y-m-d H:i:s'));
    }

    public function test_fixed_trial_end_overrides_the_default_number_of_days(): void
    {
        config(['klartfoto.trial_ends_at' => '2026-12-31']);

        $this->post('/register', [
            'company_name' => 'Glans Städ AB',
            'email' => 'anna@glans.se',
            'password' => 'hemligt-losen',
            'password_confirmation' => 'hemligt-losen',
            'privacy' => '1',
        ])->assertRedirect(route('assignments.index'));

        $user = User::query()->where('email', 'anna@glans.se')->first();

        $this->assertSame('2026-12-31 23:59:59', $user?->trial_ends_at?->format('Y-m-d H:i:s'));
    }

    public function test_registration_requires_a_company_password_and_privacy_consent(): void
    {
        $this->from('/register')->post('/register', [
            'company_name' => '',
            'email' => 'inte-en-adress',
            'password' => 'kort',
            'password_confirmation' => 'annat',
        ])->assertRedirect('/register')
            ->assertSessionHasErrors(['company_name', 'email', 'password', 'privacy']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_page_links_to_the_privacy_policy_and_hides_google_without_a_client_id(): void
    {
        config(['services.google.client_id' => null]);

        $this->get('/register')
            ->assertOk()
            ->assertSee('href="/integritetspolicy"', false)
            ->assertSee('Kom igång gratis', false)
            ->assertDontSee('Fortsätt med Google', false);
    }

    public function test_login_accepts_the_account_password_and_rejects_the_wrong_one(): void
    {
        $user = User::factory()->create([
            'email' => 'anna@glans.se',
            'password' => 'hemligt-losen',
        ]);

        $this->post('/login', [
            'email' => 'anna@glans.se',
            'password' => 'fel-losenord',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', [
            'email' => 'anna@glans.se',
            'password' => 'hemligt-losen',
        ])->assertRedirect(route('assignments.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_reset_mail_is_sent_only_when_the_address_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'anna@glans.se']);

        $this->post('/forgot-password', ['email' => 'anna@glans.se'])
            ->assertSessionHas('status', 'Om adressen finns hos oss har vi skickat en länk för att välja ett nytt lösenord.');
        $this->post('/forgot-password', ['email' => 'saknas@glans.se'])
            ->assertSessionHas('status', 'Om adressen finns hos oss har vi skickat en länk för att välja ett nytt lösenord.');

        Notification::assertSentTo($user, SwedishResetPassword::class);
        Notification::assertCount(1);
    }

    public function test_reset_link_stores_a_new_password(): void
    {
        $user = User::factory()->create(['email' => 'anna@glans.se']);
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'anna@glans.se',
            'password' => 'nytt-losenord',
            'password_confirmation' => 'nytt-losenord',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('nytt-losenord', $user->fresh()->password));
    }
}
