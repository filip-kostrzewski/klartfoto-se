<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesJobPhotos;
use App\Models\CustomerProperty;
use App\Support\SwedishDate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAssignmentRequest extends FormRequest
{
    use ValidatesJobPhotos;

    public const START_TIME_REQUIRED = 'Ange en starttid när sluttiden är ifylld.';

    protected function prepareForValidation(): void
    {
        $times = [
            'performed_on' => SwedishDate::toIso($this->input('performed_on')),
        ];

        foreach (['performed_time', 'performed_end_time'] as $field) {
            if ($this->input($field) === '') {
                $times[$field] = null;
            }
        }

        if ($times !== []) {
            $this->merge($times);
        }
    }

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
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_email' => ['nullable', 'email:filter', 'max:254'],
            'address' => ['required', 'string', 'max:255'],
            'performed_on' => ['required', 'date', 'after:1990-01-01', 'before:2100-01-01'],
            'performed_time' => ['nullable', 'date_format:H:i'],
            'performed_end_time' => ['nullable', 'date_format:H:i'],
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where('company_id', $this->user()?->company_id),
            ],
            'customer_property_id' => [
                'nullable',
                'integer',
                Rule::exists('customer_properties', 'id')->where('company_id', $this->user()?->company_id),
            ],
            'place_name' => ['nullable', 'string', 'max:120'],
            ...$this->photoRules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectDroppedPhotos($validator);

            $start = $this->input('performed_time');
            $end = $this->input('performed_end_time');
            $startIsSet = is_string($start) && $start !== '';
            $endIsSet = is_string($end) && $end !== '';

            if ($endIsSet && ! $startIsSet) {
                $validator->errors()->add('performed_time', self::START_TIME_REQUIRED);
            } elseif (
                $startIsSet
                && $endIsSet
                && preg_match('/^\d{2}:\d{2}$/', $start)
                && preg_match('/^\d{2}:\d{2}$/', $end)
                && $end <= $start
            ) {
                $validator->errors()->add('performed_end_time', 'Sluttiden måste vara senare än starttiden.');
            }

            $this->validateUploadShape($validator);

            $propertyId = $this->input('customer_property_id');
            $customerId = $this->input('customer_id');

            if (filled($propertyId) && blank($customerId)) {
                $validator->errors()->add('customer_property_id', 'Välj kunden som adressen tillhör.');

                return;
            }

            if (filled($propertyId) && filled($customerId)) {
                $belongsToCustomer = CustomerProperty::query()
                    ->where('company_id', $this->user()?->company_id)
                    ->where('customer_id', $customerId)
                    ->whereKey($propertyId)
                    ->exists();

                if (! $belongsToCustomer) {
                    $validator->errors()->add('customer_property_id', 'Välj en adress tillhörande den valda kunden.');
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_name.required' => 'Ange kundens namn.',
            'customer_name.max' => 'Kundens namn får vara högst 120 tecken.',
            'customer_email.email' => 'Ange en giltig e-postadress till kunden.',
            'customer_email.max' => 'E-postadressen får vara högst 254 tecken.',
            'address.required' => 'Ange adressen.',
            'address.max' => 'Adressen får vara högst 255 tecken.',
            'performed_on.required' => 'Ange datumet för städningen.',
            'performed_on.date' => 'Ange ett giltigt datum.',
            'performed_on.after' => 'Ange ett giltigt datum.',
            'performed_on.before' => 'Ange ett giltigt datum.',
            'performed_time.date_format' => 'Ange en giltig tid.',
            'performed_end_time.date_format' => 'Ange en giltig tid.',
            'place_name.max' => 'Platsens namn får vara högst 120 tecken.',
            ...$this->photoMessages(),
        ];
    }
}
