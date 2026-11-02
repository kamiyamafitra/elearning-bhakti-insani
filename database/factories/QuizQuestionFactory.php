<?php

namespace Database\Factories;

use App\Models\QuizQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizQuestion>
 */
class QuizQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question' => fake()->sentence().'?',
            'position' => 1,
        ];
    }

    /**
     * Add answer options; the first one is correct.
     */
    public function withOptions(int $count = 3): static
    {
        return $this->afterCreating(function (QuizQuestion $question) use ($count): void {
            foreach (range(1, $count) as $position) {
                $question->options()->create([
                    'text' => "Pilihan {$position}",
                    'is_correct' => $position === 1,
                    'position' => $position,
                ]);
            }
        });
    }
}
