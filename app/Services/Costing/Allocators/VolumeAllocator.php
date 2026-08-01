<?php

namespace App\Services\Costing\Allocators;

use App\Models\LandedCostLine;
use App\Services\Costing\CostAllocator;

/**
 * Splits by cubic metres.
 *
 * The right basis for sea freight, port charges, inland trucking and demurrage:
 * all of them are paid for space. A container of sofas can be a tenth of the
 * goods value and half the volume, and it is the volume that was actually bought.
 */
class VolumeAllocator extends CostAllocator
{
    public function basisValueFor(LandedCostLine $line): float
    {
        return (float) $line->volume_cbm;
    }
}
