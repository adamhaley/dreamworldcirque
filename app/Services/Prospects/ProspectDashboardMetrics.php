<?php

namespace App\Services\Prospects;

use App\Enums\ProspectClassification;
use App\Models\Prospect;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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
        $prospectsByDay = $this->classifiedProspectsQuery()
            ->get()
            ->groupBy(fn (Prospect $prospect): string => $prospect->classified_at?->toDateString() ?? '');

        return $this->dailySeries($days, $prospectsByDay, 'needs_human_rates', function (Collection $prospects): ?int {
            $count = $prospects->count();

            if ($count === 0) {
                return null;
            }

            $needsHuman = $prospects
                ->filter(fn (Prospect $prospect): bool => $prospect->classification === ProspectClassification::NeedsHuman)
                ->count();

            return (int) round(($needsHuman / $count) * 100);
        });
    }

    /**
     * Bucketed by submitted_at (when the lead came in), not reviewed_at
     * (when a human happened to get to it) -- classification and review for
     * the legacy backfill all happened in a couple of batch days, so a
     * reviewed_at/classified_at bucketing collapses years of real leads into
     * one or two points. submitted_at spreads across the actual history, so
     * this is meaningful immediately from backfilled data and keeps working
     * once live, since a live lead's review will happen close to its
     * submission date anyway.
     *
     * Monthly, not daily: the legacy corpus averages only a handful of
     * leads per month, so daily buckets would be almost entirely empty.
     *
     * @return array{labels: list<string>, alignment_rates: list<int|null>}
     */
    public function monthlyAlignmentRatesBySubmission(): array
    {
        $reviewed = Prospect::query()
            ->select(['id', 'classification', 'human_classification', 'submitted_at'])
            ->whereNotNull('classification')
            ->whereNotNull('human_classification')
            ->get();

        return $this->monthlySeries($reviewed, 'alignment_rates', function (Collection $prospects): ?int {
            $scored = $prospects->filter(fn (Prospect $prospect): bool => $prospect->classification !== ProspectClassification::NeedsHuman);
            $count = $scored->count();

            if ($count === 0) {
                return null;
            }

            $aligned = $scored
                ->filter(fn (Prospect $prospect): bool => $prospect->classification === $prospect->human_classification)
                ->count();

            return (int) round(($aligned / $count) * 100);
        });
    }

    /**
     * Lead-quality composition over time, not accuracy -- how much of what
     * comes in is genuine vs. spam, by the AI's own call (broadest coverage:
     * every classified prospect has one, unlike human_classification which
     * only exists once someone's reviewed it). Same submitted_at/monthly
     * bucketing as the alignment-by-submission metric, for the same reason.
     *
     * @return array{labels: list<string>, valid_rates: list<int|null>, spam_rates: list<int|null>}
     */
    public function monthlyClassificationComposition(): array
    {
        $classified = Prospect::query()
            ->select(['id', 'classification', 'submitted_at'])
            ->whereNotNull('classification')
            ->get();

        $byMonth = $this->groupByMonth($classified);
        $months = $this->monthRange($classified);

        $labels = [];
        $validRates = [];
        $spamRates = [];

        foreach ($months as $month) {
            $prospects = $byMonth->get($month->format('Y-m'), collect());
            $count = $prospects->count();

            $labels[] = $month->format('M Y');
            $validRates[] = $count === 0 ? null : (int) round($prospects->where('classification', ProspectClassification::Valid)->count() / $count * 100);
            $spamRates[] = $count === 0 ? null : (int) round($prospects->where('classification', ProspectClassification::Spam)->count() / $count * 100);
        }

        return [
            'labels' => $labels,
            'valid_rates' => $validRates,
            'spam_rates' => $spamRates,
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
     * @param  Collection<int, Prospect>  $prospectsByDay  Keyed by 'Y-m-d'.
     * @param  callable(Collection<int, Prospect>): (int|null)  $metric
     * @return array<string, list<string>|list<int|null>>
     */
    private function dailySeries(int $days, Collection $prospectsByDay, string $key, callable $metric): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $end = now()->endOfDay();

        $labels = [];
        $values = [];

        foreach (CarbonPeriod::create($start, '1 day', $end) as $date) {
            $prospects = $prospectsByDay->get($date->toDateString(), collect());

            $labels[] = $date->format('M j');
            $values[] = $metric($prospects);
        }

        return ['labels' => $labels, $key => $values];
    }

    /**
     * @param  Collection<int, Prospect>  $prospects
     * @param  callable(Collection<int, Prospect>): (int|null)  $metric
     * @return array<string, list<string>|list<int|null>>
     */
    private function monthlySeries(Collection $prospects, string $key, callable $metric): array
    {
        $byMonth = $this->groupByMonth($prospects);

        $labels = [];
        $values = [];

        foreach ($this->monthRange($prospects) as $month) {
            $labels[] = $month->format('M Y');
            $values[] = $metric($byMonth->get($month->format('Y-m'), collect()));
        }

        return ['labels' => $labels, $key => $values];
    }

    /**
     * @param  Collection<int, Prospect>  $prospects
     * @return Collection<string, Collection<int, Prospect>>
     */
    private function groupByMonth(Collection $prospects): Collection
    {
        return $prospects->groupBy(fn (Prospect $prospect): string => $prospect->submitted_at->format('Y-m'));
    }

    /**
     * From the earliest submission in the set through the current month, so
     * the range grows on its own as both real history and new leads accrue
     * -- never hard-coded to "the last N months".
     *
     * @param  Collection<int, Prospect>  $prospects
     */
    private function monthRange(Collection $prospects): CarbonPeriod
    {
        $earliest = $prospects->min('submitted_at');

        // With nothing to plot, start the range one month past the end so
        // the period yields zero iterations rather than a single "now" bucket.
        $start = $earliest === null
            ? now()->addMonth()->startOfMonth()
            : Carbon::parse($earliest)->startOfMonth();

        return CarbonPeriod::create($start, '1 month', now()->startOfMonth());
    }
}
