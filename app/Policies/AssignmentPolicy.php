<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Assignment $assignment): Response
    {
        return $this->owns($user, $assignment);
    }

    public function create(User $user): bool
    {
        return $user->onTrial();
    }

    public function update(User $user, Assignment $assignment): Response
    {
        return $this->owns($user, $assignment);
    }

    private function owns(User $user, Assignment $assignment): Response
    {
        return $user->id === $assignment->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
