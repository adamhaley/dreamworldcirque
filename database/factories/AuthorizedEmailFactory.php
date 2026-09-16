<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\AuthorizedEmail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuthorizedEmail>
 */
class AuthorizedEmailFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'role' => UserRole::Editor,
            'is_active' => true,
        ];
    }
}
