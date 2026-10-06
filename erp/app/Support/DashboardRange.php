<?php

namespace App\Support;

use App\Models\Deal;
use Illuminate\Support\Carbon;

/**
 * Resolves the dashboard's time-range filter into a concrete from/to window.
 *
 * One place decides what "last 90 days" or "2025" or "this quarter" actually
 * means, so every widget that reads the filter agrees on the dates. The filter
 * only ever drives the period-based figures — flows, the customer and supplier
 * rankings, cash flow. Balances and aging are true right now and belong to no
 * window, so they never pass through here.
 */
final class DashboardRange
{
    public const CUSTOM = 'custom';

    /** The window shown before anyone has chosen one. */
    public static function defaultValue(): string
    {
        return '90';
    }

    /**
     * Grouped options for the filter's Select.
     *
     * @return array<string, array<string, string>>
     */
    public static function options(): array
    {
        $years = [];
        foreach (self::availableYears() as $year) {
            $years["year_{$year}"] = (string) $year;
        }

        return [
            'Rolling window' => [
                '7' => 'Last 7 days',
                '30' => 'Last 30 days',
                '90' => 'Last 90 days',
            ],
            'Accounting period' => [
                'month' => 'This month',
                'quarter' => 'This quarter',
                'ytd' => 'Year to date',
            ],
            'Calendar year' => $years,
            'Custom' => [
                self::CUSTOM => 'Custom range',
            ],
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public static function resolve(?array $filters): array
    {
        $value = $filters['range'] ?? self::defaultValue();

        if ($value === self::CUSTOM) {
            $start = $filters['startDate'] ?? null;
            $end = $filters['endDate'] ?? null;

            if ($start && $end) {
                $from = Carbon::parse($start)->startOfDay();
                $to = Carbon::parse($end)->endOfDay();

                // A range entered end-first still means the span between them.
                return $from->lte($to) ? [$from, $to] : [$to->startOfDay(), $from->endOfDay()];
            }

            $value = self::defaultValue();
        }

        return match (true) {
            $value === 'month' => [now()->startOfMonth(), now()->endOfDay()],
            $value === 'quarter' => [now()->startOfQuarter(), now()->endOfDay()],
            $value === 'ytd' => [now()->startOfYear(), now()->endOfDay()],
            str_starts_with((string) $value, 'year_') => self::calendarYear((int) substr($value, 5)),
            default => [now()->subDays((int) $value)->startOfDay(), now()->endOfDay()],
        };
    }

    /**
     * The equal-length window immediately before this one, for comparison.
     *
     * Measured off the span rather than the preset, so a custom range and a
     * calendar year are both compared against the stretch of the same length
     * that ended just before they began.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function previous(Carbon $from, Carbon $to): array
    {
        $length = $to->getTimestamp() - $from->getTimestamp();

        $prevTo = $from->copy()->subSecond();
        $prevFrom = $prevTo->copy()->subSeconds($length);

        return [$prevFrom, $prevTo];
    }

    /** Heading-style name for the current selection. */
    public static function label(?array $filters): string
    {
        $value = $filters['range'] ?? self::defaultValue();

        if ($value === self::CUSTOM) {
            $start = $filters['startDate'] ?? null;
            $end = $filters['endDate'] ?? null;

            if ($start && $end) {
                [$from, $to] = self::resolve($filters);

                return $from->format('M j, Y').' – '.$to->format('M j, Y');
            }

            $value = self::defaultValue();
        }

        return match (true) {
            $value === 'month' => 'This month',
            $value === 'quarter' => 'This quarter',
            $value === 'ytd' => 'Year to date',
            str_starts_with((string) $value, 'year_') => substr($value, 5),
            default => 'Last '.((int) $value).' days',
        };
    }

    /**
     * Years that have deals, newest first, always including the current year.
     *
     * Derived in PHP from the earliest and latest deal date rather than with a
     * date function in SQL, so it reads the same on SQLite here and PostgreSQL
     * in production.
     *
     * @return array<int, int>
     */
    public static function availableYears(): array
    {
        $earliest = Deal::query()->min('deal_date');
        $latest = Deal::query()->max('deal_date');

        $from = $earliest ? Carbon::parse($earliest)->year : now()->year;
        $to = $latest ? Carbon::parse($latest)->year : now()->year;

        $to = max($to, now()->year);
        $from = min($from, $to);

        return range($to, $from); // descending
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private static function calendarYear(int $year): array
    {
        return [
            Carbon::create($year, 1, 1)->startOfDay(),
            Carbon::create($year, 12, 31)->endOfDay(),
        ];
    }
}
