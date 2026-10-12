<?php

namespace App\Filament\Widgets;

use App\Support\DashboardRange;
use App\Support\Insights;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/** The projects people look at most, and what each one led to. */
class TopProjects extends Widget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.top-projects';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 2];

    protected function getViewData(): array
    {
        [$start, $end] = DashboardRange::resolve($this->pageFilters);
        $rows = Insights::topProjects($start, $end);

        return [
            'rows' => $rows,
            'max' => max(1, ...array_map(fn ($r) => $r['views'], $rows ?: [['views' => 0]])),
            'range' => DashboardRange::label($this->pageFilters),
        ];
    }
}
