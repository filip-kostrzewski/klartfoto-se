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

    <section id="contact-form" class="section-pad" aria-labelledby="contact-form-heading">
        <div class="signup-form-wrap contact-form-wrap">
            <h2 id="contact-form-heading">{{ $page['form_heading'] }}</h2>

            @if (session('contact_status') === 'sent' || session('contact_status') === 'success')
                <p class="form-status" role="status" tabindex="-1" data-focus-status data-focus-on-load="true">{{ $page['status_success'] }}</p>
            @elseif (session('contact_status') === 'failed')
                <div class="form-status form-status-error" role="alert" tabindex="-1" data-focus-status data-focus-on-load="true">
                    <p>{{ $page['status_failed'] }}</p>
                    @if (filled($page['provider_contact_email'] ?? null))
                        <p>{{ $page['status_direct_email'] }} <a href="mailto:{{ $page['provider_contact_email'] }}">{{ $page['provider_contact_email'] }}</a></p>
                    @endif
                </div>
            @endif

            @if ($errors->any())
                <div class="form-status form-status-error" role="alert" tabindex="-1" data-focus-status data-focus-on-load="true" aria-labelledby="contact-error-heading">
                    <p id="contact-error-heading">{{ $page['error_heading'] }}</p>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form class="signup-form contact-form" method="POST" action="{{ \App\LocalizedUrl::route('contact.message', $locale, false) }}" data-submit-feedback>
                @csrf

                <div class="contact-honeypot" aria-hidden="true">
                    <label for="website">{{ $page['honeypot_label'] }}</label>
                    <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                </div>

                <label for="name">{{ $page['name_label'] }}</label>
                <input id="name" name="name" type="text" value="{{ is_string(old('name')) ? old('name') : '' }}" maxlength="120" autocomplete="name" required @if ($errors->has('name')) aria-invalid="true" aria-describedby="name-error" @endif>
                @if ($errors->has('name'))
                    <p class="field-error" id="name-error">{{ $errors->first('name') }}</p>
                @endif

                <label for="email">{{ $page['email_label'] }}</label>
                <input id="email" name="email" type="email" value="{{ is_string(old('email')) ? old('email') : '' }}" maxlength="254" autocomplete="email" required @if ($errors->has('email')) aria-invalid="true" aria-describedby="email-error" @endif>
                @if ($errors->has('email'))
                    <p class="field-error" id="email-error">{{ $errors->first('email') }}</p>
                @endif

                <label for="company">{{ $page['company_label'] }}</label>
                <input id="company" name="company" type="text" value="{{ is_string(old('company')) ? old('company') : '' }}" maxlength="160" autocomplete="organization" @if ($errors->has('company')) aria-invalid="true" aria-describedby="company-error" @endif>
                @if ($errors->has('company'))
                    <p class="field-error" id="company-error">{{ $errors->first('company') }}</p>
                @endif

                <label for="message">{{ $page['message_label'] }}</label>
                <textarea id="message" name="message" maxlength="5000" required @if ($errors->has('message')) aria-invalid="true" aria-describedby="message-error" @endif>{{ is_string(old('message')) ? old('message') : '' }}</textarea>
                @if ($errors->has('message'))
                    <p class="field-error" id="message-error">{{ $errors->first('message') }}</p>
                @endif

                <p class="form-privacy">{{ $page['privacy_note'] }} <a href="{{ \App\LocalizedUrl::route('privacy', $locale, false) }}">{{ $page['privacy_link'] }}</a></p>
                <button class="button button-lime signup-submit" type="submit" data-loading-text="{{ $page['sending'] }}">{{ $page['submit'] }}</button>
            </form>
        </div>

        @if (filled($page['provider_contact_email'] ?? null))
            <p class="contact-direct-link">{{ $page['direct_email_label'] }} <a href="mailto:{{ $page['provider_contact_email'] }}">{{ $page['provider_contact_email'] }}</a></p>
        @endif
    </section>
@endsection
