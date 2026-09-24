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
     * Bucketed by classified_at, not reviewed_at: deferring to a human is
     * purely an AI-side decision made the moment it classifies a prospect,
     * independent of whenever a human later gets around to reviewing it. A
     * reviewed_at bucketing would smear the signal across whatever the
     * review backlog happens to look like that week instead of showing when
     * the AI actually started deferring more.
     *
     * @return array{labels: list<string>, needs_human_rates: list<int|null>}
     */
    public function dailyNeedsHumanRates(int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $end = now()->endOfDay();

        $prospectsByDay = $this->classifiedProspectsQuery()
            ->whereBetween('classified_at', [$start, $end])
            ->get()
            ->groupBy(fn (Prospect $prospect): string => $prospect->classified_at?->toDateString() ?? '');

        $labels = [];
        $needsHumanRates = [];

        foreach (CarbonPeriod::create($start, '1 day', $end) as $date) {
            $prospects = $prospectsByDay->get($date->toDateString(), collect());

            $labels[] = $date->format('M j');
            $needsHumanRates[] = $this->needsHumanRate($prospects);
        }

        return [
            'labels' => $labels,
            'needs_human_rates' => $needsHumanRates,
        ];
    }

    /**
     * @return Builder<Prospect>
     */
    private function classifiedProspectsQuery(): Builder
    {
        return Prospect::query()
            ->select(['id', 'classification', 'classified_at'])
            ->whereNotNull('classified_at');
    }

    /**
     * @param  Collection<int, Prospect>  $prospects
     */
    private function needsHumanRate(Collection $prospects): ?int
    {
        $count = $prospects->count();

        if ($count === 0) {
            return null;
        }

        $needsHuman = $prospects
            ->filter(fn (Prospect $prospect): bool => $prospect->classification === ProspectClassification::NeedsHuman)
            ->count();

        return (int) round(($needsHuman / $count) * 100);
    }
}
