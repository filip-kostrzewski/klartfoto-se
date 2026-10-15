@auth
    @if (! auth()->user()->hasVerifiedEmail())
        <section class="verify-banner" aria-label="Bekräfta e-post">
            <p><strong>Bekräfta din e-postadress</strong></p>
            <p>Vi har skickat en länk till {{ auth()->user()->email }}. Du kan använda Klartfoto medan du väntar.</p>

            @if (session('status'))
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

            <form method="post" action="{{ route('verification.send', absolute: false) }}">
                @csrf
                <button class="button button-outline-dark" type="submit">Skicka länken igen</button>
            </form>

            <form class="app-form" method="post" action="{{ route('verification.update', absolute: false) }}">
                @csrf
                @method('PUT')
                <label for="account_email">Byt e-postadress</label>
                <input id="account_email" name="account_email" type="email" inputmode="email" autocomplete="email" required maxlength="254" value="{{ old('account_email', auth()->user()->email) }}">
                <button class="button button-lime" type="submit">Spara adressen</button>
            </form>
        </section>
    @endif
@endauth
