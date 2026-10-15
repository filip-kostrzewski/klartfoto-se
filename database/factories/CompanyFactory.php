<?php

namespace Database\Factories;

use App\CompanyPlan;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Glans Städ AB',
            'plan' => CompanyPlan::Start,
            'trial_ends_at' => now()->addDays(30),
        ];
    }

    public function team(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => CompanyPlan::Team,
        ]);
    }

    public function expiredTrial(): static
    {
        return $this->state(fn (array $attributes) => [
            'trial_ends_at' => now()->subDay(),
        ]);
    }
}
