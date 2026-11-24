<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The essay "Staying cool without machines" (the old house in section): its words, plates and sources are
 * in database/data/journal-staying-cool-without-machines.json, its new drawing in resources/journal/cooling-drawings.json.
 * Added once, as the essays before it were; after that it is edited in the admin like any other.
 */
return new class extends Migration
{
    private const SLUG = 'staying-cool-without-machines';

    public function up(): void
    {
        if (DB::table('essays')->where('slug', self::SLUG)->exists()) {
            return;
        }
        $e = json_decode(file_get_contents(database_path('data/journal-'.self::SLUG.'.json')), true);
        $now = now();
        DB::table('essays')->insert([...$e,
            'extras' => json_encode($e['extras'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'sections' => json_encode($e['sections'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now, 'updated_at' => $now]);
    }

    public function down(): void
    {
        DB::table('essays')->where('slug', self::SLUG)->delete();
    }
};
