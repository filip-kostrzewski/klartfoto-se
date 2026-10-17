@props([
    'name',
    'id',
    'value' => '',
    'required' => false,
    'invalid' => false,
    'errorId' => null,
])

@php
    $swedishDateIso = \App\Support\SwedishDate::toIso($value);
    $swedishDateIso = is_string($swedishDateIso) && \App\Support\SwedishDate::isIsoDate($swedishDateIso) ? $swedishDateIso : '';
@endphp

<div class="swedish-date-field" data-swedish-date-field data-swedish-date-iso="{{ $swedishDateIso }}">
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="text"
        lang="sv-SE"
        inputmode="numeric"
        autocomplete="off"
        spellcheck="false"
        placeholder="ÅÅÅÅ-MM-DD"
        value="{{ \App\Support\SwedishDate::display($value) }}"
        data-swedish-date
        @if ($required) required @endif
        aria-invalid="{{ $invalid ? 'true' : 'false' }}"
        @if ($invalid && filled($errorId)) aria-describedby="{{ $errorId }}" @endif
    >
    <button class="button button-outline-dark swedish-date-open" type="button" data-swedish-date-open aria-expanded="false" aria-haspopup="dialog" aria-controls="{{ $id }}-calendar">Välj datum</button>
    <div class="swedish-date-popover" id="{{ $id }}-calendar" data-swedish-date-popover popover="manual" role="dialog" aria-label="Välj datum">
        <div class="swedish-date-popover-bar">
            <button class="swedish-date-nav" type="button" data-swedish-date-prev aria-label="Föregående månad">‹</button>
            <strong data-swedish-date-caption></strong>
            <button class="swedish-date-nav" type="button" data-swedish-date-next aria-label="Nästa månad">›</button>
        </div>
        <div class="swedish-date-weekdays" aria-hidden="true">
            <span>må</span>
            <span>ti</span>
            <span>on</span>
            <span>to</span>
            <span>fr</span>
            <span>lö</span>
            <span>sö</span>
        </div>
        <div class="swedish-date-days" data-swedish-date-days role="grid"></div>
    </div>
    <input class="swedish-date-picker" type="date" data-swedish-date-picker tabindex="-1" aria-hidden="true">
</div>
