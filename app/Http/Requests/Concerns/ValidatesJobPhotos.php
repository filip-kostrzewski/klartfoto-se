<?php

namespace App\Http\Requests\Concerns;

use App\Models\Assignment;
use App\Models\AssignmentPhotoPair;
use App\Rules\AcceptedJobPhoto;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\UploadedFile;

trait ValidatesJobPhotos
{
    /**
     * @return array<string, array<int, AcceptedJobPhoto|string>>
     */
    protected function photoRules(): array
    {
        $fileRules = ['file', new AcceptedJobPhoto];

        return [
            'before_photos' => ['nullable', 'array', 'max:10'],
            'before_photos.*' => $fileRules,
            'after_photos' => ['nullable', 'array', 'max:10'],
            'after_photos.*' => $fileRules,
            'before_upload_ids' => ['sometimes', 'array', 'max:10'],
            'before_upload_ids.*' => ['required', 'uuid'],
            'after_upload_ids' => ['sometimes', 'array', 'max:10'],
            'after_upload_ids.*' => ['required', 'uuid'],
        ];
    }

    protected function validateUploadShape(Validator $validator, bool $requirePhotos = false, bool $singlePair = false): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $beforeFiles = $this->filesForUpload('before_photos');
        $afterFiles = $this->filesForUpload('after_photos');
        $beforeIds = $this->input('before_upload_ids');
        $afterIds = $this->input('after_upload_ids');

        if (is_array($beforeIds) && count($beforeIds) !== count($beforeFiles)) {
            $validator->errors()->add('before_upload_ids', 'Varje förebild behöver en egen uppladdningsnyckel.');
        }

        if (is_array($afterIds) && count($afterIds) !== count($afterFiles)) {
            $validator->errors()->add('after_upload_ids', 'Varje efterbild behöver en egen uppladdningsnyckel.');
        }

        $submittedIds = array_merge(
            is_array($beforeIds) ? array_values($beforeIds) : [],
            is_array($afterIds) ? array_values($afterIds) : [],
        );
        if (count($submittedIds) !== count(array_unique($submittedIds))) {
            $validator->errors()->add('before_upload_ids', 'Varje bild behöver en unik uppladdningsnyckel.');
        }

        if ($requirePhotos && $beforeFiles === [] && $afterFiles === []) {
            $validator->errors()->add('before_photos', 'Lägg till minst en före- eller efterbild.');
        }

        if ($singlePair && (count($beforeFiles) > 1 || count($afterFiles) > 1)) {
            $validator->errors()->add('before_photos', 'Lägg till högst en bild på varje sida i det valda bildparet.');
        }
    }

    protected function validateTargetPhotoPairScope(Validator $validator): void
    {
        $pairId = $this->input('assignment_photo_pair_id');
        $assignment = $this->route('assignment');

        if (blank($pairId) || ! $assignment instanceof Assignment || $validator->errors()->isNotEmpty()) {
            return;
        }

        $pairIsInAssignment = AssignmentPhotoPair::query()
            ->whereKey($pairId)
            ->whereHas('place', fn ($query) => $query->where('assignment_id', $assignment->id))
            ->exists();

        abort_unless($pairIsInAssignment, 404);
    }

    /**
     * @return list<UploadedFile>
     */
    private function filesForUpload(string $key): array
    {
        $files = $this->file($key, []);

        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (! is_array($files)) {
            return [];
        }

        return array_values(array_filter($files, fn (mixed $file): bool => $file instanceof UploadedFile));
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
