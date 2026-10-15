<?php

namespace App\Http\Controllers;

use App\Actions\StoreAssignmentPhotos;
use App\Http\Requests\StoreAssignmentPhotoRequest;
use App\Http\Requests\StoreAssignmentRequest;
use App\Models\Assignment;
use App\Models\AssignmentPhoto;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function index(): View
    {
        $user = request()->user();
        abort_if($user === null, 403);

        $company = $user->company;
        abort_if($company === null, 403);

        $assignments = $company->assignments()
            ->withCount('photos')
            ->orderByDesc('performed_on')
            ->orderByDesc('id')
            ->get();
        $onboardingSteps = $user->onboarding_dismissed_at === null
            ? $this->onboardingSteps($user, $company, $assignments)
            : [];
        $showOnboarding = collect($onboardingSteps)->contains(
            fn (array $step): bool => $step['done'] === false,
        );

        return view('assignments.index', [
            'assignments' => $assignments,
            'onTrial' => $company->onTrial(),
            'canManageJobs' => $company->canManageJobs(),
            'companyName' => $company->name,
            'trialEndsAt' => $company->trial_ends_at,
            'showOnboarding' => $showOnboarding,
            'onboardingSteps' => $onboardingSteps,
        ]);
    }

    public function create(): View
    {
        $user = request()->user();
        abort_if($user === null, 403);

        return view('assignments.create', [
            'canManageJobs' => $user->canManageJobs(),
        ]);
    }

    public function store(StoreAssignmentRequest $request, StoreAssignmentPhotos $photos): RedirectResponse
    {
        if (! Gate::allows('create', Assignment::class)) {
            return back()->withInput()->withErrors([
                'trial' => 'Provperioden är slut. Du kan fortfarande öppna rapporter som redan är skapade.',
            ]);
        }

        $assignment = DB::transaction(function () use ($request, $photos): Assignment {
            $assignment = $request->user()->assignments()->create($request->safe()->only([
                'customer_name',
                'address',
                'performed_on',
            ]));

            $photos->handle(
                $assignment,
                $this->uploadedFiles($request, 'before_photos'),
                $this->uploadedFiles($request, 'after_photos'),
            );

            return $assignment;
        });

        return redirect()
            ->route('assignments.show', $assignment)
            ->with('toast', 'Uppdraget är sparat.');
    }

    public function show(Assignment $assignment): View
    {
        Gate::authorize('view', $assignment);
        $assignment->load('photos');

        return view('assignments.show', [
            'assignment' => $assignment,
            'reportUrl' => route('reports.show', ['token' => $assignment->public_token]),
            'canManageJobs' => request()->user()?->canManageJobs() ?? false,
        ]);
    }

    public function storePhotos(StoreAssignmentPhotoRequest $request, Assignment $assignment, StoreAssignmentPhotos $photos): RedirectResponse
    {
        Gate::authorize('update', $assignment);

        if (! $request->user()->canManageJobs()) {
            return back()->withErrors([
                'trial' => 'Provperioden är slut. Du kan fortfarande öppna rapporter som redan är skapade.',
            ]);
        }

        DB::transaction(function () use ($request, $assignment, $photos): void {
            $photos->handle(
                $assignment,
                $this->uploadedFiles($request, 'before_photos'),
                $this->uploadedFiles($request, 'after_photos'),
            );
        });

        return redirect()
            ->route('assignments.show', $assignment)
            ->with('toast', 'Bilderna är uppladdade.');
    }

    public function destroyPhoto(Assignment $assignment, AssignmentPhoto $photo): RedirectResponse
    {
        Gate::authorize('update', $assignment);
        abort_unless($photo->assignment_id === $assignment->id, 404);

        Storage::disk('local')->delete($photo->path);
        $photo->delete();

        return redirect()
            ->route('assignments.show', $assignment)
            ->with('toast', 'Bilden är borttagen.');
    }

    public function share(Request $request, Assignment $assignment): RedirectResponse
    {
        Gate::authorize('view', $assignment);
        $assignment->markReportShared();

        $redirect = back();

        if ($request->boolean('checklist')) {
            $redirect = $redirect->with('toast', 'Länken är kopierad.');
        }

        return $redirect;
    }

    public function dismissOnboarding(): RedirectResponse
    {
        $user = request()->user();
        abort_if($user === null, 403);
        $user->forceFill(['onboarding_dismissed_at' => now()])->save();

        return redirect()->route('assignments.index');
    }

    /**
     * @param  Collection<int, Assignment>  $assignments
     * @return list<array{label: string, done: bool, url: string|null, copyUrl: string|null, shareAction: string|null}>
     */
    private function onboardingSteps(User $user, Company $company, Collection $assignments): array
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
                    fn (Assignment $assignment): bool => $assignment->report_shared_at !== null,
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

    /**
     * @return list<UploadedFile>
     */
    private function uploadedFiles(StoreAssignmentRequest|StoreAssignmentPhotoRequest $request, string $key): array
    {
        $files = $request->file($key, []);

        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (! is_array($files)) {
            return [];
        }

        return array_values(array_filter($files, fn (mixed $file): bool => $file instanceof UploadedFile));
    }
}
