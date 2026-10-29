@extends('layouts.site')

@section('content')
    <header class="industry-hero guide-hero">
        <div class="industry-hero-inner">
            <nav class="breadcrumbs" aria-label="{{ $copy['nav']['main'] }}">
                    <a href="{{ \App\LocalizedUrl::route('home', $locale, false) }}">{{ $page['home_crumb'] }}</a>
                <span aria-hidden="true">/</span>
                <span>{{ $page['breadcrumb'] }}</span>
            </nav>
            <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> {{ $page['eyebrow'] }}</p>
            <h1>{{ $page['h1'] }}</h1>
            <p class="industry-lead">{{ $page['intro'] }}</p>
            <p class="guide-index-report"><a href="{{ \App\LocalizedUrl::route('report', $locale, false) }}">{{ $page['report_link'] }}</a></p>
        </div>
    </header>

    <section class="guide-index section-pad" aria-label="{{ $page['h1'] }}">
        <div class="guide-index-list">
            @foreach ($guides as $guide)
                <a class="guide-index-card" href="{{ $guide['href'] }}">
                    <time datetime="{{ $guide['published_on'] }}">{{ $guide['published_label'] }}</time>
                    <h2>{{ $guide['h1'] }}</h2>
                    <p>{{ $guide['card'] }}</p>
                </a>
            @endforeach
        </div>
    </section>
@endsection
