<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A line names its supplier directly.
 *
 * `deal_purchase_id` already exists and groups lines into the internal purchase
 * document for each supplier — but a purchase record cannot exist until there
 * is a supplier to hang it on, and while a deal is still a draft you often know
 * "this comes from Supplier A" before anything has been ordered.
 *
 * So the line records the intent, and the purchase record is derived from it.
 * The alternative — forcing a purchase into existence the moment a supplier is
 * picked — would litter half-built drafts with empty purchase documents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deal_lines', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('deal_purchase_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deal_lines', fn (Blueprint $t) => $t->dropConstrainedForeignId('supplier_id'));
    }
};
