<?php

namespace Tests\Models;

use App\Enums\ProspectClassification;
use App\Enums\ProspectStatus;
use App\Events\RecordPipelineStatusChanged;
use App\Jobs\ClassifyProspect;
use App\Jobs\GenerateProspectReply;
use App\Mail\ProspectReplyMail;
use App\Models\Prospect;
use App\Models\User;
use Generator;
use Illuminate\Support\Facades\Event;
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

    #[Test]
    public function not_spam_lets_a_human_valid_override_ai_spam(): void
    {
        Queue::fake();

        $rescued = Prospect::factory()->create([
            'classification' => ProspectClassification::Spam,
            'human_classification' => ProspectClassification::Valid,
        ]);

        $this->assertTrue(Prospect::query()->notSpam()->pluck('id')->contains($rescued->id));
    }

    #[Test]
    public function reply_attachments_round_trip_as_an_array_of_entries(): void
    {
        Queue::fake();

        $prospect = Prospect::factory()->withReplyAttachments()->create();

        $this->assertEquals([
            ['disk' => 'public', 'path' => 'gallery/stilt-walker.jpg', 'name' => 'stilt-walker.jpg'],
            ['disk' => 'local', 'path' => 'prospect-replies/1/custom-quote.png', 'name' => 'custom-quote.png'],
        ], $prospect->refresh()->reply_attachments);
    }

    #[Test]
    public function reply_attachments_default_to_null(): void
    {
        Queue::fake();

        $prospect = Prospect::factory()->create();

        $this->assertNull($prospect->refresh()->reply_attachments);
    }

    public static function statusProvider(): Generator
    {
        foreach (ProspectStatus::cases() as $status) {
            yield $status->value => [$status];
        }
    }

    #[Test]
    #[DataProvider('statusProvider')]
    public function it_overrides_status_without_side_effects(ProspectStatus $status): void
    {
        Queue::fake();
        Mail::fake();
        $user = User::factory()->create();
        $prospect = Prospect::factory()->draftReady()->create();
        Queue::fake();
        Event::fake([RecordPipelineStatusChanged::class]);

        $result = $prospect->overrideStatus($status, $user->id);

        $this->assertTrue($result);
        $prospect->refresh();
        $this->assertSame($status, $prospect->status);
        $this->assertSame($user->id, $prospect->reviewed_by_user_id);
        $this->assertNotNull($prospect->reviewed_at);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Queue::assertNothingPushed();
        Event::assertDispatched(RecordPipelineStatusChanged::class);
    }

    #[Test]
    public function it_does_not_send_the_reply_when_overriding_to_replied(): void
    {
        Queue::fake();
        Mail::fake();
        $prospect = Prospect::factory()->draftReady()->withReplyAttachments()->create();

        $prospect->overrideStatus(ProspectStatus::Replied, null);

        $prospect->refresh();
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        $this->assertNull($prospect->replied_at);
        $this->assertNotNull($prospect->final_reply);
        $this->assertCount(2, $prospect->reply_attachments);
    }
}
