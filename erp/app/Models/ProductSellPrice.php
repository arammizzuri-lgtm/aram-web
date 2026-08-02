<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What you sell a product for, per customer type.
 *
 * Separate from cost, which lives per supplier: your customer does not care
 * where you sourced it, so a cheaper supplier means more profit rather than a
 * cheaper price. A null customer type is the price everyone else gets.
 */
class ProductSellPrice extends Model
{
    protected $fillable = [
        'product_id', 'customer_type_id', 'price', 'currency',
        'min_quantity', 'effective_date',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'min_quantity' => 'decimal:4',
            'effective_date' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customerType(): BelongsTo
    {
        return $this->belongsTo(CustomerType::class);
    }
}
