<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;

/**
 * Seed the dynamic footer social links (JSON) from the four legacy per-network
 * URL settings, so existing environments keep their current links and the
 * admin repeater shows them ready to edit / remove / extend.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (SiteSetting::get('footer_socials')) {
            return; // already migrated
        }

        $legacy = [
            'Instagram' => 'footer_instagram_url',
            'LinkedIn'  => 'footer_linkedin_url',
            'Behance'   => 'footer_behance_url',
            'Archello'  => 'footer_archello_url',
        ];

        $rows = [];
        foreach ($legacy as $label => $key) {
            $rows[] = ['label' => $label, 'url' => (string) SiteSetting::get($key, '#')];
        }

        SiteSetting::put(
            'footer_socials',
            json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'footer',
        );
    }

    public function down(): void
    {
        SiteSetting::query()->where('key', 'footer_socials')->delete();
    }
};
