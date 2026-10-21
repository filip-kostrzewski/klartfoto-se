<?php

namespace Tests\Feature;

use App\CompanyPlan;
use App\Mail\NewAccountConfirmed;
use App\Models\Company;
use App\Models\User;
use App\Notifications\SwedishVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use RuntimeException;
use Tests\TestCase;

class NewAccountNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['klartfoto.provider_contact_email' => 'kontakt@klartfoto.se']);
    }

    public function test_registration_alone_does_not_notify_and_confirming_the_address_does(): void
    {
        $this->travelTo('2026-10-21 12:05:00');
        Mail::fake();
        Notification::fake();

        $this->post('/register', [
            'company_name' => 'Glans Städ AB',
            'name' => 'Anna Andersson',
            'email' => 'anna@glans.se',
            'password' => 'hemligt-losen',
            'password_confirmation' => 'hemligt-losen',
            'privacy' => '1',
        ])->assertRedirect(route('assignments.index'));

        Mail::assertNotSent(NewAccountConfirmed::class);

        $user = User::query()->where('email', 'anna@glans.se')->sole();
        $this->travelTo('2026-10-21 12:30:00');

        $this->get((new SwedishVerifyEmail)->toMail($user)->actionUrl)
            ->assertRedirect(route('assignments.index'))
            ->assertSessionHas('status', 'E-postadressen är bekräftad.');

        Mail::assertSent(NewAccountConfirmed::class, 1);
        Mail::assertSent(NewAccountConfirmed::class, function (NewAccountConfirmed $mail): bool {
            $mail->assertSeeInHtml('Företag:</strong> Glans Städ AB', false)
                ->assertSeeInHtml('Namn:</strong> Anna Andersson', false)
                ->assertSeeInHtml('E-post:</strong> anna@glans.se', false)
                ->assertSeeInHtml('Registrerad:</strong> 2026-10-21 14:05', false)
                ->assertDontSeeInHtml('Plan:')
                ->assertSeeInText('Företag: Glans Städ AB')
                ->assertSeeInText('Registrerad: 2026-10-21 14:05')
                ->assertHasSubject('Nytt bekräftat konto på Klartfoto')
                ->assertHasReplyTo('anna@glans.se');

            return $mail->hasTo('kontakt@klartfoto.se');
        });
    }

    public function test_opening_the_link_again_does_not_send_a_second_notification(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();
        $url = (new SwedishVerifyEmail)->toMail($user)->actionUrl;

        $this->get($url)->assertRedirect(route('login'));
        $this->get($url)->assertRedirect(route('login'));

        Mail::assertSent(NewAccountConfirmed::class, 1);
    }

    public function test_chosen_plan_is_included(): void
    {
        Mail::fake();
        $company = Company::factory()->create([
            'requested_plan' => CompanyPlan::Team->value,
            'requested_at' => now(),
        ]);
        $user = User::factory()->unverified()->create(['company_id' => $company->id]);

        $this->get((new SwedishVerifyEmail)->toMail($user)->actionUrl)->assertRedirect(route('login'));

        Mail::assertSent(NewAccountConfirmed::class, function (NewAccountConfirmed $mail): bool {
            $mail->assertSeeInHtml('Plan:</strong> Team', false);

            return true;
        });
    }

    public function test_team_member_confirming_a_changed_address_is_not_reported_as_a_new_account(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $member = User::factory()->unverified()->memberOf($owner->company)->create();

        $this->get((new SwedishVerifyEmail)->toMail($member)->actionUrl)->assertRedirect(route('login'));

        $this->assertNotNull($member->refresh()->email_verified_at);
        Mail::assertNothingOutgoing();
    }

    public function test_missing_recipient_skips_the_notification(): void
    {
        config(['klartfoto.provider_contact_email' => '']);
        Mail::fake();
        $user = User::factory()->unverified()->create();

        $this->get((new SwedishVerifyEmail)->toMail($user)->actionUrl)
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'E-postadressen är bekräftad.');

        $this->assertNotNull($user->refresh()->email_verified_at);
        Mail::assertNothingOutgoing();
    }

    public function test_mail_failure_does_not_break_the_confirmation(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP is down'));
        $user = User::factory()->unverified()->create();

        $this->get((new SwedishVerifyEmail)->toMail($user)->actionUrl)
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'E-postadressen är bekräftad.');

        $this->assertNotNull($user->refresh()->email_verified_at);
        $this->assertNull($user->company->refresh()->new_account_notified_at);
    }

    public function test_confirming_a_changed_address_does_not_notify_the_same_company_again(): void
    {
        Mail::fake();
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'anna@glans.se']);

        $this->get((new SwedishVerifyEmail)->toMail($user)->actionUrl)->assertRedirect(route('login'));
        $this->assertNotNull($user->company->refresh()->new_account_notified_at);
        $user->refresh();

        $this->actingAs($user)->from('/uppdrag')->put('/epost', [
            'account_email' => 'ny@glans.se',
        ])->assertRedirect('/uppdrag');
        $user->refresh();
        $this->assertNull($user->email_verified_at);

        $this->get((new SwedishVerifyEmail)->toMail($user)->actionUrl)->assertRedirect(route('assignments.index'));

        $this->assertNotNull($user->refresh()->email_verified_at);
        Mail::assertSent(NewAccountConfirmed::class, 1);
    }

    public function test_google_sign_up_of_a_new_owner_notifies_once(): void
    {
        $this->travelTo('2026-10-21 12:05:00');
        Mail::fake();
        config([
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'secret',
        ]);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-user-1',
            'name' => 'Anna Andersson',
            'email' => 'anna@glans.se',
            'email_verified' => true,
            'hd' => 'glans.se',
        ]));

        $this->get('/auth/google/callback')->assertRedirect(route('assignments.index'));

        Mail::assertSent(NewAccountConfirmed::class, function (NewAccountConfirmed $mail): bool {
            $mail->assertSeeInHtml('Företag:</strong> Anna Andersson', false)
                ->assertSeeInHtml('E-post:</strong> anna@glans.se', false)
                ->assertSeeInHtml('Registrerad:</strong> 2026-10-21 14:05', false);

            return $mail->hasTo('kontakt@klartfoto.se');
        });

        $this->post('/logout');
        $this->get('/auth/google/callback')->assertRedirect(route('assignments.index'));

        Mail::assertSent(NewAccountConfirmed::class, 1);
    }

    public function test_google_sign_up_without_a_verified_email_waits_for_the_confirmation_link(): void
    {
        Mail::fake();
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

        $this->get('/auth/google/callback')->assertRedirect(route('assignments.index'));

        Mail::assertNotSent(NewAccountConfirmed::class);
    }

    public function test_migration_backfills_companies_whose_owner_is_already_verified(): void
    {
        $migration = require database_path('migrations/2026_10_21_131622_add_new_account_notified_at_to_companies_table.php');
        $migration->down();

        $verifiedOwner = User::factory()->create();
        $unverifiedOwner = User::factory()->unverified()->create();
        $memberOnly = Company::factory()->create();
        User::factory()->memberOf($memberOnly)->create();

        $migration->up();

        $this->assertNotNull($verifiedOwner->company->refresh()->new_account_notified_at);
        $this->assertNull($unverifiedOwner->company->refresh()->new_account_notified_at);
        $this->assertNull($memberOnly->refresh()->new_account_notified_at);
    }
}
