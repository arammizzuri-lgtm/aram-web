<?php

namespace App\Models;

use App\Enums\AllocationBasis;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShipmentCost extends Model
{
    protected $fillable = [
        'shipment_id', 'shipment_cost_type_id', 'description', 'supplier_id', 'vendor_name',
        'amount', 'currency', 'exchange_rate', 'base_amount', 'allocation_basis',
        'manual_allocations', 'is_estimated', 'document_reference', 'expense_id', 'incurred_at',
    ];

    protected function casts(): array
    {
        return [
            'allocation_basis' => AllocationBasis::class,
            'amount' => 'decimal:4',
            'exchange_rate' => 'decimal:8',
            'base_amount' => 'decimal:4',
            'manual_allocations' => 'array',
            'is_estimated' => 'boolean',
            'incurred_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $cost) {
            $cost->base_amount = (float) $cost->amount * (float) $cost->exchange_rate;
        });

        static::saved(fn (self $cost) => $cost->shipment?->refreshTotals());
        static::deleted(fn (self $cost) => $cost->shipment?->refreshTotals());
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ShipmentCostType::class, 'shipment_cost_type_id');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(LandedCostAllocation::class);
    }

    public function baseAmount(): Money
    {
        return Money::of($this->base_amount, Currency::base());
    }

    public function affectsLandedCost(): bool
    {
        return $this->allocation_basis !== AllocationBasis::None;
    }
}
