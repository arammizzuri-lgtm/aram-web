<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One supplier's side of one deal — the internal purchase invoice.
 *
 * Private. Never shown to a customer and never printed on anything they see.
 *
 * You do not create these directly: naming a supplier on a deal line creates or
 * attaches one. It exists as its own record so that payments, the supplier's
 * own invoice reference, and extra purchasing costs have somewhere to live that
 * a line cannot provide.
 */
class DealPurchase extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'draft' => 'Draft',
        'ordered' => 'Ordered',
        'paid_partial' => 'Part paid',
        'paid' => 'Paid',
        'received' => 'Received',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'deal_id', 'supplier_id', 'number', 'supplier_reference', 'currency',
        'status', 'bought_at_risk', 'ordered_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'ordered_at' => 'date',
            'bought_at_risk' => 'boolean',
        ];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DealLine::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(PurchaseCost::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    // ---------------------------------------------------------------- money

    /** The goods, in the supplier's own currency. */
    public function goodsTotal(): Money
    {
        $total = Money::zero($this->currency);

        foreach ($this->lines as $line) {
            $total = $total->plus(Money::of($line->unit_cost, $this->currency)->times($line->quantity));
        }

        return $total;
    }

    public function goodsTotalBase(): Money
    {
        $total = Money::zero('USD');

        foreach ($this->lines as $line) {
            $total = $total->plus($line->costTotalBase());
        }

        return $total;
    }

    /** Inspection, packing, the supplier's delivery to the collection point. */
    public function extraCostsBase(): Money
    {
        return Money::of($this->costs->sum('base_amount'), 'USD');
    }

    public function totalBase(): Money
    {
        return $this->goodsTotalBase()->plus($this->extraCostsBase());
    }

    /**
     * What the supplier has received, in USD.
     *
     * Deliberately `base_amount`, not `actual_cost_base`: what you still owe is
     * settled by what reached them. The exchange house's cut is a cost you bear,
     * not credit against the debt.
     */
    public function paidBase(): Money
    {
        return Money::of($this->payments->sum('base_amount'), 'USD');
    }

    public function outstandingBase(): Money
    {
        return $this->totalBase()->minus($this->paidBase());
    }

    /**
     * What sending the money really cost, above what the supplier received.
     *
     * Recorded because the quoted rate is not the rate you trade at. Left out,
     * every deal reports slightly more profit than you actually made — by an
     * amount too small to look like an error and too consistent to be noise.
     */
    public function transferLossBase(): Money
    {
        $loss = Money::zero('USD');

        foreach ($this->payments as $payment) {
            $loss = $loss->plus($payment->transferLossBase());
        }

        return $loss;
    }

    public function isFullyPaid(): bool
    {
        return ! $this->outstandingBase()->isPositive();
    }
}
