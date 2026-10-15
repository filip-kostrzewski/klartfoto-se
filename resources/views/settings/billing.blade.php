@extends('layouts.app')

@section('title', 'Abonnemang · Klartfoto')

@section('content')
    <section class="app-card">
        <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> Inställningar</p>
        <h1>Abonnemang</h1>
        <p class="auth-switch"><a href="{{ route('settings.edit', absolute: false) }}">Tillbaka till inställningar</a></p>

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

        @if ($billingReady && $checkoutSuccess)
            <div class="form-status form-status-success" role="status">Betalningen är mottagen. Abonnemanget uppdateras när Stripe har bekräftat den.</div>
        @endif

        @include('settings.partials.subscription')
    </section>
@endsection
