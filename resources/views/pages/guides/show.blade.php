@extends('layouts.site')

@section('content')
    <article class="guide-article">
        <header class="industry-hero guide-hero">
            <div class="industry-hero-inner">
                <nav class="breadcrumbs" aria-label="{{ $copy['nav']['main'] }}">
                    <a href="{{ \App\LocalizedUrl::route('home', $locale, false) }}">{{ $page['home_crumb'] }}</a>
                    <span aria-hidden="true">/</span>
                    <a href="{{ route('guides.index', absolute: false) }}">{{ $page['index_label'] }}</a>
                    <span aria-hidden="true">/</span>
                    <span>{{ $page['breadcrumb'] }}</span>
                </nav>
                <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> {{ $page['eyebrow'] }}</p>
                <h1>{{ $page['h1'] }}</h1>
                <p class="guide-byline">{{ $page['byline'] }}</p>
                <p class="guide-dates">
                    <time datetime="{{ $page['published_on'] }}">{{ $page['published_label'] }}</time>
                    @if ($page['updated_on'] !== $page['published_on'])
                        <time datetime="{{ $page['updated_on'] }}">{{ $page['updated_label'] }}</time>
                    @endif
                </p>
                <p class="industry-lead">{{ $page['intro'] }}</p>
                <p class="industry-lead guide-lead-more">{!! $page['intro_more'] !!}</p>
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
                                    @if (is_array($bullet))
                                        <li><strong>{{ $bullet['term'] }}</strong> {{ $bullet['text'] }}</li>
                                    @else
                                        <li>{{ $bullet }}</li>
                                    @endif
                                @endforeach
                            </ul>
                        @endif
                        @if (! empty($section['ordered']))
                            <ol class="guide-ordered">
                                @foreach ($section['ordered'] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ol>
                        @endif
                        @foreach ($section['paragraphs_after'] as $paragraph)
                            <p>{!! $paragraph !!}</p>
                        @endforeach
                        @if (! empty($page['pairs']))
                            @if ($section['id'] === 'what')
                                @include('pages.guides.pair', ['pair' => $page['pairs'][1]])
                            @elseif ($section['id'] === 'same')
                                @include('pages.guides.pair', ['pair' => $page['pairs'][0]])
                                @include('pages.guides.pair', ['pair' => $page['pairs'][2]])
                            @elseif ($section['id'] === 'share')
                                <figure class="guide-figure">
                                    <img src="{{ $page['report_image_url'] }}" width="1600" height="1244" alt="{{ $page['report_image']['alt'] }}" loading="lazy" decoding="async">
                                    <figcaption>{{ $page['report_image']['caption'] }}</figcaption>
                                </figure>
                            @endif
                        @endif
                        @if (! empty($section['show_cta']))
                            <p class="guide-inline-cta">
                                <a class="button button-lime" href="{{ route('register', absolute: false) }}">{{ $page['cta_label'] }}</a>
                            </p>
                        @endif
                    </section>
                @endforeach

                @if (! empty($page['steps']))
                    <section aria-labelledby="guide-steps-title">
                        <h2 id="guide-steps-title">{{ $page['steps_caption'] }}</h2>
                        <table class="guide-steps">
                            <caption class="sr-only">{{ $page['steps_caption'] }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Steg</th>
                                    <th scope="col">Gör så här</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($page['steps'] as $step)
                                    <tr>
                                        <th scope="row">{{ $step[0] }}</th>
                                        <td>{{ $step[1] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </section>
                @endif

                @if (! empty($page['checklist']))
                    <section aria-labelledby="guide-checklist-title">
                        <h2 id="guide-checklist-title">{{ $page['checklist_title'] }}</h2>
                        <ul class="guide-checklist">
                            @foreach ($page['checklist'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                        @if (! empty($page['closing']))
                            <p>{!! $page['closing'] !!}</p>
                        @endif
                    </section>
                @endif

                @if (! empty($page['faqs']))
                    <section class="guide-faq" id="guide-faq" aria-labelledby="guide-faq-title">
                        <h2 id="guide-faq-title">{{ $page['faq_title'] }}</h2>
                        @foreach ($page['faqs'] as $faq)
                            <h3>{{ $faq['question'] }}</h3>
                            <p>{{ $faq['answer'] }}</p>
                        @endforeach
                    </section>
                @endif

                @if (! empty($page['related']))
                    <section class="guide-related" aria-labelledby="guide-related-title">
                        <h2 id="guide-related-title">{{ $page['related_title'] }}</h2>
                        <ul class="guide-list">
                            @foreach ($page['related'] as $related)
                                <li><a href="{{ route($related['route'], absolute: false) }}">{{ $related['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>
        </div>

        @if (! empty($page['cta_title']))
            <section class="industry-cta section-pad">
                <p class="section-kicker">{{ $page['cta_eyebrow'] }}</p>
                <h2>{{ $page['cta_title'] }} <em>{{ $page['cta_emphasis'] }}</em></h2>
                <p>{{ $page['cta_body'] }}</p>
                <div class="hero-actions">
                    <a class="button button-dark" href="{{ route('register', absolute: false) }}">{{ $page['cta_label'] }}</a>
                </div>
            </section>
        @endif
    </article>
@endsection
