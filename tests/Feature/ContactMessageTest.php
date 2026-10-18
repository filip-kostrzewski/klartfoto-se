<?php

namespace Tests\Feature;

use App\LocalizedUrl;
use App\Mail\ContactMessage;
use App\Models\SpamSubmission;
use App\Support\FormSpam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class ContactMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_message_is_sent_to_provider_with_fixed_sender_and_escaped_content(): void
    {
        config([
            'klartfoto.provider_contact_email' => 'kontakt@klartfoto.se',
            'mail.from.address' => 'noreply@klartfoto.se',
            'mail.from.name' => 'Klartfoto',
            'marketing.waitlist_enabled' => false,
        ]);
        Mail::fake();

        $this->post('/en/contact/message', [
            'name' => 'Taylor <script>alert(1)</script>',
            'email' => 'SENDER@example.com',
            'company' => 'Example Co',
            'message' => "Hello <script>alert('x')</script>\nThanks",
            'form_started_at' => $this->formStartedAt(),
        ])->assertRedirect('/en/contact#contact-form')
            ->assertSessionHas('contact_status', 'sent')
            ->assertSessionMissing('_old_input');

        Mail::assertSent(ContactMessage::class, function (ContactMessage $mail): bool {
            $envelope = $mail->envelope();

            $this->assertCount(1, $mail->to);
            $this->assertSame('kontakt@klartfoto.se', $mail->to[0]['address']);
            $this->assertTrue($mail->hasTo('kontakt@klartfoto.se'));
            $this->assertSame('noreply@klartfoto.se', $envelope->from?->address);
            $this->assertSame('Klartfoto', $envelope->from?->name);
            $this->assertSame('sender@example.com', $envelope->replyTo[0]->address);
            $this->assertSame('New contact message for Klartfoto', $envelope->subject);

            $html = $mail->render();
            $this->assertStringContainsString('&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;', $html);
            $this->assertStringNotContainsString('<script>alert(', $html);

            $text = view('emails.contact-message-text', [
                'name' => 'Taylor <script>alert(1)</script>',
                'email' => 'SENDER@example.com',
                'company' => 'Example Co',
                'messageBody' => "Hello <script>alert('x')</script>\nThanks",
            ])->render();
            $this->assertStringContainsString('Name: Taylor <script>alert(1)</script>', $text);
            $this->assertStringContainsString("Hello <script>alert('x')</script>", $text);
            $this->assertStringNotContainsString('&lt;script&gt;', $text);

            return true;
        });
        Mail::assertSentTimes(ContactMessage::class, 1);
    }

    public function test_failed_delivery_shows_retry_and_direct_email_without_logging_submitted_data(): void
    {
        config(['klartfoto.provider_contact_email' => 'kontakt@klartfoto.se']);
        Mail::shouldReceive('to')
            ->once()
            ->with('kontakt@klartfoto.se')
            ->andThrow(new RuntimeException('PRIVATE MESSAGE CONTENT'));
        Log::shouldReceive('warning')
            ->once()
            ->with('Contact message delivery failed.');

        $this->from('/kontakt')->post('/kontakt/meddelande', [
            'name' => 'Private Name',
            'email' => 'private@example.com',
            'company' => 'Private Company',
            'message' => 'PRIVATE MESSAGE CONTENT',
            'form_started_at' => $this->formStartedAt(),
        ])->assertRedirect('/kontakt#contact-form')
            ->assertSessionHas('contact_status', 'failed')
            ->assertSessionHas('_old_input.name', 'Private Name')
            ->assertSessionHas('_old_input.email', 'private@example.com')
            ->assertSessionHas('_old_input.message', 'PRIVATE MESSAGE CONTENT');

        $this->get('/kontakt')->assertOk()
            ->assertSee('Meddelandet kunde inte skickas', false)
            ->assertSee('mailto:kontakt@klartfoto.se', false)
            ->assertSee('value="Private Name"', false)
            ->assertSee('value="private@example.com"', false)
            ->assertSee('PRIVATE MESSAGE CONTENT', false);
    }

    public function test_invalid_array_fields_are_not_flashed_and_contact_page_still_renders(): void
    {
        $response = $this->followingRedirects()->post('/en/contact/message', [
            'name' => ['not-a-string'],
            'email' => ['not-a-string'],
            'company' => ['not-a-string'],
            'message' => ['not-a-string'],
            'form_started_at' => $this->formStartedAt(),
        ]);

        $response->assertOk()
            ->assertDontSee('not-a-string', false)
            ->assertSee('Please check the information in the form:', false)
            ->assertSee('Enter your name as text.', false)
            ->assertSee('Enter a valid email address.', false)
            ->assertSee('id="name" name="name" type="text" value=""', false)
            ->assertSee('id="email" name="email" type="email" value=""', false)
            ->assertSee('id="company" name="company" type="text" value=""', false)
            ->assertSee('<textarea id="message" name="message" maxlength="5000" required', false)
            ->assertSee('aria-invalid="true"', false);
    }

    public function test_required_name_validation_is_localized_for_every_contact_route(): void
    {
        $localizedRoutes = [
            ['locale' => 'sv', 'message' => 'Ange ditt namn.'],
            ['locale' => 'en', 'message' => 'Enter your name.'],
            ['locale' => 'pl', 'message' => 'Podaj imię i nazwisko.'],
            ['locale' => 'de', 'message' => 'Bitte geben Sie Ihren Namen ein.'],
            ['locale' => 'nb', 'message' => 'Oppgi navnet ditt.'],
            ['locale' => 'da', 'message' => 'Skriv dit navn.'],
            ['locale' => 'fi', 'message' => 'Anna nimesi.'],
        ];

        foreach ($localizedRoutes as $index => ['locale' => $locale, 'message' => $expectedError]) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($index + 40)])
                ->followingRedirects()
                ->post(LocalizedUrl::route('contact.message', $locale, false), [
                    'email' => 'owner@example.com',
                    'message' => 'A valid contact message',
                    'form_started_at' => $this->formStartedAt(),
                ])
                ->assertOk()
                ->assertSee($expectedError, false);
        }
    }

    public function test_honeypot_suppresses_delivery_and_uses_a_generic_success_response(): void
    {
        Mail::fake();

        $this->post('/kontakt/meddelande', [
            'name' => 'Automation',
            'email' => 'robot@example.com',
            'company' => '',
            'message' => 'Automated submission',
            'website' => 'https://spam.example',
        ])->assertRedirect('/kontakt#contact-form')
            ->assertSessionHas('contact_status', 'success');

        Mail::assertNothingOutgoing();
        $this->assertDatabaseCount('spam_submissions', 1);

        $submission = SpamSubmission::query()->sole();
        $this->assertSame('contact', $submission->form);
        $this->assertSame('honeypot', $submission->reason);
        $this->assertSame('https://spam.example', $submission->payload['website']);
    }

    public function test_contact_message_throttle_is_shared_across_locales_for_the_same_ip(): void
    {
        Mail::fake();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.30']);
        $startedAt = $this->formStartedAt();

        foreach ([
            ['/kontakt/meddelande', 'first@example.com'],
            ['/en/contact/message', 'second@example.com'],
            ['/pl/kontakt/wiadomosc', 'third@example.com'],
            ['/de/kontakt/nachricht', 'fourth@example.com'],
            ['/nb/kontakt/melding', 'fifth@example.com'],
        ] as [$path, $email]) {
            $this->post($path, [
                'name' => 'Test Sender',
                'email' => $email,
                'message' => 'A valid message',
                'form_started_at' => $startedAt,
            ])->assertRedirect();
        }

        $this->post('/da/kontakt/besked', [
            'name' => 'Test Sender',
            'email' => 'sixth@example.com',
            'message' => 'A valid message',
            'form_started_at' => $startedAt,
        ])->assertTooManyRequests();

        Mail::assertSentTimes(ContactMessage::class, 5);
    }

    private function formStartedAt(): string
    {
        return FormSpam::stamp(10);
    }
}
