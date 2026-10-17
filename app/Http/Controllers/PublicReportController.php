<?php

namespace App\Http\Controllers;

use App\Actions\CompanyLogoResponse;
use App\Actions\GenerateAssignmentReportPdf;
use App\Http\Requests\StorePublicReportResponseRequest;
use App\Models\Assignment;
use App\Models\AssignmentPhoto;
use App\Models\AssignmentReportResponse;
use App\Models\AssignmentReportVersion;
use App\Models\AssignmentReportVersionAsset;
use App\PhotoPhase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicReportController extends Controller
{
    public function pdf(string $token, GenerateAssignmentReportPdf $pdf): Response
    {
        $version = AssignmentReportVersion::query()
            ->where('token', $token)
            ->firstOrFail();
        $contents = $pdf->handle($version);

        if ($contents === null) {
            return response()
                ->view('reports.pdf-unavailable', [
                    'reportUrl' => route('reports.show', ['token' => $version->token], false),
                ], 503)
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        $response = response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="klartfoto-rapport-v'.$version->version_number.'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public function storeResponse(StorePublicReportResponseRequest $request, string $token): RedirectResponse
    {
        $version = AssignmentReportVersion::query()
            ->with('assignment')
            ->where('token', $token)
            ->firstOrFail();
        $assignment = $version->assignment;
        abort_unless($assignment instanceof Assignment && $assignment->company_id !== null, 404);
        $data = $request->validated();

        if ($data['response_type'] === AssignmentReportResponse::TYPE_COMMENT) {
            $place = $version->snapshot['places'][$data['place_index']] ?? null;
            $pair = is_array($place) ? ($place['pairs'][$data['pair_index']] ?? null) : null;

            if (! is_array($pair)) {
                throw ValidationException::withMessages([
                    'pair_index' => 'Välj ett bildpar i rapporten.',
                ]);
            }

            $version->responses()->create([
                'company_id' => $assignment->company_id,
                'assignment_id' => $assignment->id,
                'type' => AssignmentReportResponse::TYPE_COMMENT,
                'response_key' => Str::uuid()->toString(),
                'place_index' => (int) $data['place_index'],
                'pair_index' => (int) $data['pair_index'],
                'message' => $data['message'],
            ]);

            return redirect()
                ->route('reports.show', ['token' => $token])
                ->with('report_response_status', 'Din kommentar har skickats till företaget.');
        }

        $response = $version->responses()->firstOrCreate(
            ['response_key' => 'received'],
            [
                'company_id' => $assignment->company_id,
                'assignment_id' => $assignment->id,
                'type' => AssignmentReportResponse::TYPE_RECEIVED,
            ],
        );

        return redirect()
            ->route('reports.show', ['token' => $token])
            ->with(
                'report_response_status',
                $response->wasRecentlyCreated ? 'Tack, ditt svar har sparats.' : 'Ditt svar är redan registrerat.',
            );
    }

    public function show(string $token): Response
    {
        $version = AssignmentReportVersion::query()
            ->with('assets')
            ->where('token', $token)
            ->first();

        if ($version instanceof AssignmentReportVersion) {
            $photoAssets = $version->assets
                ->where('type', AssignmentReportVersionAsset::TYPE_PHOTO)
                ->keyBy(fn (AssignmentReportVersionAsset $asset): string => $asset->source_photo_id.':'.$asset->phase);
            $photoPairs = [];

            foreach ($version->snapshot['places'] ?? [] as $place) {
                foreach ($place['pairs'] ?? [] as $pair) {
                    $before = isset($pair['before'])
                        ? $photoAssets->get($pair['before'].':'.PhotoPhase::Before->value)
                        : null;
                    $after = isset($pair['after'])
                        ? $photoAssets->get($pair['after'].':'.PhotoPhase::After->value)
                        : null;
                    $photoPairs[] = [
                        'placeName' => (string) ($place['name'] ?? 'Övrigt'),
                        'label' => $pair['label'] ?? null,
                        'customerComment' => $pair['customer_comment'] ?? null,
                        'beforeUrl' => $before instanceof AssignmentReportVersionAsset
                            ? route('reports.version-assets.show', ['token' => $token, 'asset' => $before], false)
                            : null,
                        'afterUrl' => $after instanceof AssignmentReportVersionAsset
                            ? route('reports.version-assets.show', ['token' => $token, 'asset' => $after], false)
                            : null,
                    ];
                }
            }

            $logoUrl = $version->assets->contains(
                fn (AssignmentReportVersionAsset $asset): bool => $asset->type === AssignmentReportVersionAsset::TYPE_LOGO,
            ) ? route('reports.logo', ['token' => $token], false) : null;
            $latestVersion = AssignmentReportVersion::query()
                ->where('assignment_id', $version->assignment_id)
                ->orderByDesc('version_number')
                ->orderByDesc('id')
                ->first();
            $newerVersionUrl = $latestVersion instanceof AssignmentReportVersion && $latestVersion->isNot($version)
                ? route('reports.show', ['token' => $latestVersion->token], false)
                : null;

            return response()
                ->view('reports.version', [
                    'snapshot' => $version->snapshot,
                    'photoPairs' => $photoPairs,
                    'logoUrl' => $logoUrl,
                    'versionCreatedAt' => $version->created_at,
                    'pdfUrl' => route('reports.pdf', ['token' => $token], false),
                    'newerVersionUrl' => $newerVersionUrl,
                ])
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        $assignment = Assignment::query()
            ->with(['photos.uploadedBy', 'places.photoPairs.photos.uploadedBy', 'company'])
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
                'companyPhone' => $company?->reportPhone(),
                'companyFooter' => $company?->reportFooter() ?? '',
                'logoUrl' => $logoUrl,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function logo(string $token, CompanyLogoResponse $response): BinaryFileResponse
    {
        $version = AssignmentReportVersion::query()->with('assets')->where('token', $token)->first();

        if ($version instanceof AssignmentReportVersion) {
            $asset = $version->assets->first(
                fn (AssignmentReportVersionAsset $candidate): bool => $candidate->type === AssignmentReportVersionAsset::TYPE_LOGO,
            );

            abort_unless($asset instanceof AssignmentReportVersionAsset, 404);

            return $this->versionAssetResponse($asset);
        }

        $assignment = Assignment::query()->with('company')->where('public_token', $token)->firstOrFail();
        abort_if($assignment->company === null, 404);

        return $response($assignment->company);
    }

    public function photo(string $token, int $photo): BinaryFileResponse
    {
        $version = AssignmentReportVersion::query()->where('token', $token)->first();

        if ($version instanceof AssignmentReportVersion) {
            $asset = $version->photoAssets()
                ->where('source_photo_id', $photo)
                ->first();

            abort_unless($asset instanceof AssignmentReportVersionAsset, 404);

            return $this->versionAssetResponse($asset);
        }

        $assignment = Assignment::query()->where('public_token', $token)->firstOrFail();
        $assignmentPhoto = AssignmentPhoto::query()
            ->whereKey($photo)
            ->where('assignment_id', $assignment->id)
            ->firstOrFail();

        $absolutePath = Storage::disk('local')->path($assignmentPhoto->path);
        abort_unless(is_file($absolutePath), 404);

        return $this->privateFile($absolutePath, [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    public function versionAsset(string $token, AssignmentReportVersionAsset $asset): BinaryFileResponse
    {
        abort_unless(
            $asset->type === AssignmentReportVersionAsset::TYPE_PHOTO
                && $asset->reportVersion()->where('token', $token)->exists(),
            404,
        );

        return $this->versionAssetResponse($asset);
    }

    private function versionAssetResponse(AssignmentReportVersionAsset $asset): BinaryFileResponse
    {
        $absolutePath = Storage::disk('local')->path($asset->path);
        abort_unless(is_file($absolutePath), 404);

        $extension = strtolower(pathinfo($asset->path, PATHINFO_EXTENSION));
        $contentType = match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            default => null,
        };
        abort_if($contentType === null, 404);

        $headers = [
            'Content-Type' => $contentType,
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ];

        if ($contentType === 'image/svg+xml') {
            $headers['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'; sandbox";
        }

        return $this->privateFile($absolutePath, $headers);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function privateFile(string $absolutePath, array $headers): BinaryFileResponse
    {
        $response = response()->file($absolutePath, $headers);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
