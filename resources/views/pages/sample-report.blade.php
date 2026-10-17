@extends('layouts.site')

@section('content')
    @php
        $demoVersion = config('klartfoto.demo_asset_version');
        $demoImage = fn (string $file): string => asset('images/demo/'.$file).'?v='.$demoVersion;
        $pairs = [
            [
                'place' => 'Kök',
                'before' => 'example-kitchen-before.webp',
                'after' => 'example-kitchen-after.webp',
                'before_alt' => 'AI-demo av köket före städning, med fläckar på hällen och disk i hon.',
                'after_alt' => 'AI-demo av samma kök efter städning, med ren häll och tom diskho.',
                'note' => 'Fläckar på hällen och disk i hon före arbetet. Bänk och ho är avtorkade efteråt.',
            ],
            [
                'place' => 'Badrum',
                'before' => 'example-bathroom-before.webp',
                'after' => 'example-bathroom-after.webp',
                'before_alt' => 'AI-demo av badrummet före städning, med fläckar på spegeln och en handduk på golvet.',
                'after_alt' => 'AI-demo av samma badrum efter städning, med ren spegel och tomt golv.',
                'note' => 'Spegel och handfat är rengjorda. Handduken som låg på golvet är undanplockad.',
            ],
        ];
        $checklist = [
            'Bänkskivan avtorkad',
            'Diskhon rengjord',
            'Spishällen avtorkad',
            'Badrumsspegeln putsad',
            'Golvet i badrummet moppat',
        ];
    @endphp
    <section class="resource-hero">
        <div class="resource-hero-inner">
            <nav class="breadcrumbs" aria-label="{{ $copy['nav']['main'] }}">
                <a href="{{ \App\LocalizedUrl::route('home', $locale, false) }}">{{ str_replace(':brand', $page['brand'], $copy['nav']['brand_home']) }}</a><span aria-hidden="true">/</span><span>{{ $page['breadcrumb'] }}</span>
            </nav>
            <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> {{ $page['eyebrow'] }}</p>
            <h1>{{ $page['h1'] }}</h1>
            <p>{{ $page['intro'] }}</p>
            <div class="hero-actions">
                <button class="button button-lime" type="button" data-print>{{ $page['print'] }}</button>
                <a class="button button-outline" href="{{ route('register', absolute: false) }}">{{ $copy['nav']['start'] }}</a>
            </div>
            <p class="resource-note">{{ $page['print_note'] }}</p>
        </div>
    </section>

    <section class="printable-section section-pad" id="printable-report">
        <article class="customer-report version-report example-report-sheet">
            <p class="example-report-flag">{{ $page['example_flag'] }}</p>
            <header class="customer-report-header">
                <img class="customer-report-logo report-logo" src="{{ $companyLogoUrl }}" alt="{{ $companyLogoAlt }}" width="160" height="64">
                <div class="customer-report-heading-copy">
                    <p class="report-overline">Fotorapport</p>
                    <h2>Exempel Städ AB</h2>
                    <p class="customer-report-date">14 okt 2026 · kl. 09:30</p>
                </div>
            </header>
            <p class="printable-disclaimer"><span aria-hidden="true">✳</span> AI-demo · inte ett kundfoto</p>
            <dl class="customer-report-meta">
                <div><dt>Objekt</dt><dd>Lgh 1102, kök och badrum</dd></div>
                <div><dt>Adress</dt><dd>Exempelgatan 4, 123 45 Exempelstad</dd></div>
                <div><dt>Datum</dt><dd>14 okt 2026 · kl. 09:30</dd></div>
            </dl>
            @foreach ($pairs as $pair)
                <h2 class="version-report-place">{{ $pair['place'] }}</h2>
                <div class="photo-pair version-photo-pair">
                    <figure>
                        <figcaption>{{ $page['before_caption'] }} <span>AI-demo</span></figcaption>
                        <img src="{{ $demoImage($pair['before']) }}" width="1152" height="864" alt="{{ $pair['before_alt'] }}" loading="lazy" decoding="async">
                    </figure>
                    <figure>
                        <figcaption>{{ $page['after_caption'] }} <span>AI-demo</span></figcaption>
                        <img src="{{ $demoImage($pair['after']) }}" width="1152" height="864" alt="{{ $pair['after_alt'] }}" loading="lazy" decoding="async">
                    </figure>
                </div>
                <aside class="version-photo-comment" aria-label="{{ $page['note_title'] }}">
                    <strong>{{ $page['note_title'] }}</strong>
                    <p>{{ $pair['note'] }}</p>
                </aside>
            @endforeach
            <section class="example-report-checklist" aria-labelledby="example-checklist-heading">
                <h2 id="example-checklist-heading">Sammanfattning</h2>
                <ul>
                    @foreach ($checklist as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </section>
            <footer class="customer-report-footer">
                <p>
                    <span>Exempel Städ AB</span>
                    <span><a href="tel:0701740605">070-174 06 05</a></span>
                    <span><a href="mailto:info@example.se">info@example.se</a></span>
                    <span>Org.nr 000000-0000 (exempel)</span>
                </p>
                <p>{{ $page['footer_desc'] }}</p>
                <p>AI-demo · inte ett kundfoto</p>
            </footer>
        </article>
    </section>
@endsection
