<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\AssignmentReportResponse;
use App\Models\AssignmentReportVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AssignmentReportResponse>
 */
class AssignmentReportResponseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_report_version_id' => AssignmentReportVersion::factory(),
            'assignment_id' => fn (array $attributes): int => (int) AssignmentReportVersion::query()->findOrFail($attributes['assignment_report_version_id'])->assignment_id,
            'company_id' => fn (array $attributes): int => (int) Assignment::query()->findOrFail($attributes['assignment_id'])->company_id,
            'type' => AssignmentReportResponse::TYPE_COMMENT,
            'response_key' => Str::uuid()->toString(),
            'message' => fake()->sentence(),
        ];
    }
}
