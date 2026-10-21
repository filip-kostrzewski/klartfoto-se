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
    }
}
