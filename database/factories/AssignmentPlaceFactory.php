<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\AssignmentPlace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentPlace>
 */
class AssignmentPlaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'name' => fake()->word(),
            'sort_order' => fake()->numberBetween(1, 5),
        ];
    }
}
