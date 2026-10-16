@extends('layouts.app')

@section('title', 'Nytt lösenord · Klartfoto')

@section('content')
    <div class="auth-layout auth-layout-compact">
        @include('auth.partials.visual', [
            'authVisualTitle' => 'Snart är du tillbaka.',
            'authVisualText' => 'Välj ett nytt lösenord så kan du fortsätta skapa och dela rapporter.',
        ])
        <section class="auth-card" aria-label="Byt lösenord">
            <div class="auth-card-heading">
                <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> Konto</p>
                <h1>Byt lösenord</h1>
                <p class="auth-lead">Välj ett lösenord med minst åtta tecken.</p>
            </div>

            @if ($errors->any())
                <div class="form-status form-status-error" role="alert" tabindex="-1" data-focus-status>
                    <ul>
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form class="app-form auth-form" method="post" action="{{ route('password.update', absolute: false) }}" data-submit-feedback>
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <label for="email">E-postadress</label>
                <input id="email" name="email" type="email" inputmode="email" autocomplete="username" required maxlength="254" value="{{ old('email', $email) }}" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" @if ($errors->has('email')) aria-describedby="email-error" @endif>
                @error('email') <span class="field-error" id="email-error">{{ $message }}</span> @enderror

                <label for="password">Nytt lösenord</label>
                <div class="password-field">
                    <input id="password" name="password" type="password" required autocomplete="new-password" minlength="8" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" @if ($errors->has('password')) aria-describedby="password-error" @endif>
                    <button class="password-toggle" type="button" hidden data-password-toggle data-show-label="Visa" data-hide-label="Dölj" aria-label="Visa lösenord" aria-pressed="false"><x-icon name="eye" /><span>Visa</span></button>
                </div>
                @error('password') <span class="field-error" id="password-error">{{ $message }}</span> @enderror

                <label for="password_confirmation">Upprepa lösenordet</label>
                <div class="password-field">
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" aria-invalid="{{ $errors->has('password_confirmation') ? 'true' : 'false' }}" @if ($errors->has('password_confirmation')) aria-describedby="password-confirmation-error" @endif>
                    <button class="password-toggle" type="button" hidden data-password-toggle data-show-label="Visa" data-hide-label="Dölj" aria-label="Visa lösenord" aria-pressed="false"><x-icon name="eye" /><span>Visa</span></button>
                </div>
                @error('password_confirmation') <span class="field-error" id="password-confirmation-error">{{ $message }}</span> @enderror

                <button class="button button-lime auth-submit" type="submit" data-loading-text="Sparar…">Spara nytt lösenord</button>
            </form>
        </section>
    </div>
@endsection
