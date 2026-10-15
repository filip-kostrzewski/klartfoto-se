<?php

namespace App\Http\Controllers;

use App\Actions\CompanyLogoResponse;
use App\Models\Assignment;
use App\Models\AssignmentPhoto;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicReportController extends Controller
{
    public function show(string $token): Response
    {
        $assignment = Assignment::query()
            ->with(['photos', 'company'])
            ->where('public_token', $token)
            ->firstOrFail();

        $company = $assignment->company;
        $logoUrl = $company !== null && $company->logo_path !== null && $company->allowsTeamFeatures()
            ? route('reports.logo', ['token' => $assignment->public_token], false)
            : null;

        return response()
            ->view('reports.public', [
                'assignment' => $assignment,
                'companyName' => $company?->name ?? '',
                'logoUrl' => $logoUrl,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function logo(string $token, CompanyLogoResponse $response): BinaryFileResponse
    {
        $assignment = Assignment::query()->with('company')->where('public_token', $token)->firstOrFail();
        abort_if($assignment->company === null, 404);

        return $response($assignment->company);
    }

    public function photo(string $token, AssignmentPhoto $photo): BinaryFileResponse
    {
        $assignment = Assignment::query()->where('public_token', $token)->firstOrFail();
        abort_unless($photo->assignment_id === $assignment->id, 404);

        $absolutePath = Storage::disk('local')->path($photo->path);
        abort_unless(is_file($absolutePath), 404);

        return response()->file($absolutePath, [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
