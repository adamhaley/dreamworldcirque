<?php

namespace App\Filament\Widgets;

use App\Services\Prospects\ProspectDashboardMetrics;
use Filament\Widgets\ChartWidget;

class ProspectAlignmentBySubmissionChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getHeading(): string
    {
        return 'AI/human classification alignment, by lead submission month';
    }

    protected function getData(): array
    {
        $metrics = app(ProspectDashboardMetrics::class)->monthlyAlignmentRatesBySubmission();

        return [
            'datasets' => [
                [
                    'label' => 'Alignment rate',
                    'data' => $metrics['alignment_rates'],
                    'borderColor' => '#2d9d68',
                    'backgroundColor' => 'rgba(45, 157, 104, 0.16)',
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
