<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasWidgetRange;
use App\Services\Reporting\BusinessMetrics;
use App\Support\ChartFormat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * What you sold and bought, in the money it changed hands in.
 *
 * The business is priced in three currencies — dollars and dinars on the
 * selling side, yuan and dollars on the buying side — and every other figure
 * on the dashboard has already been turned into dollars. This is the one place
 * that turning is undone: how much of the trade sits in each currency, which
 * is the exposure a rate move would land on.
 *
 * Every amount is shown both ways, its own and in dollars at the rate stamped
 * on the deal, because converting quietly is how a yuan exposure ends up
 * reading as a dollar one. Goods only: a commission or a discount belongs to
 * the deal, not to any one line's currency.
 */
class CurrencyExposureWidget extends Widget
{
    use HasWidgetRange;
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.currency-exposure';

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    /** The buying side is supplier cost. */
    public static function canView(): bool
    {
        return auth()->user()?->can('view_cost') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function exposure(): array
    {
        [$from, $to] = $this->activeRange();

        $data = app(BusinessMetrics::class)->currencyExposure($from, $to);

        $sides = [
            $this->side('Sold', 'sold', $data['sold']),
            $this->side('Bought', 'bought', $data['bought']),
        ];

        $used = $data['sold']->concat($data['bought'])->pluck('currency')->unique();

        return [
            'anything' => collect($sides)->contains(fn (array $side) => $side['segments'] !== []),
            'sides' => $sides,
            'legend' => collect(['USD', 'CNY', 'IQD'])
                ->filter(fn (string $c) => $used->contains($c))
                ->map(fn (string $c) => [ChartFormat::currencyName($c), self::colour($c)])
                ->values()->all(),
            'table' => collect(['Sold' => $data['sold'], 'Bought' => $data['bought']])
                ->flatMap(fn (Collection $rows, string $side) => $rows->map(fn (array $r) => [
                    $side,
                    ChartFormat::currencyName($r['currency']),
                    ChartFormat::inCurrency($r['original'], $r['currency']),
                    ChartFormat::money($r['base']),
                    ChartFormat::percent($r['share']),
                ]))
                ->values()->all(),
        ];
    }

    /**
     * One side of the trade as a single bar divided by currency.
     *
     * @param  Collection<int, array{currency: string, original: float, base: float, share: float}>  $rows
     * @return array<string, mixed>
     */
    private function side(string $heading, string $verb, Collection $rows): array
    {
        $rows = $rows->filter(fn (array $r) => $r['base'] > 0)->values();

        return [
            'heading' => $heading,
            'total' => ChartFormat::money((float) $rows->sum('base')),
            'segments' => $rows->map(fn (array $r) => [
                'width' => $r['share'],
                'colour' => self::colour($r['currency']),
                // Written inside the segment only where it fits; a sliver keeps
                // its figure in the hover card and the line underneath.
                'label' => $r['share'] >= 14 ? ChartFormat::currencyName($r['currency']).' '.ChartFormat::percent($r['share']) : null,
                'tip' => [
                    ChartFormat::currencyName($r['currency']).' — '.$verb,
                    ChartFormat::inCurrency($r['original'], $r['currency']),
                    '≈ '.ChartFormat::money($r['base']).' · '.ChartFormat::percent($r['share']).' of what you '.$verb,
                ],
            ])->all(),
            'amounts' => $rows->map(fn (array $r) => ChartFormat::inCurrency($r['original'], $r['currency'])
                .($r['currency'] !== 'USD' ? ' ≈ '.ChartFormat::money($r['base']) : ''))->implode('  ·  '),
        ];
    }

    /**
     * A currency keeps its colour everywhere — the validated categorical slots
     * in their fixed order — so "RMB is orange" holds on both bars.
     */
    private static function colour(string $currency): string
    {
        return match (strtoupper($currency)) {
            'USD' => 'var(--erp-series-1)',
            'CNY', 'RMB' => 'var(--erp-series-2)',
            'IQD' => 'var(--erp-series-3)',
            default => 'var(--erp-muted-fill)',
        };
    }
}
