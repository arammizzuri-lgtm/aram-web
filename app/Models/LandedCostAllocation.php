<?php

namespace App\Models;

use App\Enums\AllocationBasis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandedCostAllocation extends Model
{
    protected $fillable = [
        'landed_cost_line_id', 'shipment_cost_id', 'basis_used',
        'basis_value', 'share_percent', 'amount_base',
    ];

    protected function casts(): array
    {
        return [
            'basis_used' => AllocationBasis::class,
            'basis_value' => 'decimal:6',
            'share_percent' => 'decimal:6',
            'amount_base' => 'decimal:4',
        ];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(LandedCostLine::class, 'landed_cost_line_id');
    }

    public function shipmentCost(): BelongsTo
    {
        return $this->belongsTo(ShipmentCost::class);
    }

    /** Plain-language maths for the tooltip, e.g. "$3,200.00 × 32.00 / 58.00 CBM". */
    public function explanation(): string
    {
        $cost = $this->shipmentCost;

        return match ($this->basis_used) {
            AllocationBasis::PerLineHs => sprintf(
                'CIF %s × %s%% duty', number_format((float) $this->basis_value, 2), number_format((float) $this->share_percent, 2)
            ),
            AllocationBasis::Manual => 'Entered manually',
            default => sprintf(
                '%s × %s%% (%s)',
                number_format((float) $cost->base_amount, 2),
                number_format((float) $this->share_percent, 4),
                $this->basis_used->getLabel(),
            ),
        };
    }
}
