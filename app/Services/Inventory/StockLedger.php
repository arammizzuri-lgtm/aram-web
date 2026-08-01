<?php

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The only way stock is ever written.
 *
 * Every change appends a movement and derives the level from it, rather than
 * updating a quantity in place. That is what makes inventory reconstructable:
 * `stock_levels` is a cache of the ledger, and a scheduled check can prove the
 * two agree. Nothing else in the application may touch `stock_levels` directly.
 *
 * Costing is moving weighted average. Each receipt blends its landed cost into
 * the running average; issues leave the average alone and are valued at it.
 */
class StockLedger
{
    /**
     * Receive stock at a known landed unit cost.
     *
     * `$shipmentId` is recorded on the movement so a unit can always be traced
     * back to the container it arrived on — per-container margin without the
     * bookkeeping weight of FIFO layers.
     */
    public function receive(
        Product $product,
        int $warehouseId,
        float $quantity,
        float $unitCost,
        StockMovementType $type = StockMovementType::PurchaseReceipt,
        ?Model $reference = null,
        ?int $shipmentId = null,
        ?string $notes = null,
    ): StockMovement {
        if ($quantity <= 0) {
            throw new RuntimeException('Receipt quantity must be positive.');
        }

        return $this->write($product, $warehouseId, $type, $quantity, $unitCost, $reference, $shipmentId, $notes);
    }

    /**
     * Issue stock out. Valued at the current weighted average, which is the
     * figure that becomes COGS on the invoice.
     */
    public function issue(
        Product $product,
        int $warehouseId,
        float $quantity,
        StockMovementType $type = StockMovementType::SalesDelivery,
        ?Model $reference = null,
        ?string $notes = null,
    ): StockMovement {
        if ($quantity <= 0) {
            throw new RuntimeException('Issue quantity must be positive.');
        }

        $level = $this->level($product->id, $warehouseId);

        return $this->write(
            $product, $warehouseId, $type, -$quantity, (float) $level->average_cost, $reference, null, $notes
        );
    }

