<!doctype html>
<html lang="{{ $page['locale'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#111715">
    <title>{{ $page['title'] }}</title>
    <meta name="description" content="{{ $page['description'] }}">
    <meta name="robots" content="{{ $page['robots'] ?? ($page['is_indexable'] ? 'index,follow,max-image-preview:large' : 'noindex,nofollow') }}">
    <link rel="canonical" href="{{ $page['canonical'] }}">
    @foreach (($page['alternates'] ?? []) as $hreflang => $url)
        <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $url }}">
    @endforeach
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <meta property="og:type" content="{{ $page['og_type'] ?? 'website' }}">
    @if (! empty($page['published_on']))
        <meta property="article:published_time" content="{{ $page['published_on'] }}">
        <meta property="article:modified_time" content="{{ $page['updated_on'] ?? $page['published_on'] }}">
    @endif
    <meta property="og:locale" content="{{ $page['og_locale'] }}">
    <meta property="og:site_name" content="{{ $page['brand'] }}">
    <meta property="og:title" content="{{ $page['title'] }}">
    <meta property="og:description" content="{{ $page['description'] }}">
    <meta property="og:url" content="{{ $page['canonical'] }}">
    @if (! empty($page['og_image']))
        <meta property="og:image" content="{{ $page['og_image'] }}">
        @if (! empty($page['og_image_alt']))
            <meta property="og:image:alt" content="{{ $page['og_image_alt'] }}">
        @endif
    @endif
    @if (! empty($page['schema']))
        <script type="application/ld+json">@json($page['schema'])</script>
    @endif
    @vite(['resources/css/site.css', 'resources/js/site.js'])
    @include('partials.google-analytics')
