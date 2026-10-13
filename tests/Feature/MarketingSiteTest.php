<?php

namespace Tests\Feature;

use App\LocalizedUrl;
use App\SiteSeo;
use Tests\TestCase;

class MarketingSiteTest extends TestCase
{
    public function test_root_stays_swedish_regardless_of_browser_language(): void
    {
        $response = $this->withHeaders(['Accept-Language' => 'da,en;q=0.9'])->get('/');

        $response->assertOk()
            ->assertSee('<html lang="sv">', false)
            ->assertSee('Visa jobbet.', false)
            ->assertSee('content="sv_SE"', false)
            ->assertSee('http://klartfoto.test/', false);
    }

    public function test_each_of_the_42_localized_pages_has_a_unique_language_title_and_self_canonical(): void
    {
        $pageKeys = ['home', 'cleaning', 'renovation', 'property', 'report', 'privacy'];
        $titlesByPage = [];

        foreach (config('marketing.locales') as $locale => $localeMetadata) {
            foreach ($pageKeys as $pageKey) {
                $path = LocalizedUrl::route($pageKey, $locale, false);
                $html = $this->get($path)->assertOk()->getContent();
                $canonical = SiteSeo::canonical($path);
                $catalogKey = match ($pageKey) {
                    'cleaning', 'renovation', 'property' => "marketing.industries.{$pageKey}.title",
                    default => "marketing.{$pageKey}.title",
                };
                $title = str_replace(':brand', config('marketing.brand'), __($catalogKey, [], $locale));

                $this->assertStringContainsString('<html lang="'.$locale.'">', $html);
                $this->assertStringContainsString('<title>'.$title.'</title>', $html);
                $this->assertStringContainsString('<link rel="canonical" href="'.$canonical.'">', $html);
                $this->assertStringContainsString('property="og:locale" content="'.$localeMetadata['og'].'"', $html);
                $this->assertStringContainsString('"inLanguage":"'.$localeMetadata['schema'].'"', $html);

                foreach (config('marketing.locales') as $alternateLocale => $_alternateMetadata) {
                    $alternatePath = LocalizedUrl::route($pageKey, $alternateLocale, false);
                    $this->assertStringContainsString(
                        'hreflang="'.$alternateLocale.'" href="'.SiteSeo::canonical($alternatePath).'"',
                        $html,
                    );
                }

                $defaultPath = LocalizedUrl::route($pageKey, 'sv', false);
                $this->assertStringContainsString('hreflang="x-default" href="'.SiteSeo::canonical($defaultPath).'"', $html);
                $titlesByPage[$pageKey][] = $title;

                if ($locale !== 'pl') {
                    $this->assertStringNotContainsString('Dołącz do listy testowej', $html);
                    $this->assertStringNotContainsString('Nie znaleziono tej strony', $html);
                    $this->assertStringNotContainsString(':brand', $html);
                }

                $this->assertStringNotContainsString('<div class="announcement"', $html);

                if ($pageKey === 'privacy') {
                    $this->assertStringContainsString('name="robots" content="noindex,nofollow"', $html);
                }
            }
        }

        foreach ($titlesByPage as $titles) {
            $this->assertCount(count(config('marketing.locales')), array_unique($titles));
        }
    }

