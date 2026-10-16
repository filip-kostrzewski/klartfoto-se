<?php

namespace App\Http\Requests\Concerns;

use App\Rules\AcceptedJobPhoto;

trait ValidatesJobPhotos
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function photoRules(): array
    {
        $fileRules = ['file', new AcceptedJobPhoto];

        return [
            'before_photos' => ['nullable', 'array', 'max:10'],
            'before_photos.*' => $fileRules,
            'after_photos' => ['nullable', 'array', 'max:10'],
            'after_photos.*' => $fileRules,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function photoMessages(): array
    {
        return [
            'before_photos.max' => 'Du kan ladda upp högst 10 förebilder åt gången.',
            'after_photos.max' => 'Du kan ladda upp högst 10 efterbilder åt gången.',
            'before_photos.*.file' => AcceptedJobPhoto::FORMAT_MESSAGE,
            'after_photos.*.file' => AcceptedJobPhoto::FORMAT_MESSAGE,
        ];
    }
}
