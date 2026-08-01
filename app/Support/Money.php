<?php

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An immutable amount in a single currency, held as a base-10 string.
 *
 * Money is never a float anywhere in this system. Every figure that reaches a
 * customer, a supplier or a cost report passes through here, so the arithmetic
 * is done with bcmath at a fixed scale rather than in binary floating point.
 *
 * Amounts are stored at SCALE (4) to match the `numeric(19,4)` columns.
 * Intermediate work — notably cost allocation — runs at CALC_SCALE (6) and is
 * only reduced to SCALE at the end, so repeated division does not bleed value.
 */
final readonly class Money implements JsonSerializable, Stringable
{
    /** Matches the `numeric(19,4)` money columns. */
    public const int SCALE = 4;

    /** Intermediate precision, so chained division keeps its tail. */
    public const int CALC_SCALE = 6;

    private function __construct(
        public string $amount,
        public string $currency,
    ) {}

    public static function of(string|int|float $amount, string $currency): self
    {
        return new self(self::normalise($amount, self::SCALE), self::currency($currency));
    }

    public static function zero(string $currency): self
    {
        return self::of('0', $currency);
    }

    /** Sum a list, taking the currency from the list itself. */
    public static function sum(Money ...$amounts): self
    {
        if ($amounts === []) {
            throw new InvalidArgumentException('Cannot sum an empty list of Money.');
        }

        return array_reduce(
            array_slice($amounts, 1),
            fn (self $carry, self $item) => $carry->plus($item),
            $amounts[0],
        );
    }

    // ---------------------------------------------------------------- maths

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self(bcadd($this->amount, $other->amount, self::SCALE), $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self(bcsub($this->amount, $other->amount, self::SCALE), $this->currency);
    }

    /** Multiply by a scalar — a quantity or a rate, never another Money. */
    public function times(string|int|float $multiplier, int $scale = self::SCALE): self
    {
        $product = bcmul($this->amount, self::normalise($multiplier, self::CALC_SCALE), self::CALC_SCALE + 2);

        return new self(self::round($product, $scale), $this->currency);
    }

    public function dividedBy(string|int|float $divisor, int $scale = self::SCALE): self
    {
        $divisor = self::normalise($divisor, self::CALC_SCALE);

        if (bccomp($divisor, '0', self::CALC_SCALE) === 0) {
            throw new InvalidArgumentException('Division by zero.');
        }

        $quotient = bcdiv($this->amount, $divisor, self::CALC_SCALE + 2);

        return new self(self::round($quotient, $scale), $this->currency);
    }

    public function negated(): self
    {
        return new self(bcmul($this->amount, '-1', self::SCALE), $this->currency);
    }

    public function absolute(): self
    {
        return $this->isNegative() ? $this->negated() : $this;
    }

    /** Reduce to a currency's real precision — IQD has none, USD has two. */
    public function roundTo(int $decimals): self
    {
        return new self(self::round($this->amount, $decimals), $this->currency);
    }

    /** Express this amount in another currency at a given rate. */
    public function convertTo(string $currency, string|int|float $rate): self
    {
        $converted = bcmul($this->amount, self::normalise($rate, 8), self::CALC_SCALE + 2);

        return new self(self::round($converted, self::SCALE), self::currency($currency));
    }

    // ----------------------------------------------------------- allocation

    /**
     * Split this amount across the given ratios, losing nothing to rounding.
     *
     * Each share is rounded to SCALE, then the residual — always smaller than
     * one minor unit per share — is handed to the largest ratio. That keeps
     * `sum(shares) === this` exactly, which is what lets a landed-cost run
     * reconcile to the cent against the shipment costs it came from.
     *
     * Keys are preserved, so callers can map shares straight back onto lines.
     *
     * @param  array<array-key, string|int|float>  $ratios
     * @return array<array-key, self>
     */
    public function allocate(array $ratios): array
    {
        if ($ratios === []) {
            throw new InvalidArgumentException('Cannot allocate across an empty set of ratios.');
        }

        $normalised = array_map(fn ($ratio) => self::normalise($ratio, self::CALC_SCALE), $ratios);

        foreach ($normalised as $ratio) {
            if (bccomp($ratio, '0', self::CALC_SCALE) < 0) {
                throw new InvalidArgumentException('Allocation ratios cannot be negative.');
            }
        }

        $total = array_reduce(
            $normalised,
            fn (string $carry, string $ratio) => bcadd($carry, $ratio, self::CALC_SCALE),
            '0',
        );

        // Nothing to weight by (a zero-CBM shipment, say) — fall back to an even split.
        if (bccomp($total, '0', self::CALC_SCALE) === 0) {
            $normalised = array_fill_keys(array_keys($normalised), '1');
            $total = (string) count($normalised);
        }

        $shares = [];
        foreach ($normalised as $key => $ratio) {
            $share = bcdiv(bcmul($this->amount, $ratio, self::CALC_SCALE + 4), $total, self::CALC_SCALE + 2);
            $shares[$key] = new self(self::round($share, self::SCALE), $this->currency);
        }

        $residual = $this->minus(self::sum(...array_values($shares)));

        if (! $residual->isZero()) {
            $largest = array_keys($normalised, max($normalised))[0];
            $shares[$largest] = $shares[$largest]->plus($residual);
        }

        return $shares;
    }

    // ---------------------------------------------------------- comparison

    public function compareTo(self $other): int
    {
        $this->assertSameCurrency($other);

        return bccomp($this->amount, $other->amount, self::SCALE);
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->compareTo($other) === 0;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function isLessThan(self $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function isZero(): bool
    {
        return bccomp($this->amount, '0', self::SCALE) === 0;
    }

    public function isNegative(): bool
    {
        return bccomp($this->amount, '0', self::SCALE) < 0;
    }

    public function isPositive(): bool
    {
        return bccomp($this->amount, '0', self::SCALE) > 0;
    }

    // ------------------------------------------------------------- output

    public function format(int $decimals = 2, string $symbol = '', string $position = 'before'): string
    {
        $rounded = self::round($this->amount, $decimals);
        $negative = bccomp($rounded, '0', $decimals) < 0;
        $formatted = number_format((float) ltrim($rounded, '-'), $decimals, '.', ',');

        $withSymbol = $symbol === ''
            ? $formatted
            : ($position === 'after' ? "{$formatted} {$symbol}" : "{$symbol}{$formatted}");

        // A true minus sign, not a hyphen — it lines up in tabular figures.
        return $negative ? "\u{2212}{$withSymbol}" : $withSymbol;
    }

    public function toFloat(): float
    {
        return (float) $this->amount;
    }

    public function __toString(): string
    {
        return "{$this->amount} {$this->currency}";
    }

    /** @return array{amount: string, currency: string} */
    public function jsonSerialize(): array
    {
        return ['amount' => $this->amount, 'currency' => $this->currency];
    }

    // ------------------------------------------------------------ internals

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot combine {$this->currency} with {$other->currency}. Convert one of them first."
            );
        }
    }

    private static function currency(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException("Invalid currency code: {$currency}");
        }

        return $currency;
    }

    /** Accept the usual scalar shapes and hand bcmath a clean decimal string. */
    private static function normalise(string|int|float $value, int $scale): string
    {
        $string = is_float($value)
            ? sprintf('%.'.(self::CALC_SCALE + 4).'F', $value)
            : trim((string) $value);

        if ($string === '' || ! preg_match('/^-?\d+(\.\d+)?$/', $string)) {
            throw new InvalidArgumentException("Not a numeric value: {$string}");
        }

        return self::round($string, $scale);
    }

    /**
     * Half-up rounding, away from zero.
     *
     * bcmath truncates rather than rounds, so adding half a unit at the target
     * scale first turns truncation into the rounding people expect on an invoice.
     */
    private static function round(string $value, int $scale): string
    {
        $negative = str_starts_with($value, '-');
        $absolute = ltrim($value, '-');

        $half = $scale > 0 ? '0.'.str_repeat('0', $scale).'5' : '0.5';
        $rounded = bcadd($absolute, $half, $scale);

        return $negative && bccomp($rounded, '0', $scale) !== 0 ? "-{$rounded}" : $rounded;
    }
}
