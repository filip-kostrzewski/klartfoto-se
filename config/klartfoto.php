<?php

return [
    'trial_days' => (int) env('KLARTFOTO_TRIAL_DAYS', 30),
    'trial_ends_at' => env('KLARTFOTO_TRIAL_ENDS_AT'),
    'contact_email' => ($email = env('KLARTFOTO_CONTACT_EMAIL')) && filter_var($email, FILTER_VALIDATE_EMAIL)
        ? $email
        : null,
    'pricing_visible' => filter_var(env('KLARTFOTO_PRICING_VISIBLE', true), FILTER_VALIDATE_BOOL),
    'demo_asset_version' => 2,
    'photo_max_edge' => 1600,
    'logo_max_edge' => 800,
    'start_seat_limit' => 1,
    'team_seat_limit' => 10,
    'stripe_price_start' => env('STRIPE_PRICE_START'),
    'stripe_price_team' => env('STRIPE_PRICE_TEAM'),
];
