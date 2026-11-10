<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Five "Sources & further reading" links on the landmark pages had moved and
 * answered "not found". Each is replaced where it stands — only that one
 * entry in a page's sources, matched by its old address — so anything else
 * edited in the admin since is left as it is. (The seeds in
 * database/seeders/heritage/ carry the new addresses for a fresh install.)
 */
return new class extends Migration
{
    private const MOVED = [
        // Shanidar Cave
        'https://humanorigins.si.edu/evidence/human-fossils/fossils/shanidar-3' => [
            'url' => 'https://humanorigins.si.edu/evidence/human-fossils/shanidar-3-neanderthal-skeleton',
        ],
        // Jerwan Aqueduct
        'https://isac.uchicago.edu/research/publications/oip/oip-24-sennacheribs-aqueduct-jerwan' => [
            'url' => 'https://isac.uchicago.edu/publications/sennacheribs-aqueduct-jerwan',
        ],
        // Amedi
        'https://mcid.mcah.columbia.edu/node/87269' => [
            'url' => 'https://mcid.mcah.columbia.edu/mapping-mesopotamian-monuments/amadiyaamedi-citadel',
        ],
        // Taq-e Bostan: Iranica has no article of its own on the site; its
        // entry on the Sasanian reliefs is where the site is treated
        'https://www.iranicaonline.org/articles/taq-e-bostan' => [
            'title' => 'Sasanian Rock Reliefs — Encyclopaedia Iranica',
            'url' => 'https://www.iranicaonline.org/articles/sasanian-rock-reliefs/',
            'note' => 'Ṭāq-e Bostān among the reliefs of the dynasty, and their dating',
        ],
        // Mudhafaria Minaret: Erbil and the minaret are in the second volume
        'https://archive.org/details/narrativeofresid01rich' => [
            'url' => 'https://archive.org/details/narrativeofresi02rich',
            'note' => 'Volume 2: Erbil in 1820, and the minaret measured',
        ],
    ];

    public function up(): void
    {
        foreach (DB::table('landmarks')->select('id', 'sections')->get() as $row) {
            $sections = json_decode((string) $row->sections, true);
            if (! is_array($sections)) {
                continue;
            }
            $changed = false;
            foreach ($sections as $s => $section) {
                if (($section['type'] ?? null) !== 'sources' || ! is_array($section['data']['items'] ?? null)) {
                    continue;
                }
                foreach ($section['data']['items'] as $i => $item) {
                    $fix = self::MOVED[$item['url'] ?? ''] ?? null;
                    if ($fix) {
                        $sections[$s]['data']['items'][$i] = array_merge($item, $fix);
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
        // the old addresses led nowhere; nothing to take back
    }
};
