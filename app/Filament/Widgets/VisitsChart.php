<?php

namespace App\Filament\Widgets;

use App\Models\PageView;
use App\Support\DashboardRange;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

class VisitsChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 2];

    protected ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'line';
    }

    public function getHeading(): ?string
    {
        return 'Visitors — '.DashboardRange::label($this->pageFilters);
    }

    protected function getData(): array
    {
        [$start, $end] = DashboardRange::resolve($this->pageFilters);
        $series = PageView::seriesBetween($start, $end);
        $monthly = $start->diffInDays($end) > 92;

        return [
            'datasets' => [[
                'label' => 'Visitors',
                'data' => array_values($series),
                'borderColor' => '#F5C518',
                'backgroundColor' => 'rgba(245, 197, 24, 0.12)',
                'fill' => true,
                'tension' => 0.35,
                'pointRadius' => 0,
                'pointHoverRadius' => 4,
                'borderWidth' => 2,
            ]],
            'labels' => array_map(
                fn ($bucket) => $monthly
                    ? Carbon::parse("{$bucket}-01")->format('M Y')
                    : Carbon::parse($bucket)->format('M j'),
                array_keys($series),
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
