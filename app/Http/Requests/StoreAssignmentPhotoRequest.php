<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesJobPhotos;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreAssignmentPhotoRequest extends FormRequest
{
    use ValidatesJobPhotos;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'place_name' => ['nullable', 'string', 'max:120'],
            'assignment_photo_pair_id' => ['nullable', 'integer', 'min:1'],
            'defer_report_version' => ['sometimes', 'boolean'],
            ...$this->photoRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->photoMessages();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectDroppedPhotos($validator);
            $this->validateUploadShape(
                $validator,
                requirePhotos: true,
                singlePair: $this->filled('assignment_photo_pair_id'),
            );
            $this->validateTargetPhotoPairScope($validator);
        });
    }
}
