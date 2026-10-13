<?php

namespace App;

class SiteSeo
{
    public static function baseUrl(): string
    {
        return rtrim((string) config('marketing.base_url', config('app.url')), '/');
    }

    public static function canonical(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: '/';
        $path = '/'.ltrim($path, '/');

        return self::baseUrl().($path === '//' ? '/' : $path);
    }

    public static function isIndexable(): bool
    {
        $baseUrl = self::baseUrl();
        $host = parse_url($baseUrl, PHP_URL_HOST);

        return app()->environment('production')
            && (bool) config('marketing.indexing_enabled')
            && parse_url($baseUrl, PHP_URL_SCHEME) === 'https'
            && is_string($host)
            && ! preg_match('/(^localhost$|^127\.|\.test$|\.local$)/i', $host);
    }

    public static function waitlistIsOpen(): bool
    {
        if (! (bool) config('marketing.waitlist_enabled')) {
            return false;
        }

        if (! app()->environment('production')) {
            return true;
        }

        return filled(config('marketing.data_controller'))
            && filter_var(config('marketing.data_contact'), FILTER_VALIDATE_EMAIL) !== false
            && filled(config('marketing.data_hosting_provider'))
            && (bool) config('marketing.retention_purge_enabled');
    }

    /**
     * @return array<string, mixed>
     */
    public static function structuredData(
        string $canonical,
        string $pageName,
        bool $includeBreadcrumb,
        string $locale,
        string $homeUrl,
    ): array {
        $baseUrl = self::canonical('/');
        $brand = (string) config('marketing.brand');
        $organizationId = $baseUrl.'#organization';

        $graph = [
            [
                '@type' => 'Organization',
                '@id' => $organizationId,
                'name' => $brand,
                'url' => $baseUrl,
            ],
            [
                '@type' => 'WebSite',
                '@id' => $baseUrl.'#website',
                'url' => $baseUrl,
                'name' => $brand,
                'inLanguage' => config("marketing.locales.{$locale}.schema", $locale),
                'publisher' => ['@id' => $organizationId],
            ],
        ];

        if ($includeBreadcrumb) {
            $graph[] = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => trans('marketing.nav.brand_home', ['brand' => $brand]),
                        'item' => $homeUrl,
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => $pageName,
                        'item' => $canonical,
                    ],
                ],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }
}
