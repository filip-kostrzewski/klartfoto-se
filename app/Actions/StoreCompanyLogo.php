<?php

namespace App\Actions;

use App\Models\AssignmentReportVersionAsset;
use App\Models\Company;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class StoreCompanyLogo
{
    public function __construct(private SanitizeSvgLogo $svg) {}

    public function handle(Company $company, UploadedFile $file): void
    {
        $contents = (string) file_get_contents($file->getRealPath());
        $trimmed = ltrim($contents);
        $looksLikeSvg = str_starts_with($trimmed, '<svg')
            || str_starts_with($trimmed, '<?xml')
            || str_ends_with(strtolower($file->getClientOriginalName()), '.svg');

        if ($looksLikeSvg) {
            $this->storeSvg($company, $contents);

            return;
        }

        $this->storeRaster($company, $file, $contents);
    }

    public function delete(Company $company): void
    {
        $previousPath = DB::transaction(function () use ($company): ?string {
            $lockedCompany = Company::query()
                ->whereKey($company->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $previousPath = $lockedCompany->logo_path;

            $lockedCompany->forceFill(['logo_path' => null])->save();

            return $previousPath;
        }, attempts: 3);

        if (filled($previousPath)) {
            $this->deleteIfUnreferenced($previousPath);
        }
    }

    private function storeSvg(Company $company, string $contents): void
    {
        $sanitized = $this->svg->handle($contents);

        if ($sanitized === null) {
            throw ValidationException::withMessages([
                'logo' => 'SVG-filen innehåller något vi inte kan visa säkert. Förenkla filen och försök igen.',
            ]);
        }

        $this->replace($company, 'svg', $sanitized);
    }

    private function storeRaster(Company $company, UploadedFile $file, string $contents): void
    {
        $info = @getimagesizefromstring($contents);
        $type = is_array($info) ? ($info[2] ?? null) : null;

        if (! in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            throw ValidationException::withMessages([
                'logo' => 'Logotypen behöver vara en PNG-, JPEG- eller SVG-fil.',
            ]);
        }

        $source = @imagecreatefromstring($contents);

        if (! $source instanceof GdImage) {
            throw ValidationException::withMessages([
                'logo' => 'Bilden gick inte att läsa. Prova en PNG- eller JPEG-fil.',
            ]);
        }

        if ($type === IMAGETYPE_JPEG) {
            $source = $this->applyOrientation($source, $file);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $maxEdge = max(1, (int) config('klartfoto.logo_max_edge', 800));
        $scale = min(1, $maxEdge / max($width, $height, 1));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $asJpeg = $type === IMAGETYPE_JPEG;

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($asJpeg) {
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $white);
        } else {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($source);

        ob_start();

        if ($asJpeg) {
            imagejpeg($canvas, null, 82);
            $extension = 'jpg';
        } else {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagepng($canvas);
            $extension = 'png';
        }

        $binary = ob_get_clean();
        imagedestroy($canvas);

        if (! is_string($binary) || $binary === '') {
            throw ValidationException::withMessages([
                'logo' => 'Logotypen gick inte att spara. Prova igen.',
            ]);
        }

        $this->replace($company, $extension, $binary);
    }

    private function replace(Company $company, string $extension, string $contents): void
    {
        $path = 'companies/'.$company->id.'/logos/'.Str::uuid()->toString().'.'.$extension;

        Storage::disk('local')->put($path, $contents);

        try {
            $previousPath = DB::transaction(function () use ($company, $path): ?string {
                $lockedCompany = Company::query()
                    ->whereKey($company->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $previousPath = $lockedCompany->logo_path;

                $lockedCompany->forceFill(['logo_path' => $path])->save();

                return $previousPath;
            }, attempts: 3);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        if (filled($previousPath) && $previousPath !== $path) {
            $this->deleteIfUnreferenced($previousPath);
        }
    }

    private function deleteIfUnreferenced(string $path): void
    {
        $referenced = AssignmentReportVersionAsset::query()
            ->where('type', AssignmentReportVersionAsset::TYPE_LOGO)
            ->where('path', $path)
            ->exists();

        if (! $referenced) {
            Storage::disk('local')->delete($path);
        }
    }

    private function applyOrientation(GdImage $image, UploadedFile $file): GdImage
    {
        if (! function_exists('exif_read_data')) {
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
