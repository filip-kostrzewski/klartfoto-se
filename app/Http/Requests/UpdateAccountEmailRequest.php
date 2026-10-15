<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountEmailRequest extends FormRequest
{
    protected $errorBag = 'accountEmail';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'account_email' => [
                'required',
                'string',
                'email',
                'max:254',
                Rule::unique('users', 'email')->ignore($this->user()?->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_email.required' => 'Ange e-postadressen.',
            'account_email.email' => 'Skriv en giltig e-postadress.',
            'account_email.max' => 'E-postadressen får vara högst 254 tecken.',
            'account_email.unique' => 'Det finns redan ett konto med den e-postadressen.',
        ];
    }
}
