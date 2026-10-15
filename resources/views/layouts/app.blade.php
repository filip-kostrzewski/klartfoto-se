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

    @auth
        <div class="app-layout">
            <aside class="app-sidebar">
                <a class="brand" href="{{ route('assignments.index', absolute: false) }}" aria-label="Klartfoto – uppdrag">
                    <span class="brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 32 32" fill="none">
                            <path d="M5 5h10v22H5z" fill="currentColor"/>
                            <path d="M17 5h10v10H17z" fill="#D7FF68"/>
                            <path d="M17 17h10v10H17z" fill="currentColor"/>
                        </svg>
                    </span>
                    <span class="brand-name">Klartfoto</span>
                </a>

                <p class="app-sidebar-label">ARBETSYTA</p>
                <nav class="app-sidebar-nav" aria-label="Huvudnavigation">
                    <a href="{{ route('assignments.index', absolute: false) }}" @if (request()->routeIs('assignments.index')) aria-current="page" @endif>
                        <x-icon name="report" /> <span>Uppdrag</span>
                    </a>
                    <a href="{{ route('settings.edit', absolute: false) }}" @if (request()->routeIs('settings.edit', 'settings.billing')) aria-current="page" @endif>
                        <x-icon name="tools" /> <span>Inställningar</span>
                    </a>
                </nav>

                <div class="app-sidebar-footer">
                    <span class="app-sidebar-footer-icon"><x-icon name="camera" /></span>
                    <p>Samla bilderna. Dela rapporten.</p>
                    <a href="{{ route('assignments.index', absolute: false) }}">Visa uppdrag <span aria-hidden="true">↗</span></a>
                </div>
            </aside>

            <div class="app-workspace">
                <header class="app-topbar">
                    <div class="app-topbar-title">
                        <span>Arbetsyta</span><span aria-hidden="true">/</span><strong>@yield('page-heading', 'Uppdrag')</strong>
                    </div>
                    <details class="app-user-menu">
                        <summary>
                            <span class="user-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name ?: auth()->user()->email, 0, 1)) }}</span>
                            <span class="user-menu-label"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->email }}</small></span>
                            <span class="user-menu-chevron" aria-hidden="true"></span>
                        </summary>
                        <div class="app-user-menu-panel">
                            <a href="{{ route('settings.edit', absolute: false) }}">Inställningar</a>
                            <form method="post" action="{{ route('logout', absolute: false) }}">
                                @csrf
                                <button type="submit">Logga ut</button>
                            </form>
                        </div>
                    </details>
                </header>
                <nav class="app-mobile-nav" aria-label="Huvudnavigation">
                    <a href="{{ route('assignments.index', absolute: false) }}" @if (request()->routeIs('assignments.index')) aria-current="page" @endif><x-icon name="report" /> Uppdrag</a>
                    <a href="{{ route('settings.edit', absolute: false) }}" @if (request()->routeIs('settings.edit', 'settings.billing')) aria-current="page" @endif><x-icon name="tools" /> Inställningar</a>
                </nav>

                <main id="main" class="app-main">
                    @if (session('toast'))
                        <div class="toast" role="status" data-toast>
                            <p>{{ session('toast') }}</p>
                            <button type="button" data-toast-close>Stäng</button>
                        </div>
                    @endif
                    @include('partials.verify-email')
                    @yield('content')
                </main>
            </div>
        </div>
    @else
        <header class="app-public-header">
            <a class="brand" href="{{ route('site.sv.home', absolute: false) }}" aria-label="Klartfoto – startsida">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 32 32" fill="none">
                        <path d="M5 5h10v22H5z" fill="currentColor"/>
                        <path d="M17 5h10v10H17z" fill="#D7FF68"/>
                        <path d="M17 17h10v10H17z" fill="currentColor"/>
                    </svg>
                </span>
                <span class="brand-name">Klartfoto</span>
            </a>
            <a href="{{ route('site.sv.home', absolute: false) }}">Till startsidan <span aria-hidden="true">↗</span></a>
        </header>
        <main id="main" class="app-guest-main">
            @yield('content')
        </main>
    @endauth
</body>
</html>
