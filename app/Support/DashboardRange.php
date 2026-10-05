<?php

namespace App\Support;

use App\Models\PageView;
use Illuminate\Support\Carbon;

/** Resolves the dashboard's time-range filter into a concrete start/end window. */
final class DashboardRange
{
    public const CUSTOM = 'custom';

    public static function defaultValue(): string
    {
        return 'year_'.now()->year;
    }

    /** Grouped options for the filter form's Select field. */
    public static function options(): array
    {
        $years = PageView::availableYears();

        return [
            'Rolling window' => [
                '7' => 'Last 7 days',
                '30' => 'Last 30 days',
                '90' => 'Last 90 days',
            ],
            'Calendar year' => array_combine(
                array_map(fn (int $year) => "year_{$year}", $years),
                $years,
            ),
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
                return [Carbon::parse($start)->startOfDay(), Carbon::parse($end)->endOfDay()];
            }

            $value = self::defaultValue();
        }

        if (str_starts_with($value, 'year_')) {
            $year = (int) substr($value, 5);

            return [Carbon::create($year, 1, 1)->startOfDay(), Carbon::create($year, 12, 31)->endOfDay()];
        }

        $days = (int) $value;

        return [now()->subDays($days - 1)->startOfDay(), now()->endOfDay()];
    }

    /** Human-readable label for the currently selected range, for chart headings. */
    public static function label(?array $filters): string
    {
        $value = $filters['range'] ?? self::defaultValue();

        if ($value === self::CUSTOM) {
            $start = $filters['startDate'] ?? null;
            $end = $filters['endDate'] ?? null;

            if (! $start || ! $end) {
                return self::label(null);
            }

            return Carbon::parse($start)->format('M j, Y').' – '.Carbon::parse($end)->format('M j, Y');
        }

        if (str_starts_with($value, 'year_')) {
            return substr($value, 5);
        }

        return 'last '.((int) $value).' days';
    }
}
