<!doctype html>
<html lang="sv">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#111715">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Klartfoto')</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/site.css', 'resources/js/site.js'])
</head>
<body class="app-body">
    <a class="skip-link" href="#main">Hoppa till innehållet</a>
    <header class="app-header">
        <a class="brand" href="{{ auth()->check() ? route('assignments.index', absolute: false) : route('site.sv.home', absolute: false) }}">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 32 32" fill="none">
                    <path d="M5 5h10v22H5z" fill="currentColor"/>
                    <path d="M17 5h10v10H17z" fill="#D7FF68"/>
                    <path d="M17 17h10v10H17z" fill="currentColor"/>
                </svg>
            </span>
            <span class="brand-name">Klartfoto</span>
        </a>
        @auth
            <nav class="app-nav" aria-label="Konto">
                <a href="{{ route('assignments.index', absolute: false) }}">Uppdrag</a>
                <form method="post" action="{{ route('logout', absolute: false) }}">
                    @csrf
                    <button type="submit">Logga ut</button>
                </form>
            </nav>
        @endauth
    </header>
    <main id="main" class="app-main">
        @yield('content')
    </main>
</body>
</html>
