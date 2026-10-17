<?php

namespace App\Http\Controllers;

use App\CompanyPlan;
use App\Http\Requests\StoreManualBillingRequest;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Mail\ManualBillingRequestReceived;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Checkout;
use Throwable;

class BillingController extends Controller
{
    public function show(): View
    {
        $company = $this->company();
        $user = request()->user();
        $isOwner = $user?->isOwner() ?? false;

        if (! $isOwner) {
            $company->setAttribute('billing_details', null);
        }

        return view('settings.billing', [
            'company' => $company,
            'isOwner' => $isOwner,
            'hasPendingInvoiceRequest' => $company->hasPendingInvoiceRequest(),
            'isTrialActive' => $company->onTrial(),
            'isFeedbackPilot' => $company->onActiveFeedbackPilot(),
            'isManualPlanActive' => $company->isManualPlanActive(),
            'canManageJobs' => $user?->canManageJobs() ?? false,
            'hasTeamFeatures' => $company->allowsTeamFeatures(),
            'canRequestInvoice' => $isOwner
                && ! $company->hasPendingInvoiceRequest()
                && ! $company->subscribed('default'),
        ]);
    }

    public function requestInvoice(StoreManualBillingRequest $request): RedirectResponse
    {
        $company = $this->company();
        Gate::authorize('update', $company);

        $validated = $request->validated();
        $requestedAt = now();
        $billingDetails = [
            'company_name' => $validated['billing_company_name'],
            'organisation_number' => $validated['organisation_number'],
            'invoice_email' => $validated['invoice_email'],
            'billing_address' => $validated['billing_address'],
        ];

        $stored = DB::transaction(function () use ($company, $validated, $requestedAt, $billingDetails): bool {
            $lockedCompany = Company::query()->lockForUpdate()->findOrFail($company->id);

            if ($lockedCompany->hasPendingInvoiceRequest()
                || $lockedCompany->subscribed('default')) {
                return false;
            }

            $lockedCompany->forceFill([
                'requested_plan' => $validated['plan'],
                'requested_at' => $requestedAt,
                'billing_details' => $billingDetails,
            ])->save();

            return true;
        });

        if (! $stored) {
            return back()->with('status', 'En fakturaförfrågan väntar redan eller abonnemanget hanteras utanför Fortnox-flödet.');
        }

        $contactEmail = config('klartfoto.billing_notify_email');

        if (is_string($contactEmail) && $contactEmail !== '') {
            try {
                Mail::to($contactEmail)->send(new ManualBillingRequestReceived(
                    companyId: $company->id,
                    companyName: $company->name,
                    plan: CompanyPlan::from($validated['plan']),
                    billingDetails: $billingDetails,
                    requestedAt: $requestedAt->toDateTimeString(),
                ));
            } catch (Throwable $exception) {
                Log::warning('Manual billing request notification failed.', [
                    'company_id' => $company->id,
                    'exception' => $exception::class,
                ]);
            }
        }

        return back()->with('status', 'Tack. Er fakturaförfrågan är registrerad.');
    }

    public function checkout(StoreSubscriptionRequest $request): Checkout|RedirectResponse
    {
        $company = $this->company();

        if (! $company->billingIsReady()) {
            return back()->with('status', 'Betalning aktiveras snart');
        }

        Cashier::$calculatesTaxes = (bool) config('cashier.tax_enabled');

        if ($company->subscribed('default')) {
            abort_unless($company->hasStripeId(), 404);

            return $company->redirectToBillingPortal(route('settings.billing'));
        }

        $price = $request->validated('plan') === CompanyPlan::Team->value
            ? (string) config('klartfoto.stripe_price_team')
            : (string) config('klartfoto.stripe_price_start');

        return $company->newSubscription('default', $price)->checkout([
            'success_url' => route('settings.billing').'?checkout=success',
            'cancel_url' => route('settings.billing'),
            'locale' => 'sv',
        ]);
    }

    public function portal(): RedirectResponse
    {
        $company = $this->company();
        Gate::authorize('update', $company);

        if (! $company->billingIsReady()) {
            return back()->with('status', 'Betalning aktiveras snart');
        }

        abort_unless($company->hasStripeId(), 404);

        return $company->redirectToBillingPortal(route('settings.billing'));
    }

    private function company(): Company
    {
        $user = request()->user();
        abort_if($user === null, 403);

        $company = $user->company;
        abort_if($company === null, 403);
        Gate::authorize('view', $company);

        return $company;
    }
}
