<?php

namespace App\Filament\Widgets;

use App\Services\Reporting\BusinessMetrics;
use App\Support\Money;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Which suppliers are actually worth buying from.
 *
 * The mirror of the customer ranking, and the more useful of the two for
 * deciding anything: a customer is mostly given to you, while a supplier is
 * chosen — so this is the chart a next order can be changed by.
 *
 * Two costs, both exact and both belonging to the supplier alone. The goods
 * margin comes off the deal lines, which carry their own cost, their own sell
 * price and the supplier they came from. The transfer cost comes off their
 * payments, which record what the exchange really took above the quoted rate —
 * the cheap supplier who is expensive to pay is the one this exists to find.
 *
 * Freight is not in it. A consignment carries deals rather than suppliers, so a
 * freight figure here would be apportioned by a rule nobody agreed to. It is
 * compared on the reports screen instead, by shipping mode, where the question
 * can be answered without inventing anything.
 */
class SupplierProfitChart extends Widget
{
    protected string $view = 'filament.widgets.supplier-profit';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    /** Supplier cost is the entire content. */
    public static function canView(): bool
    {
        return auth()->user()?->can('view_cost') ?? false;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(): Collection
    {
        $rows = app(BusinessMetrics::class)
            ->profitBySupplier(now()->subDays(90)->startOfDay(), now()->endOfDay())
            ->take(8);

        // Bars are drawn against the largest magnitude either way, so a supplier
        // who lost you four hundred reads as heavily as one who made it.
        $widest = max(1.0, $rows->max(fn (array $row) => abs((float) $row['profit'])) ?: 1.0);

        return $rows->map(fn (array $row) => [
            ...$row,
            'money' => Money::of($row['profit'], 'USD'),
            'transfer' => Money::of($row['transfer_cost'], 'USD'),
            'negative' => (float) $row['profit'] < 0,
            'share' => max(2, round(abs((float) $row['profit']) / $widest * 100)),
        ]);
    }
}
