@extends('layouts.site')

@section('content')
    <section class="legal-hero">
        <div class="legal-hero-inner">
            <nav class="breadcrumbs" aria-label="{{ $copy['nav']['main'] }}">
                <a href="{{ \App\LocalizedUrl::route('home', 'sv', false) }}">{{ str_replace(':brand', $page['brand'], $copy['nav']['brand_home']) }}</a><span aria-hidden="true">/</span><span>{{ $page['breadcrumb'] }}</span>
            </nav>
            <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> {{ $page['eyebrow'] }}</p>
            <h1>{{ $page['h1'] }}</h1>
            <p>{{ $page['intro'] }}</p>
        </div>
    </section>

    <article class="legal-content section-pad">
        <p class="legal-updated">{{ $page['updated'] }}</p>
        <p class="legal-provider">
            Prodi Digital AB, org.nr 559214-9370, Tegelbrukets väg 41, 195 59 Märsta.
            Kontakt: <a href="mailto:{{ $page['provider_contact_email'] }}">{{ $page['provider_contact_email'] }}</a>.
        </p>
        @foreach ($page['sections'] as $section)
            <section>
                <h2>{{ $section['title'] }}</h2>
                @foreach ($section['paragraphs'] as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </section>
        @endforeach
        <p>Se även <a href="{{ route('site.sv.privacy', absolute: false) }}">integritetspolicyn</a>.</p>
    </article>
@endsection
