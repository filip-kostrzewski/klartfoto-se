<?php

namespace Tests\Feature;

use App\LocalizedUrl;
use App\SiteSeo;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Tests\TestCase;

class MarketingSiteTest extends TestCase
{
    public function test_root_stays_swedish_regardless_of_browser_language(): void
    {
        $response = $this->withHeaders(['Accept-Language' => 'da,en;q=0.9'])->get('/');

        $response->assertOk()
            ->assertSee('<html lang="sv">', false)
            ->assertSee('Tydliga foton', false)
            ->assertSee('Jobbet är klart.', false)
            ->assertSee('content="sv_SE"', false)
            ->assertSee('https://klartfoto.se/', false)
            ->assertDontSee('<link rel="canonical" href="https://klartfoto.test/', false)
            ->assertDontSee('property="og:url" content="https://klartfoto.test/', false);
    }

    public function test_swedish_terms_page_explains_trial_prices_and_manual_invoice_activation(): void
    {
        config(['klartfoto.provider_contact_email' => 'kontakt@klartfoto.se']);

        $html = $this->get('/villkor')->assertOk()->getContent();

        $this->assertStringContainsString('<html lang="sv">', $html);
        $this->assertStringContainsString('<title>Användarvillkor | Klartfoto</title>', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://klartfoto.se/villkor">', $html);
        $this->assertStringContainsString('Uppdaterad 17 oktober 2026 · Version 1.2.', $html);
        $this->assertStringContainsString('Den här provperioden är skild från feedbackpiloten.', $html);
        $this->assertStringContainsString('Feedbackpilot (på inbjudan): ett fåtal företag får Team gratis till 31 december 2026 mot ärlig feedback. Intresserad? Skriv till kontakt@klartfoto.se.', $html);
        $this->assertStringContainsString('mailto:kontakt@klartfoto.se', $html);
        $this->assertStringContainsString('30 dagar', $html);
        $this->assertStringContainsString('290 kr per månad exklusive moms', $html);
        $this->assertStringContainsString('590 kr per månad exklusive moms', $html);
        $this->assertStringContainsString('Den är inte en betalning och aktiverar inte planen.', $html);
        $this->assertStringContainsString('Vi aktiverar den betalda planen när betalningen har registrerats.', $html);
        $this->assertStringContainsString('Fakturabetalda planer förnyas inte automatiskt', $html);
        $this->assertStringNotContainsString('hreflang="en"', $html);
        $this->assertStringContainsString('hreflang="x-default" href="https://klartfoto.se/villkor"', $html);
    }

    public function test_every_marketing_footer_links_to_the_swedish_terms_page(): void
    {
        foreach (config('marketing.locales') as $locale => $_metadata) {
            $this->get(LocalizedUrl::route('home', $locale, false))
                ->assertOk()
                ->assertSee('href="'.LocalizedUrl::route('contact', $locale, false).'"', false)
                ->assertSee(trans('marketing.nav.contact', [], $locale), false)
                ->assertSee('href="/villkor" lang="sv"', false)
                ->assertSee(trans('marketing.nav.terms', [], $locale), false)
                ->assertDontSee('marketing.nav.terms', false);
        }
    }

    public function test_each_of_the_49_localized_pages_has_a_unique_language_title_and_self_canonical(): void
    {
        $pageKeys = ['home', 'cleaning', 'renovation', 'property', 'report', 'privacy', 'contact'];
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

                if (in_array($locale, config('marketing.indexable_locales'), true)) {
                    if ($pageKey === 'privacy') {
                        $this->assertStringContainsString('name="robots" content="noindex,nofollow"', $html);
                    }
                } else {
                    $this->assertStringContainsString('name="robots" content="noindex,follow"', $html);
                }
            }
        }

        foreach ($titlesByPage as $pageKey => $titles) {
            $this->assertCount(count(config('marketing.locales')), array_unique($titles), "Localized titles must be unique for {$pageKey}.");
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
            $this->assertSame($path, LocalizedUrl::route('contact', $locale, false));
            $this->assertSame(
                $path.'/'.config("marketing.contact_message_slugs.{$locale}"),
                LocalizedUrl::route('contact.message', $locale, false),
            );

            $this->get($path)->assertOk()
                ->assertSee('action="'.LocalizedUrl::route('contact.message', $locale, false).'"', false)
                ->assertSee('href="'.LocalizedUrl::route('privacy', $locale, false).'"', false)
                ->assertSee(trans('marketing.contact.submit', [], $locale), false)
                ->assertDontSee('marketing.contact.', false);
        }

