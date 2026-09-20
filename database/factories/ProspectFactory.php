<?php

namespace Database\Factories;

use App\Enums\ProspectClassification;
use App\Enums\ProspectStatus;
use App\Models\Prospect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prospect>
 */
class ProspectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => 'contact_form',
            'source_external_id' => (string) fake()->unique()->randomNumber(8),
            'submitted_at' => fake()->dateTimeBetween('-1 year'),
            'form_type' => 'Get_A_Quote Form Submission',
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->numerify('##########'),
            'website' => null,
            'message' => fake()->paragraph(),
            'interests' => [fake()->randomElement(['Wedding', 'Corporate Event', 'Birthday Party'])],
            'status' => ProspectStatus::PendingClassification,
        ];
    }

    public function pendingReview(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProspectStatus::PendingReview,
            'classification' => ProspectClassification::Valid,
            'classification_confidence' => 90,
            'classification_reason' => 'Looks like a genuine booking inquiry.',
            'classified_at' => now(),
        ]);
    }

    public function draftReady(): static
    {
        return $this->pendingReview()->state(fn (array $attributes): array => [
            'status' => ProspectStatus::DraftReady,
            'generated_reply' => "Hi there,\n\nThanks for reaching out!\n\nThe Dream World Cirque Team",
            'final_reply' => "Hi there,\n\nThanks for reaching out!\n\nThe Dream World Cirque Team",
            'generation_reason' => 'Drafted a warm acknowledgement.',
            'generated_at' => now(),
        ]);
    }

    public function spam(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProspectStatus::Spam,
            'review_note' => 'Likely spam per legacy mailbox recovery (2026-09-02 automated flagging).',
        ]);
    }
}
