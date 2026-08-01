<?php

namespace App\Services\Costing;

use App\Enums\AllocationBasis;
use App\Services\Costing\Allocators\ManualAllocator;
use App\Services\Costing\Allocators\QuantityAllocator;
use App\Services\Costing\Allocators\ValueAllocator;
use App\Services\Costing\Allocators\VolumeAllocator;
use App\Services\Costing\Allocators\WeightAllocator;
use InvalidArgumentException;

/** Resolves the strategy for an allocation basis. Adding a basis adds a class here. */
class CostAllocatorFactory
{
    public function for(AllocationBasis $basis): CostAllocator
    {
        return match ($basis) {
            AllocationBasis::Value => new ValueAllocator,
            AllocationBasis::Weight => new WeightAllocator,
            AllocationBasis::Volume => new VolumeAllocator,
            AllocationBasis::Quantity => new QuantityAllocator,
            AllocationBasis::Manual => new ManualAllocator,
            // Duty is calculated per line inside the engine, never allocated.
            AllocationBasis::PerLineHs, AllocationBasis::None => throw new InvalidArgumentException(
                "{$basis->value} is not distributed by an allocator."
            ),
        };
    }
}
