<?php

namespace App\Actions;

use App\Models\Assignment;
use App\Models\AssignmentPhoto;
use App\Models\AssignmentReportVersionAsset;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Removes one job photo from storage and from every report version of that job.
 *
 * Share links stay valid. They render without the deleted photo.
 */
class DeleteAssignmentPhoto
{
    public function handle(Assignment $assignment, AssignmentPhoto $photo): void
    {
        $paths = DB::transaction(function () use ($assignment, $photo): array {
            $lockedAssignment = Assignment::query()
                ->whereKey($assignment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedPhoto = AssignmentPhoto::query()
                ->whereKey($photo->getKey())
                ->where('assignment_id', $lockedAssignment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $versions = DB::table('assignment_report_versions')
                ->where('assignment_id', $lockedAssignment->getKey())
                ->get(['id', 'snapshot', 'snapshot_hash']);
            $paths = $this->pathsFor((int) $lockedAssignment->getKey(), $lockedPhoto, $versions);
            $versionsWithPhotoAsset = $this->versionIdsWithPhotoAsset($versions->pluck('id')->all(), $lockedPhoto);

            foreach ($versions as $version) {
                $this->removePhotoFromVersion(
                    $version,
                    $lockedPhoto,
                    in_array((int) $version->id, $versionsWithPhotoAsset, true),
                );
            }

            if ($versionsWithPhotoAsset !== []) {
                DB::table('assignment_report_version_assets')
                    ->whereIn('assignment_report_version_id', $versionsWithPhotoAsset)
                    ->where('type', AssignmentReportVersionAsset::TYPE_PHOTO)
                    ->where(function ($query) use ($lockedPhoto): void {
                        $query->where('source_photo_id', $lockedPhoto->id)
                            ->orWhere('path', $lockedPhoto->path);
                    })
                    ->delete();
            }

            $lockedPhoto->delete();
            $lockedAssignment->markDraftChanged();

            return $paths;
        }, attempts: 3);

        $this->deleteStoredFiles($paths);
    }

    /**
     * @param  Collection<int, object>  $versions
     * @return list<string>
     */
    private function pathsFor(int $assignmentId, AssignmentPhoto $photo, Collection $versions): array
    {
        $paths = [];
        $this->rememberPath($paths, $photo->path, $assignmentId);

        if ($versions->isNotEmpty()) {
            $assetPaths = DB::table('assignment_report_version_assets')
                ->whereIn('assignment_report_version_id', $versions->pluck('id'))
                ->where('type', AssignmentReportVersionAsset::TYPE_PHOTO)
                ->where(function ($query) use ($photo): void {
                    $query->where('source_photo_id', $photo->id)
                        ->orWhere('path', $photo->path);
                })
                ->pluck('path');

            foreach ($assetPaths as $path) {
                $this->rememberPath($paths, $path, $assignmentId);
            }
        }

        foreach ($versions as $version) {
            $snapshot = $this->decodeSnapshot($version->snapshot);

            if (! is_array($snapshot)) {
                continue;
            }

            foreach ($snapshot['assets'] ?? [] as $asset) {
                if (! is_array($asset) || ! $this->assetReferencesPhoto($asset, (int) $photo->id, (string) $photo->path)) {
                    continue;
                }

                $this->rememberPath($paths, $asset['path'] ?? null, $assignmentId);
            }
        }

        return array_keys($paths);
    }

    /**
     * @param  list<int>  $versionIds
     * @return list<int>
     */
    private function versionIdsWithPhotoAsset(array $versionIds, AssignmentPhoto $photo): array
    {
        if ($versionIds === []) {
            return [];
        }

        return DB::table('assignment_report_version_assets')
            ->whereIn('assignment_report_version_id', $versionIds)
            ->where('type', AssignmentReportVersionAsset::TYPE_PHOTO)
            ->where(function ($query) use ($photo): void {
                $query->where('source_photo_id', $photo->id)
                    ->orWhere('path', $photo->path);
            })
            ->pluck('assignment_report_version_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function removePhotoFromVersion(object $version, AssignmentPhoto $photo, bool $hasMatchingAsset): void
    {
        $snapshot = $this->decodeSnapshot($version->snapshot);
        $snapshotJson = null;

        if (is_array($snapshot)) {
            $updated = $this->snapshotWithoutPhoto($snapshot, (int) $photo->id, (string) $photo->path);
            $updatedJson = $this->encodeSnapshot($updated);
            $originalJson = $this->encodeSnapshot($snapshot);

            if ($updatedJson !== $originalJson) {
                $snapshotJson = $updatedJson;
            }
        }

        if ($snapshotJson === null && ! $hasMatchingAsset) {
            return;
        }

        $this->forgetPdfCache($version);

        if ($snapshotJson === null) {
            return;
        }

        DB::table('assignment_report_versions')->where('id', $version->id)->update([
            'snapshot' => $snapshotJson,
            'snapshot_hash' => hash('sha256', $snapshotJson),
            'updated_at' => now(),
        ]);
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

    private function decodeSnapshot(mixed $snapshot): mixed
    {
        if (is_array($snapshot)) {
            return $snapshot;
        }

        if (! is_string($snapshot) || $snapshot === '') {
            return null;
        }

        $decoded = json_decode($snapshot, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function encodeSnapshot(array $snapshot): string
    {
        return json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<string, mixed>  $asset
     */
    private function assetReferencesPhoto(array $asset, int $photoId, string $path): bool
    {
        $samePhoto = isset($asset['source_photo_id']) && (int) $asset['source_photo_id'] === $photoId;
        $sameFile = ($asset['type'] ?? AssignmentReportVersionAsset::TYPE_PHOTO) === AssignmentReportVersionAsset::TYPE_PHOTO
            && ($asset['path'] ?? null) === $path;

        return $samePhoto || $sameFile;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function snapshotWithoutPhoto(array $snapshot, int $photoId, string $path): array
    {
        $assets = [];

        foreach ($snapshot['assets'] ?? [] as $asset) {
            if (! is_array($asset) || $this->assetReferencesPhoto($asset, $photoId, $path)) {
                continue;
            }

            $assets[] = $asset;
        }

        $snapshot['assets'] = $assets;
        $places = [];

        foreach ($snapshot['places'] ?? [] as $place) {
            if (! is_array($place)) {
                continue;
            }

            $pairs = [];

            foreach ($place['pairs'] ?? [] as $pair) {
                if (! is_array($pair)) {
                    continue;
                }

                if ((int) ($pair['before'] ?? 0) === $photoId) {
                    $pair['before'] = null;
                }

                if ((int) ($pair['after'] ?? 0) === $photoId) {
                    $pair['after'] = null;
                }

                if ($pair['before'] === null && $pair['after'] === null) {
                    continue;
                }

                $pairs[] = $pair;
            }

            if ($pairs === []) {
                continue;
            }

            $place['pairs'] = $pairs;
            $places[] = $place;
        }

        $snapshot['places'] = $places;

        return $snapshot;
    }

    private function forgetPdfCache(object $version): void
    {
        $store = (string) config('services.report_pdf.cache_store', 'file');

        try {
            Cache::store($store)->forget('assignment-report-pdf:'.$version->id.':'.$version->snapshot_hash);
        } catch (Throwable $exception) {
            Log::warning('Could not forget a cached report PDF while deleting a photo.', [
                'exception' => $exception::class,
            ]);
        }
    }

    /**
     * @param  list<string>  $paths
     */
    private function deleteStoredFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if ($this->pathStillUsed($path)) {
                continue;
            }

            foreach (array_keys(config('filesystems.disks', [])) as $disk) {
                $this->deleteOnDisk((string) $disk, $path);
            }
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
            if ($disk === 'local' || $disk === (string) config('filesystems.default')) {
                throw $exception;
            }

            if ($exception instanceof Error && str_contains($exception->getMessage(), 'not found')) {
                return;
            }

            Log::warning('Could not delete a removed photo from an optional disk.', [
                'disk' => $disk,
                'exception' => $exception::class,
            ]);
        }
    }
}
