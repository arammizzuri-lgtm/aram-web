<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A deal's share of one consignment's freight bill.
 *
 * Exists as a real pivot model so the money on it behaves like money
 * everywhere else in the system. Without it, `$pivot->freight_share` comes back
 * as a bare number while `$line->unit_cost` comes back as a fixed-point string,
 * and comparisons between the two quietly stop meaning what they look like.
 */
class ConsignmentDeal extends Pivot
{
    protected $table = 'consignment_deal';

    public $incrementing = true;

    protected $fillable = [
        'consignment_id', 'deal_id',
        'freight_share', 'freight_share_base', 'share_is_manual',
    ];

    protected function casts(): array
    {
        return [
            'freight_share' => 'decimal:4',
            'freight_share_base' => 'decimal:4',
            'share_is_manual' => 'boolean',
        ];
    }

    public function shareBase(): Money
    {
        return Money::of($this->freight_share_base, 'USD');
    }
}
