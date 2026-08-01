<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Groups variants of one model (same chandelier in gold and chrome) without
        // a full variant matrix; each sellable variant stays its own product.
        Schema::create('product_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('product_category_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('price_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->decimal('default_discount_percent', 5, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_tier_id')->constrained()->cascadeOnDelete();
            $table->char('currency', 3);
            $table->decimal('price', 19, 4);
            $table->decimal('min_quantity', 15, 4)->default(1);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'price_tier_id', 'min_quantity']);
        });

        // parent_id, slug, description and is_active already exist on this table.
        Schema::table('product_categories', function (Blueprint $table) {
            $table->string('image_path')->nullable();
            // Duty is legally per HS code; the category default saves retyping it
            // on every product in the same tariff line.
            $table->string('default_hs_code')->nullable();
            $table->decimal('default_duty_rate', 5, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
        });

        Schema::table('units', function (Blueprint $table) {
            $table->string('symbol')->nullable();
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('type')->default('main');
            $table->string('city')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', fn (Blueprint $t) => $t->dropColumn(['type', 'city']));
        Schema::table('units', fn (Blueprint $t) => $t->dropColumn('symbol'));

        Schema::table('product_categories', fn (Blueprint $t) => $t->dropColumn([
            'image_path', 'default_hs_code', 'default_duty_rate', 'sort_order',
        ]));

        Schema::dropIfExists('product_prices');
        Schema::dropIfExists('price_tiers');
        Schema::dropIfExists('product_groups');
        Schema::dropIfExists('brands');
    }
};
