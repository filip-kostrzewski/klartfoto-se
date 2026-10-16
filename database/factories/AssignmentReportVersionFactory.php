<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\AssignmentReportVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AssignmentReportVersion>
 */
class AssignmentReportVersionFactory extends Factory
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
            'version_number' => 1,
            'token' => Str::random(48),
            'status' => AssignmentReportVersion::STATUS_READY,
            'snapshot' => [],
            'snapshot_hash' => hash('sha256', '[]'),
        ];
    }
}
