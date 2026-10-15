<?php

namespace App\Actions;

use App\Models\Assignment;
use App\PhotoPhase;
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

        try {
            $this->storePhase($assignment, PhotoPhase::Before, $before, $storedPaths);
            $this->storePhase($assignment, PhotoPhase::After, $after, $storedPaths);
        } catch (Throwable $exception) {
            if ($storedPaths !== []) {
                Storage::disk('local')->delete($storedPaths);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  list<string>  $storedPaths
     */
    private function storePhase(Assignment $assignment, PhotoPhase $phase, array $files, array &$storedPaths): void
    {
        $sortOrder = (int) $assignment->photos()->where('phase', $phase)->max('sort_order');

        foreach ($files as $file) {
            $sortOrder++;
            $path = $this->storeResized($assignment, $file);
            $storedPaths[] = $path;

            $assignment->photos()->create([
                'phase' => $phase,
                'path' => $path,
                'sort_order' => $sortOrder,
            ]);
        }
    }

    private function storeResized(Assignment $assignment, UploadedFile $file): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if (! $source instanceof GdImage) {
            throw ValidationException::withMessages([
                'before_photos' => 'En av bilderna gick inte att läsa. Prova en JPEG-, PNG- eller WebP-fil.',
            ]);
        }

        $source = $this->applyOrientation($source, $file);
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
