<?php

namespace App\Filament\Widgets;

use App\Support\DashboardRange;
use App\Support\Insights;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/** Where visitors come from, and how many of each start a chat or write. */
class TrafficSources extends Widget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.traffic-sources';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected function getViewData(): array
    {
        [$start, $end] = DashboardRange::resolve($this->pageFilters);
        $rows = Insights::sources($start, $end);

        return [
            'rows' => $rows,
            'max' => max(1, ...array_map(fn ($r) => $r['visitors'], $rows ?: [['visitors' => 0]])),
            'range' => DashboardRange::label($this->pageFilters),
        ];
    }
}
