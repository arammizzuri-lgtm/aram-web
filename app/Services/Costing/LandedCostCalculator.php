<?php

namespace App\Services\Costing;

use App\Enums\AllocationBasis;
use App\Models\Currency;
use App\Models\LandedCostAllocation;
use App\Models\LandedCostLine;
use App\Models\LandedCostRun;
use App\Models\Shipment;
use App\Models\ShipmentCost;
use App\Models\ShipmentItem;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Works out what every item in a container actually cost, landed in the warehouse.
 *
 * The costs are allocated in dependency order, because customs duty is charged on
 * the CIF value — goods plus freight plus insurance — so those two have to be
 * spread before duty can be worked out at all:
 *
 *   Pass 1  freight, insurance      → establishes each line's CIF value
 *   Pass 2  duty                    → per line, CIF × that line's own HS rate
 *   Pass 3  value-based fees        → clearance, bank charges
 *   Pass 4  post-arrival costs      → port, inland transport, demurrage
 *
 * Each pass hands the actual division to a CostAllocator strategy, so adding a
 * new basis means adding a class, not editing this one.
 *
 * @see docs/04-LANDED-COST.md for the worked example these numbers are tested against
 */
class LandedCostCalculator
{
    public function __construct(private readonly CostAllocatorFactory $allocators) {}

    /**
     * Produce a new costing run for a shipment.
     *
     * Always creates a new version rather than editing the last one: the history
     * of what a container was believed to cost is itself worth keeping.
     */
    public function calculate(Shipment $shipment, bool $final = false, ?int $userId = null): LandedCostRun
    {
        $items = $shipment->items()->with('product')->get();

        if ($items->isEmpty()) {
            throw new RuntimeException("Shipment {$shipment->number} has no items to cost.");
        }

        $costs = $shipment->costs()
            ->with('type')
            ->get()
            ->filter(fn (ShipmentCost $cost) => $cost->affectsLandedCost());

        if ($final && $shipment->hasEstimatedCosts()) {
            throw new RuntimeException(
                "Shipment {$shipment->number} still has estimated costs. Replace them with actuals before finalising."
            );
        }

        return DB::transaction(function () use ($shipment, $items, $costs, $final, $userId) {
            $run = $this->openRun($shipment, $items, $costs, $final, $userId);
            $lines = $this->openLines($run, $items);

            // Pass 1 — freight and insurance, which together create the CIF value.
            $this->allocatePass($costs, $lines, pass: 1);
            $this->establishCifValues($lines);

            // Pass 2 — duty, per line, on that CIF value.
            $this->allocateDuty($costs, $lines);

            // Passes 3 and 4 — everything else.
            $this->allocatePass($costs, $lines, pass: 3);

            $this->finaliseLines($run, $lines);

            return $run->fresh(['lines.allocations']);
        });
    }

    private function openRun(Shipment $shipment, Collection $items, Collection $costs, bool $final, ?int $userId): LandedCostRun
    {
        $version = (int) $shipment->landedCostRuns()->max('version') + 1;

        // Anything older is history now.
        $shipment->landedCostRuns()->where('status', 'applied')->update(['status' => 'superseded']);

        return LandedCostRun::create([
            'shipment_id' => $shipment->id,
            'version' => $version,
            'status' => 'draft',
            'basis_snapshot' => $costs->map(fn (ShipmentCost $c) => [
                'cost_id' => $c->id,
                'type' => $c->type->code,
                'basis' => $c->allocation_basis->value,
                'base_amount' => (string) $c->base_amount,
                'is_estimated' => $c->is_estimated,
            ])->values()->all(),
            'total_goods_base' => $items->sum(fn (ShipmentItem $i) => (float) $i->goods_value_base),
            'total_costs_base' => $costs->sum(fn (ShipmentCost $c) => (float) $c->base_amount),
            'total_weight_kg' => $items->sum(fn (ShipmentItem $i) => (float) $i->total_weight_kg),
            'total_volume_cbm' => $items->sum(fn (ShipmentItem $i) => (float) $i->total_volume_cbm),
            'total_quantity' => $items->sum(fn (ShipmentItem $i) => (float) $i->quantity),
            'is_final' => $final,
            'calculated_at' => now(),
            'calculated_by' => $userId,
        ]);
    }

    /** @return Collection<int, LandedCostLine> keyed by shipment_item_id */
    private function openLines(LandedCostRun $run, Collection $items): Collection
    {
        return $items->mapWithKeys(fn (ShipmentItem $item) => [
            $item->id => LandedCostLine::create([
                'landed_cost_run_id' => $run->id,
                'shipment_item_id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'goods_value_base' => $item->goods_value_base,
                'weight_kg' => $item->total_weight_kg,
                'volume_cbm' => $item->total_volume_cbm,
                'cif_value_base' => $item->goods_value_base,
                'previous_unit_cost' => $item->product?->average_cost,
            ]),
        ]);
    }

