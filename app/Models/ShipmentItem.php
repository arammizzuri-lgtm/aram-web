<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentItem extends Model
{
    protected $fillable = [
        'shipment_id', 'purchase_order_item_id', 'product_id', 'quantity',
        'unit_cost', 'currency', 'exchange_rate', 'unit_cost_base', 'goods_value_base',
        'unit_weight_kg', 'unit_volume_cbm', 'total_weight_kg', 'total_volume_cbm',
        'hs_code', 'duty_rate', 'customs_value_base', 'received_quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'exchange_rate' => 'decimal:8',
            'unit_cost_base' => 'decimal:4',
            'goods_value_base' => 'decimal:4',
            'unit_weight_kg' => 'decimal:4',
            'unit_volume_cbm' => 'decimal:6',
            'total_weight_kg' => 'decimal:4',
            'total_volume_cbm' => 'decimal:6',
            'duty_rate' => 'decimal:2',
            'customs_value_base' => 'decimal:4',
            'received_quantity' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            $item->unit_cost_base = (float) $item->unit_cost * (float) $item->exchange_rate;
            $item->goods_value_base = (float) $item->quantity * (float) $item->unit_cost_base;
            $item->total_weight_kg = (float) $item->quantity * (float) $item->unit_weight_kg;
            $item->total_volume_cbm = (float) $item->quantity * (float) $item->unit_volume_cbm;

            // Customs usually accepts the invoice value; it stays overridable
            // because declared value and commercial value are not always equal.
            if (blank($item->customs_value_base) || (float) $item->customs_value_base === 0.0) {
                $item->customs_value_base = $item->goods_value_base;
            }
        });
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    /**
     * Copy weight, volume, HS code and duty rate off the product.
     *
     * Snapshotted rather than joined: correcting a carton size next year must not
     * silently restate what this container cost.
     */
    public function snapshotFromProduct(Product $product): self
    {
        $this->unit_weight_kg = $product->weight_kg;
        $this->unit_volume_cbm = $product->volume_cbm;
        $this->hs_code = $product->hs_code;
        $this->duty_rate = $product->duty_rate ?? $product->category?->default_duty_rate ?? 0;

        return $this;
    }
}
