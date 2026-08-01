<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    protected $fillable = [
        'product_id', 'warehouse_id', 'type', 'quantity', 'unit_cost',
        'balance_after', 'reference_type', 'reference_id', 'user_id',
        'notes', 'occurred_at', 'total_cost', 'balance_value_after',
        'average_cost_after', 'shipment_id', 'is_revaluation',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'balance_after' => 'decimal:4',
            'total_cost' => 'decimal:4',
            'balance_value_after' => 'decimal:4',
            'average_cost_after' => 'decimal:4',
            'is_revaluation' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The document that caused this movement, such as a goods receipt. */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