        foreach ([
            '/intresse', '/en/early-access', '/pl/lista-testowa', '/de/fruehzugang',
            '/nb/tidlig-tilgang', '/da/tidlig-adgang', '/fi/ennakkokaytto',
        ] as $oldPath) {
            $this->post($oldPath)->assertStatus(405);
        }

        $this->get('/lista-testowa')->assertNotFound();
    }

    public function test_homepage_cta_links_to_contact_and_the_sample_report(): void
    {
        config([
            'marketing.waitlist_enabled' => false,
            'marketing.data_contact' => 'hello@example.com',
        ]);

        $this->get('/')->assertOk()
            ->assertSee('href="/kontakt"', false)
            ->assertSee(__('marketing.home.signup.closed_contact', [], 'sv'))
            ->assertSee('href="/register"', false)
            ->assertSee('href="/exempel-rapport"', false)
            ->assertDontSee('mailto:hello@example.com', false)
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
        $this->get('/rapportmall-fore-efter')->assertStatus(301)->assertRedirect('/exempel-rapport');
        $this->get('/priser')->assertStatus(301)->assertRedirect('/#priser');
    }

    public function test_homepage_shows_prices_photo_demos_and_the_company_footer(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('id="priser"', false)
            ->assertSee('href="/#priser"', false)
            ->assertSee('290 kr/mån', false)
            ->assertSee('590 kr/mån', false)
            ->assertSee('1 användare', false)
            ->assertSee('obegränsade rapporter', false)
            ->assertSee('upp till 10 användare', false)
            ->assertSee('egen logga i rapporten', false)
            ->assertSee('30 dagar gratis utan kort. Ingen bindningstid.', false)
            ->assertSee('Feedbackpilot (på inbjudan): ett fåtal företag får Team gratis till 31 december 2026 mot ärlig feedback. Intresserad? Skriv till kontakt@klartfoto.se.', false)
            ->assertSee('Betalning sker mot faktura.', false)
            ->assertSee('class="hero-price"', false)
            ->assertDontSee('Fortnox', false)
            ->assertSee('Kom igång gratis', false)
            ->assertSee('href="/register"', false)
            ->assertSee('images/demo/kitchen-before-small.webp?v='.config('klartfoto.demo_asset_version'), false)
            ->assertSee('images/demo/kitchen-after-small.webp?v='.config('klartfoto.demo_asset_version'), false)
            ->assertSee('images/demo/example-report-phone.webp?v='.config('klartfoto.demo_asset_version'), false)
            ->assertSee('AI-demo · inte ett kundfoto', false)
            ->assertSee('images/renovation-detail-small.webp?v='.config('klartfoto.demo_asset_version'), false)
            ->assertSee('images/property-detail-small.webp?v='.config('klartfoto.demo_asset_version'), false)
            ->assertDontSee('images/demo/kitchen-before.svg', false)
            ->assertSee('Prodi Digital AB, org.nr 559214-9370, Tegelbrukets väg 41, 195 59 Märsta', false)
            ->assertSee('<!--email_off--><a href="mailto:kontakt@klartfoto.se">kontakt@klartfoto.se</a><!--/email_off-->', false)
            ->assertDontSee('/cdn-cgi/l/email-protection', false)
            ->assertDontSee('filip@prodi.se', false)
            ->assertSee('href="/integritetspolicy"', false);

        $this->get('/exempel-rapport')
            ->assertOk()
            ->assertSee('images/demo/kitchen-before-small.webp?v='.config('klartfoto.demo_asset_version'), false)
            ->assertSee('images/demo/kitchen-after-small.webp?v='.config('klartfoto.demo_asset_version'), false)
            ->assertSee('class="example-report-flag"', false)
            ->assertSee('Exempel', false)
            ->assertSee('AI-demo · inte ett kundfoto', false)
            ->assertDontSee('checklista', false);

        $this->get('/for-stadforetag')
            ->assertOk()
            ->assertSee('images/cleaning-detail.webp?v='.config('klartfoto.demo_asset_version'), false)
            ->assertSee('AI-genererat demofoto av en person som torkar av en köksbänk', false)
            ->assertSee('Efter städning', false)
            ->assertSee('Exempelkund', false)
            ->assertSee('Exempelvägen 12', false)
            ->assertSee('12 mars 2026', false)
            ->assertSee('AI-demo · inte ett kundfoto', false)
            ->assertSee('Fotodokumentation för städföretag.', false)
            ->assertSee('images/demo/kitchen-before-small.webp?v='.config('klartfoto.demo_asset_version'), false)
            ->assertSee('images/demo/example-report-phone.webp?v='.config('klartfoto.demo_asset_version'), false);

        $this->get('/for-hantverkare')
            ->assertOk()
            ->assertSee('Efter utfört arbete', false)
            ->assertSee('Brf Lindgården', false)
            ->assertSee('Badrumsrenovering', false)
            ->assertSee('AI-demo · inte ett kundfoto', false)
            ->assertSee('Fotodokumentation för hantverkare.', false)
            ->assertSee('images/renovation-detail-small.webp?v='.config('klartfoto.demo_asset_version'), false)
            ->assertSee('images/demo/example-report-phone.webp?v='.config('klartfoto.demo_asset_version'), false)
            ->assertDontSee('Efter städning', false)
            ->assertDontSee('Exempelkund', false)
            ->assertDontSee('Exempelvägen 12', false)
            ->assertDontSee('Fotodokumentation för städföretag.', false)
            ->assertDontSee('images/demo/kitchen-before-small.webp', false);

        $this->get('/for-uthyrare')
            ->assertOk()
            ->assertSee('Inför nästa gäst', false)
            ->assertSee('Lägenhet 3B', false)
            ->assertSee('Incheckning 14:00', false)
            ->assertSee('AI-demo · inte ett kundfoto', false)
            ->assertSee('Fotodokumentation för uthyrare.', false)
            ->assertSee('images/property-detail-small.webp?v='.config('klartfoto.demo_asset_version'), false)
            ->assertSee('images/demo/example-report-phone.webp?v='.config('klartfoto.demo_asset_version'), false)
            ->assertDontSee('Efter städning', false)
            ->assertDontSee('Exempelkund', false)
            ->assertDontSee('Fotodokumentation för städföretag.', false)
            ->assertDontSee('images/demo/kitchen-after-small.webp', false);

        $this->get('/pl')
            ->assertOk()
            ->assertSee('290 kr/mies. bez VAT', false)
            ->assertSee('590 kr/mies. bez VAT', false)
            ->assertDontSee('kr/mån', false)
            ->assertSee('Pilotaż (na zaproszenie): kilka firm otrzymuje Team bez opłat do 31 grudnia 2026 w zamian za szczerą opinię. Zainteresowani? Napisz na kontakt@klartfoto.se.', false)
            ->assertSee('Usługa jest przeznaczona dla firm działających w Szwecji i rozliczana w koronach szwedzkich (SEK).', false)
            ->assertSee('firm sprzątających', false)
            ->assertDontSee('usługowych', false);

        config(['klartfoto.provider_contact_email' => null]);
        $this->get('/integritetspolicy')
            ->assertOk()
            ->assertSee('Prodi Digital AB', false)
            ->assertSee('foton av kundens hem', false)
            ->assertSee('eventuellt företagsnamn', false)
            ->assertSee('vår e-posttjänst', false)
            ->assertSee('Formuläret sparar ingen kopia i Klartfotos databas', false)
            ->assertDontSee('mailto:', false);

        config(['klartfoto.provider_contact_email' => 'privacy@klartfoto.se']);
        $this->get('/integritetspolicy')
            ->assertOk()
            ->assertSee('mailto:privacy@klartfoto.se', false);

        config(['klartfoto.pricing_visible' => false]);
        $this->get('/')
            ->assertOk()
            ->assertDontSee('id="priser"', false)
            ->assertDontSee('290 kr/mån', false);
    }

    public function test_contact_page_renders_the_public_address_as_a_server_side_mailto(): void
    {
        $anchor = '<!--email_off--><a href="mailto:kontakt@klartfoto.se">kontakt@klartfoto.se</a><!--/email_off-->';
        $html = $this->get('/kontakt')->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, $anchor));
        $this->assertStringNotContainsString('/cdn-cgi/l/email-protection', $html);
        $this->assertStringNotContainsString('email-decode.min.js', $html);
        $this->assertStringNotContainsString('filip@prodi.se', $html);
    }

    public function test_polish_privacy_page_uses_verified_provider_contact_when_optional_marketing_fields_are_empty(): void
    {
        config([
            'marketing.data_controller' => null,
            'marketing.data_contact' => null,
            'klartfoto.provider_contact_email' => 'kontakt@klartfoto.se',
        ]);

        $this->get('/pl/polityka-prywatnosci')->assertOk()
            ->assertSee('Administratorem danych jest Prodi Digital AB', false)
            ->assertSee('mailto:kontakt@klartfoto.se', false)
            ->assertSee('Wiadomości z formularza kontaktowego', false)
            ->assertDontSee('Na tej stronie nie podano danych kontaktowych', false);
    }

    public function test_homepage_slider_description_is_screen_reader_only(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $description = 'Till vänster visas 54 procent av bilden före arbetet och till höger bilden efter arbetet.';
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//*[@data-comparison-description]');

        $this->assertNotFalse($nodes);
        $this->assertSame(1, $nodes->length);
        $descriptionNode = $nodes->item(0);
        $this->assertInstanceOf(DOMElement::class, $descriptionNode);
        $this->assertSame('comparison-description', $descriptionNode->getAttribute('id'));
        $this->assertSame('sr-only', $descriptionNode->getAttribute('class'));
        $this->assertSame($description, trim($descriptionNode->textContent ?? ''));
        $this->assertSame('', $descriptionNode->getAttribute('aria-label'));
        $this->assertSame('polite', $descriptionNode->getAttribute('aria-live'));
        $this->assertSame(0, $xpath->query('ancestor::*[contains(concat(" ", normalize-space(@class), " "), " comparison-frame ")]', $descriptionNode)->length);
        $this->assertSame(1, substr_count($html, $description));

        $control = $xpath->query('//*[@data-comparison]//input[@type="range"]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $control);
        $this->assertSame('comparison-description', $control->getAttribute('aria-describedby'));
        $this->assertSame('54 procent före, resten efter', $control->getAttribute('aria-valuetext'));

        $css = file_get_contents(resource_path('css/site.css'));
        $this->assertIsString($css);
        $start = strpos($css, '.sr-only {');
        $this->assertNotFalse($start);
        $rule = substr($css, $start, strpos($css, '}', $start) - $start);
        $this->assertStringContainsString('position: absolute;', $rule);
        $this->assertStringContainsString('width: 1px;', $rule);
        $this->assertStringContainsString('height: 1px;', $rule);
        $this->assertStringContainsString('overflow: hidden;', $rule);
        $this->assertStringContainsString('clip: rect(0, 0, 0, 0);', $rule);
        $this->assertStringContainsString('clip-path: none;', $rule);
    }

    public function test_unknown_localized_slug_and_unknown_locale_are_real_404_responses(): void
    {
        $this->get('/en/no-such-page')->assertNotFound()->assertSee('<html lang="en">', false);
        $this->get('/unknown/for-something')->assertNotFound();
        $this->get('/no-such-page')->assertNotFound()->assertSee('Sidan kunde inte hittas');
    }

    public function test_canonical_and_hreflang_stay_on_configured_domain_for_untrusted_host(): void
    {
        config(['app.url' => 'https://klartfoto.example']);

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

    public function test_production_sitemap_lists_swedish_and_polish_urls_including_privacy(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config([
            'app.url' => 'https://klartfoto.example',
            'marketing.indexing_enabled' => false,
        ]);

        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('content-type', 'application/xml; charset=UTF-8');
        $xml = $response->getContent();

        $this->assertSame(17, substr_count($xml, '<url>'));
        $this->assertStringContainsString('<loc>https://klartfoto.example/</loc>', $xml);
        $this->assertStringContainsString('<loc>https://klartfoto.example/exempel-rapport</loc>', $xml);
        $this->assertStringContainsString('<loc>https://klartfoto.example/integritetspolicy</loc>', $xml);
        $this->assertStringContainsString('<loc>https://klartfoto.example/villkor</loc>', $xml);
        $this->assertStringContainsString('<loc>https://klartfoto.example/kontakt</loc>', $xml);
        $this->assertStringContainsString('<loc>https://klartfoto.example/pl/kontakt</loc>', $xml);
        $this->assertSame(1, substr_count($xml, '<loc>https://klartfoto.example/guider</loc>'));
        $this->assertSame(1, substr_count($xml, '<loc>https://klartfoto.example/guider/fotodokumentation-stadning</loc>'));
        $this->assertStringContainsString('https://klartfoto.example/for-stadforetag', $xml);
        $this->assertStringContainsString('<loc>https://klartfoto.example/pl/dla-firm-sprzatajacych</loc>', $xml);
        $this->assertStringContainsString('<loc>https://klartfoto.example/pl/polityka-prywatnosci</loc>', $xml);
        $this->assertStringNotContainsString('/en/', $xml);
        $this->assertStringNotContainsString('/de/', $xml);
        $this->assertStringNotContainsString('/nb/', $xml);
        $this->assertStringNotContainsString('/da/', $xml);
        $this->assertStringNotContainsString('/fi/', $xml);
        $this->assertStringNotContainsString('datenschutz', $xml);
        $this->assertStringContainsString('<loc>https://klartfoto.example/guider/fotodokumentation-stadning</loc><lastmod>2026-10-16</lastmod>', $xml);
        $this->assertDoesNotMatchRegularExpression('#<loc>(?!https://)#', $xml);

        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: https://klartfoto.example/sitemap.xml', false);
    }

    public function test_production_replaces_a_local_base_url_and_allows_indexing(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config([
            'app.url' => 'http://klartfoto.test',
            'marketing.base_url' => 'http://klartfoto.test',
            'marketing.indexing_enabled' => false,
        ]);

        $html = $this->get('/')->assertOk()->getContent();
        $schema = $this->structuredData($html);

        $this->assertSame('https://klartfoto.se/', $schema['@graph'][0]['url']);
        $this->assertSame('kontakt@klartfoto.se', $schema['@graph'][0]['email']);
        $this->assertStringContainsString('rel="canonical" href="https://klartfoto.se/"', $html);
        $this->assertStringContainsString('name="robots" content="index,follow,max-image-preview:large"', $html);
        $this->assertStringContainsString('property="og:url" content="https://klartfoto.se/"', $html);

        $this->get('/integritet')->assertStatus(301)->assertRedirect('/integritetspolicy');
        $this->get('/integritetspolicy')->assertOk()
            ->assertSee('name="robots" content="index,follow,max-image-preview:large"', false);
        $this->get('/en/privacy')->assertOk()
            ->assertSee('name="robots" content="noindex,follow"', false);
        $this->get('/pl/polityka-prywatnosci')->assertOk()
            ->assertSee('name="robots" content="index,follow,max-image-preview:large"', false);

        $this->get('/robots.txt')->assertOk()->assertSee("User-agent: *\nAllow: /\n\nSitemap: https://klartfoto.se/sitemap.xml", false);

        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<loc>https://klartfoto.se/</loc>', $sitemap);
        $this->assertStringContainsString('<loc>https://klartfoto.se/guider</loc>', $sitemap);
        $this->assertStringContainsString('<loc>https://klartfoto.se/guider/fotodokumentation-stadning</loc><lastmod>2026-10-16</lastmod>', $sitemap);
        $this->assertStringContainsString('<loc>https://klartfoto.se/guider</loc><lastmod>2026-10-16</lastmod>', $sitemap);
        $this->assertStringNotContainsString('klartfoto.test', $sitemap);
    }

    public function test_swedish_cleaning_photo_guide_is_an_indexable_article(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $html = $this->get('/guider/fotodokumentation-stadning')->assertOk()->getContent();
        $document = $this->htmlDocument($html);
        $xpath = new DOMXPath($document);
        $h1 = $xpath->query('//h1');
        $schema = $this->structuredData($html);
        $types = array_column($schema['@graph'], '@type');
        $faq = collect($schema['@graph'])->firstWhere('@type', 'FAQPage');
        $article = collect($schema['@graph'])->firstWhere('@type', 'Article');
        $breadcrumb = collect($schema['@graph'])->firstWhere('@type', 'BreadcrumbList');
        $faqNode = $xpath->query('//*[@id="guide-faq"]')->item(0);

        $this->assertNotFalse($h1);
        $this->assertSame(1, $h1->length);
        $this->assertInstanceOf(DOMElement::class, $h1->item(0));
        $this->assertSame('Fotodokumentation vid städning med före- och efterbilder', trim($h1->item(0)->textContent ?? ''));
        $this->assertStringContainsString('<html lang="sv">', $html);
        $this->assertStringContainsString('<title>Fotodokumentation vid städning: före- och efterbilder | Klartfoto</title>', $html);
        $this->assertStringContainsString('name="description" content="Så tar du före- och efterbilder vid städning, samlar en städrapport och delar den med kunden. Checklista, reklamationsunderlag och GDPR-vanor."', $html);
        $this->assertStringContainsString('rel="canonical" href="https://klartfoto.se/guider/fotodokumentation-stadning"', $html);
        $this->assertStringContainsString('property="og:type" content="article"', $html);
        $this->assertStringContainsString('property="og:title" content="Fotodokumentation vid städning: före- och efterbilder | Klartfoto"', $html);
        $this->assertStringContainsString('property="og:description" content="Så tar du före- och efterbilder vid städning, samlar en städrapport och delar den med kunden. Checklista, reklamationsunderlag och GDPR-vanor."', $html);
        $this->assertStringContainsString('property="og:url" content="https://klartfoto.se/guider/fotodokumentation-stadning"', $html);
        $this->assertStringContainsString('property="og:locale" content="sv_SE"', $html);
        $this->assertStringContainsString('property="article:published_time" content="2026-10-16"', $html);
        $this->assertStringContainsString('property="article:modified_time" content="2026-10-16"', $html);
        $this->assertStringContainsString('<time datetime="2026-10-16">Publicerad 16 oktober 2026</time>', $html);
        $this->assertStringNotContainsString('>Uppdaterad 16 oktober 2026</time>', $html);
        $this->assertSame(1, substr_count($html, '<time datetime="2026-10-16">'));
        $this->assertStringContainsString('name="robots" content="index,follow,max-image-preview:large"', $html);
        $this->assertStringContainsString('property="og:image" content="https://klartfoto.se/images/guides/exempel-rapport.jpg?v='.config('klartfoto.demo_asset_version').'"', $html);
        $this->assertStringContainsString('href="/exempel-rapport"', $html);
        $this->assertStringContainsString('href="/integritetspolicy"', $html);
        $this->assertStringContainsString('href="/for-stadforetag"', $html);
        $this->assertStringContainsString('https://www.imy.se/verksamhet/dataskydd/det-har-galler-enligt-gdpr/de-registrerades-rattigheter', $html);
        $this->assertStringContainsString('Du måste informera kunden om att bilder tas.', $html);
        $this->assertStringContainsString('Spara originalbilderna oskurna.', $html);
        $this->assertStringContainsString('Slipp ord mot ord när kunden reklamerar.', $html);
        $this->assertStringNotContainsString('rel="alternate"', $html);
        $this->assertContains('Article', $types);
        $this->assertContains('FAQPage', $types);
        $this->assertContains('BreadcrumbList', $types);
        $this->assertIsArray($article);
        $this->assertSame('Fotodokumentation vid städning med före- och efterbilder', $article['headline']);
        $this->assertSame('2026-10-16', $article['datePublished']);
        $this->assertSame('2026-10-16', $article['dateModified']);
        $this->assertSame('sv-SE', $article['inLanguage']);
        $this->assertIsArray($breadcrumb);
        $this->assertCount(3, $breadcrumb['itemListElement']);
        $this->assertSame('Startsida', $breadcrumb['itemListElement'][0]['name']);
        $this->assertSame('https://klartfoto.se/guider/fotodokumentation-stadning', $breadcrumb['itemListElement'][2]['item']);
        $this->assertSame(['@id' => 'https://klartfoto.se/#organization'], $article['author']);
        $this->assertSame('https://klartfoto.se/images/guides/exempel-rapport.jpg?v='.config('klartfoto.demo_asset_version'), $article['image']);
        $this->assertNotContains('Person', $types);
        $this->assertIsArray($faq);
        $this->assertCount(6, $faq['mainEntity']);
        $this->assertInstanceOf(DOMElement::class, $faqNode);

        foreach ($faq['mainEntity'] as $question) {
            $this->assertStringContainsString($question['name'], $faqNode->textContent ?? '');
            $this->assertStringContainsString($question['acceptedAnswer']['text'], $faqNode->textContent ?? '');
        }

        $articleNode = $xpath->query('//article[contains(concat(" ", normalize-space(@class), " "), " guide-article ")]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $articleNode);
        $articleHtml = $document->saveHTML($articleNode);
        $this->assertIsString($articleHtml);
        $this->assertStringContainsString('Av Klartfoto-teamet', $articleHtml);
        $this->assertStringContainsString('>Startsida</a>', $articleHtml);
        $this->assertStringContainsString('href="/register"', $articleHtml);
        $this->assertSame(2, substr_count($articleHtml, 'href="/register"'));
        $this->assertSame(2, substr_count($articleHtml, 'Kom igång gratis'));
        $this->assertStringNotContainsString('href="/#signup"', $articleHtml);
        $this->assertStringNotContainsString('Anmäl intresse', $articleHtml);
        $this->assertStringNotContainsString('↗', $articleHtml);
        $this->assertStringNotContainsString('reklamation fastnar', $articleHtml);
        $this->assertStringNotContainsString('Bilden avgör inte hela avtalet', $articleHtml);
        $this->assertStringNotContainsString('innan länken går', $articleHtml);
        $this->assertStringContainsString('<table class="guide-steps">', $articleHtml);
        $this->assertStringContainsString('images/guides/exempel-rapport.jpg?v='.config('klartfoto.demo_asset_version'), $articleHtml);
        $this->assertStringContainsString('images/guides/kok-fore.svg?v='.config('klartfoto.demo_asset_version'), $articleHtml);
        $this->assertStringContainsString('images/guides/badrum-efter.svg?v='.config('klartfoto.demo_asset_version'), $articleHtml);
        $this->assertStringContainsString('images/guides/hall-fore.svg?v='.config('klartfoto.demo_asset_version'), $articleHtml);
        $this->assertGreaterThanOrEqual(4, substr_count($articleHtml, '>Exempel<'));
        $words = preg_split('/\s+/u', trim($articleNode->textContent ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        $this->assertIsArray($words);
        $this->assertGreaterThanOrEqual(1000, count($words));
        $this->assertLessThanOrEqual(1400, count($words));
    }

    public function test_guide_index_lists_the_cleaning_photo_article(): void
    {
        $html = $this->get('/guider')->assertOk()->getContent();
        $xpath = new DOMXPath($this->htmlDocument($html));
        $h1 = $xpath->query('//h1');

        $this->assertNotFalse($h1);
        $this->assertSame(1, $h1->length);
        $this->assertInstanceOf(DOMElement::class, $h1->item(0));
        $this->assertSame('Guider', trim($h1->item(0)->textContent ?? ''));
        $this->assertStringContainsString('<html lang="sv">', $html);
        $this->assertStringContainsString('<title>Guider för städföretag | Klartfoto</title>', $html);
        $this->assertStringContainsString('name="description" content="Praktiska guider för städföretag om fotodokumentation, före- och efterbilder och städrapporter att dela med kunden."', $html);
        $this->assertStringContainsString('rel="canonical" href="https://klartfoto.se/guider"', $html);
        $this->assertStringContainsString('property="og:type" content="website"', $html);
        $this->assertStringContainsString('>Startsida</a>', $html);
        $this->assertStringContainsString('href="/exempel-rapport"', $html);
        $this->assertStringContainsString('Se exempelrapporten', $html);
        $this->assertStringContainsString('href="/guider/fotodokumentation-stadning"', $html);
        $this->assertStringContainsString('Fotodokumentation vid städning med före- och efterbilder', $html);

        $this->get('/guider/saknas')->assertNotFound();
        $this->get('/en/guider')->assertNotFound();
    }

    public function test_marketing_calls_to_action_open_registration_without_an_arrow(): void
    {
        foreach (['/', '/guider', '/guider/fotodokumentation-stadning', '/for-stadforetag', '/exempel-rapport'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString('class="mobile-menu-signup" href="/register"', $html);
            $this->assertStringContainsString('class="button button-small button-lime" href="/register"', $html);
            $this->assertStringContainsString('class="footer-cta" href="/register"', $html);
            $this->assertStringContainsString('Kom igång gratis', $html);
            $this->assertStringNotContainsString('Anmäl intresse', $html);
            $this->assertInternalLinksOmitTheExternalMark($html);
        }

        $home = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('id="signup"', $home);
        $this->assertStringContainsString('class="button button-lime" href="/register">Kom igång gratis</a>', $home);
        $this->assertStringNotContainsString('<form class="signup-form"', $home);
    }

    public function test_swedish_cleaning_page_and_footer_link_to_the_photo_guide(): void
    {
        $this->get('/for-stadforetag')->assertOk()
            ->assertSee('href="/guider/fotodokumentation-stadning"', false)
            ->assertSee('Läs guiden om fotodokumentation vid städning', false)
            ->assertSee('>Guider</a>', false);

        $this->get('/')->assertOk()->assertSee('>Guider</a>', false);
        $this->get('/en/for-cleaning-companies')->assertOk()->assertDontSee('/guider', false);
        $this->get('/en')->assertOk()->assertDontSee('>Guider</a>', false);
    }

    public function test_homepage_video_object_uses_the_swedish_thumbnail_and_keeps_the_page_schema(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $documents = array_map(function (string $json): array {
            $decoded = json_decode($json, true);
            $this->assertIsArray($decoded);

            return $decoded;
        }, $matches[1]);
        $video = collect($documents)->firstWhere('@type', 'VideoObject');
        $pageSchema = collect($documents)->first(fn (array $document): bool => array_key_exists('@graph', $document));

        $this->assertIsArray($pageSchema);
        $this->assertIsArray($video);
        $this->assertSame('https://schema.org', $video['@context']);
        $this->assertSame('Ett uppdrag. Från början till rapport.', $video['name']);
        $this->assertSame('Se hur du lägger till ett uppdrag, laddar upp bilder och delar rapporten med kunden.', $video['description']);
        $this->assertSame(url('/images/video-miniatura.jpg'), $video['thumbnailUrl']);
        $this->assertSame('2026-10-16T14:48:02+02:00', $video['uploadDate']);
        $this->assertSame('PT1M', $video['duration']);
        $this->assertSame('https://iframe.mediadelivery.net/embed/781538/b62dea40-8f45-42cd-a9df-1d770297f50f', $video['embedUrl']);
        $this->assertSame('sv', $video['inLanguage']);
        $this->assertArrayNotHasKey('contentUrl', $video);
        $this->assertSame(1, substr_count($html, 'property="og:image"'));
        $this->assertStringContainsString('property="og:image" content="'.url('/images/video-miniatura.jpg').'"', $html);

        $image = getimagesize(public_path('images/video-miniatura.jpg'));
        $this->assertIsArray($image);
        $this->assertSame(1280, $image[0]);
        $this->assertSame(720, $image[1]);
        $this->assertSame('image/jpeg', $image['mime']);
    }

    /**
     * @return array<string, mixed>
     */
    private function structuredData(string $html): array
    {
        $this->assertSame(1, preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches));

        $decoded = json_decode(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5), true);

        $this->assertIsArray($decoded);

        return $decoded;
    }

    private function assertInternalLinksOmitTheExternalMark(string $html): void
    {
        $anchors = (new DOMXPath($this->htmlDocument($html)))->query('//a[@href]');
        $this->assertNotFalse($anchors);

        foreach ($anchors as $anchor) {
            $this->assertInstanceOf(DOMElement::class, $anchor);
            $href = $anchor->getAttribute('href');

            if (preg_match('#^(https?:)?//#', $href) === 1 || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                continue;
            }

            $this->assertStringNotContainsString('↗', $anchor->textContent ?? '', $href);
        }
    }

    private function htmlDocument(string $html): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document;
    }
}
