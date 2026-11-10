<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Two figures on the landmark pages are written with words — "88 BC – AD
 * 33" (Hawraman's parchments), "1 Apr 2020" (the water reaching
 * Hasankeyf) — and showed those English words on the Kurdish and Arabic
 * pages. A figure can carry its own Kurdish and Arabic now (value_ku,
 * value_ar); these two get theirs, where the figure is still as it was
 * written and has none yet. Anything edited in the admin is left alone.
 */
return new class extends Migration
{
    private const WORDS = [
        '88 BC – AD 33' => ['value_ku' => '٨٨ پ.ز – ٣٣ ز', 'value_ar' => '٨٨ ق.م – ٣٣ م'],
        '1 Apr 2020' => ['value_ku' => '١ی نیسانی ٢٠٢٠', 'value_ar' => '١ نيسان ٢٠٢٠'],
    ];

    public function up(): void
    {
        foreach (DB::table('landmarks')->whereIn('slug', ['hawraman', 'hasankeyf'])->get(['id', 'sections']) as $row) {
            $sections = json_decode((string) $row->sections, true);
            if (! is_array($sections)) {
                continue;
            }
            $changed = false;
            foreach ($sections as $s => $section) {
                if (($section['type'] ?? null) !== 'stats' || ! is_array($section['data']['items'] ?? null)) {
                    continue;
                }
                foreach ($section['data']['items'] as $i => $item) {
                    $words = self::WORDS[$item['value'] ?? ''] ?? null;
                    if ($words && blank($item['value_ku'] ?? null) && blank($item['value_ar'] ?? null)) {
                        $sections[$s]['data']['items'][$i] = $item + $words;
                        $changed = true;
                    }
                }
            }
            if ($changed) {
                DB::table('landmarks')->where('id', $row->id)->update(['sections' => json_encode($sections)]);
            }
        }
    }

    public function down(): void
    {
        // the translations stay; nothing to take back
    }
};
