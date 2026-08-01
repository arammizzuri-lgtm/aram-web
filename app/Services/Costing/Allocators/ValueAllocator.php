<?php

namespace App\Services\Costing\Allocators;

use App\Models\LandedCostLine;
use App\Services\Costing\CostAllocator;

/**
 * Splits in proportion to goods value.
 *
 * Correct for insurance (the premium is a percentage of declared value), clearance
 * fees and bank charges. Wrong for anything paid for by space or weight — which is
 * why it is not the default for freight.
 */
class ValueAllocator extends CostAllocator
{
    public function basisValueFor(LandedCostLine $line): float
    {
        return (float) $line->goods_value_base;
    }
}
