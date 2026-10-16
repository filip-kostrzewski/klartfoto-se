<?php

namespace App\Actions;

use App\Models\Assignment;
use App\Models\AssignmentPhotoPair;
use App\Models\AssignmentPlace;
use App\Models\User;
use App\PhotoPhase;
use App\Rules\AcceptedJobPhoto;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class StoreAssignmentPhotos
{
    /**
     * @param  array<int, UploadedFile>  $before
     * @param  array<int, UploadedFile>  $after
     * @return array{uploaded: int, duplicates: int, uploads: list<array{uploadId: string, photoId: int, pairId: int, phase: string, result: 'uploaded'|'duplicate'}>}
     */
    public function handle(
        Assignment $assignment,
        array $before,
        array $after,
        string $placeName = 'Övrigt',
        ?User $uploader = null,
        array $beforeUploadIds = [],
        array $afterUploadIds = [],
    ): array {
        return $this->store($assignment, $before, $after, $placeName, $uploader, $beforeUploadIds, $afterUploadIds);
    }

    /**
     * @param  array<int, UploadedFile>  $before
     * @param  array<int, UploadedFile>  $after
     * @param  array<int, string>  $beforeUploadIds
     * @param  array<int, string>  $afterUploadIds
     * @return array{uploaded: int, duplicates: int, uploads: list<array{uploadId: string, photoId: int, pairId: int, phase: string, result: 'uploaded'|'duplicate'}>}
     */
    public function addToPair(
        Assignment $assignment,
        AssignmentPlace $place,
        AssignmentPhotoPair $pair,
        array $before,
        array $after,
        ?User $uploader = null,
        array $beforeUploadIds = [],
        array $afterUploadIds = [],
    ): array {
        abort_unless($place->assignment_id === $assignment->id, 404);
        abort_unless($pair->assignment_place_id === $place->id, 404);

        return $this->store(
            $assignment,
            $before,
            $after,
            $place->name,
            $uploader,
            $beforeUploadIds,
            $afterUploadIds,
            $pair,
        );
    }

    /**
     * @param  array<int, UploadedFile>  $before
     * @param  array<int, UploadedFile>  $after
     * @param  array<int, string>  $beforeUploadIds
     * @param  array<int, string>  $afterUploadIds
     * @return array{uploaded: int, duplicates: int, uploads: list<array{uploadId: string, photoId: int, pairId: int, phase: string, result: 'uploaded'|'duplicate'}>}
     */
    private function store(
        Assignment $assignment,
        array $before,
        array $after,
        string $placeName,
        ?User $uploader,
        array $beforeUploadIds,
        array $afterUploadIds,
        ?AssignmentPhotoPair $targetPair = null,
    ): array {
        $storedPaths = [];
        $before = array_values($before);
        $after = array_values($after);
        $beforeUploadIds = $this->uploadIds($before, $beforeUploadIds);
        $afterUploadIds = $this->uploadIds($after, $afterUploadIds);

        try {
            if ($before === [] && $after === []) {
                return ['uploaded' => 0, 'duplicates' => 0, 'uploads' => []];
            }

            $beforeDuplicates = $this->existingUploads(
                $assignment,
                $before,
                $beforeUploadIds,
                PhotoPhase::Before,
                $placeName,
                $targetPair,
            );
            $afterDuplicates = $this->existingUploads(
                $assignment,
                $after,
                $afterUploadIds,
                PhotoPhase::After,
                $placeName,
                $targetPair,
            );
            $duplicateCount = count(array_filter($beforeDuplicates)) + count(array_filter($afterDuplicates));

            if ($targetPair !== null) {
                abort_unless(max(count($before), count($after)) <= 1, 422);
                $this->assertTargetPairSlotsAvailable($targetPair, $before, $after, $beforeDuplicates, $afterDuplicates);
            }

            $uploadedCount = 0;

            $hasNewUploads = $this->hasNewFiles($before, $beforeDuplicates)
                || $this->hasNewFiles($after, $afterDuplicates);
            if (! $hasNewUploads) {
                return [
                    'uploaded' => 0,
                    'duplicates' => $duplicateCount,
                    'uploads' => $this->uploadMappings(
                        $assignment,
                        $before,
                        $beforeUploadIds,
                        $beforeDuplicates,
                        PhotoPhase::Before,
                        $after,
                        $afterUploadIds,
                        $afterDuplicates,
                    ),
                ];
            }

            $place = $targetPair?->place ?? $this->resolvePlace($assignment, $placeName);
            $pairSortOrder = (int) $place->photoPairs()->max('sort_order');
            $beforeSortOrder = (int) $assignment->photos()->where('phase', PhotoPhase::Before)->max('sort_order');
            $afterSortOrder = (int) $assignment->photos()->where('phase', PhotoPhase::After)->max('sort_order');

            for ($index = 0; $index < max(count($before), count($after)); $index++) {
                $beforeFile = $before[$index] ?? null;
                $afterFile = $after[$index] ?? null;
                $newBeforeFile = $beforeFile instanceof UploadedFile && ! ($beforeDuplicates[$index] ?? false);
                $newAfterFile = $afterFile instanceof UploadedFile && ! ($afterDuplicates[$index] ?? false);

                if (! $newBeforeFile && ! $newAfterFile) {
                    continue;
                }

                if ($targetPair !== null) {
                    $pair = $targetPair;
                } else {
                    $pairSortOrder++;
                    $pair = $place->photoPairs()->create(['sort_order' => $pairSortOrder]);
                }

                if ($newBeforeFile && $beforeFile instanceof UploadedFile) {
                    $beforeSortOrder++;
                    $this->storePhoto(
                        $assignment,
                        $pair,
                        PhotoPhase::Before,
                        $beforeFile,
                        $beforeSortOrder,
                        $beforeUploadIds[$index],
                        $storedPaths,
                        $uploader,
                    );
                    $uploadedCount++;
                }

                if ($newAfterFile && $afterFile instanceof UploadedFile) {
                    $afterSortOrder++;
                    $this->storePhoto(
                        $assignment,
                        $pair,
                        PhotoPhase::After,
                        $afterFile,
                        $afterSortOrder,
                        $afterUploadIds[$index],
                        $storedPaths,
                        $uploader,
                    );
                    $uploadedCount++;
                }
            }

            return [
                'uploaded' => $uploadedCount,
                'duplicates' => $duplicateCount,
                'uploads' => $this->uploadMappings(
                    $assignment,
                    $before,
                    $beforeUploadIds,
                    $beforeDuplicates,
                    PhotoPhase::Before,
                    $after,
                    $afterUploadIds,
                    $afterDuplicates,
                ),
            ];
        } catch (Throwable $exception) {
            if ($storedPaths !== []) {
                Storage::disk('local')->delete($storedPaths);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  array<int, string>  $uploadIds
     * @return list<string>
     */
    private function uploadIds(array $files, array $uploadIds): array
    {
        $ids = [];

        foreach ($files as $index => $file) {
            $ids[] = $uploadIds[$index] ?? Str::uuid()->toString();
        }

        return $ids;
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  list<string>  $uploadIds
     * @return array<int, bool>
     */
    private function existingUploads(
        Assignment $assignment,
        array $files,
        array $uploadIds,
        PhotoPhase $phase,
        string $placeName,
        ?AssignmentPhotoPair $targetPair,
    ): array {
        $duplicates = [];
        $normalizedPlaceName = trim($placeName) === '' ? 'Övrigt' : trim($placeName);

        foreach ($files as $index => $file) {
            $uploadId = $uploadIds[$index];
            $uploadHash = hash_file('sha256', $file->getRealPath());
            $existingPhoto = $assignment->photos()
                ->where('client_upload_id', $uploadId)
                ->first();

            if ($existingPhoto === null) {
                $duplicates[$index] = false;

                continue;
            }

            $samePayloadAndPhase = $existingPhoto->phase === $phase
                && is_string($uploadHash)
                && hash_equals((string) $existingPhoto->upload_sha256, $uploadHash);
            $samePair = $targetPair === null
                ? $existingPhoto->photoPair?->place?->name === $normalizedPlaceName
                : $existingPhoto->assignment_photo_pair_id === $targetPair->id;

            if (! $samePayloadAndPhase || ! $samePair) {
                throw ValidationException::withMessages([
                    $phase === PhotoPhase::Before ? 'before_photos' : 'after_photos' => 'Den här uppladdningsnyckeln används redan för en annan bild eller plats.',
                ]);
            }

            $duplicates[$index] = true;
        }

        return $duplicates;
    }

    /**
     * @param  array<int, UploadedFile>  $before
     * @param  array<int, UploadedFile>  $after
     * @param  array<int, bool>  $beforeDuplicates
     * @param  array<int, bool>  $afterDuplicates
     */
    private function assertTargetPairSlotsAvailable(
        AssignmentPhotoPair $pair,
        array $before,
        array $after,
        array $beforeDuplicates,
        array $afterDuplicates,
    ): void {
        foreach ([
            [PhotoPhase::Before, $before, $beforeDuplicates, 'before_photos'],
            [PhotoPhase::After, $after, $afterDuplicates, 'after_photos'],
        ] as [$phase, $files, $duplicates, $field]) {
            if (($files[0] ?? null) instanceof UploadedFile
                && ! ($duplicates[0] ?? false)
                && $pair->photos()->where('phase', $phase)->exists()) {
                throw ValidationException::withMessages([
                    $field => 'Den valda bildrutan innehåller redan en bild på den här sidan.',
                ]);
            }
        }
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  array<int, bool>  $duplicates
     */
    private function hasNewFiles(array $files, array $duplicates): bool
    {
        foreach ($files as $index => $file) {
            if ($file instanceof UploadedFile && ! ($duplicates[$index] ?? false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<UploadedFile>  $before
     * @param  list<string>  $beforeUploadIds
     * @param  array<int, bool>  $beforeDuplicates
     * @param  list<UploadedFile>  $after
     * @param  list<string>  $afterUploadIds
     * @param  array<int, bool>  $afterDuplicates
     * @return list<array{uploadId: string, photoId: int, pairId: int, phase: string, result: 'uploaded'|'duplicate'}>
     */
    private function uploadMappings(
        Assignment $assignment,
        array $before,
        array $beforeUploadIds,
        array $beforeDuplicates,
        PhotoPhase $beforePhase,
        array $after,
        array $afterUploadIds,
        array $afterDuplicates,
    ): array {
        $uploads = [];

        foreach ([
            [$before, $beforeUploadIds, $beforeDuplicates, $beforePhase],
            [$after, $afterUploadIds, $afterDuplicates, PhotoPhase::After],
        ] as [$files, $uploadIds, $duplicates, $phase]) {
            foreach ($files as $index => $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $photo = $assignment->photos()->where('client_upload_id', $uploadIds[$index])->first();

                if ($photo === null) {
                    continue;
                }

                $uploads[] = [
                    'uploadId' => $uploadIds[$index],
                    'photoId' => (int) $photo->getKey(),
                    'pairId' => (int) $photo->assignment_photo_pair_id,
                    'phase' => $phase->value,
                    'result' => ($duplicates[$index] ?? false) ? 'duplicate' : 'uploaded',
                ];
            }
        }

        return $uploads;
    }

    private function resolvePlace(Assignment $assignment, string $name): AssignmentPlace
    {
        $name = trim($name) === '' ? 'Övrigt' : trim($name);

        $place = $assignment->places()->where('name', $name)->first();

        if ($place instanceof AssignmentPlace) {
            return $place;
        }

        $sortOrder = (int) $assignment->places()->max('sort_order') + 1;

        return $assignment->places()->create(['name' => $name, 'sort_order' => $sortOrder]);
    }

    /**
     * @param  list<string>  $storedPaths
     */
    private function storePhoto(
        Assignment $assignment,
        AssignmentPhotoPair $pair,
        PhotoPhase $phase,
        UploadedFile $file,
        int $sortOrder,
        string $clientUploadId,
        array &$storedPaths,
        ?User $uploader,
    ): void {
        $path = $this->storeResized($assignment, $file);
        $storedPaths[] = $path;

        $assignment->photos()->create([
            'assignment_photo_pair_id' => $pair->id,
            'phase' => $phase,
            'path' => $path,
            'sort_order' => $sortOrder,
            'uploaded_by_user_id' => $uploader?->id ?? $assignment->user_id,
            'client_upload_id' => $clientUploadId,
            'upload_sha256' => hash_file('sha256', $file->getRealPath()),
        ]);
    }

    private function storeResized(Assignment $assignment, UploadedFile $file): string
    {
        $temporaryPath = null;

        try {
            $sourcePath = $file->getRealPath();

            if ((new AcceptedJobPhoto)->isHeic($file)) {
                $sourcePath = app(ConvertHeicToJpeg::class)->handle($file);
                $temporaryPath = $sourcePath;
            }

            $contents = @file_get_contents($sourcePath);
            $source = is_string($contents) ? @imagecreatefromstring($contents) : false;

            if (! $source instanceof GdImage) {
                throw ValidationException::withMessages([
                    'before_photos' => 'En av bilderna gick inte att läsa. Prova en JPEG-, PNG-, WebP- eller HEIC-fil.',
                ]);
            }

            if ($temporaryPath === null) {
                $source = $this->applyOrientation($source, $file);
            }
            $width = imagesx($source);
            $height = imagesy($source);
            $maxEdge = max(1, (int) config('klartfoto.photo_max_edge', 1600));
            $scale = min(1, $maxEdge / max($width, $height, 1));
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));

            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $white);
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
            imagedestroy($source);

            ob_start();
            imagejpeg($canvas, null, 82);
            $jpeg = ob_get_clean();
            imagedestroy($canvas);

            if (! is_string($jpeg) || $jpeg === '') {
                throw ValidationException::withMessages([
                    'before_photos' => 'En av bilderna gick inte att spara. Prova igen.',
                ]);
            }

            $path = 'assignments/'.$assignment->id.'/'.Str::uuid()->toString().'.jpg';
            Storage::disk('local')->put($path, $jpeg);

            return $path;
        } finally {
            if (is_string($temporaryPath) && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private function applyOrientation(GdImage $image, UploadedFile $file): GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $orientation = (int) (is_array($exif) ? ($exif['Orientation'] ?? 1) : 1);
        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        if ($rotated instanceof GdImage && $rotated !== $image) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }
}
