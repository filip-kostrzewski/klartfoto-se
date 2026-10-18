@php
    $compact = $compact ?? false;
    $currentPlan = $company->plan === \App\CompanyPlan::Team ? 'Team' : 'Start';
    $requestedPlan = $company->requested_plan instanceof \App\CompanyPlan
        ? $company->requested_plan->value
        : $company->requested_plan;
    $requestedPlanLabel = $requestedPlan === 'team' ? 'Team' : 'Start';
    $requestedIntervalLabel = $company->requested_interval?->label();
    $billingDetails = is_array($company->billing_details) ? $company->billing_details : [];
    $selectedPlan = old('plan', $isManualPlanActive ? ($company->plan->value ?? 'team') : 'team');
    $selectedInterval = old('interval', $company->billing_interval?->value ?? \App\BillingInterval::Month->value);
    $startMonthly = \App\PlanPricing::formatted(\App\CompanyPlan::Start, \App\BillingInterval::Month);
    $startYearly = \App\PlanPricing::formatted(\App\CompanyPlan::Start, \App\BillingInterval::Year);
    $teamMonthly = \App\PlanPricing::formatted(\App\CompanyPlan::Team, \App\BillingInterval::Month);
    $teamYearly = \App\PlanPricing::formatted(\App\CompanyPlan::Team, \App\BillingInterval::Year);
    $freeMonths = \App\PlanPricing::freeMonthsPerYear(\App\CompanyPlan::Team);
    $teamSeats = (int) config('klartfoto.team_seat_limit', 10);
@endphp

