<?php

namespace App\Filament\Widgets;

use App\Services\Prospects\ProspectDashboardMetrics;
use Filament\Widgets\ChartWidget;

class ProspectClassificationAlignmentChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getHeading(): string
    {
        return 'AI classification alignment (last 30 days)';
    }

    protected function getData(): array
    {
        $metrics = app(ProspectDashboardMetrics::class)->dailyAlignmentRates(30);

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
                    'pointRadius' => 0,
                    'pointHoverRadius' => 4,
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
