@extends('layouts.site')

@section('content')
    <article class="guide-article">
        <header class="industry-hero guide-hero">
            <div class="industry-hero-inner">
                <nav class="breadcrumbs" aria-label="{{ $copy['nav']['main'] }}">
                    <a href="{{ \App\LocalizedUrl::route('home', $locale, false) }}">{{ str_replace(':brand', $page['brand'], $copy['nav']['brand_home']) }}</a>
                    <span aria-hidden="true">/</span>
                    <a href="{{ route('guides.index', absolute: false) }}">{{ $page['index_label'] }}</a>
                    <span aria-hidden="true">/</span>
                    <span>{{ $page['breadcrumb'] }}</span>
                </nav>
                <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> {{ $page['eyebrow'] }}</p>
                <h1>{{ $page['h1'] }}</h1>
                <p class="guide-dates">
                    <time datetime="{{ $page['published_on'] }}">{{ $page['published_label'] }}</time>
                    <time datetime="{{ $page['updated_on'] }}">{{ $page['updated_label'] }}</time>
                </p>
                <p class="industry-lead">{{ $page['intro'] }}</p>
                <p class="industry-lead guide-lead-more">{{ $page['intro_more'] }}</p>
                <div class="hero-actions">
                    <a class="button button-lime" href="{{ route('register', absolute: false) }}">{{ $copy['nav']['start'] }} <span aria-hidden="true">↗</span></a>
                    <a class="text-link" href="{{ \App\LocalizedUrl::route('report', $locale, false) }}">{{ $page['cta_report'] }} <span aria-hidden="true">↗</span></a>
                </div>
            </div>
        </header>

        <div class="guide-body section-pad">
            <div class="guide-body-inner">
                @foreach ($page['sections'] as $section)
                    <section>
                        <h2>{{ $section['title'] }}</h2>
                        @foreach ($section['paragraphs'] as $paragraph)
                            <p>{!! $paragraph !!}</p>
                        @endforeach
                        @if (! empty($section['bullets']))
                            <ul class="guide-list">
                                @foreach ($section['bullets'] as $bullet)
                                    <li>{{ $bullet }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endforeach

                <section aria-labelledby="guide-checklist-title">
                    <h2 id="guide-checklist-title">{{ $page['checklist_title'] }}</h2>
                    <ul class="guide-checklist">
                        @foreach ($page['checklist'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </section>

                <section class="guide-faq" id="guide-faq" aria-labelledby="guide-faq-title">
                    <h2 id="guide-faq-title">{{ $page['faq_title'] }}</h2>
                    @foreach ($page['faqs'] as $faq)
                        <h3>{{ $faq['question'] }}</h3>
                        <p>{{ $faq['answer'] }}</p>
                    @endforeach
                </section>
            </div>
        </div>

        <section class="industry-cta section-pad">
            <p class="section-kicker">{{ $page['cta_eyebrow'] }}</p>
            <h2>{{ $page['cta_title'] }} <em>{{ $page['cta_emphasis'] }}</em></h2>
            <p>{{ $page['cta_body'] }}</p>
            <div class="hero-actions">
                <a class="button button-dark" href="{{ route('register', absolute: false) }}">{{ $copy['nav']['start'] }} <span aria-hidden="true">↗</span></a>
                <a class="text-link" href="{{ \App\LocalizedUrl::route('report', $locale, false) }}">{{ $page['cta_report'] }} <span aria-hidden="true">↗</span></a>
            </div>
        </section>
    </article>
@endsection
