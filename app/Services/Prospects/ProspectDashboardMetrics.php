<?php

namespace App\Services\Prospects;

use App\Enums\ProspectClassification;
use App\Models\Prospect;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProspectDashboardMetrics
{
    /**
     * @return array{labels: list<string>, alignment_rates: list<int|null>}
     */
    public function dailyAlignmentRates(int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $end = now()->endOfDay();

        $prospectsByDay = $this->reviewedProspectsQuery()
            ->whereBetween('reviewed_at', [$start, $end])
            ->get()
            ->groupBy(fn (Prospect $prospect): string => $prospect->reviewed_at?->toDateString() ?? '');

        $labels = [];
        $alignmentRates = [];

        foreach (CarbonPeriod::create($start, '1 day', $end) as $date) {
            $prospects = $prospectsByDay->get($date->toDateString(), collect());

            $labels[] = $date->format('M j');
            $alignmentRates[] = $this->alignmentRate($prospects);
        }

        return [
            'labels' => $labels,
            'alignment_rates' => $alignmentRates,
        ];
    }

    /**
     * @return Builder<Prospect>
     */
    private function reviewedProspectsQuery(): Builder
    {
        return Prospect::query()
            ->select(['id', 'classification', 'human_classification', 'reviewed_at'])
            ->whereNotNull('reviewed_at')
            ->whereNotNull('human_classification');
    }

    /**
     * A NeedsHuman classification isn't a guess to be scored against --
     * it's the AI correctly deferring rather than committing to a
     * definitive call it isn't confident in (see ProspectClassifier's own
     * "be conservative" instruction). Counting it as a misalignment would
     * penalize exactly the behavior that instruction asks for, so those
     * rows are excluded from both the numerator and the denominator
     * rather than treated as wrong.
     *
     * @param  Collection<int, Prospect>  $prospects
     */
    private function alignmentRate(Collection $prospects): ?int
    {
        $scored = $prospects->filter(fn (Prospect $prospect): bool => $prospect->classification !== ProspectClassification::NeedsHuman);
        $scoredCount = $scored->count();

        if ($scoredCount === 0) {
            return null;
        }

        $alignments = $scored
            ->filter(fn (Prospect $prospect): bool => $prospect->classification === $prospect->human_classification)
            ->count();

        return (int) round(($alignments / $scoredCount) * 100);
    }
}
