<?php

namespace App\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ConvertHeicToJpeg
{
    public function handle(UploadedFile $file): string
    {
        $binary = (new ExecutableFinder)->find('heif-convert')
            ?? (new ExecutableFinder)->find('magick');

        if ($binary === null) {
            throw ValidationException::withMessages([
                'before_photos' => 'HEIC-bilden kunde inte läsas. Spara den som JPEG och försök igen.',
            ]);
        }

        $input = sys_get_temp_dir().'/'.Str::uuid()->toString().'.heic';
        $output = sys_get_temp_dir().'/'.Str::uuid()->toString().'.jpg';

        if (! copy($file->getRealPath(), $input)) {
            throw ValidationException::withMessages([
                'before_photos' => 'HEIC-bilden kunde inte läsas. Spara den som JPEG och försök igen.',
            ]);
        }

        $process = new Process([$binary, $input, $output]);
        $process->setTimeout(30);
        $process->run();
        @unlink($input);

        if (! $process->isSuccessful() || ! is_file($output) || filesize($output) === 0) {
            if (is_file($output)) {
                @unlink($output);
            }

            throw ValidationException::withMessages([
                'before_photos' => 'HEIC-bilden kunde inte läsas. Spara den som JPEG och försök igen.',
            ]);
        }

        return $output;
    }
}
