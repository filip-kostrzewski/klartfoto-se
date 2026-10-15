@extends('layouts.app')

@section('title', 'Glömt lösenord · Klartfoto')

@section('content')
    <section class="auth-card">
        <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> Lösenord</p>
        <h1>Återställ lösenord</h1>
        <p class="auth-lead">Skriv e-postadressen till kontot. Om den finns hos oss skickar vi en länk.</p>

        @if (session('status'))
            <div class="form-status form-status-success" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="form-status form-status-error" role="alert">
                <ul>
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form class="app-form" method="post" action="{{ route('password.email', absolute: false) }}">
            @csrf
            <label for="email">E-postadress</label>
            <input id="email" name="email" type="email" inputmode="email" autocomplete="username" required maxlength="254" value="{{ old('email') }}" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
            <button class="button button-lime" type="submit">Skicka länk</button>
        </form>
        <p class="auth-switch"><a href="{{ route('login', absolute: false) }}">Tillbaka till inloggningen</a></p>
    </section>
@endsection
