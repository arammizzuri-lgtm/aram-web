<?php

namespace App\Filament\Widgets;

use App\Services\Reporting\BusinessMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The headline figures.
 *
 * A rolling 30 days rather than "this month": on the 1st, a calendar month
 * compares one day against a full previous month and reports a collapse that
 * did not happen.
 *
 * Cost-bearing tiles are dropped entirely for the assistant rather than blanked
 * — a row of tiles with three showing "—" invites the question the permission
 * exists to prevent.
 */
class BusinessOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Last 30 days';

    protected function getStats(): array
    {
        $metrics = app(BusinessMetrics::class);
        [$from, $to] = $metrics->window();

        $canSeeCost = auth()->user()?->can('view_cost') ?? false;

        $revenue = $metrics->revenue($from, $to);
        $receivables = $metrics->receivables();
        $credit = $metrics->customerCredit();

        $stats = [
            Stat::make('Invoiced', $revenue->display())
                ->description('What customers were billed')
                ->color('gray'),

            Stat::make('Owed to you', $receivables->display())
                ->description($credit->isPositive()
                    ? $credit->display().' credit held separately'
                    : 'Nothing sitting as credit')
                ->color($receivables->isPositive() ? 'warning' : 'gray'),
        ];

        if (! $canSeeCost) {
            return $stats;
        }

        $profit = $metrics->profit($from, $to);
        $atRisk = $metrics->boughtAtRisk();
        $losses = $metrics->transferLosses($from, $to);

        array_splice($stats, 1, 0, [
            Stat::make('Profit', $this->signed($profit->toFloat()))
                ->description($metrics->marginPercent($from, $to).'% margin')
                ->color($profit->isNegative() ? 'danger' : 'success'),
        ]);

        $stats[] = Stat::make('Owed to suppliers', $metrics->payables()->display())
            ->description('On live purchases')
            ->color('gray');

        /*
         * The number nothing else surfaces.
         *
         * Approval is a warning rather than a wall, so this is what that
         * judgement is currently costing you: goods on order that nobody has
         * committed to buying.
         */
        $stats[] = Stat::make('Bought at your own risk', $atRisk->display())
            ->description($atRisk->isPositive() ? 'Nobody has approved these yet' : 'Everything is approved')
            ->color($atRisk->isPositive() ? 'danger' : 'success');

        $stats[] = Stat::make('Lost on transfers', $losses->display())
            ->description('What the exchange took, above the quoted rate')
            ->color($losses->isPositive() ? 'warning' : 'gray');

        return $stats;
    }

    /**
     * A true minus outside the symbol.
     *
     * `$-3,431.65` reads as a currency code followed by a negative; the minus
     * belongs to the amount, not to the dollar.
     */
    private function signed(float $amount): string
    {
        return ($amount < 0 ? '−' : '').'$'.number_format(abs($amount), 2);
    }
}
