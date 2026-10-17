<?php

namespace App\Actions;

use App\CompanyRole;
use App\Models\AssignmentReportVersionAsset;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * One-shot removal of public report links and stored photos for a single test job.
 *
 * The match is assignment id, company name, owner email and customer name together.
 * Any other row is left untouched. Running this again after a successful purge does nothing.
 */
class PurgeExposedTestJobPhotos
{
    public const ASSIGNMENT_ID = 21;

    public const COMPANY_NAME = 'TEST Kriti Städ';

    public const OWNER_EMAIL = 'inori+kriti-klartfoto@mail.grokbot.com';

    public const CUSTOMER_NAME = 'TEST Kriti Kund 2';

    public function handle(): void
    {
        $assignment = $this->matchingAssignment();

        if ($assignment === null) {
            return;
        }

        $assignmentId = (int) $assignment->id;
        $versions = DB::table('assignment_report_versions')
            ->where('assignment_id', $assignmentId)
            ->get(['id', 'token', 'snapshot', 'snapshot_hash']);
        $versionIds = $versions->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $paths = $this->photoPaths($assignmentId, $versions);
        $softDeletedPhotoIds = $this->softDeletedPhotoIds($assignmentId);

        if ($versionIds === [] && $paths === [] && $softDeletedPhotoIds === [] && ! $this->directoryHasFiles()) {
            return;
        }

        $this->forgetPdfCache($versions);

        DB::transaction(function () use ($assignmentId, $versionIds, $softDeletedPhotoIds): void {
            if ($versionIds !== []) {
                DB::table('assignment_email_attempts')
                    ->whereIn('assignment_report_version_id', $versionIds)
                    ->update(['assignment_report_version_id' => null]);
                DB::table('assignment_report_responses')
                    ->whereIn('assignment_report_version_id', $versionIds)
                    ->delete();
                DB::table('assignment_report_version_assets')
                    ->whereIn('assignment_report_version_id', $versionIds)
                    ->delete();
                DB::table('assignment_report_versions')
                    ->whereIn('id', $versionIds)
                    ->delete();
                $this->rotatePublicToken($assignmentId);
            }

            if ($softDeletedPhotoIds !== []) {
                DB::table('assignment_photos')->whereIn('id', $softDeletedPhotoIds)->delete();
            }
        });

        $this->deleteStoredFiles($paths);
    }

    private function matchingAssignment(): ?object
    {
        return DB::table('assignments')
            ->join('companies', 'companies.id', '=', 'assignments.company_id')
            ->join('users', 'users.company_id', '=', 'companies.id')
            ->where('assignments.id', self::ASSIGNMENT_ID)
            ->where('assignments.customer_name', self::CUSTOMER_NAME)
            ->where('companies.name', self::COMPANY_NAME)
            ->where('users.email', self::OWNER_EMAIL)
            ->where('users.role', CompanyRole::Owner->value)
            ->select('assignments.id')
            ->first();
    }

    /**
     * @param  Collection<int, object>  $versions
     * @return list<string>
     */
    private function photoPaths(int $assignmentId, $versions): array
    {
        $paths = [];

        $photoQuery = DB::table('assignment_photos')->where('assignment_id', $assignmentId);

        foreach ($photoQuery->pluck('path') as $path) {
            $this->rememberPath($paths, $path);
        }

        if ($versions->isNotEmpty()) {
            $assetPaths = DB::table('assignment_report_version_assets')
                ->whereIn('assignment_report_version_id', $versions->pluck('id'))
                ->where('type', AssignmentReportVersionAsset::TYPE_PHOTO)
                ->pluck('path');

            foreach ($assetPaths as $path) {
                $this->rememberPath($paths, $path);
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

                $this->rememberPath($paths, $asset['path'] ?? null);
            }
        }

        return array_keys($paths);
    }

    /**
     * @param  array<string, true>  $paths
     */
    private function rememberPath(array &$paths, mixed $path): void
    {
        if (! is_string($path) || $path === '' || str_contains($path, '..') || str_starts_with($path, '/') || str_contains($path, '\\')) {
            return;
        }

        $paths[$path] = true;
    }

    /**
     * @return list<int>
     */
    private function softDeletedPhotoIds(int $assignmentId): array
    {
        if (! DB::getSchemaBuilder()->hasColumn('assignment_photos', 'deleted_at')) {
            return [];
        }

        return DB::table('assignment_photos')
            ->where('assignment_id', $assignmentId)
            ->whereNotNull('deleted_at')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  Collection<int, object>  $versions
     */
    private function forgetPdfCache($versions): void
    {
        $store = (string) config('services.report_pdf.cache_store', 'file');

        foreach ($versions as $version) {
            try {
                Cache::store($store)->forget('assignment-report-pdf:'.$version->id.':'.$version->snapshot_hash);
            } catch (Throwable $exception) {
                Log::warning('Could not forget a cached report PDF during the test-job purge.', [
                    'disk' => $store,
                    'exception' => $exception::class,
                ]);
            }
        }
    }

    private function rotatePublicToken(int $assignmentId): void
    {
        do {
            $token = Str::random(48);
        } while (
            DB::table('assignments')->where('public_token', $token)->exists()
            || DB::table('assignment_report_versions')->where('token', $token)->exists()
        );

        DB::table('assignments')->where('id', $assignmentId)->update([
            'public_token' => $token,
            'updated_at' => now(),
        ]);
    }

    private function directoryHasFiles(): bool
    {
        try {
            return Storage::disk('local')->allFiles('assignments/'.self::ASSIGNMENT_ID) !== [];
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  list<string>  $paths
     */
    private function deleteStoredFiles(array $paths): void
    {
        $directory = 'assignments/'.self::ASSIGNMENT_ID;

        foreach (array_keys(config('filesystems.disks', [])) as $disk) {
            try {
                $filesystem = Storage::disk($disk);

                if ($paths !== []) {
                    $filesystem->delete($paths);
                }

                $filesystem->deleteDirectory($directory);
            } catch (Throwable $exception) {
                if ($disk === 'local' || $disk === config('filesystems.default')) {
                    throw $exception;
                }

                Log::warning('Could not purge test-job photos from an optional disk.', [
                    'disk' => $disk,
                    'exception' => $exception::class,
                ]);
            }
        }
    }
}
