<?php

namespace Database\Factories;

use App\Models\AssignmentPhotoPair;
use App\Models\AssignmentPlace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentPhotoPair>
 */
class AssignmentPhotoPairFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_place_id' => AssignmentPlace::factory(),
            'label' => null,
            'customer_comment' => null,
            'internal_note' => null,
            'sort_order' => fake()->numberBetween(1, 5),
        ];
    }
}
