<!doctype html>
<html lang="sv">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#111715">
    <title>Fotorapport från {{ $companyName }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/site.css', 'resources/js/site.js'])
</head>
<body class="report-body">
    <main class="customer-report">
        <header class="customer-report-header">
            @if ($logoUrl)
                <img class="customer-report-logo" src="{{ $logoUrl }}" alt="{{ $companyName }}">
            @endif
            <p class="report-overline">Fotorapport</p>
            <h1>{{ $companyName }}</h1>
            <p>{{ $assignment->performed_on->locale('sv')->translatedFormat('j F Y') }}</p>
        </header>

        <dl class="customer-report-meta">
            <div>
                <dt>Kund</dt>
                <dd>{{ $assignment->customer_name }}</dd>
            </div>
            <div>
                <dt>Adress</dt>
                <dd>{{ $assignment->address }}</dd>
            </div>
        </dl>

        @forelse ($assignment->photoPairs() as $pair)
            @include('reports.partials.photo-pair', ['pair' => $pair, 'assignment' => $assignment])
        @empty
            <p class="empty-note">Inga bilder är tillagda i den här rapporten.</p>
        @endforelse

        <footer class="customer-report-footer">Rapport via Klartfoto</footer>
    </main>
</body>
</html>
