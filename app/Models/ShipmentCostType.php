<?php

namespace App\Models;

use App\Enums\AllocationBasis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShipmentCostType extends Model
{
    protected $fillable = [
        'name', 'code', 'default_allocation_basis', 'is_customs_duty',
        'affects_landed_cost', 'calculation_pass', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_allocation_basis' => AllocationBasis::class,
            'is_customs_duty' => 'boolean',
            'affects_landed_cost' => 'boolean',
            'calculation_pass' => 'integer',
        ];
    }

    public function costs(): HasMany
    {
        return $this->hasMany(ShipmentCost::class);
    }
}
