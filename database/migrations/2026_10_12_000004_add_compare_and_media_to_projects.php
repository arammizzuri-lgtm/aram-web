<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two ways a project page can show more than photographs:
 *
 * - compare_before / compare_after: a render and a photograph of the same
 *   view, laid one over the other with a slider between them.
 * - media: film and 360° tours, as links to where they are hosted (YouTube,
 *   Vimeo, Matterport, Kuula) — never uploaded, since this host's disk is
 *   small and those services stream far better than it could.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('compare_before')->nullable()->after('cover_y');
            $table->string('compare_after')->nullable()->after('compare_before');
            $table->json('media')->nullable()->after('compare_after');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['compare_before', 'compare_after', 'media']);
        });
    }
};
