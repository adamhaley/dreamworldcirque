<?php

namespace Database\Factories;

use App\Models\GalleryPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GalleryPhoto>
 */
class GalleryPhotoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'image' => 'gallery/'.fake()->uuid().'.jpg',
            'alt' => fake()->sentence(4),
            'caption' => fake()->sentence(3),
            'in_homepage_carousel' => false,
        ];
    }

    public function inHomepageCarousel(): static
    {
        return $this->state(fn (array $attributes): array => [
            'in_homepage_carousel' => true,
        ]);
    }
}
