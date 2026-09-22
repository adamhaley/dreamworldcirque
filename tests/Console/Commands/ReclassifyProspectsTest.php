<?php

namespace Tests\Console\Commands;

use App\Console\Commands\ReclassifyProspects;
use App\Enums\ProspectStatus;
use App\Jobs\ClassifyProspect;
use App\Models\Prospect;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ReclassifyProspects::class)]
class ReclassifyProspectsTest extends TestCase
{
    #[Test]
    public function it_resets_matching_prospects_and_dispatches_classification(): void
    {
        Queue::fake();
        $matching = Prospect::factory()->pendingReview()->create();
        $other = Prospect::factory()->spam()->create();

        $this->artisan('prospects:reclassify')
            ->expectsConfirmation('Reclassify 1 prospect(s)?', 'yes')
            ->assertSuccessful();

        $this->assertSame(ProspectStatus::PendingClassification, $matching->refresh()->status);
        $this->assertSame(ProspectStatus::Spam, $other->refresh()->status);
        Queue::assertPushed(ClassifyProspect::class, fn (ClassifyProspect $job): bool => $job->prospectId === $matching->id);
        Queue::assertNotPushed(ClassifyProspect::class, fn (ClassifyProspect $job): bool => $job->prospectId === $other->id);
    }

    #[Test]
    public function it_does_nothing_when_the_confirmation_is_declined(): void
    {
        Queue::fake();
        $prospect = Prospect::factory()->pendingReview()->create();

        $this->artisan('prospects:reclassify')
            ->expectsConfirmation('Reclassify 1 prospect(s)?', 'no')
            ->assertSuccessful();

        $this->assertSame(ProspectStatus::PendingReview, $prospect->refresh()->status);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_lists_matching_prospects_without_changes_on_dry_run(): void
    {
        Queue::fake();
        $prospect = Prospect::factory()->pendingReview()->create();

        $this->artisan('prospects:reclassify --dry-run')->assertSuccessful();

        $this->assertSame(ProspectStatus::PendingReview, $prospect->refresh()->status);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_fails_for_an_unknown_status(): void
    {
        $this->artisan('prospects:reclassify --status=bogus')->assertFailed();
    }

    #[Test]
    public function it_reports_success_when_nothing_matches(): void
    {
        $this->artisan('prospects:reclassify --status=spam')->assertSuccessful();
    }
}
