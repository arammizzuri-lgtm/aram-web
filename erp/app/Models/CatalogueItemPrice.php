<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One quantity break for one catalogue item.
 *
 * An item with rows at 1 / 500 / 5000 is priced three ways; the largest
 * applicable break wins at order time.
 */
class CatalogueItemPrice extends Model
{
    protected $fillable = [
        'catalogue_item_id', 'supplier_id', 'min_quantity',
        'price', 'currency', 'effective_date', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'min_quantity' => 'decimal:4',
            'price' => 'decimal:4',
            'effective_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // The denormalised supplier must always agree with the item's.
        static::saving(function (self $price) {
            $price->supplier_id ??= $price->item?->supplier_id;
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(CatalogueItem::class, 'catalogue_item_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function money(): Money
    {
        return Money::of($this->price, $this->currency);
    }
}
