@extends('layouts.app')

@section('title', 'Redigera uppdrag · Klartfoto')
@section('page-heading', 'Redigera uppdrag')

@section('content')
    <section class="assignment-form-page">
        <div class="app-page-heading">
            <div>
                <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> Utkast</p>
                <h1>Redigera uppdrag</h1>
                <p class="auth-lead">Uppdatera kundens uppgifter inför nästa rapportversion.</p>
            </div>
            <a class="button button-outline-dark" href="{{ route('assignments.show', $assignment, false) }}">Tillbaka till uppdraget</a>
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
            <form class="app-form assignment-form assignment-edit-form" method="post" action="{{ route('assignments.update', $assignment, false) }}" data-submit-feedback>
                @csrf
                @method('PUT')
                @if ($assignment->customer_id)
                    <input type="hidden" name="customer_id" value="{{ $assignment->customer_id }}">
                @endif
                @if ($assignment->customer_property_id)
                    <input type="hidden" name="customer_property_id" value="{{ $assignment->customer_property_id }}">
                @endif

                <fieldset class="assignment-details">
                    <legend>Kund och besök</legend>
                    <p class="field-help assignment-edit-help">Uppgifterna sparas på den valda kundprofilen. Den befintliga rapporten och dess länk ändras inte.</p>

                    <label for="customer_name">Kundens namn</label>
                    <input id="customer_name" name="customer_name" type="text" required maxlength="120" autocomplete="name" value="{{ old('customer_name', $assignment->customer_name) }}" aria-invalid="{{ $errors->has('customer_name') ? 'true' : 'false' }}" @if ($errors->has('customer_name')) aria-describedby="customer-name-error" @endif>
                    @error('customer_name') <span class="field-error" id="customer-name-error">{{ $message }}</span> @enderror

                    <label for="customer_email">Kundens e-postadress <span class="field-optional">valfritt</span></label>
                    <input id="customer_email" name="customer_email" type="email" inputmode="email" autocomplete="email" maxlength="254" value="{{ old('customer_email', $assignment->customer_email) }}" aria-invalid="{{ $errors->has('customer_email') ? 'true' : 'false' }}" @if ($errors->has('customer_email')) aria-describedby="customer-email-error" @endif>
                    @error('customer_email') <span class="field-error" id="customer-email-error">{{ $message }}</span> @enderror

                    <label for="address">Adress</label>
                    <input id="address" name="address" type="text" required maxlength="255" autocomplete="street-address" value="{{ old('address', $assignment->address) }}" aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}" @if ($errors->has('address')) aria-describedby="address-error" @endif>
                    @error('address') <span class="field-error" id="address-error">{{ $message }}</span> @enderror

                    <label for="performed_on">Datum</label>
                    <x-swedish-date id="performed_on" name="performed_on" :value="old('performed_on', $assignment->performed_on->toDateString())" :required="true" :invalid="$errors->has('performed_on')" error-id="performed-on-error" />
                    @error('performed_on') <span class="field-error" id="performed-on-error">{{ $message }}</span> @enderror

                    <div class="assignment-time-range">
                        <div>
                            <label for="performed_time">Starttid <span class="field-optional">valfritt</span></label>
                            <input id="performed_time" name="performed_time" type="time" value="{{ old('performed_time', filled($assignment->performed_time) ? \Illuminate\Support\Carbon::parse($assignment->performed_time)->format('H:i') : '') }}" aria-invalid="{{ $errors->has('performed_time') ? 'true' : 'false' }}" @if ($errors->has('performed_time')) aria-describedby="performed-time-error" @endif>
                            @error('performed_time') <span class="field-error" id="performed-time-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="performed_end_time">Sluttid <span class="field-optional">valfritt</span></label>
                            <input id="performed_end_time" name="performed_end_time" type="time" value="{{ old('performed_end_time', filled($assignment->performed_end_time) ? \Illuminate\Support\Carbon::parse($assignment->performed_end_time)->format('H:i') : '') }}" aria-invalid="{{ $errors->has('performed_end_time') ? 'true' : 'false' }}" @if ($errors->has('performed_end_time')) aria-describedby="performed-end-time-error" @endif>
                            @error('performed_end_time') <span class="field-error" id="performed-end-time-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <p class="assignment-time-hint">Ange bara de tider som ska visas i rapporten.</p>
                </fieldset>

                <div class="assignment-form-footer">
                    <p>Skapa en ny rapportversion på uppdragssidan när utkastet är klart.</p>
                    <button class="button button-lime" type="submit" data-loading-text="Sparar ändringar…">Spara ändringar <span aria-hidden="true">↗</span></button>
                </div>
            </form>
        @else
            <div class="app-panel"><p>Åtkomsten är pausad. Du kan fortfarande öppna befintliga rapporter.</p></div>
        @endif
    </section>
@endsection
