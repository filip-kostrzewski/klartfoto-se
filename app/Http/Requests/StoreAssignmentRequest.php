<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesJobPhotos;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAssignmentRequest extends FormRequest
{
    use ValidatesJobPhotos;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('performed_time') === '') {
            $this->merge(['performed_time' => null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:255'],
            'performed_on' => ['required', 'date', 'after:1990-01-01', 'before:2100-01-01'],
            'performed_time' => ['nullable', 'date_format:H:i'],
            ...$this->photoRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_name.required' => 'Ange kundens namn.',
            'customer_name.max' => 'Kundens namn får vara högst 120 tecken.',
            'address.required' => 'Ange adressen.',
            'address.max' => 'Adressen får vara högst 255 tecken.',
            'performed_on.required' => 'Ange datumet för städningen.',
            'performed_on.date' => 'Ange ett giltigt datum.',
            'performed_on.after' => 'Ange ett giltigt datum.',
            'performed_on.before' => 'Ange ett giltigt datum.',
            'performed_time.date_format' => 'Ange en giltig tid.',
            ...$this->photoMessages(),
        ];
    }
}
