@php
    $locale ??= app()->getLocale();
    $copy ??= trans('marketing');
    $language_options ??= config('marketing.locales');
    $notFound = trans('marketing.not_found');
    $brand = (string) config('marketing.brand');
    $page = array_merge([
        'h1' => $notFound['h1'],
        'title' => str_replace(':brand', $brand, $notFound['title']),
        'description' => $notFound['description'],
        'body' => $notFound['body'],
        'home' => $notFound['home'],
        'report' => $notFound['report'],
        'eyebrow' => $notFound['eyebrow'],
        'canonical' => \App\SiteSeo::canonical(request()->getPathInfo()),
        'is_indexable' => false,
        'schema' => [],
        'alternates' => [],
        'brand' => $brand,
        'route_key' => 'not-found',
        'locale' => $locale,
        'og_locale' => config("marketing.locales.{$locale}.og", 'sv_SE'),
    ], $page ?? []);
@endphp

@extends('layouts.site')

@section('content')
    <section class="not-found section-pad">
        <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> {{ $page['eyebrow'] }}</p>
        <h1>{{ $page['h1'] }}</h1>
        <p>{{ $page['body'] }}</p>
        <div class="hero-actions">
            <a class="button button-dark" href="{{ \App\LocalizedUrl::route('home', $locale, false) }}">{{ $page['home'] }} <span aria-hidden="true">↗</span></a>
            <a class="button button-outline-dark" href="{{ \App\LocalizedUrl::route('report', $locale, false) }}">{{ $page['report'] }} <span aria-hidden="true">↗</span></a>
        </div>
    </section>
@endsection
