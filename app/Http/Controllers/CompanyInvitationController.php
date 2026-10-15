<?php

namespace App\Http\Controllers;

use App\CompanyRole;
use App\Http\Requests\AcceptCompanyInvitationRequest;
use App\Http\Requests\StoreCompanyInvitationRequest;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use App\Notifications\CompanyInvitationNotification;
use App\OnboardingChecklist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class CompanyInvitationController extends Controller
{
    public function store(StoreCompanyInvitationRequest $request, OnboardingChecklist $checklist): RedirectResponse
    {
        $owner = $request->user();
        abort_if($owner === null, 403);
        $wasComplete = $checklist->isComplete($owner);
        $email = Str::lower($request->string('email')->toString());

        if (User::query()->where('email', $email)->exists()) {
            return back()->withInput()->withErrors([
                'email' => 'Det finns redan ett konto med den e-postadressen.',
            ]);
        }

        $invitation = DB::transaction(function () use ($owner, $email): CompanyInvitation {
            $company = Company::query()->whereKey($owner->company_id)->lockForUpdate()->first();
            abort_if($company === null, 403);

            $existing = $company->invitations()->pending()->where('email', $email)->first();

            if ($existing instanceof CompanyInvitation) {
                $existing->forceFill([
                    'token' => Str::random(48),
                    'expires_at' => now()->addDays(14),
                    'invited_by' => $owner->id,
                ])->save();
                $existing->setRelation('company', $company);

                return $existing;
            }

            $used = $company->users()->count() + $company->invitations()->pending()->count();

            if ($used >= $company->seatLimit()) {
                throw ValidationException::withMessages([
                    'email' => 'Ni har nått gränsen för antalet användare.',
                ]);
            }

            $invitation = $company->invitations()->create([
                'email' => $email,
                'token' => Str::random(48),
                'invited_by' => $owner->id,
                'expires_at' => now()->addDays(14),
            ]);
            $invitation->setRelation('company', $company);

            return $invitation;
        });

        $finished = $checklist->finishedDuring($owner, $wasComplete);

        try {
            Notification::route('mail', $email)->notifyNow(new CompanyInvitationNotification($invitation));
        } catch (Throwable $exception) {
            Log::error('Kunde inte skicka inbjudan till '.$email.'. '.$exception->getMessage());
            report($exception);

            return back()->withErrors([
                'email' => 'Inbjudan är sparad, men mejlet till '.$email.' kunde inte skickas. Försök igen om en stund.',
            ]);
        }

        if ($finished) {
            return redirect()
                ->route('assignments.index')
                ->with('toast', OnboardingChecklist::COMPLETED_MESSAGE);
        }

        return back()->with('status', 'Inbjudan är skickad till '.$email.'.');
    }

    public function destroy(CompanyInvitation $invitation): RedirectResponse
    {
        $user = request()->user();
        abort_if($user === null, 403);
        abort_unless($user->company_id === $invitation->company_id, 404);
        abort_unless($user->can('update', $invitation->company), 403);

        $invitation->delete();

        return back()->with('status', 'Inbjudan är borttagen.');
    }

    public function create(string $token): View
    {
        $invitation = $this->invitation($token);

        return view('invitations.accept', [
            'invitation' => $invitation,
            'closed' => $invitation->isClosed(),
        ]);
    }

    public function storeAcceptance(AcceptCompanyInvitationRequest $request, string $token): RedirectResponse
    {
        $invitation = $this->invitation($token);

        if ($invitation->isClosed()) {
            return back()->withErrors([
                'token' => 'Inbjudan är inte längre giltig. Be ägaren skicka en ny.',
            ]);
        }

        $user = DB::transaction(function () use ($invitation, $request): User {
            $locked = CompanyInvitation::query()->whereKey($invitation->id)->lockForUpdate()->first();
            abort_if($locked === null, 404);

            if ($locked->isClosed()) {
                throw ValidationException::withMessages([
                    'token' => 'Inbjudan är inte längre giltig. Be ägaren skicka en ny.',
                ]);
            }

            if (User::query()->where('email', $locked->email)->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'Det finns redan ett konto med den e-postadressen.',
                ]);
            }

            $company = Company::query()->whereKey($locked->company_id)->lockForUpdate()->first();
            abort_if($company === null, 404);

            if ($company->users()->count() >= $company->seatLimit()) {
                throw ValidationException::withMessages([
                    'email' => 'Företaget har nått gränsen för antalet användare.',
                ]);
            }

            $user = User::query()->create([
                'company_id' => $company->id,
                'role' => CompanyRole::Member,
                'name' => $request->string('name')->toString(),
                'email' => $locked->email,
                'password' => $request->string('password')->toString(),
                'email_verified_at' => now(),
                'trial_ends_at' => $company->trial_ends_at,
            ]);

            $locked->forceFill(['accepted_at' => now()])->save();

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('assignments.index');
    }

    private function invitation(string $token): CompanyInvitation
    {
        return CompanyInvitation::query()
            ->with('company')
            ->where('token', $token)
            ->firstOrFail();
    }
}
