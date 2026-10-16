@extends('layouts.app')

@section('title', 'Bekräftelselänken fungerar inte · Klartfoto')

@section('content')
    <div class="auth-layout">
        @include('auth.partials.visual', [
            'authVisualTitle' => 'Ett tydligt avslut på varje uppdrag.',
            'authVisualText' => 'Samla bilderna i en rapport och dela den med kunden när arbetet är klart.',
        ])

        <section class="auth-card" aria-labelledby="auth-title">
            <div class="auth-card-heading">
                <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> Bekräfta e-post</p>
                <h1 id="auth-title">Länken fungerar inte</h1>
                <p class="auth-lead">Bekräftelselänken är ogiltig eller har gått ut. Av säkerhetsskäl kan vi inte använda den här länken.</p>
                <p class="auth-lead">Länkar fungerar i 60 minuter. Använd den senaste länken du fått.</p>
            </div>

            @if (session('status'))
                <div class="form-status form-status-success" role="status">{{ session('status') }}</div>
            @endif

            @auth
                @if (! auth()->user()->hasVerifiedEmail())
                    <p class="auth-lead">Du kan skicka en ny länk till e-postadressen på kontot du är inloggad på.</p>
                    <form class="app-form auth-form" method="post" action="{{ route('verification.send', absolute: false) }}" data-submit-feedback>
                        @csrf
                        <button class="button button-lime auth-submit" type="submit" data-loading-text="Skickar…">Skicka en ny bekräftelselänk</button>
                    </form>
                @else
                    <a class="button button-lime auth-submit" href="{{ route('assignments.index', absolute: false) }}">Till uppdragen</a>
                @endif
            @else
                <p class="auth-lead">Logga in på kontot du vill bekräfta. Där kan du skicka en ny länk.</p>
                <a class="button button-lime auth-submit" href="{{ route('login', absolute: false) }}">Logga in</a>
            @endauth
        </section>
    </div>
@endsection
