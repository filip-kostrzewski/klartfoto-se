<?php

namespace Tests\Feature;

use App\CompanyRole;
use App\Models\Assignment;
use App\Models\User;
use App\Notifications\SwedishResetPassword;
use App\PhotoPhase;
use App\SiteSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_trial_account_and_signs_the_company_in(): void
    {
        $this->travelTo('2026-10-15 12:00:00');

        $this->get('/register')
            ->assertOk()
            ->assertSee('href="/villkor"', false)
            ->assertSee('användarvillkoren', false);

        $response = $this->post('/register', [
            'company_name' => 'Glans Städ AB',
            'name' => 'Anna Andersson',
            'email' => 'anna@glans.se',
            'password' => 'hemligt-losen',
            'password_confirmation' => 'hemligt-losen',
            'privacy' => '1',
        ]);

        $response->assertRedirect(route('assignments.index'))
            ->assertSessionHas('toast', 'Välkommen! Ditt konto är skapat.');
        $this->get('/uppdrag')
            ->assertOk()
            ->assertSee('Välkommen! Ditt konto är skapat.', false);
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'anna@glans.se')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('Anna Andersson', $user->name);
        $this->assertSame(CompanyRole::Owner, $user->role);
        $this->assertSame('Glans Städ AB', $user->company?->name);
        $this->assertTrue(Hash::check('hemligt-losen', $user->password));
        $this->assertSame('2026-11-14 12:00:00', $user->trial_ends_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-14 12:00:00', $user->company?->trial_ends_at?->format('Y-m-d H:i:s'));

        $this->actingAs($user)->get('/installningar')
            ->assertOk()
            ->assertSee('Anna Andersson', false)
            ->assertSee('Ägare', false)
            ->assertSee('Glans Städ AB', false);
    }

    public function test_a_verified_registration_can_create_a_job_upload_photos_and_share_the_report(): void
    {
        Storage::fake('local');
        Notification::fake();

        $this->post('/register', [
            'company_name' => 'Glans Städ AB',
            'name' => 'Anna Andersson',
            'email' => 'anna@glans.se',
            'password' => 'hemligt-losen',
            'password_confirmation' => 'hemligt-losen',
            'privacy' => '1',
        ])->assertRedirect(route('assignments.index'));

        $user = User::query()->where('email', 'anna@glans.se')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->canManageJobs());
        $this->get('/uppdrag')
            ->assertOk()
            ->assertSee('Nytt uppdrag', false)
            ->assertSee('href="/uppdrag/nytt"', false);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->get($url)
            ->assertRedirect(route('assignments.index'))
            ->assertSessionHas('status', 'E-postadressen är bekräftad.');
        $this->assertNotNull($user->refresh()->email_verified_at);

        $this->post('/uppdrag', [
            'customer_name' => 'Kund Kundsson',
            'address' => 'Storgatan 1, 111 22 Stockholm',
            'performed_on' => '2026-10-16',
            'before_photos' => [UploadedFile::fake()->image('fore.jpg', 120, 80)],
            'after_photos' => [UploadedFile::fake()->image('efter.jpg', 120, 80)],
        ])->assertRedirect();

        $assignment = Assignment::query()->first();
        $this->assertInstanceOf(Assignment::class, $assignment);
        $this->assertSame($user->company_id, $assignment->company_id);
        $photos = $assignment->photos()->orderBy('id')->get();
        $this->assertCount(2, $photos);
        $this->assertSame(PhotoPhase::Before, $photos[0]->phase);
        $this->assertSame(PhotoPhase::After, $photos[1]->phase);
        Storage::disk('local')->assertExists($photos[0]->path);
        Storage::disk('local')->assertExists($photos[1]->path);

        $this->post(route('assignments.share', $assignment))->assertRedirect();
        $this->assertNotNull($assignment->refresh()->link_copied_at);

        $version = $assignment->latestReportVersion();
        $this->assertNotNull($version);
        $versionAssets = $version->photoAssets()->get()->keyBy('source_photo_id');

        $this->post('/logout')->assertRedirect('/login');

        $report = '/r/'.$version->token;
        $this->get($report)
            ->assertOk()
            ->assertSee('Glans Städ AB', false)
            ->assertSee('Kund Kundsson', false)
            ->assertSee('Storgatan 1, 111 22 Stockholm', false)
            ->assertSee($report.'/version-foton/'.$versionAssets->get($photos[0]->id)->id, false)
            ->assertSee($report.'/version-foton/'.$versionAssets->get($photos[1]->id)->id, false);

        $this->get($report.'/version-foton/'.$versionAssets->get($photos[0]->id)->id)
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');
        $this->get($report.'/version-foton/'.$versionAssets->get($photos[1]->id)->id)
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');
    }

    public function test_runtime_routes_and_assets_use_app_url_while_seo_uses_public_origin(): void
    {
        $this->assertSame('https://klartfoto.test', config('app.url'));

        $this->post('/register', [
            'company_name' => 'Glans Städ AB',
            'name' => 'Anna Andersson',
            'email' => 'anna@glans.se',
            'password' => 'hemligt-losen',
            'password_confirmation' => 'hemligt-losen',
            'privacy' => '1',
        ])->assertRedirect('https://klartfoto.test/uppdrag');

        $this->assertSame('https://klartfoto.test/images/cleaning-detail.webp', asset('images/cleaning-detail.webp'));
        $this->assertSame('https://klartfoto.se/', SiteSeo::canonical('/'));
    }

    public function test_fixed_trial_end_overrides_the_default_number_of_days(): void
    {
        config(['klartfoto.trial_ends_at' => '2026-12-31']);

        $this->post('/register', [
            'company_name' => 'Glans Städ AB',
            'name' => 'Anna Andersson',
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
            'name' => '',
            'email' => 'inte-en-adress',
            'password' => 'kort',
            'password_confirmation' => 'annat',
        ])->assertRedirect('/register')
            ->assertSessionHasErrors(['company_name', 'name', 'email', 'password', 'privacy']);

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

    public function test_logout_returns_to_the_login_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')
            ->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_swedish_auth_addresses_redirect_to_the_working_pages(): void
    {
        $this->get('/logga-in')->assertStatus(301)->assertRedirect('/login');
        $this->get('/registrera')->assertStatus(301)->assertRedirect('/register');
        $this->get('/glomt-losenord')->assertStatus(301)->assertRedirect('/forgot-password');
    }

    public function test_forgot_password_page_uses_the_reset_heading(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('<h1>Återställ lösenord</h1>', false);
    }

    public function test_login_forgot_password_link_completes_the_reset_flow(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('<a href="/forgot-password">Glömt lösenordet?</a>', false);

        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('<h1>Återställ lösenord</h1>', false)
            ->assertSee('action="/forgot-password"', false)
            ->assertSee('name="email"', false)
            ->assertSee('Skicka länk', false);

        $user = User::factory()->create([
            'email' => 'anna@glans.se',
            'password' => 'hemligt-losen',
        ]);

        $this->post('/forgot-password', ['email' => 'anna@glans.se'])
            ->assertSessionHas('status', 'Om adressen finns hos oss har vi skickat en länk för att välja ett nytt lösenord.');

        $transport = Mail::mailer()->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);
        $message = $transport->messages()->sole()->getOriginalMessage();
        $this->assertInstanceOf(Email::class, $message);
        $body = html_entity_decode(($message->getHtmlBody() ?? '').($message->getTextBody() ?? ''));
        $this->assertSame(1, preg_match('#https?://[^"\s<>]+/reset-password/[A-Za-z0-9]+\?email=[^"\s<>]+#', $body, $matches));

        $parts = parse_url($matches[0]);
        $this->assertIsArray($parts);
        $resetPath = ($parts['path'] ?? '').(isset($parts['query']) ? '?'.$parts['query'] : '');

        $this->get($resetPath)
            ->assertOk()
            ->assertSee('<h1>Byt lösenord</h1>', false)
            ->assertSee('name="token"', false)
            ->assertSee('value="anna@glans.se"', false);

        $this->assertSame(1, preg_match('#/reset-password/([A-Za-z0-9]+)#', $resetPath, $token));

        $this->post('/reset-password', [
            'token' => $token[1],
            'email' => 'anna@glans.se',
            'password' => 'nytt-losenord',
            'password_confirmation' => 'nytt-losenord',
        ])->assertRedirect('/login')
            ->assertSessionHas('status', 'Lösenordet är bytt. Logga in med det nya lösenordet.');

        $this->post('/login', [
            'email' => 'anna@glans.se',
            'password' => 'hemligt-losen',
        ])->assertSessionHasErrors('email');

        $this->post('/login', [
            'email' => 'anna@glans.se',
            'password' => 'nytt-losenord',
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
