<?php

namespace App\Http\Requests;

use App\CompanyPlan;
use App\Models\Company;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualBillingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->user()?->company;

        return $company instanceof Company && ($this->user()?->can('update', $company) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['plan', 'billing_company_name', 'organisation_number', 'invoice_email', 'billing_address'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $normalized[$field] = trim($value);
            }
        }

        if (isset($normalized['invoice_email'])) {
            $normalized['invoice_email'] = mb_strtolower($normalized['invoice_email']);
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan' => ['required', 'string', Rule::in([CompanyPlan::Start->value, CompanyPlan::Team->value])],
            'billing_company_name' => ['required', 'string', 'max:255'],
            'organisation_number' => ['required', 'string', 'max:64'],
            'invoice_email' => ['required', 'string', 'email', 'max:254'],
            'billing_address' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan.required' => 'Välj Start eller Team.',
            'plan.in' => 'Välj Start eller Team.',
            'billing_company_name.required' => 'Ange företagsnamnet för fakturan.',
            'billing_company_name.max' => 'Företagsnamnet får vara högst 255 tecken.',
            'organisation_number.required' => 'Ange organisationsnumret.',
            'organisation_number.max' => 'Organisationsnumret får vara högst 64 tecken.',
            'invoice_email.required' => 'Ange e-postadressen för fakturan.',
            'invoice_email.email' => 'Ange en giltig e-postadress för fakturan.',
            'invoice_email.max' => 'E-postadressen får vara högst 254 tecken.',
            'billing_address.required' => 'Ange hela fakturaadressen.',
            'billing_address.max' => 'Fakturaadressen får vara högst 2 000 tecken.',
        ];
    }
}
