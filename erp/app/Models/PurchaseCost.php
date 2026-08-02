<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cost on a purchase that is not the goods themselves.
 *
 * Inspection, packing, the supplier's own delivery to the collection point.
 * Kept apart from the line costs so the goods figure stays comparable to the
 * price list — otherwise a one-off inspection fee would look like the crystals
 * getting more expensive.
 */
class PurchaseCost extends Model
{
    protected $fillable = [
        'deal_purchase_id', 'description', 'amount', 'currency', 'base_amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'base_amount' => 'decimal:4',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(DealPurchase::class, 'deal_purchase_id');
    }
}
