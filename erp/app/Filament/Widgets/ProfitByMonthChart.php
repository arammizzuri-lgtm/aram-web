<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasWidgetRange;
use App\Models\Deal;
use App\Support\Money;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Profit per month, twelve months back.
 *
 * Drawn rather than charted, for the same reason the customer account is. The
 * charting library gave a canvas a third of a screen tall to show one bar,
 * with a y-axis of eight gridlines standing over eleven empty months — a chart
 * whose furniture outweighed its data, telling you about itself rather than
 * about the business. A young business has mostly empty months and the design
 * has to be honest about that instead of inflating one column to fill a box.
 *
 * So: a compact strip of columns against a zero line, with the total and the
 * best month stated in words above it, because those are the two things anybody
 * actually takes from a twelve-month profit chart. Every column carries its own
 * figure for a hover.
 *
 * Polarity is the point — above the line or below it — with position doing the
 * work and colour only confirming it. That is what keeps it readable for
 * someone who cannot tell the two colours apart.
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
        $months = $this->months();
        $values = $months->pluck('profit')->all();

        $high = max(max($values), 0.0);
        $low = min(min($values), 0.0);
        $span = ($high - $low) ?: 1.0;

        // Headroom, so the tallest column is never flush with the edge.
        $high += $span * 0.15;
        $low -= $span * 0.08;
        $span = $high - $low;

        $zero = round((1 - ((0 - $low) / $span)) * 100, 2);

        $columns = $months->map(function (array $month) use ($low, $span, $zero): array {
            $y = round((1 - (($month['profit'] - $low) / $span)) * 100, 2);
            $positive = $month['profit'] >= 0;

            return [
                ...$month,
                /*
                 * Percentages of the plot rather than pixels, so the columns
                 * keep their proportions at any card width and the whole thing
                 * scales without a redraw.
                 */
                'top' => $positive ? $y : $zero,
                // A month at exactly zero still gets a hairline, so the run of
                // months reads as a run rather than as gaps.
                'height' => max(0.6, abs($zero - $y)),
                'positive' => $positive,
                'empty' => abs($month['profit']) < 0.005,
            ];
        });

        return [
            'columns' => $columns,
            'zero' => $zero,
            'total' => Money::of(array_sum($values), 'USD'),
            'best' => $months->sortByDesc('profit')->first(),
            'anything' => collect($values)->contains(fn (float $value) => abs($value) > 0.005),
        ];
    }

    /**
     * One entry per calendar month the window touches, oldest first.
     *
     * The window is whatever this widget is looking at — its own or, by default,
     * the dashboard's. The first and last months are clamped to the window's
     * edges, so a range that opens mid-month counts only the days it covers
     * rather than inventing the rest of the month around it.
     *
     * @return Collection<int, array{label: string, full: string, profit: float}>
     */
    private function months(): Collection
    {
        [$from, $to] = $this->activeRange();

        $cursor = $from->copy()->startOfMonth();
        $last = $to->copy()->startOfMonth();
        $months = collect();

        while ($cursor->lte($last)) {
            $monthFrom = $cursor->copy()->startOfMonth()->max($from);
            $monthTo = $cursor->copy()->endOfMonth()->min($to);

            $deals = Deal::query()
                ->whereBetween('deal_date', [$monthFrom, $monthTo])
                ->whereNot('status', 'cancelled')
                ->with(['lines', 'purchases.costs', 'expenses', 'consignments'])
                ->get();

            $months->push([
                'label' => $cursor->format('M'),
                'full' => $cursor->format('F Y'),
                'profit' => round($deals->sum(fn (Deal $deal) => $deal->profitBase()->toFloat()), 2),
            ]);

            $cursor->addMonth();
        }

        return $months;
    }
}
