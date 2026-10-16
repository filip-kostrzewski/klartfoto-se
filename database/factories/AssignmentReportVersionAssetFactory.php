<?php

namespace Database\Factories;

use App\Models\AssignmentReportVersion;
use App\Models\AssignmentReportVersionAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentReportVersionAsset>
 */
class AssignmentReportVersionAssetFactory extends Factory
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
            'type' => AssignmentReportVersionAsset::TYPE_PHOTO,
            'source_photo_id' => fake()->unique()->numberBetween(1, 100000),
            'phase' => 'before',
            'place_name' => 'Övrigt',
            'place_sort_order' => 0,
            'pair_sort_order' => 1,
            'path' => 'assignments/1/example.jpg',
        ];
    }
}
