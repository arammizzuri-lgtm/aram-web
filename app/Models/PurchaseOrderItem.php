<?php

namespace App\Models;

use App\Models\Concerns\CalculatesLineTotal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use CalculatesLineTotal;

    protected $fillable = [
        'purchase_order_id', 'product_id', 'description', 'quantity',
        'received_quantity', 'unit_price', 'discount_rate', 'tax_rate', 'line_total',
        'supplier_product_id', 'supplier_sku', 'order_unit_id', 'order_quantity',
        'pack_size', 'shipped_quantity', 'unit_weight_kg', 'unit_volume_cbm',
        'hs_code', 'duty_rate',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'received_quantity' => 'decimal:4',
            'shipped_quantity' => 'decimal:4',
            // Ordered in cartons; `quantity` is the same line in base units.
            'order_quantity' => 'decimal:4',
            'pack_size' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'discount_rate' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'line_total' => 'decimal:2',
            'unit_weight_kg' => 'decimal:4',
            'unit_volume_cbm' => 'decimal:6',
            'duty_rate' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Keep the two quantity views consistent however the line was created —
        // the form sets both, an import or a script may set only the cartons.
        static::saving(function (self $item) {
            $packSize = (float) ($item->pack_size ?: 1);

            if ((float) $item->order_quantity > 0 && (float) $item->quantity <= 0) {
                $item->quantity = (float) $item->order_quantity * $packSize;
            }

            if ((float) $item->order_quantity <= 0 && (float) $item->quantity > 0) {
                $item->order_quantity = (float) $item->quantity / $packSize;
            }
        });
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Quantity still due from the supplier. */
    public function outstandingQuantity(): float
    {
        return max(0, (float) $this->quantity - (float) $this->received_quantity);
    }
}
