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
    public function it_computes_daily_alignment_rates_from_reviewed_prospects(): void
    {
        Carbon::setTestNow('2026-09-20 12:00:00');

        Prospect::factory()->count(3)->create([
            'classification' => ProspectClassification::Valid,
            'human_classification' => ProspectClassification::Valid,
            'reviewed_at' => now(),
        ]);
        Prospect::factory()->create([
            'classification' => ProspectClassification::Spam,
            'human_classification' => ProspectClassification::Valid,
            'reviewed_at' => now(),
        ]);

        $metrics = app(ProspectDashboardMetrics::class)->dailyAlignmentRates(30);

        $this->assertSame('Sep 20', end($metrics['labels']));
        $this->assertSame(75, end($metrics['alignment_rates']));

        Carbon::setTestNow();
    }

    #[Test]
    public function it_ignores_prospects_that_have_not_been_human_reviewed(): void
    {
        Carbon::setTestNow('2026-09-20 12:00:00');

        Prospect::factory()->create(['reviewed_at' => null, 'human_classification' => null]);

        $metrics = app(ProspectDashboardMetrics::class)->dailyAlignmentRates(30);

        $this->assertNull(end($metrics['alignment_rates']));

        Carbon::setTestNow();
    }
}
