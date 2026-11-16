<?php

namespace Tests\Database\Seeders;

use App\Enums\ProspectStatus;
use App\Models\Prospect;
use Database\Seeders\PreviewSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(PreviewSeeder::class)]
class PreviewSeederTest extends TestCase
{
    private string $originalEnvironment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalEnvironment = app()->environment();
    }

    protected function tearDown(): void
    {
        $original = $this->originalEnvironment;
        app()->detectEnvironment(fn (): string => $original);

        parent::tearDown();
    }

    #[Test]
    public function it_creates_one_lead_per_status_in_the_preview_environment(): void
    {
        $this->usePreviewEnvironment();

        $this->seed(PreviewSeeder::class);

        $expected = [
            ProspectStatus::PendingReview,
            ProspectStatus::Valid,
            ProspectStatus::DraftReady,
            ProspectStatus::Replied,
            ProspectStatus::Spam,
            ProspectStatus::Unanswerable,
        ];

        foreach ($expected as $status) {
            $count = $status === ProspectStatus::DraftReady ? 2 : 1;
            $this->assertSame($count, Prospect::query()->where('status', $status)->count(), $status->value);
        }
        $this->assertSame(7, Prospect::query()->count());

        $drafts = Prospect::query()->where('status', ProspectStatus::DraftReady)->get();
        $edited = $drafts->filter(fn (Prospect $p): bool => $p->final_reply !== null);
        $untouched = $drafts->filter(fn (Prospect $p): bool => $p->final_reply === null);
        $this->assertCount(1, $edited);
        $this->assertCount(1, $untouched);
        $this->assertNotSame($edited->first()->generated_reply, $edited->first()->final_reply);
        $this->assertNotNull($untouched->first()->generated_reply);

        $replied = Prospect::query()->where('status', ProspectStatus::Replied)->sole();
        $this->assertNotNull($replied->replied_at);
        $this->assertNotNull($replied->final_reply);

        $pending = Prospect::query()->where('status', ProspectStatus::PendingReview)->sole();
        $this->assertNotNull($pending->classification);
        $this->assertNotNull($pending->classification_confidence);
        $this->assertNotNull($pending->classification_reason);
        $this->assertNotNull($pending->classified_at);
    }

    #[Test]
    public function it_dispatches_no_jobs_and_sends_no_mail(): void
    {
        Queue::fake();
        Mail::fake();
        $this->usePreviewEnvironment();

        $this->seed(PreviewSeeder::class);

        Queue::assertNothingPushed();
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    #[Test]
    public function it_creates_nothing_outside_the_preview_environment(): void
    {
        $this->seed(PreviewSeeder::class);

        $this->assertSame(0, Prospect::query()->count());
    }

    #[Test]
    public function it_is_idempotent_when_run_twice(): void
    {
        $this->usePreviewEnvironment();

        $this->seed(PreviewSeeder::class);
        $firstCount = Prospect::query()->count();
        $this->seed(PreviewSeeder::class);

        $this->assertSame($firstCount, Prospect::query()->count());
        $this->assertSame($firstCount, Prospect::query()->distinct()->count('email'));
    }

    private function usePreviewEnvironment(): void
    {
        app()->detectEnvironment(fn (): string => 'preview');
    }
}
