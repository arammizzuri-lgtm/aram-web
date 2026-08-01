<?php

namespace App\Services\Costing;

use App\Models\LandedCostLine;
use App\Models\ShipmentCost;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Spreads one shipment cost across the lines of a container.
 *
 * Implementations differ only in which measure they weight by; the division and
 * the residual handling live in Money::allocate(), so every basis reconciles to
 * the cent by construction.
 */
abstract class CostAllocator
{
    /**
     * @param  Collection<int, LandedCostLine>  $lines
     * @return array<int, Money> keyed by landed_cost_line id
     */
    public function allocate(ShipmentCost $cost, Collection $lines): array
    {
        $ratios = $lines->mapWithKeys(fn (LandedCostLine $line) => [
            $line->id => $this->basisValueFor($line),
        ])->all();

        return $cost->baseAmount()->allocate($ratios);
    }

    /** The measure this allocator weights by, for one line. */
    abstract public function basisValueFor(LandedCostLine $line): float;

    /**
     * This line's share of the total, as a percentage — stored for the UI so a
     * user can see why a line was charged what it was.
     *
     * @param  Collection<int, LandedCostLine>  $lines
     */
    public function sharePercentFor(LandedCostLine $line, Collection $lines): float
    {
        $total = $lines->sum(fn (LandedCostLine $l) => $this->basisValueFor($l));

        return $total > 0 ? round($this->basisValueFor($line) / $total * 100, 6) : 0.0;
    }
}
