<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A line has to remember which size it was picked from.
 *
 * product_id alone cannot say it: a P13 is sold in three sizes at three
 * different costs, and without this the line knows what was sold but not what
 * it cost — which is the number every margin on the deal is worked out from.
 *
 * Nulled rather than cascaded when the size goes: a delivered line is history,
 * and history should not lose its description because a size was tidied out of
 * a price list a year later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deal_lines', function (Blueprint $table) {
            $table->foreignId('product_size_id')->nullable()->after('product_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deal_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_size_id');
        });
    }
};
