<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One costing of one shipment.
 *
 * Runs are versioned and never overwritten, so "what did we think this container
 * cost in March?" stays answerable after the final reconciliation in June.
 */
class LandedCostRun extends Model
{
    protected $fillable = [
        'shipment_id', 'version', 'status', 'basis_snapshot',
        'total_goods_base', 'total_costs_base', 'total_weight_kg',
        'total_volume_cbm', 'total_quantity', 'is_final',
        'calculated_at', 'applied_at', 'calculated_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'basis_snapshot' => 'array',
            'is_final' => 'boolean',
            'calculated_at' => 'datetime',
            'applied_at' => 'datetime',
            'total_goods_base' => 'decimal:4',
            'total_costs_base' => 'decimal:4',
            'total_weight_kg' => 'decimal:4',
            'total_volume_cbm' => 'decimal:6',
            'total_quantity' => 'decimal:4',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(LandedCostLine::class);
    }

    public function revaluations(): HasMany
    {
        return $this->hasMany(CostRevaluation::class);
    }

    public function calculatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    public function totalLandedBase(): float
    {
        return (float) $this->total_goods_base + (float) $this->total_costs_base;
    }

    public function costUpliftPercent(): float
    {
        $goods = (float) $this->total_goods_base;

        return $goods > 0 ? round((float) $this->total_costs_base / $goods * 100, 2) : 0.0;
    }
}
