<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAssignmentPhotoPairNotesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['label', 'customer_comment', 'internal_note'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $normalized[$field] = trim($value) === '' ? null : trim($value);
            }
        }

        $this->merge($normalized);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:120'],
            'customer_comment' => ['nullable', 'string', 'max:1000'],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.max' => 'Bildens namn får vara högst 120 tecken.',
            'customer_comment.max' => 'Kundkommentaren får vara högst 1000 tecken.',
            'internal_note.max' => 'Den interna anteckningen får vara högst 2000 tecken.',
        ];
    }
}
