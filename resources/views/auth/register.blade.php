@extends('layouts.app')

@section('title', 'Skapa konto · Klartfoto')

@section('content')
    <section class="auth-card">
        <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> Nytt konto</p>
        <h1>Kom igång gratis</h1>
        <p class="auth-lead">Skapa konto för städföretaget. Provperioden kräver inget kort.</p>

        @include('auth.partials.google')

        @if ($errors->any())
            <div class="form-status form-status-error" role="alert">
                <strong>Kontrollera formuläret:</strong>
                <ul>
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form class="app-form" method="post" action="{{ route('register', absolute: false) }}">
            @csrf
            <label for="company_name">Företagsnamn</label>
            <input id="company_name" name="company_name" type="text" required maxlength="120" autocomplete="organization" value="{{ old('company_name') }}" aria-invalid="{{ $errors->has('company_name') ? 'true' : 'false' }}">

            <label for="email">E-postadress</label>
            <input id="email" name="email" type="email" inputmode="email" autocomplete="email" required maxlength="254" value="{{ old('email') }}" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">

            <label for="password">Lösenord</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" minlength="8" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">

            <label for="password_confirmation">Upprepa lösenordet</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">

            <label class="consent-label" for="privacy">
                <input id="privacy" type="checkbox" name="privacy" value="1" required @checked(old('privacy')) aria-invalid="{{ $errors->has('privacy') ? 'true' : 'false' }}">
                <span>Jag har läst <a href="{{ route('site.sv.privacy', absolute: false) }}">integritetspolicyn</a> och godkänner att Klartfoto sparar kontouppgifter, uppdrag och foton enligt den.</span>
            </label>

            <button class="button button-lime" type="submit">Skapa konto</button>
        </form>
        <p class="auth-switch">Har du redan konto? <a href="{{ route('login', absolute: false) }}">Logga in</a></p>
    </section>
@endsection
