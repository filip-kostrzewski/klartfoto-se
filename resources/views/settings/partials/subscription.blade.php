@php
    $planLabel = $company->plan === \App\CompanyPlan::Team ? 'Team' : 'Start';
    $trialDaysLeft = $company->trialDaysLeft();
    $billingReady = $company->billingIsReady();
    $subscriptionActive = $company->subscribed('default');
@endphp

<p>Nuvarande plan: {{ $planLabel }}</p>

@if ($company->onTrial() && $company->trial_ends_at)
    <p>
        @if ($trialDaysLeft === 1)
            1 dag kvar.
        @else
            {{ $trialDaysLeft }} dagar kvar.
        @endif
        Provperioden gäller till {{ $company->trial_ends_at->locale('sv')->translatedFormat('j F Y') }}.
    </p>
@elseif ($subscriptionActive)
    <p>Abonnemanget är aktivt.</p>
@else
    <p>Provperioden är slut.</p>
@endif

<div class="plan-grid">
    <article class="plan-card">
        <h3>Start</h3>
        <p>290 kr/mån exkl. moms</p>
        <p>1 användare</p>
        <p>Ingen bindningstid.</p>
        @if ($billingReady && $isOwner)
            <form class="app-form" method="post" action="{{ route('settings.billing.checkout', absolute: false) }}">
                @csrf
                <input type="hidden" name="plan" value="start">
                <button class="button button-lime" type="submit">Välj Start</button>
            </form>
        @endif
    </article>
    <article class="plan-card">
        <h3>Team</h3>
        <p>590 kr/mån exkl. moms</p>
        <p>Upp till 10 användare och egen logotyp</p>
        <p>Ingen bindningstid.</p>
        @if ($billingReady && $isOwner)
            <form class="app-form" method="post" action="{{ route('settings.billing.checkout', absolute: false) }}">
                @csrf
                <input type="hidden" name="plan" value="team">
                <button class="button button-lime" type="submit">Välj Team</button>
            </form>
        @endif
    </article>
</div>

@unless ($billingReady)
    <p class="form-status" role="status">Betalning aktiveras snart</p>
@else
    @if (config('cashier.tax_enabled'))
        <p>Svensk moms läggs till i kassan.</p>
    @endif
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
