<?php

namespace App\Support;

/**
 * How a figure is written on a chart, in one place.
 *
 * Every chart was carrying its own little `$signed` closure, and they had
 * started to disagree about minus signs. These are the forms the dashboard
 * uses: the exact amount for tables and hover cards, a compact one for labels
 * drawn on the chart itself, where cents are noise.
 */
final class ChartFormat
{
    /** $1,234.56 — a true minus outside the symbol; "+" only when asked for. */
    public static function money(float $amount, bool $signed = false): string
    {
        return self::sign($amount, $signed).'$'.number_format(abs($amount), 2);
    }

    /** $9.2k, $1.4M, $760 — for labels drawn on the chart. */
    public static function compact(float $amount, bool $signed = false): string
    {
        $abs = abs($amount);

        $text = match (true) {
            $abs >= 1_000_000 => self::trim(number_format($abs / 1_000_000, 1)).'M',
            $abs >= 1_000 => self::trim(number_format($abs / 1_000, 1)).'k',
            default => number_format($abs, 0),
        };

        return self::sign($amount, $signed).'$'.$text;
    }

    /** 21.8%, 20%, −17% — one decimal, none when it is zero, a true minus. */
    public static function percent(float $value): string
    {
        return self::sign($value, false).self::trim(number_format(abs($value), 1)).'%';
    }

    /** An amount in the currency it changed hands in: $1,250.00, ¥8,640.00, IQD 1,637,500. */
    public static function inCurrency(float $amount, string $currency): string
    {
        return match (strtoupper($currency)) {
            'USD' => '$'.number_format($amount, 2),
            'CNY', 'RMB' => '¥'.number_format($amount, 2),
            'IQD' => 'IQD '.number_format($amount, 0),
            default => strtoupper($currency).' '.number_format($amount, 2),
        };
    }

    /** CNY is what the system stores; RMB is what anybody here calls it. */
    public static function currencyName(string $currency): string
    {
        return strtoupper($currency) === 'CNY' ? 'RMB' : strtoupper($currency);
    }

    private static function sign(float $amount, bool $signed): string
    {
        return match (true) {
            $amount < 0 && abs($amount) >= 0.005 => '−',
            $signed && $amount >= 0.005 => '+',
            default => '',
        };
    }

    /** "9.0" → "9", "21.8" stays. Only ever given a one-decimal figure. */
    private static function trim(string $figure): string
    {
        return rtrim(rtrim($figure, '0'), '.');
    }
}
