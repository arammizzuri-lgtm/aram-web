<?php

namespace Database\Seeders;

use App\Models\PageView;
use Illuminate\Database\Seeder;

/**
 * OPTIONAL demo data — fills the last 30 days with sample visitor traffic so the
 * dashboard looks alive before the site goes live. NOT part of DatabaseSeeder.
 * Run with:  php artisan db:seed --class=DemoVisitsSeeder
 * Clear with: php artisan tinker --execute="App\Models\PageView::query()->delete();"
 */
class DemoVisitsSeeder extends Seeder
{
    public function run(): void
    {
        PageView::query()->delete();
        $rows = [];

        for ($d = 0; $d < 30; $d++) {
            $day = now()->subDays($d);
            $visitors = $d === 0 ? rand(8, 16) : rand(4, 24);

            for ($v = 0; $v < $visitors; $v++) {
                $hash = hash('sha256', "demo-{$d}-{$v}");

                for ($w = 0, $views = rand(1, 3); $w < $views; $w++) {
                    $rows[] = [
                        'visitor_hash' => $hash,
                        'path'         => '/',
                        'referrer'     => null,
                        'created_at'   => $day->copy()->setTime(rand(8, 22), rand(0, 59))->toDateTimeString(),
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            PageView::insert($chunk);
        }

        $this->command->info('Seeded '.count($rows).' demo page views.');
    }
}
