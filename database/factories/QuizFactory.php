<?php

namespace Database\Factories;

use App\Models\Quiz;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quiz>
 */
class QuizFactory extends Factory
{
    /**
     * Define the model's default state. Subject, class, and teacher are supplied by the caller.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Kuis '.fake()->word(),
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addHours(2),
            'duration_minutes' => 30,
            'shuffles' => false,
            'status' => Quiz::STATUS_PUBLISHED,
        ];
    }
}
