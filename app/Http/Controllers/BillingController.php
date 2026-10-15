<?php

namespace App\Http\Controllers;

use App\CompanyPlan;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Checkout;

class BillingController extends Controller
{
    public function show(): View
    {
        $company = $this->company();
        Gate::authorize('update', $company);

        return view('settings.billing', [
            'company' => $company,
            'isOwner' => request()->user()?->isOwner() ?? false,
            'billingReady' => $company->billingIsReady(),
            'checkoutSuccess' => request()->query('checkout') === 'success',
        ]);
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
