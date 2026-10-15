@extends('layouts.app')

@section('title', 'Uppdrag · Klartfoto')

@section('content')
    <section class="app-card">
        <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> {{ $companyName }}</p>
        <h1>Uppdrag</h1>
        @if ($onTrial && $trialEndsAt)
            <p class="auth-lead">Provperioden gäller till {{ $trialEndsAt->locale('sv')->translatedFormat('j F Y') }}. Inget kort behövs.</p>
        @elseif (! $canManageJobs)
            <p class="form-status form-status-error" role="status">Provperioden är slut. Du kan fortfarande öppna rapporter som redan är skapade.</p>
        @endif

        @if ($canManageJobs)
            <a class="button button-lime" href="{{ route('assignments.create', absolute: false) }}">Nytt uppdrag</a>
        @endif

        @if ($assignments->isEmpty())
            @if ($showOnboarding)
                <section class="onboarding" aria-label="Kom igång">
                    <h2>Kom igång</h2>
                    <ol class="onboarding-list">
                        <li>
                            <a href="{{ route('assignments.create', absolute: false) }}">Skapa första uppdraget</a>
                        </li>
                        <li>
                            <a href="{{ route('settings.edit', absolute: false) }}#logotyp">Ladda upp logotyp</a>
                            @if ($hasLogo)
                                <span>Klar</span>
                            @endif
                        </li>
                        <li>
                            <a href="{{ route('settings.edit', absolute: false) }}#medlemmar">Bjud in teamet</a>
                            @if ($hasTeam)
                                <span>Klar</span>
                            @endif
                        </li>
                    </ol>
                </section>
            @endif
            <p class="empty-note">Inga uppdrag ännu. Lägg till kunden, adressen och bilder från telefonen.</p>
        @else
            <ul class="job-list">
                @foreach ($assignments as $assignment)
                    <li>
                        <a href="{{ route('assignments.show', $assignment, false) }}">
                            <strong>{{ $assignment->customer_name }}</strong>
                            <span>{{ $assignment->address }}</span>
                            <span>{{ $assignment->performed_on->locale('sv')->translatedFormat('j F Y') }} · {{ $assignment->photos_count }} bilder</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
