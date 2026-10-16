<?php

namespace App\Http\Requests;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssignmentTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $assignment = $this->route('assignment');

        if ($assignment instanceof Assignment && $user instanceof User) {
            abort_unless($user->company_id === $assignment->company_id, 404);
        }

        return $user instanceof User
            && $assignment instanceof Assignment
            && $user->isOwner()
            && $user->canManageJobs()
            && $user->company?->allowsTeamFeatures() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'assigned_to_user_id' => [
                'present',
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('company_id', $companyId),
            ],
            'reviewer_user_id' => [
                'present',
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('company_id', $companyId),
            ],
        ];
    }
}
