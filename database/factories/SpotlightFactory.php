<?php

namespace Database\Factories;

use App\Enums\ButtonStyle;
use App\Enums\SpotlightImageSide;
use App\Models\Spotlight;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Spotlight>
 */
class SpotlightFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'eyebrow' => fake()->words(2, true),
            'heading' => fake()->sentence(3),
            'body' => fake()->paragraph(),
            'bullets' => [fake()->sentence(), fake()->sentence()],
            'image_side' => SpotlightImageSide::Right,
            'buttons' => [['label' => 'Learn more', 'url' => '/about/', 'style' => ButtonStyle::Primary->value]],
        ];
    }
}
