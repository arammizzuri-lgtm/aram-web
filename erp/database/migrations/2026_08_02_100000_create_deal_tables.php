<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The deal is the centre of this system.
 *
 * One customer request, followed from "they asked" to "they paid". Everything
 * else — purchases, quotations, consignments, invoices — hangs off it.
 *
 * The important design decision is in `deal_lines`: a line carries BOTH what it
 * costs and what it sells for. That is what makes "never type the same thing
 * twice" possible. The supplier purchase document and the customer invoice are
 * both derived from the same set of lines, seen from different sides.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            /*
             * draft      — being put together, customer may not have seen it
             * quoted     — quotation sent, waiting on the customer
             * approved   — customer said yes; the snapshot is frozen
             * purchasing — buying from suppliers
             * shipping   — goods are moving
             * arrived    — landed, ready to hand over
             * delivered  — customer has the goods
             * closed     — delivered and settled
             * cancelled  — did not happen
             */
            $table->string('status')->default('draft')->index();

            $table->date('deal_date');
            $table->date('expected_delivery')->nullable();

            /*
             * Frozen exchange rates.
             *
             * Typed per deal (pre-filled from the last used) and never touched
             * again. Without freezing, changing a rate would rewrite the profit
             * on every historical deal — a March deal that made $400 would show
             * $310 in June with nothing changed but a setting.
             *
             * Null means "not needed": a deal bought and sold in USD needs
             * neither, and the form will not ask.
             */
            $table->decimal('rmb_usd_rate', 19, 6)->nullable();
            $table->decimal('iqd_usd_rate', 19, 6)->nullable();

            // What the customer is billed in. Cost currency lives per line.
            $table->string('sell_currency', 3)->default('IQD');

            /*
             * A lump added to the whole order for your effort or arranging.
             * It belongs to the deal, not to any product in it, which is why
             * per-product profit is reported as approximate when it is set.
             */
            $table->decimal('deal_commission', 19, 4)->default(0);
            $table->string('deal_commission_currency', 3)->nullable();

            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status']);
            $table->index('deal_date');
        });

        /*
         * A purchase is one supplier's side of one deal.
         *
         * You never create these directly — picking a supplier on a deal line
         * creates or attaches one. It exists as its own row so that supplier
         * payments, the supplier's own invoice reference, and any extra
         * purchasing costs have somewhere to live.
         */
        Schema::create('deal_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();

            $table->string('number')->unique();
            $table->string('supplier_reference')->nullable();  // their PI / invoice no.
            $table->string('currency', 3)->default('CNY');

            // draft → ordered → paid_partial → paid → received → cancelled
            $table->string('status')->default('draft')->index();

            /*
             * Bought before the customer approved.
             *
             * Approval is a warning rather than a wall, so this records that you
             * pushed through. The dashboard totals these: goods you are holding
             * that nobody has committed to is the number worth watching.
             */
            $table->boolean('bought_at_risk')->default(false);

            $table->date('ordered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['deal_id', 'supplier_id']);
        });

        /*
         * The line: what the customer wants, what it costs, what they pay.
         *
         * Both sides on one row. The purchase document is these lines grouped by
         * supplier; the customer invoice is these lines with the cost columns
         * removed. Neither requires re-entry.
         */
        Schema::create('deal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_purchase_id')->nullable()->constrained()->nullOnDelete();

            // Where the cost comes from. Null on both = a custom one-off product.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('catalogue_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('crystal_product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('crystal_size_id')->nullable()->constrained()->nullOnDelete();

            // Custom products are described here rather than in the catalogue.
            $table->string('description');
            $table->string('description_ku')->nullable();
            $table->string('description_zh')->nullable();
            $table->text('specification')->nullable();

            $table->decimal('quantity', 19, 4);
            $table->string('unit')->default('pcs');

            /*
             * Frozen USD values are stored as line TOTALS, not per unit.
             *
             * A per-unit figure rounded to four places and then multiplied by
             * the quantity amplifies its own rounding error: ¥12.50 / 7.2 is
             * 1.7361111, stored as 1.7361, and across 500 pieces that drifts a
             * full cent away from the truth. Converting the total once keeps
             * the line exact, and a per-unit figure can always be derived for
             * display.
             */

            // ---- cost side (owner only) ----
            $table->decimal('unit_cost', 19, 4)->default(0);
            $table->string('cost_currency', 3)->default('CNY');
            $table->decimal('cost_total_base', 19, 4)->default(0);   // frozen USD

            // ---- sell side (customer sees this) ----
            $table->decimal('unit_price', 19, 4)->default(0);
            $table->decimal('sell_total_base', 19, 4)->default(0);   // frozen USD

            /*
             * How the selling price was arrived at. All three are used, mixed
             * within a single deal, so this is per line rather than per deal.
             *
             * markup  — unit_price computed from unit_cost + markup_percent
             * manual  — typed directly
             * list    — pulled from the price list for the customer's type
             */
            $table->string('pricing_method')->default('markup');
            $table->decimal('markup_percent', 9, 4)->nullable();

            // Forces the shipping mode: battery goods cannot fly as normal cargo.
            $table->boolean('contains_battery')->default(false);

            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['deal_id', 'display_order']);
            $table->index('deal_purchase_id');
        });

        /*
         * Extra costs on a purchase that are not the goods themselves —
         * supplier's domestic delivery to the collection point, inspection,
         * packing. Kept separate so the goods cost stays comparable to the
         * price list.
         */
        Schema::create('purchase_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_purchase_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('amount', 19, 4);
            $table->string('currency', 3)->default('CNY');
            $table->decimal('base_amount', 19, 4);   // frozen USD
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_costs');
        Schema::dropIfExists('deal_lines');
        Schema::dropIfExists('deal_purchases');
        Schema::dropIfExists('deals');
    }
};
