<?php

namespace App\Filament\Widgets;

use App\Services\Prospects\ProspectDashboardMetrics;
use Filament\Widgets\ChartWidget;

class ProspectAverageConfidenceChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getHeading(): string
    {
        return 'AI average confidence (last 30 days)';
    }

    protected function getData(): array
    {
        $metrics = app(ProspectDashboardMetrics::class)->dailyAverageConfidence(30);

        return [
            'datasets' => [
                [
                    'label' => 'Average confidence',
                    'data' => $metrics['average_confidence'],
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
