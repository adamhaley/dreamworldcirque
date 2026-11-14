<?php

namespace Database\Factories;

use App\Models\ReplyExample;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReplyExample>
 */
class ReplyExampleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'inquiry' => fake()->paragraph(),
            'reply' => fake()->paragraphs(2, true),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
