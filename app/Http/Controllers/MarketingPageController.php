<?php

namespace App\Http\Controllers;

use App\LocalizedUrl;
use App\SiteSeo;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class MarketingPageController extends Controller
{
    private const INDEXABLE_PAGES = ['home', 'cleaning', 'renovation', 'property', 'report', 'privacy', 'terms', 'contact'];

    private const GUIDE_INDEX_PATH = '/guider';

    private const CLEANING_GUIDE_PATH = '/guider/fotodokumentation-stadning';

    public function home(): View
    {
        return $this->page('home', 'pages.home');
    }

    public function cleaning(): View
    {
        return $this->industry('cleaning');
    }

    public function renovation(): View
    {
        return $this->industry('renovation');
    }

    public function property(): View
    {
        return $this->industry('property');
    }

    public function sampleReport(): View
    {
        return $this->page('report', 'pages.sample-report');
    }

    public function privacy(): View
    {
        return $this->page('privacy', 'pages.privacy');
    }

    public function terms(): View
    {
        return $this->page('terms', 'pages.terms');
    }

    public function contact(): View
    {
        return $this->page('contact', 'pages.contact');
    }

    public function guideIndex(): View
    {
        $content = $this->prepareGuide('index');
        $canonical = SiteSeo::canonical(self::GUIDE_INDEX_PATH);
        $page = $this->pageData(
            $content,
            'guides',
            $canonical,
            $this->guideIsIndexable(),
            SiteSeo::structuredData(
                $canonical,
                (string) $content['breadcrumb'],
                true,
                app()->getLocale(),
                LocalizedUrl::route('home', app()->getLocale()),
            ),
        );

        return view('pages.guides.index', [
            ...$this->guideViewData($page),
            'featured' => $this->prepareGuide('cleaning_photos'),
        ]);
    }

    public function cleaningPhotoGuide(): View
    {
        $content = $this->prepareGuide('cleaning_photos');
        $content['index_label'] = (string) trans('guides.index.breadcrumb');
        $canonical = SiteSeo::canonical(self::CLEANING_GUIDE_PATH);
        $homeUrl = LocalizedUrl::route('home', app()->getLocale());
        $page = $this->pageData($content, 'guides', $canonical, $this->guideIsIndexable(), []);
        $page['og_type'] = 'article';
        $page['og_image'] = $page['report_image_url_absolute'];
        $page['og_image_alt'] = $page['report_image']['alt'];
        $page['schema'] = SiteSeo::articleDocument(
            $canonical,
            (string) $page['h1'],
            (string) $page['description'],
            (string) $page['published_on'],
            (string) $page['updated_on'],
            app()->getLocale(),
            $homeUrl,
            [
                ['name' => (string) $page['home_crumb'], 'item' => $homeUrl],
                ['name' => (string) $page['index_label'], 'item' => SiteSeo::canonical(self::GUIDE_INDEX_PATH)],
                ['name' => (string) $page['breadcrumb'], 'item' => $canonical],
            ],
            $page['faqs'],
            $page['report_image_url_absolute'],
        );

        return view('pages.guides.show', $this->guideViewData($page));
    }

    public function notFound(): Response
    {
        $copy = trans('marketing.not_found');
        $page = $this->pageData($copy, 'not-found', SiteSeo::canonical(request()->getPathInfo()), false);

        return response()->view('errors.404', [
            'page' => $page,
            'copy' => trans('marketing'),
            'locale' => app()->getLocale(),
            'language_options' => config('marketing.locales'),
        ], 404);
    }

    public function sitemap(): Response
    {
        abort_unless(SiteSeo::isIndexable(), 404);

        $urls = [];

        foreach (config('marketing.indexable_locales', []) as $locale) {
            foreach (self::INDEXABLE_PAGES as $page) {
                if ($page === 'terms' && $locale !== 'sv') {
                    continue;
                }

                $urls[] = ['loc' => LocalizedUrl::route($page, $locale)];
            }
        }

        $guideUpdatedOn = (string) trans('guides.cleaning_photos.updated_on');

        foreach ([self::GUIDE_INDEX_PATH, self::CLEANING_GUIDE_PATH] as $path) {
            $urls[] = ['loc' => SiteSeo::canonical($path), 'lastmod' => $guideUpdatedOn];
        }

        return response()
            ->view('seo.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        if (! SiteSeo::isIndexable()) {
            $contents = "User-agent: *\nDisallow: /\n";
        } else {
            $contents = "User-agent: *\nAllow: /\n\nSitemap: ".SiteSeo::canonical('/sitemap.xml')."\n";
        }

        return new Response($contents, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function industry(string $key): View
    {
        abort_unless(in_array($key, ['cleaning', 'renovation', 'property'], true), 404);

        return $this->page($key, 'pages.industry', $key);
    }

    private function page(string $pageKey, string $view, ?string $industryKey = null): View
    {
        $contentKey = $industryKey === null ? $pageKey : "industries.{$industryKey}";
        $content = trans("marketing.{$contentKey}");
        $canonical = LocalizedUrl::route($pageKey, app()->getLocale());
        $indexable = $this->localeIsIndexable()
            && SiteSeo::isIndexable()
            && in_array($pageKey, self::INDEXABLE_PAGES, true);
        $breadcrumb = $content['breadcrumb'] ?? $content['h1'] ?? $content['title'];
        $schema = SiteSeo::structuredData(
            $canonical,
            $breadcrumb,
            $pageKey !== 'home',
            app()->getLocale(),
            LocalizedUrl::route('home', app()->getLocale()),
        );
        $page = $this->pageData($content, $pageKey, $canonical, $indexable, $schema);
        $page['alternates'] = $pageKey === 'terms'
            ? ['sv' => $canonical, 'x-default' => $canonical]
            : LocalizedUrl::alternates($pageKey);
        $data = [
            'page' => $page,
            'copy' => trans('marketing'),
            'locale' => app()->getLocale(),
            'language_options' => config('marketing.locales'),
        ];

        if ($pageKey === 'report') {
            [$data['companyLogoUrl'], $data['companyLogoAlt']] = $this->sampleReportLogo();
        }

        return view($view, $data);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function sampleReportLogo(): array
    {
        $company = request()->user()?->company;

        if ($company !== null && filled($company->logo_path) && $company->allowsTeamFeatures()) {
            return [route('settings.logo', absolute: false), $company->name];
        }

        return [asset('images/demo/company-logo.svg').'?v='.config('klartfoto.demo_asset_version'), (string) trans('marketing.report.logo_alt')];
    }

    private function localeIsIndexable(): bool
    {
        return in_array(app()->getLocale(), config('marketing.indexable_locales', []), true);
    }

    private function guideIsIndexable(): bool
    {
        return $this->localeIsIndexable() && SiteSeo::isIndexable();
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareGuide(string $key): array
    {
        $content = trans("guides.{$key}");

        if (! is_array($content) || ! isset($content['title'], $content['description'], $content['h1'])) {
            abort(404);
        }

        $content['home_crumb'] = (string) trans('guides.home_crumb');

        if (isset($content['intro_more'])) {
            $content['intro_more'] = $this->withInlineLinks((string) $content['intro_more']);
        }

        if (isset($content['sections']) && is_array($content['sections'])) {
            foreach ($content['sections'] as $index => $section) {
                if (! is_array($section)) {
                    continue;
                }

                $paragraphs = [];

                foreach ($section['paragraphs'] ?? [] as $paragraph) {
                    $paragraphs[] = $this->withInlineLinks((string) $paragraph);
                }

                $content['sections'][$index]['paragraphs'] = $paragraphs;
            }
        }

        if ($key === 'cleaning_photos') {
            $content = $this->withGuideImages($content);
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function withGuideImages(array $content): array
    {
        $version = config('klartfoto.demo_asset_version');
        $url = function (string $path) use ($version): string {
            return asset($path).'?v='.$version;
        };
        $files = [
            ['images/guides/kok-fore.svg', 'images/guides/kok-efter.svg'],
            ['images/guides/badrum-fore.svg', 'images/guides/badrum-efter.svg'],
            ['images/guides/hall-fore.svg', 'images/guides/hall-efter.svg'],
        ];

        foreach ($files as $index => $pair) {
            if (! isset($content['pairs'][$index]) || ! is_array($content['pairs'][$index])) {
                continue;
            }

            $content['pairs'][$index]['before_url'] = $url($pair[0]);
            $content['pairs'][$index]['after_url'] = $url($pair[1]);
        }

        $content['report_image_url'] = $url('images/guides/exempel-rapport.jpg');
        $content['report_image_url_absolute'] = SiteSeo::baseUrl().'/images/guides/exempel-rapport.jpg?v='.$version;

        return $content;
    }

    private function withInlineLinks(string $paragraph): string
    {
        $locale = app()->getLocale();

        return str_replace(
            [':report', ':privacy', ':cleaning', ':imy'],
            [
                '<a href="'.e(LocalizedUrl::route('report', $locale, false)).'">'.e((string) trans('guides.links.report')).'</a>',
                '<a href="'.e(LocalizedUrl::route('privacy', $locale, false)).'">'.e((string) trans('guides.links.privacy')).'</a>',
                '<a href="'.e(LocalizedUrl::route('cleaning', $locale, false)).'">'.e((string) trans('guides.links.cleaning')).'</a>',
                '<a href="https://www.imy.se/verksamhet/dataskydd/det-har-galler-enligt-gdpr/de-registrerades-rattigheter">'.e((string) trans('guides.links.imy')).'</a>',
            ],
            e($paragraph),
        );
    }

    /**
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>
     */
    private function guideViewData(array $page): array
    {
        return [
            'page' => $page,
            'copy' => trans('marketing'),
            'locale' => app()->getLocale(),
            'language_options' => config('marketing.locales'),
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function pageData(array $content, string $pageKey, string $canonical, bool $indexable, array $schema = []): array
    {
        $brand = (string) config('marketing.brand');
        $locale = app()->getLocale();
        $localeConfig = config("marketing.locales.{$locale}");

        $content['brand'] = $brand;
        $content['title'] = str_replace(':brand', $brand, (string) $content['title']);
        $content['description'] = str_replace(':brand', $brand, (string) $content['description']);
        $content['canonical'] = $canonical;
        $content['is_indexable'] = $indexable;
        $content['robots'] = $this->robotsDirective($indexable);
        $content['schema'] = $schema;
        $content['route_key'] = $pageKey;
        $content['locale'] = $locale;
        $content['og_locale'] = $localeConfig['og'];
        $content['controller_name'] = config('marketing.data_controller')
            ?: ($pageKey === 'privacy' ? 'Prodi Digital AB' : null);
        $content['contact_email'] = config('marketing.data_contact')
            ?: ($pageKey === 'privacy' ? config('klartfoto.provider_contact_email') : null);
        $content['provider_contact_email'] = config('klartfoto.provider_contact_email');
        $content['waitlist_open'] = SiteSeo::waitlistIsOpen();

        return $content;
    }

    private function robotsDirective(bool $indexable): string
    {
        if (! $this->localeIsIndexable()) {
            return 'noindex,follow';
        }

        return $indexable
            ? 'index,follow,max-image-preview:large'
            : 'noindex,nofollow';
    }
}
