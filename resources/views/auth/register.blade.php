@extends('layouts.app')

@section('title', 'Skapa konto · Klartfoto')

@section('content')
    <div class="auth-layout auth-layout-register">
        @include('auth.partials.visual', [
            'authVisualTitle' => 'Fota. Samla. Dela.',
            'authVisualText' => 'Skapa en rapport med kundens uppgifter och bilder från uppdraget.',
        ])
        <section class="auth-card" aria-labelledby="auth-title">
            <div class="auth-card-heading">
                <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> Nytt konto</p>
                <h1 id="auth-title">Kom igång gratis</h1>
                <p class="auth-lead">30 dagar utan kostnad. Inget betalkort behövs.</p>
            </div>

            @if ($errors->any())
                <div class="form-status form-status-error" role="alert" tabindex="-1" data-focus-status>
                    <strong>Kontrollera formuläret</strong>
                    <ul>
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @include('auth.partials.google')

            <form class="app-form auth-form" method="post" action="{{ route('register', absolute: false) }}" data-submit-feedback>
                @csrf
                <label for="company_name">Företagsnamn</label>
                <input id="company_name" name="company_name" type="text" required maxlength="120" autocomplete="organization" value="{{ old('company_name') }}" aria-invalid="{{ $errors->has('company_name') ? 'true' : 'false' }}" @if ($errors->has('company_name')) aria-describedby="company-name-error" @endif>
                @error('company_name') <span class="field-error" id="company-name-error">{{ $message }}</span> @enderror

                <label for="name">Ditt namn</label>
                <input id="name" name="name" type="text" required maxlength="120" autocomplete="name" value="{{ old('name') }}" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" @if ($errors->has('name')) aria-describedby="name-error" @endif>
                @error('name') <span class="field-error" id="name-error">{{ $message }}</span> @enderror

                <label for="email">E-postadress</label>
                <input id="email" name="email" type="email" inputmode="email" autocomplete="email" required maxlength="254" value="{{ old('email') }}" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" @if ($errors->has('email')) aria-describedby="email-error" @endif>
                @error('email') <span class="field-error" id="email-error">{{ $message }}</span> @enderror

                <label for="password">Lösenord <span class="label-hint">Minst 8 tecken</span></label>
                <div class="password-field">
                    <input id="password" name="password" type="password" required autocomplete="new-password" minlength="8" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" @if ($errors->has('password')) aria-describedby="password-error" @endif>
                    <button class="password-toggle" type="button" hidden data-password-toggle data-show-label="Visa" data-hide-label="Dölj" aria-label="Visa lösenord" aria-pressed="false"><x-icon name="eye" /><span>Visa</span></button>
                </div>
                @error('password') <span class="field-error" id="password-error">{{ $message }}</span> @enderror

                <label for="password_confirmation">Upprepa lösenordet</label>
                <div class="password-field">
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" aria-invalid="{{ $errors->has('password_confirmation') ? 'true' : 'false' }}" @if ($errors->has('password_confirmation')) aria-describedby="password-confirmation-error" @endif>
                    <button class="password-toggle" type="button" hidden data-password-toggle data-show-label="Visa" data-hide-label="Dölj" aria-label="Visa lösenord" aria-pressed="false"><x-icon name="eye" /><span>Visa</span></button>
                </div>
                @error('password_confirmation') <span class="field-error" id="password-confirmation-error">{{ $message }}</span> @enderror

                <label class="consent-label" for="privacy">
                    <input id="privacy" type="checkbox" name="privacy" value="1" required @checked(old('privacy')) aria-invalid="{{ $errors->has('privacy') ? 'true' : 'false' }}" @if ($errors->has('privacy')) aria-describedby="privacy-error" @endif>
                    <span>Jag har läst <a href="{{ route('site.sv.privacy', absolute: false) }}">integritetspolicyn</a> och godkänner att Klartfoto sparar kontouppgifter, uppdrag och foton enligt den.</span>
                </label>
                @error('privacy') <span class="field-error" id="privacy-error">{{ $message }}</span> @enderror

                <button class="button button-lime auth-submit" type="submit" data-loading-text="Skapar konto…">Skapa konto <span aria-hidden="true">↗</span></button>
            </form>
            <p class="auth-switch">Har du redan konto? <a href="{{ route('login', absolute: false) }}">Logga in</a></p>
        </section>
    </div>
@endsection
