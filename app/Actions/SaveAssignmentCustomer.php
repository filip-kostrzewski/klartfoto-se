<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerProperty;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveAssignmentCustomer
{
    /**
     * @return array{customer: Customer, property: CustomerProperty}
     */
    public function handle(
        User $user,
        string $name,
        ?string $email,
        string $address,
        ?int $customerId = null,
        ?int $propertyId = null,
    ): array {
        return DB::transaction(function () use ($user, $name, $email, $address, $customerId, $propertyId): array {
            abort_if($user->company_id === null, 403);

            $company = Company::query()->whereKey($user->company_id)->lockForUpdate()->firstOrFail();
            $customer = $customerId === null
                ? $this->findOrCreateCustomer($company, $name, $email)
                : Customer::query()
                    ->where('company_id', $company->id)
                    ->lockForUpdate()
                    ->findOrFail($customerId);

            if ($customerId !== null) {
                $customer->forceFill(['name' => $name, 'email' => $email])->save();
            }

            $property = $propertyId === null
                ? $customer->properties()->where('address', $address)->lockForUpdate()->first()
                : CustomerProperty::query()
                    ->where('company_id', $company->id)
                    ->where('customer_id', $customer->id)
                    ->lockForUpdate()
                    ->findOrFail($propertyId);

            if ($property === null) {
                $property = $company->customerProperties()->create([
                    'customer_id' => $customer->id,
                    'label' => null,
                    'address' => $address,
                ]);
            } elseif ($propertyId !== null) {
                $property->forceFill(['address' => $address])->save();
            }

            return ['customer' => $customer, 'property' => $property];
        });
    }

    private function findOrCreateCustomer(Company $company, string $name, ?string $email): Customer
    {
        if (filled($email)) {
            $existing = $company->customers()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Customer) {
                $existing->forceFill(['name' => $name, 'email' => $email])->save();

                return $existing;
            }
        }

        return $company->customers()->create(['name' => $name, 'email' => $email]);
    }
}
