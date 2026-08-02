<?php

namespace App\Filament\Widgets;

use App\Models\Deal;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Profit per month, twelve months back.
 *
 * A single series, so there is no legend — the heading names it. Polarity is
 * the point of the chart, so it uses the diverging pair from the validated
 * palette (blue above zero, red below) with the zero baseline doing the primary
 * work; colour only reinforces what position already says, which is what keeps
 * it readable without colour.
 */
class ProfitByMonthChart extends ChartWidget
{
    protected ?string $heading = 'Profit by month';

    protected ?string $description = 'What each month earned, in USD, after everything it cost.';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    /** Cost is the whole content — hidden from anyone without `view_cost`. */
    public static function canView(): bool
    {
        return auth()->user()?->can('view_cost') ?? false;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $months = collect(range(11, 0))->map(fn (int $back) => now()->subMonths($back)->startOfMonth());

        $profits = $months->map(function (Carbon $month) {
            $deals = Deal::query()
                ->whereBetween('deal_date', [$month, $month->copy()->endOfMonth()])
                ->whereNot('status', 'cancelled')
                ->with(['lines', 'purchases.costs', 'expenses', 'consignments'])
                ->get();

            return round($deals->sum(fn (Deal $deal) => $deal->profitBase()->toFloat()), 2);
        });

        return [
            'datasets' => [[
                'label' => 'Profit',
                'data' => $profits->all(),
                /*
                 * Blue above zero, red below — the diverging pair from
                 * docs/05-UIUX.md. Not the reserved `critical` status colour:
                 * that one ships with an icon and a label and means something
                 * has gone wrong, whereas a losing month is a value on a scale.
                 *
                 * One hardcoded pair for both themes, which needed checking
                 * rather than assuming: the panel runs on ThemeMode::System and
                 * Chart.js takes its colours from PHP, which cannot know which
                 * mode the reader is in. Validated on both surfaces —
                 *
                 *   light #ffffff · dark #17171a
                 *   lightness PASS · chroma PASS · contrast PASS (both ≥ 3:1)
                 *   CVD adjacent ΔE 21.6 protan · normal-vision ΔE 32.3
                 *
                 * so no per-mode swap is needed. Re-run the palette validator
                 * before changing either value.
                 */
                'backgroundColor' => $profits
                    ->map(fn (float $p) => $p < 0 ? '#e34948' : '#2a78d6')
                    ->all(),
                'borderRadius' => 4,
                'borderSkipped' => false,
            ]],
            'labels' => $months->map(fn (Carbon $m) => $m->format('M'))->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            // One series needs no legend; the heading names it.
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'y' => [
                    'grid' => ['color' => 'rgba(140,140,140,0.15)'],
                    'ticks' => ['color' => '#898781'],
                ],
                'x' => [
                    'grid' => ['display' => false],
                    'ticks' => ['color' => '#898781'],
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
