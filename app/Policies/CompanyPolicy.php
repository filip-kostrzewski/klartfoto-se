<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CompanyPolicy
{
    public function view(User $user, Company $company): Response
    {
        return $user->company_id !== null && $user->company_id === $company->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Company $company): Response
    {
        if ($user->company_id !== $company->id) {
            return Response::denyAsNotFound();
        }

        return $user->isOwner()
            ? Response::allow()
            : Response::deny();
    }
}
