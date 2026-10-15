@extends('layouts.app')

@section('title', 'Logga in · Klartfoto')

@section('content')
    <section class="auth-card">
        <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> Logga in</p>
        <h1>Välkommen tillbaka</h1>
        <p class="auth-lead">Logga in för att lägga upp före- och efterbilder.</p>

        @include('auth.partials.google')

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

        <form class="app-form" method="post" action="{{ route('login', absolute: false) }}">
            @csrf
            <label for="email">E-postadress</label>
            <input id="email" name="email" type="email" inputmode="email" autocomplete="username" required maxlength="254" value="{{ old('email') }}" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">

            <label for="password">Lösenord</label>
            <input id="password" name="password" type="password" required autocomplete="current-password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">

            <label class="consent-label" for="remember">
                <input id="remember" type="checkbox" name="remember" value="1" @checked(old('remember'))>
                <span>Kom ihåg mig på den här telefonen</span>
            </label>

            <button class="button button-lime" type="submit">Logga in</button>
        </form>
        <p class="auth-switch"><a href="{{ route('password.request', absolute: false) }}">Glömt lösenordet?</a></p>
        <p class="auth-switch">Ny här? <a href="{{ route('register', absolute: false) }}">Kom igång gratis</a></p>
    </section>
@endsection