    /**
     * Restate the value of stock on hand without moving any of it.
     *
     * Raised when a container is finalised and the real costs differ from the
     * estimate the stock was received at.
     */
    public function revalue(
        Product $product,
        int $warehouseId,
        float $newUnitCost,
        ?Model $reference = null,
        ?string $notes = null,
    ): ?StockMovement {
        $level = $this->level($product->id, $warehouseId);
        $onHand = (float) $level->quantity;

        if ($onHand <= 0) {
            return null;
        }

        return DB::transaction(function () use ($product, $warehouseId, $newUnitCost, $reference, $notes, $level, $onHand) {
            $delta = round(($newUnitCost - (float) $level->average_cost) * $onHand, 4);

            $level->forceFill([
                'average_cost' => round($newUnitCost, 4),
                'total_value' => round($onHand * $newUnitCost, 4),
                'last_movement_at' => now(),
            ])->save();

            $product->forceFill([
                'average_cost' => round($newUnitCost, 4),
                'last_landed_cost' => round($newUnitCost, 4),
            ])->saveQuietly();

            return StockMovement::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'type' => StockMovementType::Revaluation,
                'quantity' => 0,
                'unit_cost' => round($newUnitCost, 4),
                'total_cost' => $delta,
                'balance_after' => $onHand,
                'balance_value_after' => round($onHand * $newUnitCost, 4),
                'average_cost_after' => round($newUnitCost, 4),
                'reference_type' => $reference ? $reference::class : null,
                'reference_id' => $reference?->getKey(),
                'user_id' => auth()->id(),
                'is_revaluation' => true,
                'notes' => $notes,
                'occurred_at' => now(),
            ]);
        });
    }

    /** Append the movement and recompute the level it implies. */
    private function write(
        Product $product,
        int $warehouseId,
        StockMovementType $type,
        float $signedQuantity,
        float $unitCost,
        ?Model $reference,
        ?int $shipmentId,
        ?string $notes,
    ): StockMovement {
        return DB::transaction(function () use ($product, $warehouseId, $type, $signedQuantity, $unitCost, $reference, $shipmentId, $notes) {
            // Locked so two concurrent receipts cannot both read the same
            // opening balance and average each other away.
            $level = $this->level($product->id, $warehouseId, lock: true);

            $openingQuantity = (float) $level->quantity;
            $openingAverage = (float) $level->average_cost;
            $closingQuantity = round($openingQuantity + $signedQuantity, 4);

            $closingAverage = $type->affectsAverageCost() && $signedQuantity > 0
                ? $this->blendAverage($openingQuantity, $openingAverage, $signedQuantity, $unitCost)
                : $openingAverage;

            $level->forceFill([
                'quantity' => $closingQuantity,
                'average_cost' => $closingAverage,
                'total_value' => round($closingQuantity * $closingAverage, 4),
                'last_movement_at' => now(),
            ])->save();

            // The product-level figure is what pricing and margin screens read.
            if ($type->affectsAverageCost() && $signedQuantity > 0) {
                $product->forceFill([
                    'average_cost' => $this->weightedAverageAcrossWarehouses($product),
                    'last_landed_cost' => round($unitCost, 4),
                ])->saveQuietly();
            }

            return StockMovement::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'type' => $type,
                'quantity' => round($signedQuantity, 4),
                'unit_cost' => round($unitCost, 4),
                'total_cost' => round(abs($signedQuantity) * $unitCost, 4),
                'balance_after' => $closingQuantity,
                'balance_value_after' => round($closingQuantity * $closingAverage, 4),
                'average_cost_after' => $closingAverage,
                'reference_type' => $reference ? $reference::class : null,
                'reference_id' => $reference?->getKey(),
                'shipment_id' => $shipmentId,
                'user_id' => auth()->id(),
                'notes' => $notes,
                'occurred_at' => now(),
            ]);
        });
    }

    /**
     * Moving weighted average.
     *
     * When the opening balance is zero or negative the incoming cost simply
     * becomes the new average — averaging against a negative balance would
     * produce a nonsensical figure.
     */
    private function blendAverage(float $openingQuantity, float $openingAverage, float $incomingQuantity, float $incomingCost): float
    {
        if ($openingQuantity <= 0) {
            return round($incomingCost, 4);
        }

        $totalValue = ($openingQuantity * $openingAverage) + ($incomingQuantity * $incomingCost);
        $totalQuantity = $openingQuantity + $incomingQuantity;

        return $totalQuantity > 0 ? round($totalValue / $totalQuantity, 4) : round($incomingCost, 4);
    }

    private function weightedAverageAcrossWarehouses(Product $product): float
    {
        $levels = StockLevel::query()->where('product_id', $product->id)->get();

        $quantity = (float) $levels->sum(fn (StockLevel $l) => (float) $l->quantity);
        $value = (float) $levels->sum(fn (StockLevel $l) => (float) $l->quantity * (float) $l->average_cost);

        return $quantity > 0 ? round($value / $quantity, 4) : (float) $product->average_cost;
    }

    private function level(int $productId, int $warehouseId, bool $lock = false): StockLevel
    {
        $level = StockLevel::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();

        return $level ?? StockLevel::create([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity' => 0,
            'average_cost' => 0,
        ]);
    }

    /**
     * Prove `stock_levels` still agrees with the ledger it is derived from.
     *
     * @return array<int, array{product_id: int, warehouse_id: int, level: float, ledger: float}>
     */
    public function reconcile(): array
    {
        $discrepancies = [];

        foreach (StockLevel::query()->cursor() as $level) {
            $ledger = (float) StockMovement::query()
                ->where('product_id', $level->product_id)
                ->where('warehouse_id', $level->warehouse_id)
                ->sum('quantity');

            if (abs($ledger - (float) $level->quantity) > 0.0001) {
                $discrepancies[] = [
                    'product_id' => $level->product_id,
                    'warehouse_id' => $level->warehouse_id,
                    'level' => (float) $level->quantity,
                    'ledger' => $ledger,
                ];
            }
        }

        return $discrepancies;
    }
}
