<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each landmark of Kurdistan's architectural heritage becomes an editorial
 * page of its own: an opening (hero_style, intro), a page built from
 * reorderable sections of different kinds (sections — text, photographs,
 * galleries, statistics, facts, a timeline, quotations, annotated photographs,
 * a before/after pair, the map, UNESCO, related landmarks, sources), the years
 * it spans for the collection's timeline, and the UNESCO inscription's
 * details. The old single body text is kept and still shown by a landmark
 * that has no sections.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landmarks', function (Blueprint $table) {
            $table->string('hero_style', 20)->default('editorial');
            $table->text('intro_en')->nullable();
            $table->text('intro_ku')->nullable();
            $table->text('intro_ar')->nullable();
            $table->json('sections')->nullable();
            // negative = BC; year_to null = still lived in or in use
            $table->integer('year_from')->nullable();
            $table->integer('year_to')->nullable();
            $table->unsignedSmallInteger('unesco_year')->nullable();
            $table->unsignedInteger('unesco_ref')->nullable();
            $table->string('unesco_criteria', 40)->nullable();
            $table->string('unesco_name')->nullable();
            $table->string('image_source')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('landmarks', function (Blueprint $table) {
            $table->dropColumn(['hero_style', 'intro_en', 'intro_ku', 'intro_ar', 'sections', 'year_from', 'year_to',
                'unesco_year', 'unesco_ref', 'unesco_criteria', 'unesco_name', 'image_source']);
        });
    }
};
