<?php

namespace App\Services\Prospects;

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
     * @param  Collection<int, Prospect>  $prospects
     */
    private function alignmentRate(Collection $prospects): ?int
    {
        $reviewedCount = $prospects->count();

        if ($reviewedCount === 0) {
            return null;
        }

        $alignments = $prospects
            ->filter(fn (Prospect $prospect): bool => $prospect->classification === $prospect->human_classification)
            ->count();

        return (int) round(($alignments / $reviewedCount) * 100);
    }
}
