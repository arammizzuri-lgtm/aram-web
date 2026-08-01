<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Runs are versioned and never overwritten, so "what did we think this
         * container cost in March?" stays answerable after the final reconciliation.
         */
        Schema::create('landed_cost_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('status')->default('draft');
            $table->json('basis_snapshot')->nullable();
            $table->decimal('total_goods_base', 19, 4)->default(0);
            $table->decimal('total_costs_base', 19, 4)->default(0);
            $table->decimal('total_weight_kg', 14, 4)->default(0);
            $table->decimal('total_volume_cbm', 14, 6)->default(0);
            $table->decimal('total_quantity', 15, 4)->default(0);
            $table->boolean('is_final')->default(false);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['shipment_id', 'version']);
        });

        Schema::create('landed_cost_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landed_cost_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('goods_value_base', 19, 4)->default(0);
            $table->decimal('weight_kg', 14, 4)->default(0);
            $table->decimal('volume_cbm', 14, 6)->default(0);
            $table->decimal('cif_value_base', 19, 4)->default(0);
            $table->decimal('allocated_costs_base', 19, 4)->default(0);
            $table->decimal('total_landed_base', 19, 4)->default(0);

            // The number the whole business runs on.
            $table->decimal('landed_unit_cost', 19, 4)->default(0);

            $table->decimal('previous_unit_cost', 19, 4)->nullable();
            $table->decimal('variance_amount', 19, 4)->default(0);
            $table->decimal('variance_percent', 8, 2)->default(0);
            $table->decimal('cost_uplift_percent', 8, 2)->default(0);
            $table->timestamps();

            $table->index('landed_cost_run_id');
        });

        // Kept per cost so the UI can show "$4.41 freight + $13.54 duty + $3.86 other"
        // rather than an unexplained lump.
        Schema::create('landed_cost_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landed_cost_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_cost_id')->constrained()->cascadeOnDelete();
            $table->string('basis_used');
            $table->decimal('basis_value', 19, 6)->default(0);
            $table->decimal('share_percent', 9, 6)->default(0);
            $table->decimal('amount_base', 19, 4)->default(0);
            $table->timestamps();

            $table->index('landed_cost_line_id');
        });

        /*
         * Raised when a shipment is finalised after stock has already moved.
         * The correction splits: units still on hand adjust inventory value, units
         * already sold adjust COGS. Posted invoices are never edited.
         */
        Schema::create('cost_revaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('landed_cost_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_on_hand', 15, 4)->default(0);
            $table->decimal('quantity_sold', 15, 4)->default(0);
            $table->decimal('old_unit_cost', 19, 4)->default(0);
            $table->decimal('new_unit_cost', 19, 4)->default(0);
            $table->decimal('unit_delta', 19, 4)->default(0);
            $table->decimal('inventory_adjustment_base', 19, 4)->default(0);
            $table->decimal('cogs_adjustment_base', 19, 4)->default(0);
            $table->string('status')->default('pending');
            $table->timestamp('applied_at')->nullable();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_revaluations');
        Schema::dropIfExists('landed_cost_allocations');
        Schema::dropIfExists('landed_cost_lines');
        Schema::dropIfExists('landed_cost_runs');
    }
};
