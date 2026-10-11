<?php

namespace Database\Factories;

use App\Enums\PageTemplate;
use App\Models\ServicePhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServicePhoto>
 */
class ServicePhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'page_id' => PageFactory::new()->template(PageTemplate::Service),
            'image' => 'service-photos/'.fake()->uuid().'.jpg',
            'alt' => fake()->sentence(4),
            'caption' => fake()->sentence(3),
            'sort_order' => 0,
            'is_placeholder' => false,
        ];
    }
}
