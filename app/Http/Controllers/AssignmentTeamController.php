<?php

namespace App\Http\Controllers;

use App\Actions\CreateAssignmentReportVersion;
use App\CompanyRole;
use App\Http\Requests\UpdateAssignmentTeamRequest;
use App\Models\Assignment;
use App\Models\AssignmentReportVersion;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AssignmentTeamController extends Controller
{
    public function update(UpdateAssignmentTeamRequest $request, Assignment $assignment): RedirectResponse
    {
        Gate::authorize('update', $assignment);

        $changes = $request->validated();
        $reviewerChanged = (int) ($changes['reviewer_user_id'] ?? 0) !== (int) ($assignment->reviewer_user_id ?? 0)
            || (int) ($changes['assigned_to_user_id'] ?? 0) !== (int) ($assignment->assigned_to_user_id ?? 0);

        $assignment->forceFill([
            'assigned_to_user_id' => $changes['assigned_to_user_id'] ?? null,
            'reviewer_user_id' => $changes['reviewer_user_id'] ?? null,
            ...($reviewerChanged ? ['reviewed_by_user_id' => null, 'reviewed_at' => null] : []),
        ])->save();

        return back()->with('toast', 'Ansvariga för uppdraget är uppdaterade.');
    }

    public function review(
        Request $request,
        Assignment $assignment,
        AssignmentReportVersion $reportVersion,
        CreateAssignmentReportVersion $versions,
    ): RedirectResponse {
        Gate::authorize('update', $assignment);

        $user = $request->user();
        $company = $user?->company;
        abort_unless(
            $user instanceof User
                && $user->canManageJobs()
                && $company?->allowsTeamFeatures()
                && ($user->isOwner() || $assignment->reviewer_user_id === $user->id),
            403,
        );

        $reviewResult = DB::transaction(function () use ($assignment, $reportVersion, $user, $versions): ?string {
            $lockedAssignment = Assignment::query()
                ->whereKey($assignment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $version = AssignmentReportVersion::query()
                ->whereKey($reportVersion->getKey())
                ->where('assignment_id', $lockedAssignment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $currentVersion = $lockedAssignment->latestReportVersion();

            if (! $currentVersion instanceof AssignmentReportVersion || $currentVersion->getKey() !== $version->getKey()) {
                return 'stale';
            }

            $versionIsCurrent = $version->token === $lockedAssignment->public_token
                && $version->status === AssignmentReportVersion::STATUS_READY
                && $lockedAssignment->photos()->exists()
                && ! $versions->hasUnpublishedDraftChanges($lockedAssignment, $version);

            if (! $versionIsCurrent) {
                return 'not_ready';
            }

            $lockedAssignment->forceFill([
                'reviewed_by_user_id' => $user->id,
                'reviewed_at' => now(),
            ])->save();

            return null;
        }, attempts: 3);

        if ($reviewResult === 'stale') {
            return back()->withErrors([
                'review' => 'Rapportversionen har ändrats. Uppdatera sidan och granska den senaste versionen.',
            ]);
        }

        if ($reviewResult !== null) {
            return back()->withErrors([
                'review' => 'Skapa en aktuell rapportversion innan uppdraget kan granskas.',
            ]);
        }

        return back()->with('toast', 'Rapportversionen är granskad.');
    }

    public function handoff(Request $request, Assignment $assignment): RedirectResponse
    {
        Gate::authorize('update', $assignment);

        $user = $request->user();
        $company = $user?->company;
        abort_unless(
            $user instanceof User
                && $user->canManageJobs()
                && $company?->allowsTeamFeatures(),
            403,
        );

        $ownerId = $company->users()->where('role', CompanyRole::Owner)->value('id');
        abort_if($ownerId === null, 404);
        abort_unless(
            $user->isOwner() || (int) $assignment->assigned_to_user_id === $user->id,
            403,
        );
        abort_unless((int) $assignment->assigned_to_user_id !== (int) $ownerId, 422);

        $assignment->forceFill([
            'assigned_to_user_id' => $ownerId,
            'reviewer_user_id' => $ownerId,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
        ])->save();

        return back()->with('toast', 'Uppdraget har lämnats över till ägaren för granskning.');
    }
}
