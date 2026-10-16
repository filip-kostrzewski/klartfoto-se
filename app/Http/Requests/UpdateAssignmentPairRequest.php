<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAssignmentPairRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'room_name' => $this->blank($this->input('room_name')),
            'note' => $this->blank($this->input('note')),
            'pair_id' => $this->filled('pair_id') ? $this->input('pair_id') : null,
            'before_photo_id' => $this->filled('before_photo_id') ? $this->input('before_photo_id') : null,
            'after_photo_id' => $this->filled('after_photo_id') ? $this->input('after_photo_id') : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pair_key' => ['required', 'string', 'max:40'],
            'pair_id' => ['nullable', 'integer'],
            'before_photo_id' => ['nullable', 'integer'],
            'after_photo_id' => ['nullable', 'integer'],
            'room_name' => ['nullable', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'room_name.max' => 'Rummets namn får vara högst 80 tecken.',
            'note.max' => 'Anteckningen får vara högst 500 tecken.',
        ];
    }

    private function blank(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
