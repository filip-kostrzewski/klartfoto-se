<?php

namespace App\Http\Controllers;

use App\Actions\CreateAssignmentReportVersion;
use App\Actions\SendAssignmentReportEmail;
use App\Http\Requests\SendAssignmentEmailRequest;
use App\Models\Assignment;
use App\Models\AssignmentEmailAttempt;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class SendAssignmentEmailController extends Controller
{
    public function __invoke(
        SendAssignmentEmailRequest $request,
        Assignment $assignment,
        SendAssignmentReportEmail $sendReportEmail,
        CreateAssignmentReportVersion $versions,
    ): RedirectResponse {
        Gate::authorize('view', $assignment);

        $user = $request->user();
        $previousInput = $request->safe()->only(['recipient_email', 'message']);

        if (! $user instanceof User || ! $user->hasVerifiedEmail()) {
            return back()->withInput($previousInput)->withErrors([
                'email' => 'Bekräfta din e-postadress innan du skickar rapporter.',
            ]);
        }

        if (! $user->canManageJobs()) {
            return back()->withInput($previousInput)->withErrors([
                'email' => 'Din åtkomst till nya uppdrag har pausats. Du kan fortfarande öppna befintliga rapporter.',
            ]);
        }

        if (! $assignment->photos()->exists()) {
            return back()->withInput($previousInput)->withErrors([
                'email' => 'Ladda upp minst ett foto innan du skickar rapporten.',
            ]);
        }

        if ($request->boolean('confirm_incomplete_pairs')) {
            $previousInput['confirm_incomplete_pairs'] = '1';
        }

        if ($assignment->isMissingAfterPhoto() && ! $request->boolean('confirm_incomplete_pairs')) {
            return back()->withInput($previousInput)->withErrors([
                'confirm_incomplete_pairs' => __('assignments.incomplete_confirm_error'),
            ]);
        }

        $attempt = $sendReportEmail->send(
            $assignment,
            $user,
            $request->validated('recipient_email'),
            $request->validated('message'),
            $versions,
        );

        if (! $attempt instanceof AssignmentEmailAttempt) {
            $latestAttempt = $assignment->emailAttempts()->first();

            $message = match ($latestAttempt?->status) {
                AssignmentEmailAttempt::STATUS_SENDING => 'Ett mejl håller redan på att skickas. Vänta en stund innan du försöker igen.',
                AssignmentEmailAttempt::STATUS_SENT => 'Rapporten skickades nyligen. Vänta en minut innan du skickar igen.',
                AssignmentEmailAttempt::STATUS_UNKNOWN => 'Det gick inte att bekräfta om mejlet skickades. Kontrollera med mottagaren innan du skickar igen.',
                default => 'Rapporten skickas redan. Kontrollera status innan du försöker igen.',
            };

            return back()->withInput($previousInput)->with('error', $message);
        }

        return match ($attempt->status) {
            AssignmentEmailAttempt::STATUS_SENT => back()->with('status', 'Mejlet har lämnats till e-postleverantören.'),
            AssignmentEmailAttempt::STATUS_UNKNOWN => back()->withInput($previousInput)->with('error', 'Det gick inte att bekräfta om mejlet skickades. Kontrollera med mottagaren innan du skickar igen.'),
            default => back()->withInput($previousInput)->with('error', 'Mejlet kunde inte skickas. Försök igen eller kopiera rapportlänken och skicka den själv.'),
        };
    }
}
