<?php

namespace App\Filament\Widgets;

use App\Services\Reporting\BusinessMetrics;
use Filament\Widgets\ChartWidget;

/**
 * Which customers actually earned you money.
 *
 * Ranked by profit rather than revenue, because the two disagree often enough
 * to matter — a customer who buys a lot at a thin margin can outrank one who
 * quietly makes you more. Losses appear as red bars below the baseline instead
 * of being sorted out of sight, which is the only way you find them.
 */
class TopCustomersChart extends ChartWidget
{
    protected ?string $heading = 'Profit by customer';

    protected ?string $description = 'Last 90 days, in USD. Ranked by what they earned, not what they spent.';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return auth()->user()?->can('view_cost') ?? false;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $metrics = app(BusinessMetrics::class);

        $rows = $metrics
            ->profitByCustomer(now()->subDays(90)->startOfDay(), now()->endOfDay())
            ->take(8);

        return [
            'datasets' => [[
                'label' => 'Profit',
                'data' => $rows->pluck('profit')->all(),
                // The same diverging pair as the monthly chart: consistency
                // matters more than variety when both answer "did this earn?"
                'backgroundColor' => $rows
                    ->map(fn (array $r) => $r['profit'] < 0 ? '#e34948' : '#2a78d6')
                    ->all(),
                'borderRadius' => 4,
                'borderSkipped' => false,
            ]],
            'labels' => $rows
                ->map(fn (array $r) => str($r['customer'] ?? 'Unknown')->limit(22)->toString())
                ->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            // Horizontal: customer names are words, and words read better along
            // the axis they are written on than rotated under a vertical bar.
            'indexAxis' => 'y',
            'scales' => [
                'x' => [
                    'grid' => ['color' => 'rgba(140,140,140,0.15)'],
                    'ticks' => ['color' => '#898781'],
                ],
                'y' => [
                    'grid' => ['display' => false],
                    'ticks' => ['color' => '#898781'],
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
