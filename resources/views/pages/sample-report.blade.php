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
                    <p class="report-overline">{{ $page['report_overline'] }}</p>
                    <h2>{{ $page['report_title'] }}</h2>
                    <p class="customer-report-date">{{ $page['date_value'] }}</p>
                </div>
            </header>
            <p class="printable-disclaimer"><span aria-hidden="true">✳</span> {{ $copy['home']['photo_note'] }}</p>
            <dl class="customer-report-meta">
                <div><dt>{{ $page['object'] }}</dt><dd>{{ $page['object_value'] }}</dd></div>
                <div><dt>{{ $page['place'] }}</dt><dd>{{ $page['place_value'] }}</dd></div>
                <div><dt>{{ $page['date'] }}</dt><dd>{{ $page['date_value'] }}</dd></div>
            </dl>
            <h2 class="version-report-place">{{ $page['place_value'] }}</h2>
            <div class="photo-pair version-photo-pair">
                <figure>
                    <figcaption>{{ $page['before_caption'] }}</figcaption>
                    <img src="{{ asset('images/demo/kitchen-before-small.webp') }}?v={{ config('klartfoto.demo_asset_version') }}" srcset="{{ asset('images/demo/kitchen-before-small.webp') }}?v={{ config('klartfoto.demo_asset_version') }} 768w, {{ asset('images/demo/kitchen-before.webp') }}?v={{ config('klartfoto.demo_asset_version') }} 1536w" sizes="(max-width: 680px) 100vw, 42vw" width="1536" height="1024" alt="{{ $page['before_alt'] }}" loading="lazy" decoding="async">
                </figure>
                <figure>
                    <figcaption>{{ $page['after_caption'] }}</figcaption>
                    <img src="{{ asset('images/demo/kitchen-after-small.webp') }}?v={{ config('klartfoto.demo_asset_version') }}" srcset="{{ asset('images/demo/kitchen-after-small.webp') }}?v={{ config('klartfoto.demo_asset_version') }} 768w, {{ asset('images/demo/kitchen-after.webp') }}?v={{ config('klartfoto.demo_asset_version') }} 1536w" sizes="(max-width: 680px) 100vw, 42vw" width="1536" height="1024" alt="{{ $page['after_alt'] }}" loading="lazy" decoding="async">
                </figure>
            </div>
            <footer class="customer-report-footer">
                <p>{{ str_replace(':brand', $page['brand'], $page['footer_brand']) }}</p>
                <p>{{ $page['footer_desc'] }}</p>
                <p>{{ $page['after_note'] }}</p>
            </footer>
        </article>
    </section>
@endsection
