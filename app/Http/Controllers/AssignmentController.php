<?php

namespace App\Http\Controllers;

use App\Actions\StoreAssignmentPhotos;
use App\Http\Requests\StoreAssignmentPhotoRequest;
use App\Http\Requests\StoreAssignmentRequest;
use App\Models\Assignment;
use App\Models\AssignmentPhoto;
use App\Models\User;
use App\OnboardingChecklist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    private const ACCESS_PAUSED_MESSAGE = 'Ingen aktiv åtkomst till nya uppdrag. Du kan fortfarande öppna befintliga rapporter.';

    public function index(OnboardingChecklist $checklist): View
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
        $onboardingSteps = $checklist->steps($user, $company, $assignments);
        $onboardingComplete = collect($onboardingSteps)->every(
            fn (array $step): bool => $step['done'] === true,
        );
        $dismissed = $user->onboarding_dismissed_at !== null;
        $celebrate = (int) session('onboarding_celebrate') === $user->id;

        if ($celebrate) {
            session()->forget('onboarding_celebrate');
        }

        return view('assignments.index', [
            'assignments' => $assignments,
            'onTrial' => $company->onTrial(),
            'canManageJobs' => $user->canManageJobs(),
            'companyName' => $company->name,
            'trialEndsAt' => $company->trial_ends_at,
            'showOnboarding' => $celebrate || (! $dismissed && ! $onboardingComplete),
            'showOnboardingRestore' => ! $celebrate && $dismissed && ! $onboardingComplete,
            'onboardingCelebrate' => $celebrate,
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

    public function store(StoreAssignmentRequest $request, StoreAssignmentPhotos $photos, OnboardingChecklist $checklist): RedirectResponse
    {
        if (! Gate::allows('create', Assignment::class)) {
            return back()->withInput()->withErrors([
                'trial' => self::ACCESS_PAUSED_MESSAGE,
            ]);
        }

        $user = $request->user();
        $wasComplete = $user instanceof User && $checklist->isComplete($user);

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

        if ($user instanceof User && $checklist->finishedDuring($user, $wasComplete)) {
            return redirect()
                ->route('assignments.index')
                ->with('toast', OnboardingChecklist::COMPLETED_MESSAGE);
        }

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

    public function storePhotos(StoreAssignmentPhotoRequest $request, Assignment $assignment, StoreAssignmentPhotos $photos, OnboardingChecklist $checklist): RedirectResponse
    {
        Gate::authorize('update', $assignment);

        $user = $request->user();
        if (! $user instanceof User || ! $user->canManageJobs()) {
            return back()->withErrors([
                'trial' => self::ACCESS_PAUSED_MESSAGE,
            ]);
        }

        $wasComplete = $checklist->isComplete($user);

        DB::transaction(function () use ($request, $assignment, $photos): void {
            $photos->handle(
                $assignment,
                $this->uploadedFiles($request, 'before_photos'),
                $this->uploadedFiles($request, 'after_photos'),
            );
        });

        if ($checklist->finishedDuring($user, $wasComplete)) {
            return redirect()
                ->route('assignments.index')
                ->with('toast', OnboardingChecklist::COMPLETED_MESSAGE);
        }

        return redirect()
            ->route('assignments.show', $assignment)
            ->with('toast', 'Bilderna är uppladdade.');
    }

    public function destroyPhoto(Assignment $assignment, AssignmentPhoto $photo): RedirectResponse
    {
        Gate::authorize('update', $assignment);
        abort_unless($photo->assignment_id === $assignment->id, 404);

        $user = request()->user();

        if (! $user instanceof User || ! $user->canManageJobs()) {
            return back()->withErrors([
                'trial' => self::ACCESS_PAUSED_MESSAGE,
            ]);
        }

        Storage::disk('local')->delete($photo->path);
        $photo->delete();

        return redirect()
            ->route('assignments.show', $assignment)
            ->with('toast', 'Bilden är borttagen.');
    }

    public function share(Request $request, Assignment $assignment, OnboardingChecklist $checklist): RedirectResponse
    {
        Gate::authorize('view', $assignment);

        $user = $request->user();
        $wasComplete = $user instanceof User && $checklist->isComplete($user);
        $assignment->markLinkCopied();

        if ($user instanceof User && $checklist->finishedDuring($user, $wasComplete)) {
            return redirect()
                ->route('assignments.index')
                ->with('toast', OnboardingChecklist::COMPLETED_MESSAGE);
        }

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

    public function restoreOnboarding(): RedirectResponse
    {
        $user = request()->user();
        abort_if($user === null, 403);
        $user->forceFill(['onboarding_dismissed_at' => null])->save();

        return redirect()->route('assignments.index');
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
