<?php

namespace App;

class SiteSeo
{
    public const PUBLIC_BASE_URL = 'https://klartfoto.se';

    public static function baseUrl(): string
    {
        return self::publishableBaseUrl((string) config('app.url', self::PUBLIC_BASE_URL))
            ?? self::PUBLIC_BASE_URL;
    }

    public static function canonical(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: '/';
        $path = '/'.ltrim($path, '/');

        return self::baseUrl().($path === '//' ? '/' : $path);
    }

    public static function isIndexable(): bool
    {
        return app()->environment('production')
            && self::publishableBaseUrl(self::baseUrl()) !== null;
    }

    /**
     * Accept only an https origin whose host can be published.
     * Local hosts are rejected so a development APP_URL cannot leak into canonicals.
     */
    private static function publishableBaseUrl(string $url): ?string
    {
        $parts = parse_url(trim($url));

        if (! is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https' || $host === '' || self::isLocalHost($host)) {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return 'https://'.$host.$port;
    }

    private static function isLocalHost(string $host): bool
    {
        return (bool) preg_match('/(^localhost$|^127\.|\.local$|\.test$)/i', $host);
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
