<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use Filament\Widgets\ChartWidget;

/**
 * Revenue against the landed cost of what was sold, by month.
 *
 * Two series only, in categorical slots 1 and 2, on one shared axis. Margin is
 * deliberately not overlaid as a second y-axis — a percentage and a currency on
 * one plot is the single most misread chart in business reporting.
 *
 * @see docs/05-UIUX.md §A1 for the validated palette
 */
class RevenueVsCostChart extends ChartWidget
{
    protected ?string $heading = 'Revenue vs cost of goods';

    protected ?string $description = 'Landed cost, not the supplier invoice — so the gap is real margin.';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

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
        $months = collect(range(11, 0))->map(fn (int $back) => now()->subMonths($back)->startOfMonth());

        $revenue = [];
        $cogs = [];
        $labels = [];

        foreach ($months as $month) {
            $window = [$month, $month->copy()->endOfMonth()];

            $invoices = Invoice::query()
                ->where('invoice_type', 'standard')
                ->whereNot('status', 'cancelled')
                ->whereBetween('invoice_date', $window);

            $labels[] = $month->format('M y');
            $revenue[] = round((float) $invoices->clone()->sum('total'), 2);
            $cogs[] = round((float) $invoices->clone()->sum('cogs_total_base'), 2);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $revenue,
                    'backgroundColor' => '#2a78d6',
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Cost of goods',
                    'data' => $cogs,
                    'backgroundColor' => '#eb6834',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                // Two series, so a legend is always present — identity is never
                // carried by colour alone.
                'legend' => ['display' => true, 'position' => 'bottom'],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true, 'grid' => ['color' => 'rgba(128,128,128,0.15)']],
                'x' => ['grid' => ['display' => false]],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