<div class="subscription-panel">
    <div class="billing-overview">
        <div class="billing-status {{ $isManualPlanActive ? 'billing-status-active' : ($isTrialActive ? 'billing-status-trial' : 'billing-status-expired') }}" role="status">
            <span class="billing-status-icon" aria-hidden="true">{{ $isTrialActive || $isManualPlanActive ? '✓' : '!' }}</span>
            <span class="billing-status-copy">
                @if ($isManualPlanActive)
                    <strong>Abonnemanget är aktivt</strong>
                    <span>Planen gäller till {{ $company->paid_until?->locale('sv')->translatedFormat('j F Y') }}. Abonnemanget löper tills vidare och nästa period faktureras {{ $company->billing_interval === \App\BillingInterval::Year ? 'årsvis' : 'månadsvis' }} i förskott.</span>
                @elseif ($isTrialActive)
                    <strong>Provperiod pågår</strong>
                    <span>Gratis till {{ $company->trial_ends_at?->locale('sv')->translatedFormat('j F Y') }}. Inget kort behövs. Välj plan innan dess så fortsätter du utan avbrott. Annars pausas nya uppdrag och befintliga rapporter blir skrivskyddade.</span>
                @else
                    <strong>Ingen aktiv betald plan</strong>
                    <span>Befintliga rapporter finns kvar i skrivskyddat läge. Begär en faktura så aktiverar vi planen när betalningen är registrerad.</span>
                @endif
            </span>
        </div>

        <div class="billing-current-plan">
            <span>Nuvarande plan</span>
            <strong>{{ $currentPlan }}</strong>
        </div>
    </div>

    @if (! $compact && ! ($isManualPlanActive && $company->plan === \App\CompanyPlan::Team))
        <p class="subscription-summary-note">{{ $company->afterTrialAccessSummary() }}</p>
    @endif

    @if ($hasPendingInvoiceRequest)
        <div class="billing-pending" role="status">
            <div class="billing-pending-heading">
                <span class="billing-pending-mark" aria-hidden="true">↗</span>
                <div>
                    <strong>Fakturaförfrågan väntar</strong>
                    <span>{{ $requestedPlanLabel }}@if ($requestedIntervalLabel) · {{ $requestedIntervalLabel }}@endif · skickad {{ $company->requested_at?->locale('sv')->translatedFormat('j F Y') }}</span>
                </div>
            </div>
            <p>Begäran är inte en betalning. Vi skickar faktura via Fortnox och aktiverar planen när betalningen har registrerats.</p>
            @if ($isOwner && filled($billingDetails['company_name'] ?? null))
                <p class="billing-pending-meta">{{ $billingDetails['company_name'] }} · {{ $billingDetails['invoice_email'] ?? '' }}</p>
            @endif
        </div>
    @endif

    @if ($compact)
        @if (! $hasPendingInvoiceRequest && $isManualPlanActive)
            <p class="subscription-summary-note">
                @if ($isOwner && $canRequestInvoice)
                    Du kan byta plan eller faktureringsperiod när som helst.
                @elseif ($isOwner)
                    Ett annat abonnemang är aktivt för kontot, så en Fortnox-faktura kan inte begäras just nu.
                @else
                    Plan och faktura hanteras av kontoägaren.
                @endif
            </p>
        @elseif (! $hasPendingInvoiceRequest && ! $canManageJobs)
            <p class="subscription-summary-note">
                @if ($isOwner && $canRequestInvoice)
                    Begär en Fortnox-faktura för att återaktivera planen.
                @elseif ($isOwner)
                    Ett annat abonnemang är aktivt för kontot, så en Fortnox-faktura kan inte begäras just nu.
                @else
                    Be kontoägaren begära en faktura för att återaktivera planen.
                @endif
            </p>
        @elseif (! $hasPendingInvoiceRequest && $isOwner && ! $canRequestInvoice)
            <p class="subscription-summary-note">Ett annat abonnemang är aktivt för kontot, så en Fortnox-faktura kan inte begäras just nu.</p>
        @endif
        <a class="button {{ $isManualPlanActive ? 'button-outline-dark' : 'button-lime' }} subscription-manage-link" href="{{ route('settings.billing', absolute: false) }}">{{ $isManualPlanActive ? 'Visa abonnemang' : 'Välj plan' }}</a>
    @else
        @if ($canRequestInvoice)
            <form class="billing-request-form app-form" method="post" action="{{ route('settings.billing.request', absolute: false) }}" data-submit-feedback>
                @csrf

                <fieldset class="billing-plan-fieldset" aria-describedby="billing-plan-help">
                    <legend>Välj plan</legend>
                    <p id="billing-plan-help" class="billing-field-help">
                        @if ($isManualPlanActive)
                            Välj om du vill byta plan eller faktureringsperiod. Ändringen gäller från nästa period.
                        @else
                            Under provperioden har du allt i Team. Välj Team för att behålla användarna och logotypen.
                        @endif
                    </p>
                    @error('plan') <span class="field-error" id="plan-error">{{ $message }}</span> @enderror

                    <div class="plan-grid">
                        <label class="plan-card {{ $selectedPlan === 'start' ? 'is-selected' : '' }}">
                            <input class="plan-option-input" type="radio" name="plan" value="start" required @checked($selectedPlan === 'start') aria-invalid="{{ $errors->has('plan') ? 'true' : 'false' }}" @if ($errors->has('plan')) aria-describedby="plan-error" @endif>
                            <span class="plan-card-content">
                                <span class="plan-card-heading"><strong>Start</strong><span>En användare</span></span>
                                <span class="plan-price">{{ $startMonthly }}/mån <small>exkl. moms</small></span>
                                <span class="plan-feature-list">eller {{ $startYearly }}/år · Obegränsade rapporter</span>
                            </span>
                        </label>
                        <label class="plan-card {{ $selectedPlan === 'team' ? 'is-selected' : '' }}">
                            <input class="plan-option-input" type="radio" name="plan" value="team" required @checked($selectedPlan === 'team') aria-invalid="{{ $errors->has('plan') ? 'true' : 'false' }}" @if ($errors->has('plan')) aria-describedby="plan-error" @endif>
                            <span class="plan-card-content">
                                <span class="plan-card-heading"><strong>Team</strong><span>Upp till {{ $teamSeats }} användare · Rekommenderad</span></span>
                                <span class="plan-price">{{ $teamMonthly }}/mån <small>exkl. moms</small></span>
                                <span class="plan-feature-list">eller {{ $teamYearly }}/år · Allt i Start · egen logotyp i rapporten</span>
                            </span>
                        </label>
                    </div>
                </fieldset>

                <fieldset class="billing-plan-fieldset billing-interval-fieldset" aria-describedby="billing-interval-help">
                    <legend>Fakturering</legend>
                    <p id="billing-interval-help" class="billing-field-help">Fakturan skickas i förskott för varje period. Ingen bindningstid.</p>
                    @error('interval') <span class="field-error" id="interval-error">{{ $message }}</span> @enderror

                    <div class="plan-grid">
                        <label class="plan-card {{ $selectedInterval === 'month' ? 'is-selected' : '' }}">
                            <input class="plan-option-input" type="radio" name="interval" value="month" required @checked($selectedInterval === 'month') aria-invalid="{{ $errors->has('interval') ? 'true' : 'false' }}" @if ($errors->has('interval')) aria-describedby="interval-error" @endif>
                            <span class="plan-card-content">
                                <span class="plan-card-heading"><strong>Månadsvis</strong><span>En faktura per månad</span></span>
                                <span class="plan-feature-list">Start {{ $startMonthly }} · Team {{ $teamMonthly }} per månad</span>
                            </span>
                        </label>
                        <label class="plan-card {{ $selectedInterval === 'year' ? 'is-selected' : '' }}">
                            <input class="plan-option-input" type="radio" name="interval" value="year" required @checked($selectedInterval === 'year') aria-invalid="{{ $errors->has('interval') ? 'true' : 'false' }}" @if ($errors->has('interval')) aria-describedby="interval-error" @endif>
                            <span class="plan-card-content">
                                <span class="plan-card-heading"><strong>Årsvis</strong><span>{{ $freeMonths }} månader gratis</span></span>
                                <span class="plan-feature-list">Start {{ $startYearly }} · Team {{ $teamYearly }} per år</span>
                            </span>
                        </label>
                    </div>
                </fieldset>

                <fieldset class="billing-details-fieldset">
                    <legend>Fakturauppgifter</legend>
                    <div class="billing-details-grid">
                        <div class="billing-field">
                            <label for="billing_company_name">Företagsnamn</label>
                            <input id="billing_company_name" name="billing_company_name" type="text" autocomplete="organization" required value="{{ old('billing_company_name', $billingDetails['company_name'] ?? '') }}" aria-invalid="{{ $errors->has('billing_company_name') ? 'true' : 'false' }}" @if ($errors->has('billing_company_name')) aria-describedby="billing_company_name-error" @endif>
                            @error('billing_company_name') <span class="field-error" id="billing_company_name-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="billing-field">
                            <label for="organisation_number">Organisationsnummer</label>
                            <input id="organisation_number" name="organisation_number" type="text" inputmode="text" autocomplete="off" required value="{{ old('organisation_number', $billingDetails['organisation_number'] ?? '') }}" aria-invalid="{{ $errors->has('organisation_number') ? 'true' : 'false' }}" @if ($errors->has('organisation_number')) aria-describedby="organisation_number-error" @endif>
                            @error('organisation_number') <span class="field-error" id="organisation_number-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="billing-field">
                            <label for="invoice_email">E-post för faktura</label>
                            <input id="invoice_email" name="invoice_email" type="email" inputmode="email" autocomplete="email" required value="{{ old('invoice_email', $billingDetails['invoice_email'] ?? '') }}" aria-invalid="{{ $errors->has('invoice_email') ? 'true' : 'false' }}" @if ($errors->has('invoice_email')) aria-describedby="invoice_email-error" @endif>
                            @error('invoice_email') <span class="field-error" id="invoice_email-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="billing-field billing-field-address">
                            <label for="billing_address">Fullständig fakturaadress</label>
                            <textarea id="billing_address" name="billing_address" rows="3" autocomplete="street-address" required aria-invalid="{{ $errors->has('billing_address') ? 'true' : 'false' }}" @if ($errors->has('billing_address')) aria-describedby="billing_address-error" @endif>{{ old('billing_address', $billingDetails['billing_address'] ?? '') }}</textarea>
                            @error('billing_address') <span class="field-error" id="billing_address-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </fieldset>

                <div class="billing-request-footer">
                    <p id="billing-request-note">
                        Förfrågan är inte en betalning. Fakturan skickas via Fortnox. Abonnemanget löper tills vidare utan bindningstid och kan sägas upp före nästa period.
                        @if ($isManualPlanActive)
                            Din nuvarande betalda period fortsätter till {{ $company->paid_until?->locale('sv')->translatedFormat('j F Y') }}. Ändringen aktiveras av oss när betalningen har registrerats.
                        @else
                            Planen aktiveras av oss när betalningen har registrerats.
                        @endif
                    </p>
                    <button class="button button-lime" type="submit" aria-describedby="billing-request-note" data-loading-text="Skickar förfrågan…">{{ $isManualPlanActive ? 'Byt plan eller period' : 'Begär faktura' }}</button>
                </div>
            </form>
        @elseif (! $isOwner)
            <p class="subscription-summary-note">Endast kontoägaren kan begära en faktura.</p>
        @elseif (! $hasPendingInvoiceRequest)
            <p class="subscription-summary-note">Ett annat abonnemang är aktivt för kontot, så en Fortnox-faktura kan inte begäras just nu.</p>
        @endif
    @endif
</div>