    /**
     * Allocate every cost belonging to a pass.
     *
     * The pass comes from the cost *type*, not from the basis: insurance is
     * value-based like a clearance fee, but it forms part of the CIF value that
     * duty is charged on, so it has to be spread in pass 1 alongside freight.
     *
     * Pass 3 sweeps up everything later, so a newly added cost type cannot
     * silently fall out of the calculation.
     */
    private function allocatePass(Collection $costs, Collection $lines, int $pass): void
    {
        $costs
            ->reject(fn (ShipmentCost $cost) => $cost->allocation_basis === AllocationBasis::PerLineHs)
            ->filter(function (ShipmentCost $cost) use ($pass) {
                $costPass = (int) ($cost->type->calculation_pass ?? 3);

                return $pass === 3 ? $costPass >= 3 : $costPass === $pass;
            })
            ->each(fn (ShipmentCost $cost) => $this->allocateCost($cost, $lines));
    }

    private function allocateCost(ShipmentCost $cost, Collection $lines): void
    {
        $allocator = $this->allocators->for($cost->allocation_basis);
        $shares = $allocator->allocate($cost, $lines);

        foreach ($shares as $lineId => $share) {
            $line = $lines->first(fn (LandedCostLine $l) => $l->id === $lineId);

            LandedCostAllocation::create([
                'landed_cost_line_id' => $lineId,
                'shipment_cost_id' => $cost->id,
                'basis_used' => $cost->allocation_basis,
                'basis_value' => $allocator->basisValueFor($line),
                'share_percent' => $allocator->sharePercentFor($line, $lines),
                'amount_base' => $share->amount,
            ]);
        }
    }

    /** CIF = goods + everything allocated so far (freight and insurance). */
    private function establishCifValues(Collection $lines): void
    {
        foreach ($lines as $line) {
            $allocatedSoFar = (float) LandedCostAllocation::query()
                ->where('landed_cost_line_id', $line->id)
                ->sum('amount_base');

            $line->cif_value_base = (float) $line->goods_value_base + $allocatedSoFar;
            $line->saveQuietly();
        }
    }

    /**
     * Duty is not an allocation.
     *
     * Each line is charged its own HS rate against its own CIF value, so the total
     * duty is whatever those come to — not a lump spread across the container. The
     * recorded cost row is then trued up to that total.
     */
    private function allocateDuty(Collection $costs, Collection $lines): void
    {
        $dutyCosts = $costs->filter(fn (ShipmentCost $c) => $c->allocation_basis === AllocationBasis::PerLineHs);

        foreach ($dutyCosts as $cost) {
            $total = 0.0;

            foreach ($lines as $line) {
                $item = $line->shipmentItem;
                $rate = (float) ($item->duty_rate ?? 0);

                if ($rate <= 0) {
                    continue;
                }

                $duty = Money::of($line->cif_value_base, Currency::base())->times($rate / 100);
                $total += (float) $duty->amount;

                LandedCostAllocation::create([
                    'landed_cost_line_id' => $line->id,
                    'shipment_cost_id' => $cost->id,
                    'basis_used' => AllocationBasis::PerLineHs,
                    'basis_value' => $line->cif_value_base,
                    'share_percent' => $rate,
                    'amount_base' => $duty->amount,
                ]);
            }

            // The duty payable is what the HS rates produce, so the cost record
            // follows the calculation rather than the other way round.
            $cost->forceFill([
                'amount' => $total / max((float) $cost->exchange_rate, 1e-8),
                'base_amount' => $total,
            ])->saveQuietly();
        }
    }

    private function finaliseLines(LandedCostRun $run, Collection $lines): void
    {
        $totalCosts = 0.0;

        foreach ($lines as $line) {
            $allocated = (float) LandedCostAllocation::query()
                ->where('landed_cost_line_id', $line->id)
                ->sum('amount_base');

            $goods = (float) $line->goods_value_base;
            $quantity = (float) $line->quantity;
            $landed = $goods + $allocated;
            $unitCost = $quantity > 0 ? $landed / $quantity : 0.0;
            $previous = (float) ($line->previous_unit_cost ?? 0);

            $line->forceFill([
                'allocated_costs_base' => round($allocated, 4),
                'total_landed_base' => round($landed, 4),
                'landed_unit_cost' => round($unitCost, 4),
                'variance_amount' => round($unitCost - $previous, 4),
                'variance_percent' => $previous > 0 ? round(($unitCost - $previous) / $previous * 100, 2) : 0,
                'cost_uplift_percent' => $goods > 0 ? round($allocated / $goods * 100, 2) : 0,
            ])->saveQuietly();

            $totalCosts += $allocated;
        }

        // Duty was computed rather than given, so the run total is restated here.
        $run->forceFill(['total_costs_base' => round($totalCosts, 4)])->saveQuietly();
        $run->shipment->refreshTotals();
    }
}
