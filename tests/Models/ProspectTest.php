<?php

namespace Tests\Models;

use App\Enums\ProspectClassification;
use App\Enums\ProspectStatus;
use App\Jobs\ClassifyProspect;
use App\Jobs\GenerateProspectReply;
use App\Mail\ProspectReplyMail;
use App\Models\Prospect;
use App\Models\User;
use Generator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(Prospect::class)]
class ProspectTest extends TestCase
{
    #[Test]
    public function it_dispatches_classification_when_created_pending_classification(): void
    {
        Queue::fake();

        $prospect = Prospect::factory()->create();

        Queue::assertPushed(ClassifyProspect::class, fn (ClassifyProspect $job): bool => $job->prospectId === $prospect->id);
    }

    #[Test]
    public function it_does_not_dispatch_classification_for_prespecified_statuses(): void
    {
        Queue::fake();

        Prospect::factory()->spam()->create();

        Queue::assertNotPushed(ClassifyProspect::class);
    }

    #[Test]
    public function marking_valid_moves_to_draft_generating_and_dispatches_reply_generation(): void
    {
        Queue::fake();
        $reviewer = User::factory()->admin()->create();
        $prospect = Prospect::factory()->pendingReview()->create();

        $result = $prospect->markStatus(ProspectStatus::Valid, $reviewer->id, 'Looks legitimate.');

        $this->assertTrue($result);
        $prospect->refresh();
        $this->assertSame(ProspectStatus::DraftGenerating, $prospect->status);
        $this->assertSame($reviewer->id, $prospect->reviewed_by_user_id);
        $this->assertSame('Looks legitimate.', $prospect->review_note);
        $this->assertNotNull($prospect->reviewed_at);
        Queue::assertPushed(GenerateProspectReply::class, fn (GenerateProspectReply $job): bool => $job->prospectId === $prospect->id);
    }

    /**
     * @return Generator<string, array{ProspectStatus}>
     */
    public static function nonValidDecisionProvider(): Generator
    {
        yield 'spam' => [ProspectStatus::Spam];
        yield 'unanswerable' => [ProspectStatus::Unanswerable];
    }

    #[Test]
    #[DataProvider('nonValidDecisionProvider')]
    public function it_does_not_dispatch_reply_generation_for_non_valid_decisions(ProspectStatus $status): void
    {
        Queue::fake();
        $prospect = Prospect::factory()->pendingReview()->create();

        $prospect->markStatus($status, User::factory()->admin()->create()->id);

        Queue::assertNotPushed(GenerateProspectReply::class);
    }

    #[Test]
    #[DataProvider('nonValidDecisionProvider')]
    public function it_keeps_the_decided_status_for_non_valid_decisions(ProspectStatus $status): void
    {
        Queue::fake();
        $prospect = Prospect::factory()->pendingReview()->create();

        $prospect->markStatus($status, User::factory()->admin()->create()->id);

        $this->assertSame($status, $prospect->refresh()->status);
        $this->assertNotSame(ProspectStatus::DraftGenerating, $prospect->status);
    }

    #[Test]
    public function marking_replied_sends_the_final_reply_and_stamps_replied_at(): void
    {
        Mail::fake();
        Queue::fake();
        $reviewer = User::factory()->admin()->create();
        $prospect = Prospect::factory()->draftReady()->create();

        $prospect->markStatus(ProspectStatus::Replied, $reviewer->id);

        Mail::assertSent(ProspectReplyMail::class, fn (ProspectReplyMail $mail): bool => $mail->hasTo($prospect->email));
        $this->assertNotNull($prospect->refresh()->replied_at);
    }

    #[Test]
    public function marking_replied_without_an_email_does_not_send(): void
    {
        Mail::fake();
        Queue::fake();
        $prospect = Prospect::factory()->draftReady()->create(['email' => null]);

        $prospect->markStatus(ProspectStatus::Replied, User::factory()->admin()->create()->id);

        Mail::assertNothingSent();
        $this->assertNull($prospect->refresh()->replied_at);
    }

    #[Test]
    public function not_spam_excludes_ai_or_human_spam_classifications(): void
    {
        Queue::fake();

        $humanSpam = Prospect::factory()->create([
            'classification' => ProspectClassification::NeedsHuman,
            'human_classification' => ProspectClassification::Spam,
        ]);
        $aiSpam = Prospect::factory()->create([
            'classification' => ProspectClassification::Spam,
            'human_classification' => null,
        ]);
        $needsReview = Prospect::factory()->create([
            'classification' => ProspectClassification::NeedsHuman,
            'human_classification' => null,
        ]);

        $visible = Prospect::query()->notSpam()->pluck('id');

        $this->assertTrue($visible->contains($needsReview->id));
        $this->assertFalse($visible->contains($humanSpam->id));
        $this->assertFalse($visible->contains($aiSpam->id));
    }
}
