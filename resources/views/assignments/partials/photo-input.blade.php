<div class="photo-picker" data-photo-picker>
    <label class="photo-picker-trigger" for="{{ $inputId }}">{{ $label }}</label>
    <input id="{{ $inputId }}" class="photo-picker-input" name="{{ $inputName }}" type="file" accept="image/*,.heic,.heif" capture multiple>
    <p class="photo-picker-count" data-photo-count hidden></p>
    <p class="photo-picker-error" data-photo-error hidden></p>
    <ul class="photo-picker-list" data-photo-list></ul>
</div>
