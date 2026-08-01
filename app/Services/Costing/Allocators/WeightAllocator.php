<?php

namespace App\Services\Costing\Allocators;

use App\Models\LandedCostLine;
use App\Services\Costing\CostAllocator;

/** Splits by kilograms — the basis for air freight and courier charges. */
class WeightAllocator extends CostAllocator
{
    public function basisValueFor(LandedCostLine $line): float
    {
        return (float) $line->weight_kg;
    }
}
