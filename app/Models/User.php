<?php

namespace App\Models;

use App\CompanyRole;
use App\Notifications\SwedishResetPassword;
use App\Notifications\SwedishVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Fillable(['company_id', 'role', 'name', 'email', 'password', 'google_id', 'trial_ends_at', 'email_verified_at'])]
#[Hidden(['password', 'remember_token', 'google_id'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, MustVerifyEmail, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'onboarding_dismissed_at' => 'datetime',
            'password' => 'hashed',
            'role' => CompanyRole::class,
        ];
    }

    /**
     * Locale is not a stored column. A present locale attribute still selects
     * the notification language, and every other recipient gets Swedish.
     */
    public function preferredLocale(): ?string
    {
        $locale = $this->getAttribute('locale');
        $locales = config('marketing.locales', []);

        if (is_string($locale) && array_key_exists($locale, $locales)) {
            return $locale;
        }

        return config('marketing.default_locale', 'sv');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function isOwner(): bool
    {
        return $this->role === CompanyRole::Owner;
    }

    public function onTrial(): bool
    {
        $this->loadMissing('company');

        return $this->company?->onTrial() ?? false;
    }

    public function canManageJobs(): bool
    {
        $this->loadMissing('company');

        if ($this->company === null || ! $this->company->canManageJobs()) {
            return false;
        }

        return $this->isOwner() || $this->company->allowsTeamFeatures();
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        try {
            $this->notifyNow(new SwedishResetPassword($token));
        } catch (Throwable $exception) {
            Log::error('Kunde inte skicka lösenordsåterställning till '.$this->email.'. '.$exception->getMessage());
            report($exception);
        }
    }

    public function sendEmailVerificationNotification(): void
    {
        try {
            $this->notifyNow(new SwedishVerifyEmail);
        } catch (Throwable $exception) {
            Log::error('Kunde inte skicka e-postbekräftelse till '.$this->email.'. '.$exception->getMessage());
            report($exception);
        }
    }
}
