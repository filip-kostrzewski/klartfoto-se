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

        $converted = false;

        try {
            if (! copy($file->getRealPath(), $input)) {
                throw ValidationException::withMessages([
                    'before_photos' => 'HEIC-bilden kunde inte läsas. Spara den som JPEG och försök igen.',
                ]);
            }

            $process = new Process([$binary, $input, $output]);
            $process->setTimeout(30);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($output) || filesize($output) === 0) {
                throw ValidationException::withMessages([
                    'before_photos' => 'HEIC-bilden kunde inte läsas. Spara den som JPEG och försök igen.',
                ]);
            }

            $converted = true;

            return $output;
        } catch (\Throwable $exception) {
            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'before_photos' => 'HEIC-bilden kunde inte läsas. Spara den som JPEG och försök igen.',
            ]);
        } finally {
            @unlink($input);
            if (! $converted && is_file($output)) {
                @unlink($output);
            }
        }
    }
}
