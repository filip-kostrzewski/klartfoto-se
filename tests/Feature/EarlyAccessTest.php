<?php

namespace Tests\Feature;

use App\LocalizedUrl;
use App\SiteSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EarlyAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'marketing.waitlist_enabled' => true,
            'marketing.email_confirmation' => false,
        ]);
    }

    public function test_production_waitlist_stays_closed_until_privacy_and_retention_are_configured(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config([
            'marketing.waitlist_enabled' => true,
            'marketing.data_controller' => null,
            'marketing.data_contact' => null,
            'marketing.data_hosting_provider' => null,
            'marketing.retention_purge_enabled' => false,
        ]);

        $this->assertFalse(SiteSeo::waitlistIsOpen());

        config([
            'marketing.data_controller' => 'Example Company',
            'marketing.data_contact' => 'privacy@example.com',
            'marketing.data_hosting_provider' => 'Example Host',
            'marketing.retention_purge_enabled' => true,
        ]);

        $this->assertTrue(SiteSeo::waitlistIsOpen());
    }

    public function test_valid_signup_is_localized_saved_once_and_sends_no_mail_by_default(): void
    {
        Mail::fake();

        foreach (array_keys(config('marketing.locales')) as $index => $locale) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.1.0.'.($index + 1)]);
            $response = $this->post(LocalizedUrl::route('signup', $locale, false), [
                'email' => "owner-{$locale}@example.com",
                'industry' => 'cleaning',
                'consent' => '1',
                'company_url' => '',
            ]);

            $response->assertRedirect(LocalizedUrl::route('home', $locale, false).'#signup')
                ->assertSessionHas('signup_status', __('marketing.form.status.success', [], $locale));
        }

        $this->assertSame(count(config('marketing.locales')), DB::table('early_access_signups')->count());
        Mail::assertNothingOutgoing();
    }

    public function test_duplicate_signup_keeps_the_request_locale_and_creates_no_duplicate(): void
    {
        $payload = ['email' => 'owner@example.com', 'industry' => 'property', 'consent' => '1'];
        $this->post(LocalizedUrl::route('signup', 'sv', false), $payload);

        $this->post(LocalizedUrl::route('signup', 'de', false), $payload)
            ->assertRedirect('/de#signup')
            ->assertSessionHas('signup_status', __('marketing.form.status.duplicate', [], 'de'));

        $this->assertDatabaseCount('early_access_signups', 1);
    }

    public function test_validation_messages_render_in_each_locale_after_post_and_redirect(): void
    {
        foreach (array_keys(config('marketing.locales')) as $index => $locale) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.2.0.'.($index + 1)]);
            $validation = trans('marketing', [], $locale)['form']['validation'];
            $response = $this->followingRedirects()->post(LocalizedUrl::route('signup', $locale, false), [
                'email' => 'not-an-email',
                'industry' => 'cleaning',
                'consent' => '1',
            ]);

            $response->assertOk()
                ->assertSee($validation['email.email'])
                ->assertSee('<html lang="'.$locale.'">', false)
                ->assertSee('id="email-error"', false);

            if ($locale !== 'pl') {
                $response->assertDontSee('Dołącz do listy testowej');
            }
        }

        $this->assertDatabaseCount('early_access_signups', 0);
    }

    public function test_malformed_email_and_industry_arrays_are_rejected_after_the_redirect(): void
    {
        $response = $this->followingRedirects()->post(LocalizedUrl::route('signup', 'pl', false), [
            'email' => ['invalid'],
            'industry' => ['cleaning'],
            'consent' => '1',
        ]);

        $validation = trans('marketing', [], 'pl')['form']['validation'];

        $response->assertOk()
            ->assertSee('<html lang="pl">', false)
            ->assertSee($validation['email.string'])
            ->assertSee($validation['industry.string'])
            ->assertSee('id="email"', false)
            ->assertSee('id="industry"', false);

        $this->assertDatabaseCount('early_access_signups', 0);
    }

    public function test_honeypot_returns_a_locale_matched_success_without_writing_a_signup(): void
    {
        $this->post(LocalizedUrl::route('signup', 'fi', false), [
            'company_url' => 'https://bot.example',
        ])->assertRedirect('/fi#signup')
            ->assertSessionHas('signup_status', __('marketing.form.status.honeypot', [], 'fi'));

        $this->assertDatabaseCount('early_access_signups', 0);
    }

    public function test_expired_signups_are_removed_by_the_retention_command(): void
    {
        $expired = now()->subMonths(13);
        $current = now();

        foreach ([['expired@example.com', $expired], ['current@example.com', $current]] as [$email, $createdAt]) {
            DB::table('early_access_signups')->insert([
                'email' => $email,
                'industry' => 'other',
                'consented_at' => $createdAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        $this->artisan('app:purge-expired-early-access-signups')->assertSuccessful();

        $this->assertDatabaseMissing('early_access_signups', ['email' => 'expired@example.com']);
        $this->assertDatabaseHas('early_access_signups', ['email' => 'current@example.com']);
    }
}
