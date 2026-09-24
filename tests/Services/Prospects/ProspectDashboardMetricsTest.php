<?php

namespace Tests\Services\Prospects;

use App\Enums\ProspectClassification;
use App\Models\Prospect;
use App\Services\Prospects\ProspectDashboardMetrics;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ProspectDashboardMetrics::class)]
class ProspectDashboardMetricsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    #[Test]
    public function it_computes_daily_needs_human_rates_from_classified_prospects(): void
    {
        Carbon::setTestNow('2026-09-20 12:00:00');

        Prospect::factory()->count(3)->create([
            'classification' => ProspectClassification::Valid,
            'classified_at' => now(),
        ]);
        Prospect::factory()->create([
            'classification' => ProspectClassification::NeedsHuman,
            'classified_at' => now(),
        ]);

        $metrics = app(ProspectDashboardMetrics::class)->dailyNeedsHumanRates(30);

        $this->assertSame('Sep 20', end($metrics['labels']));
        $this->assertSame(25, end($metrics['needs_human_rates']));

        Carbon::setTestNow();
    }

    #[Test]
    public function it_buckets_by_classified_at_not_reviewed_at(): void
    {
        Carbon::setTestNow('2026-09-20 12:00:00');

        Prospect::factory()->create([
            'classification' => ProspectClassification::NeedsHuman,
            'classified_at' => now(),
            'reviewed_at' => now()->addDays(3),
            'human_classification' => ProspectClassification::Valid,
        ]);

        $metrics = app(ProspectDashboardMetrics::class)->dailyNeedsHumanRates(30);

        $this->assertSame(100, end($metrics['needs_human_rates']));

        Carbon::setTestNow();
    }

    #[Test]
    public function it_returns_null_for_a_day_with_no_classified_prospects(): void
    {
        Carbon::setTestNow('2026-09-20 12:00:00');

        Prospect::factory()->create(['classified_at' => null]);

        $metrics = app(ProspectDashboardMetrics::class)->dailyNeedsHumanRates(30);

        $this->assertNull(end($metrics['needs_human_rates']));

        Carbon::setTestNow();
    }

    #[Test]
    public function it_computes_daily_average_confidence_including_needs_human(): void
    {
        Carbon::setTestNow('2026-09-20 12:00:00');

        Prospect::factory()->create([
            'classification' => ProspectClassification::Valid,
            'classification_confidence' => 90,
            'classified_at' => now(),
        ]);
        Prospect::factory()->create([
            'classification' => ProspectClassification::NeedsHuman,
            'classification_confidence' => 60,
            'classified_at' => now(),
        ]);

        $metrics = app(ProspectDashboardMetrics::class)->dailyAverageConfidence(30);

        $this->assertSame(75, end($metrics['average_confidence']));

        Carbon::setTestNow();
    }

    #[Test]
    public function it_computes_monthly_alignment_rates_bucketed_by_submitted_at(): void
    {
        Carbon::setTestNow('2026-09-24 12:00:00');

        Prospect::factory()->count(3)->create([
            'classification' => ProspectClassification::Valid,
            'human_classification' => ProspectClassification::Valid,
            'submitted_at' => '2023-06-11',
            'reviewed_at' => now(),
        ]);
        Prospect::factory()->create([
            'classification' => ProspectClassification::Spam,
            'human_classification' => ProspectClassification::Valid,
            'submitted_at' => '2023-06-20',
            'reviewed_at' => now(),
        ]);

        $metrics = app(ProspectDashboardMetrics::class)->monthlyAlignmentRatesBySubmission();

        $juneIndex = array_search('Jun 2023', $metrics['labels'], true);
        $this->assertNotFalse($juneIndex);
        $this->assertSame(75, $metrics['alignment_rates'][$juneIndex]);

        Carbon::setTestNow();
    }

    #[Test]
    public function it_excludes_needs_human_from_the_submission_alignment_rate(): void
    {
        Carbon::setTestNow('2026-09-24 12:00:00');

        Prospect::factory()->count(2)->create([
            'classification' => ProspectClassification::Valid,
            'human_classification' => ProspectClassification::Valid,
            'submitted_at' => '2023-06-11',
        ]);
        Prospect::factory()->count(5)->create([
            'classification' => ProspectClassification::NeedsHuman,
            'human_classification' => ProspectClassification::Spam,
            'submitted_at' => '2023-06-11',
        ]);

        $metrics = app(ProspectDashboardMetrics::class)->monthlyAlignmentRatesBySubmission();

        $juneIndex = array_search('Jun 2023', $metrics['labels'], true);
        $this->assertSame(100, $metrics['alignment_rates'][$juneIndex]);

        Carbon::setTestNow();
    }

    #[Test]
    public function it_computes_monthly_valid_and_spam_rates_bucketed_by_submitted_at(): void
    {
        Carbon::setTestNow('2026-09-24 12:00:00');

        Prospect::factory()->count(3)->create([
            'classification' => ProspectClassification::Valid,
            'submitted_at' => '2023-06-11',
        ]);
        Prospect::factory()->create([
            'classification' => ProspectClassification::Spam,
            'submitted_at' => '2023-06-20',
        ]);

        $metrics = app(ProspectDashboardMetrics::class)->monthlyClassificationComposition();

        $juneIndex = array_search('Jun 2023', $metrics['labels'], true);
        $this->assertNotFalse($juneIndex);
        $this->assertSame(75, $metrics['valid_rates'][$juneIndex]);
        $this->assertSame(25, $metrics['spam_rates'][$juneIndex]);

        Carbon::setTestNow();
    }

    #[Test]
    public function it_returns_an_empty_series_when_nothing_is_classified(): void
    {
        $metrics = app(ProspectDashboardMetrics::class)->monthlyClassificationComposition();

        $this->assertSame([], $metrics['labels']);
        $this->assertSame([], $metrics['valid_rates']);
        $this->assertSame([], $metrics['spam_rates']);
    }
}
