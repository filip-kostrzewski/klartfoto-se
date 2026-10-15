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

        @forelse ($assignment->photoPairs() as $pair)
            @include('reports.partials.photo-pair', ['pair' => $pair, 'assignment' => $assignment])
        @empty
            <p class="empty-note">Inga bilder ännu.</p>
        @endforelse

        @if ($canManageJobs)
            <form class="app-form" method="post" action="{{ route('assignments.photos.store', $assignment, false) }}" enctype="multipart/form-data">
                @csrf
                @include('assignments.partials.photo-input', [
                    'inputId' => 'before_photos',
                    'inputName' => 'before_photos[]',
                    'label' => 'Fler förebilder',
                ])
                @include('assignments.partials.photo-input', [
                    'inputId' => 'after_photos',
                    'inputName' => 'after_photos[]',
                    'label' => 'Fler efterbilder',
                ])
                <button class="button button-lime" type="submit">Ladda upp bilder</button>
            </form>
        @endif

        <p class="auth-switch"><a href="{{ route('assignments.index', absolute: false) }}">Alla uppdrag</a></p>
    </section>
@endsection
