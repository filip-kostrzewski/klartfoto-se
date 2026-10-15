@extends('layouts.site')

@section('content')
    <section class="legal-hero">
        <div class="legal-hero-inner">
            <nav class="breadcrumbs" aria-label="{{ $copy['nav']['main'] }}">
                <a href="{{ \App\LocalizedUrl::route('home', $locale, false) }}">{{ str_replace(':brand', $page['brand'], $copy['nav']['brand_home']) }}</a><span aria-hidden="true">/</span><span>{{ $page['breadcrumb'] }}</span>
            </nav>
            <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> {{ $page['eyebrow'] }}</p>
            <h1>{{ $page['h1'] }}</h1>
            <p>{{ $page['intro'] }}</p>
        </div>
    </section>

    <article class="legal-content section-pad">
        <p class="legal-updated">{{ $page['updated'] }}</p>
        @foreach ($page['sections'] as $section)
            <section>
                <h2>{{ $section['title'] }}</h2>
                @foreach ($section['paragraphs'] as $paragraph)
                    @php
                        if (str_contains($paragraph, ':policy_email')) {
                            if (! filled($page['policy_contact_email'] ?? null)) {
                                $paragraph = null;
                            } else {
                                $escapedPolicyEmail = e($page['policy_contact_email']);
                                $paragraph = str_replace(':policy_email', '<a href="mailto:'.$escapedPolicyEmail.'">'.$escapedPolicyEmail.'</a>', $paragraph);
                            }
                        } elseif (str_contains($paragraph, ':controller') && (! $page['controller_name'] || ! $page['contact_email'])) {
                            $paragraph = __('marketing.privacy.unconfigured_contact');
                        } else {
                            $paragraph = str_replace(':controller', e($page['controller_name'] ?? ''), $paragraph);
                            if (str_contains($paragraph, ':email') && ! $page['contact_email']) {
                                $paragraph = __('marketing.privacy.unconfigured_contact');
                            } elseif ($page['contact_email']) {
                                $escapedEmail = e($page['contact_email']);
                                $paragraph = str_replace(':email', '<a href="mailto:'.$escapedEmail.'">'.$escapedEmail.'</a>', $paragraph);
                            }
                        }
                    @endphp
                    @if (filled($paragraph))
                        <p>{!! $paragraph !!}</p>
                    @endif
                @endforeach
            </section>
        @endforeach
    </article>
@endsection
