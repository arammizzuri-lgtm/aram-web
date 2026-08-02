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
        'product_id', 'catalogue_item_id', 'crystal_product_id', 'crystal_size_id',
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
     * Goes through USD because cost and sale are usually in different
     * currencies: yuan in, dinars out. Marking up ¥12.50 by 25% has no meaning
     * in dinars until the yuan has been valued.
     */
    public function priceFromMarkup(Deal $deal): ?Money
    {
        if ($this->markup_percent === null) {
            return null;
        }

        /*
         * Converts the raw unit cost rather than reading the stored USD figure.
         *
         * The stored figure is a line total frozen for reporting; deriving a
         * per-unit price back out of it would round twice for no reason. This
         * is a suggestion the operator sees and can overwrite, so it should be
         * as close to the true number as the arithmetic allows.
         */
        $costBase = $deal->toBase(Money::of($this->unit_cost, $this->cost_currency));
        $withMargin = $costBase->times(1 + (float) $this->markup_percent / 100, Money::CALC_SCALE);

        if ($deal->sell_currency === 'USD') {
            return $withMargin->roundTo(Money::SCALE);
        }

        return Money::of(
            $withMargin->times($deal->rateFor($deal->sell_currency))->amount,
            $deal->sell_currency,
        );
    }
}
