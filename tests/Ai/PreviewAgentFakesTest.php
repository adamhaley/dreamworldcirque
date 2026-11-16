<?php

namespace Tests\Ai;

use App\Ai\Agents\ProspectClassifier;
use App\Ai\Agents\ProspectReplyGenerator;
use App\Ai\PreviewAgentFakes;
use App\Enums\ProspectStatus;
use App\Models\Prospect;
use Laravel\Ai\Ai;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(PreviewAgentFakes::class)]
class PreviewAgentFakesTest extends TestCase
{
    /**
     * Switch environment and OpenAI key configuration.
     */
    private function configure(string $environment, ?string $key): void
    {
        $this->app['env'] = $environment;
        config(['ai.providers.openai.key' => $key, 'services.openai.api_key' => $key]);
    }

    #[Test]
    public function it_lets_a_valid_mark_reach_draft_ready_in_preview_without_a_key(): void
    {
        $this->configure('preview', '');
        $this->assertTrue(PreviewAgentFakes::register());
        $prospect = Prospect::factory()->create(['status' => ProspectStatus::PendingReview]);

        $prospect->markStatus(ProspectStatus::Valid);
        $prospect->refresh();

        $this->assertSame(ProspectStatus::DraftReady, $prospect->status);
        $this->assertNotEmpty($prospect->generated_reply);

        $second = Prospect::factory()->create(['status' => ProspectStatus::PendingReview]);
        $second->markStatus(ProspectStatus::Valid);
        $this->assertSame(ProspectStatus::DraftReady, $second->refresh()->status);
    }

    #[Test]
    public function it_fakes_nothing_in_the_testing_environment(): void
    {
        $this->configure('testing', '');

        $this->assertFalse(PreviewAgentFakes::register());
        $this->assertFalse(Ai::hasFakeGatewayFor(ProspectClassifier::class));
        $this->assertFalse(Ai::hasFakeGatewayFor(ProspectReplyGenerator::class));
    }

    #[Test]
    public function it_fakes_nothing_in_preview_when_a_key_is_set(): void
    {
        $this->configure('preview', 'sk-real-key');

        $this->assertFalse(PreviewAgentFakes::register());
        $this->assertFalse(Ai::hasFakeGatewayFor(ProspectClassifier::class));
        $this->assertFalse(Ai::hasFakeGatewayFor(ProspectReplyGenerator::class));
    }
}
