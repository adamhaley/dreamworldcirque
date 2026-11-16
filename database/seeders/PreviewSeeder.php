<?php

namespace Database\Seeders;

use App\Enums\ProspectClassification;
use App\Enums\ProspectStatus;
use App\Models\Prospect;
use Illuminate\Database\Seeder;

class PreviewSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('preview')) {
            return;
        }

        Prospect::withoutEvents(function (): void {
            foreach ($this->leads() as $lead) {
                Prospect::query()->updateOrCreate(['email' => $lead['email']], $lead);
            }
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function leads(): array
    {
        $base = [
            'source' => 'contact_form',
            'form_type' => 'Get_A_Quote Form Submission',
            'submission_count' => 1,
            'email_mx_valid' => true,
        ];

        return [
            [
                ...$base,
                'submitted_at' => now()->subDays(1),
                'name' => 'Priya Raman',
                'email' => 'priya.raman@example.com',
                'message' => 'We are planning a June wedding for 150 guests and would love aerialists during the cocktail hour. Is that something you can do?',
                'interests' => ['Wedding', 'Aerialists'],
                'status' => ProspectStatus::PendingReview,
                'classification' => ProspectClassification::NeedsHuman,
                'classification_confidence' => 62,
                'classification_reason' => 'Genuine wedding inquiry, but the venue ceiling height and date need a human to confirm.',
                'classified_at' => now()->subDays(1)->addMinutes(2),
            ],
            [
                ...$base,
                'submitted_at' => now()->subDays(2),
                'name' => 'Marcus Webb',
                'email' => 'marcus.webb@example.com',
                'message' => 'Our company is hosting an annual gala in October for about 400 guests. We are looking for roaming performers and a stage act.',
                'interests' => ['Corporate Event', 'Stage Show'],
                'status' => ProspectStatus::Valid,
                'classification' => ProspectClassification::Valid,
                'classification_confidence' => 96,
                'classification_reason' => 'Clear corporate gala request with date, headcount and performance types.',
                'classified_at' => now()->subDays(2)->addMinutes(1),
            ],
            [
                ...$base,
                'submitted_at' => now()->subDays(3),
                'name' => 'Elena Fischer',
                'email' => 'elena.fischer@example.com',
                'message' => 'I am a producer on an indie film shoot next month and we need circus costume rentals for a carnival scene. Do you rent out costumes?',
                'interests' => ['Film', 'Costume Rental'],
                'status' => ProspectStatus::DraftReady,
                'classification' => ProspectClassification::Valid,
                'classification_confidence' => 91,
                'classification_reason' => 'Legitimate production inquiry about costume rentals.',
                'classified_at' => now()->subDays(3)->addMinutes(1),
                'generated_reply' => "Hi Elena,\n\nThank you so much for reaching out. A carnival scene sounds wonderful, and we would love to help! We have a collection of vintage-inspired circus costumes available for rental. Could you tell me your shoot dates and how many performers you need to dress? I will pull together some options for you.\n\nWarmly,\nJena",
                'generation_reason' => 'Valid costume rental inquiry; asked for dates and headcount to quote accurately.',
                'generated_at' => now()->subDays(3)->addMinutes(5),
            ],
            [
                ...$base,
                'submitted_at' => now()->subDays(4),
                'name' => 'Tomás Alvarez',
                'email' => 'tomas.alvarez@example.com',
                'message' => 'Looking for a fire dancer and a juggler for my daughter\'s quinceañera in August. About 80 guests, outdoor backyard.',
                'interests' => ['Birthday Party', 'Fire Dancer', 'Juggler'],
                'status' => ProspectStatus::DraftReady,
                'classification' => ProspectClassification::Valid,
                'classification_confidence' => 94,
                'classification_reason' => 'Specific party request with date, guest count and acts.',
                'classified_at' => now()->subDays(4)->addMinutes(1),
                'generated_reply' => "Hi Tomás,\n\nCongratulations to your daughter! We would be honored to be part of her quinceañera. Fire and juggling are a great fit for an evening backyard party. Can you share the date and whether there is a clear, open area for the fire performance?\n\nWarmly,\nJena",
                'final_reply' => "Hi Tomás,\n\nCongratulations to your daughter, what a special milestone! A fire dancer and juggler would be a lovely fit for an August evening. For safety we need a 15 by 15 foot clear area away from trees. If that works, send me your date and I will put together a quote.\n\nWarmly,\nJena",
                'generation_reason' => 'Valid party inquiry; asked about date and space.',
                'generated_at' => now()->subDays(4)->addMinutes(5),
            ],
            [
                ...$base,
                'submitted_at' => now()->subDays(6),
                'name' => 'Hannah Obi',
                'email' => 'hannah.obi@example.com',
                'message' => 'We are a school fundraiser committee and wanted a clown workshop for the kids in May.',
                'interests' => ['Workshop', 'Clown'],
                'status' => ProspectStatus::Replied,
                'classification' => ProspectClassification::Valid,
                'classification_confidence' => 93,
                'classification_reason' => 'Genuine school workshop inquiry.',
                'classified_at' => now()->subDays(6)->addMinutes(1),
                'generated_reply' => "Hi Hannah,\n\nThank you for thinking of us! We would love to run a clown workshop for your students. What age group and how many kids?\n\nWarmly,\nJena",
                'final_reply' => "Hi Hannah,\n\nThank you for thinking of us! We would love to run a clown workshop for your students in May. What age group and how many kids are you expecting? I can send our workshop packages right away.\n\nWarmly,\nJena",
                'generation_reason' => 'Valid workshop inquiry; asked for age group and size.',
                'generated_at' => now()->subDays(6)->addMinutes(5),
                'replied_at' => now()->subDays(5),
            ],
            [
                ...$base,
                'submitted_at' => now()->subDays(7),
                'name' => 'Seo Boost Team',
                'email' => 'rank.fast@example.com',
                'message' => 'We can get your website to the first page of Google in 7 days! Reply now for a free audit.',
                'interests' => [],
                'status' => ProspectStatus::Spam,
                'classification' => ProspectClassification::Spam,
                'classification_confidence' => 99,
                'classification_reason' => 'Unsolicited SEO sales pitch unrelated to circus services.',
                'classified_at' => now()->subDays(7)->addMinutes(1),
            ],
            [
                ...$base,
                'submitted_at' => now()->subDays(8),
                'name' => 'Sam',
                'email' => 'sam.lee@example.com',
                'message' => 'Hi, how much?',
                'interests' => [],
                'status' => ProspectStatus::Unanswerable,
                'classification' => ProspectClassification::Unanswerable,
                'classification_confidence' => 85,
                'classification_reason' => 'No event details, date or service named, so there is nothing to reply to meaningfully.',
                'classified_at' => now()->subDays(8)->addMinutes(1),
            ],
        ];
    }
}
