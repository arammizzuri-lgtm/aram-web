<?php

namespace App\Models;

use App\Enums\LandedCostStatus;
use App\Enums\ShipmentStatus;
use App\Models\Concerns\HasDocumentNumber;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    use HasDocumentNumber;

    protected $fillable = [
        'number', 'reference', 'freight_forwarder_id', 'warehouse_id', 'shipping_method',
        'container_number', 'container_type', 'bl_number', 'seal_number',
        'port_of_loading', 'port_of_discharge', 'etd', 'atd', 'eta', 'ata',
        'customs_entry_number', 'customs_cleared_at', 'delivered_at',
        'status', 'landed_cost_status', 'tracking_url', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'landed_cost_status' => LandedCostStatus::class,
            'etd' => 'date',
            'atd' => 'date',
            'eta' => 'date',
            'ata' => 'date',
            'customs_cleared_at' => 'date',
            'delivered_at' => 'date',
            'total_weight_kg' => 'decimal:4',
            'total_volume_cbm' => 'decimal:6',
            'total_goods_base' => 'decimal:4',
            'total_costs_base' => 'decimal:4',
        ];
    }

    public static function documentPrefix(): string
    {
        return 'SHP';
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(ShipmentCost::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class);
    }

    public function landedCostRuns(): HasMany
    {
        return $this->hasMany(LandedCostRun::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function freightForwarder(): BelongsTo
    {
        return $this->belongsTo(FreightForwarder::class);
    }

    /** The run currently in force — the newest applied one. */
    public function currentRun(): ?LandedCostRun
    {
        return $this->landedCostRuns()
            ->where('status', 'applied')
            ->orderByDesc('version')
            ->first();
    }

    /** Recompute the allocation denominators from the items. */
    public function refreshTotals(): void
    {
        $items = $this->items()->get();

        $this->forceFill([
            'total_weight_kg' => $items->sum('total_weight_kg'),
            'total_volume_cbm' => $items->sum('total_volume_cbm'),
            'total_goods_base' => $items->sum('goods_value_base'),
            'total_costs_base' => $this->costs()->sum('base_amount'),
        ])->saveQuietly();
    }

    public function goodsValue(): Money
    {
        return Money::of($this->total_goods_base, Currency::base());
    }

    public function costsTotal(): Money
    {
        return Money::of($this->total_costs_base, Currency::base());
    }

    /** How much the shipment costs added on top of the goods, as a percentage. */
    public function costUpliftPercent(): float
    {
        $goods = (float) $this->total_goods_base;

        return $goods > 0 ? round((float) $this->total_costs_base / $goods * 100, 2) : 0.0;
    }

    public function hasEstimatedCosts(): bool
    {
        return $this->costs()->where('is_estimated', true)->exists();
    }

    public function scopeInTransit(Builder $query): Builder
    {
        return $query->whereIn('status', ['booked', 'in_transit', 'arrived', 'customs']);
    }

    /** Shipments still carrying a provisional cost, oldest first. */
    public function scopeAwaitingFinalCosting(Builder $query): Builder
    {
        return $query->whereIn('landed_cost_status', ['estimated', 'actual'])
            ->orderBy('ata');
    }
}
