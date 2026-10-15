<?php

namespace App\Actions;

use App\Models\Company;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CompanyLogoResponse
{
    public function __invoke(Company $company): BinaryFileResponse
    {
        abort_unless($company->allowsTeamFeatures() && filled($company->logo_path), 404);

        $absolutePath = Storage::disk('local')->path($company->logo_path);
        abort_unless(is_file($absolutePath), 404);

        $extension = strtolower(pathinfo($company->logo_path, PATHINFO_EXTENSION));
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
            'Cache-Control' => 'private, max-age=3600',
        ];

        if ($contentType === 'image/svg+xml') {
            $headers['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'; sandbox";
        }

        return response()->file($absolutePath, $headers);
    }
}
