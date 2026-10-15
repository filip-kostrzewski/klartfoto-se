@extends('layouts.app')

@section('title', 'Uppdrag · Klartfoto')

@section('content')
    <section class="app-card">
        <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> {{ auth()->user()->name }}</p>
        <h1>Uppdrag</h1>
        @if ($onTrial && auth()->user()->trial_ends_at)
            <p class="auth-lead">Provperioden gäller till {{ auth()->user()->trial_ends_at->locale('sv')->translatedFormat('j F Y') }}. Inget kort behövs.</p>
        @else
            <p class="form-status form-status-error" role="status">Provperioden är slut. Du kan fortfarande öppna rapporter som redan är skapade.</p>
        @endif

        @if ($onTrial)
            <a class="button button-lime" href="{{ route('assignments.create', absolute: false) }}">Nytt uppdrag</a>
        @endif

        @if ($assignments->isEmpty())
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
