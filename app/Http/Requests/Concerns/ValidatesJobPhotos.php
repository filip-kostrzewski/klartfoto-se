<?php

namespace App\Http\Requests\Concerns;

trait ValidatesJobPhotos
{
    /**
     * @return array<string, array<int, string>>
     */
    protected function photoRules(): array
    {
        $fileRules = ['image', 'mimes:jpg,jpeg,png,webp', 'max:15360', 'dimensions:max_width=8000,max_height=8000'];

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
            'before_photos.*.image' => 'Förebilderna behöver vara bildfiler.',
            'after_photos.*.image' => 'Efterbilderna behöver vara bildfiler.',
            'before_photos.*.mimes' => 'Använd JPEG, PNG eller WebP för förebilderna.',
            'after_photos.*.mimes' => 'Använd JPEG, PNG eller WebP för efterbilderna.',
            'before_photos.*.max' => 'Varje förebild får vara högst 15 MB.',
            'after_photos.*.max' => 'Varje efterbild får vara högst 15 MB.',
            'before_photos.*.dimensions' => 'En förebild har för hög upplösning. Prova en mindre bild.',
            'after_photos.*.dimensions' => 'En efterbild har för hög upplösning. Prova en mindre bild.',
        ];
    }
}
