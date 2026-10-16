@php
    $multiple = $multiple ?? true;
    $uploadIdName = $uploadIdName ?? null;
    $required = $required ?? false;
@endphp
<div class="photo-picker" data-photo-picker>
    <label class="photo-picker-trigger" for="{{ $inputId }}">{{ $label }}</label>
    <input id="{{ $inputId }}" class="photo-picker-input" name="{{ $inputName }}" type="file" accept="image/*,.heic,.heif" capture @if ($multiple) multiple @endif @if ($required) required @endif @if ($uploadIdName) data-upload-id-name="{{ $uploadIdName }}" @endif>
    @if ($uploadIdName)
        <span hidden data-upload-id-container data-upload-id-name="{{ $uploadIdName }}"></span>
        <span hidden data-upload-slot-container></span>
    @endif
    <p class="photo-picker-count" data-photo-count hidden></p>
    <p class="photo-picker-error" data-photo-error hidden></p>
    <ul class="photo-picker-list" data-photo-list></ul>
</div>
