@props([
    'name',
    'id',
    'value' => '',
    'required' => false,
    'invalid' => false,
    'errorId' => null,
])

<div class="swedish-date-field" data-swedish-date-field>
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
    <button class="button button-outline-dark swedish-date-open" type="button" data-swedish-date-open>Välj datum</button>
    <input class="sr-only" type="date" data-swedish-date-picker tabindex="-1" aria-hidden="true">
</div>
