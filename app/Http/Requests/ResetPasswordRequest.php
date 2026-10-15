<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
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
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Ange e-postadressen.',
            'email.email' => 'Skriv en giltig e-postadress.',
            'password.required' => 'Välj ett lösenord.',
            'password.min' => 'Lösenordet behöver vara minst 8 tecken.',
            'password.confirmed' => 'Lösenorden matchar inte.',
        ];
    }
}
