<?php

namespace App\Http\Controllers;

use App\LocalizedUrl;
use App\SiteSeo;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class MarketingPageController extends Controller
{
    private const INDEXABLE_PAGES = ['home', 'cleaning', 'renovation', 'property', 'report', 'privacy'];

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
                $urls[] = LocalizedUrl::route($page, $locale);
            }
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
        $page['alternates'] = LocalizedUrl::alternates($pageKey);
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

        return [asset('images/demo/company-logo.svg'), (string) trans('marketing.report.logo_alt')];
    }

    private function localeIsIndexable(): bool
    {
        return in_array(app()->getLocale(), config('marketing.indexable_locales', []), true);
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
        $content['controller_name'] = config('marketing.data_controller');
        $content['contact_email'] = config('marketing.data_contact');
        $content['policy_contact_email'] = config('klartfoto.contact_email');
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
