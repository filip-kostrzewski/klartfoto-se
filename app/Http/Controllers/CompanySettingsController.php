<?php

namespace App\Http\Controllers;

use App\Actions\CompanyLogoResponse;
use App\Actions\StoreCompanyLogo;
use App\Http\Requests\StoreCompanyLogoRequest;
use App\Http\Requests\UpdateCompanyDetailsRequest;
use App\Http\Requests\UpdateProfileNameRequest;
use App\Models\Company;
use App\Models\User;
use App\WorkerHealth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CompanySettingsController extends Controller
{
    public function edit(): View
    {
        $user = $this->member();
        $company = $user->company;
        abort_if($company === null, 403);
        Gate::authorize('view', $company);

        $isOwner = $user->isOwner();

        if ($isOwner) {
            $company->load([
                'users' => fn ($query) => $query->orderBy('id'),
                'invitations' => fn ($query) => $query->pending()->orderBy('id'),
            ]);
        } else {
            $company->setAttribute('billing_details', null);
        }

        return view('settings.edit', [
            'company' => $company,
            'isOwner' => $isOwner,
            'profile' => $user,
            'seatLimit' => $isOwner ? $company->seatLimit() : 0,
            'userCount' => $isOwner ? $company->users->count() : 0,
            'pendingInvites' => $isOwner ? $company->invitations->count() : 0,
            'hasPendingInvoiceRequest' => $company->hasPendingInvoiceRequest(),
            'isTrialActive' => $company->onTrial(),
            'isManualPlanActive' => $company->isManualPlanActive(),
            'canManageJobs' => $user->canManageJobs(),
            'hasTeamFeatures' => $company->allowsTeamFeatures(),
            'canRequestInvoice' => $isOwner
                && ! $company->hasPendingInvoiceRequest()
                && ! $company->subscribed('default'),
            'workerHealth' => $isOwner ? app(WorkerHealth::class)->summary() : null,
        ]);
    }

    public function updateCompany(UpdateCompanyDetailsRequest $request): RedirectResponse
    {
        $company = $this->company($request->user());
        Gate::authorize('update', $company);

        $company->update([
            'name' => $request->string('company_name')->toString(),
            'phone' => $request->validated('phone'),
            'organization_number' => $request->validated('organization_number'),
        ]);

        return back()->with('toast', 'Företagsuppgifterna är sparade.');
    }

    public function updateProfile(UpdateProfileNameRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $user->update([
            'name' => $request->string('name')->toString(),
        ]);

        return back()->with('toast', 'Ditt namn är sparat.');
    }

    public function storeLogo(StoreCompanyLogoRequest $request, StoreCompanyLogo $logos): RedirectResponse
    {
        $company = $this->company($request->user());

        if (! $company->allowsTeamFeatures()) {
            return back()->withErrors([
                'logo' => 'Provperioden är slut. Egen logotyp ingår i Team.',
            ]);
        }

        $logos->handle($company, $request->file('logo'));

        return back()->with('status', 'Logotypen är sparad.');
    }

    public function destroyLogo(StoreCompanyLogo $logos): RedirectResponse
    {
        $user = $this->member();
        $company = $this->company($user);
        Gate::authorize('update', $company);
        $logos->delete($company);

        return back()->with('status', 'Logotypen är borttagen.');
    }

    public function logo(CompanyLogoResponse $response): BinaryFileResponse
    {
        $user = $this->member();
        $company = $this->company($user);
        Gate::authorize('view', $company);

        return $response($company);
    }

    private function member(): User
    {
        $user = request()->user();
        abort_if($user === null, 403);

        return $user;
    }

    private function company(User $user): Company
    {
        $company = $user->company;
        abort_if($company === null, 403);

        return $company;
    }
}
