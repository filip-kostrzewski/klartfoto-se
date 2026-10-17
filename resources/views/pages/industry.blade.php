@extends('layouts.site')

@section('content')
    @php($home = $copy['home'])

    <section class="industry-hero">
        <div class="industry-hero-inner">
            <nav class="breadcrumbs" aria-label="{{ $copy['nav']['main'] }}">
                <a href="{{ \App\LocalizedUrl::route('home', $locale, false) }}">{{ str_replace(':brand', $page['brand'], $copy['nav']['brand_home']) }}</a><span aria-hidden="true">/</span><span>{{ $page['breadcrumb'] }}</span>
            </nav>
            <div class="industry-hero-grid">
                <div class="industry-hero-copy">
                    <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> {{ $page['eyebrow'] }}</p>
                    <h1>{{ $page['h1'] }}</h1>
                    <p class="industry-lead">{{ $home['lead'] }}</p>
                    <div class="hero-actions">
                        <a class="button button-lime" href="{{ route('register', absolute: false) }}">{{ $copy['nav']['start'] }}</a>
                        <a class="text-link" href="{{ \App\LocalizedUrl::route('report', $locale, false) }}">{{ $copy['nav']['report'] }}</a>
                    </div>
                </div>
                <div class="industry-hero-visual">
                    @php($industryImage = ($page['route_key'] ?? 'cleaning').'-detail')
                    <img src="{{ asset('images/'.$industryImage.'.webp') }}?v={{ config('klartfoto.demo_asset_version') }}" srcset="{{ asset('images/'.$industryImage.'-small.webp') }}?v={{ config('klartfoto.demo_asset_version') }} 768w, {{ asset('images/'.$industryImage.'.webp') }}?v={{ config('klartfoto.demo_asset_version') }} 1536w" sizes="(max-width: 760px) 100vw, 48vw" width="1536" height="1024" alt="{{ $page['image_alt'] }}" fetchpriority="high" decoding="async">
                    <span class="industry-image-note">{{ $home['photo_note'] }}</span>
                    <span class="industry-visual-caption"><span>{{ $page['eyebrow'] }}</span><span>R/01</span></span>
                </div>
            </div>
        </div>
    </section>

    <section class="industry-workflow section-pad">
        <div class="section-heading-row">
            <div>
                <p class="section-kicker"><span>01</span> {{ $home['workflow']['kicker'] }}</p>
                <h2>{{ $home['workflow']['title'] }}</h2>
            </div>
            <p>{{ $home['workflow']['intro'] }}</p>
        </div>
        <div class="workflow-grid">
            @foreach ($home['workflow']['steps'] as $index => $step)
                <article class="workflow-card">
                    <div class="workflow-card-top">
                        <span class="workflow-icon"><x-icon :name="$step['icon']" /></span>
                        <span class="workflow-number">0{{ $index + 1 }}</span>
                    </div>
                    <h3>{{ $step['title'] }}</h3>
                    <p>{{ $step['text'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="industry-example section-pad">
        <div class="industry-example-copy">
            <p class="section-kicker"><span>02</span> {{ $home['sample_kicker'] }}</p>
            <h2>{{ $home['sample_title'] }} <em>{{ $home['sample_emphasis'] }}</em></h2>
            <p>{{ $home['sample_intro'] }}</p>
            <a class="text-link" href="{{ \App\LocalizedUrl::route('report', $locale, false) }}">{{ $home['sample_link'] }}</a>
        </div>
        <article class="industry-report-preview">
            <div class="industry-report-preview-heading">
                <span class="report-mark" aria-hidden="true"><x-icon name="report" /></span>
                <div><span>{{ $page['example_label'] }}</span><strong>{{ $page['preview_title'] }}</strong></div>
            </div>
            <div class="industry-report-preview-meta">
                <span><small>{{ $page['example_object_label'] ?? $home['object_label'] }}</small><strong>{{ $page['example_object_value'] ?? $home['object_value'] }}</strong></span>
                <span><small>{{ $page['example_place_label'] ?? $home['place_label'] }}</small><strong>{{ $page['example_place_value'] ?? $home['place_value'] }}</strong></span>
                <span><small>{{ $page['example_status_label'] ?? $home['status_label'] }}</small><strong>{{ $page['example_status_value'] ?? $home['status_value'] }}</strong></span>
            </div>
            @if (($page['route_key'] ?? 'cleaning') === 'cleaning')
                <div class="industry-report-preview-photos">
                    <figure>
                        <img src="{{ asset('images/demo/kitchen-before-small.webp') }}?v={{ config('klartfoto.demo_asset_version') }}" width="768" height="512" alt="" loading="lazy" decoding="async">
                        <figcaption>{{ $home['before'] }}</figcaption>
                    </figure>
                    <figure>
                        <img src="{{ asset('images/demo/kitchen-after-small.webp') }}?v={{ config('klartfoto.demo_asset_version') }}" width="768" height="512" alt="" loading="lazy" decoding="async">
                        <figcaption>{{ $home['after'] }}</figcaption>
                    </figure>
                </div>
            @else
                <div class="industry-report-preview-photos industry-report-preview-photos-single">
                    <figure>
                        <img src="{{ asset('images/'.($page['route_key'] ?? 'cleaning').'-detail-small.webp') }}?v={{ config('klartfoto.demo_asset_version') }}" width="768" height="512" alt="{{ $page['image_alt'] }}" loading="lazy" decoding="async">
                        <figcaption>{{ $page['preview_title'] }}</figcaption>
                    </figure>
                </div>
            @endif
        </article>
        @include('pages.partials.example-mobile-shots')
    </section>

    @if (($page['route_key'] ?? null) === 'cleaning')
        @php($wisegentLocale = ['sv' => 'se', 'en' => 'en', 'pl' => 'pl'][$locale] ?? 'en')
        <section class="wisegent-callout section-pad" aria-labelledby="wisegent-title">
            <div class="wisegent-callout-mark" aria-hidden="true"><x-icon name="calendar" /></div>
            <div>
                <p class="section-kicker">{{ $home['wisegent']['eyebrow'] }}</p>
                <h2 id="wisegent-title">{{ $home['wisegent']['title'] }}</h2>
                <p>{{ $home['wisegent']['text'] }}</p>
            </div>
            <a class="button button-outline-dark" href="https://wisegent.se/{{ $wisegentLocale }}" target="_blank" rel="noopener noreferrer">{{ $home['wisegent']['link'] }} <span aria-hidden="true">↗</span></a>
        </section>
    @endif

    @if (($page['route_key'] ?? null) === 'cleaning' && $locale === 'sv')
        @php($guideLink = trans('guides.cleaning_link'))
        <aside class="guide-promo section-pad">
            <div class="guide-promo-copy">
                <p class="section-kicker">{{ $guideLink['kicker'] }}</p>
                <h2>{{ $guideLink['title'] }}</h2>
                <p>{{ $guideLink['text'] }}</p>
                <a class="text-link" href="{{ route('guides.cleaning-photos', absolute: false) }}">{{ $guideLink['label'] }}</a>
            </div>
        </aside>
    @endif

    <section class="industry-cta section-pad">
        <p class="section-kicker"><span>03</span> {{ $page['cta_eyebrow'] }}</p>
        <h2>{{ $page['cta_title'] }} <em>{{ $page['cta_emphasis'] }}</em></h2>
        <p>{{ $page['cta_body'] }}</p>
        <a class="button button-dark" href="{{ route('register', absolute: false) }}">{{ $copy['nav']['start'] }}</a>
    </section>
@endsection
