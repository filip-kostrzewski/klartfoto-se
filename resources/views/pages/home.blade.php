@extends('layouts.site')

@section('content')
    @php($home = $copy['home'])
    @php($form = $home['signup'])

    <section class="hero">
        <div class="hero-inner">
            <div class="hero-copy">
                <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span>{{ $copy['nav']['announcement'] }}</p>
                <h1>{{ $home['h1_start'] }}<br><span>{{ $home['h1_emphasis'] }}</span></h1>
                <p class="hero-lead">{{ $home['lead'] }}</p>
                <div class="hero-actions">
                    <a class="button button-lime" href="#signup">{{ $home['cta_primary'] }} <span aria-hidden="true">↗</span></a>
                    <a class="button button-outline" href="#sample-report">{{ $home['cta_secondary'] }} <span aria-hidden="true">↓</span></a>
                </div>
                <p class="hero-footnote">{{ $home['footnote'] }}</p>
                <div class="hero-trust" aria-label="{{ $home['scope_label'] }}">
                    <span><i aria-hidden="true">01</i> {{ $home['trust_1'] }}</span>
                    <span><i aria-hidden="true">02</i> {{ $home['trust_2'] }}</span>
                </div>
            </div>

            <div class="hero-visual">
                <div class="hero-photo-wrap">
                    <div class="photo-caption">
                        <span class="caption-dot" aria-hidden="true"></span>
                        <span>{{ $home['photo_note'] }}</span>
                    </div>
                    <div class="photo-comparison" data-comparison style="--split: 54%;">
                        <img
                            class="comparison-image comparison-after"
                            src="{{ asset('images/demo/kitchen-after-768.webp') }}"
                            srcset="{{ asset('images/demo/kitchen-after-480.webp') }} 480w, {{ asset('images/demo/kitchen-after-768.webp') }} 768w"
                            sizes="(max-width: 900px) calc(100vw - 40px), 43vw"
                            width="768"
                            height="1024"
                            alt="{{ $home['photo_alt'] }}"
                            fetchpriority="high"
                            decoding="async">
                        <div class="comparison-before">
                            <img
                                class="comparison-image"
                                src="{{ asset('images/demo/kitchen-before-768.webp') }}"
                                srcset="{{ asset('images/demo/kitchen-before-480.webp') }} 480w, {{ asset('images/demo/kitchen-before-768.webp') }} 768w"
                                sizes="(max-width: 900px) calc(100vw - 40px), 43vw"
                                width="768"
                                height="1024"
                                alt=""
                                aria-hidden="true"
                                decoding="async">
                        </div>
                        <span class="photo-label photo-label-before">{{ $home['before'] }}</span>
                        <span class="photo-label photo-label-after">{{ $home['after'] }}</span>
                        <input
                            class="comparison-control"
                            type="range"
                            min="0"
                            max="100"
                            value="54"
                            aria-label="{{ $home['slider_label'] }}"
                            aria-valuetext="54 {{ $home['slider_before_text'] }}, {{ $home['slider_after_text'] }}"
                            data-before-text="{{ $home['slider_before_text'] }}"
                            data-after-text="{{ $home['slider_after_text'] }}"
                            data-description-template="{{ $home['slider_description'] }}">
                        <span class="comparison-divider" aria-hidden="true"></span>
                        <span class="comparison-handle" aria-hidden="true"><span></span></span>
                        <span class="sr-only" data-comparison-description aria-live="polite">{{ str_replace(':percent', '54', $home['slider_description']) }}</span>
                    </div>
                    <div class="photo-meta">
                        <span>{{ $home['same_view'] }}</span>
                        <span class="photo-meta-badge">{{ $home['photo_detail'] }}</span>
                    </div>
                </div>
                <div class="floating-report">
                    <div class="floating-report-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M7 3.75h7l4 4V20a.75.75 0 0 1-.75.75h-10.5A.75.75 0 0 1 6 20V4.5a.75.75 0 0 1 .75-.75Z" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M14 4v4h4M9 12h6M9 15.5h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div>
                        <span class="floating-report-label">{{ $home['report_fragment'] }}</span>
                        <strong>{{ $home['report_fragment_title'] }}</strong>
                        <span class="floating-report-note">{{ $home['report_fragment_note'] }}</span>
                    </div>
                    <span class="floating-report-arrow" aria-hidden="true">↗</span>
                </div>
                <span class="hero-index" aria-hidden="true">{{ $home['hero_index'] }}</span>
            </div>
        </div>
        <div class="hero-bottom">
            <span>{{ $home['hero_bottom_1'] }}</span>
            <span>{{ $home['hero_bottom_2'] }}</span>
        </div>
    </section>

    <section class="intro-section section-pad" id="how-it-works">
        <div class="section-kicker"><span>01</span> {{ $home['intro_kicker'] }}</div>
        <div class="intro-grid">
            <h2>{{ $home['intro_title'] }}<br><em>{{ $home['intro_emphasis'] }}</em></h2>
            <div class="intro-copy"><p>{{ $home['intro_copy'] }}</p></div>
        </div>
        <div class="value-list">
            @foreach ($home['values'] as $index => $item)
                <article>
                    <span class="value-number">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                    <h3>{{ $item['title'] }}</h3>
                    <p>{{ $item['text'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="industries-section section-pad" id="for-who">
        <div class="section-topline">
            <div class="section-kicker"><span>02</span> {{ $home['industries_kicker'] }}</div>
            <p>{{ $home['industries_intro'] }}</p>
        </div>
        <div class="industry-list">
            @foreach ($home['industries'] as $index => $industry)
                <a class="industry-row" href="{{ \App\LocalizedUrl::route($industry['key'], $locale, false) }}">
                    <span class="industry-index">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                    <span class="industry-copy">
                        <small>{{ $industry['label'] }}</small>
                        <strong>{{ $industry['title'] }}</strong>
                        <span>{{ $industry['text'] }}</span>
                    </span>
                    <span class="industry-arrow" aria-hidden="true">↗</span>
                </a>
            @endforeach
        </div>
    </section>

    <section class="sample-section section-pad" id="sample-report">
        <div class="sample-heading">
            <div>
                <div class="section-kicker"><span>03</span> {{ $home['sample_kicker'] }}</div>
                <h2>{{ $home['sample_title'] }}<br><em>{{ $home['sample_emphasis'] }}</em></h2>
            </div>
            <a class="text-link" href="{{ \App\LocalizedUrl::route('report', $locale, false) }}">{{ $home['sample_link'] }} <span aria-hidden="true">↗</span></a>
        </div>
        <div class="sample-layout">
            <div class="sample-explainer">
                <p class="sample-disclaimer"><span aria-hidden="true">✳</span> {{ $home['sample_disclaimer'] }}</p>
                <p>{{ $home['sample_intro'] }}</p>
                <ul class="sample-points">
                    @foreach ($home['sample_points'] as $point)
                        <li><span aria-hidden="true">↳</span> {{ $point }}</li>
                    @endforeach
                </ul>
                <a class="button button-dark" href="{{ \App\LocalizedUrl::route('report', $locale, false) }}">{{ $home['sample_cta'] }} <span aria-hidden="true">↗</span></a>
            </div>
            <div class="report-card" aria-label="{{ $home['sample_card_aria'] }}">
                <div class="report-card-header">
                    <div>
                        <span class="report-overline">{{ $home['sample_overline'] }}</span>
                        <h3>{{ $home['sample_card_title'] }}</h3>
                    </div>
                    <span class="report-mark" aria-hidden="true">
                        <svg viewBox="0 0 32 32" fill="none"><path d="M5 5h10v22H5z" fill="currentColor"/><path d="M17 5h10v10H17z" fill="#A4BD45"/><path d="M17 17h10v10H17z" fill="currentColor"/></svg>
                    </span>
                </div>
                <div class="report-card-meta">
                    <div><span>{{ $home['object_label'] }}</span><strong>{{ $home['object_value'] }}</strong></div>
                    <div><span>{{ $home['place_label'] }}</span><strong>{{ $home['place_value'] }}</strong></div>
                    <div><span>{{ $home['status_label'] }}</span><strong class="status-ready"><i aria-hidden="true"></i> {{ $home['status_value'] }}</strong></div>
                </div>
                <div class="report-photo-row">
                    <div class="report-photo report-photo-before">
                        <img src="{{ asset('images/demo/kitchen-before-480.webp') }}" width="480" height="640" alt="" loading="lazy" decoding="async">
                        <span>{{ $home['before'] }}</span>
                    </div>
                    <div class="report-photo report-photo-after">
                        <img src="{{ asset('images/demo/kitchen-after-480.webp') }}" width="480" height="640" alt="" loading="lazy" decoding="async">
                        <span>{{ $home['after'] }}</span>
                    </div>
                    <div class="report-note">
                        <span>{{ $home['note_label'] }}</span>
                        <p>{{ $home['note_text'] }}</p>
                    </div>
                </div>
                <div class="report-card-footer">
                    <span>{{ $home['report_footer'] }}</span>
                    <span aria-hidden="true">R/01</span>
                </div>
            </div>
        </div>
    </section>

    <section class="future-section section-pad">
        <div class="future-stamp">{{ $home['future_stamp'] }}</div>
        <div class="future-copy">
            <div class="section-kicker"><span>04</span> {{ $home['future_kicker'] }}</div>
            <h2>{{ $home['future_title'] }}<br><em>{{ $home['future_emphasis'] }}</em></h2>
            <p>{{ $home['future_copy'] }}</p>
            <a class="text-link" href="{{ \App\LocalizedUrl::route('report', $locale, false) }}">{{ $home['future_link'] }} <span aria-hidden="true">↗</span></a>
        </div>
    </section>

    <section class="signup-section section-pad" id="signup">
        <div class="signup-copy">
            <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> {{ $form['eyebrow'] }}</p>
            <h2>{{ $form['title'] }}<br><em>{{ $form['emphasis'] }}</em></h2>
            <p>{{ $form['body'] }}</p>
            <p class="signup-small">{{ $form['privacy_note'] }}</p>
        </div>
        <div class="signup-form-wrap">
            @if ($errors->any())
                <div class="form-status form-status-error" role="alert" tabindex="-1" data-focus-status>
                    <strong>{{ $form['error_heading'] }}</strong>
                    <ul>
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('signup_status'))
                <div class="form-status form-status-success" role="status" tabindex="-1" data-focus-status>{{ session('signup_status') }}</div>
            @endif

            @if ($page['waitlist_open'])
                <form class="signup-form" method="post" action="{{ \App\LocalizedUrl::route('signup', $locale, false) }}">
                    @csrf
                    <div class="honeypot" aria-hidden="true">
                        <input name="company_url" type="text" tabindex="-1" autocomplete="off">
                    </div>
                    <label for="email">{{ $form['email_label'] }}</label>
                    <input id="email" name="email" type="email" inputmode="email" autocomplete="email" maxlength="254" required value="{{ old('email') }}" placeholder="{{ $form['email_placeholder'] }}" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" aria-describedby="email-hint{{ $errors->has('email') ? ' email-error' : '' }}">
                    <span id="email-hint" class="field-hint">{{ $form['email_hint'] }}</span>
                    @error('email') <span class="field-error" id="email-error">{{ $message }}</span> @enderror

                    <label for="industry">{{ $form['industry_label'] }}</label>
                    <select id="industry" name="industry" required aria-invalid="{{ $errors->has('industry') ? 'true' : 'false' }}" aria-describedby="industry-hint{{ $errors->has('industry') ? ' industry-error' : '' }}">
                        <option value="" disabled @selected(! old('industry'))>{{ $form['industry_placeholder'] }}</option>
                        @foreach ($form['industries'] as $industry => $label)
                            <option value="{{ $industry }}" @selected(old('industry') === $industry)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <span id="industry-hint" class="field-hint">{{ $form['industry_hint'] }}</span>
                    @error('industry') <span class="field-error" id="industry-error">{{ $message }}</span> @enderror

                    <label class="consent-label" for="consent">
                        <input id="consent" type="checkbox" name="consent" value="1" required @checked(old('consent')) aria-invalid="{{ $errors->has('consent') ? 'true' : 'false' }}" aria-describedby="consent-hint{{ $errors->has('consent') ? ' consent-error' : '' }}">
                        <span id="consent-hint">{{ $form['consent'] }}</span>
                    </label>
                    @error('consent') <span class="field-error" id="consent-error">{{ $message }}</span> @enderror

                    <button class="button button-lime signup-submit" type="submit">{{ $form['submit'] }} <span aria-hidden="true">↗</span></button>
                    <p class="form-privacy">{{ $form['privacy_link'] }}: <a href="{{ \App\LocalizedUrl::route('privacy', $locale, false) }}">{{ $copy['nav']['privacy'] }}</a>.</p>
                </form>
            @else
                <div class="signup-closed" role="status">
                    <span class="signup-closed-icon" aria-hidden="true">↗</span>
                    <h3>{{ $form['closed_title'] }}</h3>
                    <p>{{ $form['closed_body'] }}</p>
                    <a class="button button-outline" href="{{ \App\LocalizedUrl::route('report', $locale, false) }}">{{ $form['closed_link'] }} <span aria-hidden="true">↗</span></a>
                    @if ($page['contact_email'])
                        <a class="text-link signup-closed-contact" href="mailto:{{ $page['contact_email'] }}">{{ $form['closed_contact'] }} <span aria-hidden="true">↗</span></a>
                    @endif
                </div>
            @endif
        </div>
    </section>
@endsection
