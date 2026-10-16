<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesJobPhotos;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAssignmentRequest extends FormRequest
{
    use ValidatesJobPhotos;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $times = [];

        foreach (['performed_time', 'performed_end_time'] as $field) {
            if ($this->input($field) === '') {
                $times[$field] = null;
            }
        }

        if ($times !== []) {
            $this->merge($times);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $start = $this->input('performed_time');
            $end = $this->input('performed_end_time');

            if (! is_string($start) || ! is_string($end) || $start === '' || $end === '') {
                return;
            }

            if ($end <= $start) {
                $validator->errors()->add('performed_end_time', 'Sluttiden måste vara senare än starttiden.');
            }
        });
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
            'performed_end_time' => ['nullable', 'date_format:H:i'],
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
            'performed_end_time.date_format' => 'Ange en giltig tid.',
            ...$this->photoMessages(),
        ];
    }
}
