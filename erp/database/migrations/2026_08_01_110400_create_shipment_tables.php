<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freight_forwarders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->char('country', 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('reference')->nullable();
            $table->foreignId('freight_forwarder_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('shipping_method')->default('sea_fcl');
            $table->string('container_number')->nullable();
            $table->string('container_type')->nullable();
            $table->string('bl_number')->nullable();
            $table->string('seal_number')->nullable();
            $table->string('port_of_loading')->nullable();
            $table->string('port_of_discharge')->nullable();
            $table->date('etd')->nullable();
            $table->date('atd')->nullable();
            $table->date('eta')->nullable();
            $table->date('ata')->nullable();
            $table->string('customs_entry_number')->nullable();
            $table->date('customs_cleared_at')->nullable();
            $table->date('delivered_at')->nullable();
            $table->string('status')->default('planning');

            // none → estimated → actual → final. Goods routinely arrive and sell
            // before the clearance agent's real invoice lands, so costing has to
            // be provisional first and reconciled later.
            $table->string('landed_cost_status')->default('none');

            // Denormalised allocation denominators, recomputed from the items.
            $table->decimal('total_weight_kg', 14, 4)->default(0);
            $table->decimal('total_volume_cbm', 14, 6)->default(0);
            $table->decimal('total_goods_base', 19, 4)->default(0);
            $table->decimal('total_costs_base', 19, 4)->default(0);

            $table->string('tracking_url')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'eta']);
            $table->index('landed_cost_status');
            $table->index('container_number');
        });

        // Links PO lines to a container with partial quantities, so one order can
        // span two containers and one container can carry several orders.
        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 19, 4)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 19, 8)->default(1);
            $table->decimal('unit_cost_base', 19, 4)->default(0);
            $table->decimal('goods_value_base', 19, 4)->default(0);

            // Snapshotted from the product at the moment of shipping: correcting a
            // carton size next year must not silently restate this container's costs.
            $table->decimal('unit_weight_kg', 12, 4)->default(0);
            $table->decimal('unit_volume_cbm', 14, 6)->default(0);
            $table->decimal('total_weight_kg', 14, 4)->default(0);
            $table->decimal('total_volume_cbm', 14, 6)->default(0);

            $table->string('hs_code')->nullable();
            $table->decimal('duty_rate', 5, 2)->default(0);
            $table->decimal('customs_value_base', 19, 4)->default(0);
            $table->decimal('received_quantity', 15, 4)->default(0);
            $table->timestamps();

            $table->index('shipment_id');
            $table->index('product_id');
        });

        Schema::create('shipment_cost_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('default_allocation_basis')->default('value');
            $table->boolean('is_customs_duty')->default(false);
            $table->boolean('affects_landed_cost')->default(true);
            // Freight and insurance must be allocated before duty, because duty is
            // charged on the CIF value that those two create.
            $table->unsignedTinyInteger('calculation_pass')->default(3);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shipment_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_cost_type_id')->constrained()->restrictOnDelete();
            $table->string('description')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vendor_name')->nullable();
            $table->decimal('amount', 19, 4)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 19, 8)->default(1);
            $table->decimal('base_amount', 19, 4)->default(0);
            $table->string('allocation_basis')->default('value');
            $table->json('manual_allocations')->nullable();
            $table->boolean('is_estimated')->default(true);
            $table->string('document_reference')->nullable();
            $table->unsignedBigInteger('expense_id')->nullable();
            $table->date('incurred_at')->nullable();
            $table->timestamps();

            $table->index(['shipment_id', 'shipment_cost_type_id']);
        });

        Schema::create('shipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->text('description')->nullable();
            $table->timestamp('occurred_at');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['shipment_id', 'occurred_at']);
        });

        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->foreignId('shipment_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropForeign(['shipment_id']);
            $table->dropColumn('shipment_id');
        });

        Schema::dropIfExists('shipment_events');
        Schema::dropIfExists('shipment_costs');
        Schema::dropIfExists('shipment_cost_types');
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('freight_forwarders');
    }
};
