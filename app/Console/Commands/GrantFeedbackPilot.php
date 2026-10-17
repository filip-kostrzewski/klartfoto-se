<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\TrialPeriod;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('company:feedback-pilot {company : Company ID} {--revoke : End the invitation and return the company to the normal 30-day trial}')]
#[Description('Grant or revoke the invitation-only feedback pilot for one company')]
class GrantFeedbackPilot extends Command
{
    public function handle(): int
    {
        $companyId = filter_var($this->argument('company'), FILTER_VALIDATE_INT);

        if (! is_int($companyId) || $companyId < 1) {
            $this->error('Company ID must be a positive integer.');

            return self::FAILURE;
        }

        $company = Company::query()->find($companyId);

        if (! $company instanceof Company) {
            $this->error('Company not found.');

            return self::FAILURE;
        }

        if ($this->option('revoke')) {
            $trialEndsAt = TrialPeriod::endsAt($company->created_at);
            $company->forceFill([
                'feedback_pilot' => false,
                'trial_ends_at' => $trialEndsAt,
            ])->save();
            $company->users()->update(['trial_ends_at' => $trialEndsAt]);
            $this->info('Feedback pilot revoked for company '.$company->id.'.');

            return self::SUCCESS;
        }

        $pilotEndsAt = $company->feedbackPilotEndsAt();
        $company->forceFill([
            'feedback_pilot' => true,
            'trial_ends_at' => $pilotEndsAt,
        ])->save();
        $company->users()->update(['trial_ends_at' => $pilotEndsAt]);
        $this->info('Feedback pilot granted for company '.$company->id.' until '.$pilotEndsAt->timezone('Europe/Stockholm')->toDateString().'.');

        return self::SUCCESS;
    }
}
