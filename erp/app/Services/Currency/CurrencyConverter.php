<?php

namespace App\Services\Currency;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Converts money between currencies at the rate in force on a given date.
 *
 * Every method takes an explicit date. There is deliberately no "current rate"
 * shortcut: a purchase order raised in March must keep its March rate forever,
 * or today's movement in the dinar would silently rewrite last quarter's margins.
 */
class CurrencyConverter
{
    /** @var array<string, string> */
    private array $cache = [];

    public function convert(Money $money, string $to, CarbonInterface|string $on): Money
    {
        $to = strtoupper($to);

        if ($money->currency === $to) {
            return $money;
        }

        return $money->convertTo($to, $this->rate($money->currency, $to, $on));
    }

    /** Convert into the reporting/base currency — the common case. */
    public function toBase(Money $money, CarbonInterface|string $on): Money
    {
        return $this->convert($money, Currency::base(), $on);
    }

    /**
     * The rate to multiply a `$from` amount by to get `$to`, on `$on`.
     *
     * Falls back to the inverse of the opposite pair, so only one direction has
     * to be maintained by hand.
     */
    public function rate(string $from, string $to, CarbonInterface|string $on): string
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return '1';
        }

        $date = $on instanceof CarbonInterface ? $on : Carbon::parse($on);
        $key = "{$from}:{$to}:{$date->toDateString()}";

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $direct = ExchangeRate::query()->inForce($from, $to, $date)->value('rate');

        if ($direct !== null) {
            return $this->cache[$key] = (string) $direct;
        }

        $inverse = ExchangeRate::query()->inForce($to, $from, $date)->value('rate');

        if ($inverse !== null && bccomp((string) $inverse, '0', 8) !== 0) {
            return $this->cache[$key] = bcdiv('1', (string) $inverse, 8);
        }

        throw new RuntimeException(
            "No exchange rate for {$from}→{$to} on or before {$date->toDateString()}."
        );
    }

    public function hasRate(string $from, string $to, CarbonInterface|string $on): bool
    {
        try {
            $this->rate($from, $to, $on);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /** Drop memoised rates — needed after a rate is edited mid-request. */
    public function flush(): void
    {
        $this->cache = [];
    }
}
