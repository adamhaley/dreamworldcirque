<?php

namespace App\Filament\Widgets;

use App\Services\Prospects\ProspectDashboardMetrics;
use Filament\Widgets\ChartWidget;

class ProspectConfidenceRateChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getHeading(): string
    {
        return 'AI confidence (last 30 days)';
    }

    protected function getData(): array
    {
        $metrics = app(ProspectDashboardMetrics::class)->dailyNeedsHumanRates(30);

        // Presented as its inverse: "confidence" reads naturally as higher-
        // is-better, whereas showing the raw needs-human rate under that
        // label would mean a rising line actually meant less confidence.
        $confidenceRates = array_map(
            fn (?int $needsHumanRate): ?int => $needsHumanRate === null ? null : 100 - $needsHumanRate,
            $metrics['needs_human_rates'],
        );

        return [
            'datasets' => [
                [
                    'label' => 'Confidence rate',
                    'data' => $confidenceRates,
                    'borderColor' => '#d97706',
                    'backgroundColor' => 'rgba(217, 119, 6, 0.14)',
                    'fill' => true,
                    'tension' => 0.35,
                    'spanGaps' => true,
                    'pointRadius' => 3,
                    'pointHoverRadius' => 5,
                ],
            ],
            'labels' => $metrics['labels'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'max' => 100,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
