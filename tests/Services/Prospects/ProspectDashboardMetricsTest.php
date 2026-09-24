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
}
