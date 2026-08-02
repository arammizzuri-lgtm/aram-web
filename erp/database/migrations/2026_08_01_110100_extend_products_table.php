<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // name_zh is not decoration: it is what you paste into WeChat when
            // asking a Chinese supplier about this item.
            $table->string('name_ar')->nullable()->after('name');
            $table->string('name_ku')->nullable()->after('name_ar');
            $table->string('name_zh')->nullable()->after('name_ku');
            $table->string('slug')->nullable()->after('sku');

            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('default_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();

            // Free-form per-category attributes (colour, size, material) instead of
            // a rigid variant matrix — see docs/01-BUSINESS-ANALYSIS.md rec. 12.
            $table->json('attributes')->nullable();

            // Bought by the carton, sold by the piece.
            $table->foreignId('purchase_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('pack_size', 15, 4)->default(1);

            // Per base unit. These two drive freight and duty allocation, so a
            // missing CBM is a costing problem, not a cosmetic one.
            $table->decimal('weight_kg', 12, 4)->default(0);
            $table->decimal('volume_cbm', 14, 6)->default(0);
            $table->decimal('carton_length_cm', 10, 2)->nullable();
            $table->decimal('carton_width_cm', 10, 2)->nullable();
            $table->decimal('carton_height_cm', 10, 2)->nullable();

            $table->string('hs_code')->nullable();
            $table->decimal('duty_rate', 5, 2)->nullable();
            $table->char('country_of_origin', 2)->default('CN');

            // Moving weighted average landed cost, in base currency.
            $table->decimal('average_cost', 19, 4)->default(0);
            $table->decimal('last_landed_cost', 19, 4)->default(0);

            $table->char('selling_price_currency', 3)->default('USD');
            $table->decimal('min_selling_price', 19, 4)->nullable();
            $table->decimal('target_margin_percent', 5, 2)->nullable();

            $table->decimal('reorder_quantity', 15, 4)->default(0);
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->boolean('is_sellable')->default(true);
            $table->boolean('is_purchasable')->default(true);
            $table->string('status')->default('active');
            $table->text('internal_notes')->nullable();

            $table->index(['status', 'is_sellable']);
            $table->index('brand_id');
            $table->index('default_supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->dropForeign(['product_group_id']);
            $table->dropForeign(['default_supplier_id']);
            $table->dropForeign(['purchase_unit_id']);

            $table->dropColumn([
                'name_ar', 'name_ku', 'name_zh', 'slug', 'brand_id', 'product_group_id',
                'default_supplier_id', 'attributes', 'purchase_unit_id', 'pack_size',
                'weight_kg', 'volume_cbm', 'carton_length_cm', 'carton_width_cm', 'carton_height_cm',
                'hs_code', 'duty_rate', 'country_of_origin', 'average_cost', 'last_landed_cost',
                'selling_price_currency', 'min_selling_price', 'target_margin_percent',
                'reorder_quantity', 'lead_time_days', 'is_sellable', 'is_purchasable',
                'status', 'internal_notes',
            ]);
        });
    }
};
