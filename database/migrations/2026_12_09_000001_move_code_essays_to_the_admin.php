<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The two essays that were written in code (the Citadel's houses, the Qaysari bazaar) become
 * admin essays, every text editable in the three languages: their words, plates and sources are in
 * database/data/journal-essays.json, read off the pages as they were. `extras` holds what an essay
 * with drawings has besides: its card's drawing sheet, a link at its end, what it is about.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('essays', function (Blueprint $table) {
            $table->json('extras')->nullable()->after('sections');
        });

        $now = now();
        foreach (json_decode(file_get_contents(database_path('data/journal-essays.json')), true) as $e) {
            if (DB::table('essays')->where('slug', $e['slug'])->exists()) {
                continue;
            }
            DB::table('essays')->insert([...$e,
                'extras' => json_encode($e['extras'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'sections' => json_encode($e['sections'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('essays')->whereIn('slug', ['citadel-house-plans', 'qaysari-bazaar'])->delete();
        Schema::table('essays', function (Blueprint $table) {
            $table->dropColumn('extras');
        });
    }
};
