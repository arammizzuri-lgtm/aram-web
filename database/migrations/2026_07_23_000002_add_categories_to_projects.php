<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A project can belong to several categories at once (e.g. Residential *and*
 * Exterior), so the single `category` key becomes a list. The original column
 * is kept as the primary category — it still drives the card meta line, the map
 * card and the hashtags — and is kept in sync with the first entry of the list
 * by the model, so nothing on the public site changes for existing projects.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->json('categories')->nullable()->after('category');
        });

        // Every existing project starts as a one-category list.
        foreach (DB::table('projects')->get(['id', 'category']) as $row) {
            DB::table('projects')->where('id', $row->id)->update([
                'categories' => json_encode(filled($row->category) ? [$row->category] : []),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('categories');
        });
    }
};
