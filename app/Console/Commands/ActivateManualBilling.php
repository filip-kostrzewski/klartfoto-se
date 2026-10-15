<?php

namespace App\Console\Commands;

use App\CompanyPlan;
use App\Models\Company;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

#[Signature('billing:activate {company : Company ID} {--plan= : start or team} {--until= : Paid-through date in YYYY-MM-DD format} {--invoice= : Fortnox invoice reference} {--by= : Operator name} {--renew : Renew an existing manual plan without another request} {--shorten : Allow shortening an existing paid period}')]
#[Description('Activate a manually invoiced company plan')]
class ActivateManualBilling extends Command
{
    public function handle(): int
    {
        $companyId = filter_var($this->argument('company'), FILTER_VALIDATE_INT);
        $planValue = $this->option('plan');
        $plan = is_string($planValue) ? CompanyPlan::tryFrom($planValue) : null;

        if (! is_int($companyId) || $companyId < 1) {
            $this->error('Company ID must be a positive integer.');

            return self::FAILURE;
        }

        if (! $plan instanceof CompanyPlan) {
            $this->error('Plan must be "start" or "team".');

            return self::FAILURE;
        }

        $validation = Validator::make([
            'until' => $this->option('until'),
            'invoice' => $this->option('invoice'),
            'by' => $this->option('by'),
        ], [
            'until' => ['required', 'date_format:Y-m-d', 'after:today'],
            'invoice' => ['required', 'string', 'max:120'],
            'by' => ['required', 'string', 'max:120'],
        ]);

        if ($validation->fails()) {
            foreach ($validation->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $untilValue = trim((string) $this->option('until'));
        $invoiceReference = trim((string) $this->option('invoice'));
        $operator = trim((string) $this->option('by'));

        if ($invoiceReference === '' || $operator === '') {
            $this->error('Invoice reference and operator name are required.');

            return self::FAILURE;
        }

        if (preg_match('/[\x00-\x1F\x7F]/u', $invoiceReference.$operator) === 1) {
            $this->error('Invoice reference and operator name cannot contain control characters.');

            return self::FAILURE;
        }

        $paidUntil = Carbon::parse($untilValue)->endOfDay();
        $activatedAt = now();
        $allowRenewalWithoutRequest = (bool) $this->option('renew');
        $allowShorterPeriod = (bool) $this->option('shorten');
        $failure = null;

        $activated = DB::transaction(function () use (
            $companyId,
            $plan,
            $paidUntil,
            $activatedAt,
            $invoiceReference,
            $operator,
            $allowRenewalWithoutRequest,
            $allowShorterPeriod,
            &$failure,
        ): bool {
            $company = Company::query()->lockForUpdate()->find($companyId);

            if (! $company instanceof Company) {
                $failure = 'Company not found.';

                return false;
            }

            if ($company->hasPendingInvoiceRequest()) {
                if ($company->requested_plan !== $plan->value) {
                    $failure = 'Plan does not match the pending request.';

                    return false;
                }
            } elseif ($allowRenewalWithoutRequest) {
                if ($company->paid_until === null) {
                    $failure = 'Only a company with a previous manual paid period can be renewed without a request.';

                    return false;
                }

                if ($company->plan !== $plan) {
                    $failure = 'A renewal without a request must keep the current plan.';

                    return false;
                }

                if ($paidUntil->toDateString() <= $company->paid_until->toDateString()) {
                    $failure = 'A renewal must extend the existing paid-through date.';

                    return false;
                }
            } else {
                $failure = 'Company has no pending invoice request.';

                return false;
            }

            if ($company->paid_until?->isFuture()
                && $paidUntil->isBefore($company->paid_until)
                && ! $allowShorterPeriod) {
                $failure = 'The new paid-through date would shorten the current paid period. Use --shorten to confirm.';

                return false;
            }

            if (DB::table('manual_billing_activations')->where('invoice_reference', $invoiceReference)->exists()) {
                $failure = 'Invoice reference has already been used.';

                return false;
            }

            DB::table('manual_billing_activations')->insert([
                'company_id' => $company->id,
                'plan' => $plan->value,
                'starts_at' => $company->plan === $plan
                    && $company->paid_until?->isFuture()
                    && $paidUntil->toDateString() > $company->paid_until->toDateString()
                    ? $company->paid_until
                    : $activatedAt,
                'paid_until' => $paidUntil,
                'invoice_reference' => $invoiceReference,
                'activated_by' => $operator,
                'activated_at' => $activatedAt,
            ]);

            $company->forceFill([
                'plan' => $plan,
                'paid_until' => $paidUntil,
                'requested_plan' => null,
                'requested_at' => null,
            ])->save();

            return true;
        });

        if (! $activated) {
            $this->error($failure ?? 'Manual billing activation failed.');

            return self::FAILURE;
        }

        $this->info("Activated company {$companyId} on the {$plan->value} plan through {$paidUntil->toDateString()}.");

        return self::SUCCESS;
    }
}
