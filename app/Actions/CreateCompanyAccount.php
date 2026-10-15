<?php

namespace App\Actions;

use App\CompanyPlan;
use App\CompanyRole;
use App\Models\Company;
use App\Models\User;
use App\TrialPeriod;
use Illuminate\Support\Facades\DB;

class CreateCompanyAccount
{
    public function handle(
        string $companyName,
        string $email,
        string $password,
        ?string $googleId = null,
        ?string $ownerName = null,
        bool $emailIsVerified = false,
    ): User {
        $ownerName = trim((string) $ownerName);

        return DB::transaction(function () use ($companyName, $email, $password, $googleId, $ownerName, $emailIsVerified): User {
            $trialEndsAt = TrialPeriod::endsAt();

            $company = Company::query()->create([
                'name' => $companyName,
                'plan' => CompanyPlan::Start,
                'trial_ends_at' => $trialEndsAt,
            ]);

            return User::query()->create([
                'company_id' => $company->id,
                'role' => CompanyRole::Owner,
                'name' => $ownerName !== '' ? $ownerName : $companyName,
                'email' => $email,
                'password' => $password,
                'google_id' => $googleId,
                'email_verified_at' => $emailIsVerified ? now() : null,
                'trial_ends_at' => $trialEndsAt,
            ]);
        });
    }
}
