<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Widgets\Concerns\HasWidgetRange;
use App\Filament\Widgets\Concerns\RanksByProfit;
use App\Models\Customer;
use App\Services\Reporting\BusinessMetrics;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Which customers actually earned you money — and on what.
 *
 * A ranked list rather than a bar chart: with a handful of names the ranking is
 * the point, and a list gives the name, the exact figure and the way through
 * to the account in a fraction of the height. Each row now shows its profit
 * inside its revenue, because the two disagree often enough to matter — a
 * customer who buys a lot at a thin margin is a long bar with little in it.
 *
 * Ranked by profit rather than revenue, and losses stay in the list, marked,
 * rather than being sorted out of sight: they are the rows worth finding. The
 * strip above says how concentrated the profit is, which is the one thing a
 * list of figures makes you add up yourself.
 */
class TopCustomersChart extends Widget
{
    use HasWidgetRange;
    use InteractsWithPageFilters;
    use RanksByProfit;

    protected string $view = 'filament.widgets.top-customers';

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('view_cost') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function ranking(): array
    {
        [$from, $to] = $this->activeRange();

        $rows = app(BusinessMetrics::class)
            ->profitByCustomer($from, $to)
            // A deal with nothing on it yet is a customer with nothing to rank.
            ->filter(fn (array $row) => $row['revenue'] > 0 || abs($row['profit']) >= 0.005)
            ->values();

        /*
         * The row is a way in, not only a figure. Matched by name because that
         * is what the report carries — looked up in one query for the whole
         * list — and a customer since renamed simply does not link, which is
         * better than linking to the wrong account.
         */
        $accounts = Customer::query()
            ->whereIn('name', $rows->take($this->listed)->pluck('customer'))
            ->pluck('id', 'name');

        $url = fn (array $row): ?string => isset($accounts[$row['customer']])
            ? CustomerResource::getUrl('account', ['record' => $accounts[$row['customer']]])
            : null;

        return [
            'rows' => $this->profitRows($rows, 'customer', $url),
            'strip' => $this->shareStrip($rows, 'customer', 'customers'),
            'table' => $this->profitTable($rows, 'customer', 'Customer'),
        ];
    }
}
