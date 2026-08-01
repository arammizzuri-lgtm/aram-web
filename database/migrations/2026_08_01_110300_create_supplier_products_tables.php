<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * The join that makes importing work.
         *
         * Suppliers quote their own SKUs and their own product names. Matching a
         * price list against your catalogue SKU never works; matching against the
         * supplier's SKU does. It also lets the same product be sourced from two
         * suppliers at different prices, which is how you know who to reorder from.
         */
        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('supplier_sku');
            $table->string('supplier_name')->nullable();
            $table->string('supplier_name_zh')->nullable();
            $table->char('currency', 3)->default('USD');
            $table->decimal('unit_price', 19, 4)->default(0);
            $table->foreignId('price_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('moq', 15, 4)->nullable();
            $table->decimal('pack_size', 15, 4)->default(1);
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->date('last_quoted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'supplier_sku']);
            $table->index(['product_id', 'is_preferred']);
        });

        Schema::create('supplier_product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_product_id')->constrained()->cascadeOnDelete();
            $table->char('currency', 3);
            $table->decimal('unit_price', 19, 4);
            $table->decimal('previous_price', 19, 4)->nullable();
            $table->decimal('change_percent', 8, 2)->nullable();
            $table->date('effective_date');
            $table->string('source')->default('manual');
            $table->unsignedBigInteger('price_list_import_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['supplier_product_id', 'effective_date']);
        });

        Schema::create('import_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sheet_name')->nullable();
            $table->unsignedSmallInteger('header_row')->default(1);
            $table->unsignedSmallInteger('first_data_row')->default(2);
            $table->json('column_map');
            $table->char('currency', 3)->default('USD');
            $table->string('decimal_separator', 1)->default('.');
            $table->string('thousands_separator', 1)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('price_list_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_filename');
            $table->string('stored_path')->nullable();
            $table->string('disk')->default('local');
            $table->string('status')->default('uploaded');
            $table->string('sheet_name')->nullable();
            $table->unsignedSmallInteger('header_row')->default(1);
            $table->json('column_map')->nullable();
            $table->char('currency', 3)->default('USD');
            $table->date('effective_date')->nullable();
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_new')->default(0);
            $table->unsignedInteger('rows_updated')->default(0);
            $table->unsignedInteger('rows_unchanged')->default(0);
            $table->unsignedInteger('rows_error')->default(0);
            $table->decimal('avg_change_percent', 8, 2)->nullable();
            $table->json('error_log')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('price_list_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw')->nullable();
            $table->string('supplier_sku')->nullable();
            $table->string('name')->nullable();
            $table->string('name_zh')->nullable();
            $table->char('currency', 3)->nullable();
            $table->decimal('unit_price', 19, 4)->nullable();
            $table->decimal('moq', 15, 4)->nullable();
            $table->decimal('pack_size', 15, 4)->nullable();
            $table->decimal('volume_cbm', 14, 6)->nullable();
            $table->decimal('weight_kg', 12, 4)->nullable();
            $table->foreignId('matched_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('matched_supplier_product_id')->nullable()->constrained('supplier_products')->nullOnDelete();
            $table->string('match_method')->default('none');
            $table->decimal('match_confidence', 5, 2)->default(0);
            $table->string('action')->default('skip');
            $table->decimal('old_price', 19, 4)->nullable();
            $table->decimal('new_price', 19, 4)->nullable();
            $table->decimal('change_percent', 8, 2)->nullable();
            $table->boolean('is_approved')->default(true);
            $table->json('errors')->nullable();
            $table->timestamps();

            $table->index(['price_list_import_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_list_import_rows');
        Schema::dropIfExists('price_list_imports');
        Schema::dropIfExists('import_profiles');
        Schema::dropIfExists('supplier_product_prices');
        Schema::dropIfExists('supplier_products');
    }
};
