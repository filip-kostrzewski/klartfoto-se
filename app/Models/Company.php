<?php

namespace App\Models;

use App\CompanyPlan;
use App\CompanyRole;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Subscription;

#[Fillable(['name', 'plan', 'trial_ends_at', 'logo_path', 'paid_until', 'requested_plan', 'requested_at', 'billing_details', 'phone', 'organization_number'])]
#[Hidden(['billing_details'])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use Billable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan' => CompanyPlan::class,
            'feedback_pilot' => 'boolean',
            'trial_ends_at' => 'datetime',
            'paid_until' => 'datetime',
            'requested_at' => 'datetime',
            'billing_details' => 'encrypted:array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(CompanyInvitation::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function reportPhone(): ?string
    {
        $phone = $this->filledReportText($this->phone);

        if ($phone === null) {
            return null;
        }

        return $this->formatReportPhone($phone);
    }

    public function reportOrganizationNumber(): ?string
    {
        return $this->filledReportText($this->organization_number);
    }

    public function reportFooter(): string
    {
        $parts = [];
        $name = $this->filledReportText($this->name);

        if ($name !== null) {
            $parts[] = $name;
        }

        $phone = $this->reportPhone();

        if ($phone !== null) {
            $parts[] = $phone;
        }

        $organizationNumber = $this->reportOrganizationNumber();

        if ($organizationNumber !== null) {
            $parts[] = 'org.nr '.$organizationNumber;
        }

        return implode(' · ', $parts);
    }

    private function formatReportPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '0046') && strlen($digits) === 13) {
            $digits = '0'.substr($digits, 4);
        }

        if (str_starts_with($digits, '46') && strlen($digits) === 11) {
            $digits = '0'.substr($digits, 2);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '07')) {
            return substr($digits, 0, 3).'-'.substr($digits, 3, 3).' '.substr($digits, 6, 2).' '.substr($digits, 8, 2);
        }

        return $phone;
    }

    private function filledReportText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function customerProperties(): HasMany
    {
        return $this->hasMany(CustomerProperty::class);
    }

    public function stripeEmail(): ?string
    {
        return $this->users()->where('role', CompanyRole::Owner)->value('email');
    }

    /**
     * @return list<string>
     */
    public function stripePreferredLocales(): array
    {
        return ['sv'];
    }

    /**
     * @return array<string, string>
     */
    public function stripeAddress(): array
    {
        if (! config('cashier.tax_enabled')) {
            return [];
        }

        return ['country' => 'SE'];
    }

    public function trialDaysLeft(): int
    {
        if ($this->trial_ends_at === null || ! $this->trial_ends_at->isFuture()) {
            return 0;
        }

        $seconds = now()->diffInSeconds($this->trial_ends_at, true);

        return max(1, (int) ceil($seconds / 86400));
    }

    public function feedbackPilotEndsAt(): Carbon
    {
        return Carbon::parse((string) config('klartfoto.feedback_pilot_ends_on', '2026-12-31'), 'Europe/Stockholm')->endOfDay()->utc();
    }

    public function onActiveFeedbackPilot(): bool
    {
        return $this->feedback_pilot === true && $this->feedbackPilotEndsAt()->isFuture();
    }

    public function canManageJobs(): bool
    {
        return $this->onGenericTrial()
            || $this->onActiveFeedbackPilot()
            || $this->isManualPlanActive()
            || $this->subscribed('default');
    }

    public function billingIsReady(): bool
    {
        return filled(config('cashier.secret'))
            && filled(config('cashier.webhook.secret'))
            && filled(config('klartfoto.stripe_price_start'))
            && filled(config('klartfoto.stripe_price_team'));
    }

    public function allowsTeamFeatures(): bool
    {
        if ($this->onTrial() || $this->onActiveFeedbackPilot()) {
            return true;
        }

        return $this->plan === CompanyPlan::Team
            && ($this->isManualPlanActive() || $this->subscribed('default'));
    }

    public function afterTrialAccessSummary(): string
    {
        $keptUsers = min(
            max($this->users()->count(), 1),
            (int) config('klartfoto.team_seat_limit', 10),
        );
        $startSeats = (int) config('klartfoto.start_seat_limit', 1);

        return "Efter provperioden: välj Team för att behålla {$keptUsers} användare och logotypen. Med Start kan bara {$startSeats} användare logga in och logotypen visas inte.";
    }

    public function startPlanSignInMessage(): string
    {
        $startSeats = (int) config('klartfoto.start_seat_limit', 1);

        return "Med Start kan bara {$startSeats} användare logga in. Befintliga rapporter kan öppnas av kontoägaren.";
    }

    public function isManualPlanActive(): bool
    {
        return $this->paid_until?->isFuture() ?? false;
    }

    public function hasPendingInvoiceRequest(): bool
    {
        return filled($this->requested_plan) && $this->requested_at !== null;
    }

    public function seatLimit(): int
    {
        return $this->allowsTeamFeatures()
            ? (int) config('klartfoto.team_seat_limit', 10)
            : (int) config('klartfoto.start_seat_limit', 1);
    }

    public function syncPlanFromSubscription(): void
    {
        if (blank(config('cashier.secret'))) {
            return;
        }

        $subscription = $this->subscription('default');

        if (! $subscription instanceof Subscription) {
            return;
        }

        if (! $subscription->valid()) {
            $this->forceFill(['plan' => CompanyPlan::Start])->save();

            return;
        }

        $plan = match ($subscription->stripe_price) {
            config('klartfoto.stripe_price_team') => CompanyPlan::Team,
            config('klartfoto.stripe_price_start') => CompanyPlan::Start,
            default => null,
        };

        if ($plan === null) {
            return;
        }

        $this->forceFill(['plan' => $plan])->save();
    }
}
