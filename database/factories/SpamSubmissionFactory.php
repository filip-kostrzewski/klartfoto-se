<?php

namespace Database\Factories;

use App\Models\SpamSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpamSubmission>
 */
class SpamSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form' => 'contact',
            'payload' => [
                'name' => 'Bot',
                'email' => 'bot@example.com',
                'message' => 'Automated submission',
            ],
            'reason' => 'honeypot',
            'ip' => '192.0.2.1',
        ];
    }
}
