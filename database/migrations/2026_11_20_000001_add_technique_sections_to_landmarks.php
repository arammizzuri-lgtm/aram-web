<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "How it was built" on six landmark pages — Göbekli Tepe, Jerwan, Hawraman,
 * Erbil Citadel, the Mudhafaria Minaret, Taq-e Bostan: a 'technique' section,
 * the studio's drawing built up in steps, with its words in three languages
 * (database/seeders/heritage/_techniques.php). Added before the page's
 * timeline — or its map, or at the end — and only to a page that has no such
 * section yet, so nothing the admin has arranged is moved or replaced.
 */
return new class extends Migration
{
    public function up(): void
    {
        $techniques = require database_path('seeders/heritage/_techniques.php');

        foreach ($techniques as $slug => $data) {
            $row = DB::table('landmarks')->where('slug', $slug)->first(['id', 'sections']);
            $sections = $row ? json_decode((string) $row->sections, true) : null;
            if (! is_array($sections) || $sections === [] || collect($sections)->contains('type', 'technique')) {
                continue;
            }

            $at = count($sections);
            foreach (['timeline', 'map', 'related', 'sources'] as $before) {
                $found = collect($sections)->search(fn ($s) => ($s['type'] ?? null) === $before);
                if ($found !== false) {
                    $at = $found;
                    break;
                }
            }
            array_splice($sections, $at, 0, [['type' => 'technique', 'data' => $data]]);

            DB::table('landmarks')->where('id', $row->id)->update(['sections' => json_encode($sections)]);
        }
    }

    public function down(): void
    {
        // the sections stay; the admin can remove one from a page
    }
};
