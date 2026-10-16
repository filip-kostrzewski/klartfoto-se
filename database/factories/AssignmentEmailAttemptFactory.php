<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\AssignmentEmailAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentEmailAttempt>
 */
class AssignmentEmailAttemptFactory extends Factory
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
            'company_id' => null,
            'user_id' => null,
            'recipient_email' => fake()->safeEmail(),
            'status' => AssignmentEmailAttempt::STATUS_SENDING,
            'sent_at' => null,
            'failed_at' => null,
            'unknown_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (AssignmentEmailAttempt $attempt): void {
            $assignment = Assignment::query()->find($attempt->assignment_id);

            if ($assignment === null) {
                return;
            }

            $attempt->company_id ??= $assignment->company_id;
            $attempt->user_id ??= $assignment->user_id;
        });
    }
}
