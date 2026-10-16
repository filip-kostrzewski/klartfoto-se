<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->isOwner() && $user->company_id !== null;
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('company_name');

        $this->merge([
            'company_name' => is_string($name) ? trim($name) : $name,
            'phone' => $this->blank($this->input('phone')),
            'organization_number' => $this->blank($this->input('organization_number')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'organization_number' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_name.required' => 'Ange företagsnamnet.',
            'company_name.max' => 'Företagsnamnet får vara högst 120 tecken.',
            'phone.max' => 'Telefonnumret får vara högst 40 tecken.',
            'organization_number.max' => 'Organisationsnumret får vara högst 32 tecken.',
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
