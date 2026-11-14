<?php

namespace Tests\Services\Prospects;

use App\Ai\Agents\ProspectReplyGenerator;
use App\Enums\ProspectStatus;
use App\Models\Prospect;
use App\Models\ReplyExample;
use App\Services\Prospects\ProspectReplyGenerationService;
use Generator;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ProspectReplyGenerationService::class)]
class ProspectReplyGenerationServiceTest extends TestCase
{
    #[Test]
    public function it_includes_active_reply_examples_in_the_prompt(): void
    {
        Queue::fake();
        ProspectReplyGenerator::fake([['reply' => 'Hi!', 'reason' => 'Greeting.']]);
        ReplyExample::factory()->create([
            'inquiry' => 'Do you do aerial silks for weddings?',
            'reply' => 'Hi love! Yes we absolutely do. xo Jena',
        ]);

        app(ProspectReplyGenerationService::class)->generate(Prospect::factory()->create(['status' => ProspectStatus::DraftGenerating]));

        ProspectReplyGenerator::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'Do you do aerial silks for weddings?')
            && str_contains($prompt->prompt, 'Hi love! Yes we absolutely do. xo Jena'));
    }

    #[Test]
    public function it_leaves_inactive_reply_examples_out_of_the_prompt(): void
    {
        Queue::fake();
        ProspectReplyGenerator::fake([['reply' => 'Hi!', 'reason' => 'Greeting.']]);
        ReplyExample::factory()->inactive()->create(['reply' => 'An old reply Jena retired.']);

        app(ProspectReplyGenerationService::class)->generate(Prospect::factory()->create(['status' => ProspectStatus::DraftGenerating]));

        ProspectReplyGenerator::assertPrompted(fn ($prompt): bool => ! str_contains($prompt->prompt, 'An old reply Jena retired.')
            && str_contains($prompt->prompt, 'No examples are available.'));
    }

    #[Test]
    public function it_saves_the_draft_and_marks_a_generating_prospect_draft_ready(): void
    {
        Queue::fake();
        ProspectReplyGenerator::fake([['reply' => 'Hi there!', 'reason' => 'Greeting.']]);
        $prospect = Prospect::factory()->create(['status' => ProspectStatus::DraftGenerating]);

        $result = app(ProspectReplyGenerationService::class)->generate($prospect);

        $this->assertSame(ProspectStatus::DraftReady, $result->status);
        $this->assertSame('Hi there!', $result->generated_reply);
        $this->assertSame('Hi there!', $result->final_reply);
        $this->assertNotNull($result->generated_at);
    }

    public static function nonDraftableStatusProvider(): Generator
    {
        yield 'unanswerable' => [ProspectStatus::Unanswerable];
        yield 'spam' => [ProspectStatus::Spam];
    }

    #[Test]
    #[DataProvider('nonDraftableStatusProvider')]
    public function it_discards_the_draft_when_a_reviewer_already_decided_the_status(ProspectStatus $status): void
    {
        Queue::fake();
        ProspectReplyGenerator::fake([['reply' => 'Hi there!', 'reason' => 'Greeting.']]);
        $prospect = Prospect::factory()->create(['status' => $status]);

        $result = app(ProspectReplyGenerationService::class)->generate($prospect);

        $this->assertSame($status, $result->status);
        $this->assertNull($result->generated_reply);
        $this->assertNull($result->final_reply);
        $this->assertNull($result->generated_at);
    }
}
