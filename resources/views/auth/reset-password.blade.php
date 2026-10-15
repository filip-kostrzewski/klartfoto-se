@extends('layouts.app')

@section('title', 'Nytt lösenord · Klartfoto')

@section('content')
    <section class="auth-card">
        <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> Nytt lösenord</p>
        <h1>Byt lösenord</h1>

        @if ($errors->any())
            <div class="form-status form-status-error" role="alert">
                <ul>
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form class="app-form" method="post" action="{{ route('password.update', absolute: false) }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label for="email">E-postadress</label>
            <input id="email" name="email" type="email" inputmode="email" autocomplete="username" required maxlength="254" value="{{ old('email', $email) }}" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">

            <label for="password">Nytt lösenord</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" minlength="8">

            <label for="password_confirmation">Upprepa lösenordet</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">

            <button class="button button-lime" type="submit">Spara lösenordet</button>
        </form>
    </section>
@endsection
