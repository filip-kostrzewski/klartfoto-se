<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\PlanPricing;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:requests')]
#[Description('List pending manual invoice requests')]
class ListManualBillingRequests extends Command
{
    public function handle(): int
    {
        $companies = Company::query()
            ->whereNotNull('requested_plan')
            ->whereNotNull('requested_at')
            ->orderBy('requested_at')
            ->get();

        if ($companies->isEmpty()) {
            $this->info('No pending manual billing requests.');

            return self::SUCCESS;
        }

        $rows = $companies->map(function (Company $company): array {
            $details = $company->billing_details ?? [];

            return [
                $company->id,
                $this->terminalText($company->name),
                $company->requested_plan,
                $company->nextBillingInterval()->label(),
                PlanPricing::formatted($company->nextBillingPlan(), $company->nextBillingInterval()),
                $company->requested_at?->toDateTimeString(),
                $this->terminalText($details['company_name'] ?? null),
                $this->terminalText($details['organisation_number'] ?? null),
                $this->terminalText($details['invoice_email'] ?? null),
                $this->terminalText($details['billing_address'] ?? null),
            ];
        });

        $this->table(
            ['ID', 'Account', 'Plan', 'Interval', 'Amount excl. VAT', 'Requested at', 'Invoice company', 'Org. number', 'Invoice email', 'Invoice address'],
            $rows,
        );

        return self::SUCCESS;
    }

    private function terminalText(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '—';
        }

        $withoutControls = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $withoutControls) ?? '');
    }
}
