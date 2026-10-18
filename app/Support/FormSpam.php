<?php

namespace App\Support;

use App\Models\SpamSubmission;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class FormSpam
{
    public const STARTED_AT_FIELD = 'form_started_at';

    public const TURNSTILE_FIELD = 'cf-turnstile-response';

    public const TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public const MINIMUM_FILL_SECONDS = 3;

    public static function turnstileEnabled(): bool
    {
        return filled(config('services.turnstile.site_key'))
            && filled(config('services.turnstile.secret_key'));
    }

    public static function stamp(int $secondsAgo = 0): string
    {
        return Crypt::encryptString((string) now()->subSeconds($secondsAgo)->getTimestamp());
    }

    /**
     * @param  list<string>  $textFields
     */
    public static function rejectionReason(Request $request, string $honeypotField = 'website', array $textFields = ['name', 'message']): ?string
    {
        if (filled($request->input($honeypotField))) {
            return 'honeypot';
        }

        if (self::filledTooFast($request)) {
            return 'too_fast';
        }

        if (self::turnstileRejected($request)) {
            return 'turnstile';
        }

        if (self::emailDomainIsBlocked($request->input('email'))) {
            return 'blocked_email_domain';
        }

        foreach ($textFields as $field) {
            $value = $request->input($field);

            if (is_string($value) && self::isGibberish($value)) {
                return 'gibberish';
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $fields
     */
    public static function record(Request $request, string $form, string $reason, array $fields): void
    {
        $payload = [];

        foreach ($fields as $field) {
            if (! $request->exists($field)) {
                continue;
            }

            $value = $request->input($field);
            $payload[$field] = is_string($value) ? Str::limit($value, 5000, '') : null;
        }

        $ip = $request->ip();

        try {
            SpamSubmission::query()->create([
                'form' => $form,
                'payload' => $payload,
                'reason' => $reason,
                'ip' => is_string($ip) ? $ip : null,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }

        Log::info('Spam form submission rejected.', [
            'form' => $form,
            'reason' => $reason,
            'ip' => is_string($ip) ? $ip : null,
        ]);
    }

    private static function filledTooFast(Request $request): bool
    {
        $stamp = $request->input(self::STARTED_AT_FIELD);

        if (! is_string($stamp) || $stamp === '') {
            return true;
        }

        try {
            $startedAt = Crypt::decryptString($stamp);
        } catch (DecryptException) {
            return true;
        }

        if (! ctype_digit($startedAt)) {
            return true;
        }

        return now()->getTimestamp() - (int) $startedAt < self::MINIMUM_FILL_SECONDS;
    }

    private static function turnstileRejected(Request $request): bool
    {
        if (! self::turnstileEnabled()) {
            return false;
        }

        $token = $request->input(self::TURNSTILE_FIELD);

        if (! is_string($token) || $token === '') {
            return true;
        }

        $payload = [
            'secret' => config('services.turnstile.secret_key'),
            'response' => $token,
        ];
        $ip = $request->ip();

        if (is_string($ip) && $ip !== '') {
            $payload['remoteip'] = $ip;
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(5)
                ->post(self::TURNSTILE_VERIFY_URL, $payload);
        } catch (ConnectionException) {
            return true;
        }

        return $response->json('success') !== true;
    }

    private static function emailDomainIsBlocked(mixed $email): bool
    {
        if (! is_string($email) || ! str_contains($email, '@')) {
            return false;
        }

        $domain = Str::lower(trim(Str::afterLast($email, '@')));
        $patterns = config('spam.blocked_email_domains', []);

        if ($domain === '' || ! is_array($patterns)) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (! is_string($pattern) || trim($pattern) === '') {
                continue;
            }

            $regex = '/^'.str_replace('\*', '.*', preg_quote(Str::lower(trim($pattern)), '/')).'$/';

            if (preg_match($regex, $domain) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * A token of at least 12 characters is gibberish when it mixes letters with
     * digits, or when its letters include no vowels. Shorter tokens stay, so
     * names like "Ms" and words like "PDF" are not rejected.
     */
    private static function isGibberish(string $value): bool
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $value, -1, PREG_SPLIT_NO_EMPTY);

        if ($tokens === false) {
            return false;
        }

        foreach ($tokens as $token) {
            if (Str::length($token) < 12) {
                continue;
            }

            $hasLetter = preg_match('/\p{L}/u', $token) === 1;
            $hasDigit = preg_match('/\p{N}/u', $token) === 1;

            if ($hasLetter && $hasDigit) {
                return true;
            }

            if ($hasLetter && preg_match('/[aeiouyåäöæøüéáíóúýàèìòùëïÿ]/iu', $token) !== 1) {
                return true;
            }
        }

        return false;
    }
}
