<?php

namespace App\Actions;

use App\Models\AssignmentReportVersion;
use App\Models\AssignmentReportVersionAsset;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class GenerateAssignmentReportPdf
{
    public function handle(AssignmentReportVersion $version): ?string
    {
        $cacheKey = 'assignment-report-pdf:'.$version->id.':'.$version->snapshot_hash;
        $cache = Cache::store((string) config('services.report_pdf.cache_store', 'file'));
        $cachedPdf = $cache->get($cacheKey);

        if (is_string($cachedPdf) && $this->isPdf($cachedPdf)) {
            return $cachedPdf;
        }

        if ($cachedPdf !== null) {
            $cache->forget($cacheKey);
        }

        $viewData = $this->viewData($version);

        if ($viewData === null) {
            return null;
        }

        $url = config('services.report_pdf.url');

        if (! is_string($url) || blank($url)) {
            return null;
        }

        $html = view('reports.pdf', $viewData)->render();

        try {
            $response = Http::connectTimeout(max(1, (int) config('services.report_pdf.connect_timeout', 5)))
                ->timeout(max(1, (int) config('services.report_pdf.timeout', 60)))
                ->accept('application/pdf')
                ->post($url, ['html' => $html]);
        } catch (ConnectionException) {
            return null;
        }

        $contentType = strtolower(trim(explode(';', (string) $response->header('Content-Type'), 2)[0]));
        $pdf = $response->body();

        if (! $response->successful() || $contentType !== 'application/pdf' || ! $this->isPdf($pdf)) {
            return null;
        }

        $cache->put(
            $cacheKey,
            $pdf,
            now()->addSeconds(max(1, (int) config('services.report_pdf.cache_seconds', 604800))),
        );

        return $pdf;
    }

    /**
     * @return array{
     *     companyName: string,
     *     customerName: string,
     *     address: string,
     *     performedOn: ?string,
     *     performedTime: ?string,
     *     companyPhone: ?string,
     *     companyOrganizationNumber: ?string,
     *     reportCreatedAt: ?string,
     *     versionNumber: int,
     *     logoDataUri: ?string,
     *     places: list<array{name: string, pairs: list<array{label: ?string, customerComment: ?string, beforeDataUri: ?string, afterDataUri: ?string}>}>
     * }|null
     */
    private function viewData(AssignmentReportVersion $version): ?array
    {
        $snapshot = $version->snapshot;

        if (! is_array($snapshot)) {
            return null;
        }

        $assets = $version->assets;
        $photoAssets = $assets
            ->where('type', AssignmentReportVersionAsset::TYPE_PHOTO)
            ->keyBy(function (AssignmentReportVersionAsset $asset): string {
                return $asset->source_photo_id.':'.$asset->phase;
            });
        $places = [];
        $hasPhoto = false;

        foreach ($snapshot['places'] ?? [] as $place) {
            if (! is_array($place)) {
                continue;
            }

            $pairs = [];

            foreach ($place['pairs'] ?? [] as $pair) {
                if (! is_array($pair)) {
                    continue;
                }

                $beforeId = isset($pair['before']) ? (int) $pair['before'] : null;
                $afterId = isset($pair['after']) ? (int) $pair['after'] : null;
                $beforeAsset = $beforeId === null ? null : $photoAssets->get($beforeId.':before');
                $afterAsset = $afterId === null ? null : $photoAssets->get($afterId.':after');

                if (($beforeId !== null && ! $beforeAsset instanceof AssignmentReportVersionAsset)
                    || ($afterId !== null && ! $afterAsset instanceof AssignmentReportVersionAsset)) {
                    return null;
                }

                $beforeDataUri = $beforeAsset instanceof AssignmentReportVersionAsset
                    ? $this->assetDataUri($beforeAsset)
                    : null;
                $afterDataUri = $afterAsset instanceof AssignmentReportVersionAsset
                    ? $this->assetDataUri($afterAsset)
                    : null;

                if (($beforeAsset instanceof AssignmentReportVersionAsset && $beforeDataUri === null)
                    || ($afterAsset instanceof AssignmentReportVersionAsset && $afterDataUri === null)) {
                    return null;
                }

                $hasPhoto = $hasPhoto || $beforeDataUri !== null || $afterDataUri !== null;
                $pairs[] = [
                    'label' => filled($pair['label'] ?? null) ? (string) $pair['label'] : null,
                    'customerComment' => filled($pair['customer_comment'] ?? null)
                        ? (string) $pair['customer_comment']
                        : null,
                    'beforeDataUri' => $beforeDataUri,
                    'afterDataUri' => $afterDataUri,
                ];
            }

            $places[] = [
                'name' => (string) ($place['name'] ?? 'Övrigt'),
                'pairs' => $pairs,
            ];
        }

        if (! $hasPhoto) {
            return null;
        }

        $logoDataUri = null;
        $logoPath = $snapshot['company_logo_path'] ?? null;

        if (filled($logoPath)) {
            $logoAsset = $assets->first(
                fn (AssignmentReportVersionAsset $asset): bool => $asset->type === AssignmentReportVersionAsset::TYPE_LOGO,
            );

            if (! $logoAsset instanceof AssignmentReportVersionAsset || $logoAsset->path !== $logoPath) {
                return null;
            }

            $logoDataUri = $this->assetDataUri($logoAsset);

            if ($logoDataUri === null) {
                return null;
            }
        }

        return [
            'companyName' => (string) ($snapshot['company_name'] ?? ''),
            'customerName' => (string) ($snapshot['customer_name'] ?? ''),
            'address' => (string) ($snapshot['address'] ?? ''),
            'performedOn' => is_string($snapshot['performed_on'] ?? null) ? $snapshot['performed_on'] : null,
            'performedTime' => is_string($snapshot['performed_time_label'] ?? null)
                ? $snapshot['performed_time_label']
                : (is_string($snapshot['performed_time'] ?? null) ? $snapshot['performed_time'] : null),
            'companyPhone' => is_string($snapshot['company_phone'] ?? null) ? $snapshot['company_phone'] : null,
            'companyOrganizationNumber' => is_string($snapshot['company_organization_number'] ?? null)
                ? $snapshot['company_organization_number']
                : null,
            'reportCreatedAt' => $version->created_at?->copy()->utc()->format('Y-m-d H:i:s'),
            'versionNumber' => (int) $version->version_number,
            'logoDataUri' => $logoDataUri,
            'places' => $places,
        ];
    }

    private function assetDataUri(AssignmentReportVersionAsset $asset): ?string
    {
        $extension = strtolower(pathinfo($asset->path, PATHINFO_EXTENSION));
        $mimeType = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            default => null,
        };

        if ($mimeType === null || ! Storage::disk('local')->exists($asset->path)) {
            return null;
        }

        $contents = Storage::disk('local')->get($asset->path);

        if (! is_string($contents) || $contents === '') {
            return null;
        }

        return 'data:'.$mimeType.';base64,'.base64_encode($contents);
    }

    private function isPdf(string $contents): bool
    {
        return str_starts_with($contents, '%PDF-')
            && str_contains(substr($contents, -1024), '%%EOF');
    }
}
