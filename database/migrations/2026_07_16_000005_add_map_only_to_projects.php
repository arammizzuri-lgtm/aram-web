<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Map-only" projects: pinned on the map and counted in the statistics, but
 * with no images and no card in the Selected Works gallery. Clicking their pin
 * shows a spec-only card (title, type, year, plot area, location).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('map_only')->default(false)->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('map_only');
        });
    }
};
