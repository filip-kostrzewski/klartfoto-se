@props([
    'name',
    'id',
    'value' => '',
    'required' => false,
    'invalid' => false,
    'errorId' => null,
])

<input
    id="{{ $id }}"
    name="{{ $name }}"
    type="text"
    lang="sv-SE"
    inputmode="text"
    autocomplete="off"
    spellcheck="false"
    placeholder="ÅÅÅÅ-MM-DD"
    value="{{ \App\Support\SwedishDate::display($value) }}"
    @if ($required) required @endif
    aria-invalid="{{ $invalid ? 'true' : 'false' }}"
    @if ($invalid && filled($errorId)) aria-describedby="{{ $errorId }}" @endif
>
