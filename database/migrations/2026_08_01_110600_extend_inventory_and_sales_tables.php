<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // reserved_quantity and average_cost already exist on this table.
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->decimal('incoming_quantity', 15, 4)->default(0);
            $table->decimal('damaged_quantity', 15, 4)->default(0);
            $table->decimal('total_value', 19, 4)->default(0);
            $table->timestamp('last_movement_at')->nullable();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('total_cost', 19, 4)->default(0);
            $table->decimal('balance_value_after', 19, 4)->default(0);
            $table->decimal('average_cost_after', 19, 4)->default(0);
            // Per-container traceability without FIFO's bookkeeping overhead.
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_revaluation')->default(false);
        });

        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->string('status')->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'warehouse_id', 'status']);
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->date('adjustment_date');
            $table->string('reason')->default('correction');
            $table->string('status')->default('draft');
            $table->decimal('total_value_base', 19, 4)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('system_quantity', 15, 4)->default(0);
            $table->decimal('counted_quantity', 15, 4)->default(0);
            $table->decimal('difference', 15, 4)->default(0);
            $table->decimal('unit_cost', 19, 4)->default(0);
            $table->decimal('value', 19, 4)->default(0);
            $table->timestamps();
        });

        // ---- Sales: currency, COGS snapshot, credit control ----

        foreach (['quotations', 'sales_orders', 'invoices'] as $documentTable) {
            Schema::table($documentTable, function (Blueprint $table) {
                $table->char('currency', 3)->default('USD');
                $table->decimal('exchange_rate', 19, 8)->default(1);
                $table->decimal('base_total', 19, 4)->default(0);
                $table->foreignId('price_tier_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('sales_rep_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->boolean('is_reserved')->default(false);
            $table->timestamp('reserved_at')->nullable();
            $table->foreignId('credit_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('credit_approved_at')->nullable();
            $table->text('delivery_address')->nullable();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_type')->default('standard');
            // COGS frozen at posting time. Without this, a later revaluation would
            // silently rewrite the margin on invoices you have already issued.
            $table->decimal('cogs_total_base', 19, 4)->default(0);
            $table->decimal('gross_profit_base', 19, 4)->default(0);
            $table->decimal('margin_percent', 8, 2)->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('related_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('unit_cost_base', 19, 4)->default(0);
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->char('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 19, 8)->default(1);
            $table->decimal('base_amount', 19, 4)->default(0);
            $table->decimal('unallocated_amount', 19, 4)->default(0);
            $table->decimal('fx_gain_loss', 19, 4)->default(0);
            $table->unsignedBigInteger('bank_account_id')->nullable();
        });

        // One payment settling several invoices — the normal case in wholesale.
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 19, 4);
            $table->decimal('base_amount', 19, 4)->default(0);
            $table->timestamp('allocated_at')->nullable();
            $table->foreignId('allocated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('invoice_id');
            $table->index('payment_id');
        });

        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->date('return_date');
            $table->string('reason')->nullable();
            $table->string('status')->default('draft');
            $table->decimal('total', 19, 4)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_price', 19, 4)->default(0);
            $table->decimal('unit_cost_base', 19, 4)->default(0);
            $table->decimal('line_total', 19, 4)->default(0);
            $table->string('condition')->default('good');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
        Schema::dropIfExists('sales_returns');
        Schema::dropIfExists('payment_allocations');

        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn([
            'currency', 'exchange_rate', 'base_amount', 'unallocated_amount', 'fx_gain_loss', 'bank_account_id',
        ]));

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['shipment_id']);
            $table->dropColumn(['unit_cost_base', 'shipment_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['related_invoice_id']);
            $table->dropColumn([
                'invoice_type', 'cogs_total_base', 'gross_profit_base',
                'margin_percent', 'posted_at', 'related_invoice_id',
            ]);
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropForeign(['credit_approved_by']);
            $table->dropColumn([
                'is_reserved', 'reserved_at', 'credit_approved_by', 'credit_approved_at', 'delivery_address',
            ]);
        });

        foreach (['quotations', 'sales_orders', 'invoices'] as $documentTable) {
            Schema::table($documentTable, function (Blueprint $table) {
                $table->dropForeign(['price_tier_id']);
                $table->dropForeign(['sales_rep_id']);
                $table->dropColumn(['currency', 'exchange_rate', 'base_total', 'price_tier_id', 'sales_rep_id']);
            });
        }

        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_reservations');

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['shipment_id']);
            $table->dropColumn(['total_cost', 'balance_value_after', 'average_cost_after', 'shipment_id', 'is_revaluation']);
        });

        Schema::table('stock_levels', fn (Blueprint $t) => $t->dropColumn([
            'incoming_quantity', 'damaged_quantity', 'total_value', 'last_movement_at',
        ]));
    }
};
