<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Products become the tree, and carry their own sizes.
 *
 * The catalogue was flat: a product sat under a category and that was the whole
 * hierarchy. Real stock is nested further than that — Crystal holds Flat
 * Crystal holds P13, and only P13 is a thing anyone buys. Each level is a
 * product here rather than a category, because the depth varies by section and
 * nobody wants to decide in advance how many category levels a section gets.
 *
 * The tree belongs to a supplier from the top down. Two suppliers quoting the
 * same shape of catalogue keep two separate trees, because their codes, names
 * and finishes are their own — the same reasoning crystal_products already
 * carried, applied to the whole structure rather than one table.
 *
 * Sizes are per product and free-form: 10mm means something to a crystal and
 * nothing to a bolt of fabric, so there is no shared pool to keep aligned.
 *
 * A size's cost is nullable on purpose. Products are added without prices and
 * priced later on the Price Lists screen, so "not priced yet" has to be a state
 * the column can hold — a NOT NULL DEFAULT 0 would make unpriced and free the
 * same number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')
                ->constrained('products')->nullOnDelete();

            // The whole branch is one supplier's, not just the priced leaf.
            $table->foreignId('supplier_id')->nullable()->after('parent_id')
                ->constrained()->nullOnDelete();

            $table->foreignId('price_list_section_id')->nullable()->after('supplier_id')
                ->constrained()->nullOnDelete();

            $table->index(['price_list_section_id', 'supplier_id']);
            $table->index('parent_id');
        });

        Schema::create('product_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // "10mm", "150cm wide", "3 seater" — whatever the product is sold by.
            $table->string('label');

            // Null until someone types it on the Price Lists screen.
            $table->decimal('cost_price', 19, 4)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->decimal('moq', 15, 4)->nullable();
            $table->date('effective_date')->nullable();

            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sizes');

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['price_list_section_id', 'supplier_id']);
            $table->dropIndex(['parent_id']);

            $table->dropConstrainedForeignId('parent_id');
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropConstrainedForeignId('price_list_section_id');
        });
    }
};
