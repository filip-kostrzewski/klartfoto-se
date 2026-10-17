@extends('layouts.app')

@section('title', 'Glömt lösenord · Klartfoto')

@section('content')
    <div class="auth-layout auth-layout-compact">
        @include('auth.partials.visual', [
            'authVisualTitle' => 'Tillbaka till era rapporter.',
            'authVisualText' => 'Återställ lösenordet med länken vi skickar till din e-postadress.',
        ])
        <section class="auth-card" aria-label="Återställ lösenord">
            <div class="auth-card-heading">
                <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> Konto</p>
                <h1>Återställ lösenord</h1>
                <p class="auth-lead">Skriv e-postadressen till kontot. Om den finns hos oss skickar vi en länk.</p>
            </div>

            @if (session('status'))
                <div class="form-status form-status-success" role="status">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="form-status form-status-error" role="alert" tabindex="-1" data-focus-status>
                    <ul>
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form class="app-form auth-form" method="post" action="{{ route('password.email', absolute: false) }}" data-submit-feedback>
                @csrf
                <label for="email">E-postadress</label>
                <input id="email" name="email" type="email" inputmode="email" autocomplete="username" required maxlength="254" value="{{ old('email') }}" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" @if ($errors->has('email')) aria-describedby="email-error" @endif>
                @error('email') <span class="field-error" id="email-error">{{ $message }}</span> @enderror
                <button class="button button-lime auth-submit" type="submit" data-loading-text="Skickar…">Skicka länk</button>
            </form>
            <p class="auth-switch"><a href="{{ route('login', absolute: false) }}">Tillbaka till inloggningen</a></p>
        </section>
    </div>
@endsection
