<?php

namespace App\Services\Deals;

use App\Models\Deal;
use App\Support\Money;

/**
 * What the two discounts on a finished deal come to.
 *
 * One class rather than arithmetic in the model and again in the form, because
 * the deal screen has to show these figures while the rows are still being
 * typed — against an unsaved deal that exists only as form state — and the
 * saved record has to report the same numbers afterwards. Written twice they
 * would eventually disagree, and the place it would show is the profit box,
 * which is the one figure on that screen a decision is made on.
 *
 * Everything here is derived. Nothing is stored from this class; DealWriter
 * stamps the results onto the deal at save time, the same way line totals are
 * frozen, so a rate typed in August cannot move a discount agreed in March.
 *
 * ------------------------------------------------------------------- the maths
 *
 * The supplier's side, in dollars:
 *
 *   per-supplier discounts        each purchase's own concession
 *   + deal-wide percentage        taken off what is LEFT after those, because
 *                                 "and another 2% off the lot" is a second
 *                                 concession on the reduced figure, not a
 *                                 second slice of the original
 *   + deal-wide typed amount
 *
 * The customer's side, in the currency they are billed in:
 *
 *   passed-on   the supplier's effective percentage, applied to the goods, and
 *               only when you have said to pass it on. Goods rather than the
 *               grand total on purpose: the concession was on goods, and the
 *               commission is your fee for arranging — the supplier had no
 *               hand in it. It also keeps one figure valid on both documents,
 *               since the quotation carries no commission line and the invoice
 *               does.
 *
 *   profit      a share of what the ITEMS earn — their selling total less what
 *               they cost you after the supplier's discount — plus any flat sum
 *               you typed. Measured before the passed-on discount, so it is a
 *               steady number: a fifth of the margin agreed on the phone stays
 *               that, rather than shrinking because of a second discount
 *               applied in the same breath.
 */
final readonly class DealDiscounts
{
    private function __construct(
        /** Every supplier concession on this deal, in USD, off what you pay. */
        public Money $supplier,

        /** The deal-wide part of it, kept apart so the screen can show both. */
        public Money $dealWideSupplier,

        /** The supplier's concession handed to the customer, in their currency. */
        public Money $passedOn,

        /** What you gave away out of margin, in the customer's currency. */
        public Money $profitGiven,

        /** The item profit the percentage was taken from, in USD. */
        public Money $profitBefore,
    ) {}

    /**
     * Work the discounts out from the figures a deal comes down to.
     *
     * Takes totals rather than a loaded deal so the form can call it with what
     * is currently on screen. `$deal` is only ever read for its rates, its
     * selling currency and the discount fields themselves — an unsaved carrier
     * is perfectly acceptable, and is what the deal screen passes.
     *
     * @param  Money  $grossGoodsCost  what the items cost, in USD, before any discount
     * @param  Money  $goodsRevenue  what the items sell for, in the selling currency
     * @param  Money  $perSupplierDiscount  the purchases' own discounts, in USD
     */
    public static function of(
        Deal $deal,
        Money $grossGoodsCost,
        Money $goodsRevenue,
        Money $perSupplierDiscount,
    ): self {
        $sellCurrency = $deal->sell_currency ?: 'USD';

        $dealWide = self::dealWideSupplierDiscount($deal, $grossGoodsCost, $perSupplierDiscount);
        $supplier = $perSupplierDiscount->plus($dealWide);

        $passedOn = $deal->pass_supplier_discount_on
            ? $goodsRevenue->times(self::ratio($supplier, $grossGoodsCost), Money::CALC_SCALE)
            : Money::zero($sellCurrency);

        /*
         * The margin the percentage is a share of.
         *
         * Net of the supplier's discount, because that is the profit you
         * actually stand to make; giving away a fifth of a figure the supplier
         * has already improved would be giving away less than you meant to.
         */
        $profitBefore = $deal->toBase($goodsRevenue)->minus($grossGoodsCost->minus($supplier));

        return new self(
            supplier: $supplier,
            dealWideSupplier: $dealWide,
            passedOn: Money::of($passedOn->amount, $sellCurrency),
            profitGiven: self::profitDiscount($deal, $profitBefore),
            profitBefore: $profitBefore,
        );
    }

    /** A deal with nothing typed into either box. */
    public static function none(string $sellCurrency = 'USD'): self
    {
        return new self(
            supplier: Money::zero('USD'),
            dealWideSupplier: Money::zero('USD'),
            passedOn: Money::zero($sellCurrency),
            profitGiven: Money::zero($sellCurrency),
            profitBefore: Money::zero('USD'),
        );
    }

    /** Everything that comes off the customer's total, in their currency. */
    public function customer(): Money
    {
        return $this->passedOn->plus($this->profitGiven);
    }

    public function isEmpty(): bool
    {
        return $this->supplier->isZero() && $this->customer()->isZero();
    }

    /**
     * A discount bigger than the margin it came out of.
     *
     * Reported rather than prevented. Selling at a loss to keep a customer is a
     * real decision and the system is in no position to overrule it — but it
     * should never be something you find out about afterwards, so the screen
     * says so plainly while there is still time to change the figure.
     */
    public function exceedsProfit(Deal $deal): bool
    {
        return $deal->toBase($this->customer())->isGreaterThan($this->profitBefore);
    }

    // -------------------------------------------------------------- internals

    /**
     * The discount typed against the whole deal rather than one supplier.
     *
     * The percentage bites on what is left after the individual suppliers'
     * concessions; the typed amount is added whole. Both apply together — a
     * supplier saying "5% off, and another ¥200 off the carriage" is making one
     * concession in two parts, and asking you to choose between them would lose
     * half of it.
     */
    private static function dealWideSupplierDiscount(
        Deal $deal,
        Money $grossGoodsCost,
        Money $perSupplierDiscount,
    ): Money {
        $remaining = $grossGoodsCost->minus($perSupplierDiscount);

        $percentage = ((float) $deal->supplier_discount_percent) > 0 && $remaining->isPositive()
            ? $remaining->times((float) $deal->supplier_discount_percent / 100, Money::CALC_SCALE)
            : Money::zero('USD');

        $typed = ((float) $deal->supplier_discount_amount) > 0
            ? $deal->toBase(Money::of(
                $deal->supplier_discount_amount,
                $deal->supplier_discount_currency ?: 'CNY',
            ))
            : Money::zero('USD');

        return Money::of($percentage->amount, 'USD')->plus($typed);
    }

    /**
     * What you chose to give away out of your own margin.
     *
     * A percentage of a loss is nothing rather than a surcharge — "give them
     * 20% of my profit" on a deal that is under water can only sensibly mean
     * zero. A flat sum typed into the box still stands, because that one is a
     * decision about a number, not a share of one.
     */
    private static function profitDiscount(Deal $deal, Money $profitBefore): Money
    {
        $sellCurrency = $deal->sell_currency ?: 'USD';

        $share = ((float) $deal->profit_discount_percent) > 0 && $profitBefore->isPositive()
            ? $deal->toSellCurrency($profitBefore->times(
                (float) $deal->profit_discount_percent / 100,
                Money::CALC_SCALE,
            ))
            : Money::zero($sellCurrency);

        return $share->plus(Money::of($deal->profit_discount_amount ?: 0, $sellCurrency));
    }

    /** How big one amount is against another, as a plain multiplier. */
    private static function ratio(Money $part, Money $whole): float
    {
        return $whole->isPositive() ? $part->toFloat() / $whole->toFloat() : 0.0;
    }
}
