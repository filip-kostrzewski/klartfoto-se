<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class AuthMailTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $logMessages = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'queue.default' => 'database',
            'mail.default' => 'array',
            'mail.from.address' => 'noreply@klartfoto.se',
            'mail.from.name' => 'Klartfoto',
        ]);
        Mail::purge();
    }

    public function test_invitation_mail_is_delivered_before_the_response(): void
    {
        $owner = User::factory()->create(['name' => 'Glans Städ AB']);

        $this->actingAs($owner)->post('/installningar/inbjudningar', [
            'email' => 'kim@exempel.se',
        ]);

        $this->assertMailLeftDuringTheRequest('kim@exempel.se', 'Inbjudan till Glans Städ AB på Klartfoto');
    }

    public function test_password_reset_mail_is_delivered_before_the_response(): void
    {
        User::factory()->create(['email' => 'anna@glans.se']);

        $this->post('/forgot-password', ['email' => 'anna@glans.se']);

        $this->assertMailLeftDuringTheRequest('anna@glans.se', 'Återställ ditt lösenord till Klartfoto');
    }

    public function test_verification_mail_is_delivered_before_the_response(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'anna@glans.se']);

        $this->actingAs($user)->post('/epost/skicka-igen');

        $this->assertMailLeftDuringTheRequest('anna@glans.se', 'Bekräfta din e-postadress');
    }

    public function test_a_failed_invitation_mail_is_logged_and_the_invitation_stays_saved(): void
    {
        $this->recordLogs();
        $this->failTheNextMail();
        $owner = User::factory()->create();

        $this->actingAs($owner)->from('/installningar')->post('/installningar/inbjudningar', [
            'email' => 'kim@exempel.se',
        ])->assertRedirect('/installningar')
            ->assertSessionHasErrors([
                'email' => 'Inbjudan är sparad, men mejlet till kim@exempel.se kunde inte skickas. Försök igen om en stund.',
            ]);

        $this->assertDatabaseHas('company_invitations', ['email' => 'kim@exempel.se']);
        $this->assertLogged('Kunde inte skicka inbjudan till kim@exempel.se. Postmark avvisade mejlet');
    }

    public function test_a_failed_password_reset_mail_is_logged_without_changing_the_reply(): void
    {
        $this->recordLogs();
        $this->failTheNextMail();
        User::factory()->create(['email' => 'anna@glans.se']);

        $this->post('/forgot-password', ['email' => 'anna@glans.se'])
            ->assertSessionHas('status', 'Om adressen finns hos oss har vi skickat en länk för att välja ett nytt lösenord.');

        $this->assertLogged('Kunde inte skicka lösenordsåterställning till anna@glans.se. Postmark avvisade mejlet');
    }

    public function test_a_failed_verification_mail_is_logged_and_registration_continues(): void
    {
        $this->recordLogs();
        $this->failTheNextMail();

        $this->post('/register', [
            'company_name' => 'Glans Städ AB',
            'name' => 'Anna Andersson',
            'email' => 'anna@glans.se',
            'password' => 'hemligt-losen',
            'password_confirmation' => 'hemligt-losen',
            'privacy' => '1',
        ])->assertRedirect(route('assignments.index'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'anna@glans.se']);
        $this->assertLogged('Kunde inte skicka e-postbekräftelse till anna@glans.se. Postmark avvisade mejlet');
    }

    private function assertMailLeftDuringTheRequest(string $recipient, string $subject): void
    {
        $this->assertDatabaseCount('jobs', 0);

        $transport = Mail::mailer()->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);

        $message = $transport->messages()->sole()->getOriginalMessage();
        $this->assertInstanceOf(Email::class, $message);
        $this->assertSame([$recipient], array_map(
            fn ($address): string => $address->getAddress(),
            $message->getTo(),
        ));
        $this->assertSame('noreply@klartfoto.se', $message->getFrom()[0]->getAddress());
        $this->assertSame('Klartfoto', $message->getFrom()[0]->getName());
        $this->assertSame($subject, $message->getSubject());
    }

    private function recordLogs(): void
    {
        Log::listen(function (MessageLogged $log): void {
            $this->logMessages[] = $log->message;
        });
    }

    private function failTheNextMail(): void
    {
        $this->app['events']->listen(NotificationSending::class, function (): void {
            throw new RuntimeException('Postmark avvisade mejlet');
        });
    }

    private function assertLogged(string $message): void
    {
        $this->assertStringContainsString($message, implode("\n", $this->logMessages));
    }
}
