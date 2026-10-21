<?php

namespace App\Jobs;

use App\Enums\ProspectStatus;
use App\Events\RecordPipelineStatusChanged;
use App\Models\Prospect;
use App\Notifications\ProspectPipelineFailed;
use App\Services\Prospects\ProspectReplyGenerationService;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Notification;
use Throwable;

class GenerateProspectReply implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    public function __construct(public int $prospectId) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [5, 30, 120];
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new RateLimited('openai'))->releaseAfter(30)];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(30);
    }

    public function uniqueId(): string
    {
        return (string) $this->prospectId;
    }

    public function handle(ProspectReplyGenerationService $generator): void
    {
        $prospect = Prospect::query()->find($this->prospectId);

        if ($prospect === null || $prospect->status !== ProspectStatus::DraftGenerating) {
            return;
        }

        $generator->generate($prospect);

        RecordPipelineStatusChanged::dispatch('prospects-pipeline');
    }

    public function failed(?Throwable $exception): void
    {
        RecordPipelineStatusChanged::dispatch('prospects-pipeline');

        $prospect = Prospect::query()->find($this->prospectId);

        if ($prospect !== null) {
            Notification::route('mail', config('mail.ops_alert_address'))
                ->notify(new ProspectPipelineFailed($prospect, 'reply draft', $exception?->getMessage() ?? 'Unknown error'));
        }
    }
}
