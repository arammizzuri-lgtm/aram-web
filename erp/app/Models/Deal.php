<?php

namespace App\Models;

use App\Services\Deals\DealDiscounts;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * One customer request, from "they asked" to "they paid".
 *
 * This is the centre of the system. It replaces the old sales-order-plus-
 * purchase-order-plus-shipment arrangement, which assumed goods pass through a
 * warehouse and could therefore be tracked independently of any customer. Here
 * nothing is ever bought except against a request that already exists, so the
 * request is the only sensible thing to organise around.
 */
class Deal extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'draft' => 'Draft',
        'quoted' => 'Quoted',
        'approved' => 'Approved',
        'purchasing' => 'Purchasing',
        'shipping' => 'Shipping',
        'arrived' => 'Arrived',
        'delivered' => 'Delivered',
        'closed' => 'Closed',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'number', 'customer_id', 'status', 'deal_date', 'expected_delivery',
        'rmb_usd_rate', 'iqd_usd_rate', 'sell_currency',
        'deal_commission', 'deal_commission_currency',
        'supplier_discount_percent', 'supplier_discount_amount', 'supplier_discount_currency',
        'pass_supplier_discount_on', 'profit_discount_percent', 'profit_discount_amount',
        'supplier_discount_base', 'customer_discount', 'customer_discount_base',
        'customer_notes', 'internal_notes', 'created_by', 'approved_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'deal_date' => 'date',
            'expected_delivery' => 'date',
            'rmb_usd_rate' => 'decimal:6',
            'iqd_usd_rate' => 'decimal:6',
            'deal_commission' => 'decimal:4',
            'supplier_discount_percent' => 'decimal:4',
            'supplier_discount_amount' => 'decimal:4',
            'pass_supplier_discount_on' => 'boolean',
            'profit_discount_percent' => 'decimal:4',
            'profit_discount_amount' => 'decimal:4',
            'supplier_discount_base' => 'decimal:4',
            'customer_discount' => 'decimal:4',
            'customer_discount_base' => 'decimal:4',
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    // ------------------------------------------------------------ relations

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DealLine::class)->orderBy('display_order');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(DealPurchase::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class)->orderByDesc('version');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(CustomerInvoice::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function consignments(): BelongsToMany
    {
        return $this->belongsToMany(Consignment::class)
            ->using(ConsignmentDeal::class)
            ->withPivot(['freight_share', 'freight_share_base', 'share_is_manual'])
            ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The quotation the customer actually agreed to, if any. */
    public function approvedQuotation(): ?Quotation
    {
        return $this->quotations()->where('status', 'approved')->first();
    }

    // ---------------------------------------------------------------- money

    /**
     * Convert an amount in one of the deal's currencies into USD.
     *
     * The single place the direction of a rate is decided. Rates are stored the
     * way you type them — units of foreign currency per one dollar, so 7.20 for
     * yuan and 1,470 for dinars — because that is how they are quoted to you.
     * Getting to dollars therefore means dividing, not multiplying.
     *
     * Division rather than multiplying by 1/rate on purpose: 1/1470 rounded to
     * the eight decimal places `convertTo()` allows loses about 0.3 IQD per
     * million, which is small but accumulates silently across a year of deals.
     */
    public function toBase(Money $amount): Money
    {
        $rate = $this->rateFor($amount->currency);

        if ($rate === '1') {
            return Money::of($amount->amount, 'USD');
        }

        return Money::of($amount->dividedBy($rate, Money::CALC_SCALE)->amount, 'USD');
    }

    /**
     * Any of the deal's currencies into what the customer is billed in.
     *
     * Through the dollar, because yuan and dinars have no rate between them
     * here — both are quoted against it, so it is the only road from one to the
     * other. Rates are units per dollar, so the journey is a division followed
     * by a multiplication.
     *
     * Both steps are held at calculation precision and rounded once at the end.
     * Rounding the dollar in the middle, as a Money-typed intermediate does,
     * costs four decimal places — which is nothing in dollars and 0.147 dinars
     * once the dinar rate multiplies it back up, and it showed: ¥12.50 plus 25%
     * came out at 3,190.08 dinars where the arithmetic says 3,190.10.
     */
    public function toSellCurrency(Money $amount): Money
    {
        $converted = $amount
            ->dividedBy($this->rateFor($amount->currency), Money::CALC_SCALE)
            ->times($this->rateFor($this->sell_currency), Money::CALC_SCALE);

        return Money::of($converted->amount, $this->sell_currency);
    }

    /**
     * Sum that tolerates an empty list.
     *
     * Money::sum() throws on no arguments, which is right for it — summing
     * nothing has no currency to report. Here the currency is always USD, and a
     * deal with no lines yet is perfectly ordinary.
     *
     * @param  iterable<Money>  $amounts
     */
    private static function totalBase(iterable $amounts): Money
    {
        $total = Money::zero('USD');

        foreach ($amounts as $amount) {
            $total = $total->plus($amount);
        }

        return $total;
    }

    /** What the customer is charged for goods, before the deal commission. */
    public function goodsRevenueBase(): Money
    {
        return self::totalBase(
            $this->lines->map(fn (DealLine $line) => $line->sellTotalBase())
        );
    }

    /**
     * The same figure in the currency the customer is actually billed in.
     *
     * Priced from this deal's own currency rather than through
     * `DealLine::sellTotal()`, which reaches back up for the deal it belongs to.
     * That round trip is a lazy load, and lazy loading is off — so a discount
     * saved from the purchase screen, where the lines were never eager-loaded
     * with their deal, threw rather than saving.
     */
    public function goodsRevenue(): Money
    {
        $currency = $this->sell_currency ?: 'USD';
        $total = Money::zero($currency);

        foreach ($this->lines as $line) {
            $total = $total->plus(Money::of($line->unit_price, $currency)->times($line->quantity));
        }

        return $total;
    }

    /** What the items cost you before any supplier knocked anything off. */
    public function grossGoodsCostBase(): Money
    {
        return self::totalBase(
            $this->lines->map(fn (DealLine $line) => $line->costTotalBase())
        );
    }

    public function commissionBase(): Money
    {
        if ((float) $this->deal_commission <= 0) {
            return Money::zero('USD');
        }

        return $this->toBase(Money::of(
            $this->deal_commission,
            $this->deal_commission_currency ?: $this->sell_currency,
        ));
    }

    /**
     * Every supplier concession on this deal, in USD.
     *
     * The deal-wide one plus each supplier's own. Read from the frozen figures
     * rather than recomputed, so a rate typed today cannot resize a discount
     * agreed in March — the same rule the line totals follow.
     */
    public function supplierDiscountBase(): Money
    {
        return Money::of($this->supplier_discount_base, 'USD')
            ->plus(Money::of($this->purchases->sum('discount_base'), 'USD'));
    }

    /** What comes off the customer's total, in the currency they are billed in. */
    public function customerDiscount(): Money
    {
        return Money::of($this->customer_discount, $this->sell_currency ?: 'USD');
    }

    public function customerDiscountBase(): Money
    {
        return Money::of($this->customer_discount_base, 'USD');
    }

    public function hasDiscount(): bool
    {
        return ! $this->supplierDiscountBase()->isZero()
            || ! $this->customerDiscountBase()->isZero();
    }

    /**
     * Rework the discounts from the deal as it stands now.
     *
     * The live answer, as against the frozen one above. DealWriter calls this
     * on save and stamps the result; nothing else should, because everything
     * else wants the figure as it was agreed rather than as it would be today.
     */
    public function computeDiscounts(): DealDiscounts
    {
        $perSupplier = Money::zero('USD');

        foreach ($this->purchases as $purchase) {
            $perSupplier = $perSupplier->plus($this->toBase($purchase->discountTotal()));
        }

        return DealDiscounts::of(
            $this,
            $this->grossGoodsCostBase(),
            $this->goodsRevenue(),
            $perSupplier,
        );
    }

    /**
     * What the customer owes, after anything you took off.
     *
     * The discount is subtracted here rather than written back into the line
     * prices, so the items go on saying what they were quoted at and the
     * concession stays a visible row on the invoice — which is most of the
     * value of giving one.
     */
    public function revenueBase(): Money
    {
        return $this->goodsRevenueBase()
            ->plus($this->commissionBase())
            ->minus($this->customerDiscountBase());
    }

    /**
     * Everything the deal cost you, in USD.
     *
     * Four parts, and leaving any of them out overstates what you earned:
     * the goods themselves, extra costs on each purchase, your share of the
     * freight, and any expense charged to this deal.
     */
    public function costBase(): Money
    {
        // Net of every supplier concession: the point of one is that you send
        // them less, and a cost that ignored it would report profit you never
        // actually made back.
        $goods = $this->grossGoodsCostBase()->minus($this->supplierDiscountBase());

        $extras = self::totalBase(
            $this->purchases->map(fn (DealPurchase $p) => $p->extraCostsBase())
        );

        $freight = Money::of(
            $this->consignments->sum(fn ($c) => (float) $c->pivot->freight_share_base),
            'USD',
        );

        $expenses = Money::of($this->expenses->sum('base_amount'), 'USD');

        return $goods->plus($extras)->plus($freight)->plus($expenses);
    }

    public function profitBase(): Money
    {
        return $this->revenueBase()->minus($this->costBase());
    }

    public function marginPercent(): float
    {
        $revenue = $this->revenueBase()->toFloat();

        return $revenue > 0
            ? round($this->profitBase()->toFloat() / $revenue * 100, 2)
            : 0.0;
    }

    /**
     * Whether per-line profit on this deal is exact or an estimate.
     *
     * A deal commission belongs to the whole request, not to any product in it.
     * Any way of spreading it across lines is a guess, so reports say so rather
     * than presenting a number that looks precise and is not.
     */
    public function perLineProfitIsApproximate(): bool
    {
        return (float) $this->deal_commission > 0 || $this->hasDiscount();
    }

    /**
     * The rate you last used, to pre-fill the box rather than start it blank.
     *
     * You asked to type the rate on every deal rather than keep a standing one,
     * so this is a starting point and nothing more — it is never applied on its
     * own, and the value that ends up on the deal is whatever you leave in the
     * field. Falls back to null so an empty system shows an empty box instead
     * of an invented number.
     */
    public static function lastRate(string $column): ?string
    {
        $rate = static::query()
            ->whereNotNull($column)
            ->orderByDesc('id')
            ->value($column);

        return $rate === null ? null : (string) $rate;
    }

    /**
     * The frozen rate for a currency, or 1 for USD.
     *
     * Rates are stamped on the deal when it is created and never revisited.
     * Without that, changing a rate would rewrite the profit on every deal
     * already closed.
     */
    public function rateFor(string $currency): string
    {
        /*
         * Cast before the fallback, not after.
         *
         * The rates are decimal-cast, so an unset one reads back as the string
         * "0.000000" — which PHP counts as true, since only "0" and "" are
         * false. Written as `$this->rmb_usd_rate ?: 1` the zero was therefore
         * kept and handed to toBase() to divide by.
         */
        return match (strtoupper($currency)) {
            'USD' => '1',
            'CNY', 'RMB' => (string) (((float) $this->rmb_usd_rate) ?: 1),
            'IQD' => (string) (((float) $this->iqd_usd_rate) ?: 1),
            default => '1',
        };
    }

    // --------------------------------------------------------------- status

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /** Purchases made before the customer committed — money at your own risk. */
    public function atRiskPurchases(): Collection
    {
        return $this->purchases->where('bought_at_risk', true);
    }

    public function amountInvoicedBase(): float
    {
        return (float) $this->invoices()->whereNot('status', 'cancelled')->sum('total_base');
    }

    /** Goods on the deal that cannot travel as ordinary air cargo. */
    public function hasBatteryGoods(): bool
    {
        return $this->lines->contains(fn (DealLine $line) => (bool) $line->contains_battery);
    }

    /**
     * Rough weight and volume, from whatever the catalogue knows.
     *
     * Only used to *suggest* a share of a shared freight bill, never to compute
     * one. Custom one-off items have no weight recorded and contribute nothing,
     * which is why the suggestion is a starting number the operator adjusts
     * rather than an answer the system asserts.
     */
    public function estimatedWeightKg(): float
    {
        return $this->lines->sum(
            fn (DealLine $line) => (float) $line->quantity * (float) ($line->product?->weight_kg ?? 0)
        );
    }

    public function estimatedCbm(): float
    {
        return $this->lines->sum(
            fn (DealLine $line) => (float) $line->quantity * (float) ($line->product?->volume_cbm ?? 0)
        );
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['closed', 'cancelled']);
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }
}
