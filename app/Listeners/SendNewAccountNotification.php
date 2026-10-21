<?php

namespace App\Listeners;

use App\CompanyPlan;
use App\Mail\NewAccountConfirmed;
use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendNewAccountNotification
{
    public function handle(Verified $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || ! $user->isOwner()) {
            return;
        }

        $recipient = config('klartfoto.provider_contact_email');

        if (! is_string($recipient) || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        try {
            $company = $user->company;

            Mail::to($recipient)->send(new NewAccountConfirmed(
                companyName: $company?->name,
                name: $user->name,
                email: $user->email,
                registeredAt: ($user->created_at ?? now())->timezone('Europe/Stockholm')->format('Y-m-d H:i'),
                plan: $company instanceof Company ? $this->chosenPlan($company)?->name : null,
            ));
        } catch (Throwable $exception) {
            Log::warning('New account notification failed.', [
                'user_id' => $user->id,
                'exception' => $exception::class,
            ]);
        }
    }

    /**
     * Every new company starts on a trial of the Start plan, so a plan only
     * counts as chosen once it is requested, invoiced or subscribed to.
     */
    private function chosenPlan(Company $company): ?CompanyPlan
    {
        if ($company->hasPendingInvoiceRequest()) {
            return $company->nextBillingPlan();
        }

        if ($company->isManualPlanActive() || $company->subscribed('default')) {
            return $company->plan;
        }

        return null;
    }
}
