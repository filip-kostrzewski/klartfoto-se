@if ($googleEnabled)
    <a class="button button-outline google-button" href="{{ route('auth.google.redirect', absolute: false) }}">Fortsätt med Google</a>
    <p class="auth-or"><span>eller med e-post</span></p>
@endif
