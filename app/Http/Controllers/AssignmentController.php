<?php

namespace App\Http\Controllers;

use App\Actions\CreateAssignmentReportVersion;
use App\Actions\DeleteAssignment;
use App\Actions\DeleteAssignmentPhoto;
use App\Actions\SaveAssignmentCustomer;
use App\Actions\SendAssignmentReportEmail;
use App\Actions\StoreAssignmentPhotos;
use App\CompanyRole;
use App\Http\Requests\StoreAssignmentPhotoRequest;
use App\Http\Requests\StoreAssignmentRequest;
use App\Http\Requests\UpdateAssignmentPhotoPairNotesRequest;
use App\Models\Assignment;
use App\Models\AssignmentEmailAttempt;
use App\Models\AssignmentPhoto;
use App\Models\AssignmentPhotoPair;
use App\Models\AssignmentPlace;
use App\Models\AssignmentReportVersion;
use App\Models\User;
use App\OnboardingChecklist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssignmentController extends Controller
{
    private const ACCESS_PAUSED_MESSAGE = 'Ingen aktiv åtkomst till nya uppdrag. Du kan fortfarande öppna befintliga rapporter.';

    public function index(Request $request, OnboardingChecklist $checklist): View
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $company = $user->company;
        abort_if($company === null, 403);

        $validatedFilters = Validator::make($request->query(), [
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['draft', 'ready', 'sent'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ])->validate();
        $filters = [
            'q' => trim((string) ($validatedFilters['q'] ?? '')),
            'status' => (string) ($validatedFilters['status'] ?? ''),
            'from' => (string) ($validatedFilters['from'] ?? ''),
            'to' => (string) ($validatedFilters['to'] ?? ''),
        ];

        $assignmentQuery = $company->assignments();
        if ($filters['q'] !== '') {
            $search = '%'.$filters['q'].'%';
            $assignmentQuery->where(function (Builder $query) use ($search): void {
                $query->where('customer_name', 'like', $search)
                    ->orWhere('address', 'like', $search);
            });
        }

        if ($filters['status'] !== '') {
            $assignmentQuery->where('status', $filters['status']);
        }

        if ($filters['from'] !== '') {
            $assignmentQuery->whereDate('performed_on', '>=', $filters['from']);
        }

        if ($filters['to'] !== '') {
            $assignmentQuery->whereDate('performed_on', '<=', $filters['to']);
        }

        $assignmentTotal = (clone $assignmentQuery)->count();
        $photoTotal = AssignmentPhoto::query()
            ->whereIn('assignment_id', (clone $assignmentQuery)->select('assignments.id'))
            ->count();
        $assignments = (clone $assignmentQuery)
            ->with(['assignedTo:id,name'])
            ->withCount('photos')
            ->orderByDesc('performed_on')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();
        $readyToSendCount = $company->assignments()
            ->where('status', 'ready')
            ->whereHas('photos')
            ->whereHas('reportVersions', function (Builder $query): void {
                $query->where('status', AssignmentReportVersion::STATUS_READY)
                    ->whereColumn('assignment_report_versions.token', 'assignments.public_token');
            })
            ->count();

        $latestJob = $company->assignments()
            ->withCount('photos')
            ->orderByDesc('performed_on')
            ->orderByDesc('id')
            ->first();
        $jobWithoutPhotos = $company->assignments()
            ->withCount('photos')
            ->whereDoesntHave('photos')
            ->orderByDesc('performed_on')
            ->orderByDesc('id')
            ->first();
        $jobWithPhotos = $company->assignments()
            ->withCount('photos')
            ->whereHas('photos')
            ->orderByDesc('performed_on')
            ->orderByDesc('id')
            ->first();
        $sharedJob = $company->assignments()
            ->withCount('photos')
            ->where(fn (Builder $query) => $query->whereNotNull('link_copied_at')->orWhereNotNull('report_shared_at'))
            ->first();
        $onboardingAssignments = new Collection(
            collect([$latestJob, $jobWithoutPhotos, $jobWithPhotos, $sharedJob])
                ->filter()
                ->unique('id')
                ->values()
                ->all(),
        );
        $onboardingSteps = $checklist->steps($user, $company, $onboardingAssignments);
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
            'filters' => $filters,
            'assignmentTotal' => $assignmentTotal,
            'photoTotal' => $photoTotal,
            'readyToSendCount' => $readyToSendCount,
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
        $company = $user->company;
        abort_if($company === null, 403);

        $savedCustomers = $company->customers()
            ->with(['properties' => fn ($query) => $query->orderBy('id')])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return view('assignments.create', [
            'canManageJobs' => $user->canManageJobs(),
            'savedCustomers' => $savedCustomers,
            'selectedCustomerId' => request()->query('customer_id'),
            'selectedCustomerPropertyId' => request()->query('customer_property_id'),
        ]);
    }

    public function store(
        StoreAssignmentRequest $request,
        StoreAssignmentPhotos $photos,
        SaveAssignmentCustomer $customers,
        CreateAssignmentReportVersion $versions,
        OnboardingChecklist $checklist,
    ): JsonResponse|RedirectResponse {
        if (! Gate::allows('create', Assignment::class)) {
            return $this->pausedAccess(withInput: true);
        }

        $user = $request->user();
        $wasComplete = $user instanceof User && $checklist->isComplete($user);

        $assignment = DB::transaction(function () use ($request, $photos, $customers, $versions): Assignment {
            $user = $request->user();
            abort_unless($user instanceof User, 403);
            $customerData = $request->safe()->only(['customer_name', 'customer_email', 'address']);
            $assignment = $request->user()->assignments()->create($request->safe()->only([
                'customer_name',
                'customer_email',
                'address',
                'performed_on',
                'performed_time',
                'performed_end_time',
            ]));
            $assignment->forceFill(['assigned_to_user_id' => $user->id])->save();

            $savedCustomer = $customers->handle(
                $user,
                $customerData['customer_name'],
                $customerData['customer_email'] ?? null,
                $customerData['address'],
                $request->filled('customer_id') ? (int) $request->validated('customer_id') : null,
                $request->filled('customer_property_id') ? (int) $request->validated('customer_property_id') : null,
            );
            $assignment->forceFill([
                'customer_id' => $savedCustomer['customer']->id,
                'customer_property_id' => $savedCustomer['property']->id,
            ])->save();

            $before = $this->uploadedFiles($request, 'before_photos');
            $after = $this->uploadedFiles($request, 'after_photos');

            if ($before !== [] || $after !== []) {
                $photos->handle(
                    $assignment,
                    $before,
                    $after,
                    (string) ($request->validated('place_name') ?: 'Övrigt'),
                    $user,
                    $this->uploadedIds($request, 'before_upload_ids'),
                    $this->uploadedIds($request, 'after_upload_ids'),
                );
            }

            if ($assignment->photos()->exists()) {
                $versions->create($assignment, $user, preserveCurrentToken: true);
            }

            return $assignment;
        });

        if ($user instanceof User && $checklist->finishedDuring($user, $wasComplete)) {
            return $this->jsonOrRedirect(route('assignments.index'), OnboardingChecklist::COMPLETED_MESSAGE);
        }

        return $this->jsonOrRedirect(route('assignments.show', $assignment), 'Uppdraget är sparat.');
    }

    public function show(
        Assignment $assignment,
        SendAssignmentReportEmail $sendReportEmail,
        CreateAssignmentReportVersion $versions,
    ): View {
        Gate::authorize('view', $assignment);
        $assignment->load([
            'user:id,name,email',
            'assignedTo:id,name,email',
            'reviewer:id,name,email',
            'reviewedBy:id,name,email',
            'photos.uploadedBy:id,name,email',
            'places.photoPairs.photos.uploadedBy:id,name,email',
        ]);
        $latestReportVersion = $assignment->reportVersions()->with(['createdBy:id,name,email', 'firstSentBy:id,name,email'])->first();
        $latestEmailAttempt = $assignment->emailAttempts()->with('user:id,name,email')->first();
        $user = request()->user();
        $canManageJobs = $user instanceof User && $user->canManageJobs();
        $company = $assignment->company;
        $reportToken = $latestReportVersion?->token ?? $assignment->public_token;
        $hasUnpublishedDraftChanges = $versions->hasUnpublishedDraftChanges($assignment, $latestReportVersion);
        $currentVersionIsReady = $latestReportVersion instanceof AssignmentReportVersion
            && $latestReportVersion->token === $assignment->public_token
            && $latestReportVersion->status === AssignmentReportVersion::STATUS_READY
            && $assignment->photos->isNotEmpty()
            && ! $hasUnpublishedDraftChanges;
        $canManageTeamAssignment = $user instanceof User
            && $user->isOwner()
            && $canManageJobs
            && $company?->allowsTeamFeatures() === true;
        $ownerId = $company?->users()->where('role', CompanyRole::Owner)->value('id');
        $canReviewAssignment = $user instanceof User
            && $canManageJobs
            && $company?->allowsTeamFeatures() === true
            && ($user->isOwner() || (int) $assignment->reviewer_user_id === (int) $user->id)
            && $currentVersionIsReady;
        $canHandoffAssignment = $user instanceof User
            && $canManageJobs
            && $company?->allowsTeamFeatures() === true
            && $assignment->assigned_to_user_id !== null
            && (int) $assignment->assigned_to_user_id !== (int) $ownerId
            && ($user->isOwner() || (int) $assignment->assigned_to_user_id === (int) $user->id);
        $teamMembers = $canManageTeamAssignment
            ? $company->users()->select(['id', 'name', 'email'])->orderBy('name')->orderBy('id')->get()
            : collect();
        $canViewCustomerResponses = $user instanceof User && $user->isOwner();
        $customerResponses = $canViewCustomerResponses
            ? $assignment->reportResponses()
                ->with('reportVersion:id,version_number,token,snapshot')
                ->limit(10)
                ->get()
                ->map(function ($response): array {
                    $snapshot = $response->reportVersion?->snapshot ?? [];
                    $place = $snapshot['places'][$response->place_index] ?? null;
                    $pair = is_array($place) ? ($place['pairs'][$response->pair_index] ?? null) : null;
                    $placeIndex = $response->place_index;
                    $pairIndex = $response->pair_index;
                    $reportUrl = $response->reportVersion === null || blank($response->reportVersion->token)
                        ? null
                        : route('reports.show', ['token' => $response->reportVersion->token]);

                    if ($reportUrl !== null && $placeIndex !== null && $pairIndex !== null) {
                        $reportUrl .= '#report-pair-'.$placeIndex.'-'.$pairIndex;
                    }

                    return [
                        'type' => $response->type,
                        'message' => $response->message,
                        'placeName' => is_array($place) ? (string) ($place['name'] ?? '') : null,
                        'pairLabel' => is_array($pair)
                            ? (filled($pair['label'] ?? null) ? (string) $pair['label'] : 'Bildpar '.((int) $pairIndex + 1))
                            : null,
                        'placeIndex' => $placeIndex,
                        'pairIndex' => $pairIndex,
                        'reportUrl' => $reportUrl,
                        'versionNumber' => $response->reportVersion?->version_number,
                        'createdAt' => $response->created_at,
                        'readAt' => $response->read_at,
                    ];
                })
                ->all()
            : [];

        return view('assignments.show', [
            'assignment' => $assignment,
            'reportUrl' => route('reports.show', ['token' => $reportToken]),
            'latestReportVersion' => $latestReportVersion,
            'latestReportPdfUrl' => $latestReportVersion instanceof AssignmentReportVersion
                ? route('reports.pdf', ['token' => $latestReportVersion->token])
                : null,
            'hasUnpublishedDraftChanges' => $hasUnpublishedDraftChanges,
            'placeName' => $assignment->places->first()?->name ?? 'Övrigt',
            'canCreateReportVersion' => $canManageJobs && $assignment->photos->isNotEmpty(),
            'canManageJobs' => $canManageJobs,
            'customerEmail' => $assignment->customer_email,
            'latestEmailAttempt' => $latestEmailAttempt === null
                ? null
                : $this->emailAttemptViewData($latestEmailAttempt),
            'currentVersionIsReady' => $currentVersionIsReady,
            'assignmentIsMissingAfterPhoto' => $assignment->isMissingAfterPhoto(),
            'canSendAssignmentEmail' => $user instanceof User
                && $user->hasVerifiedEmail()
                && $canManageJobs
                && $assignment->photos->isNotEmpty()
                && $sendReportEmail->canStartAnotherAttempt($latestEmailAttempt),
            'canManageTeamAssignment' => $canManageTeamAssignment,
            'canReviewAssignment' => $canReviewAssignment,
            'canHandoffAssignment' => $canHandoffAssignment,
            'isReviewed' => $assignment->reviewed_at !== null,
            'teamMembers' => $teamMembers,
            'canViewCustomerResponses' => $canViewCustomerResponses,
            'customerResponses' => $customerResponses,
            'canDeleteAssignment' => $user instanceof User && $user->can('delete', $assignment),
        ]);
    }

    public function destroy(Assignment $assignment, DeleteAssignment $deleteAssignment): RedirectResponse
    {
        Gate::authorize('delete', $assignment);

        $deleteAssignment->handle($assignment);

        return redirect()
            ->route('assignments.index')
            ->with('toast', 'Uppdraget är raderat.');
    }

    public function storePhotos(
        StoreAssignmentPhotoRequest $request,
        Assignment $assignment,
        StoreAssignmentPhotos $photos,
        CreateAssignmentReportVersion $versions,
        OnboardingChecklist $checklist,
    ): RedirectResponse|JsonResponse {
        Gate::authorize('update', $assignment);

        $user = $request->user();
        if (! $user instanceof User || ! $user->canManageJobs()) {
            return $this->pausedAccess(withInput: false, status: 403);
        }

        $wasComplete = $checklist->isComplete($user);

        $result = DB::transaction(function () use ($request, $assignment, $photos, $versions, $user): array {
            $lockedAssignment = Assignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $before = $this->uploadedFiles($request, 'before_photos');
            $after = $this->uploadedFiles($request, 'after_photos');
            $pairId = $request->validated('assignment_photo_pair_id');

            if ($pairId !== null) {
                $pair = AssignmentPhotoPair::query()
                    ->whereKey($pairId)
                    ->whereHas('place', fn (Builder $query) => $query->where('assignment_id', $lockedAssignment->id))
                    ->with('place')
                    ->firstOrFail();
                $uploadResult = $photos->addToPair(
                    $lockedAssignment,
                    $pair->place,
                    $pair,
                    $before,
                    $after,
                    $user,
                    $this->uploadedIds($request, 'before_upload_ids'),
                    $this->uploadedIds($request, 'after_upload_ids'),
                );
            } else {
                $uploadResult = $photos->handle(
                    $lockedAssignment,
                    $before,
                    $after,
                    (string) ($request->validated('place_name') ?: 'Övrigt'),
                    $user,
                    $this->uploadedIds($request, 'before_upload_ids'),
                    $this->uploadedIds($request, 'after_upload_ids'),
                );
            }

            if ($uploadResult['uploaded'] > 0) {
                $lockedAssignment->markDraftChanged();
            }

            if (
                ! $request->boolean('defer_report_version')
                && $lockedAssignment->photos()->exists()
                && $lockedAssignment->reportVersions()->doesntExist()
            ) {
                $versions->create($lockedAssignment, $user, preserveCurrentToken: true);
            }

            return $uploadResult;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $result['uploaded'] > 0 ? 'Bilderna är uppladdade.' : 'Bilderna var redan uppladdade.',
                'uploaded' => $result['uploaded'],
                'duplicates' => $result['duplicates'],
                'uploads' => $result['uploads'],
            ]);
        }

        if ($checklist->finishedDuring($user, $wasComplete)) {
            return $this->jsonOrRedirect(route('assignments.index'), OnboardingChecklist::COMPLETED_MESSAGE);
        }

        return $this->jsonOrRedirect(route('assignments.show', $assignment), 'Bilderna är uppladdade.');
    }

    public function destroyPhoto(Assignment $assignment, AssignmentPhoto $photo, DeleteAssignmentPhoto $deletePhoto): RedirectResponse
    {
        Gate::authorize('update', $assignment);
        abort_unless($photo->assignment_id === $assignment->id, 404);

        $user = request()->user();

        if (! $user instanceof User || ! $user->canManageJobs()) {
            return back()->withErrors([
                'trial' => self::ACCESS_PAUSED_MESSAGE,
            ]);
        }

        $deletePhoto->handle($assignment, $photo);

        return redirect()
            ->route('assignments.show', $assignment)
            ->with('toast', 'Bilden är borttagen.');
    }

    public function photoPreview(Assignment $assignment, AssignmentPhoto $photo): BinaryFileResponse
    {
        Gate::authorize('view', $assignment);
        abort_unless($photo->assignment_id === $assignment->id, 404);

        $absolutePath = Storage::disk('local')->path($photo->path);
        abort_unless(is_file($absolutePath), 404);

        $response = response()->file($absolutePath, [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
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

    public function edit(Assignment $assignment): View
    {
        Gate::authorize('update', $assignment);
        $user = request()->user();
        abort_if(! $user instanceof User, 403);

        return view('assignments.edit', [
            'assignment' => $assignment->load('customer', 'customerProperty'),
            'placeName' => $assignment->places()->first()?->name ?? 'Övrigt',
            'canManageJobs' => $user->canManageJobs(),
        ]);
    }

    public function update(
        StoreAssignmentRequest $request,
        Assignment $assignment,
        StoreAssignmentPhotos $photos,
        SaveAssignmentCustomer $customers,
    ): RedirectResponse {
        Gate::authorize('update', $assignment);
        $user = $request->user();

        if (! $user instanceof User || ! $user->canManageJobs()) {
            return back()->withInput()->withErrors(['trial' => self::ACCESS_PAUSED_MESSAGE]);
        }

        DB::transaction(function () use ($request, $assignment, $photos, $customers, $user): void {
            $lockedAssignment = Assignment::query()
                ->whereKey($assignment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $data = $request->safe()->only([
                'customer_name',
                'customer_email',
                'address',
                'performed_on',
                'performed_time',
                'performed_end_time',
            ]);
            $savedCustomer = $customers->handle(
                $user,
                $data['customer_name'],
                $data['customer_email'] ?? null,
                $data['address'],
                $request->filled('customer_id') ? (int) $request->validated('customer_id') : null,
                $request->filled('customer_property_id') ? (int) $request->validated('customer_property_id') : null,
            );

            $lockedAssignment->forceFill([
                ...$data,
                'customer_id' => $savedCustomer['customer']->id,
                'customer_property_id' => $savedCustomer['property']->id,
            ]);
            $draftChanged = $lockedAssignment->isDirty([
                'customer_name',
                'customer_email',
                'address',
                'performed_on',
                'performed_time',
                'performed_end_time',
                'customer_id',
                'customer_property_id',
            ]);
            $lockedAssignment->save();

            $before = $this->uploadedFiles($request, 'before_photos');
            $after = $this->uploadedFiles($request, 'after_photos');

            if ($before !== [] || $after !== []) {
                $uploadResult = $photos->handle(
                    $lockedAssignment,
                    $before,
                    $after,
                    (string) ($request->validated('place_name') ?: 'Övrigt'),
                    $user,
                    $this->uploadedIds($request, 'before_upload_ids'),
                    $this->uploadedIds($request, 'after_upload_ids'),
                );

                $draftChanged = $draftChanged || $uploadResult['uploaded'] > 0;
            }

            if ($draftChanged) {
                $lockedAssignment->markDraftChanged();
            }
        });

        return redirect()->route('assignments.show', $assignment)->with('toast', 'Uppdraget är sparat som utkast.');
    }

    public function storeVersion(
        Assignment $assignment,
        CreateAssignmentReportVersion $versions,
    ): RedirectResponse {
        Gate::authorize('update', $assignment);
        $user = request()->user();
        abort_if(! $user instanceof User, 403);

        if (! $user->canManageJobs()) {
            return back()->withErrors(['trial' => self::ACCESS_PAUSED_MESSAGE]);
        }

        if (! $assignment->photos()->exists()) {
            return back()->withErrors(['report' => 'Lägg till minst en bild innan du skapar en rapportversion.']);
        }

        $versions->create($assignment, $user);

        return redirect()->route('assignments.show', $assignment)->with('toast', 'En ny rapportversion är skapad.');
    }

    public function updatePhotoPairNotes(
        UpdateAssignmentPhotoPairNotesRequest $request,
        Assignment $assignment,
        AssignmentPlace $place,
        AssignmentPhotoPair $pair,
    ): RedirectResponse {
        Gate::authorize('update', $assignment);
        abort_unless($place->assignment_id === $assignment->id, 404);
        abort_unless($pair->assignment_place_id === $place->id, 404);

        $user = $request->user();
        if (! $user instanceof User || ! $user->canManageJobs()) {
            return back()->withInput()->withErrors(['trial' => self::ACCESS_PAUSED_MESSAGE]);
        }

        DB::transaction(function () use ($request, $assignment, $place, $pair): void {
            $lockedAssignment = Assignment::query()
                ->whereKey($assignment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedPair = AssignmentPhotoPair::query()
                ->whereKey($pair->getKey())
                ->where('assignment_place_id', $place->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedPair->forceFill($request->validated())->save();
            $lockedAssignment->markDraftChanged();
        });

        return back()->with('toast', 'Kommentarerna är sparade som utkast.');
    }

    public function reorderPlaces(Request $request, Assignment $assignment): RedirectResponse
    {
        Gate::authorize('update', $assignment);
        $user = $request->user();
        abort_if(! $user instanceof User, 403);
        abort_unless($user->canManageJobs(), 403);

        $ids = $request->validate([
            'place_ids' => ['required', 'array', 'min:1'],
            'place_ids.*' => ['required', 'integer'],
        ])['place_ids'];
        DB::transaction(function () use ($assignment, $ids): void {
            $lockedAssignment = Assignment::query()
                ->whereKey($assignment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->reorderScopedRows($lockedAssignment->places(), $ids, 'place_ids');
            $lockedAssignment->markDraftChanged();
        }, attempts: 3);

        return back()->with('toast', 'Platsernas ordning är sparad.');
    }

    public function reorderPhotoPairs(Request $request, Assignment $assignment, AssignmentPlace $place): RedirectResponse
    {
        Gate::authorize('update', $assignment);
        abort_unless($place->assignment_id === $assignment->id, 404);
        $user = $request->user();
        abort_if(! $user instanceof User, 403);
        abort_unless($user->canManageJobs(), 403);

        $ids = $request->validate([
            'pair_ids' => ['required', 'array', 'min:1'],
            'pair_ids.*' => ['required', 'integer'],
        ])['pair_ids'];
        DB::transaction(function () use ($assignment, $place, $ids): void {
            $lockedAssignment = Assignment::query()
                ->whereKey($assignment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedPlace = AssignmentPlace::query()
                ->whereKey($place->getKey())
                ->where('assignment_id', $lockedAssignment->getKey())
                ->firstOrFail();
            $this->reorderScopedRows($lockedPlace->photoPairs(), $ids, 'pair_ids');
            $lockedAssignment->markDraftChanged();
        }, attempts: 3);

        return back()->with('toast', 'Bildparens ordning är sparad.');
    }

    /**
     * @param  list<int|string>  $ids
     */
    private function reorderScopedRows(HasMany $relation, array $ids, string $inputKey): void
    {
        $normalizedIds = array_map('intval', $ids);
        $currentIds = $relation->pluck('id')->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();
        $submittedIds = collect($normalizedIds)->sort()->values()->all();

        if ($normalizedIds === [] || count($normalizedIds) !== count(array_unique($normalizedIds)) || $submittedIds !== $currentIds) {
            throw ValidationException::withMessages([$inputKey => 'Välj alla rader i samma lista och försök igen.']);
        }

        DB::transaction(function () use ($relation, $normalizedIds): void {
            $rows = $relation->get()->keyBy('id');
            $offset = ((int) $rows->max('sort_order')) + count($normalizedIds) + 1;

            foreach ($normalizedIds as $index => $id) {
                $rows->get($id)->forceFill(['sort_order' => $offset + $index])->save();
            }

            foreach ($normalizedIds as $index => $id) {
                $rows->get($id)->forceFill(['sort_order' => $index + 1])->save();
            }
        });
    }

    private function pausedAccess(bool $withInput, int $status = 422): JsonResponse|RedirectResponse
    {
        if (request()->expectsJson()) {
            return response()->json([
                'message' => self::ACCESS_PAUSED_MESSAGE,
                'errors' => [
                    'trial' => [self::ACCESS_PAUSED_MESSAGE],
                ],
            ], $status);
        }

        $redirect = $withInput ? back()->withInput() : back();

        return $redirect->withErrors([
            'trial' => self::ACCESS_PAUSED_MESSAGE,
        ]);
    }

    private function jsonOrRedirect(string $url, string $toast): JsonResponse|RedirectResponse
    {
        if (request()->expectsJson()) {
            session()->flash('toast', $toast);

            return response()->json(['redirect' => $url]);
        }

        return redirect()->to($url)->with('toast', $toast);
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

    /**
     * @return list<string>
     */
    private function uploadedIds(StoreAssignmentRequest|StoreAssignmentPhotoRequest $request, string $key): array
    {
        $ids = $request->input($key, []);

        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_filter($ids, fn (mixed $id): bool => is_string($id)));
    }

    /**
     * @return array{status: string, recipientEmail: string, attemptedAt: Carbon|null, sentAt: Carbon|null, failedAt: Carbon|null, unknownAt: Carbon|null}
     */
    private function emailAttemptViewData(AssignmentEmailAttempt $attempt): array
    {
        $status = $attempt->status;
        $unknownAt = $attempt->unknown_at;

        if (
            $status === AssignmentEmailAttempt::STATUS_SENDING
            && $attempt->created_at?->lte(now()->subMinutes(SendAssignmentReportEmail::SENDING_TIMEOUT_MINUTES))
        ) {
            $status = AssignmentEmailAttempt::STATUS_UNKNOWN;
            $unknownAt = $attempt->created_at;
        }

        return [
            'status' => $status,
            'recipientEmail' => $attempt->recipient_email,
            'attemptedAt' => $attempt->created_at,
            'sentAt' => $attempt->sent_at,
            'failedAt' => $attempt->failed_at,
            'unknownAt' => $unknownAt,
        ];
    }
}
