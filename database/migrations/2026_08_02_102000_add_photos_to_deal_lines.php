<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A photo on the line, because "models" is how these deals are actually agreed.
 *
 * The supplier sends pictures, the customer picks one, and that choice is what
 * gets argued about when the goods land. Stored as a path rather than through
 * the media library so a quotation can copy the path and freeze it: replacing
 * the picture later must not change what an approved quotation showed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deal_lines', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('specification');
        });
    }

    public function down(): void
    {
        Schema::table('deal_lines', fn (Blueprint $t) => $t->dropColumn('photo_path'));
    }
};
