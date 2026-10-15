@extends('layouts.site')

@section('content')
    <section class="industry-hero">
        <div class="industry-hero-inner">
            <nav class="breadcrumbs" aria-label="{{ $copy['nav']['main'] }}">
                <a href="{{ \App\LocalizedUrl::route('home', $locale, false) }}">{{ str_replace(':brand', $page['brand'], $copy['nav']['brand_home']) }}</a><span aria-hidden="true">/</span><span>{{ $page['breadcrumb'] }}</span>
            </nav>
            <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> {{ $page['eyebrow'] }}</p>
            <h1>{{ $page['h1'] }}</h1>
            <p class="industry-lead">{{ $page['intro'] }}</p>
            <div class="hero-actions">
                <a class="button button-lime" href="{{ \App\LocalizedUrl::route('home', $locale, false) }}#signup">{{ $copy['home']['signup']['eyebrow'] }} <span aria-hidden="true">↗</span></a>
                <a class="button button-outline" href="{{ \App\LocalizedUrl::route('report', $locale, false) }}">{{ $copy['nav']['report'] }} <span aria-hidden="true">↗</span></a>
            </div>
            <p class="industry-honesty">{{ $page['buyer_note'] }}</p>
        </div>
    </section>

    <section class="industry-content section-pad">
        <div class="industry-content-heading">
            <div class="section-kicker"><span>01</span> {{ $page['eyebrow'] }}</div>
            <h2>{{ $page['section_heading'] }}</h2>
            <p>{{ $page['section_intro'] }}</p>
        </div>
        <div class="checklist-grid">
            @foreach ($page['checklist'] as $index => $item)
                <article class="checklist-item">
                    <span class="checklist-index">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                    <div>
                        <h3>{{ $item['title'] }}</h3>
                        <p>{{ $item['text'] }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="industry-example section-pad">
        <div class="industry-example-copy">
            <div class="section-kicker"><span>02</span> {{ $page['example_label'] }}</div>
            <h2>{{ $page['example_heading'] }}</h2>
            <p>{{ $page['example_text'] }}</p>
            <a class="text-link" href="{{ \App\LocalizedUrl::route('report', $locale, false) }}">{{ $page['example_link'] }} <span aria-hidden="true">↗</span></a>
        </div>
        <div class="industry-example-visual">
            <span class="generated-label">{{ $copy['home']['photo_note'] }}</span>
            <img src="{{ asset('images/demo/kitchen-after.svg') }}?v={{ config('klartfoto.demo_asset_version') }}" width="768" height="1024" alt="{{ $page['image_alt'] }}" loading="lazy" decoding="async">
            <div class="visual-caption"><span>{{ $page['eyebrow'] }}</span><span>R/01</span></div>
        </div>
    </section>

    <section class="industry-cta section-pad">
        <p class="section-kicker"><span>03</span> {{ $page['cta_eyebrow'] }}</p>
        <h2>{{ $page['cta_title'] }}<br><em>{{ $page['cta_emphasis'] }}</em></h2>
        <p>{{ $page['cta_body'] }}</p>
        <a class="button button-dark" href="{{ \App\LocalizedUrl::route('home', $locale, false) }}#signup">{{ $page['cta_button'] }} <span aria-hidden="true">↗</span></a>
    </section>
@endsection
