@extends('layouts.app')

@section('title', 'Logga in · Klartfoto')

@section('content')
    <div class="auth-layout">
        @include('auth.partials.visual', [
            'authVisualTitle' => 'Ett tydligt avslut på varje uppdrag.',
            'authVisualText' => 'Samla bilderna i en rapport och dela den med kunden när arbetet är klart.',
        ])
        <section class="auth-card" aria-labelledby="auth-title">
            <div class="auth-card-heading">
                <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> Klartfoto</p>
                <h1 id="auth-title">Välkommen tillbaka</h1>
                <p class="auth-lead">Logga in för att hantera dina uppdrag och rapporter.</p>
            </div>

            @if (session('status'))
                <div class="form-status form-status-success" role="status">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="form-status form-status-error" role="alert" tabindex="-1" data-focus-status>
                    <strong>Kontrollera uppgifterna</strong>
                    <ul>
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @include('auth.partials.google')

            <form class="app-form auth-form" method="post" action="{{ route('login', absolute: false) }}" data-submit-feedback>
                @csrf
                <label for="email">E-postadress</label>
                <input id="email" name="email" type="email" inputmode="email" autocomplete="username" required maxlength="254" value="{{ old('email') }}" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" @if ($errors->has('email')) aria-describedby="email-error" @endif>
                @error('email') <span class="field-error" id="email-error">{{ $message }}</span> @enderror

                <div class="field-label-row"><label for="password">Lösenord</label><a href="{{ route('password.request', absolute: false) }}">Glömt lösenordet?</a></div>
                <div class="password-field">
                    <input id="password" name="password" type="password" required autocomplete="current-password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" @if ($errors->has('password')) aria-describedby="password-error" @endif>
                    <button class="password-toggle" type="button" hidden data-password-toggle data-show-label="Visa" data-hide-label="Dölj" aria-label="Visa lösenord" aria-pressed="false"><x-icon name="eye" /><span>Visa</span></button>
                </div>
                @error('password') <span class="field-error" id="password-error">{{ $message }}</span> @enderror

                <label class="consent-label" for="remember">
                    <input id="remember" type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    <span>Kom ihåg mig på den här telefonen</span>
                </label>

                <button class="button button-lime auth-submit" type="submit" data-loading-text="Loggar in…">Logga in <span aria-hidden="true">↗</span></button>
            </form>
            <p class="auth-switch">Nytt konto? <a href="{{ route('register', absolute: false) }}">Prova Klartfoto gratis i 30 dagar</a></p>
        </section>
    </div>
@endsection
