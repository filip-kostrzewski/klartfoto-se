<?php

namespace App\Http\Controllers;

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
            ->with(['photos', 'user'])
            ->where('public_token', $token)
            ->firstOrFail();

        return response()
            ->view('reports.public', [
                'assignment' => $assignment,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow');
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
