<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasWidgetRange;
use App\Services\Reporting\BusinessMetrics;
use App\Support\DashboardRange;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Where the business stands, in two groups that mean different things.
 *
 * The tiles this replaces sat in one block under the heading "Last 30 days",
 * and half of them were not thirty-day figures at all. Invoiced and profit are
 * **flows** — they happen across a window. What a customer owes you, what you
 * owe suppliers, and what you have bought without approval are **balances**:
 * they are true at this moment and have no window. Putting them under one time
 * heading told the reader something false, and "Owed to you — last 30 days" is
 * a sentence that cannot be acted on because it does not mean anything.
 *
 * So there are two rows, each labelled with its own timeframe, and one figure
 * at display size. Six tiles of equal weight is not a hierarchy — the eye has
 * no way in, and everything competes. Profit leads because it is the question
 * the owner opens the screen to ask.
 *
 * Colour lands only where something wants doing: a loss, or goods bought that
 * nobody has committed to. A figure that is merely large is not an alarm.
 */
class PositionWidget extends Widget
{
    use HasWidgetRange;
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.position';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    public function position(): array
    {
        $metrics = app(BusinessMetrics::class);
        [$from, $to] = $this->activeRange();
        [$prevFrom, $prevTo] = DashboardRange::previous($from, $to);

        $canSeeCost = auth()->user()?->can('view_cost') ?? false;

        $receivables = $metrics->receivables();
        $credit = $metrics->customerCredit();

        /*
         * Flows first, and only what the window actually covers. Each one
         * carries how it moved against the window before it, because a figure
         * on its own cannot tell you whether it is the good news or the bad.
         *
         * The assistant sees what was billed and nothing beneath it; the tiles
         * are dropped rather than blanked, because a row with figures reading
         * "—" invites exactly the question the permission exists to prevent.
         */
        $flows = [];

        if ($canSeeCost) {
            $operating = $metrics->operatingProfit($from, $to);
            $gross = $metrics->profit($from, $to);
            $revenue = $metrics->revenue($from, $to);
            $overheads = $metrics->overheads($from, $to);
            $freight = $metrics->freightSpend($from, $to);
            $losses = $metrics->transferLosses($from, $to);

            $flows[] = [
                'label' => 'Operating profit',
                'value' => $this->signed($operating->toFloat()),
                'hint' => $metrics->marginPercent($from, $to).'% gross margin',
                'lead' => true,
                'tone' => $operating->isNegative() ? 'critical' : null,
                'compare' => $this->delta(
                    $operating->toFloat(),
                    $metrics->operatingProfit($prevFrom, $prevTo)->toFloat(),
                    higherIsGood: true,
                ),
            ];

            $flows[] = [
                'label' => 'Gross profit',
                'value' => $this->signed($gross->toFloat()),
                'hint' => 'before overheads',
                'tone' => $gross->isNegative() ? 'critical' : null,
                'compare' => $this->delta(
                    $gross->toFloat(),
                    $metrics->profit($prevFrom, $prevTo)->toFloat(),
                    higherIsGood: true,
                ),
            ];

            $flows[] = [
                'label' => 'Invoiced',
                'value' => $revenue->display(),
                'hint' => 'what customers were billed',
                'compare' => $this->delta(
                    $revenue->toFloat(),
                    $metrics->revenue($prevFrom, $prevTo)->toFloat(),
                    higherIsGood: true,
                ),
            ];

            $flows[] = [
                'label' => 'Overheads',
                'value' => $overheads->display(),
                'hint' => 'rent, tools, wages — no single deal pays these',
                'compare' => $this->delta(
                    $overheads->toFloat(),
                    $metrics->overheads($prevFrom, $prevTo)->toFloat(),
                    higherIsGood: false,
                ),
            ];

            $flows[] = [
                'label' => 'Freight',
                'value' => $freight->display(),
                'hint' => 'shipping you paid for',
                'compare' => $this->delta(
                    $freight->toFloat(),
                    $metrics->freightSpend($prevFrom, $prevTo)->toFloat(),
                    higherIsGood: false,
                ),
            ];

            $flows[] = [
                'label' => 'Lost on transfers',
                'value' => $losses->display(),
                'hint' => 'what the exchange took above the rate',
                'tone' => $losses->isPositive() ? 'warning' : null,
                'compare' => $this->delta(
                    $losses->toFloat(),
                    $metrics->transferLosses($prevFrom, $prevTo)->toFloat(),
                    higherIsGood: false,
                ),
            ];
        } else {
            $revenue = $metrics->revenue($from, $to);

            $flows[] = [
                'label' => 'Invoiced',
                'value' => $revenue->display(),
                'hint' => 'what customers were billed',
                'lead' => true,
                'compare' => $this->delta(
                    $revenue->toFloat(),
                    $metrics->revenue($prevFrom, $prevTo)->toFloat(),
                    higherIsGood: true,
                ),
            ];
        }

        // Balances. True now, and belonging to no window at all.
        $balances = [
            [
                'label' => 'Owed to you',
                'value' => $receivables->display(),
                'hint' => 'invoiced and not yet settled',
            ],
            [
                'label' => 'Credit you hold',
                'value' => $credit->display(),
                'hint' => 'their money, against no invoice',
            ],
        ];

        if ($canSeeCost) {
            $atRisk = $metrics->boughtAtRisk();

            $balances[] = [
                'label' => 'Owed to suppliers',
                'value' => $metrics->payables()->display(),
                'hint' => 'on live purchases',
            ];

            /*
             * The number nothing else surfaces. Approval is a warning rather
             * than a wall, so this is what that judgement is costing right now:
             * goods on order that nobody has committed to buying.
             */
            $balances[] = [
                'label' => 'Bought at your own risk',
                'value' => $atRisk->display(),
                'hint' => $atRisk->isPositive() ? 'nobody has approved these' : 'everything is approved',
                'tone' => $atRisk->isPositive() ? 'critical' : 'good',
            ];
        }

        return [
            'flows' => $flows,
            'balances' => $balances,
            'windowLabel' => $this->activeLabel(),
        ];
    }

    /**
     * How a figure moved against the window before it.
     *
     * A percentage needs something to be a percentage *of*: with no prior
     * activity there is no change to state, so this returns nothing rather than
     * a division by zero dressed up as "▲∞%". Colour lands only where more is
     * plainly better or worse — profit rising is good news, profit falling is
     * not; a change in overheads or freight is reported without a verdict,
     * because spending more is not wrong on its face.
     *
     * @return array{text: string, tone: ?string}|null
     */
    private function delta(float $current, float $previous, bool $higherIsGood): ?array
    {
        if (abs($previous) < 0.005) {
            return null;
        }

        $pct = (int) round(($current - $previous) / abs($previous) * 100);

        if ($pct === 0) {
            return ['text' => 'level vs the window before', 'tone' => null];
        }

        $up = $current > $previous;
        $tone = $higherIsGood ? ($up ? 'good' : 'critical') : null;

        return [
            'text' => ($up ? '▲' : '▼').abs($pct).'% vs the window before',
            'tone' => $tone,
        ];
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
