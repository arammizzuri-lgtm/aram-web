<?php

namespace App\Models;

use App\Models\Concerns\CalculatesLineTotal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrderItem extends Model
{
    use CalculatesLineTotal;

    protected $fillable = [
        'sales_order_id', 'product_id', 'description', 'quantity',
        'delivered_quantity', 'invoiced_quantity', 'unit_price',
        'discount_rate', 'tax_rate', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'delivered_quantity' => 'decimal:4',
            'invoiced_quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'discount_rate' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }
}
