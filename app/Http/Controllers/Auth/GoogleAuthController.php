<?php

namespace App\Http\Controllers\Auth;

use App\Actions\CreateCompanyAccount;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class GoogleAuthController extends Controller
{
    public static function configured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    public function redirect(): RedirectResponse
    {
        if ($response = $this->unavailable()) {
            return $response;
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(CreateCompanyAccount $accounts): RedirectResponse
    {
        if ($response = $this->unavailable()) {
            return $response;
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return redirect()->route('login')->withErrors([
                'email' => 'Google-inloggningen avbröts. Försök igen.',
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('login')->withErrors([
                'email' => 'Google-inloggningen avbröts. Försök igen.',
            ]);
        }

        $googleId = (string) $googleUser->getId();
        if ($googleId === '') {
            return redirect()->route('login')->withErrors([
                'email' => 'Google kunde inte bekräfta kontot. Logga in med e-post och lösenord.',
            ]);
        }

        $claims = $googleUser->getRaw();
        $email = $googleUser->getEmail();
        $user = User::query()->where('google_id', $googleId)->first();
        $created = false;

        if ($user instanceof User) {
            if (
                is_string($email)
                && strcasecmp(trim($email), $user->email) === 0
                && $this->hasAuthoritativeVerifiedEmail($claims, $email)
                && ! $user->hasVerifiedEmail()
            ) {
                $user->markEmailAsVerified();
            }
        } else {
            if (! is_string($email) || trim($email) === '') {
                return redirect()->route('login')->withErrors([
                    'email' => 'Google lämnade ingen e-postadress. Skapa kontot med e-post och lösenord i stället.',
                ]);
            }

            $email = trim($email);
            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [Str::lower($email)])
                ->first();
            $emailIsAuthoritative = $this->hasAuthoritativeVerifiedEmail($claims, $email);

            if ($user instanceof User) {
                if (filled($user->google_id)) {
                    return redirect()->route('login')->withErrors([
                        'email' => 'Kontot är redan kopplat till en annan Google-inloggning. Logga in med e-post och lösenord.',
                    ]);
                }

                if (! $emailIsAuthoritative) {
                    return redirect()->route('login')->withErrors([
                        'email' => 'Google kan inte bekräfta att e-postadressen tillhör kontot. Logga in med e-post och lösenord.',
                    ]);
                }

                $user->forceFill(['google_id' => $googleId])->save();

                if (! $user->hasVerifiedEmail()) {
                    $user->markEmailAsVerified();
                }
            } else {
                $name = trim((string) $googleUser->getName());

                $user = $accounts->handle(
                    $name !== '' ? $name : Str::before($email, '@'),
                    $email,
                    Str::password(40),
                    googleId: $googleId,
                    emailIsVerified: $emailIsAuthoritative,
                );
                $created = true;

                if (! $user->hasVerifiedEmail()) {
                    $user->sendEmailVerificationNotification();
                }
            }
        }

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        $redirect = redirect()->route('assignments.index');

        if ($created) {
            $redirect->with('toast', 'Välkommen! Ditt konto är skapat.');
        }

        return $redirect;
    }

    private function unavailable(): ?RedirectResponse
    {
        if (self::configured()) {
            return null;
        }

        return redirect()->route('login')->withErrors([
            'email' => 'Google-inloggning är inte aktiverad. Logga in med e-post och lösenord.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function hasAuthoritativeVerifiedEmail(array $claims, string $email): bool
    {
        $emailIsVerified = ($claims['email_verified'] ?? $claims['verified_email'] ?? false) === true;

        if (! $emailIsVerified) {
            return false;
        }

        $emailDomain = Str::afterLast(Str::lower($email), '@');
        $hostedDomain = $claims['hd'] ?? null;

        return $emailDomain === 'gmail.com'
            || (is_string($hostedDomain) && trim($hostedDomain) !== '');
    }
}
