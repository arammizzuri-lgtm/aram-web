<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LandedCostLine extends Model
{
    protected $fillable = [
        'landed_cost_run_id', 'shipment_item_id', 'product_id', 'quantity',
        'goods_value_base', 'weight_kg', 'volume_cbm', 'cif_value_base',
        'allocated_costs_base', 'total_landed_base', 'landed_unit_cost',
        'previous_unit_cost', 'variance_amount', 'variance_percent', 'cost_uplift_percent',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'goods_value_base' => 'decimal:4',
            'weight_kg' => 'decimal:4',
            'volume_cbm' => 'decimal:6',
            'cif_value_base' => 'decimal:4',
            'allocated_costs_base' => 'decimal:4',
            'total_landed_base' => 'decimal:4',
            'landed_unit_cost' => 'decimal:4',
            'previous_unit_cost' => 'decimal:4',
            'variance_amount' => 'decimal:4',
            'variance_percent' => 'decimal:2',
            'cost_uplift_percent' => 'decimal:2',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(LandedCostRun::class, 'landed_cost_run_id');
    }

    public function shipmentItem(): BelongsTo
    {
        return $this->belongsTo(ShipmentItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(LandedCostAllocation::class);
    }

    /** Per-unit breakdown by cost type, for the hover-to-explain UI. */
    public function unitCostBreakdown(): array
    {
        $quantity = (float) $this->quantity;

        if ($quantity <= 0) {
            return [];
        }

        return $this->allocations
            ->groupBy(fn (LandedCostAllocation $a) => $a->shipmentCost->type->name)
            ->map(fn ($group) => round($group->sum(fn ($a) => (float) $a->amount_base) / $quantity, 4))
            ->all();
    }
}
