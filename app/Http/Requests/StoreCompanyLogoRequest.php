<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->user()?->company;

        return $company instanceof Company && ($this->user()?->can('update', $company) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'logo' => ['required', 'file', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.required' => 'Välj en logotyp att ladda upp.',
            'logo.file' => 'Logotypen behöver vara en PNG-, JPEG- eller SVG-fil.',
            'logo.max' => 'Logotypen får vara högst 5 MB.',
        ];
    }
}
