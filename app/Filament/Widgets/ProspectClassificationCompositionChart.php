<?php

namespace App\Filament\Widgets;

use App\Services\Prospects\ProspectDashboardMetrics;
use Filament\Widgets\ChartWidget;

class ProspectClassificationCompositionChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getHeading(): string
    {
        return 'Valid vs. spam leads, by submission month';
    }

    protected function getData(): array
    {
        $metrics = app(ProspectDashboardMetrics::class)->monthlyClassificationComposition();

        return [
            'datasets' => [
                [
                    'label' => 'Valid',
                    'data' => $metrics['valid_rates'],
                    'borderColor' => '#2d9d68',
                    'backgroundColor' => 'rgba(45, 157, 104, 0.16)',
                    'fill' => false,
                    'tension' => 0.35,
                    'spanGaps' => true,
                    'pointRadius' => 3,
                    'pointHoverRadius' => 5,
                ],
                [
                    'label' => 'Spam',
                    'data' => $metrics['spam_rates'],
                    'borderColor' => '#a3433f',
                    'backgroundColor' => 'rgba(163, 67, 63, 0.16)',
                    'fill' => false,
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
                    'display' => true,
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
