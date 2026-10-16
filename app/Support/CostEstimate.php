<?php

namespace App\Support;

use App\Models\SiteSetting;

/**
 * The cost estimator (/estimate): what a house might cost to build, and the
 * studio's design fee for it, as a range — from the rates set in the admin
 * (Manage → Cost estimator).
 *
 * The one place the sums are done. The page asks the server for every
 * result (EstimateController), and an estimate sent with an enquiry is
 * worked out again here from its inputs, so what the studio receives is
 * always this formula at these rates, never a figure typed into a browser.
 *
 *   built area     plot × coverage × floors — or the visitor's own figure
 *   footprint      built area ÷ floors (the basement's area)
 *   building       built area × the finish level's rate, low to high
 *   basement       footprint × the basement rate
 *   bathrooms      each at the finish level's price
 *   pool           a fixed range
 *   design fee     a % of the building cost, or a rate per m² built
 *                  (basement included) — the admin chooses
 *
 * Amounts are US dollars; dinars are shown beside them at the admin's rate.
 */
class CostEstimate
{
    /** Finish levels, in order: their settings are est_{level}_… */
    public const LEVELS = ['standard', 'premium', 'luxury'];

    public const PREFIX = 'est_';

    /** Where the studio has not set a rate yet. Placeholders: set real ones in the admin. */
    public const DEFAULTS = [
        'enabled' => '1',
        'coverage' => '60',              // % of the plot each floor covers
        'max_floors' => '4',
        'iqd_rate' => '1310',            // dinars to the dollar
        'standard_en' => 'Standard', 'standard_ku' => 'ستاندارد',
        'standard_low' => '300', 'standard_high' => '380', 'standard_bath' => '3000',
        'premium_en' => 'Premium', 'premium_ku' => 'باش',
        'premium_low' => '420', 'premium_high' => '520', 'premium_bath' => '6000',
        'luxury_en' => 'Luxury', 'luxury_ku' => 'لوکس',
        'luxury_low' => '600', 'luxury_high' => '800', 'luxury_bath' => '10000',
        'basement_low' => '350', 'basement_high' => '450',
        'pool_low' => '25000', 'pool_high' => '45000',
        'fee_basis' => 'percent',        // percent | per_m2
        'fee_low' => '5', 'fee_high' => '8',
    ];

    public static function get(string $key): string
    {
        $value = SiteSetting::get(self::PREFIX.$key);

        return $value === null || $value === '' ? (self::DEFAULTS[$key] ?? '') : (string) $value;
    }

    public static function num(string $key): float
    {
        return max(0.0, (float) self::get($key));
    }

    public static function enabled(): bool
    {
        return self::get('enabled') !== '0';
    }

    public static function maxFloors(): int
    {
        return max(1, min(12, (int) self::get('max_floors')));
    }

    /** @return array<string, array{en: string, ku: string, low: float, high: float, bath: float}> */
    public static function levels(): array
    {
        $levels = [];
        foreach (self::LEVELS as $level) {
            $levels[$level] = [
                'en' => self::get($level.'_en'),
                'ku' => self::get($level.'_ku') ?: self::get($level.'_en'),
                'low' => self::num($level.'_low'),
                'high' => max(self::num($level.'_low'), self::num($level.'_high')),
                'bath' => self::num($level.'_bath'),
            ];
        }

        return $levels;
    }

