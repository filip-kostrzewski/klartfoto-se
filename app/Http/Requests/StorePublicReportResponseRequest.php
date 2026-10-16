<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePublicReportResponseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'response_type' => ['required', 'string', 'in:received,comment'],
            'message' => ['prohibited_unless:response_type,comment', 'required_if:response_type,comment', 'string', 'max:1000'],
            'place_index' => ['prohibited_unless:response_type,comment', 'required_if:response_type,comment', 'integer', 'min:0', 'max:65535'],
            'pair_index' => ['prohibited_unless:response_type,comment', 'required_if:response_type,comment', 'integer', 'min:0', 'max:65535'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('message'))) {
            $this->merge(['message' => trim($this->input('message'))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'response_type.required' => 'Välj en typ av svar.',
            'response_type.in' => 'Välj ett giltigt svar.',
            'message.required_if' => 'Skriv en kommentar.',
            'message.max' => 'Kommentaren får innehålla högst 1 000 tecken.',
            'place_index.required_if' => 'Välj platsen som kommentaren gäller.',
            'pair_index.required_if' => 'Välj bildparet som kommentaren gäller.',
        ];
    }
}
