<?php

namespace App\Actions\Inventory;

use App\Enums\ShipmentStatus;
use App\Enums\StockMovementType;
use App\Models\LandedCostLine;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\ShipmentItem;
use App\Models\StockLevel;
use App\Services\Inventory\StockLedger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Books an arrived container into stock at its landed cost.
 *
 * This is the join between the costing chain and the inventory chain: goods go
 * in valued at what they truly cost, not at what the supplier invoiced, so every
 * margin computed downstream is honest from the first sale.
 */
class ReceiveShipment
{
    public function __construct(private readonly StockLedger $ledger) {}

    /**
     * @param  array<int, float>  $quantities  shipment_item_id => quantity received
     *                                         (defaults to the full expected quantity)
     */
    public function handle(Shipment $shipment, array $quantities = [], ?string $notes = null): Shipment
    {
        if (! $shipment->status->canReceiveGoods()) {
            throw new RuntimeException(
                "Shipment {$shipment->number} is {$shipment->status->getLabel()} — it must be cleared before goods can be received."
            );
        }

        $run = $shipment->currentRun();

        if ($run === null) {
            throw new RuntimeException(
                "Shipment {$shipment->number} has no applied landed cost run. Cost it before receiving, "
                .'or the stock goes in at the wrong value.'
            );
        }

        // Landed unit cost per item, from the run that is currently in force.
        $unitCosts = $run->lines()->get()
            ->mapWithKeys(fn (LandedCostLine $line) => [
                $line->shipment_item_id => (float) $line->landed_unit_cost,
            ]);

        return DB::transaction(function () use ($shipment, $quantities, $notes, $unitCosts) {
            foreach ($shipment->items()->with('product')->get() as $item) {
                $outstanding = (float) $item->quantity - (float) $item->received_quantity;
                $quantity = $quantities[$item->id] ?? $outstanding;

                if ($quantity <= 0) {
                    continue;
                }

                if ($quantity > $outstanding + 0.0001) {
                    throw new RuntimeException(
                        "Cannot receive {$quantity} of {$item->product->sku}: only {$outstanding} outstanding."
                    );
                }

                $this->ledger->receive(
                    product: $item->product,
                    warehouseId: $shipment->warehouse_id,
                    quantity: $quantity,
                    unitCost: $unitCosts[$item->id] ?? (float) $item->unit_cost_base,
                    type: StockMovementType::PurchaseReceipt,
                    reference: $shipment,
                    shipmentId: $shipment->id,
                    notes: $notes,
                );

                $item->forceFill([
                    'received_quantity' => round((float) $item->received_quantity + $quantity, 4),
                ])->saveQuietly();

                $this->releaseIncoming($item, $shipment->warehouse_id, $quantity);
            }

            $shipment->update([
                'status' => $this->isFullyReceived($shipment)
                    ? ShipmentStatus::Delivered
                    : ShipmentStatus::Cleared,
                'delivered_at' => $this->isFullyReceived($shipment) ? now()->toDateString() : null,
            ]);

            ShipmentEvent::create([
                'shipment_id' => $shipment->id,
                'event' => 'goods_received',
                'description' => $notes ?? 'Goods received into stock at landed cost',
                'occurred_at' => now(),
                'user_id' => auth()->id(),
            ]);

            return $shipment->fresh();
        });
    }

    /** Stock has arrived, so it is no longer "on the water". */
    private function releaseIncoming(ShipmentItem $item, int $warehouseId, float $quantity): void
    {
        $level = StockLevel::query()
            ->where('product_id', $item->product_id)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if ($level === null) {
            return;
        }

        $level->forceFill([
            'incoming_quantity' => max(0, round((float) $level->incoming_quantity - $quantity, 4)),
        ])->save();
    }

    private function isFullyReceived(Shipment $shipment): bool
    {
        return ! $shipment->items()
            ->whereColumn('received_quantity', '<', 'quantity')
            ->exists();
    }
}
