<?php

namespace App;

class LocalizedUrl
{
    public static function route(string $page, ?string $locale = null, bool $absolute = true): string
    {
        $locale ??= app()->getLocale();
        abort_unless(isset(config('marketing.locales')[$locale]), 404);

        $path = route("site.{$locale}.{$page}", [], false);

        return $absolute ? SiteSeo::canonical($path) : $path;
    }

    /**
     * @return array<string, string>
     */
    public static function alternates(string $page): array
    {
        $alternates = [];

        foreach (config('marketing.locales') as $locale => $_metadata) {
            $alternates[$locale] = self::route($page, $locale);
        }

        $alternates['x-default'] = self::route($page, 'sv');

        return $alternates;
    }
}
