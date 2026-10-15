@extends('layouts.app')

@section('title', $assignment->customer_name.' · Klartfoto')

@section('content')
    <section class="app-card">
        <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> Uppdrag</p>
        <h1>{{ $assignment->customer_name }}</h1>
        <p class="auth-lead">{{ $assignment->address }}<br>{{ $assignment->performed_on->locale('sv')->translatedFormat('j F Y') }}</p>

        <div class="share-box">
            <label for="share-link">Länk till kunden</label>
            <input id="share-link" type="text" readonly value="{{ $reportUrl }}">
            <button class="button button-dark" type="button" data-share-url="{{ $reportUrl }}">Dela rapporten</button>
        </div>

        @if ($errors->any())
            <div class="form-status form-status-error" role="alert">
                <ul>
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($assignment->photos->isEmpty())
            <p class="empty-note">Inga bilder ännu.</p>
        @else
            <div class="owner-photos">
                @foreach ($assignment->photos as $photo)
                    <figure>
                        <img src="{{ route('reports.photos.show', ['token' => $assignment->public_token, 'photo' => $photo], false) }}" alt="{{ $photo->phase === \App\PhotoPhase::Before ? 'Före' : 'Efter' }}">
                        <figcaption>{{ $photo->phase === \App\PhotoPhase::Before ? 'Före' : 'Efter' }}</figcaption>
                    </figure>
                @endforeach
            </div>
        @endif

        @if ($canManageJobs)
            <form class="app-form" method="post" action="{{ route('assignments.photos.store', $assignment, false) }}" enctype="multipart/form-data">
                @csrf
                <label for="before_photos">Fler förebilder</label>
                <input id="before_photos" name="before_photos[]" type="file" accept="image/*" capture multiple>
                <label for="after_photos">Fler efterbilder</label>
                <input id="after_photos" name="after_photos[]" type="file" accept="image/*" capture multiple>
                <button class="button button-lime" type="submit">Ladda upp bilder</button>
            </form>
        @endif

        <p class="auth-switch"><a href="{{ route('assignments.index', absolute: false) }}">Alla uppdrag</a></p>
    </section>
@endsection
