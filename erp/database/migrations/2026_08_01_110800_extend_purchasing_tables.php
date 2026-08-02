<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->char('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 19, 8)->default(1);
            $table->decimal('base_total', 19, 4)->default(0);
            $table->string('incoterm')->default('FOB');
            // The supplier's proforma invoice number — what they quote back at you.
            $table->string('supplier_reference')->nullable();
            $table->decimal('deposit_percent', 5, 2)->default(30);
            $table->date('deposit_due_date')->nullable();
            $table->date('balance_due_date')->nullable();
            $table->date('expected_ship_date')->nullable();
            $table->string('port_of_loading')->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreignId('supplier_product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_sku')->nullable();
            // Ordered in cartons, stocked in pieces: quantity holds base units and
            // order_quantity holds what was actually typed on the order.
            $table->foreignId('order_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('order_quantity', 15, 4)->default(0);
            $table->decimal('pack_size', 15, 4)->default(1);
            $table->decimal('shipped_quantity', 15, 4)->default(0);
            $table->decimal('unit_weight_kg', 12, 4)->default(0);
            $table->decimal('unit_volume_cbm', 14, 6)->default(0);
            $table->string('hs_code')->nullable();
            $table->decimal('duty_rate', 5, 2)->default(0);
        });

        foreach (['supplier_bills', 'supplier_payments'] as $financeTable) {
            Schema::table($financeTable, function (Blueprint $table) {
                $table->char('currency', 3)->default('USD');
                $table->decimal('exchange_rate', 19, 8)->default(1);
                $table->decimal('base_amount', 19, 4)->default(0);
            });
        }

        Schema::table('supplier_payments', function (Blueprint $table) {
            // Rate on the payment date differs from the rate frozen on the order;
            // that gap is real money and gets its own line.
            $table->decimal('fx_gain_loss', 19, 4)->default(0);
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('supplier_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_bill_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 19, 4);
            $table->decimal('base_amount', 19, 4)->default(0);
            $table->timestamps();

            $table->index('supplier_bill_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_allocations');

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn(['fx_gain_loss', 'bank_account_id']);
        });

        foreach (['supplier_bills', 'supplier_payments'] as $financeTable) {
            Schema::table($financeTable, fn (Blueprint $t) => $t->dropColumn(['currency', 'exchange_rate', 'base_amount']));
        }

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['supplier_product_id']);
            $table->dropForeign(['order_unit_id']);
            $table->dropColumn([
                'supplier_product_id', 'supplier_sku', 'order_unit_id', 'order_quantity',
                'pack_size', 'shipped_quantity', 'unit_weight_kg', 'unit_volume_cbm',
                'hs_code', 'duty_rate',
            ]);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'currency', 'exchange_rate', 'base_total', 'incoterm', 'supplier_reference',
                'deposit_percent', 'deposit_due_date', 'balance_due_date', 'expected_ship_date',
                'port_of_loading', 'payment_terms_days', 'approved_by', 'approved_at', 'closed_at',
            ]);
        });
    }
};
