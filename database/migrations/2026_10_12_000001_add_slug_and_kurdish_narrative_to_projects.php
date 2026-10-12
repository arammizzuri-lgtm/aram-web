<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Every project gets its own address — /projects/{slug} and the Kurdish
 * /ku/projects/{slug} — so a single project can be linked, shared and indexed
 * instead of only living inside the homepage overlay.
 *
 * The slug is filled in from the English name for the projects that already
 * exist, and the architect's narrative gains a Kurdish twin so the project
 * page reads fully in both languages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('slug', 191)->nullable()->unique()->after('name');
            $table->text('narrative_ku')->nullable()->after('narrative');
        });

        // Oldest first, so where two projects share a name the earlier one
        // keeps the clean slug and the later one takes the "-2".
        $taken = [];
        foreach (DB::table('projects')->orderBy('id')->get(['id', 'name', 'num']) as $row) {
            // Same rule as Project::slugCandidate(), spelled out here so this
            // migration keeps working whatever later becomes of the model.
            $slug = Str::slug((string) $row->name) ?: 'project-'.(Str::slug((string) $row->num) ?: $row->id);
            $unique = $slug;
            for ($n = 2; in_array($unique, $taken, true); $n++) {
                $unique = $slug.'-'.$n;
            }
            $taken[] = $unique;

            DB::table('projects')->where('id', $row->id)->update(['slug' => $unique]);
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'narrative_ku']);
        });
    }
};
