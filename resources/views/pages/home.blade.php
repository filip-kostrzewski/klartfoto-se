@extends('layouts.site')

@section('content')
    @php($home = $copy['home'])
    @php($cta = $home['signup'])

    <section class="landing-hero">
        <div class="landing-hero-inner">
            <div class="landing-hero-copy">
                <p class="eyebrow"><x-icon name="camera" /> {{ $copy['nav']['announcement'] }}</p>
                <h1>{{ $home['h1_start'] }} <em>{{ $home['h1_emphasis'] }}</em></h1>
                <p class="landing-hero-lead">{{ $home['lead'] }}</p>
                @if (config('klartfoto.pricing_visible'))
                    <p class="hero-price">
                        <span>{{ $home['pricing']['start_name'] }} <strong>{{ $home['pricing']['start_price'] }}</strong> <span class="hero-price-vat">{{ $home['pricing']['start_vat'] }}</span></span>
                        <span>{{ $home['pricing']['team_name'] }} <strong>{{ $home['pricing']['team_price'] }}</strong> <span class="hero-price-vat">{{ $home['pricing']['team_vat'] }}</span></span>
                    </p>
                @endif
                <div class="hero-actions">
                    <a class="button button-lime" href="{{ route('register', absolute: false) }}">{{ $home['cta_primary'] }}</a>
                    <a class="hero-sample-link" href="{{ \App\LocalizedUrl::route('report', $locale, false) }}">{{ $home['cta_secondary'] }}</a>
                </div>
                <p class="hero-footnote">{{ $home['footnote'] }}</p>
                <div class="hero-proof">
                    <span><x-icon name="check" /> {{ $home['trust_1'] }}</span>
                    <span><x-icon name="report" /> {{ $home['trust_2'] }}</span>
                </div>
            </div>

            <div class="landing-hero-visual">
                <div class="comparison-slider" data-comparison style="--split: 54%;">
                    <div class="comparison-frame">
                        <img
                            class="comparison-image comparison-after"
                            src="{{ asset('images/demo/kitchen-after.webp') }}?v={{ config('klartfoto.demo_asset_version') }}"
                            srcset="{{ asset('images/demo/kitchen-after-mobile.webp') }}?v={{ config('klartfoto.demo_asset_version') }} 480w, {{ asset('images/demo/kitchen-after-small.webp') }}?v={{ config('klartfoto.demo_asset_version') }} 768w, {{ asset('images/demo/kitchen-after.webp') }}?v={{ config('klartfoto.demo_asset_version') }} 1536w"
                            sizes="(max-width: 760px) calc(100vw - 32px), 52vw"
                            width="1536"
                            height="1024"
                            alt="{{ $home['photo_alt'] }}"
                            fetchpriority="high"
                            decoding="async">
                        <div class="comparison-before">
                            <img
                                class="comparison-image"
                                src="{{ asset('images/demo/kitchen-before.webp') }}?v={{ config('klartfoto.demo_asset_version') }}"
                                srcset="{{ asset('images/demo/kitchen-before-mobile.webp') }}?v={{ config('klartfoto.demo_asset_version') }} 480w, {{ asset('images/demo/kitchen-before-small.webp') }}?v={{ config('klartfoto.demo_asset_version') }} 768w, {{ asset('images/demo/kitchen-before.webp') }}?v={{ config('klartfoto.demo_asset_version') }} 1536w"
                                sizes="(max-width: 760px) calc(100vw - 32px), 52vw"
                                width="1536"
                                height="1024"
                                alt=""
                                aria-hidden="true"
                                decoding="async">
                        </div>
                        <span class="photo-label photo-label-before">{{ $home['before'] }}</span>
                        <span class="photo-label photo-label-after">{{ $home['after'] }}</span>
                        <span class="demo-photo-badge"><span aria-hidden="true">✳</span> {{ $home['photo_note'] }}</span>
                        <input
                            class="comparison-control"
                            type="range"
                            min="0"
                            max="100"
                            value="54"
                            aria-label="{{ $home['slider_label'] }}"
                            aria-describedby="comparison-description"
                            aria-valuetext="54 {{ $home['slider_before_text'] }}, {{ $home['slider_after_text'] }}"
                            data-before-text="{{ $home['slider_before_text'] }}"
                            data-after-text="{{ $home['slider_after_text'] }}"
                            data-description-template="{{ $home['slider_description'] }}">
                        <span class="comparison-divider" aria-hidden="true"></span>
                        <span class="comparison-handle" aria-hidden="true"><span></span></span>
                    </div>
                    <span id="comparison-description" class="sr-only" data-comparison-description aria-live="polite">{{ str_replace(':percent', '54', $home['slider_description']) }}</span>
                </div>
                <div class="comparison-caption">
                    <span><span class="caption-live-dot" aria-hidden="true"></span> {{ $home['same_view'] }}</span>
                    <span>{{ $home['photo_detail'] }}</span>
                </div>
                <div class="hero-report-chip">
                    <span class="hero-report-icon"><x-icon name="report" /></span>
                    <span><small>{{ $home['report_fragment'] }}</small><strong>{{ $home['report_fragment_title'] }}</strong></span>
                    <span class="hero-report-check" aria-hidden="true"><x-icon name="check" /></span>
                </div>
                <span class="hero-visual-index" aria-hidden="true">R/01</span>
            </div>
        </div>
    </section>

    <section class="benefits-section section-pad" aria-label="{{ $home['intro_kicker'] }}">
        @foreach ($home['values'] as $value)
            <article class="benefit-item">
                <span class="benefit-icon"><x-icon name="check" /></span>
                <div>
                    <h2>{{ $value['title'] }}</h2>
                    <p>{{ $value['text'] }}</p>
                </div>
            </article>
        @endforeach
    </section>

    <section class="workflow-section section-pad" id="how-it-works">
        <div class="section-heading-row">
            <div>
                <p class="section-kicker"><span>01</span> {{ $home['workflow']['kicker'] }}</p>
                <h2>{{ $home['workflow']['title'] }}</h2>
            </div>
            <p>{{ $home['workflow']['intro'] }}</p>
        </div>
        <div class="workflow-grid">
            @foreach ($home['workflow']['steps'] as $index => $step)
                <article class="workflow-card" data-reveal>
                    <div class="workflow-card-top">
                        <span class="workflow-icon"><x-icon :name="$step['icon']" /></span>
                        <span class="workflow-number">0{{ $index + 1 }}</span>
                    </div>
                    <h3>{{ $step['title'] }}</h3>
                    <p>{{ $step['text'] }}</p>
                </article>
            @endforeach
        </div>
        <a class="text-link workflow-video-link" href="#se-video">{{ $home['video']['cta'] }} <span aria-hidden="true">↓</span></a>
    </section>

    <section class="industries-section section-pad" id="for-who">
        <div class="section-heading-row industries-heading">
            <div>
                <p class="section-kicker"><span>02</span> {{ $home['industries_kicker'] }}</p>
                <h2>{{ $home['intro_title'] }} <em>{{ $home['intro_emphasis'] }}</em></h2>
            </div>
            <p>{{ $home['industries_intro'] }}</p>
        </div>
        <div class="industry-grid">
            @foreach ($home['industries'] as $industry)
                <a class="industry-card industry-card-{{ $industry['key'] }}" href="{{ \App\LocalizedUrl::route($industry['key'], $locale, false) }}">
                    @php($industryImage = $industry['key'].'-detail')
                    <span class="industry-card-image">
                        <img src="{{ asset('images/'.$industryImage.'-small.webp') }}?v={{ config('klartfoto.demo_asset_version') }}" srcset="{{ asset('images/'.$industryImage.'-small.webp') }}?v={{ config('klartfoto.demo_asset_version') }} 768w, {{ asset('images/'.$industryImage.'.webp') }}?v={{ config('klartfoto.demo_asset_version') }} 1536w" sizes="(max-width: 680px) 100vw, 33vw" width="1536" height="1024" alt="" loading="lazy" decoding="async">
                        <span class="industry-image-note">{{ $home['photo_note'] }}</span>
                    </span>
                    <span class="industry-card-content">
                        <span class="industry-card-label">{{ $industry['label'] }}</span>
                        <strong>{{ $industry['title'] }}</strong>
                        <span>{{ $industry['text'] }}</span>
                        <span class="industry-card-link">{{ $home['industry_link'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    <section class="sample-section section-pad" id="sample-report">
        <div class="sample-heading">
            <div>
                <p class="section-kicker"><span>03</span> {{ $home['sample_kicker'] }}</p>
                <h2>{{ $home['sample_title'] }} <em>{{ $home['sample_emphasis'] }}</em></h2>
            </div>
            <a class="text-link" href="{{ \App\LocalizedUrl::route('report', $locale, false) }}">{{ $home['sample_link'] }}</a>
        </div>
        @include('pages.partials.example-mobile-shots', ['loadPhoneShotEagerly' => true])
        <div class="sample-layout">
            <div class="sample-explainer">
                <p class="sample-disclaimer"><span aria-hidden="true">✳</span> {{ $home['sample_disclaimer'] }}</p>
                <p>{{ $home['sample_intro'] }}</p>
                <a class="button button-dark" href="{{ \App\LocalizedUrl::route('report', $locale, false) }}">{{ $home['sample_cta'] }}</a>
            </div>
            <article class="report-card">
                <div class="report-card-header">
                    <div>
                        <span class="report-overline">{{ $home['sample_overline'] }}</span>
                        <h3>{{ $home['sample_card_title'] }}</h3>
                    </div>
                    <span class="report-mark" aria-hidden="true"><x-icon name="report" /></span>
                </div>
                <div class="report-card-meta">
                    <div><span>{{ $home['object_label'] }}</span><strong>{{ $home['object_value'] }}</strong></div>
                    <div><span>{{ $home['place_label'] }}</span><strong>{{ $home['place_value'] }}</strong></div>
                    <div><span>{{ $home['status_label'] }}</span><strong class="status-ready"><i aria-hidden="true"></i> {{ $home['status_value'] }}</strong></div>
                </div>
                <div class="report-photo-row">
                    <figure class="report-photo report-photo-before">
                        <img src="{{ asset('images/demo/kitchen-before-small.webp') }}?v={{ config('klartfoto.demo_asset_version') }}" srcset="{{ asset('images/demo/kitchen-before-small.webp') }}?v={{ config('klartfoto.demo_asset_version') }} 768w, {{ asset('images/demo/kitchen-before.webp') }}?v={{ config('klartfoto.demo_asset_version') }} 1536w" sizes="(max-width: 560px) 50vw, 22vw" width="1536" height="1024" alt="" loading="lazy" decoding="async">
                        <figcaption>{{ $home['before'] }}</figcaption>
                    </figure>
                    <figure class="report-photo report-photo-after">
                        <img src="{{ asset('images/demo/kitchen-after-small.webp') }}?v={{ config('klartfoto.demo_asset_version') }}" srcset="{{ asset('images/demo/kitchen-after-small.webp') }}?v={{ config('klartfoto.demo_asset_version') }} 768w, {{ asset('images/demo/kitchen-after.webp') }}?v={{ config('klartfoto.demo_asset_version') }} 1536w" sizes="(max-width: 560px) 50vw, 22vw" width="1536" height="1024" alt="" loading="lazy" decoding="async">
                        <figcaption>{{ $home['after'] }}</figcaption>
                    </figure>
                </div>
                <div class="report-card-footer"><span>{{ $home['report_footer'] }}</span><span aria-hidden="true">R/01</span></div>
            </article>
        </div>
        <div class="product-video" id="se-video" aria-labelledby="video-title">
            <div class="product-video-heading">
                <div>
                    <p class="section-kicker">{{ $home['video']['kicker'] }}</p>
                    <h3 id="video-title">{{ $home['video']['title'] }}</h3>
                </div>
                <p>{{ $home['video']['description'] }}</p>
            </div>
            <p class="product-video-meta" id="video-language">{{ $home['video']['meta'] }}</p>
            <iframe
                class="product-video-player"
                src="https://player.mediadelivery.net/embed/781538/b62dea40-8f45-42cd-a9df-1d770297f50f?autoplay=false&amp;loop=false&amp;muted=false&amp;preload=false&amp;responsive=true"
                title="{{ $home['video']['title'] }}"
                loading="lazy"
                width="1920"
                height="1080"
                allow="autoplay; encrypted-media; picture-in-picture"
                allowfullscreen
                referrerpolicy="strict-origin-when-cross-origin"
                aria-describedby="video-language"></iframe>
            <a class="text-link product-video-direct" href="https://player.mediadelivery.net/play/781538/b62dea40-8f45-42cd-a9df-1d770297f50f" target="_blank" rel="noopener noreferrer">{{ $home['video']['fallback'] }} <span aria-hidden="true">↗</span></a>
        </div>
        @if (empty($page['og_image']))
            <meta property="og:image" content="{{ url('/images/video-miniatura.jpg') }}">
        @endif
        <script type="application/ld+json">{!! json_encode([
            '@@context' => 'https://schema.org',
            '@type' => 'VideoObject',
            'name' => $home['video']['title'],
            'description' => $home['video']['description'],
            'thumbnailUrl' => url('/images/video-miniatura.jpg'),
            'uploadDate' => '2026-10-16T14:48:02+02:00',
            'duration' => 'PT1M',
            'embedUrl' => 'https://iframe.mediadelivery.net/embed/781538/b62dea40-8f45-42cd-a9df-1d770297f50f',
            'inLanguage' => 'sv',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) !!}</script>
    </section>

    <x-pricing-section :pricing="$home['pricing']" />

    <section class="sales-faq section-pad" aria-labelledby="faq-title">
        <div>
            <p class="section-kicker"><span>05</span> {{ $home['faq']['kicker'] }}</p>
            <h2 id="faq-title">{{ $home['faq']['title'] }}</h2>
        </div>
        <div class="sales-faq-items">
            @foreach ($home['faq']['items'] as $item)
                <details class="sales-faq-item">
                    <summary>{{ $item['question'] }}<span aria-hidden="true">+</span></summary>
                    <p>{{ $item['answer'] }}</p>
                </details>
            @endforeach
        </div>
    </section>

    <section class="signup-section section-pad" id="signup" aria-labelledby="signup-title">
        <div class="signup-cta-visual" aria-hidden="true">
            <img src="{{ asset('images/cleaning-detail-small.webp') }}?v={{ config('klartfoto.demo_asset_version') }}" srcset="{{ asset('images/cleaning-detail-small.webp') }}?v={{ config('klartfoto.demo_asset_version') }} 768w, {{ asset('images/cleaning-detail.webp') }}?v={{ config('klartfoto.demo_asset_version') }} 1536w" sizes="(max-width: 760px) 100vw, 40vw" width="1536" height="1024" alt="" loading="lazy" decoding="async">
            <span class="industry-image-note">{{ $home['photo_note'] }}</span>
        </div>
        <div class="signup-copy">
            <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> {{ $cta['eyebrow'] }}</p>
            <h2 id="signup-title">{{ $cta['title'] }} <em>{{ $cta['emphasis'] }}</em></h2>
            <p>{{ $cta['body'] }}</p>
            <p class="signup-small">{{ $home['pricing']['note'] }}</p>
            <div class="signup-cta-actions">
                <a class="button button-lime" href="{{ route('register', absolute: false) }}">{{ $home['cta_primary'] }}</a>
                <a class="text-link" href="{{ \App\LocalizedUrl::route('report', $locale, false) }}">{{ $home['sample_cta'] }}</a>
                <a class="signup-contact-link" href="{{ \App\LocalizedUrl::route('contact', $locale, false) }}">{{ $cta['closed_contact'] }}</a>
            </div>
        </div>
    </section>

    <section class="wisegent-callout section-pad" aria-labelledby="wisegent-title">
        <div class="wisegent-callout-mark" aria-hidden="true"><x-icon name="calendar" /></div>
        <div>
            <p class="section-kicker">{{ $home['wisegent']['eyebrow'] }}</p>
            <h2 id="wisegent-title">{{ $home['wisegent']['title'] }}</h2>
            <p>{{ $home['wisegent']['text'] }}</p>
        </div>
        @php($wisegentLocale = ['sv' => 'se', 'en' => 'en', 'pl' => 'pl'][$locale] ?? 'en')
        <a class="button button-outline-dark" href="https://wisegent.se/{{ $wisegentLocale }}" target="_blank" rel="noopener noreferrer">{{ $home['wisegent']['link'] }} <span aria-hidden="true">↗</span></a>
    </section>
@endsection
