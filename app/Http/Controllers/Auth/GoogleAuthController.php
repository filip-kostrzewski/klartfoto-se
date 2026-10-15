<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\TrialPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        $this->ensureEnabled();

        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        $this->ensureEnabled();

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

            $user = User::query()->create([
                'name' => $name !== '' ? $name : Str::before($email, '@'),
                'email' => $email,
                'password' => Str::password(40),
                'google_id' => $googleId,
                'email_verified_at' => now(),
                'trial_ends_at' => TrialPeriod::endsAt(),
            ]);
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

    private function ensureEnabled(): void
    {
        abort_unless(filled(config('services.google.client_id')), 404);
    }
}
