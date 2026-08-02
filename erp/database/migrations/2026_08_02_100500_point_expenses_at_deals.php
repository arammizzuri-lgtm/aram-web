<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Expenses attach to a deal instead of a shipment.
 *
 * The old model charged costs to a container, then spread them across whatever
 * stock was inside it. There is no container of mixed stock here — a cost is
 * either general overhead (office, phone, rent) or it was incurred for one
 * customer's request, and the second kind belongs on that request.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('expenses', 'shipment_id')) {
            /*
             * The index has to go first and separately.
             *
             * dropConstrainedForeignId removes the foreign key and the column,
             * but a separately declared index on the same column survives both
             * and then refers to a column that no longer exists — which SQLite
             * only complains about at the moment the column is dropped.
             */
            Schema::table('expenses', fn (Blueprint $t) => $t->dropIndex('expenses_shipment_id_index'));
            Schema::table('expenses', fn (Blueprint $t) => $t->dropConstrainedForeignId('shipment_id'));
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
        });

        // The old flag meant "this expense gets spread across a container".
        // Now an expense either names a deal or it does not.
        if (Schema::hasColumn('expenses', 'is_allocated_to_shipment')) {
            Schema::table('expenses', fn (Blueprint $t) => $t->dropColumn('is_allocated_to_shipment'));
        }
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deal_id');
            $table->boolean('is_allocated_to_shipment')->default(false);
        });
    }
};
