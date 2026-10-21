<?php

namespace App\Http\Requests;

use App\Support\FormSpam;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
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
            'company_name' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:254', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'privacy' => ['accepted'],
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (FormSpam::turnstileRejected($this)) {
                    $validator->errors()->add(FormSpam::TURNSTILE_FIELD, 'Bekräfta att du inte är en robot och försök igen.');
                }
            },
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
            'name.required' => 'Ange ditt namn.',
            'name.max' => 'Namnet får vara högst 120 tecken.',
            'email.required' => 'Ange e-postadressen.',
            'email.email' => 'Skriv en giltig e-postadress.',
            'email.max' => 'E-postadressen får vara högst 254 tecken.',
            'email.unique' => 'Det finns redan ett konto med den e-postadressen.',
            'password.required' => 'Välj ett lösenord.',
            'password.min' => 'Lösenordet behöver vara minst 8 tecken.',
            'password.confirmed' => 'Lösenorden matchar inte.',
            'privacy.accepted' => 'Godkänn integritetspolicyn för att skapa kontot.',
        ];
    }
}
