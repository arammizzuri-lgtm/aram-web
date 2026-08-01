<?php

namespace App\Services\Costing\Allocators;

use App\Models\LandedCostLine;
use App\Services\Costing\CostAllocator;

/** Splits evenly per unit — per-item handling, labelling or inspection charges. */
class QuantityAllocator extends CostAllocator
{
    public function basisValueFor(LandedCostLine $line): float
    {
        return (float) $line->quantity;
    }
}
