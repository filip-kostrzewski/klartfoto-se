<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\PlanPricing;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:renewals {--days=14 : Include paid periods that end within this many days}')]
#[Description('List paid plans that need a new Fortnox invoice soon, or that have already lapsed')]
class ListUpcomingRenewals extends Command
{
    public function handle(): int
    {
        $days = max(0, (int) $this->option('days'));

        $companies = Company::query()
            ->whereNotNull('paid_until')
            ->where('paid_until', '<=', now()->addDays($days))
            ->where('paid_until', '>', now()->subDays(60))
            ->orderBy('paid_until')
            ->get();

        if ($companies->isEmpty()) {
            $this->info('No renewals due.');

            return self::SUCCESS;
        }

        $rows = $companies->map(function (Company $company): array {
            $details = $company->billing_details ?? [];
            $plan = $company->nextBillingPlan();
            $interval = $company->nextBillingInterval();

            return [
                $company->id,
                $this->terminalText($company->name),
                $plan->value,
                $interval->label(),
                PlanPricing::formatted($plan, $interval),
                $company->paid_until?->toDateString(),
                $company->paid_until?->isPast() ? 'lapsed' : 'due',
                $this->terminalText($details['invoice_email'] ?? null),
            ];
        });

        $this->table(
            ['ID', 'Account', 'Plan', 'Interval', 'Amount excl. VAT', 'Paid until', 'Status', 'Invoice email'],
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
