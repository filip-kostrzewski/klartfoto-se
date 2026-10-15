@extends('layouts.app')

@section('title', 'Nytt uppdrag · Klartfoto')

@section('content')
    <section class="app-card">
        <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> Nytt uppdrag</p>
        <h1>Dokumentera städningen</h1>
        <p class="auth-lead">Fyll i kunden och fota före och efter. Bilderna förminskas på servern.</p>

        @if ($errors->any())
            <div class="form-status form-status-error" role="alert">
                <ul>
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($canManageJobs)
            <form class="app-form" method="post" action="{{ route('assignments.store', absolute: false) }}" enctype="multipart/form-data">
                @csrf
                <label for="customer_name">Kundens namn</label>
                <input id="customer_name" name="customer_name" type="text" required maxlength="120" autocomplete="name" value="{{ old('customer_name') }}" aria-invalid="{{ $errors->has('customer_name') ? 'true' : 'false' }}">

                <label for="address">Adress</label>
                <input id="address" name="address" type="text" required maxlength="255" autocomplete="street-address" value="{{ old('address') }}" aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}">

                <label for="performed_on">Datum</label>
                <input id="performed_on" name="performed_on" type="date" required value="{{ old('performed_on', now()->toDateString()) }}" aria-invalid="{{ $errors->has('performed_on') ? 'true' : 'false' }}">

                <label for="before_photos">Förebilder</label>
                <input id="before_photos" name="before_photos[]" type="file" accept="image/*" capture multiple>
                <p class="field-hint">Ta bilder med telefonen eller välj flera från kamerarullen.</p>

                <label for="after_photos">Efterbilder</label>
                <input id="after_photos" name="after_photos[]" type="file" accept="image/*" capture multiple>

                <button class="button button-lime" type="submit">Spara uppdraget</button>
            </form>
        @else
            <p>Provperioden är slut, så nya uppdrag kan inte skapas just nu.</p>
        @endif
        <p class="auth-switch"><a href="{{ route('assignments.index', absolute: false) }}">Tillbaka till uppdragen</a></p>
    </section>
@endsection
