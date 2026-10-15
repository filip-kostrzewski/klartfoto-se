<?php

namespace App;

class GoogleAnalytics
{
    public const CONSENT_COOKIE = 'klartfoto_cookie_consent';

    public static function measurementId(): ?string
    {
        $configured = config('services.google_analytics.measurement_id');

        if (! is_string($configured) || preg_match('/\AG-[A-Z0-9]+\z/', $configured) !== 1) {
            return null;
        }

        return $configured;
    }
}
