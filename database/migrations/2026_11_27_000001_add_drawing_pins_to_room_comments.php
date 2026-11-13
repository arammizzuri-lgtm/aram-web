<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A client's comment can now be pinned to a spot on a drawing in their room,
 * instead of under a progress update: the drawing, its page, and the spot as
 * a fraction of the page's width and height (0–1, from the top left). The
 * studio's answers are pinned to the same spot, which makes them one thread.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_comments', function (Blueprint $table) {
            $table->foreignId('project_update_id')->nullable()->change();
            $table->foreignId('room_drawing_id')->nullable()->after('project_update_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('page')->nullable()->after('room_drawing_id');
            $table->decimal('x', 6, 5)->nullable()->after('page');
            $table->decimal('y', 6, 5)->nullable()->after('x');
        });
    }

    public function down(): void
    {
        DB::table('room_comments')->whereNull('project_update_id')->delete();
        Schema::table('room_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_drawing_id');
            $table->dropColumn(['page', 'x', 'y']);
            $table->foreignId('project_update_id')->nullable(false)->change();
        });
    }
};
