<?php

namespace App\Http\Controllers;

use App\Actions\CompanyLogoResponse;
use App\Actions\StoreCompanyLogo;
use App\Http\Requests\StoreCompanyLogoRequest;
use App\Models\Company;
use App\Models\User;
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

        $company->load([
            'users' => fn ($query) => $query->orderBy('id'),
            'invitations' => fn ($query) => $query->pending()->orderBy('id'),
        ]);

        return view('settings.edit', [
            'company' => $company,
            'isOwner' => $user->isOwner(),
            'seatLimit' => $company->seatLimit(),
            'seatsUsed' => $company->users->count() + $company->invitations->count(),
        ]);
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
