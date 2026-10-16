@extends('layouts.app')

@section('title', $assignment->customer_name.' · Klartfoto')
@section('page-heading', 'Uppdrag')

@section('content')
    <section class="assignment-detail-page">
        <div class="app-page-heading">
            <div>
                <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> Uppdrag</p>
                <h1>{{ $assignment->customer_name }}</h1>
                <p class="auth-lead">{{ $assignment->address }} <span aria-hidden="true">·</span> {{ $assignment->performedLabel() }}</p>
            </div>
            <a class="button button-outline-dark" href="{{ route('assignments.index', absolute: false) }}">Alla uppdrag</a>
        </div>

        @if (session('status'))
            <div class="form-status form-status-success" role="status">{{ session('status') }}</div>
        @endif

        <section class="share-box" aria-labelledby="share-heading">
            <div class="share-box-top">
                <div class="share-box-copy">
                    <span class="workflow-icon"><x-icon name="share" /></span>
                    <div><h2 id="share-heading">Dela rapporten</h2><p>Skicka länken till kunden när rapporten är klar.</p></div>
                </div>
                <a class="button button-outline-dark" href="{{ route('reports.show', ['token' => $assignment->public_token], false) }}" target="_blank" rel="noopener">Öppna rapport</a>
            </div>
            <form method="post" action="{{ route('assignments.share', $assignment, false) }}">
                @csrf
                <label for="share-link">Rapportlänk</label>
                <div class="share-link-row">
                    <input id="share-link" type="text" readonly value="{{ $reportUrl }}">
                    <button class="button button-dark" type="submit" data-share-url="{{ $reportUrl }}">Dela rapporten</button>
                </div>
            </form>
        </section>

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

        <section class="assignment-photos-section" aria-labelledby="assignment-photos-heading">
            <div class="assignment-section-heading">
                <div><p class="section-kicker">Foton</p><h2 id="assignment-photos-heading">Före och efter</h2></div>
                <span class="photo-pair-count">{{ $assignment->photos->count() }} {{ $assignment->photos->count() === 1 ? 'bild' : 'bilder' }}</span>
            </div>
            @forelse ($assignment->photoPairs() as $pair)
                @include('reports.partials.photo-pair', ['pair' => $pair, 'assignment' => $assignment, 'canDeletePhotos' => true, 'canEditPair' => $canManageJobs, 'pairIndex' => $loop->index])
            @empty
                <div class="app-panel photo-empty-state">
                    <span class="empty-state-icon"><x-icon name="camera" /></span>
                    <div><h3>Inga bilder ännu</h3><p>Lägg till bilder före och efter arbetet i formuläret nedan.</p></div>
                </div>
            @endforelse
        </section>

        @if ($canManageJobs)
            <section class="assignment-upload-panel" aria-labelledby="assignment-upload-heading">
                <div class="assignment-section-heading">
                    <div><p class="section-kicker">Lägg till bilder</p><h2 id="assignment-upload-heading">Komplettera rapporten</h2></div>
                </div>
                <form class="app-form assignment-form assignment-upload-form" method="post" action="{{ route('assignments.photos.store', $assignment, false) }}" enctype="multipart/form-data" data-submit-feedback>
                    @csrf
                    <div class="assignment-photo-section">
                        @include('assignments.partials.photo-input', [
                            'inputId' => 'before_photos',
                            'inputName' => 'before_photos[]',
                            'label' => 'Välj fler förebilder',
                        ])
                    </div>
                    <div class="assignment-photo-section">
                        @include('assignments.partials.photo-input', [
                            'inputId' => 'after_photos',
                            'inputName' => 'after_photos[]',
                            'label' => 'Välj fler efterbilder',
                        ])
                    </div>
                    <div class="assignment-form-footer">
                        <p>De nya bilderna läggs till rapporten.</p>
                        <button class="button button-lime" type="submit" data-loading-text="Laddar upp…">Ladda upp bilder</button>
                    </div>
                </form>
            </section>
        @endif
    </section>
@endsection
