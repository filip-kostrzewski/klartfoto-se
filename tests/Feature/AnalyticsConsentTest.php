<?php

namespace Tests\Feature;

use App\LocalizedUrl;
use App\Models\Assignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_translate_the_banner_and_set_denied_consent_without_a_measurement_id(): void
    {
        config(['services.google_analytics.measurement_id' => null]);

        foreach (config('marketing.locales') as $locale => $metadata) {
            $html = $this->get(LocalizedUrl::route('home', $locale, false))->assertOk()->getContent();
            $reject = __('marketing.cookies.reject', [], $locale);
            $accept = __('marketing.cookies.accept', [], $locale);
            $settings = __('marketing.cookies.settings', [], $locale);
            $privacy = LocalizedUrl::route('privacy', $locale, false);
            $default = strpos($html, "gtag('consent', 'default'");
            $update = strpos($html, "gtag('consent', 'update'");

            $this->assertNotFalse($default);
            $this->assertNotFalse($update);
            $this->assertLessThan($update, $default);
            $this->assertStringContainsString('window.dataLayer = window.dataLayer || [];', $html);
            $this->assertStringContainsString('function gtag(){dataLayer.push(arguments);}', $html);
            $this->assertStringContainsString("analytics_storage: 'denied'", $html);
            $this->assertStringContainsString("ad_storage: 'denied'", $html);
            $this->assertStringContainsString("ad_user_data: 'denied'", $html);
            $this->assertStringContainsString("ad_personalization: 'denied'", $html);
            $this->assertStringContainsString('wait_for_update: 500', $html);
            $this->assertStringContainsString('>'.$reject.'</button>', $html);
            $this->assertStringContainsString('>'.$accept.'</button>', $html);
            $this->assertStringContainsString(__('marketing.cookies.banner', [], $locale), $html);
            $this->assertStringContainsString(__('marketing.cookies.banner_short', [], $locale), $html);
            $this->assertStringContainsString('type="button" class="footer-cookie-settings" data-cookie-settings>'.$settings.'</button>', $html);
            $this->assertStringContainsString('href="'.$privacy.'#cookies"', $html);
            $this->assertDoesNotMatchRegularExpression('/<a[^>]*data-cookie-settings/', $html);
            $this->assertStringNotContainsString('googletagmanager.com', $html);
            $this->assertStringNotContainsString("gtag('config'", $html);

            $this->get($privacy)
                ->assertOk()
                ->assertSee('id="cookies"', false)
                ->assertSee('Google Analytics 4', false)
                ->assertSee('klartfoto_cookie_consent', false)
                ->assertSee($accept, false)
                ->assertSee($reject, false);
        }
    }

    public function test_the_phone_banner_is_a_fixed_bar_and_the_desktop_banner_stays_in_place(): void
    {
        $css = file_get_contents(resource_path('css/site.css'));
        $this->assertIsString($css);

        $desktop = strpos($css, '.cookie-banner {');
        $phone = strpos($css, '@media (max-width: 425px)');
        $this->assertNotFalse($desktop);
        $this->assertNotFalse($phone);
        $this->assertLessThan($phone, $desktop);

        $desktopRule = substr($css, $desktop, $phone - $desktop);
        $phoneRule = substr($css, $phone, strpos($css, '@media (prefers-reduced-motion: reduce)', $phone) - $phone);

        $this->assertStringContainsString('position: sticky;', $desktopRule);
        $this->assertStringNotContainsString('position: fixed;', $desktopRule);
        $this->assertStringContainsString('position: fixed;', $phoneRule);
        $this->assertStringContainsString('max-height: 110px;', $phoneRule);
        $this->assertStringContainsString('padding-bottom: 110px;', $phoneRule);
        $this->assertStringContainsString('html.cookie-choice-saved body', $phoneRule);
        $this->assertStringContainsString('padding-bottom: 0;', $phoneRule);
        $this->assertStringContainsString('.cookie-banner-short', $phoneRule);
    }

    public function test_a_measurement_id_loads_denied_consent_mode_only_on_public_pages(): void
    {
        config(['services.google_analytics.measurement_id' => 'G-TEST1234']);

        $home = $this->get('/')->assertOk()->getContent();
        $consent = strpos($home, "gtag('consent', 'default'");

        $this->assertNotFalse($consent);
        $this->assertStringContainsString("analytics_storage: 'denied'", $home);
        $this->assertStringContainsString("ad_storage: 'denied'", $home);
        $this->assertStringContainsString("ad_user_data: 'denied'", $home);
        $this->assertStringContainsString("ad_personalization: 'denied'", $home);
        $this->assertStringContainsString('wait_for_update: 500', $home);
        $this->assertStringContainsString('G-TEST1234', $home);
        $this->assertStringNotContainsString('src="https://www.googletagmanager.com/gtag/js?id=G-TEST1234"', $home);

        $granted = $this->withUnencryptedCookie('klartfoto_cookie_consent', 'granted')
            ->get('/')
            ->assertOk()
            ->getContent();
        $grantedConsent = strpos($granted, "gtag('consent', 'default'");
        $grantedTag = strpos($granted, 'src="https://www.googletagmanager.com/gtag/js?id=G-TEST1234"');

        $this->assertNotFalse($grantedConsent);
        $this->assertNotFalse($grantedTag);
        $this->assertLessThan($grantedTag, $grantedConsent);

        $this->withUnencryptedCookie('klartfoto_cookie_consent', 'denied')
            ->get('/')
            ->assertOk()
            ->assertDontSee('src="https://www.googletagmanager.com/gtag/js?id=G-TEST1234"', false);

        $this->get('/login')
            ->assertOk()
            ->assertDontSee('googletagmanager.com', false)
            ->assertDontSee('Cookie-inställningar', false);

        $user = User::factory()->create();

        $this->actingAs($user)->get('/uppdrag')
            ->assertOk()
            ->assertDontSee('googletagmanager.com', false)
            ->assertDontSee('function gtag', false)
            ->assertDontSee('Cookie-inställningar', false);

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('G-TEST1234', false)
            ->assertDontSee('src="https://www.googletagmanager.com/gtag/js?id=G-TEST1234"', false);

        $assignment = Assignment::factory()->for($user)->create();

        $this->get('/r/'.$assignment->public_token)
            ->assertOk()
            ->assertDontSee('googletagmanager.com', false)
            ->assertDontSee('Cookie-inställningar', false);
    }

    public function test_the_public_measurement_id_is_the_default_and_stays_unloaded_until_consent(): void
    {
        $services = file_get_contents(config_path('services.php'));
        $composer = file_get_contents(base_path('composer.json'));
        $this->assertIsString($services);
        $this->assertIsString($composer);
        $this->assertStringContainsString("env('GA_MEASUREMENT_ID', 'G-4T7EVVZLB2')", $services);
        $this->assertStringContainsString('config:cache', $composer);

        config(['services.google_analytics.measurement_id' => 'G-4T7EVVZLB2']);

        $home = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('G-4T7EVVZLB2', $home);
        $this->assertStringContainsString("analytics_storage: 'denied'", $home);
        $this->assertStringNotContainsString('src="https://www.googletagmanager.com/gtag/js?id=G-4T7EVVZLB2"', $home);
        $this->assertStringContainsString('klartfotoLoadAnalytics', $home);
    }

    public function test_a_malformed_measurement_id_does_not_load_the_tag(): void
    {
        config(['services.google_analytics.measurement_id' => 'G-TEST"><script>alert(1)</script>']);

        $home = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('function gtag(){dataLayer.push(arguments);}', $home);
        $this->assertStringContainsString("gtag('consent', 'default'", $home);
        $this->assertStringNotContainsString('googletagmanager.com', $home);
        $this->assertStringNotContainsString("gtag('config'", $home);
        $this->assertStringNotContainsString('alert(1)', $home);
        $this->assertStringContainsString('>Godkänn</button>', $home);
    }
}