    /**
     * The visitor's answers, cleaned: what the form sent, held to sensible
     * limits. Null without a plot (or a built area) to work from.
     *
     * @param  array<string, mixed>  $raw
     * @return array{plot: int, floors: int, built: ?int, basement: bool, bedrooms: int, bathrooms: int, finish: string, pool: bool}|null
     */
    public static function inputs(array $raw): ?array
    {
        $int = fn ($v, int $min, int $max) => is_numeric($v) ? max($min, min($max, (int) round((float) $v))) : null;

        $plot = $int($raw['plot'] ?? null, 20, 100000);
        $built = $int($raw['built'] ?? null, 20, 200000);
        if ($plot === null && $built === null) {
            return null;
        }

        $finish = (string) ($raw['finish'] ?? '');

        return [
            'plot' => $plot ?? 0,
            'floors' => $int($raw['floors'] ?? null, 1, self::maxFloors()) ?? min(2, self::maxFloors()),
            'built' => $built,
            'basement' => filter_var($raw['basement'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'bedrooms' => $int($raw['bedrooms'] ?? null, 0, 20) ?? 3,
            'bathrooms' => $int($raw['bathrooms'] ?? null, 0, 20) ?? 2,
            'finish' => in_array($finish, self::LEVELS, true) ? $finish : 'premium',
            'pool' => filter_var($raw['pool'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /** The built area worked out from the plot alone. */
    public static function autoBuilt(int $plot, int $floors): int
    {
        return (int) round($plot * self::num('coverage') / 100 * $floors);
    }

    /**
     * The estimate for a set of inputs (see inputs()).
     *
     * @param  array{plot: int, floors: int, built: ?int, basement: bool, bedrooms: int, bathrooms: int, finish: string, pool: bool}  $in
     * @return array<string, mixed>
     */
    public static function calculate(array $in): array
    {
        $level = self::levels()[$in['finish']];
        $built = $in['built'] ?? self::autoBuilt($in['plot'], $in['floors']);
        $footprint = (int) round($built / max(1, $in['floors']));
        $basement = $in['basement'] ? $footprint : 0;

        $lines = [];
        $lines['building'] = [$built * $level['low'], $built * $level['high']];
        if ($basement) {
            $lines['basement'] = [$basement * self::num('basement_low'), $basement * max(self::num('basement_low'), self::num('basement_high'))];
        }
        if ($in['bathrooms'] > 0) {
            $lines['bathrooms'] = [$in['bathrooms'] * $level['bath'], $in['bathrooms'] * $level['bath']];
        }
        if ($in['pool']) {
            $lines['pool'] = [self::num('pool_low'), max(self::num('pool_low'), self::num('pool_high'))];
        }

        $low = array_sum(array_column($lines, 0));
        $high = array_sum(array_column($lines, 1));

        $basis = self::get('fee_basis') === 'per_m2' ? 'per_m2' : 'percent';
        $feeLow = self::num('fee_low');
        $feeHigh = max($feeLow, self::num('fee_high'));
        $fee = $basis === 'percent'
            ? [$low * $feeLow / 100, $high * $feeHigh / 100]
            : [($built + $basement) * $feeLow, ($built + $basement) * $feeHigh];

        return [
            'inputs' => $in,
            'finish' => ['en' => $level['en'], 'ku' => $level['ku']],
            'built' => $built,
            'built_auto' => $in['built'] === null,
            'basement_area' => $basement,
            'rates' => ['low' => $level['low'], 'high' => $level['high'], 'bath' => $level['bath']],
            'lines' => array_map(fn ($l) => [self::round($l[0]), self::round($l[1])], $lines),
            'construction' => [self::round($low), self::round($high)],
            'fee' => [self::round($fee[0], 100), self::round($fee[1], 100)],
            'fee_basis' => $basis,
            'fee_rates' => [$feeLow, $feeHigh],
            'iqd_rate' => self::num('iqd_rate'),
        ];
    }

    /** A range reads as an estimate: building figures to the nearest $1,000. */
    private static function round(float $n, int $to = 1000): int
    {
        return (int) (round($n / $to) * $to);
    }

    /**
     * The enquiry form's budget band for an estimate: the one its middle
     * falls in (App\Support\Enquiry::budgets()).
     */
    public static function budgetBand(array $estimate): string
    {
        $mid = array_sum($estimate['construction']) / 2;

        return match (true) {
            $mid < 100_000 => 'under-100k',
            $mid < 250_000 => '100-250k',
            $mid < 500_000 => '250-500k',
            $mid < 1_000_000 => '500k-1m',
            default => 'over-1m',
        };
    }

    public static function usd(int|float $n): string
    {
        return '$'.number_format($n);
    }

    /** "$180,000 – $220,000" */
    public static function usdRange(array $range): string
    {
        return $range[0] === $range[1] ? self::usd($range[0]) : self::usd($range[0]).' – '.self::usd($range[1]);
    }

    /** "≈ 236 – 288 million IQD", or the Kurdish; null with no rate set. */
    public static function iqdRange(array $range, float $rate, string $lang): ?string
    {
        if ($rate <= 0) {
            return null;
        }
        $m = fn ($usd) => self::millions($usd * $rate);
        $text = $m($range[0]) === $m($range[1]) ? $m($range[0]) : $m($range[0]).' – '.$m($range[1]);

        // the numbers kept left to right inside the Kurdish words (LRI … PDI)
        return '≈ '."\u{2066}".$text."\u{2069}".($lang === 'ku' ? ' ملیۆن دینار' : ' million IQD');
    }

    /** Dinars in millions, to one decimal under ten million and whole above. */
    private static function millions(float $iqd): string
    {
        $m = $iqd / 1_000_000;

        return $m < 10 ? rtrim(rtrim(number_format($m, 1), '0'), '.') : number_format(round($m));
    }

    /**
     * A wording with a range in it — "{low} – {high} per m²" — filled in so
     * the range stays one left-to-right piece inside Kurdish text. Without
     * that, "$300 – $380 بۆ هەر م²" is laid out as "$380 – $300".
     */
    public static function rangeIn(string $wording, string $low, string $high): string
    {
        $range = "\u{2066}".$low.($low === $high ? '' : ' – '.$high)."\u{2069}";   // LRI … PDI

        // a callback, not a replacement string: "$300" there would be read as group 3
        return preg_replace_callback('/\{low\}.*?\{high\}/u', fn () => $range, $wording, 1)
            ?? str_replace(['{low}', '{high}'], [$low, $high], $wording);
    }

    /**
     * What the page draws beside the figures (estimate-house.js): the plot,
     * the house's footprint and floors, a basement and a pool — the numbers
     * the estimate was worked out from, nothing more. Null without one.
     *
     * @return array{plot: int, plot_known: bool, footprint: int, built: int, floors: int, basement: bool, pool: bool}|null
     */
    public static function house(?array $estimate): ?array
    {
        if (! $estimate) {
            return null;
        }
        $in = $estimate['inputs'];
        $footprint = (int) round($estimate['built'] / max(1, $in['floors']));
        $coverage = max(1.0, self::num('coverage'));

        return [
            // a built area given without a plot is drawn on the plot it would need
            'plot' => $in['plot'] ?: (int) round($footprint * 100 / $coverage),
            'plot_known' => (bool) $in['plot'],
            'footprint' => $footprint,
            'built' => (int) $estimate['built'],
            'floors' => (int) $in['floors'],
            'basement' => (bool) $in['basement'],
            'pool' => (bool) $in['pool'],
        ];
    }

    /**
     * "Built on a plot like yours": the studio's projects on the plots
     * nearest in size to the visitor's — within a third of it either way,
     * closest first. Empty without a plot, or with nothing near it.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Project>
     */
    public static function similarProjects(int $plot, int $limit = 3)
    {
        if ($plot <= 0) {
            return collect();
        }

        return \App\Models\Project::publishedOrdered()->get()
            ->filter(fn ($p) => $p->hasPage() && $p->plotArea() !== null)
            ->map(fn ($p) => ['project' => $p, 'off' => abs($p->plotArea() - $plot) / $plot])
            ->filter(fn ($m) => $m['off'] <= 0.34)
            ->sortBy('off')
            ->take($limit)
            ->pluck('project')
            ->values();
    }

    /** The finish level's name in a language. */
    public static function finishName(string $level, string $lang): string
    {
        $l = self::levels()[$level] ?? null;

        return $l ? ($lang === 'ku' ? $l['ku'] : $l['en']) : $level;
    }

    /**
     * One line saying what an estimate was for, for the admin and the alert:
     * "600 m² plot · 2 floors · 720 m² built · basement · 4 bedrooms · 3 bathrooms · Premium · pool"
     */
    public static function summary(array $estimate): string
    {
        $in = $estimate['inputs'];

        return collect([
            $in['plot'] ? number_format($in['plot']).' m² plot' : null,
            $in['floors'].' '.($in['floors'] === 1 ? 'floor' : 'floors'),
            number_format($estimate['built']).' m² built'.($estimate['built_auto'] ? '' : ' (their figure)'),
            $in['basement'] ? 'basement' : null,
            $in['bedrooms'].' bedrooms',
            $in['bathrooms'].' bathrooms',
            $estimate['finish']['en'] ?? $in['finish'],
            $in['pool'] ? 'pool' : null,
        ])->filter()->implode(' · ');
    }
}
