<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLevel extends Model
{
    protected $fillable = [
        'product_id', 'warehouse_id', 'quantity', 'reserved_quantity',
        'incoming_quantity', 'damaged_quantity', 'average_cost',
        'total_value', 'last_movement_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'reserved_quantity' => 'decimal:4',
            'incoming_quantity' => 'decimal:4',
            'damaged_quantity' => 'decimal:4',
            'average_cost' => 'decimal:4',
            'total_value' => 'decimal:4',
            'last_movement_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** Quantity that is on hand and not already committed to an order. */
    public function getAvailableQuantityAttribute(): float
    {
        return (float) $this->quantity - (float) $this->reserved_quantity;
    }
}
