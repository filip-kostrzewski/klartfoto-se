<?php

namespace App\Console\Commands;

use App\CompanyRole;
use App\Mail\TrialEnded;
use App\Mail\TrialEndingSoon;
use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

#[Signature('billing:trial-reminders')]
#[Description('Mail account owners shortly before and right after their free trial ends')]
class SendTrialReminders extends Command
{
    public function handle(): int
    {
        $reminderDays = max(1, (int) config('klartfoto.trial_ending_reminder_days', 3));
        $sent = 0;

        $endingSoon = Company::query()
            ->whereNull('trial_ending_notified_at')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now())
            ->where('trial_ends_at', '<=', now()->addDays($reminderDays))
            ->where(fn ($query) => $query->whereNull('paid_until')->orWhere('paid_until', '<=', now()))
            ->orderBy('id')
            ->get();

        foreach ($endingSoon as $company) {
            if ($this->deliver($company, new TrialEndingSoon($company))) {
                $company->forceFill(['trial_ending_notified_at' => now()])->save();
                $sent++;
            }
        }

        $ended = Company::query()
            ->whereNull('trial_ended_notified_at')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->where('trial_ends_at', '>', now()->subDays(7))
            ->where(fn ($query) => $query->whereNull('paid_until')->orWhere('paid_until', '<=', now()))
            ->whereNull('requested_plan')
            ->orderBy('id')
            ->get();

        foreach ($ended as $company) {
            if ($this->deliver($company, new TrialEnded($company))) {
                $company->forceFill(['trial_ended_notified_at' => now()])->save();
                $sent++;
            }
        }

        $this->info("Sent {$sent} trial reminder(s).");

        return self::SUCCESS;
    }

    private function deliver(Company $company, Mailable $mailable): bool
    {
        $owner = $company->users()->where('role', CompanyRole::Owner)->orderBy('id')->first();

        if (! $owner instanceof User) {
            return false;
        }

        try {
            Mail::to($owner->email)->send($mailable);
        } catch (Throwable $exception) {
            Log::warning('Trial reminder could not be sent.', [
                'company_id' => $company->id,
                'mailable' => $mailable::class,
                'exception' => $exception::class,
            ]);

            return false;
        }

        return true;
    }
}
