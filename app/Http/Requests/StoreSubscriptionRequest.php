<?php

namespace App\Http\Requests;

use App\CompanyPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->user()?->company;

        return $company !== null && ($this->user()?->can('update', $company) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan' => ['required', Rule::in([CompanyPlan::Start->value, CompanyPlan::Team->value])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan.required' => 'Välj en plan.',
            'plan.in' => 'Välj Start eller Team.',
        ];
    }
}
