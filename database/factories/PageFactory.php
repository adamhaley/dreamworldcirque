<?php

namespace Database\Factories;

use App\Enums\PageTemplate;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(2, true);

        return [
            'title' => ucfirst($title),
            'slug' => str($title)->slug()->toString(),
            'template' => PageTemplate::Standard,
            'is_published' => true,
            'hero_heading' => ucfirst($title),
            'hero_subheading' => fake()->sentence(),
            'body' => '<p>'.fake()->paragraph().'</p>',
        ];
    }

    public function template(PageTemplate $template): static
    {
        return $this->state(fn (array $attributes): array => [
            'template' => $template,
        ]);
    }

    public function home(): static
    {
        return $this->state(fn (array $attributes): array => [
            'title' => 'Home',
            'slug' => null,
            'template' => PageTemplate::Home,
        ]);
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_published' => false,
        ]);
    }
}
