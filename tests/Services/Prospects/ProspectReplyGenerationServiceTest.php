<?php

namespace Tests\Services\Prospects;

use App\Ai\Agents\ProspectReplyGenerator;
use App\Models\Prospect;
use App\Models\ReplyExample;
use App\Services\Prospects\ProspectReplyGenerationService;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
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

        app(ProspectReplyGenerationService::class)->generate(Prospect::factory()->create());

        ProspectReplyGenerator::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'Do you do aerial silks for weddings?')
            && str_contains($prompt->prompt, 'Hi love! Yes we absolutely do. xo Jena'));
    }

    #[Test]
    public function it_leaves_inactive_reply_examples_out_of_the_prompt(): void
    {
        Queue::fake();
        ProspectReplyGenerator::fake([['reply' => 'Hi!', 'reason' => 'Greeting.']]);
        ReplyExample::factory()->inactive()->create(['reply' => 'An old reply Jena retired.']);

        app(ProspectReplyGenerationService::class)->generate(Prospect::factory()->create());

        ProspectReplyGenerator::assertPrompted(fn ($prompt): bool => ! str_contains($prompt->prompt, 'An old reply Jena retired.')
            && str_contains($prompt->prompt, 'No examples are available.'));
    }
}
