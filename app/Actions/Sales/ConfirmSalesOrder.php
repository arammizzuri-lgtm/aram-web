<?php

namespace App\Actions\Sales;

use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockLevel;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Commits a sales order: checks the customer's credit, then reserves the stock.
 *
 * Reserving is what stops two salespeople promising the same boxes to two shops.
 * The credit check runs first because an order that should not exist must not
 * take stock out of circulation while somebody argues about it.
 */
class ConfirmSalesOrder
{
    public function handle(SalesOrder $order, bool $creditApproved = false, ?int $approverId = null): SalesOrder
    {
        $order->loadMissing(['items.product', 'customer']);

        if ($order->status !== 'draft') {
            throw new RuntimeException("Order {$order->number} is already {$order->status}.");
        }

        if ($order->items->isEmpty()) {
            throw new RuntimeException("Order {$order->number} has no lines.");
        }

        if ($order->customer?->is_blocked) {
            throw new RuntimeException("{$order->customer->name} is blocked: {$order->customer->blocked_reason}");
        }

        if ($order->breachesCreditLimit() && ! $creditApproved) {
            $available = $order->customer->availableCredit();

            throw new CreditLimitExceeded(sprintf(
                '%s has $%s of credit left and this order is $%s. A manager must approve it.',
                $order->customer->name,
                number_format(max(0, $available), 2),
                number_format((float) $order->total, 2),
            ));
        }

        return DB::transaction(function () use ($order, $creditApproved, $approverId) {
            $this->assertStockAvailable($order);
            $this->reserve($order);

            $order->forceFill([
                'status' => 'confirmed',
                'is_reserved' => true,
                'reserved_at' => now(),
                'credit_approved_by' => $creditApproved ? $approverId ?? auth()->id() : null,
                'credit_approved_at' => $creditApproved ? now() : null,
            ])->save();

            return $order->fresh();
        });
    }

    /**
     * Availability is on-hand minus what is already reserved.
     *
     * Checked against the live figure inside the transaction rather than the one
     * the salesperson saw when they opened the form.
     */
    private function assertStockAvailable(SalesOrder $order): void
    {
        foreach ($order->items as $item) {
            $product = $item->product;

            if (! $product?->track_stock) {
                continue;
            }

            $available = $product->stockAvailable($order->warehouse_id);

            if ((float) $item->quantity > $available + 0.0001) {
                throw new RuntimeException(sprintf(
                    '%s: %s available, %s ordered.',
                    $product->sku,
                    rtrim(rtrim(number_format($available, 2), '0'), '.'),
                    rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.'),
                ));
            }
        }
    }

    private function reserve(SalesOrder $order): void
    {
        foreach ($order->items as $item) {
            if (! $item->product?->track_stock) {
                continue;
            }

            StockReservation::create([
                'product_id' => $item->product_id,
                'warehouse_id' => $order->warehouse_id,
                'sales_order_item_id' => $item->id,
                'quantity' => $item->quantity,
                'status' => 'active',
                'created_by' => auth()->id(),
            ]);

            $this->syncReservedLevel($item, $order->warehouse_id);
        }
    }

    /** Keep the denormalised level in step with the reservations behind it. */
    private function syncReservedLevel(SalesOrderItem $item, int $warehouseId): void
    {
        $reserved = StockReservation::query()
            ->where('product_id', $item->product_id)
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'active')
            ->sum('quantity');

        StockLevel::query()
            ->where('product_id', $item->product_id)
            ->where('warehouse_id', $warehouseId)
            ->update(['reserved_quantity' => $reserved]);
    }

    /** Release everything an order was holding, e.g. when it is cancelled. */
    public function release(SalesOrder $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                StockReservation::query()
                    ->where('sales_order_item_id', $item->id)
                    ->where('status', 'active')
                    ->update(['status' => 'released']);

                $this->syncReservedLevel($item, $order->warehouse_id);
            }

            $order->forceFill(['is_reserved' => false, 'reserved_at' => null])->save();
        });
    }
}
