@extends('layouts.app')

@section('title', 'Gå med · Klartfoto')

@section('content')
    <section class="auth-card">
        <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> Inbjudan</p>
        <h1>Gå med i {{ $invitation->company->name }}</h1>
        @if ($closed)
            <p class="form-status form-status-error" role="alert">Inbjudan är inte längre giltig. Be ägaren skicka en ny.</p>
        @else
            <p class="auth-lead">{{ $invitation->company->name }} har bjudit in {{ $invitation->email }}. Välj ett lösenord så hamnar du i samma företag.</p>

            @if ($errors->any())
                <div class="form-status form-status-error" role="alert">
                    <ul>
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form class="app-form" method="post" action="{{ route('invitations.accept.store', ['token' => $invitation->token], false) }}">
                @csrf
                <label for="name">Ditt namn</label>
                <input id="name" name="name" type="text" autocomplete="name" required maxlength="120" value="{{ old('name') }}">

                <label for="password">Lösenord</label>
                <input id="password" name="password" type="password" required autocomplete="new-password" minlength="8">

                <label for="password_confirmation">Bekräfta lösenord</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" minlength="8">

                <label class="consent-label" for="privacy">
                    <input id="privacy" type="checkbox" name="privacy" value="1" @checked(old('privacy'))>
                    <span>Jag har läst <a href="{{ route('site.sv.privacy', absolute: false) }}">integritetspolicyn</a>.</span>
                </label>

                <button class="button button-lime" type="submit">Skapa konto</button>
            </form>
        @endif
    </section>
@endsection
