<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AcceptCompanyInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'privacy' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Ange ditt namn.',
            'name.max' => 'Namnet får vara högst 120 tecken.',
            'password.required' => 'Välj ett lösenord.',
            'password.min' => 'Lösenordet behöver vara minst 8 tecken.',
            'password.confirmed' => 'Lösenorden matchar inte.',
            'privacy.accepted' => 'Godkänn integritetspolicyn för att skapa kontot.',
        ];
    }
}
