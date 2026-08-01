<?php

namespace App\Filament\Widgets;

use App\Services\Reporting\BusinessMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The eight numbers that describe an import business.
 *
 * Inventory value and goods-in-transit are kept apart on purpose: they are both
 * assets, but only one of them can be sold this week.
 */
class BusinessOverviewWidget extends StatsOverviewWidget
{
    /**
     * A rolling 30 days, not the calendar month.
     *
     * On the 1st a calendar month holds a single day of trading and compares it
     * against a full previous month, which reads as a catastrophic collapse
     * every time the month turns over. A rolling window is always comparable.
     */
    protected ?string $heading = 'Last 30 days';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('view_cost') ?? false;
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $metrics = app(BusinessMetrics::class);

        $to = now()->endOfDay();
        $from = now()->subDays(30)->startOfDay();
        $previousTo = $from->copy()->subSecond();
        $previousFrom = $from->copy()->subDays(30);

        $revenue = $metrics->revenue($from, $to);
        $grossProfit = $metrics->grossProfit($from, $to);
        $netProfit = $metrics->netProfit($from, $to);
        $overdue = $metrics->overdueReceivables();

        return [
            $this->stat('Revenue', $revenue, $metrics->change($revenue, $metrics->revenue($previousFrom, $previousTo))),

            Stat::make('Gross profit', $this->money($grossProfit))
                ->description($metrics->grossMarginPercent($from, $to).'% margin')
                ->color($grossProfit > 0 ? 'success' : 'gray'),

            $this->stat('Net profit', $netProfit, $metrics->change($netProfit, $metrics->netProfit($previousFrom, $previousTo))),

            Stat::make('Operating expenses', $this->money($metrics->operatingExpenses($from, $to)))
                ->description('Excludes shipping, which sits in landed cost')
                ->color('gray'),

            Stat::make('Inventory value', $this->money($metrics->inventoryValue()))
                ->description('At landed cost, in the warehouse')
                ->color('gray'),

            Stat::make('Goods in transit', $this->money($metrics->goodsInTransit()))
                ->description($metrics->containersInTransit().' '.str('container')->plural($metrics->containersInTransit()).' on the water')
                ->color($metrics->goodsInTransit() > 0 ? 'warning' : 'gray'),

            Stat::make('Receivables', $this->money($metrics->receivables()))
                ->description($overdue > 0 ? $this->money($overdue).' overdue' : 'Nothing overdue')
                ->color($overdue > 0 ? 'danger' : 'success'),

            Stat::make('Payables', $this->money($metrics->payables()))
                ->description('Owed to suppliers')
                ->color($metrics->payables() > 0 ? 'warning' : 'gray'),
        ];
    }

    private function stat(string $label, float $value, ?float $change): Stat
    {
        $stat = Stat::make($label, $this->money($value));

        if ($change === null) {
            return $stat->description('No comparable prior period')->color('gray');
        }

        return $stat
            ->description(($change >= 0 ? '↑ ' : '↓ ').abs($change).'% vs previous 30 days')
            ->color($change >= 0 ? 'success' : 'danger');
    }

    /** Sign goes outside the currency symbol, using a true minus rather than a hyphen. */
    private function money(float $value): string
    {
        $formatted = '$'.number_format(abs($value), 2);

        return $value < 0 ? "\u{2212}{$formatted}" : $formatted;
    }
}
