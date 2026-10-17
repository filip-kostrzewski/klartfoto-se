<?php

namespace App\Actions;

use App\Models\Assignment;
use App\Models\AssignmentReportVersionAsset;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Erases one job, its photos, report versions and share links.
 */
class DeleteAssignment
{
    public function handle(Assignment $assignment): void
    {
        $deletion = DB::transaction(function () use ($assignment): array {
            $lockedAssignment = Assignment::query()
                ->whereKey($assignment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $assignmentId = (int) $lockedAssignment->getKey();
            $versions = DB::table('assignment_report_versions')
                ->where('assignment_id', $assignmentId)
                ->get(['id', 'token', 'snapshot', 'snapshot_hash']);
            $paths = $this->pathsFor($assignmentId, $versions);
            $tokens = array_values(array_unique(array_filter([
                (string) $lockedAssignment->public_token,
                ...$versions->pluck('token')->map(fn (mixed $token): string => (string) $token)->all(),
            ])));
            $cacheKeys = $versions
                ->map(fn (object $version): string => 'assignment-report-pdf:'.$version->id.':'.$version->snapshot_hash)
                ->all();
            $versionIds = $versions->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

            if ($versionIds !== []) {
                DB::table('assignment_email_attempts')
                    ->whereIn('assignment_report_version_id', $versionIds)
                    ->update(['assignment_report_version_id' => null]);
            }

            $lockedAssignment->delete();

            return [
                'assignment_id' => $assignmentId,
                'paths' => $paths,
                'tokens' => $tokens,
                'cache_keys' => $cacheKeys,
            ];
        }, attempts: 3);

        $this->forgetPdfCaches($deletion['cache_keys']);
        $this->deleteStoredFiles($deletion['paths'], $deletion['assignment_id']);
    }

    /**
     * @param  Collection<int, object>  $versions
     * @return list<string>
     */
    private function pathsFor(int $assignmentId, Collection $versions): array
    {
        $paths = [];
        $photoQuery = DB::table('assignment_photos')->where('assignment_id', $assignmentId);

        foreach ($photoQuery->pluck('path') as $path) {
            $this->rememberPath($paths, $path, $assignmentId);
        }

        if ($versions->isNotEmpty()) {
            $assetPaths = DB::table('assignment_report_version_assets')
                ->whereIn('assignment_report_version_id', $versions->pluck('id'))
                ->where('type', AssignmentReportVersionAsset::TYPE_PHOTO)
                ->pluck('path');

            foreach ($assetPaths as $path) {
                $this->rememberPath($paths, $path, $assignmentId);
            }
        }

        foreach ($versions as $version) {
            $snapshot = json_decode((string) $version->snapshot, true);

            if (! is_array($snapshot)) {
                continue;
            }

            foreach ($snapshot['assets'] ?? [] as $asset) {
                if (! is_array($asset) || ($asset['type'] ?? AssignmentReportVersionAsset::TYPE_PHOTO) !== AssignmentReportVersionAsset::TYPE_PHOTO) {
                    continue;
                }

                $this->rememberPath($paths, $asset['path'] ?? null, $assignmentId);
            }
        }

        return array_keys($paths);
    }

    /**
     * @param  array<string, true>  $paths
     */
    private function rememberPath(array &$paths, mixed $path, int $assignmentId): void
    {
        $prefix = 'assignments/'.$assignmentId.'/';

        if (! is_string($path) || ! str_starts_with($path, $prefix) || str_contains($path, '..') || str_contains($path, '\\')) {
            return;
        }

        $paths[$path] = true;
    }

    /**
     * @param  list<string>  $cacheKeys
     */
    private function forgetPdfCaches(array $cacheKeys): void
    {
        $store = (string) config('services.report_pdf.cache_store', 'file');

        foreach ($cacheKeys as $cacheKey) {
            try {
                Cache::store($store)->forget($cacheKey);
            } catch (Throwable $exception) {
                Log::warning('Could not forget a cached report PDF while deleting a job.', [
                    'exception' => $exception::class,
                ]);
            }
        }
    }

    /**
     * @param  list<string>  $paths
     */
    private function deleteStoredFiles(array $paths, int $assignmentId): void
    {
        foreach ($paths as $path) {
            if ($this->pathStillUsed($path)) {
                continue;
            }

            foreach (array_keys(config('filesystems.disks', [])) as $disk) {
                $this->deleteOnDisk((string) $disk, $path);
            }
        }

        $prefix = 'assignments/'.$assignmentId;
        $like = $prefix.'/%';
        $prefixStillUsed = DB::table('assignment_photos')->where('path', 'like', $like)->exists()
            || DB::table('assignment_report_version_assets')->where('path', 'like', $like)->exists()
            || DB::table('companies')->where('logo_path', 'like', $like)->exists();

        if ($prefixStillUsed) {
            return;
        }

        foreach (array_keys(config('filesystems.disks', [])) as $disk) {
            $this->deleteDirectoryOnDisk((string) $disk, $prefix);
        }
    }

    private function pathStillUsed(string $path): bool
    {
        return DB::table('assignment_photos')->where('path', $path)->exists()
            || DB::table('assignment_report_version_assets')->where('path', $path)->exists()
            || DB::table('companies')->where('logo_path', $path)->exists();
    }

    private function deleteOnDisk(string $disk, string $path): void
    {
        try {
            Storage::disk($disk)->delete($path);
        } catch (Throwable $exception) {
            $this->reportOptionalDiskFailure($disk, $exception);
        }
    }

    private function deleteDirectoryOnDisk(string $disk, string $directory): void
    {
        try {
            Storage::disk($disk)->deleteDirectory($directory);
        } catch (Throwable $exception) {
            $this->reportOptionalDiskFailure($disk, $exception);
        }
    }

    private function reportOptionalDiskFailure(string $disk, Throwable $exception): void
    {
        if ($disk === 'local' || $disk === (string) config('filesystems.default')) {
            throw $exception;
        }

        if ($exception instanceof Error && str_contains($exception->getMessage(), 'not found')) {
            return;
        }

        Log::warning('Could not delete job photos from an optional disk.', [
            'disk' => $disk,
            'exception' => $exception::class,
        ]);
    }
}
