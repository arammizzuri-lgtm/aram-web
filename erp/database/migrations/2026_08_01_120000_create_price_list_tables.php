<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * The sections of the Price Lists module — Crystals, Textile, Packaging,
         * Furniture — are rows, not classes. Adding a fifth is a record, and the
         * only code it needs is that section's own catalogue tables.
         */
        Schema::create('price_list_sections', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('icon')->nullable();
            // Null until that section's catalogue tables exist, so the UI can show
            // a section as planned without pretending it is usable.
            $table->string('route_name')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        /*
         * A shared pool of sizes rather than a per-supplier list.
         *
         * Suppliers stock different subsets, and a supplier's available sizes are
         * simply the ones they have priced — so a supplier with an extra size adds
         * a row here and prices it, with no schema change and no duplicate 10mm.
         */
        Schema::create('crystal_sizes', function (Blueprint $table) {
            $table->id();
            $table->decimal('size_mm', 8, 2)->unique();
            $table->string('label')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('crystal_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('crystal_code');
            $table->string('crystal_name');
            // Aurora Borealis coatings and effect finishes price differently from
            // plain colours, and the printed catalogue is grouped by it.
            $table->string('finish')->default('plain');
            $table->string('colour_hex', 7)->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            // Set when a catalogue entry is promoted to something you actually
            // stock, so the price list is not an island cut off from purchasing.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Codes are the supplier's own, so they are only unique within a supplier.
            $table->unique(['supplier_id', 'crystal_code']);
            $table->index(['supplier_id', 'is_active']);
        });

        Schema::create('crystal_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crystal_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crystal_size_id')->constrained()->cascadeOnDelete();
            // Named `price` with an explicit currency rather than `price_rmb`:
            // a column name is a terrible place to hardcode a currency.
            $table->decimal('price', 19, 4);
            $table->char('currency', 3)->default('CNY');
            $table->decimal('moq', 15, 4)->nullable();
            $table->date('effective_date')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One price per supplier / colour / size.
            $table->unique(['supplier_id', 'crystal_product_id', 'crystal_size_id'], 'crystal_prices_unique');
            $table->index(['supplier_id', 'crystal_size_id']);
        });

        // Every price change is kept, so a supplier's drift is chartable and a
        // mistaken bulk edit can be traced back.
        Schema::create('crystal_price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crystal_price_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 19, 4);
            $table->decimal('previous_price', 19, 4)->nullable();
            $table->decimal('change_percent', 8, 2)->nullable();
            $table->char('currency', 3);
            $table->date('effective_date');
            $table->string('source')->default('manual');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['crystal_price_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crystal_price_history');
        Schema::dropIfExists('crystal_prices');
        Schema::dropIfExists('crystal_products');
        Schema::dropIfExists('crystal_sizes');
        Schema::dropIfExists('price_list_sections');
    }
};
