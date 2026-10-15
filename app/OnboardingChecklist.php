<?php

namespace App;

use App\Models\Assignment;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class OnboardingChecklist
{
    public const COMPLETED_MESSAGE = 'Klart! Du är igång.';

    /**
     * @param  Collection<int, Assignment>  $assignments
     * @return list<array{label: string, done: bool, url: string|null, copyUrl: string|null, shareAction: string|null}>
     */
    public function steps(User $user, Company $company, Collection $assignments): array
    {
        $photoJob = $assignments->first(
            fn (Assignment $assignment): bool => (int) $assignment->photos_count === 0,
        ) ?? $assignments->first();
        $latestJob = $assignments->first();
        $hasTeammate = $company->users()->whereKeyNot($user->id)->exists()
            || $company->invitations()->pending()->exists();

        return [
            [
                'label' => 'Skapa första uppdraget',
                'done' => $assignments->isNotEmpty(),
                'url' => route('assignments.create', absolute: false),
                'copyUrl' => null,
                'shareAction' => null,
            ],
            [
                'label' => 'Ladda upp bilder',
                'done' => $assignments->contains(
                    fn (Assignment $assignment): bool => $assignment->photos_count > 0,
                ),
                'url' => $photoJob instanceof Assignment
                    ? route('assignments.show', $photoJob, false)
                    : route('assignments.create', absolute: false),
                'copyUrl' => null,
                'shareAction' => null,
            ],
            [
                'label' => 'Dela rapportlänken',
                'done' => $assignments->contains(
                    fn (Assignment $assignment): bool => $assignment->link_copied_at !== null,
                ),
                'url' => $latestJob instanceof Assignment ? null : route('assignments.create', absolute: false),
                'copyUrl' => $latestJob instanceof Assignment
                    ? route('reports.show', ['token' => $latestJob->public_token])
                    : null,
                'shareAction' => $latestJob instanceof Assignment
                    ? route('assignments.share', $latestJob, false)
                    : null,
            ],
            [
                'label' => 'Bjud in en teammedlem',
                'done' => $hasTeammate,
                'url' => $user->isOwner() ? route('settings.edit', absolute: false).'#medlemmar' : null,
                'copyUrl' => null,
                'shareAction' => null,
            ],
        ];
    }

    public function isComplete(User $user): bool
    {
        $company = $user->company;
        if ($company === null) {
            return false;
        }

        $assignments = $company->assignments()
            ->withCount('photos')
            ->orderByDesc('performed_on')
            ->orderByDesc('id')
            ->get();

        return collect($this->steps($user, $company, $assignments))->every(
            fn (array $step): bool => $step['done'] === true,
        );
    }

    public function finishedDuring(User $user, bool $wasComplete): bool
    {
        if ($wasComplete || ! $this->isComplete($user)) {
            return false;
        }

        session(['onboarding_celebrate' => $user->id]);

        return true;
    }
}
