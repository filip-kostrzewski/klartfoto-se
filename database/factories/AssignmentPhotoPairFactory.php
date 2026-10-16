<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\AssignmentPhotoPair;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentPhotoPair>
 */
class AssignmentPhotoPairFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'position' => 0,
            'room_name' => null,
            'note' => null,
        ];
    }
}
