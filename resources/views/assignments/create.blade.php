@extends('layouts.app')

@section('title', 'Nytt uppdrag · Klartfoto')
@section('page-heading', 'Nytt uppdrag')

@section('content')
    <section class="assignment-form-page">
        <div class="app-page-heading">
            <div>
                <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> Nytt uppdrag</p>
                <h1>Dokumentera arbetet</h1>
                <p class="auth-lead">Lägg till kunduppgifter och bilder före och efter arbetet.</p>
            </div>
            <a class="button button-outline-dark" href="{{ route('assignments.index', absolute: false) }}">Tillbaka till uppdragen</a>
        </div>

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

        @if ($canManageJobs)
            <form class="app-form assignment-form" method="post" action="{{ route('assignments.store', absolute: false) }}" enctype="multipart/form-data" data-submit-feedback>
                @csrf
                <fieldset class="assignment-details">
                    <legend>Kund och besök</legend>
                    <label for="customer_name">Kundens namn</label>
                    <input id="customer_name" name="customer_name" type="text" required maxlength="120" autocomplete="name" value="{{ old('customer_name') }}" aria-invalid="{{ $errors->has('customer_name') ? 'true' : 'false' }}" @if ($errors->has('customer_name')) aria-describedby="customer-name-error" @endif>
                    @error('customer_name') <span class="field-error" id="customer-name-error">{{ $message }}</span> @enderror

                    <label for="address">Adress</label>
                    <input id="address" name="address" type="text" required maxlength="255" autocomplete="street-address" value="{{ old('address') }}" aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}" @if ($errors->has('address')) aria-describedby="address-error" @endif>
                    @error('address') <span class="field-error" id="address-error">{{ $message }}</span> @enderror

                    <label for="performed_on">Datum</label>
                    <input id="performed_on" name="performed_on" type="date" required value="{{ old('performed_on', now()->toDateString()) }}" aria-invalid="{{ $errors->has('performed_on') ? 'true' : 'false' }}" @if ($errors->has('performed_on')) aria-describedby="performed-on-error" @endif>
                    @error('performed_on') <span class="field-error" id="performed-on-error">{{ $message }}</span> @enderror

                    <div class="assignment-time-range">
                        <div>
                            <label for="performed_time">Starttid</label>
                            <input id="performed_time" name="performed_time" type="time" value="{{ old('performed_time') }}" aria-invalid="{{ $errors->has('performed_time') ? 'true' : 'false' }}" @if ($errors->has('performed_time')) aria-describedby="performed-time-error" @endif>
                            @error('performed_time') <span class="field-error" id="performed-time-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="performed_end_time">Sluttid</label>
                            <input id="performed_end_time" name="performed_end_time" type="time" value="{{ old('performed_end_time') }}" aria-invalid="{{ $errors->has('performed_end_time') ? 'true' : 'false' }}" @if ($errors->has('performed_end_time')) aria-describedby="performed-end-time-error" @endif>
                            @error('performed_end_time') <span class="field-error" id="performed-end-time-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <p class="assignment-time-hint">Valfritt. Ett intervall visas i rapporten som kl. 09:00–11:30.</p>
                </fieldset>

                <div class="assignment-photo-section">
                    <div class="assignment-photo-heading">
                        <span class="workflow-icon"><x-icon name="camera" /></span>
                        <div><h2>Före arbetet</h2><p>Välj en eller flera bilder från mobilen.</p></div>
                    </div>
                    @include('assignments.partials.photo-input', [
                        'inputId' => 'before_photos',
                        'inputName' => 'before_photos[]',
                        'label' => 'Välj förebilder',
                    ])
                    @error('before_photos') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="assignment-photo-section">
                    <div class="assignment-photo-heading">
                        <span class="workflow-icon"><x-icon name="check" /></span>
                        <div><h2>Efter arbetet</h2><p>Ta gärna bilder från samma vinkel.</p></div>
                    </div>
                    @include('assignments.partials.photo-input', [
                        'inputId' => 'after_photos',
                        'inputName' => 'after_photos[]',
                        'label' => 'Välj efterbilder',
                    ])
                    @error('after_photos') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="assignment-form-footer">
                    <p>Bilderna optimeras innan de sparas.</p>
                    <button class="button button-lime" type="submit" data-loading-text="Sparar uppdrag…">Spara uppdraget</button>
                </div>
            </form>
        @else
            <div class="app-panel"><p>Åtkomsten är pausad, så nya uppdrag kan inte skapas just nu.</p></div>
        @endif
    </section>
@endsection
