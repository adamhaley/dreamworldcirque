<?php

namespace Tests\Notifications;

use App\Enums\ProspectClassification;
use App\Models\Prospect;
use App\Notifications\NewContactSubmission;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(NewContactSubmission::class)]
class NewContactSubmissionTest extends TestCase
{
    #[Test]
    public function it_includes_the_contact_details_and_opens_the_prospect_review(): void
    {
        $prospect = Prospect::factory()->make([
            'id' => 42,
            'name' => 'Jo Riggs',
            'email' => 'jo@example.com',
            'phone' => '555-0100',
            'form_type' => 'Home Form Submission',
            'message' => 'We would love a quote for a corporate gala.',
        ]);

        $mail = (new NewContactSubmission($prospect))->toMail(new AnonymousNotifiable);

        $this->assertSame('New Dream World Cirque inquiry', $mail->subject);
        $this->assertSame([
            'Name: Jo Riggs',
            'Email: jo@example.com',
            'Phone: 555-0100',
            'Form: Home Form Submission',
            'Message: We would love a quote for a corporate gala.',
        ], $mail->introLines);
        $this->assertSame('Review inquiry', $mail->actionText);
        $this->assertStringContainsString('/admin/prospects?tableAction=review&tableActionRecord=42', $mail->actionUrl);
    }

    #[Test]
    public function it_omits_a_missing_phone_number(): void
    {
        $prospect = Prospect::factory()->make(['phone' => null]);

        $mail = (new NewContactSubmission($prospect))->toMail(new AnonymousNotifiable);

        $this->assertCount(4, $mail->introLines);
    }

    /** @return iterable<string, array{?ProspectClassification, ?int, bool}> */
    public static function classificationCases(): iterable
    {
        yield 'classification pending' => [null, null, true];
        yield 'spam below threshold' => [ProspectClassification::Spam, 94, true];
        yield 'high-confidence spam' => [ProspectClassification::Spam, 95, false];
        yield 'high-confidence valid lead' => [ProspectClassification::Valid, 99, true];
    }

    #[Test]
    #[DataProvider('classificationCases')]
    public function it_sends_unless_the_current_classification_is_high_confidence_spam(
        ?ProspectClassification $classification,
        ?int $confidence,
        bool $expectedToSend,
    ): void {
        Queue::fake();
        $prospect = Prospect::factory()->create();
        $notification = new NewContactSubmission($prospect);
        Prospect::query()->whereKey($prospect->id)->update([
            'classification' => $classification?->value,
            'classification_confidence' => $confidence,
        ]);

        $this->assertSame($expectedToSend, $notification->shouldSend(new AnonymousNotifiable, 'mail'));
    }
}
