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
        return $this->photoRules();
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
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $before = $this->file('before_photos', []);
            $after = $this->file('after_photos', []);
            $beforeCount = is_array($before) ? count($before) : 0;
            $afterCount = is_array($after) ? count($after) : 0;

            if ($beforeCount === 0 && $afterCount === 0) {
                $validator->errors()->add('before_photos', 'Lägg till minst en före- eller efterbild.');
            }
        });
    }
}
