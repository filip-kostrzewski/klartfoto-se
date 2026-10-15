<div class="photo-picker" data-photo-picker>
    <label for="{{ $inputId }}">{{ $label }}</label>
    <input id="{{ $inputId }}" name="{{ $inputName }}" type="file" accept="image/*" capture multiple>
    <p class="photo-picker-count" data-photo-count hidden></p>
    <ul class="photo-picker-list" data-photo-list></ul>
</div>
