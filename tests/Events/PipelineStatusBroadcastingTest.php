<?php

namespace Tests\Events;

use App\Ai\Agents\ProspectClassifier;
use App\Ai\Agents\ProspectReplyGenerator;
use App\Enums\ProspectStatus;
use App\Events\RecordPipelineStatusChanged;
use App\Jobs\ClassifyProspect;
use App\Jobs\GenerateProspectReply;
use App\Models\Prospect;
use App\Models\User;
use App\Services\Prospects\ProspectClassificationService;
use App\Services\Prospects\ProspectReplyGenerationService;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(RecordPipelineStatusChanged::class)]
class PipelineStatusBroadcastingTest extends TestCase
{
    #[Test]
    public function classification_broadcasts_a_status_change_on_the_prospects_pipeline_channel(): void
    {
        Event::fake([RecordPipelineStatusChanged::class]);
        ProspectClassifier::fake([
            ['classification' => 'valid', 'confidence' => 90, 'reason' => 'Looks genuine.'],
        ])->preventStrayPrompts();

        $prospect = Prospect::factory()->create();

        (new ClassifyProspect($prospect->id))->handle(app(ProspectClassificationService::class));

        Event::assertDispatched(
            RecordPipelineStatusChanged::class,
            fn (RecordPipelineStatusChanged $event): bool => $event->channel === 'prospects-pipeline',
        );
    }

    #[Test]
    public function classification_failure_still_broadcasts_a_status_change(): void
    {
        Event::fake([RecordPipelineStatusChanged::class]);

        (new ClassifyProspect(999))->failed(null);

        Event::assertDispatched(
            RecordPipelineStatusChanged::class,
            fn (RecordPipelineStatusChanged $event): bool => $event->channel === 'prospects-pipeline',
        );
    }

    #[Test]
    public function reply_generation_broadcasts_a_status_change_on_the_prospects_pipeline_channel(): void
    {
        Event::fake([RecordPipelineStatusChanged::class]);
        ProspectReplyGenerator::fake([
            ['reply' => 'Thanks for reaching out!', 'reason' => 'Warm acknowledgement.'],
        ])->preventStrayPrompts();

        $prospect = Prospect::factory()->pendingReview()->create(['status' => ProspectStatus::DraftGenerating]);

        (new GenerateProspectReply($prospect->id))->handle(app(ProspectReplyGenerationService::class));

        Event::assertDispatched(
            RecordPipelineStatusChanged::class,
            fn (RecordPipelineStatusChanged $event): bool => $event->channel === 'prospects-pipeline',
        );
    }

    #[Test]
    public function reply_generation_failure_still_broadcasts_a_status_change(): void
    {
        Event::fake([RecordPipelineStatusChanged::class]);

        (new GenerateProspectReply(999))->failed(null);

        Event::assertDispatched(
            RecordPipelineStatusChanged::class,
            fn (RecordPipelineStatusChanged $event): bool => $event->channel === 'prospects-pipeline',
        );
    }

    #[Test]
    public function an_admin_can_authorize_the_pipeline_channel_but_a_non_admin_cannot(): void
    {
        // phpunit.xml forces BROADCAST_CONNECTION=null suite-wide so no test
        // can accidentally broadcast for real; NullBroadcaster::auth() is a
        // total no-op that never runs channel closures at all, so this one
        // test needs a broadcaster that actually performs the authorization
        // check (Reverb speaks Pusher's protocol; auth() is pure local
        // signature logic, no live socket connection required to run it).
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');

        $admin = User::factory()->admin()->create();
        $editor = User::factory()->create();

        $this->actingAs($admin)
            ->postJson('/broadcasting/auth', ['channel_name' => 'private-prospects-pipeline', 'socket_id' => '1234.5678'])
            ->assertOk();

        $this->actingAs($editor)
            ->postJson('/broadcasting/auth', ['channel_name' => 'private-prospects-pipeline', 'socket_id' => '1234.5678'])
            ->assertForbidden();
    }
}
