<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What you sell a catalogue item for, per customer type.
 *
 * The mirror of CatalogueItemPrice, which is the cost side. They are deliberately
 * two tables: cost belongs to a supplier and selling price belongs to a customer
 * type, and the whole point of the split is that a cheaper supplier means more
 * profit rather than a cheaper price.
 *
 * A null customer type is the price everyone else gets.
 */
class CatalogueItemSellPrice extends Model
{
    protected $fillable = [
        'catalogue_item_id', 'customer_type_id', 'price', 'currency', 'effective_date',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'effective_date' => 'date',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(CatalogueItem::class, 'catalogue_item_id');
    }

    public function customerType(): BelongsTo
    {
        return $this->belongsTo(CustomerType::class);
    }
}
