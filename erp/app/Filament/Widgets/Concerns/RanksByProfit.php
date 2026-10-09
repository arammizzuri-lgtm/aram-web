<?php

namespace App\Filament\Widgets\Concerns;

use App\Support\ChartFormat;
use Illuminate\Support\Collection;

/**
 * A ranking by profit — customers, categories — drawn one way.
 *
 * Both rankings carry the same figures (revenue, cost, profit, margin, deals)
 * and want the same three views of them: a list where each row shows profit
 * inside its revenue, a strip of how the total profit divides, and the table.
 * Built here once, so the two charts cannot drift into two styles.
 *
 * Rows arrive as BusinessMetrics returns them, largest profit first, each with
 * `revenue`, `cost`, `profit`, `margin` and `deals`.
 */
trait RanksByProfit
{
    /** How many rows the list draws; the table always has every one. */
    protected int $listed = 8;

    /**
     * The list rows, for <x-erp.ranked-list>.
     *
     * Every bar is measured against the widest figure in the list, so a loss of
     * 400 and a profit of 400 read as equally heavy — scaled against the biggest
     * profit, a bad month would look small.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>): ?string  $url
     * @return array<int, array<string, mixed>>
     */
    protected function profitRows(Collection $rows, string $name, callable $url): array
    {
        $earned = $this->earned($rows);
        $listed = $rows->take($this->listed);
        $widest = max(1.0, (float) $listed->max('revenue'), (float) $listed->max(fn (array $r) => abs($r['profit'])));
        $width = fn (float $value): float => round(abs($value) / $widest * 100, 2);

        return $listed->values()->map(function (array $row) use ($name, $url, $earned, $width): array {
            $share = $earned > 0 && $row['profit'] > 0 ? $row['profit'] / $earned * 100 : 0.0;
            $deals = $row['deals'].' '.str('deal')->plural($row['deals']);

            return [
                'name' => $row[$name],
                'url' => $url($row),
                'valueText' => ChartFormat::money($row['profit']),
                'negative' => $row['profit'] < 0,
                'detail' => implode(' · ', array_filter([
                    'revenue '.ChartFormat::compact($row['revenue']),
                    ChartFormat::percent($row['margin']).' margin',
                    $deals,
                    $share > 0 ? ChartFormat::percent($share).' of the profit' : null,
                ])),
                'track' => [
                    ['width' => $width($row['revenue']), 'colour' => 'var(--erp-muted-fill)'],
                    ['width' => $width($row['profit']), 'colour' => $row['profit'] < 0 ? 'var(--erp-critical)' : 'var(--erp-good)'],
                ],
                'tip' => array_values(array_filter([
                    $row[$name],
                    ($row['profit'] < 0 ? 'Lost ' : 'Profit ').ChartFormat::money(abs($row['profit']))
                        .($share > 0 ? ' · '.ChartFormat::percent($share).' of the profit' : ''),
                    'Revenue '.ChartFormat::money($row['revenue']).' · cost '.ChartFormat::money($row['cost']),
                    'Margin '.ChartFormat::percent($row['margin']).' · '.$deals,
                ])),
            ];
        })->all();
    }

    /**
     * How the profit divides, for the strip above the list.
     *
     * Only what was earned counts towards it — a share of a total that losses
     * have eaten into is not a share anybody can act on. The leader is picked
     * out in the accent and the rest stay neutral: the story this tells is
     * concentration, and eight colours would bury it.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{segments: array<int, array<string, mixed>>, caption: string}|null
     */
    protected function shareStrip(Collection $rows, string $name, string $noun): ?array
    {
        $earned = $this->earned($rows);
        $earning = $rows->filter(fn (array $r) => $r['profit'] > 0)->values();

        if ($earned <= 0 || $earning->count() < 2) {
            return null;
        }

        $shown = $earning->take($this->listed);
        $rest = $earning->slice($this->listed);

        $segments = $shown->map(fn (array $r, int $i) => [
            'width' => round($r['profit'] / $earned * 100, 2),
            'colour' => $i === 0 ? 'var(--erp-series-1)' : 'var(--erp-muted-fill)',
            'tip' => [$r[$name], ChartFormat::money($r['profit']).' · '.ChartFormat::percent($r['profit'] / $earned * 100).' of the profit'],
        ])->all();

        if ($rest->isNotEmpty()) {
            $other = $rest->sum('profit');
            $segments[] = [
                'width' => round($other / $earned * 100, 2),
                'colour' => 'var(--erp-muted-fill)',
                'tip' => ['Everyone else ('.$rest->count().')', ChartFormat::money($other).' · '.ChartFormat::percent($other / $earned * 100).' of the profit'],
            ];
        }

        $leader = $earning->first();
        $topThree = $earning->take(3)->sum('profit') / $earned * 100;

        return [
            'segments' => $segments,
            'caption' => sprintf(
                '%s brings %s of the profit%s.',
                $leader[$name],
                ChartFormat::percent($leader['profit'] / $earned * 100),
                $earning->count() > 3 ? '; the top three '.$noun.', '.ChartFormat::percent($topThree) : '',
            ),
        ];
    }

    /**
     * The same rows, exact, every one of them — for the Table view.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{columns: array<int, array<string, mixed>>, rows: array<int, array<int, string>>, footer: array<int, string>}
     */
    protected function profitTable(Collection $rows, string $name, string $heading): array
    {
        $earned = $this->earned($rows);
        $revenue = (float) $rows->sum('revenue');
        $profit = (float) $rows->sum('profit');

        return [
            'columns' => [
                ['label' => $heading],
                ['label' => 'Deals', 'numeric' => true],
                ['label' => 'Revenue', 'numeric' => true],
                ['label' => 'Cost', 'numeric' => true],
                ['label' => 'Profit', 'numeric' => true],
                ['label' => 'Margin', 'numeric' => true],
                ['label' => 'Share', 'numeric' => true],
            ],
            'rows' => $rows->map(fn (array $r) => [
                $r[$name],
                (string) $r['deals'],
                ChartFormat::money($r['revenue']),
                ChartFormat::money($r['cost']),
                ChartFormat::money($r['profit']),
                ChartFormat::percent($r['margin']),
                $earned > 0 && $r['profit'] > 0 ? ChartFormat::percent($r['profit'] / $earned * 100) : '—',
            ])->values()->all(),
            'footer' => [
                'Total',
                '',
                ChartFormat::money($revenue),
                ChartFormat::money((float) $rows->sum('cost')),
                ChartFormat::money($profit),
                ChartFormat::percent($revenue > 0 ? $profit / $revenue * 100 : 0),
                '',
            ],
        ];
    }

    /** @param  Collection<int, array<string, mixed>>  $rows */
    private function earned(Collection $rows): float
    {
        return (float) $rows->sum(fn (array $r) => max(0.0, (float) $r['profit']));
    }
}
