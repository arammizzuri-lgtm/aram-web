<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

/**
 * Seeds every editable text/image block on the public site with its current
 * value (extracted verbatim to database/seeders/data/site_settings.json).
 * Uses updateOrCreate so re-running never duplicates keys, and suppresses the
 * per-row cache-busting events for a single flush at the end.
 */
class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/site_settings.json');
        $rows = json_decode(file_get_contents($path), true);

        SiteSetting::withoutEvents(function () use ($rows) {
            foreach ($rows as $row) {
                SiteSetting::updateOrCreate(
                    ['key' => $row['key']],
                    ['value' => $row['value'], 'group' => $row['group']],
                );
            }
        });

        SiteSetting::flushCache();
        $this->command->info('Seeded '.count($rows).' site settings.');
    }
}
