<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasWidgetRange;
use App\Services\Reporting\BusinessMetrics;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * What the bank account actually did, as opposed to what the business earned.
 *
 * Profit and cash are not the same number and the gap between them is where a
 * trading business gets into trouble: you can have a profitable month and an
 * empty account because the goods were paid for in it and the customer pays in
 * the next. This is the other half of the picture — money in against money out,
 * and whether the account rose or fell across the window.
 *
 * It moves with the time filter, because cash is a flow. Cost is the whole
 * content, so it is hidden from anyone without `view_cost`.
 */
class CashFlowWidget extends Widget
{
    use HasWidgetRange;
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.cash-flow';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('view_cost') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function flow(): array
    {
        $metrics = app(BusinessMetrics::class);
        [$from, $to] = $this->activeRange();

        $in = $metrics->cashIn($from, $to);
        $out = $metrics->cashOut($from, $to);
        $net = $in->minus($out);

        return [
            'windowLabel' => $this->activeLabel(),
            'in' => $in->display(),
            'out' => $out->display(),
            'net' => $this->signed($net->toFloat()),
            // The one figure the widget exists to deliver: colour lands here,
            // and only here, because a fall in the account is the thing to act on.
            'netTone' => $net->isNegative() ? 'critical' : ($net->isPositive() ? 'good' : null),
        ];
    }

    /** A true minus outside the symbol, as the position tiles do it. */
    private function signed(float $amount): string
    {
        return ($amount < 0 ? '−' : '').'$'.number_format(abs($amount), 2);
    }
}
