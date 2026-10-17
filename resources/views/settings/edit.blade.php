@extends('layouts.app')

@section('title', 'Inställningar · Klartfoto')
@section('page-heading', 'Inställningar')

@section('content')
    <section class="settings-page">
        <div class="app-page-heading">
            <div>
                <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> {{ $company->name }}</p>
                <h1>Inställningar</h1>
                <p class="auth-lead">Hantera din profil och företagets konto.</p>
            </div>
        </div>

        @if (session('status'))
            <div class="form-status form-status-success" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="form-status form-status-error" role="alert" tabindex="-1" data-focus-status>
                <strong>Kontrollera uppgifterna</strong>
                <ul>
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @unless ($isOwner)
            <div class="settings-content">
                <section class="settings-block settings-card" id="profil" aria-labelledby="profile-heading">
                    <header class="settings-card-heading">
                        <span class="workflow-icon"><x-icon name="home" /></span>
                        <div><h2 id="profile-heading">Din profil</h2><p>Uppgifterna för ditt konto.</p></div>
                    </header>
                    @include('settings.partials.profile-name')
                    <dl class="settings-member-meta"><div><dt>E-postadress</dt><dd>{{ $profile->email }}</dd></div><div><dt>Roll</dt><dd>Medlem</dd></div></dl>
                </section>
                <section class="settings-block settings-card" id="abonnemang" aria-labelledby="member-subscription-heading">
                    <header class="settings-card-heading">
                        <span class="workflow-icon"><x-icon name="report" /></span>
                        <div><h2 id="member-subscription-heading">Abonnemang</h2><p>Se företagets aktuella åtkomst.</p></div>
                    </header>
                    @include('settings.partials.subscription', ['compact' => true])
                </section>
            </div>
        @else
            <div class="settings-layout">
                <nav class="settings-internal-nav" aria-label="Avsnitt i inställningar">
                    <a href="#profil">Profil</a>
                    <a href="#foretag">Företag</a>
                    <a href="#abonnemang">Abonnemang</a>
                    <a href="#logotyp">Logotyp</a>
                    <a href="#medlemmar">Team</a>
                </nav>

                <div class="settings-content">
                    <section class="settings-block settings-card" id="profil" aria-labelledby="profile-heading">
                        <header class="settings-card-heading">
                            <span class="workflow-icon"><x-icon name="home" /></span>
                            <div><h2 id="profile-heading">Din profil</h2><p>Uppgifterna för ditt konto.</p></div>
                        </header>
                        @include('settings.partials.profile-name')
                    </section>

                    <section class="settings-block settings-card" id="foretag" aria-labelledby="company-heading">
                        <header class="settings-card-heading">
                            <span class="workflow-icon"><x-icon name="home" /></span>
                            <div><h2 id="company-heading">Företag</h2><p>Uppgifter som kan visas i kundrapporten.</p></div>
                        </header>
                        <p class="settings-card-description">Namn, telefon och organisationsnummer visas i rapportens sidfot. Lämna telefon eller organisationsnummer tomt om det inte ska synas.</p>
                        <form class="app-form settings-inline-form" method="post" action="{{ route('settings.company.update', absolute: false) }}" data-submit-feedback>
                            @csrf
                            @method('PUT')
                            <label for="company_name">Företagsnamn</label>
                            <input id="company_name" name="company_name" type="text" required maxlength="120" autocomplete="organization" value="{{ old('company_name', $company->name) }}" aria-invalid="{{ $errors->has('company_name') ? 'true' : 'false' }}" @if ($errors->has('company_name')) aria-describedby="company-name-error" @endif>
                            @error('company_name') <span class="field-error" id="company-name-error">{{ $message }}</span> @enderror
                            <label for="phone">Telefon</label>
                            <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="40" value="{{ old('phone', $company->phone) }}" aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}" @if ($errors->has('phone')) aria-describedby="company-phone-error" @endif>
                            @error('phone') <span class="field-error" id="company-phone-error">{{ $message }}</span> @enderror
                            <label for="organization_number">Organisationsnummer</label>
                            <input id="organization_number" name="organization_number" type="text" maxlength="32" value="{{ old('organization_number', $company->organization_number) }}" spellcheck="false" aria-invalid="{{ $errors->has('organization_number') ? 'true' : 'false' }}" @if ($errors->has('organization_number')) aria-describedby="company-organization-number-error" @endif>
                            @error('organization_number') <span class="field-error" id="company-organization-number-error">{{ $message }}</span> @enderror
                            <button class="button button-lime" type="submit" data-loading-text="Sparar uppgifter…">Spara företagsuppgifter</button>
                        </form>
                    </section>

                    <section class="settings-block settings-card" id="abonnemang" aria-labelledby="subscription-heading">
                        <header class="settings-card-heading">
                            <span class="workflow-icon"><x-icon name="report" /></span>
                            <div><h2 id="subscription-heading">Abonnemang</h2><p>Se aktuell status och fakturaförfrågan.</p></div>
                        </header>
                        @include('settings.partials.subscription', ['compact' => true])
                    </section>

                    <section class="settings-block settings-card" id="logotyp" aria-labelledby="brand-heading">
                        <header class="settings-card-heading">
                            <span class="workflow-icon"><x-icon name="camera" /></span>
                            <div><h2 id="brand-heading">Logotyp</h2><p>Visa företagets logotyp i kundrapporten.</p></div>
                        </header>
                        <p class="settings-card-description">PNG, JPEG eller SVG. Bilden optimeras när den laddas upp. @if ($company->onActiveFeedbackPilot()) Feedbackpilot – Team gratis till 31 december 2026. @elseif ($company->onTrial()) Under provperioden har ni allt i Team, inklusive egen logotyp. @endif</p>
                        @if ($company->allowsTeamFeatures() && $company->logo_path)
                            <img class="settings-logo" src="{{ route('settings.logo', absolute: false) }}" alt="{{ $company->name }}">
                        @endif
                        @if ($company->allowsTeamFeatures())
                            <form class="app-form settings-inline-form" method="post" action="{{ route('settings.logo.store', absolute: false) }}" enctype="multipart/form-data">
                                @csrf
                                <label for="logo">Välj logotypfil</label>
                                <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,.svg,image/svg+xml" required>
                                <button class="button button-lime" type="submit">Ladda upp logotyp</button>
                            </form>
                            @if ($company->logo_path)
                                <form method="post" action="{{ route('settings.logo.destroy', absolute: false) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button button-outline-dark" type="submit">Ta bort logotyp</button>
                                </form>
                            @endif
                        @else
                            <p class="form-status form-status-error" role="status">Egen logotyp ingår i Team.</p>
                        @endif
                    </section>

                    <section class="settings-block settings-card" id="medlemmar" aria-labelledby="team-heading">
                        <header class="settings-card-heading">
                            <span class="workflow-icon"><x-icon name="share" /></span>
                            <div><h2 id="team-heading">Team</h2><p>Hantera vilka som kan arbeta i företagskontot.</p></div>
                        </header>
                        <p class="auth-lead settings-card-description">{{ $userCount }} av {{ $seatLimit }} användare{{ $pendingInvites === 1 ? ', 1 inbjudan väntar' : ($pendingInvites > 1 ? ', '.$pendingInvites.' inbjudningar väntar' : '') }}. @if ($company->onActiveFeedbackPilot()) Feedbackpilot – Team gratis till 31 december 2026. @elseif ($company->onTrial()) Under provperioden har ni allt i Team: upp till 10 användare och egen logotyp. @endif {{ $company->afterTrialAccessSummary() }}</p>
                        <ul class="member-list">
                            @foreach ($company->users as $member)
                                <li>
                                    <strong>{{ $member->name }}</strong>
                                    <span>{{ $member->email }}</span>
                                    <span>{{ $member->isOwner() ? 'Ägare' : 'Medlem' }}</span>
                                </li>
                            @endforeach
                        </ul>

                        @if ($company->invitations->isNotEmpty())
                            <h3>Väntande inbjudningar</h3>
                            <ul class="member-list">
                                @foreach ($company->invitations as $invitation)
                                    <li>
                                        <strong>{{ $invitation->email }}</strong>
                                        <span>Gäller till {{ $invitation->expires_at->locale('sv')->translatedFormat('j F Y') }}</span>
                                        @if ($isOwner)
                                            <form method="post" action="{{ route('invitations.destroy', $invitation, false) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit">Ta bort</button>
                                            </form>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($company->allowsTeamFeatures())
                            <form class="app-form settings-inline-form" method="post" action="{{ route('invitations.store', absolute: false) }}">
                                @csrf
                                <label for="email">Bjud in med e-post</label>
                                <input id="email" name="email" type="email" inputmode="email" autocomplete="email" required maxlength="254" value="{{ old('email') }}" placeholder="namn@foretag.se">
                                <button class="button button-lime" type="submit">Skicka inbjudan</button>
                            </form>
                        @else
                            <p class="form-status form-status-error" role="status">Fler användare ingår i Team.</p>
                        @endif
                    </section>
                </div>
            </div>
        @endunless
    </section>
@endsection
