<?php

namespace App\Http\Controllers;

use App\Actions\StoreAssignmentPhotos;
use App\Http\Requests\StoreAssignmentPhotoRequest;
use App\Http\Requests\StoreAssignmentRequest;
use App\Models\Assignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function index(): View
    {
        $user = request()->user();
        abort_if($user === null, 403);

        $company = $user->company;
        abort_if($company === null, 403);

        return view('assignments.index', [
            'assignments' => $company->assignments()
                ->withCount('photos')
                ->orderByDesc('performed_on')
                ->orderByDesc('id')
                ->get(),
            'onTrial' => $company->onTrial(),
            'companyName' => $company->name,
            'trialEndsAt' => $company->trial_ends_at,
        ]);
    }

    public function create(): View
    {
        $user = request()->user();
        abort_if($user === null, 403);

        return view('assignments.create', [
            'onTrial' => $user->onTrial(),
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

        return redirect()->route('assignments.show', $assignment);
    }

    public function show(Assignment $assignment): View
    {
        Gate::authorize('view', $assignment);
        $assignment->load('photos');

        return view('assignments.show', [
            'assignment' => $assignment,
            'reportUrl' => route('reports.show', ['token' => $assignment->public_token]),
            'onTrial' => request()->user()?->onTrial() ?? false,
        ]);
    }

    public function storePhotos(StoreAssignmentPhotoRequest $request, Assignment $assignment, StoreAssignmentPhotos $photos): RedirectResponse
    {
        Gate::authorize('update', $assignment);

        if (! $request->user()->onTrial()) {
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

        return redirect()->route('assignments.show', $assignment);
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
