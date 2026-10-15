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

        <p>Nuvarande plan: {{ $planLabel }}</p>

        @if ($trialDaysLeft === 1)
            <p>1 dag kvar. @if ($company->onTrial() && $company->trial_ends_at)Provperioden gäller till {{ $company->trial_ends_at->locale('sv')->translatedFormat('j F Y') }}.@endif</p>
        @elseif ($trialDaysLeft > 1)
            <p>{{ $trialDaysLeft }} dagar kvar. @if ($company->onTrial() && $company->trial_ends_at)Provperioden gäller till {{ $company->trial_ends_at->locale('sv')->translatedFormat('j F Y') }}.@endif</p>
        @else
            <p>Provperioden är slut.</p>
        @endif

        @unless ($billingReady)
            <p class="form-status" role="status">Betalning aktiveras snart</p>
        @else
            @if (config('cashier.tax_enabled'))
                <p>Svensk moms läggs till i kassan.</p>
            @endif

            <div class="plan-grid">
                <article class="plan-card">
                    <h2>Start</h2>
                    <p>290 kr/mån exkl. moms</p>
                    @if ($isOwner)
                        <form class="app-form" method="post" action="{{ route('settings.billing.checkout', absolute: false) }}">
                            @csrf
                            <input type="hidden" name="plan" value="start">
                            <button class="button button-lime" type="submit">Välj Start</button>
                        </form>
                    @endif
                </article>
                <article class="plan-card">
                    <h2>Team</h2>
                    <p>590 kr/mån exkl. moms</p>
                    @if ($isOwner)
                        <form class="app-form" method="post" action="{{ route('settings.billing.checkout', absolute: false) }}">
                            @csrf
                            <input type="hidden" name="plan" value="team">
                            <button class="button button-lime" type="submit">Välj Team</button>
                        </form>
                    @endif
                </article>
            </div>

            @unless ($isOwner)
                <p>Bara ägaren kan ändra abonnemanget.</p>
            @else
                @if ($company->hasStripeId())
                    <form class="app-form" method="post" action="{{ route('settings.billing.portal', absolute: false) }}">
                        @csrf
                        <button class="button button-outline-dark" type="submit">Hantera betalning</button>
                    </form>
                @endif
            @endunless
        @endunless
    </section>
@endsection
