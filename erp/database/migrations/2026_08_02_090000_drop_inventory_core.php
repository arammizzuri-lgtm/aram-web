<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Remove the inventory core.
 *
 * The previous system was built around a warehouse: goods arrive, sit, get
 * costed, get sold from stock. Every table below existed to answer "what is
 * sitting in my warehouse worth?"
 *
 * This business never holds stock — every purchase is made against a customer
 * request that already exists. That question has no meaning here, so the
 * machinery built to answer it is not simplified, it is deleted. Keeping it
 * would leave screens nobody opens and figures nobody can interpret.
 *
 * Runs before the new deal tables because several names are reused:
 * `quotations` and `supplier_payments` both come back with a different shape.
 */
return new class extends Migration
{
    /**
     * Ordered children-first, though foreign keys are disabled around the drop
     * because several of these reference each other in both directions.
     */
    private const DROP = [
        // Stock: nothing is held, so nothing moves, so nothing needs valuing.
        'stock_adjustment_items', 'stock_adjustments',
        'stock_reservations', 'stock_movements', 'stock_levels', 'warehouses',
        'goods_receipt_items', 'goods_receipts',

        // Landed costing: replaced by per-deal costs plus an occasional
        // two-way freight split. There is no container of mixed stock to
        // spread duty across.
        'landed_cost_allocations', 'landed_cost_lines', 'landed_cost_runs',
        'shipment_events', 'shipment_costs', 'shipment_cost_types',
        'shipment_items', 'shipments', 'freight_forwarders',

        // Sales: replaced by deals and customer_invoices.
        'sales_return_items', 'sales_returns',
        'delivery_note_items', 'delivery_notes',
        'payment_allocations', 'payments',
        'invoice_items', 'invoices',
        'sales_order_items', 'sales_orders',
        'quotation_items', 'quotations',

        // Purchasing: replaced by deal_purchases, which belong to a deal
        // rather than standing alone.
        'supplier_payment_allocations', 'supplier_payments',
        'supplier_bills',
        'purchase_order_items', 'purchase_orders',

        // price_tiers and customer_types were the same idea under two names.
        // Consolidated on customer_types, which is what the owner calls it.
        'product_prices', 'price_tiers',
    ];

    public function up(): void
    {
        /*
         * Release the reference before dropping what it points at.
         *
         * SQLite would tolerate the dangling foreign key with constraints
         * disabled; PostgreSQL refuses to drop a table another still references.
         * Doing it explicitly keeps both databases behaving the same way, which
         * matters because production is PostgreSQL and development is not.
         */
        if (Schema::hasColumn('customers', 'price_tier_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropConstrainedForeignId('price_tier_id');
            });
        }

        Schema::disableForeignKeyConstraints();

        foreach (self::DROP as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Not reversible.
     *
     * Recreating 30 tables from a business model that no longer applies would
     * be pretending this is undoable. It is not — restore from a backup
     * instead, which is the honest recovery path.
     */
    public function down(): void
    {
        throw new RuntimeException(
            'Dropping the inventory core cannot be reversed by migration. Restore from a backup.'
        );
    }
};
