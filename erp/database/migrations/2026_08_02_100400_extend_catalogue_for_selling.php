<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Price lists now carry both sides.
 *
 * Cost stays where it is — per supplier, because the same 20mm crystal costs
 * differently from Supplier A than Supplier B. Selling price is separate and
 * belongs to the product, because your customer does not care where you sourced
 * it: a cheaper supplier means more profit, not a cheaper price.
 *
 * Selling prices vary by customer type instead, which is why they live in their
 * own table keyed on the type rather than as a column on the product.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * Wholesale, regular, and whatever else you decide. Named by you, not
         * fixed in code — adding a type is a row, not a change to the system.
         */
        Schema::create('customer_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();

            /*
             * A fallback markup for this type, used when a product has no
             * explicit selling price. Saves setting a price on every product
             * before the type is usable.
             */
            $table->decimal('default_markup_percent', 9, 4)->nullable();

            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('customer_type_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();

            // Which language their documents print in. Set once, never asked again.
            // name_ku already exists from the partner tables migration.
            $table->string('document_language', 5)->default('en')->after('name');
        });

        /*
         * Selling price for a catalogue item, per customer type.
         *
         * Deliberately mirrors the shape of catalogue_item_prices (which is the
         * cost side) so the two read the same way, but there is no supplier here
         * — that is the whole point of the split.
         */
        Schema::create('catalogue_item_sell_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogue_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_type_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('price', 19, 4);
            $table->string('currency', 3)->default('USD');
            $table->date('effective_date')->nullable();
            $table->timestamps();

            $table->unique(['catalogue_item_id', 'customer_type_id'], 'cat_sell_item_type_unique');
        });

        /*
         * The crystal equivalent. Crystal pricing is a colour x size matrix, so
         * a selling price needs both, exactly as the cost side does.
         */
        Schema::create('crystal_sell_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crystal_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crystal_size_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_type_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('price', 19, 4);
            $table->string('currency', 3)->default('USD');
            $table->date('effective_date')->nullable();
            $table->timestamps();

            $table->unique(
                ['crystal_product_id', 'crystal_size_id', 'customer_type_id'],
                'crystal_sell_unique'
            );
        });

        /*
         * The same for general products — the catalogue entries that are not
         * crystals and not part of a price-list section.
         *
         * `min_quantity` is kept from the old product_prices table because a
         * quantity break on a selling price is genuinely useful: 500 pieces
         * should be able to sell cheaper per piece than 50.
         */
        Schema::create('product_sell_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_type_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('price', 19, 4);
            $table->string('currency', 3)->default('USD');
            $table->decimal('min_quantity', 19, 4)->default(1);
            $table->date('effective_date')->nullable();
            $table->timestamps();

            $table->unique(
                ['product_id', 'customer_type_id', 'min_quantity'],
                'product_sell_unique'
            );
        });

        /*
         * Anything with a lithium battery cannot travel as normal air cargo.
         * Recording it on the product lets the system warn before a shipping
         * mode is booked, rather than after the forwarder rejects it and a
         * promised delivery date is already gone.
         */
        Schema::table('catalogue_items', function (Blueprint $table) {
            $table->boolean('contains_battery')->default(false)->after('is_active');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('contains_battery')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('contains_battery'));
        Schema::table('catalogue_items', fn (Blueprint $t) => $t->dropColumn('contains_battery'));

        Schema::dropIfExists('crystal_sell_prices');
        Schema::dropIfExists('catalogue_item_sell_prices');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_type_id');
            $table->dropColumn('document_language');
        });

        Schema::dropIfExists('customer_types');
    }
};
