<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Money out to a supplier — in instalments or in one go.
 *
 * Two amounts, deliberately. `base_amount` is what the supplier received,
 * valued at the deal's rate. `actual_cost_base` is what leaving your hands
 * really cost, which is rarely the same:
 *
 *     Supplier invoice     ¥50,000
 *     At the quoted 7.20   looks like $6,944
 *     What it cost you              $7,100
 *                                   -------
 *     Never recorded before           $156
 *
 * That gap is real money. Without it every deal reports slightly more profit
 * than you made, by an amount too small to look like an error and too
 * consistent to be noise.
 */
class SupplierPayment extends Model
{
    use SoftDeletes;

    /**
     * A payment counts only while the purchase it paid for does.
     *
     * Deleting a purchase used to leave this row behind, still counting. The
     * supplier's balance is what was ordered minus what was paid — so the
     * ordered half respected the deletion and the paid half did not, and three
     * suppliers with nothing left in the system sat at −$208.96, −$690.30 and
     * −$1,828.36. A negative balance there reads as "they owe you", which was
     * never true. The dashboard's transfer losses had the same hole.
     *
     * A payment that never had a purchase is a different thing — an opening
     * settlement, money sent against nothing in particular — and it keeps
     * counting, because there is no parent for it to have outlived.
     *
     * Not deleted along with the purchase, on purpose: deleting is reversible
     * here, and a purchase restored six weeks later should come back with the
     * money already sent against it. So the row stays and simply stops
     * counting, which is what soft deletion does everywhere else.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('purchaseStillThere', function (Builder $query): void {
            $query->where(function (Builder $query): void {
                $query->whereNull('deal_purchase_id')->orWhereHas('purchase');
            });
        });
    }

    protected $fillable = [
        'supplier_id', 'deal_purchase_id', 'number',
        'amount', 'currency', 'exchange_rate', 'base_amount', 'actual_cost_base',
        'method', 'reference', 'paid_at', 'notes', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'exchange_rate' => 'decimal:6',
            'base_amount' => 'decimal:4',
            'actual_cost_base' => 'decimal:4',
            'paid_at' => 'date',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(DealPurchase::class, 'deal_purchase_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * The exchange house's cut. Zero when you did not record what it cost.
     *
     * Never negative: if the transfer somehow cost less than the quoted rate
     * implied, that is a rate you should have used, not a profit on shipping
     * money. Treating it as a gain would flatter the deal.
     */
    public function transferLossBase(): Money
    {
        if ($this->actual_cost_base === null) {
            return Money::zero('USD');
        }

        $loss = Money::of($this->actual_cost_base, 'USD')
            ->minus(Money::of($this->base_amount, 'USD'));

        return $loss->isPositive() ? $loss : Money::zero('USD');
    }

    /** What this payment truly cost you, falling back to the converted amount. */
    public function trueCostBase(): Money
    {
        return Money::of($this->actual_cost_base ?? $this->base_amount, 'USD');
    }
}
