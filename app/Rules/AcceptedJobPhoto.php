<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class AcceptedJobPhoto implements ValidationRule
{
    public const FORMAT_MESSAGE = 'Filen är inte en bild. Använd JPEG, PNG, WebP, HEIC eller HEIF.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail(self::FORMAT_MESSAGE);

            return;
        }

        if ($value->getSize() > 15 * 1024 * 1024) {
            $fail('Varje bild får vara högst 15 MB.');

            return;
        }

        if ($this->isHeic($value)) {
            return;
        }

        $info = @getimagesize($value->getPathname());
        $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';

        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $fail(self::FORMAT_MESSAGE);

            return;
        }

        $width = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);

        if ($width > 8000 || $height > 8000 || $width < 1 || $height < 1) {
            $fail('En bild har för hög upplösning. Prova en mindre bild.');
        }
    }

    public function isHeic(UploadedFile $file): bool
    {
        $mime = strtolower((string) $file->getMimeType());
        $name = strtolower($file->getClientOriginalName());

        return in_array($mime, ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'], true)
            || str_ends_with($name, '.heic')
            || str_ends_with($name, '.heif');
    }
}
