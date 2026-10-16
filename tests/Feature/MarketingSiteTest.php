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
            ->assertSee('Visa vad som blev gjort', false)
            ->assertSee('content="sv_SE"', false)
            ->assertSee('https://klartfoto.se/', false)
            ->assertDontSee('<link rel="canonical" href="https://klartfoto.test/', false)
            ->assertDontSee('property="og:url" content="https://klartfoto.test/', false);
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

                if (in_array($locale, config('marketing.indexable_locales'), true)) {
                    if ($pageKey === 'privacy') {
                        $this->assertStringContainsString('name="robots" content="noindex,nofollow"', $html);
                    }
                } else {
                    $this->assertStringContainsString('name="robots" content="noindex,follow"', $html);
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

    public function test_homepage_cta_links_to_signup_and_the_sample_report_with_optional_contact(): void
    {
        config([
            'marketing.waitlist_enabled' => false,
            'marketing.data_contact' => 'hello@example.com',
        ]);

        $this->get('/')->assertOk()
            ->assertSee('mailto:hello@example.com', false)
            ->assertSee(__('marketing.home.signup.closed_contact', [], 'sv'))
            ->assertSee('href="/register"', false)
            ->assertSee('href="/exempel-rapport"', false)
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
            ->assertSee('Kom igång gratis', false)
            ->assertSee('href="/register"', false)
            ->assertSee('images/demo/kitchen-before-small.webp?v='.config('klartfoto.demo_asset_version'), false)
            ->assertSee('images/demo/kitchen-after-small.webp?v='.config('klartfoto.demo_asset_version'), false)
            ->assertSee('AI-demo · inte ett kundfoto', false)
            ->assertSee('images/renovation-detail-small.webp?v='.config('klartfoto.demo_asset_version'), false)
            ->assertSee('images/property-detail-small.webp?v='.config('klartfoto.demo_asset_version'), false)
            ->assertDontSee('images/demo/kitchen-before.svg', false)
            ->assertSee('Prodi Digital AB, org.nr 559214-9370, Tegelbrukets väg 41, 195 59 Märsta', false)
            ->assertSee('href="/integritetspolicy"', false);

        $this->get('/exempel-rapport')
            ->assertOk()
            ->assertSee('images/demo/kitchen-before-small.webp?v='.config('klartfoto.demo_asset_version'), false)
            ->assertSee('images/demo/kitchen-after-small.webp?v='.config('klartfoto.demo_asset_version'), false)
            ->assertSee('AI-demo · inte ett kundfoto', false)
            ->assertDontSee('checklista', false);

        $this->get('/for-stadforetag')
            ->assertOk()
            ->assertSee('images/cleaning-detail.webp?v='.config('klartfoto.demo_asset_version'), false)
            ->assertSee('AI-genererat demofoto av en person som torkar av en köksbänk', false);

        config(['klartfoto.contact_email' => null]);
        $this->get('/integritetspolicy')
            ->assertOk()
            ->assertSee('Prodi Digital AB', false)
            ->assertSee('foton av kundens hem', false)
            ->assertDontSee('mailto:', false);

        config(['klartfoto.contact_email' => 'privacy@klartfoto.se']);
        $this->get('/integritetspolicy')
            ->assertOk()
            ->assertSee('mailto:privacy@klartfoto.se', false);

        config(['klartfoto.pricing_visible' => false]);
        $this->get('/')
            ->assertOk()
            ->assertDontSee('id="priser"', false)
            ->assertDontSee('290 kr/mån', false);
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
        $this->assertSame('', trim($descriptionNode->textContent ?? ''));
        $this->assertSame($description, $descriptionNode->getAttribute('aria-label'));
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

        $this->assertSame(14, substr_count($xml, '<url>'));
        $this->assertStringContainsString('<loc>https://klartfoto.example/</loc>', $xml);
        $this->assertStringContainsString('<loc>https://klartfoto.example/exempel-rapport</loc>', $xml);
        $this->assertStringContainsString('<loc>https://klartfoto.example/integritetspolicy</loc>', $xml);
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
        $this->assertStringNotContainsString('<lastmod>', $xml);
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
        $this->assertStringContainsString('<loc>https://klartfoto.se/guider/fotodokumentation-stadning</loc>', $sitemap);
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
        $this->assertStringContainsString('<time datetime="2026-10-16">Uppdaterad 16 oktober 2026</time>', $html);
        $this->assertStringContainsString('name="robots" content="index,follow,max-image-preview:large"', $html);
        $this->assertStringContainsString('href="/register"', $html);
        $this->assertStringContainsString('href="/exempel-rapport"', $html);
        $this->assertStringContainsString('href="/integritetspolicy"', $html);
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
        $this->assertSame('https://klartfoto.se/guider/fotodokumentation-stadning', $breadcrumb['itemListElement'][2]['item']);
        $this->assertIsArray($faq);
        $this->assertCount(6, $faq['mainEntity']);
        $this->assertInstanceOf(DOMElement::class, $faqNode);

        foreach ($faq['mainEntity'] as $question) {
            $this->assertStringContainsString($question['name'], $faqNode->textContent ?? '');
            $this->assertStringContainsString($question['acceptedAnswer']['text'], $faqNode->textContent ?? '');
        }

        $articleNode = $xpath->query('//article[contains(concat(" ", normalize-space(@class), " "), " guide-article ")]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $articleNode);
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
        $this->assertStringContainsString('href="/guider/fotodokumentation-stadning"', $html);
        $this->assertStringContainsString('Fotodokumentation vid städning med före- och efterbilder', $html);

        $this->get('/guider/saknas')->assertNotFound();
        $this->get('/en/guider')->assertNotFound();
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
