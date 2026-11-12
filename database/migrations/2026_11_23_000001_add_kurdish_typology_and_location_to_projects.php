<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A project's typology and location in Kurdish, beside the Arabic ones —
 * the Kurdish pages showed them in English. Blank still shows the English.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'typology_ku')) {
                $table->string('typology_ku', 120)->nullable();
            }
            if (! Schema::hasColumn('projects', 'location_ku')) {
                $table->string('location_ku', 191)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['typology_ku', 'location_ku']);
        });
    }
};
