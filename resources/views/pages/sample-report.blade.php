@extends('layouts.site')

@section('content')
    <section class="resource-hero">
        <div class="resource-hero-inner">
            <nav class="breadcrumbs" aria-label="{{ $copy['nav']['main'] }}">
                <a href="{{ \App\LocalizedUrl::route('home', $locale, false) }}">{{ str_replace(':brand', $page['brand'], $copy['nav']['brand_home']) }}</a><span aria-hidden="true">/</span><span>{{ $page['breadcrumb'] }}</span>
            </nav>
            <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> {{ $page['eyebrow'] }}</p>
            <h1>{{ $page['h1'] }}</h1>
            <p>{{ $page['intro'] }}</p>
            <div class="hero-actions">
                <button class="button button-lime" type="button" data-print>{{ $page['print'] }} <span aria-hidden="true">↗</span></button>
                <a class="button button-outline" href="{{ route('register', absolute: false) }}">{{ $copy['nav']['start'] }} <span aria-hidden="true">↗</span></a>
            </div>
            <p class="resource-note">{{ $page['print_note'] }}</p>
        </div>
    </section>

    <section class="printable-section section-pad" id="printable-report">
        <article class="printable-report">
            <header class="printable-header">
                <img class="report-logo" src="{{ $companyLogoUrl }}" alt="{{ $companyLogoAlt }}" width="160" height="64">
                <div>
                    <p class="report-overline">{{ $page['report_overline'] }}</p>
                    <h2>{{ $page['report_title'] }}</h2>
                    <p class="printable-subtitle">{{ $page['subtitle'] }}</p>
                </div>
                <span class="printable-mark" aria-hidden="true">R/01</span>
            </header>
            <div class="printable-meta">
                <div><span>{{ $page['object'] }}</span><strong>{{ $page['object_value'] }}</strong></div>
                <div><span>{{ $page['place'] }}</span><strong>{{ $page['place_value'] }}</strong></div>
                <div><span>{{ $page['date'] }}</span><strong>{{ $page['date_value'] }}</strong></div>
            </div>
            <div class="printable-disclaimer"><span aria-hidden="true">✳</span> {{ $copy['home']['photo_note'] }}</div>
            <div class="printable-photos">
                <figure>
                    <img src="{{ asset('images/demo/kitchen-before-small.webp') }}?v={{ config('klartfoto.demo_asset_version') }}" srcset="{{ asset('images/demo/kitchen-before-small.webp') }}?v={{ config('klartfoto.demo_asset_version') }} 768w, {{ asset('images/demo/kitchen-before.webp') }}?v={{ config('klartfoto.demo_asset_version') }} 1536w" sizes="(max-width: 680px) 100vw, 42vw" width="1536" height="1024" alt="{{ $page['before_alt'] }}" loading="lazy" decoding="async">
                    <figcaption><span>{{ $page['before_caption'] }}</span><span>{{ $page['before_description'] }}</span></figcaption>
                </figure>
                <figure>
                    <img src="{{ asset('images/demo/kitchen-after-small.webp') }}?v={{ config('klartfoto.demo_asset_version') }}" srcset="{{ asset('images/demo/kitchen-after-small.webp') }}?v={{ config('klartfoto.demo_asset_version') }} 768w, {{ asset('images/demo/kitchen-after.webp') }}?v={{ config('klartfoto.demo_asset_version') }} 1536w" sizes="(max-width: 680px) 100vw, 42vw" width="1536" height="1024" alt="{{ $page['after_alt'] }}" loading="lazy" decoding="async">
                    <figcaption><span>{{ $page['after_caption'] }}</span><span>{{ $page['after_description'] }}</span></figcaption>
                </figure>
            </div>
            <footer class="printable-footer">
                <span>{{ str_replace(':brand', $page['brand'], $page['footer_brand']) }}</span>
                <span>{{ $page['footer_desc'] }}</span>
            </footer>
        </article>
        <p class="printable-after">{{ $page['after_note'] }}</p>
    </section>
@endsection
