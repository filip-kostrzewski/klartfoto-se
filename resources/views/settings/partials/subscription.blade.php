@php
    $compact = $compact ?? false;
    $currentPlan = $company->plan === \App\CompanyPlan::Team ? 'Team' : 'Start';
    $requestedPlan = $company->requested_plan instanceof \App\CompanyPlan
        ? $company->requested_plan->value
        : $company->requested_plan;
    $requestedPlanLabel = $requestedPlan === 'team' ? 'Team' : 'Start';
    $billingDetails = is_array($company->billing_details) ? $company->billing_details : [];
    $selectedPlan = old('plan', $company->plan->value ?? 'start');
@endphp

<div class="subscription-panel">
    <div class="billing-overview">
        <div class="billing-status {{ $isManualPlanActive ? 'billing-status-active' : ($isTrialActive ? 'billing-status-trial' : 'billing-status-expired') }}" role="status">
            <span class="billing-status-icon" aria-hidden="true">{{ $isTrialActive || $isManualPlanActive ? '✓' : '!' }}</span>
            <span class="billing-status-copy">
                @if ($isManualPlanActive)
                    <strong>Abonnemanget är aktivt</strong>
                    <span>Planen gäller till {{ $company->paid_until?->locale('sv')->translatedFormat('j F Y') }}.</span>
                @elseif ($isTrialActive)
                    <strong>Provperiod pågår</strong>
                    <span>Gratis till {{ $company->trial_ends_at?->locale('sv')->translatedFormat('j F Y') }}. Inget kort behövs. Därefter har ni ingen aktiv åtkomst till nya uppdrag. Du kan fortfarande öppna befintliga rapporter.</span>
                @else
                    <strong>Ingen aktiv betald plan</strong>
                    <span>Befintliga rapporter finns kvar i skrivskyddat läge.</span>
                @endif
            </span>
        </div>

        <div class="billing-current-plan">
            <span>Nuvarande plan</span>
            <strong>{{ $currentPlan }}</strong>
        </div>
    </div>

    @if ($hasPendingInvoiceRequest)
        <div class="billing-pending" role="status">
            <div class="billing-pending-heading">
                <span class="billing-pending-mark" aria-hidden="true">↗</span>
                <div>
                    <strong>Fakturaförfrågan väntar</strong>
                    <span>{{ $requestedPlanLabel }} · skickad {{ $company->requested_at?->locale('sv')->translatedFormat('j F Y') }}</span>
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
                    Du kan förnya eller ändra plan redan nu.
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
        <a class="button button-outline-dark subscription-manage-link" href="{{ route('settings.billing', absolute: false) }}">Visa abonnemang</a>
    @else
        @if ($canRequestInvoice)
            <form class="billing-request-form app-form" method="post" action="{{ route('settings.billing.request', absolute: false) }}" data-submit-feedback>
                @csrf

                <fieldset class="billing-plan-fieldset" aria-describedby="billing-plan-help">
                    <legend>Välj plan</legend>
                    <p id="billing-plan-help" class="billing-field-help">
                        @if ($isManualPlanActive)
                            Välj om du vill förnya din nuvarande plan eller ändra till en annan.
                        @else
                            Planen aktiveras när betalningen har registrerats.
                        @endif
                    </p>
                    @error('plan') <span class="field-error" id="plan-error">{{ $message }}</span> @enderror

                    <div class="plan-grid">
                        <label class="plan-card {{ $selectedPlan === 'start' ? 'is-selected' : '' }}">
                            <input class="plan-option-input" type="radio" name="plan" value="start" required @checked($selectedPlan === 'start') aria-invalid="{{ $errors->has('plan') ? 'true' : 'false' }}" @if ($errors->has('plan')) aria-describedby="plan-error" @endif>
                            <span class="plan-card-content">
                                <span class="plan-card-heading"><strong>Start</strong><span>En användare</span></span>
                                <span class="plan-price">290 kr/mån <small>exkl. moms</small></span>
                                <span class="plan-feature-list">Obegränsade rapporter</span>
                            </span>
                        </label>
                        <label class="plan-card {{ $selectedPlan === 'team' ? 'is-selected' : '' }}">
                            <input class="plan-option-input" type="radio" name="plan" value="team" required @checked($selectedPlan === 'team') aria-invalid="{{ $errors->has('plan') ? 'true' : 'false' }}" @if ($errors->has('plan')) aria-describedby="plan-error" @endif>
                            <span class="plan-card-content">
                                <span class="plan-card-heading"><strong>Team</strong><span>Upp till 10 användare</span></span>
                                <span class="plan-price">590 kr/mån <small>exkl. moms</small></span>
                                <span class="plan-feature-list">Allt i Start · egen logotyp i rapporten</span>
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
                        Förfrågan är inte en betalning. Fakturan skickas via Fortnox.
                        @if ($isManualPlanActive)
                            Din nuvarande betalda period fortsätter till {{ $company->paid_until?->locale('sv')->translatedFormat('j F Y') }}. Förnyelse eller planändring aktiveras av oss när betalningen har registrerats.
                        @else
                            Planen aktiveras av oss när betalningen har registrerats.
                        @endif
                    </p>
                    <button class="button button-lime" type="submit" aria-describedby="billing-request-note" data-loading-text="Skickar förfrågan…">{{ $isManualPlanActive ? 'Förnya eller ändra plan' : 'Begär faktura' }}</button>
                </div>
            </form>
        @elseif (! $isOwner)
            <p class="subscription-summary-note">Endast kontoägaren kan begära en faktura.</p>
        @elseif (! $hasPendingInvoiceRequest)
            <p class="subscription-summary-note">Ett annat abonnemang är aktivt för kontot, så en Fortnox-faktura kan inte begäras just nu.</p>
        @endif
    @endif
</div>
