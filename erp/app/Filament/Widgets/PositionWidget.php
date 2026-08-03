<?php

namespace App\Filament\Widgets;

use App\Services\Reporting\BusinessMetrics;
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
    protected string $view = 'filament.widgets.position';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    /**
     * The window, stated rather than measured back off the dates.
     *
     * Deriving it with `diffInDays` gave "Last 31.999999999988 days": the
     * window runs from the start of a day to the end of another, so the
     * difference is a float a hair under 31 and no rounding of it is the number
     * anybody meant. The window is thirty days because this says so.
     */
    private const DAYS = 30;

    /**
     * @return array<string, mixed>
     */
    public function position(): array
    {
        $metrics = app(BusinessMetrics::class);
        [$from, $to] = $metrics->window(self::DAYS);

        $canSeeCost = auth()->user()?->can('view_cost') ?? false;

        $receivables = $metrics->receivables();
        $credit = $metrics->customerCredit();

        /*
         * Flows first, and only what the window actually covers.
         *
         * The assistant sees what was billed and nothing beneath it; the tiles
         * are dropped rather than blanked, because a row with three figures
         * reading "—" invites exactly the question the permission exists to
         * prevent.
         */
        $flows = [];

        if ($canSeeCost) {
            $profit = $metrics->profit($from, $to);
            $losses = $metrics->transferLosses($from, $to);

            $flows[] = [
                'label' => 'Profit',
                'value' => $this->signed($profit->toFloat()),
                'hint' => $metrics->marginPercent($from, $to).'% margin',
                'lead' => true,
                'tone' => $profit->isNegative() ? 'critical' : null,
            ];

            $flows[] = [
                'label' => 'Invoiced',
                'value' => $metrics->revenue($from, $to)->display(),
                'hint' => 'what customers were billed',
            ];

            $flows[] = [
                'label' => 'Freight',
                'value' => $metrics->freightSpend($from, $to)->display(),
                'hint' => 'shipping you paid for',
            ];

            $flows[] = [
                'label' => 'Lost on transfers',
                'value' => $losses->display(),
                'hint' => 'what the exchange took above the rate',
                'tone' => $losses->isPositive() ? 'warning' : null,
            ];
        } else {
            $flows[] = [
                'label' => 'Invoiced',
                'value' => $metrics->revenue($from, $to)->display(),
                'hint' => 'what customers were billed',
                'lead' => true,
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
            'window' => self::DAYS,
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
