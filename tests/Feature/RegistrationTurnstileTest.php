<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationTurnstileTest extends TestCase
{
    use RefreshDatabase;

    private const TURNSTILE_ERROR = 'Bekräfta att du inte är en robot och försök igen.';

    public function test_register_page_renders_turnstile_when_both_keys_are_set(): void
    {
        $this->enableTurnstile();

        $this->get('/register')
            ->assertOk()
            ->assertSee('class="cf-turnstile contact-turnstile"', false)
            ->assertSee('data-sitekey="site-key-test"', false)
            ->assertSee('https://challenges.cloudflare.com/turnstile/v0/api.js', false)
            ->assertDontSee('secret-key-test', false);
    }

    #[DataProvider('incompleteTurnstileKeys')]
    public function test_register_page_omits_turnstile_when_a_key_is_missing(?string $siteKey, ?string $secretKey): void
    {
        config([
            'services.turnstile.site_key' => $siteKey,
            'services.turnstile.secret_key' => $secretKey,
        ]);

        $this->get('/register')
            ->assertOk()
            ->assertDontSee('cf-turnstile', false)
            ->assertDontSee('challenges.cloudflare.com', false);
    }

    #[DataProvider('incompleteTurnstileKeys')]
    public function test_registration_works_without_a_token_when_turnstile_is_not_fully_configured(?string $siteKey, ?string $secretKey): void
    {
        config([
            'services.turnstile.site_key' => $siteKey,
            'services.turnstile.secret_key' => $secretKey,
        ]);
        Notification::fake();
        Http::preventStrayRequests();

        $this->register()->assertRedirect(route('assignments.index'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'anna@glans.se']);
    }

    public function test_valid_turnstile_token_is_verified_and_creates_the_account(): void
    {
        $this->enableTurnstile();
        Notification::fake();
        Http::preventStrayRequests();
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true]),
        ]);

        $this->register(['cf-turnstile-response' => 'token-ok'])
            ->assertRedirect(route('assignments.index'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'anna@glans.se']);
        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
                && ($data['secret'] ?? null) === 'secret-key-test'
                && ($data['response'] ?? null) === 'token-ok'
                && ($data['remoteip'] ?? null) === '192.0.2.20';
        });
    }

    public function test_failed_turnstile_verification_shows_a_swedish_error_and_creates_nothing(): void
    {
        $this->enableTurnstile();
        Notification::fake();
        Http::preventStrayRequests();
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => false]),
        ]);

        $this->from('/register')
            ->register(['cf-turnstile-response' => 'token-bad'])
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['cf-turnstile-response' => self::TURNSTILE_ERROR]);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('companies', 0);
        Notification::assertNothingSent();
    }

    public function test_turnstile_error_is_shown_on_the_register_page(): void
    {
        $this->enableTurnstile();
        Http::preventStrayRequests();
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => false]),
        ]);

        $this->from('/register')
            ->followingRedirects()
            ->register(['cf-turnstile-response' => 'token-bad'])
            ->assertOk()
            ->assertSee('<span class="field-error" id="turnstile-error">'.self::TURNSTILE_ERROR.'</span>', false)
            ->assertSee('value="anna@glans.se"', false);
    }

    public function test_turnstile_connection_failure_blocks_the_registration(): void
    {
        $this->enableTurnstile();
        Http::preventStrayRequests();
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::failedConnection(),
        ]);

        $this->register(['cf-turnstile-response' => 'token-ok'])
            ->assertSessionHasErrors(['cf-turnstile-response' => self::TURNSTILE_ERROR]);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_missing_turnstile_token_is_rejected_without_calling_siteverify(): void
    {
        $this->enableTurnstile();
        Http::preventStrayRequests();
        Http::fake();

        $this->register()
            ->assertSessionHasErrors(['cf-turnstile-response' => self::TURNSTILE_ERROR]);

        $this->assertDatabaseCount('users', 0);
        Http::assertNothingSent();
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

    private function enableTurnstile(): void
    {
        config([
            'services.turnstile.site_key' => 'site-key-test',
            'services.turnstile.secret_key' => 'secret-key-test',
        ]);
    }

    /**
     * @param  array<string, string>  $overrides
     */
    private function register(array $overrides = []): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])
            ->post('/register', array_merge([
                'company_name' => 'Glans Städ AB',
                'name' => 'Anna Andersson',
                'email' => 'anna@glans.se',
                'password' => 'hemligt-losen',
                'password_confirmation' => 'hemligt-losen',
                'privacy' => '1',
            ], $overrides));
    }
}
