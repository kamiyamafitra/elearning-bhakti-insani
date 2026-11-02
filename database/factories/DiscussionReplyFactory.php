<?php

namespace Database\Factories;

use App\Models\DiscussionReply;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscussionReply>
 */
class DiscussionReplyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'body' => fake()->sentence(),
        ];
    }
}
