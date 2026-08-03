<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What the customer wants, what it costs you, and what they pay — on one row.
 *
 * Carrying both sides together is the single decision that makes "never enter
 * the same thing twice" possible. The supplier's purchase document is these
 * lines grouped by supplier; the customer's invoice is these lines with the
 * cost columns removed. Neither requires re-entry, and the two can never
 * disagree about quantities because there is only one set of them.
 */
class DealLine extends Model
{
    public const PRICING_METHODS = [
        'markup' => 'Cost + %',
        'manual' => 'Typed price',
        'list' => 'From price list',
    ];

    protected $fillable = [
        'deal_id', 'deal_purchase_id', 'supplier_id',
        'product_id', 'product_size_id', 'catalogue_item_id', 'crystal_product_id', 'crystal_size_id',
        'description', 'description_ku', 'description_zh', 'specification', 'photo_path',
        'quantity', 'unit',
        'unit_cost', 'cost_currency', 'cost_total_base',
        'unit_price', 'sell_total_base',
        'pricing_method', 'markup_percent', 'contains_battery', 'display_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'cost_total_base' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'sell_total_base' => 'decimal:4',
            'markup_percent' => 'decimal:4',
            'contains_battery' => 'boolean',
        ];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /**
     * Where this line is bought from.
     *
     * Named directly on the line rather than only through the purchase, because
     * you often know the supplier while the deal is still a draft and nothing
     * has been ordered. DealWriter turns these into purchase documents.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** The internal purchase document this line was grouped into. */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(DealPurchase::class, 'deal_purchase_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function catalogueItem(): BelongsTo
    {
        return $this->belongsTo(CatalogueItem::class);
    }

    public function crystalProduct(): BelongsTo
    {
        return $this->belongsTo(CrystalProduct::class);
    }

    public function crystalSize(): BelongsTo
    {
        return $this->belongsTo(CrystalSize::class);
    }

    /** Typed on the deal rather than chosen from a catalogue. */
    public function isCustom(): bool
    {
        return $this->product_id === null
            && $this->catalogue_item_id === null
            && $this->crystal_product_id === null;
    }

    // ---------------------------------------------------------------- money

    public function costTotal(): Money
    {
        return Money::of($this->unit_cost, $this->cost_currency)->times($this->quantity);
    }

    public function sellTotal(): Money
    {
        return Money::of($this->unit_price, $this->deal?->sell_currency ?? 'USD')
            ->times($this->quantity);
    }

    /**
     * Totals in USD, read straight from the frozen figure.
     *
     * Not recomputed from the deal's rate: reaching for the rate at read time
     * is exactly how historical profit starts drifting. The stamped value is
     * the answer as it stood when the deal was agreed, and that is the only
     * answer that stays true.
     */
    public function costTotalBase(): Money
    {
        return Money::of($this->cost_total_base, 'USD');
    }

    public function sellTotalBase(): Money
    {
        return Money::of($this->sell_total_base, 'USD');
    }

    /** Per-unit USD, derived for display. Never stored — see the migration. */
    public function unitCostBase(): Money
    {
        $quantity = (float) $this->quantity;

        return $quantity > 0
            ? $this->costTotalBase()->dividedBy($quantity)
            : Money::zero('USD');
    }

    public function profitBase(): Money
    {
        return $this->sellTotalBase()->minus($this->costTotalBase());
    }

    public function marginPercent(): float
    {
        $revenue = $this->sellTotalBase()->toFloat();

        return $revenue > 0
            ? round($this->profitBase()->toFloat() / $revenue * 100, 2)
            : 0.0;
    }

    /**
     * Selling price implied by the markup, in the deal's selling currency.
     *
     * The margin is taken in the currency the goods were bought in, and the
     * result converted once. Both orders are the same arithmetic, but not the
     * same rounding: converting first fixes the unit cost at four decimals and
     * then multiplies that rounding by the markup, so ¥50 plus 150% came out a
     * hundredth of a cent above ¥125 ÷ 6.7.
     *
     * Which would not matter, except that the deal screen shows the yuan price
     * — ¥125 — beside the converted one. Two figures shown together have to
     * reconcile when somebody checks them by hand, and the way to guarantee
     * that is to do the arithmetic in the same order they read it.
     */
    public function priceFromMarkup(Deal $deal): ?Money
    {
        if ($this->markup_percent === null) {
            return null;
        }

        /*
         * Marks up the raw unit cost rather than the stored USD figure. That
         * one is a line total frozen for reporting; deriving a per-unit price
         * back out of it would round twice for no reason.
         */
        $withMargin = Money::of($this->unit_cost, $this->cost_currency)
            ->times(1 + (float) $this->markup_percent / 100, Money::CALC_SCALE);

        return $deal->toSellCurrency($withMargin);
    }
}
