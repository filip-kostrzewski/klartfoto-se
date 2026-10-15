<!doctype html>
<html lang="sv">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#111715">
    <title>Rapport från {{ $assignment->user->name }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/site.css', 'resources/js/site.js'])
</head>
<body class="report-body">
    <main class="customer-report">
        <header class="customer-report-header">
            <p class="report-overline">Städrapport</p>
            <h1>{{ $assignment->user->name }}</h1>
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
            <section class="photo-pair">
                <figure>
                    @if ($pair['before'])
                        <img src="{{ route('reports.photos.show', ['token' => $assignment->public_token, 'photo' => $pair['before']], false) }}" alt="Före städningen">
                    @else
                        <p class="missing-photo">Ingen förebild</p>
                    @endif
                    <figcaption>Före</figcaption>
                </figure>
                <figure>
                    @if ($pair['after'])
                        <img src="{{ route('reports.photos.show', ['token' => $assignment->public_token, 'photo' => $pair['after']], false) }}" alt="Efter städningen">
                    @else
                        <p class="missing-photo">Ingen efterbild</p>
                    @endif
                    <figcaption>Efter</figcaption>
                </figure>
            </section>
        @empty
            <p class="empty-note">Inga bilder är tillagda i den här rapporten.</p>
        @endforelse

        <footer class="customer-report-footer">Rapport via Klartfoto</footer>
    </main>
</body>
</html>
