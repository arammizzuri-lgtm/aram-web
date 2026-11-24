<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The essay "The courtyard house across Kurdistan" (Erbil, Sulaimani, Amed, Amedi): its words, plates and sources are
 * in database/data/journal-courtyard-house-across-kurdistan.json, its new drawings in resources/journal/kurdistan-drawings.json.
 * Added once, as the two essays before it were; after that it is edited in the admin like any other.
 */
return new class extends Migration
{
    private const SLUG = 'courtyard-house-across-kurdistan';

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
