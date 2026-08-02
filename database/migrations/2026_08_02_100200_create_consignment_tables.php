<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Consignments — the tracking numbers your forwarder gives you.
 *
 * Two things shape this design:
 *
 * 1. A deal can arrive under several tracking numbers (one per supplier, or
 *    shipped in batches), and one tracking number can carry goods for several
 *    deals (consolidated to save cost). So the link is many-to-many.
 *
 * 2. Weight and CBM are recorded not to calculate the bill — the forwarder just
 *    sends one — but to split it honestly when it covers more than one deal.
 *    Sea splits by CBM because that is what sea freight charges for; air splits
 *    by weight for the same reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * Your forwarder's collection points in China.
         *
         * These are addresses you hand to suppliers, not storage you control.
         * Nothing of yours sits here in any sense the system needs to track —
         * there are no quantities, no stock, nothing to reconcile.
         */
        Schema::create('collection_points', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city');
            $table->text('address')->nullable();
            $table->text('address_zh')->nullable();   // what the supplier actually reads
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('consignments', function (Blueprint $table) {
            $table->id();

            // The forwarder's number, e.g. 16940. Theirs, not ours.
            $table->string('tracking_number')->unique();

            // sea | air_no_battery | air_battery
            $table->string('mode')->index();

            $table->foreignId('collection_point_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedInteger('boxes')->nullable();
            $table->decimal('gross_weight_kg', 12, 3)->nullable();
            $table->decimal('cbm', 12, 4)->nullable();

            // awaiting → in_transfer → arrived → delivered
            $table->string('status')->default('awaiting')->index();

            /*
             * Typed from the bill when it arrives. No rate card is stored
             * because the forwarder does not quote one in advance.
             */
            $table->decimal('freight_amount', 19, 4)->nullable();
            $table->string('freight_currency', 3)->default('USD');
            $table->decimal('freight_base', 19, 4)->nullable();
            $table->decimal('exchange_rate', 19, 6)->nullable();

            $table->date('shipped_at')->nullable();
            $table->date('arrived_at')->nullable();
            $table->date('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        /*
         * Which deals a consignment carries, and each one's share of the bill.
         *
         * `freight_share` is only meaningful when more than one deal is
         * attached; with a single deal it is simply the whole freight amount and
         * no split interface is shown at all.
         *
         * `share_is_manual` records whether you accepted the suggestion or typed
         * your own, so a report can tell the difference between a calculated
         * split and a judged one.
         */
        Schema::create('consignment_deal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();

            $table->decimal('freight_share', 19, 4)->default(0);
            $table->decimal('freight_share_base', 19, 4)->default(0);
            $table->boolean('share_is_manual')->default(false);

            $table->timestamps();

            $table->unique(['consignment_id', 'deal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_deal');
        Schema::dropIfExists('consignments');
        Schema::dropIfExists('collection_points');
    }
};
