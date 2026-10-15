<form class="app-form" method="post" action="{{ route('settings.profile.update', absolute: false) }}">
    @csrf
    @method('PUT')
    <label for="name">Ditt namn</label>
    <input id="name" name="name" type="text" required maxlength="120" autocomplete="name" value="{{ old('name', $profile->name) }}" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
    <button class="button button-lime" type="submit">Spara namn</button>
</form>
