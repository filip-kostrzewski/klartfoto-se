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

    public function test_public_pages_ask_in_swedish_and_load_no_tag_without_a_measurement_id(): void
    {
        config(['services.google_analytics.measurement_id' => null]);

        $home = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-cookie-choice="denied"', $home);
        $this->assertStringContainsString('>Neka</button>', $home);
        $this->assertStringContainsString('data-cookie-choice="granted"', $home);
        $this->assertStringContainsString('>Godkänn</button>', $home);
        $this->assertStringContainsString('Cookie-inställningar', $home);
        $this->assertStringContainsString('href="/integritetspolicy#cookies"', $home);
        $this->assertStringContainsString('klartfoto_cookie_consent', $home);
        $this->assertStringNotContainsString('googletagmanager.com', $home);
        $this->assertStringNotContainsString('function gtag', $home);

        $english = $this->get('/en')->assertOk()->getContent();
        $this->assertStringContainsString('>Neka</button>', $english);
        $this->assertStringContainsString('>Godkänn</button>', $english);
        $this->assertStringContainsString('Cookie-inställningar', $english);

        foreach (config('marketing.locales') as $locale => $metadata) {
            $this->get(LocalizedUrl::route('privacy', $locale, false))
                ->assertOk()
                ->assertSee('id="cookies"', false)
                ->assertSee('Google Analytics 4', false)
                ->assertSee('klartfoto_cookie_consent', false)
                ->assertSee('Godkänn', false)
                ->assertSee('Neka', false);
        }
    }

    public function test_a_measurement_id_loads_denied_consent_mode_only_on_public_pages(): void
    {
        config(['services.google_analytics.measurement_id' => 'G-TEST1234']);

        $home = $this->get('/')->assertOk()->getContent();
        $consent = strpos($home, "analytics_storage: 'denied'");
        $tag = strpos($home, 'https://www.googletagmanager.com/gtag/js?id=G-TEST1234');

        $this->assertNotFalse($consent);
        $this->assertNotFalse($tag);
        $this->assertLessThan($tag, $consent);
        $this->assertStringContainsString("ad_storage: 'denied'", $home);
        $this->assertStringContainsString("ad_user_data: 'denied'", $home);
        $this->assertStringContainsString("ad_personalization: 'denied'", $home);
        $this->assertStringContainsString("gtag('config'", $home);
        $this->assertStringContainsString('G-TEST1234', $home);

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
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TEST1234', false);

        $assignment = Assignment::factory()->for($user)->create();

        $this->get('/r/'.$assignment->public_token)
            ->assertOk()
            ->assertDontSee('googletagmanager.com', false)
            ->assertDontSee('Cookie-inställningar', false);
    }

    public function test_a_malformed_measurement_id_does_not_load_the_tag(): void
    {
        config(['services.google_analytics.measurement_id' => 'G-TEST"><script>alert(1)</script>']);

        $home = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('googletagmanager.com', $home);
        $this->assertStringNotContainsString('function gtag', $home);
        $this->assertStringNotContainsString('alert(1)', $home);
        $this->assertStringContainsString('>Godkänn</button>', $home);
    }
}
