<?php

namespace App\Actions\Purchasing;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockLevel;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Commits a purchase order with the supplier.
 *
 * Confirming books the quantities as *incoming* stock, which is what stops the
 * business reordering something already on the water — the gap between placing
 * an order and the container landing runs to two months.
 */
class ConfirmPurchaseOrder
{
    public function handle(PurchaseOrder $order): PurchaseOrder
    {
        $order->loadMissing(['items.product', 'supplier']);

        if ($order->status->isCommitted()) {
            throw new RuntimeException("Order {$order->number} is already {$order->status->getLabel()}.");
        }

        if ($order->items->isEmpty()) {
            throw new RuntimeException("Order {$order->number} has no lines.");
        }

        foreach ($order->items as $item) {
            if ((float) $item->unit_price <= 0) {
                throw new RuntimeException("{$item->product?->sku} has no price. Every line needs one before sending.");
            }
        }

        return DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                $this->addIncoming($item, $order->warehouse_id);
            }

            $order->forceFill([
                'status' => PurchaseOrderStatus::Confirmed,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                // A deposit holds the production slot, so the dates the business
                // has to act on are derived rather than left to be remembered.
                'deposit_due_date' => $order->deposit_due_date ?? today()->addDays(3),
                'balance_due_date' => $order->balance_due_date
                    ?? today()->addDays($order->supplier?->average_lead_time_days ?? 45),
            ])->save();

            return $order->fresh();
        });
    }

    private function addIncoming(PurchaseOrderItem $item, int $warehouseId): void
    {
        $level = StockLevel::firstOrCreate(
            ['product_id' => $item->product_id, 'warehouse_id' => $warehouseId],
            ['quantity' => 0, 'average_cost' => 0],
        );

        $outstanding = (float) $item->quantity - (float) $item->received_quantity;

        $level->forceFill([
            'incoming_quantity' => round((float) $level->incoming_quantity + max(0, $outstanding), 4),
        ])->save();
    }

    /** Cancelling has to take the incoming quantities back off again. */
    public function cancel(PurchaseOrder $order): PurchaseOrder
    {
        $order->loadMissing('items');

        return DB::transaction(function () use ($order) {
            if ($order->status->isCommitted()) {
                foreach ($order->items as $item) {
                    $level = StockLevel::query()
                        ->where('product_id', $item->product_id)
                        ->where('warehouse_id', $order->warehouse_id)
                        ->first();

                    if ($level === null) {
                        continue;
                    }

                    $outstanding = (float) $item->quantity - (float) $item->received_quantity;

                    $level->forceFill([
                        'incoming_quantity' => max(0, round((float) $level->incoming_quantity - max(0, $outstanding), 4)),
                    ])->save();
                }
            }

            $order->forceFill(['status' => PurchaseOrderStatus::Cancelled, 'closed_at' => now()])->save();

            return $order->fresh();
        });
    }
}
