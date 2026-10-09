<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Deals\DealResource;
use App\Filament\Widgets\Concerns\HasWidgetRange;
use App\Services\Reporting\BusinessMetrics;
use App\Support\ChartFormat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * How the profit for the window was built, month by month.
 *
 * A waterfall rather than a row of bars, because the question the owner opens
 * it with is "how did we get to this total?" — and a bar per month answers a
 * different one. Each month starts where the last one ended and steps up by
 * what it earned or down by what it lost, so a bad month is a visible drop in
 * the run rather than a short bar that reads as merely smaller. The last column
 * is the total itself, standing on zero.
 *
 * Drawn in markup rather than handed to a charting library, for the reason the
 * card says in its docblock history: a young business has mostly empty months,
 * and the drawing has to be honest about that instead of inflating one column
 * to fill a box. An empty month is a flat stretch of the line, not a gap.
 *
 * Hover any month for what it was made of; click it for its deals.
 */
class ProfitByMonthChart extends Widget
{
    use HasWidgetRange;
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.profit-by-month';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    /** Cost is the whole content — hidden from anyone without `view_cost`. */
    public static function canView(): bool
    {
        return auth()->user()?->can('view_cost') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function chart(): array
    {
        [$from, $to] = $this->activeRange();

        $months = app(BusinessMetrics::class)->profitByMonth($from, $to);
        $multiYear = $from->year !== $to->year;

        // Where each month starts and ends: the run the waterfall draws.
        $running = 0.0;
        $steps = $months->map(function (array $month) use (&$running, $multiYear): array {
            $start = $running;
            $running = round($running + $month['profit'], 2);

            return [
                ...$month,
                'start' => $start,
                'end' => $running,
                'axis' => $month['month']->format($multiYear ? "M 'y" : 'M'),
            ];
        });

        $total = $running;
        $revenue = round($steps->sum('revenue'), 2);
        $deals = (int) $steps->sum('deals');

        [$low, $high, $step] = $this->scale(
            min(0.0, $steps->min('start') ?? 0.0, $steps->min('end') ?? 0.0),
            max(0.0, $steps->max('start') ?? 0.0, $steps->max('end') ?? 0.0),
        );

        // Percent from the top of the plot: the drawing is all relative, so it
        // keeps its shape at any card width without a redraw.
        $y = fn (float $value): float => round(($high - $value) / ($high - $low) * 100, 2);

        $columns = $steps->map(fn (array $s): array => [
            'kind' => 'step',
            'axis' => $s['axis'],
            'empty' => abs($s['profit']) < 0.005,
            'up' => $s['profit'] >= 0,
            'top' => $y(max($s['start'], $s['end'])),
            'height' => max(abs($y($s['start']) - $y($s['end'])), 0.8),
            'level' => $y($s['end']),
            'colour' => $s['profit'] >= 0 ? 'var(--erp-good)' : 'var(--erp-critical)',
            'label' => ChartFormat::compact($s['profit'], signed: true),
            'url' => $this->dealsBetween($s['from'], $s['to']),
            'tip' => $this->monthTip($s),
        ])->values();

        $columns->push([
            'kind' => 'total',
            'axis' => 'Total',
            'empty' => abs($total) < 0.005,
            'up' => $total >= 0,
            'top' => $y(max(0.0, $total)),
            'height' => max(abs($y(0.0) - $y($total)), 0.8),
            'level' => $y($total),
            'colour' => $total >= 0 ? 'var(--erp-series-1)' : 'var(--erp-critical)',
            'label' => ChartFormat::compact($total),
            'url' => $this->dealsBetween($from, $to),
            'tip' => array_values(array_filter([
                'Total, '.$this->activeLabel(),
                ($total >= 0 ? 'Profit ' : 'Loss ').ChartFormat::money($total),
                'Revenue '.ChartFormat::money($revenue).' · cost '.ChartFormat::money($steps->sum('cost')),
                'Margin '.ChartFormat::percent($revenue > 0 ? $total / $revenue * 100 : 0).' · '.$deals.' '.str('deal')->plural($deals),
            ])),
        ]);

        $ticks = [];
        for ($value = $low; $value <= $high + $step / 1000; $value += $step) {
            $ticks[] = ['y' => $y($value), 'label' => ChartFormat::compact($value), 'zero' => abs($value) < $step / 1000];
        }

        $best = $steps->where('profit', '>', 0)->sortByDesc('profit')->first();

        return [
            'anything' => $steps->contains(fn (array $s) => abs($s['profit']) >= 0.005),
            'columns' => $columns,
            'ticks' => $ticks,
            // On a long run the per-step figures crowd; hover and the table carry
            // them then, and only the total is written on the chart.
            'labelled' => $steps->filter(fn (array $s) => abs($s['profit']) >= 0.005)->count() <= 12,
            'total' => $total,
            'totalText' => ChartFormat::money($total),
            'best' => $best ? $best['full'] : null,
            'table' => $steps->map(fn (array $s) => [
                $s['full'],
                (string) $s['deals'],
                ChartFormat::money($s['revenue']),
                ChartFormat::money($s['cost']),
                ChartFormat::money($s['profit'], signed: true),
                ChartFormat::percent($s['margin']),
                ChartFormat::money($s['end']),
            ])->all(),
            'footer' => [
                'Total',
                (string) $deals,
                ChartFormat::money($revenue),
                ChartFormat::money($steps->sum('cost')),
                ChartFormat::money($total, signed: true),
                ChartFormat::percent($revenue > 0 ? $total / $revenue * 100 : 0),
                '',
            ],
        ];
    }

    /**
     * What a month was made of, for its hover card.
     *
     * @param  array<string, mixed>  $s
     * @return array<int, string>
     */
    private function monthTip(array $s): array
    {
        if ($s['deals'] === 0) {
            return [$s['full'], 'No deals this month', 'Running total '.ChartFormat::money($s['end'])];
        }

        return [
            $s['full'],
            ($s['profit'] >= 0 ? 'Earned ' : 'Lost ').ChartFormat::money(abs($s['profit'])),
            'Revenue '.ChartFormat::money($s['revenue']).' · cost '.ChartFormat::money($s['cost']),
            'Margin '.ChartFormat::percent($s['margin']).' · '.$s['deals'].' '.str('deal')->plural($s['deals']),
            'Running total '.ChartFormat::money($s['end']),
        ];
    }

    /** The deals list, narrowed to the days a column covers. */
    private function dealsBetween(Carbon $from, Carbon $to): string
    {
        return DealResource::getUrl('index', [
            'filters' => ['deal_date' => ['from' => $from->toDateString(), 'until' => $to->toDateString()]],
        ]);
    }

    /**
     * Round gridline values — 0, 2.5k, 5k — and the span they cover.
     *
     * The plot runs from one gridline to another, so the tallest step never
     * sits flush with the edge and every line on it is a number worth reading.
     *
     * @return array{0: float, 1: float, 2: float}
     */
    private function scale(float $low, float $high): array
    {
        $rough = max($high - $low, 1.0) / 4;
        $magnitude = 10 ** floor(log10($rough));
        $step = collect([1, 2, 2.5, 5, 10])
            ->map(fn (float|int $m) => $m * $magnitude)
            ->first(fn (float|int $s) => $s >= $rough);

        $min = floor($low / $step) * $step;
        $max = ceil($high / $step) * $step;

        return [(float) $min, (float) ($max > $min ? $max : $min + $step), (float) $step];
    }
}
