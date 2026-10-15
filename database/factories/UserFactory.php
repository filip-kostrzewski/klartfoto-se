<?php

namespace Database\Factories;

use App\CompanyRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'trial_ends_at' => now()->addDays(30),
            'role' => CompanyRole::Owner,
            'remember_token' => Str::random(10),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (User $user): void {
            if ($user->company_id !== null) {
                return;
            }

            $company = Company::factory()->create([
                'name' => $user->name,
                'trial_ends_at' => $user->trial_ends_at,
            ]);

            $user->company_id = $company->id;
            $user->role ??= CompanyRole::Owner;
        });
    }

    public function memberOf(Company $company): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $company->id,
            'role' => CompanyRole::Member,
            'trial_ends_at' => null,
        ]);
    }

    public function expiredTrial(): static
    {
        return $this->state(fn (array $attributes) => [
            'trial_ends_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
