<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerProperty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerProperty>
 */
class CustomerPropertyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'customer_id' => Customer::factory(),
            'label' => fake()->words(2, true),
            'address' => fake()->streetAddress(),
        ];
    }
}