    public function test_public_copy_uses_contact_language_without_prelaunch_promises(): void
    {
        $forbiddenByLocale = [
            'sv' => ['tidigt intresse', 'produkt under utveckling', 'testperiod', 'förbereds för serviceföretag', 'startdatum eller pris', 'konfiguration'],
            'en' => ['early access', 'early-access', 'in preparation', 'testing period', 'testing', 'project launch', 'launch date or price', 'not a finished system', 'configuration'],
            'pl' => ['wczesny dostęp', 'wczesnego dostępu', 'lista testowa', 'listy testowej', 'testów', 'w przygotowaniu', 'przed testami', 'okres testów', 'startu ani ceny', 'konfigurac'],
            'de' => ['frühzugang', 'testphase', 'zum test eingeladen', 'wird vorbereitet', 'tests', 'konfiguriert'],
            'nb' => ['tidlig tilgang', 'testperioden', 'testing', 'testlisten', 'under forberedelse', 'oppstartsdato eller pris', 'konfigurert'],
            'da' => ['tidlig adgang', 'testperioden', 'testlisten', 'under forberedelse', 'samtaler og test', 'konfigureret'],
            'fi' => ['ennakkokiinnostus', 'testijakso', 'testaamaan', 'testauksen', 'valmisteilla', 'kiinnostuslista', 'ennen testien'],
        ];

        foreach ($forbiddenByLocale as $locale => $phrases) {
            $catalog = json_encode(trans('marketing', [], $locale), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->assertIsString($catalog);
            $copy = mb_strtolower($catalog);

            foreach ($phrases as $phrase) {
                $this->assertStringNotContainsString(mb_strtolower($phrase), $copy, "Unexpected prelaunch copy for {$locale}: {$phrase}");
            }
        }
    }

    public function test_contact_submission_paths_are_localized_and_old_prelaunch_paths_are_not_public_routes(): void
    {
        $expectedPaths = [
            'sv' => '/kontakt',
            'en' => '/en/contact',
            'pl' => '/pl/kontakt',
            'de' => '/de/kontakt',
            'nb' => '/nb/kontakt',
            'da' => '/da/kontakt',
            'fi' => '/fi/yhteys',
        ];

        foreach ($expectedPaths as $locale => $path) {
            $this->assertSame($path, LocalizedUrl::route('signup', $locale, false));
        }

        foreach ([
            '/intresse', '/en/early-access', '/pl/lista-testowa', '/de/fruehzugang',
            '/nb/tidlig-tilgang', '/da/tidlig-adgang', '/fi/ennakkokaytto',
        ] as $oldPath) {
            $this->post($oldPath)->assertStatus(405);
        }

        $this->get('/lista-testowa')->assertNotFound();
    }

    public function test_closed_contact_section_uses_a_real_contact_address_and_does_not_render_the_form(): void
    {
        config([
            'marketing.waitlist_enabled' => false,
            'marketing.data_contact' => 'hello@example.com',
        ]);

        $this->get('/')->assertOk()
            ->assertSee('mailto:hello@example.com', false)
            ->assertSee(__('marketing.home.signup.closed_contact', [], 'sv'))
            ->assertDontSee('<form class="signup-form"', false);
    }

    public function test_language_switcher_keeps_the_current_page_for_all_languages(): void
    {
        foreach (config('marketing.locales') as $locale => $_metadata) {
            $path = LocalizedUrl::route('cleaning', $locale, false);
            $response = $this->get($path)->assertOk();

            foreach (config('marketing.locales') as $targetLocale => $_targetMetadata) {
                $target = LocalizedUrl::route('cleaning', $targetLocale, false);
                $response->assertSee('href="'.$target.'" lang="'.$targetLocale.'"', false);
            }
        }
    }

    public function test_old_polish_and_swedish_prefixed_paths_redirect_to_their_canonical_locales(): void
    {
        $this->get('/dla-firm-sprzatajacych')->assertStatus(301)->assertRedirect('/pl/dla-firm-sprzatajacych');
        $this->get('/wzor-raportu-przed-po')->assertStatus(301)->assertRedirect('/pl/wzor-raportu-przed-po');
        $this->get('/polityka-prywatnosci')->assertStatus(301)->assertRedirect('/pl/polityka-prywatnosci');
        $this->get('/sv')->assertStatus(301)->assertRedirect('/');
        $this->get('/sv/for-stadforetag')->assertStatus(301)->assertRedirect('/for-stadforetag');
    }

    public function test_unknown_localized_slug_and_unknown_locale_are_real_404_responses(): void
    {
        $this->get('/en/no-such-page')->assertNotFound()->assertSee('<html lang="en">', false);
        $this->get('/unknown/for-something')->assertNotFound();
        $this->get('/no-such-page')->assertNotFound()->assertSee('Sidan kunde inte hittas');
    }

    public function test_canonical_and_hreflang_stay_on_configured_domain_for_untrusted_host(): void
    {
        config(['marketing.base_url' => 'https://klartfoto.example']);

        $html = $this->get('https://untrusted.example/en/for-cleaning-companies')->assertOk()->getContent();

        $this->assertStringContainsString('rel="canonical" href="https://klartfoto.example/en/for-cleaning-companies"', $html);
        $this->assertStringContainsString('hreflang="sv" href="https://klartfoto.example/for-stadforetag"', $html);
        $this->assertStringContainsString('property="og:url" content="https://klartfoto.example/en/for-cleaning-companies"', $html);
    }

    public function test_local_robots_blocks_crawling_and_sitemap_is_unavailable(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSeeText("User-agent: *\nDisallow: /");
        $this->get('/sitemap.xml')->assertNotFound();
    }

    public function test_production_sitemap_lists_35_localized_indexable_urls_without_privacy_or_lastmod(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config([
            'marketing.base_url' => 'https://klartfoto.example',
            'marketing.indexing_enabled' => true,
        ]);

        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('content-type', 'application/xml; charset=UTF-8');
        $xml = $response->getContent();

        $this->assertSame(35, substr_count($xml, '<url>'));
        $this->assertStringContainsString('https://klartfoto.example/for-stadforetag', $xml);
        $this->assertStringContainsString('https://klartfoto.example/en/for-cleaning-companies', $xml);
        $this->assertStringContainsString('https://klartfoto.example/pl/dla-firm-sprzatajacych', $xml);
        $this->assertStringNotContainsString('integritet', $xml);
        $this->assertStringNotContainsString('datenschutz', $xml);
        $this->assertStringNotContainsString('<lastmod>', $xml);
    }
}
