@extends('layouts.app')

@section('title', 'Nytt uppdrag · Klartfoto')
@section('page-heading', 'Nytt uppdrag')

@section('content')
    @php
        $customerSelection = (string) old('customer_id', $selectedCustomerId ?? '');
        $propertySelection = (string) old('customer_property_id', $selectedCustomerPropertyId ?? '');
        $selectedCustomer = $savedCustomers->firstWhere('id', (int) $customerSelection);
        $selectedProperty = $selectedCustomer?->properties->firstWhere('id', (int) $propertySelection);
    @endphp

    <section class="assignment-form-page">
        <div class="app-page-heading">
            <div>
                <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> Nytt uppdrag</p>
                <h1>Dokumentera arbetet</h1>
                <p class="auth-lead">Spara kund och besök först. Lägg till bilder i nästa steg — eller fortsätt senare.</p>
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
            <form class="app-form assignment-form" method="post" action="{{ route('assignments.store', absolute: false) }}" data-submit-feedback>
                @csrf
                <div class="form-status form-status-error" role="alert" tabindex="-1" data-form-errors hidden></div>
                <fieldset class="assignment-details">
                    <legend>Kund och besök</legend>
                    @if ($savedCustomers->isNotEmpty())
                        <div class="saved-customer-picker" data-customer-picker>
                            <div class="saved-customer-picker-copy">
                                <strong>Återanvänd sparad kund</strong>
                                <p>Uppgifterna kan ändras inför besöket och sparas på kundprofilen.</p>
                            </div>
                            <div class="saved-customer-fields">
                                <div>
                                    <label for="customer_id">Kund</label>
                                    <select id="customer_id" name="customer_id" data-customer-select aria-describedby="customer-picker-help" aria-invalid="{{ $errors->has('customer_id') ? 'true' : 'false' }}">
                                        <option value="">Ny kund</option>
                                        @foreach ($savedCustomers as $customer)
                                            <option value="{{ $customer->id }}" data-customer-name="{{ $customer->name }}" data-customer-email="{{ $customer->email }}" @selected((string) $customer->id === $customerSelection)>{{ $customer->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="customer_property_id">Plats</label>
                                    <select id="customer_property_id" name="customer_property_id" data-property-select aria-describedby="customer-picker-help" aria-invalid="{{ $errors->has('customer_property_id') ? 'true' : 'false' }}">
                                        <option value="">Ny plats eller ange adress nedan</option>
                                        @foreach ($savedCustomers as $customer)
                                            @foreach ($customer->properties as $property)
                                                <option value="{{ $property->id }}" data-customer-id="{{ $customer->id }}" data-property-address="{{ $property->address }}" @selected((string) $property->id === $propertySelection)>
                                                    {{ $customer->name }} · {{ $property->label ?: $property->address }}
                                                </option>
                                            @endforeach
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <span class="saved-customer-help" id="customer-picker-help">En ny kund eller adress sparas när du skapar uppdraget.</span>
                            @error('customer_id') <span class="field-error">{{ $message }}</span> @enderror
                            @error('customer_property_id') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    <label for="customer_name">Kundens namn</label>
                    <input id="customer_name" name="customer_name" type="text" required maxlength="120" autocomplete="name" value="{{ old('customer_name', $selectedCustomer?->name ?? '') }}" aria-invalid="{{ $errors->has('customer_name') ? 'true' : 'false' }}" @if ($errors->has('customer_name')) aria-describedby="customer-name-error" @endif>
                    @error('customer_name') <span class="field-error" id="customer-name-error">{{ $message }}</span> @enderror

                    <label for="customer_email">Kundens e-postadress <span class="field-optional">valfritt</span></label>
                    <input id="customer_email" name="customer_email" type="email" inputmode="email" autocomplete="email" maxlength="254" value="{{ old('customer_email', $selectedCustomer?->email ?? '') }}" aria-invalid="{{ $errors->has('customer_email') ? 'true' : 'false' }}" @if ($errors->has('customer_email')) aria-describedby="customer-email-error" @endif>
                    @error('customer_email') <span class="field-error" id="customer-email-error">{{ $message }}</span> @enderror

                    <label for="address">Adress</label>
                    <input id="address" name="address" type="text" required maxlength="255" autocomplete="street-address" value="{{ old('address', $selectedProperty?->address ?? '') }}" aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}" @if ($errors->has('address')) aria-describedby="address-error" @endif>
                    @error('address') <span class="field-error" id="address-error">{{ $message }}</span> @enderror

                    <label for="performed_on">Datum</label>
                    <x-swedish-date id="performed_on" name="performed_on" :value="old('performed_on', now()->toDateString())" :required="true" :invalid="$errors->has('performed_on')" error-id="performed-on-error" />
                    @error('performed_on') <span class="field-error" id="performed-on-error">{{ $message }}</span> @enderror

                    <div class="assignment-time-range">
                        <div>
                            <label for="performed_time">Starttid <span class="field-optional">valfritt</span></label>
                            <input id="performed_time" name="performed_time" type="time" value="{{ old('performed_time') }}" aria-invalid="{{ $errors->has('performed_time') ? 'true' : 'false' }}" @if ($errors->has('performed_time')) aria-describedby="performed-time-error" @endif>
                            <span class="field-error" data-field-error="performed_time" hidden></span>
                            @error('performed_time') <span class="field-error" id="performed-time-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="performed_end_time">Sluttid <span class="field-optional">valfritt</span></label>
                            <input id="performed_end_time" name="performed_end_time" type="time" value="{{ old('performed_end_time') }}" aria-invalid="{{ $errors->has('performed_end_time') ? 'true' : 'false' }}" @if ($errors->has('performed_end_time')) aria-describedby="performed-end-time-error" @endif>
                            <span class="field-error" data-field-error="performed_end_time" hidden></span>
                            @error('performed_end_time') <span class="field-error" id="performed-end-time-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <p class="assignment-time-hint">Valfritt. Ange både start och slut för ett intervall, till exempel kl. 09:00–11:30.</p>
                </fieldset>

                <div class="assignment-form-footer">
                    <p>Utkastet sparas först. På nästa sida väljer du plats och lägger till bilderna före och efter.</p>
                    <button class="button button-lime" type="submit" data-loading-text="Sparar utkast…">Spara och lägg till bilder</button>
                </div>
            </form>
        @else
            <div class="app-panel"><p>Åtkomsten är pausad, så nya uppdrag kan inte skapas just nu.</p></div>
        @endif
    </section>
@endsection
