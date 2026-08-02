<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What you sell one colour in one size for, per customer type.
 *
 * Crystal pricing is a colour × size matrix, so the selling side needs both keys
 * exactly as the cost side does. No supplier here, and that is the point: the
 * customer is charged for a 20mm P07, not for where it was sourced.
 */
class CrystalSellPrice extends Model
{
    protected $fillable = [
        'crystal_product_id', 'crystal_size_id', 'customer_type_id',
        'price', 'currency', 'effective_date',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'effective_date' => 'date',
        ];
    }

    public function crystalProduct(): BelongsTo
    {
        return $this->belongsTo(CrystalProduct::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(CrystalSize::class, 'crystal_size_id');
    }

    public function customerType(): BelongsTo
    {
        return $this->belongsTo(CustomerType::class);
    }
}
