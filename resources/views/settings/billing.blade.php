@extends('layouts.app')

@section('title', 'Abonnemang · Klartfoto')
@section('page-heading', 'Abonnemang')

@section('content')
    <section class="billing-page">
        <div class="app-page-heading">
            <div>
                <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> Inställningar</p>
                <h1>Abonnemang</h1>
                <p class="auth-lead">Jämför planerna och skicka en fakturaförfrågan via Fortnox.</p>
                <p class="auth-lead">Läs <a href="{{ route('site.sv.terms', absolute: false) }}">användarvillkoren</a> innan ni begär faktura.</p>
            </div>
            <a class="button button-outline-dark" href="{{ route('settings.edit', absolute: false) }}">Tillbaka till inställningar</a>
        </div>

        @if (session('status') && ! $hasPendingInvoiceRequest)
            <div class="form-status form-status-success" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="form-status form-status-error" role="alert" tabindex="-1" data-focus-status>Kontrollera de markerade uppgifterna nedan.</div>
        @endif

        <section class="settings-card billing-card" aria-labelledby="billing-plans-heading">
            <header class="settings-card-heading">
                <span class="workflow-icon"><x-icon name="report" /></span>
                <div><h2 id="billing-plans-heading">Plan och faktura</h2><p>Välj plan och ange uppgifterna som ska stå på fakturan.</p></div>
            </header>
            @include('settings.partials.subscription')
        </section>
    </section>
@endsection