</head>
<body>
    <a class="skip-link" href="#main">{{ $copy['nav']['skip'] }}</a>

    <header class="site-header">
        <div class="site-header-inner">
            <a class="brand" href="{{ \App\LocalizedUrl::route('home', $locale, false) }}" aria-label="{{ __('marketing.nav.brand_home', ['brand' => $page['brand']]) }}">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 32 32" fill="none">
                        <path d="M5 5h10v22H5z" fill="currentColor"/>
                        <path d="M17 5h10v10H17z" fill="#D7FF68"/>
                        <path d="M17 17h10v10H17z" fill="currentColor"/>
                    </svg>
                </span>
                <span class="brand-name">{{ $page['brand'] }}</span>
            </a>

            <nav class="desktop-nav" aria-label="{{ $copy['nav']['main'] }}">
                <a href="{{ \App\LocalizedUrl::route('home', $locale, false) }}#for-who">{{ $copy['nav']['who'] }}</a>
                <a href="{{ \App\LocalizedUrl::route('report', $locale, false) }}">{{ $copy['nav']['report'] }}</a>
                <a href="{{ \App\LocalizedUrl::route('home', $locale, false) }}#how-it-works">{{ $copy['nav']['how'] }}</a>
                @if (config('klartfoto.pricing_visible'))
                    <a href="{{ \App\LocalizedUrl::route('home', $locale, false) }}#priser">{{ $copy['nav']['pricing'] }}</a>
                @endif
                <a href="{{ \App\LocalizedUrl::route('contact', $locale, false) }}">{{ $copy['nav']['contact'] }}</a>
            </nav>

            <div class="header-actions">
                <details class="language-menu">
                    <summary aria-label="{{ $copy['nav']['choose_language'] }}">
                        <span aria-hidden="true">{{ strtoupper($locale) }}</span>
                        <span class="language-menu-label">{{ $copy['nav']['language'] }}</span>
                    </summary>
                    <nav aria-label="{{ $copy['nav']['choose_language'] }}">
                        @foreach ($language_options as $optionLocale => $option)
                            @php
                                $languagePage = in_array($page['route_key'] ?? '', ['home', 'cleaning', 'renovation', 'property', 'report', 'privacy', 'contact'], true)
                                    ? $page['route_key']
                                    : 'home';
                            @endphp
                            <a href="{{ \App\LocalizedUrl::route($languagePage, $optionLocale, false) }}" lang="{{ $optionLocale }}" @if ($locale === $optionLocale) aria-current="page" @endif>{{ $option['name'] }}</a>
                        @endforeach
                    </nav>
                </details>
                <details class="mobile-menu">
                    <summary aria-label="{{ $copy['nav']['menu'] }}">{{ $copy['nav']['menu'] }} <span aria-hidden="true">+</span></summary>
                    <nav aria-label="{{ $copy['nav']['main'] }}">
                        <a href="{{ \App\LocalizedUrl::route('home', $locale, false) }}#for-who">{{ $copy['nav']['who'] }}</a>
                        <a href="{{ \App\LocalizedUrl::route('report', $locale, false) }}">{{ $copy['nav']['report'] }}</a>
                        <a href="{{ \App\LocalizedUrl::route('home', $locale, false) }}#how-it-works">{{ $copy['nav']['how'] }}</a>
                        @if (config('klartfoto.pricing_visible'))
                            <a href="{{ \App\LocalizedUrl::route('home', $locale, false) }}#priser">{{ $copy['nav']['pricing'] }}</a>
                        @endif
                        <a href="{{ \App\LocalizedUrl::route('contact', $locale, false) }}">{{ $copy['nav']['contact'] }}</a>
                        <a href="{{ route('login', absolute: false) }}">{{ $copy['nav']['login'] }}</a>
                        <a class="mobile-menu-signup" href="{{ route('register', absolute: false) }}">
                            {{ $copy['nav']['start'] }}
                        </a>
                    </nav>
                </details>
                <a class="header-login" href="{{ route('login', absolute: false) }}">{{ $copy['nav']['login'] }}</a>
                <a class="button button-small button-lime" href="{{ route('register', absolute: false) }}">
                    {{ $copy['nav']['start'] }}
                </a>
            </div>
        </div>
    </header>

    <main id="main">
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="footer-top">
            <a class="brand brand-footer" href="{{ \App\LocalizedUrl::route('home', $locale, false) }}">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 32 32" fill="none">
                        <path d="M5 5h10v22H5z" fill="currentColor"/>
                        <path d="M17 5h10v10H17z" fill="#D7FF68"/>
                        <path d="M17 17h10v10H17z" fill="currentColor"/>
                    </svg>
                </span>
                <span class="brand-name">{{ $page['brand'] }}</span>
            </a>
            <p>{{ $copy['nav']['footer_slogan'] }}</p>
            <a class="footer-cta" href="{{ route('register', absolute: false) }}">{{ $copy['nav']['footer_cta'] }}</a>
        </div>
        <p class="footer-legal">
            Prodi Digital AB, org.nr 559214-9370, Tegelbrukets väg 41, 195 59 Märsta
            @if (is_string($providerContactEmail = config('klartfoto.provider_contact_email')) && filter_var($providerContactEmail, FILTER_VALIDATE_EMAIL))
                · @include('partials.public-mailto', ['email' => $providerContactEmail])
            @endif
        </p>
        <div class="footer-bottom">
            <span>{{ $page['footer_tagline'] ?? $copy['nav']['footer_tagline'] }}</span>
            <nav class="footer-links" aria-label="{{ __('marketing.cookies.region') }}">
                @php($wisegentLocale = ['sv' => 'se', 'en' => 'en', 'pl' => 'pl'][$locale] ?? 'en')
                <a href="https://wisegent.se/{{ $wisegentLocale }}" target="_blank" rel="noopener noreferrer">{{ $copy['nav']['wisegent'] }} <span aria-hidden="true">↗</span></a>
                <a href="{{ route('login', absolute: false) }}">{{ $copy['nav']['login'] }}</a>
                @if ($locale === 'sv')
                    <a href="{{ route('guides.index', absolute: false) }}">Guider</a>
                @endif
                <a href="{{ \App\LocalizedUrl::route('contact', $locale, false) }}">{{ $copy['nav']['contact'] }}</a>
                <a href="{{ route('site.sv.terms', absolute: false) }}" lang="sv">{{ $copy['nav']['terms'] }}</a>
                <a href="{{ \App\LocalizedUrl::route('privacy', $locale, false) }}">{{ $copy['nav']['privacy'] }}</a>
                <button type="button" class="footer-cookie-settings" data-cookie-settings>{{ __('marketing.cookies.settings') }}</button>
            </nav>
        </div>
    </footer>
    @include('partials.cookie-banner')
</body>
</html>
