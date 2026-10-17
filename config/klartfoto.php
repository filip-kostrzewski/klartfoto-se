<?php

$contactEmail = env('KLARTFOTO_CONTACT_EMAIL');
$billingNotifyEmail = env('KLARTFOTO_BILLING_NOTIFY_EMAIL');
$providerContactEmail = $contactEmail && filter_var($contactEmail, FILTER_VALIDATE_EMAIL)
    ? $contactEmail
    : 'kontakt@klartfoto.se';

return [
    'trial_days' => (int) env('KLARTFOTO_TRIAL_DAYS', 14),
    'trial_ending_reminder_days' => 3,
    'contact_email' => $contactEmail && filter_var($contactEmail, FILTER_VALIDATE_EMAIL)
        ? $contactEmail
        : null,
    'provider_contact_email' => $providerContactEmail,
    'billing_notify_email' => $billingNotifyEmail && filter_var($billingNotifyEmail, FILTER_VALIDATE_EMAIL)
        ? $billingNotifyEmail
        : ($contactEmail && filter_var($contactEmail, FILTER_VALIDATE_EMAIL) ? $contactEmail : null),
    'pricing_visible' => filter_var(env('KLARTFOTO_PRICING_VISIBLE', true), FILTER_VALIDATE_BOOL),
    'demo_asset_version' => 3,
    'photo_max_edge' => 1600,
    'logo_max_edge' => 800,
    'start_seat_limit' => 1,
    'team_seat_limit' => 10,
    'prices' => [
        'start' => ['month' => 390, 'year' => 3900],
        'team' => ['month' => 790, 'year' => 7900],
    ],
    'stripe_price_start' => env('STRIPE_PRICE_START'),
    'stripe_price_team' => env('STRIPE_PRICE_TEAM'),
];
