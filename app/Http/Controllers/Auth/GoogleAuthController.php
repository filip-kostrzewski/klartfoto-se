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

        $email = $googleUser->getEmail();

        if (! is_string($email) || trim($email) === '') {
            return redirect()->route('login')->withErrors([
                'email' => 'Google lämnade ingen e-postadress. Skapa kontot med e-post och lösenord i stället.',
            ]);
        }

        $googleId = (string) $googleUser->getId();
        $user = User::query()->where('google_id', $googleId)->first()
            ?? User::query()->where('email', $email)->first();

        if ($user === null) {
            $name = trim((string) $googleUser->getName());

            $user = $accounts->handle(
                $name !== '' ? $name : Str::before($email, '@'),
                $email,
                Str::password(40),
                $googleId,
            );
        } else {
            $user->forceFill([
                'google_id' => $googleId,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->route('assignments.index');
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
}
