<?php

namespace App\Http\Requests;

use App\Models\Assignment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SendAssignmentEmailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');

        abort_unless($assignment instanceof Assignment, 404);
        Gate::authorize('view', $assignment);

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'recipient_email' => ['required', 'email:filter', 'max:254'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recipient_email.required' => 'Ange kundens e-postadress.',
            'recipient_email.email' => 'Ange en giltig e-postadress.',
            'recipient_email.max' => 'E-postadressen får vara högst 254 tecken.',
            'message.string' => 'Meddelandet måste vara vanlig text.',
            'message.max' => 'Meddelandet får vara högst 1 000 tecken.',
        ];
    }
}
