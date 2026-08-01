<?php

namespace App\Services\Costing\Allocators;

use App\Models\LandedCostLine;
use App\Models\ShipmentCost;
use App\Services\Costing\CostAllocator;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Uses amounts the operator entered per line.
 *
 * For charges that genuinely belong to one product — an inspection of a single
 * item, a sample fee — where any proportional split would be fiction. The stored
 * map is keyed by shipment_item_id, which is what the operator sees on screen.
 */
class ManualAllocator extends CostAllocator
{
    public function allocate(ShipmentCost $cost, Collection $lines): array
    {
        $manual = $cost->manual_allocations ?? [];
        $currency = $cost->baseAmount()->currency;
        $shares = [];
        $assigned = Money::zero($currency);

        foreach ($lines as $line) {
            $amount = Money::of($manual[$line->shipment_item_id] ?? 0, $currency);
            $shares[$line->id] = $amount;
            $assigned = $assigned->plus($amount);
        }

        // Whatever the operator did not place is spread by value, so the cost
        // always reconciles even when the manual map is incomplete.
        $remainder = $cost->baseAmount()->minus($assigned);

        if (! $remainder->isZero()) {
            $byValue = $remainder->allocate(
                $lines->mapWithKeys(fn (LandedCostLine $l) => [$l->id => (float) $l->goods_value_base])->all()
            );

            foreach ($byValue as $lineId => $extra) {
                $shares[$lineId] = $shares[$lineId]->plus($extra);
            }
        }

        return $shares;
    }

    public function basisValueFor(LandedCostLine $line): float
    {
        return (float) $line->goods_value_base;
    }
}
