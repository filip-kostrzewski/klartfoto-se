<?php

namespace App\Actions;

use App\Models\Assignment;
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
     */
    public function handle(Assignment $assignment, array $before, array $after): void
    {
        $storedPaths = [];
        $temporaryPaths = [];

        try {
            $assignment->materializeLegacyPairs();
            $this->storePairs($assignment, $before, $after, $storedPaths, $temporaryPaths);
        } catch (Throwable $exception) {
            if ($storedPaths !== []) {
                Storage::disk('local')->delete($storedPaths);
            }

            throw $exception;
        } finally {
            foreach ($temporaryPaths as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    /**
     * @param  array<int, UploadedFile>  $before
     * @param  array<int, UploadedFile>  $after
     * @param  list<string>  $storedPaths
     * @param  list<string>  $temporaryPaths
     */
    private function storePairs(Assignment $assignment, array $before, array $after, array &$storedPaths, array &$temporaryPaths): void
    {
        $count = max(count($before), count($after));

        if ($count === 0) {
            return;
        }

        $position = $assignment->photoPairRecords()->exists()
            ? ((int) $assignment->photoPairRecords()->max('position')) + 1
            : 0;

        for ($index = 0; $index < $count; $index++) {
            $pair = $assignment->photoPairRecords()->create([
                'position' => $position + $index,
                'room_name' => null,
                'note' => null,
            ]);

            $this->storeOne($assignment, $pair->id, PhotoPhase::Before, $before[$index] ?? null, $index + 1, $storedPaths, $temporaryPaths);
            $this->storeOne($assignment, $pair->id, PhotoPhase::After, $after[$index] ?? null, $index + 1, $storedPaths, $temporaryPaths);
        }
    }

    /**
     * @param  list<string>  $storedPaths
     * @param  list<string>  $temporaryPaths
     */
    private function storeOne(Assignment $assignment, int $pairId, PhotoPhase $phase, ?UploadedFile $file, int $sortOrder, array &$storedPaths, array &$temporaryPaths): void
    {
        if (! $file instanceof UploadedFile) {
            return;
        }

        $path = $this->storeResized($assignment, $file, $temporaryPaths);
        $storedPaths[] = $path;

        $assignment->photos()->create([
            'phase' => $phase,
            'path' => $path,
            'sort_order' => $sortOrder,
            'assignment_photo_pair_id' => $pairId,
        ]);
    }

    /**
     * @param  list<string>  $temporaryPaths
     */
    private function storeResized(Assignment $assignment, UploadedFile $file, array &$temporaryPaths): string
    {
        $sourcePath = $file->getRealPath();
        $converted = false;

        if ((new AcceptedJobPhoto)->isHeic($file)) {
            $sourcePath = app(ConvertHeicToJpeg::class)->handle($file);
            $temporaryPaths[] = $sourcePath;
            $converted = true;
        }

        $source = @imagecreatefromstring((string) file_get_contents($sourcePath));

        if (! $source instanceof GdImage) {
            throw ValidationException::withMessages([
                'before_photos' => 'En av bilderna gick inte att läsa. Prova en JPEG-, PNG-, WebP- eller HEIC-fil.',
            ]);
        }

        if (! $converted) {
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
