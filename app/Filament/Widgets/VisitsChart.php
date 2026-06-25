<?php

namespace App\Filament\Widgets;

use App\Models\PageView;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class VisitsChart extends ChartWidget
{
    protected ?string $heading = 'Visitors — last 30 days';

    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 2];

    protected ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $daily = PageView::dailyVisitors(30);

        return [
            'datasets' => [[
                'label' => 'Visitors',
                'data' => array_values($daily),
                'borderColor' => '#F5C518',
                'backgroundColor' => 'rgba(245, 197, 24, 0.12)',
                'fill' => true,
                'tension' => 0.35,
                'pointRadius' => 0,
                'pointHoverRadius' => 4,
                'borderWidth' => 2,
            ]],
            'labels' => array_map(
                fn ($d) => Carbon::parse($d)->format('M j'),
                array_keys($daily),
            ),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
                'x' => ['ticks' => ['maxTicksLimit' => 8]],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
