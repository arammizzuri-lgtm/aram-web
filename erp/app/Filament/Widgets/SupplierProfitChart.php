<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Widgets\Concerns\HasWidgetRange;
use App\Services\Reporting\BusinessMetrics;
use App\Support\ChartFormat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Which suppliers are actually worth buying from.
 *
 * The mirror of the customer ranking, and the more useful of the two for
 * deciding anything: a customer is mostly given to you, while a supplier is
 * chosen — so this is the chart a next order can be changed by.
 *
 * Each bar is the goods margin, with the slice the exchange house took marked
 * at its end, so what is left — the profit — is visibly the margin less the
 * cost of paying them. The cheap supplier who is expensive to pay is the one
 * this exists to find, and as a footnote under a name it was easy to miss.
 *
 * Freight is not in it. A consignment carries deals rather than suppliers, so
 * a freight figure here would be apportioned by a rule nobody agreed to. It is
 * compared on the reports screen instead, by shipping mode.
 */
class SupplierProfitChart extends Widget
{
    use HasWidgetRange;
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.supplier-profit';

    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 'full';

    /** Supplier cost is the entire content. */
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

        $all = app(BusinessMetrics::class)->profitBySupplier($from, $to);
        $listed = $all->take(8);

        // Measured against the widest figure either way, so a supplier who lost
        // you four hundred reads as heavily as one who made it.
        $widest = max(
            1.0,
            (float) $listed->max(fn (array $r) => abs($r['goods_margin'])),
            (float) $listed->max(fn (array $r) => abs($r['profit'])),
        );
        $width = fn (float $value): float => round(abs($value) / $widest * 100, 2);

        $rows = $listed->values()->map(function (array $r) use ($width): array {
            $losing = $r['profit'] < 0;

            return [
                'name' => $r['supplier'],
                'url' => PurchaseResource::getUrl('index', [
                    'filters' => ['supplier_id' => ['value' => $r['supplier_id']]],
                ]),
                'valueText' => ChartFormat::money($r['profit']),
                'negative' => $losing,
                'detail' => implode(' · ', array_filter([
                    ChartFormat::percent($r['margin_percent']).' margin',
                    'bought '.ChartFormat::compact($r['cost']),
                    'sold '.ChartFormat::compact($r['revenue']),
                    $r['transfer_cost'] > 0 ? ChartFormat::money($r['transfer_cost']).' lost on transfers' : null,
                ])),
                // The whole margin in the transfer colour, the profit drawn over
                // it — what shows past the profit is what the transfer took.
                'track' => $losing
                    ? [['width' => $width($r['profit']), 'colour' => 'var(--erp-critical)']]
                    : [
                        ['width' => $width($r['goods_margin']), 'colour' => 'var(--erp-serious)'],
                        ['width' => $width($r['profit']), 'colour' => 'var(--erp-good)'],
                    ],
                'tip' => array_values(array_filter([
                    $r['supplier'],
                    ($losing ? 'Lost ' : 'Profit ').ChartFormat::money(abs($r['profit'])).' · '.ChartFormat::percent($r['margin_percent']).' margin',
                    'Goods margin '.ChartFormat::money($r['goods_margin'])
                        .($r['transfer_cost'] > 0 ? ' − transfers '.ChartFormat::money($r['transfer_cost']) : ''),
                    'Bought '.ChartFormat::money($r['cost']).' · sold '.ChartFormat::money($r['revenue']),
                ])),
            ];
        })->all();

        return [
            'rows' => $rows,
            'table' => [
                'columns' => [
                    ['label' => 'Supplier'],
                    ['label' => 'Bought', 'numeric' => true],
                    ['label' => 'Sold', 'numeric' => true],
                    ['label' => 'Goods margin', 'numeric' => true],
                    ['label' => 'Lost on transfers', 'numeric' => true],
                    ['label' => 'Profit', 'numeric' => true],
                    ['label' => 'Margin', 'numeric' => true],
                ],
                'rows' => $all->map(fn (array $r) => [
                    $r['supplier'],
                    ChartFormat::money($r['cost']),
                    ChartFormat::money($r['revenue']),
                    ChartFormat::money($r['goods_margin']),
                    ChartFormat::money($r['transfer_cost']),
                    ChartFormat::money($r['profit']),
                    ChartFormat::percent($r['margin_percent']),
                ])->values()->all(),
                'footer' => [
                    'Total',
                    ChartFormat::money((float) $all->sum('cost')),
                    ChartFormat::money((float) $all->sum('revenue')),
                    ChartFormat::money((float) $all->sum('goods_margin')),
                    ChartFormat::money((float) $all->sum('transfer_cost')),
                    ChartFormat::money((float) $all->sum('profit')),
                    ChartFormat::percent($all->sum('revenue') > 0 ? $all->sum('profit') / $all->sum('revenue') * 100 : 0),
                ],
            ],
        ];
    }
}
