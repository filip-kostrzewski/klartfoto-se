<?php

namespace Tests\Feature;

use App\Mail\ContactMessage;
use App\Models\SpamSubmission;
use App\Support\FormSpam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContactSpamProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'klartfoto.provider_contact_email' => 'kontakt@klartfoto.se',
        ]);
    }

    public function test_submission_faster_than_three_seconds_is_stored_as_spam_and_looks_successful(): void
    {
        $this->freezeTime();
        Mail::fake();
        Http::preventStrayRequests();

        $this->postContact([
            'form_started_at' => FormSpam::stamp(2),
        ])->assertRedirect('/kontakt#contact-form')
            ->assertSessionHas('contact_status', 'success');

        Mail::assertNothingOutgoing();
        $this->assertDatabaseHas('spam_submissions', [
            'form' => 'contact',
            'reason' => 'too_fast',
            'ip' => '192.0.2.20',
        ]);
    }

    public function test_missing_timestamp_is_stored_as_spam_and_looks_successful(): void
    {
        Mail::fake();
        Http::preventStrayRequests();

        $this->postContact(withStamp: false)
            ->assertRedirect('/kontakt#contact-form')
            ->assertSessionHas('contact_status', 'success');

        Mail::assertNothingOutgoing();
        $this->assertDatabaseHas('spam_submissions', [
            'form' => 'contact',
            'reason' => 'too_fast',
        ]);
    }

    public function test_invalid_timestamp_is_stored_as_spam_and_looks_successful(): void
    {
        Mail::fake();
        Http::preventStrayRequests();

        $this->postContact([
            'form_started_at' => 'not-a-valid-stamp',
        ])->assertRedirect('/kontakt#contact-form')
            ->assertSessionHas('contact_status', 'success');

        Mail::assertNothingOutgoing();
        $this->assertDatabaseHas('spam_submissions', [
            'form' => 'contact',
            'reason' => 'too_fast',
        ]);
    }

    public function test_future_timestamp_is_stored_as_spam_and_looks_successful(): void
    {
        $this->freezeTime();
        Mail::fake();
        Http::preventStrayRequests();

        $this->postContact([
            'form_started_at' => FormSpam::stamp(-30),
        ])->assertRedirect('/kontakt#contact-form')
            ->assertSessionHas('contact_status', 'success');

        Mail::assertNothingOutgoing();
        $this->assertDatabaseHas('spam_submissions', [
            'form' => 'contact',
            'reason' => 'too_fast',
        ]);
    }

    public function test_submission_after_three_seconds_is_delivered(): void
    {
        $this->freezeTime();
        Mail::fake();
        Http::preventStrayRequests();

        $this->postContact([
            'form_started_at' => FormSpam::stamp(3),
        ])->assertRedirect('/kontakt#contact-form')
            ->assertSessionHas('contact_status', 'sent');

        Mail::assertSent(ContactMessage::class);
        $this->assertDatabaseCount('spam_submissions', 0);
    }

    public function test_validation_failure_keeps_the_original_fill_time(): void
    {
        $this->freezeTime();
        $stamp = FormSpam::stamp(10);

        $this->from('/kontakt')
            ->followingRedirects()
            ->post('/kontakt/meddelande', [
                'name' => '',
                'email' => 'anna@example.com',
                'message' => 'Vi vill se hur rapporten fungerar.',
                'form_started_at' => $stamp,
            ])
            ->assertSee('name="form_started_at"', false)
            ->assertSee('value="'.$stamp.'"', false);
    }

    #[DataProvider('gibberishFields')]
    public function test_gibberish_name_or_message_is_stored_and_looks_successful(string $field, string $value): void
    {
        Mail::fake();
        Http::preventStrayRequests();

        $this->postContact([$field => $value])
            ->assertRedirect('/kontakt#contact-form')
            ->assertSessionHas('contact_status', 'success');

        Mail::assertNothingOutgoing();

        $submission = SpamSubmission::query()->sole();
        $this->assertSame('contact', $submission->form);
        $this->assertSame('gibberish', $submission->reason);
        $this->assertSame($value, $submission->payload[$field]);
        $this->assertArrayNotHasKey('form_started_at', $submission->payload);
        $this->assertArrayNotHasKey('cf-turnstile-response', $submission->payload);
    }

    #[DataProvider('ordinaryFields')]
    public function test_ordinary_name_message_and_company_are_delivered(string $field, string $value): void
    {
        Mail::fake();
        Http::preventStrayRequests();

        $this->postContact([$field => $value])
            ->assertRedirect('/kontakt#contact-form')
            ->assertSessionHas('contact_status', 'sent');

        Mail::assertSent(ContactMessage::class);
        $this->assertDatabaseCount('spam_submissions', 0);
    }

    public function test_known_spam_pattern_is_stored_and_looks_successful(): void
    {
        Mail::fake();
        Http::preventStrayRequests();

        $this->postContact([
            'name' => 'NARYTHY1364171NERTHRTYHR',
            'email' => 'bot@notlettersmail.com',
            'company' => 'google',
            'message' => 'MERTYHR1364171MAMYJRTH',
        ])->assertRedirect('/kontakt#contact-form')
            ->assertSessionHas('contact_status', 'success');

        Mail::assertNothingOutgoing();

        $submission = SpamSubmission::query()->sole();
        $this->assertSame('blocked_email_domain', $submission->reason);
        $this->assertSame('google', $submission->payload['company']);
        $this->assertSame('bot@notlettersmail.com', $submission->payload['email']);
    }

    #[DataProvider('blockedEmails')]
    public function test_blocked_email_domain_is_stored_and_looks_successful(string $email): void
    {
        Mail::fake();
        Http::preventStrayRequests();

        $this->postContact(['email' => $email])
            ->assertRedirect('/kontakt#contact-form')
            ->assertSessionHas('contact_status', 'success');

        Mail::assertNothingOutgoing();
        $this->assertDatabaseHas('spam_submissions', [
            'form' => 'contact',
            'reason' => 'blocked_email_domain',
        ]);
    }

    #[DataProvider('allowedEmails')]
    public function test_ordinary_email_domain_is_delivered(string $email): void
    {
        Mail::fake();
        Http::preventStrayRequests();

        $this->postContact(['email' => $email])
            ->assertRedirect('/kontakt#contact-form')
            ->assertSessionHas('contact_status', 'sent');

        Mail::assertSent(ContactMessage::class);
        $this->assertDatabaseCount('spam_submissions', 0);
    }

    public function test_custom_blocklist_rejects_a_matching_domain(): void
    {
        config(['spam.blocked_email_domains' => ['*blocked.test']]);
        Mail::fake();
        Http::preventStrayRequests();

        $this->postContact(['email' => 'bot@spamblocked.test'])
            ->assertRedirect('/kontakt#contact-form')
            ->assertSessionHas('contact_status', 'success');

        Mail::assertNothingOutgoing();
        $this->assertDatabaseHas('spam_submissions', [
            'reason' => 'blocked_email_domain',
        ]);
    }

    public function test_custom_blocklist_allows_domains_outside_the_configured_pattern(): void
    {
        config(['spam.blocked_email_domains' => ['*blocked.test']]);
        Mail::fake();
        Http::preventStrayRequests();

        $this->postContact(['email' => 'bot@notlettersmail.com'])
            ->assertRedirect('/kontakt#contact-form')
            ->assertSessionHas('contact_status', 'sent');

        Mail::assertSent(ContactMessage::class);
        $this->assertDatabaseCount('spam_submissions', 0);
    }

    public function test_stored_payload_truncates_long_fields(): void
    {
        Mail::fake();
        Http::preventStrayRequests();
        $message = str_repeat('BCDFGHJKLMNP', 500);

        $this->postContact([
            'message' => $message,
            'cf-turnstile-response' => 'token-should-not-be-stored',
        ])->assertSessionHas('contact_status', 'success');

        $submission = SpamSubmission::query()->sole();
        $this->assertSame(5000, mb_strlen($submission->payload['message']));
        $this->assertArrayNotHasKey('cf-turnstile-response', $submission->payload);
    }

    public function test_rejected_submission_is_logged_without_the_message_body(): void
    {
        Mail::fake();
        Http::preventStrayRequests();
        $logged = null;
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
            if ($event->message === 'Spam form submission rejected.') {
                $logged = $event->context;
            }
        });

        $this->postContact([
            'name' => 'NARYTHY1364171NERTHRTYHR',
            'message' => 'MERTYHR1364171MAMYJRTH',
        ]);

        $this->assertSame([
            'form' => 'contact',
            'reason' => 'gibberish',
            'ip' => '192.0.2.20',
        ], $logged);
    }

    public function test_contact_page_renders_turnstile_when_both_keys_are_set(): void
    {
        config([
            'services.turnstile.site_key' => 'site-key-test',
            'services.turnstile.secret_key' => 'secret-key-test',
        ]);

        $this->get('/kontakt')
            ->assertSee('class="cf-turnstile contact-turnstile"', false)
            ->assertSee('data-sitekey="site-key-test"', false)
            ->assertSee('https://challenges.cloudflare.com/turnstile/v0/api.js', false)
            ->assertSee('name="form_started_at"', false)
            ->assertDontSee('secret-key-test', false);
    }

    #[DataProvider('incompleteTurnstileKeys')]
    public function test_contact_page_omits_turnstile_when_a_key_is_missing(?string $siteKey, ?string $secretKey): void
    {
        config([
            'services.turnstile.site_key' => $siteKey,
            'services.turnstile.secret_key' => $secretKey,
        ]);

        $this->get('/kontakt')
            ->assertSee('name="form_started_at"', false)
            ->assertDontSee('cf-turnstile', false)
            ->assertDontSee('challenges.cloudflare.com', false);
    }

    #[DataProvider('incompleteTurnstileKeys')]
    public function test_submission_is_delivered_when_turnstile_is_not_fully_configured(?string $siteKey, ?string $secretKey): void
    {
        config([
            'services.turnstile.site_key' => $siteKey,
            'services.turnstile.secret_key' => $secretKey,
        ]);
        Mail::fake();
        Http::preventStrayRequests();

        $this->postContact()
            ->assertRedirect('/kontakt#contact-form')
            ->assertSessionHas('contact_status', 'sent');

        Mail::assertSent(ContactMessage::class);
        $this->assertDatabaseCount('spam_submissions', 0);
    }

    public function test_valid_turnstile_token_is_verified_and_delivered(): void
    {
        config([
            'services.turnstile.site_key' => 'site-key-test',
            'services.turnstile.secret_key' => 'secret-key-test',
        ]);
        Mail::fake();
        Http::preventStrayRequests();
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true]),
        ]);

        $this->postContact([
            'cf-turnstile-response' => 'token-ok',
        ])->assertRedirect('/kontakt#contact-form')
            ->assertSessionHas('contact_status', 'sent');

        Mail::assertSent(ContactMessage::class);
        $this->assertDatabaseCount('spam_submissions', 0);
        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
                && ($data['secret'] ?? null) === 'secret-key-test'
                && ($data['response'] ?? null) === 'token-ok'
                && ($data['remoteip'] ?? null) === '192.0.2.20';
        });
    }

    public function test_failed_turnstile_verification_is_stored_and_looks_successful(): void
    {
        config([
            'services.turnstile.site_key' => 'site-key-test',
            'services.turnstile.secret_key' => 'secret-key-test',
        ]);
        Mail::fake();
        Http::preventStrayRequests();
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => false]),
        ]);

        $this->postContact([
            'cf-turnstile-response' => 'token-bad',
        ])->assertRedirect('/kontakt#contact-form')
            ->assertSessionHas('contact_status', 'success');

        Mail::assertNothingOutgoing();
        $this->assertDatabaseHas('spam_submissions', [
            'form' => 'contact',
            'reason' => 'turnstile',
        ]);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        });
    }

    public function test_turnstile_connection_failure_is_stored_and_looks_successful(): void
    {
        config([
            'services.turnstile.site_key' => 'site-key-test',
            'services.turnstile.secret_key' => 'secret-key-test',
        ]);
        Mail::fake();
        Http::preventStrayRequests();
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::failedConnection(),
        ]);

        $this->postContact([
            'cf-turnstile-response' => 'token-ok',
        ])->assertRedirect('/kontakt#contact-form')
            ->assertSessionHas('contact_status', 'success');

        Mail::assertNothingOutgoing();
        $this->assertDatabaseHas('spam_submissions', [
            'form' => 'contact',
            'reason' => 'turnstile',
        ]);
    }

    public function test_missing_turnstile_token_is_stored_without_calling_siteverify(): void
    {
        config([
            'services.turnstile.site_key' => 'site-key-test',
            'services.turnstile.secret_key' => 'secret-key-test',
        ]);
        Mail::fake();
        Http::preventStrayRequests();

        $this->postContact()
            ->assertRedirect('/kontakt#contact-form')
            ->assertSessionHas('contact_status', 'success');

        Mail::assertNothingOutgoing();
        $this->assertDatabaseHas('spam_submissions', [
            'form' => 'contact',
            'reason' => 'turnstile',
        ]);
    }

    public function test_spam_submissions_older_than_ninety_days_are_pruned(): void
    {
        $this->travelTo('2026-10-18 12:00:00');

        $kept = SpamSubmission::factory()->create();
        $kept->created_at = now()->subDays(90);
        $kept->save();

        $removed = SpamSubmission::factory()->create();
        $removed->created_at = now()->subDays(90)->subSecond();
        $removed->save();

        $this->artisan('app:prune-spam-submissions')->assertSuccessful();

        $this->assertModelExists($kept);
        $this->assertModelMissing($removed);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function gibberishFields(): array
    {
        return [
            'name mixes letters and digits' => ['name', 'NARYTHY1364171NERTHRTYHR'],
            'message mixes letters and digits' => ['message', 'MERTYHR1364171MAMYJRTH'],
            'twelve characters without vowels' => ['message', 'BCDFGHJKLMNP'],
            'twelve characters mixing letters and digits' => ['name', 'AB1234567890'],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function ordinaryFields(): array
    {
        return [
            'company google' => ['company', 'google'],
            'long word with vowels' => ['message', 'Fotodokumentation av trapphuset igår.'],
            'swedish vowels' => ['name', 'ÅäöÅäöÅäöÅäö'],
            'eleven characters without vowels' => ['message', 'BCDFGHJKLMN'],
            'eleven characters mixing letters and digits' => ['name', 'AB123456789'],
            'short consonant token' => ['message', 'Send the PDF today please.'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function blockedEmails(): array
    {
        return [
            'notlettersmail.com' => ['bot@notlettersmail.com'],
            'belettersmail.com' => ['bot@belettersmail.com'],
            'apex lettersmail.com' => ['bot@lettersmail.com'],
            'subdomain' => ['bot@mail.notlettersmail.com'],
            'mixed case' => ['Bot@NotLettersMail.com'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function allowedEmails(): array
    {
        return [
            'example.com' => ['owner@example.com'],
            'lookalike suffix' => ['owner@notlettersmail.com.example'],
            'different tld' => ['owner@lettersmail.org'],
        ];
    }

    /**
     * @return array<string, array{?string, ?string}>
     */
    public static function incompleteTurnstileKeys(): array
    {
        return [
            'site key only' => ['site-key-test', null],
            'secret only' => [null, 'secret-key-test'],
            'both empty' => ['', ''],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function postContact(array $overrides = [], bool $withStamp = true): TestResponse
    {
        $payload = [
            'name' => 'Anna Svensson',
            'email' => 'anna@example.com',
            'company' => 'Example AB',
            'message' => 'Vi vill se hur rapporten fungerar.',
            'form_started_at' => FormSpam::stamp(10),
        ];

        if (! $withStamp) {
            unset($payload['form_started_at']);
        }

        return $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])
            ->post('/kontakt/meddelande', array_merge($payload, $overrides));
    }
}
