@extends('layouts.app')

@section('title', 'Uppdrag · Klartfoto')
@section('page-heading', 'Uppdrag')

@section('content')
    <section class="dashboard-page">
        <div class="app-page-heading">
            <div>
                <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> {{ $companyName }}</p>
                <h1>Uppdrag</h1>
                <p class="auth-lead">Samla bilder och dela rapporten efter varje besök.</p>
            </div>
            @if ($canManageJobs)
                <a class="button button-lime" href="{{ route('assignments.create', absolute: false) }}"><x-icon name="camera" /> Nytt uppdrag</a>
            @endif
        </div>

        @if ($onTrial && $trialEndsAt)
            <div class="trial-strip"><span class="trial-strip-icon" aria-hidden="true">✓</span><p><strong>Provperiod</strong><span>Gratis till {{ $trialEndsAt->locale('sv')->translatedFormat('j F Y') }}. Inget kort behövs.</span></p></div>
        @elseif (! $canManageJobs)
            <div class="form-status form-status-error" role="status">Åtkomsten är pausad. Du kan fortfarande öppna rapporter som redan är skapade.</div>
        @endif

        @if ($assignments->isNotEmpty())
            <div class="dashboard-stats" aria-label="Översikt">
                <div class="dashboard-stat"><span>Uppdrag</span><strong>{{ $assignments->count() }}</strong><x-icon name="report" /></div>
                <div class="dashboard-stat"><span>Bilder</span><strong>{{ $assignments->sum('photos_count') }}</strong><x-icon name="camera" /></div>
            </div>
        @endif

        @if ($showOnboarding)
            @include('assignments.partials.onboarding')
        @elseif ($showOnboardingRestore)
            <form class="onboarding-restore" method="post" action="{{ route('onboarding.restore', absolute: false) }}">
                @csrf
                <button type="submit">Visa Kom igång</button>
            </form>
        @endif

        <section class="assignment-list-section" aria-labelledby="assignment-list-heading">
            <div class="assignment-list-heading">
                <div><h2 id="assignment-list-heading">Senaste uppdrag</h2><p>{{ $assignments->isEmpty() ? 'Dina sparade kundbesök visas här.' : 'Välj ett uppdrag för att öppna och dela rapporten.' }}</p></div>
                @if ($canManageJobs && $assignments->isNotEmpty())
                    <a class="text-link" href="{{ route('assignments.create', absolute: false) }}">Lägg till</a>
                @endif
            </div>

            @if ($assignments->isEmpty())
                <div class="dashboard-empty-state">
                    <div class="empty-state-image">
                        <img src="{{ asset('images/cleaning-detail-small.webp') }}?v={{ config('klartfoto.demo_asset_version') }}" width="768" height="512" alt="" loading="lazy" decoding="async">
                        <span>AI-demo · inte kundarbete</span>
                    </div>
                    <div class="empty-state-copy">
                        <span class="empty-state-icon"><x-icon name="camera" /></span>
                        <h3>Här börjar nästa rapport</h3>
                        <p>Lägg till kundens namn, adress och datum. Ladda sedan upp bilder före och efter arbetet.</p>
                        @if ($canManageJobs)
                            <a class="button button-dark" href="{{ route('assignments.create', absolute: false) }}">Skapa ditt första uppdrag</a>
                        @endif
                    </div>
                </div>
            @else
                <ul class="job-list">
                    @foreach ($assignments as $assignment)
                        <li>
                            <a href="{{ route('assignments.show', $assignment, false) }}">
                                <span class="job-list-icon" aria-hidden="true"><x-icon name="report" /></span>
                                <span class="job-list-copy">
                                    <strong>{{ $assignment->customer_name }}</strong>
                                    <span>{{ $assignment->address }}</span>
                                </span>
                                <span class="job-list-meta">
                                    <span>{{ $assignment->performed_on->locale('sv')->translatedFormat('j F Y') }}</span>
                                    <span>{{ $assignment->photos_count }} {{ $assignment->photos_count === 1 ? 'bild' : 'bilder' }}</span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </section>
@endsection
