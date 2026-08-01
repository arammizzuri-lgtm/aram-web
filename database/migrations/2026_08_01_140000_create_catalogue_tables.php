<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * Textile, Packaging and Furniture share one table pair; Crystals keeps its own.
     *
     * That split is not laziness — it follows the shape of the data. A crystal
     * price is a *matrix*: colour × size, where every cell is a separate price.
     * Textile, packaging and furniture are flat catalogues where an item has one
     * price with quantity breaks. Giving those three identical-but-separate table
     * pairs would be three copies of the same schema and three copies of every
     * query, with nothing gained.
     *
     * Each section still has its own product structure: the fields an item
     * carries are declared per section in `price_list_sections.attribute_schema`
     * and stored in `catalogue_items.attributes`, so fabric gets composition,
     * width and GSM while packaging gets dimensions and material. Adding a fifth
     * section is a row plus an attribute schema.
     */
    public function up(): void
    {
        Schema::table('price_list_sections', function (Blueprint $table) {
            // Per-section field definitions: [{key, label, type, unit}]
            $table->json('attribute_schema')->nullable();
            $table->string('price_unit')->nullable();
            $table->string('item_label')->default('Item');
        });

        Schema::create('catalogue_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('name_zh')->nullable();
            // Section-specific fields, shaped by the section's attribute_schema.
            $table->json('attributes')->nullable();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('moq', 15, 4)->nullable();
            $table->decimal('pack_size', 15, 4)->default(1);
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            // Set when an entry is promoted to something you actually stock, so
            // the price list is not cut off from purchasing.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Codes belong to the supplier, so they are unique within one.
            $table->unique(['price_list_section_id', 'supplier_id', 'code'], 'catalogue_items_unique');
            $table->index(['price_list_section_id', 'is_active']);
        });

        Schema::create('catalogue_item_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogue_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            // Quantity breaks: 1 / 500 / 5000 rows for the same item.
            $table->decimal('min_quantity', 15, 4)->default(1);
            $table->decimal('price', 19, 4);
            $table->char('currency', 3)->default('USD');
            $table->date('effective_date')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['catalogue_item_id', 'min_quantity'], 'catalogue_prices_unique');
            $table->index(['supplier_id', 'catalogue_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogue_item_prices');
        Schema::dropIfExists('catalogue_items');

        Schema::table('price_list_sections', fn (Blueprint $t) => $t->dropColumn([
            'attribute_schema', 'price_unit', 'item_label',
        ]));
    }
};
