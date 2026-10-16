@auth
    @if (! auth()->user()->hasVerifiedEmail())
        <section id="email-verification" class="verify-banner" aria-label="Bekräfta e-post">
            <span class="verify-banner-icon" aria-hidden="true">!</span>
            <div class="verify-banner-content">
                <p><strong>Bekräfta din e-postadress</strong><span>Vi skickade en länk till {{ auth()->user()->email }}. Du kan använda Klartfoto medan du väntar.</span></p>
                @if (is_string(session('status')) && str_starts_with(session('status'), 'Vi har skickat en ny länk'))
                    <p class="form-status form-status-success" role="status">{{ session('status') }}</p>
                @endif
                @if ($errors->accountEmail->any())
                    <div class="form-status form-status-error" role="alert">
                        <ul>
                            @foreach ($errors->accountEmail->all() as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <div class="verify-banner-actions">
                    <form method="post" action="{{ route('verification.send', absolute: false) }}">
                        @csrf
                        <button type="submit" class="text-button">Skicka länken igen</button>
                    </form>
                    <details class="verify-change" @if ($errors->accountEmail->any()) open @endif>
                        <summary>Byt e-postadress</summary>
                        <form class="verify-change-form" method="post" action="{{ route('verification.update', absolute: false) }}">
                            @csrf
                            @method('PUT')
                            <label for="account_email">Ny e-postadress</label>
                            <input id="account_email" name="account_email" type="email" inputmode="email" autocomplete="email" required maxlength="254" value="{{ old('account_email', auth()->user()->email) }}" aria-invalid="{{ $errors->accountEmail->has('account_email') ? 'true' : 'false' }}">
                            <button class="button button-dark" type="submit">Spara adressen</button>
                        </form>
                    </details>
                </div>
            </div>
        </section>
    @endif
@endauth
