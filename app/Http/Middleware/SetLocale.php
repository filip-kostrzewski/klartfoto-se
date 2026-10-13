<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locales = config('marketing.locales', []);
        $segments = $request->segments();
        $locale = $request->route()?->getName();

        if (is_string($locale) && preg_match('/^site\.([a-z]{2})\./', $locale, $matches)) {
            $locale = $matches[1];
        } else {
            $locale = $segments[0] ?? config('marketing.default_locale', 'sv');
        }

        if (! array_key_exists($locale, $locales)) {
            $locale = config('marketing.default_locale', 'sv');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
