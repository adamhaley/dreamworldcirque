<?php

namespace Tests\Jobs;

use App\Events\RecordPipelineStatusChanged;
use App\Jobs\ClassifyProspect;
use App\Jobs\GenerateProspectReply;
use App\Models\Prospect;
use App\Notifications\ProspectPipelineFailed;
use Closure;
use Generator;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

#[CoversClass(ClassifyProspect::class)]
#[CoversClass(GenerateProspectReply::class)]
class ProspectJobFailureTest extends TestCase
{
    /** @return Generator<string, array{Closure(int): (ClassifyProspect|GenerateProspectReply), string}> */
    public static function jobProvider(): Generator
    {
        yield 'classification' => [fn (int $id): ClassifyProspect => new ClassifyProspect($id), 'classification'];
        yield 'reply draft' => [fn (int $id): GenerateProspectReply => new GenerateProspectReply($id), 'reply draft'];
    }

    #[Test]
    #[DataProvider('jobProvider')]
    public function it_emails_an_ops_alert_when_the_job_finally_fails(Closure $makeJob, string $stage): void
    {
        Event::fake([RecordPipelineStatusChanged::class]);
        Notification::fake();
        Queue::fake();
        config(['mail.ops_alert_address' => 'ops@example.com']);
        $prospect = Prospect::factory()->create();

        $makeJob($prospect->id)->failed(new RuntimeException('Your API key has expired.'));

        Notification::assertSentOnDemand(
            ProspectPipelineFailed::class,
            fn (ProspectPipelineFailed $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notification->prospect->is($prospect)
                && $notification->stage === $stage
                && $notification->reason === 'Your API key has expired.'
                && $notifiable->routes['mail'] === 'ops@example.com',
        );
    }

    #[Test]
    #[DataProvider('jobProvider')]
    public function it_sends_no_alert_when_the_prospect_no_longer_exists(Closure $makeJob, string $stage): void
    {
        Event::fake([RecordPipelineStatusChanged::class]);
        Notification::fake();

        $makeJob(999999)->failed(new RuntimeException('boom'));

        Notification::assertNothingSent();
    }
}
