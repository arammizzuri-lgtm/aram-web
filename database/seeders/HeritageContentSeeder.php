<?php

namespace Database\Seeders;

use App\Models\Landmark;
use Illuminate\Database\Seeder;

/**
 * The researched content of Kurdistan's architectural heritage: for each of
 * the collection's landmarks, its opening, its page of sections and its
 * credited photographs, in English, Kurdish and Arabic.
 *
 * One file per landmark in database/seeders/heritage/<slug>.php returns the
 * fields to set; its photographs are listed in <slug>.images.json (file,
 * size, credit, source — written by the fetch that stored them under
 * storage/app/public/heritage/<slug>/). In the content a photograph is named
 * by its basename — 'img' => '03-pillars' inside a section, 'cover' at the
 * top — and this seeder fills in the file, its measurements and its credit.
 *
 * Applied by a migration on deploy and re-runnable by hand
 * (php artisan db:seed --class=HeritageContentSeeder): a landmark is matched
 * by slug; one the admin has removed is left alone. It overwrites the text
 * and sections of the landmarks it knows — the admin's later edits to those
 * fields are replaced by whatever the files say, so re-run on purpose only.
 */
class HeritageContentSeeder extends Seeder
{
    public function run(): void
    {
        foreach (glob(database_path('seeders/heritage/*.php')) as $file) {
            $data = require $file;
            if (! is_array($data) || empty($data['slug'])) {
                continue;
            }
            $this->apply($data, dirname($file).'/'.$data['slug'].'.images.json');
        }
    }

    /** Set one landmark's content; returns whether a landmark was found. */
    public function apply(array $data, string $imagesFile): bool
    {
        $landmark = Landmark::where('slug', $data['slug'])->first();
        if (! $landmark) {
            return false;
        }
        $images = is_file($imagesFile) ? collect(json_decode(file_get_contents($imagesFile), true) ?: [])
            ->keyBy(fn ($i) => pathinfo($i['file'], PATHINFO_FILENAME)) : collect();

        $attrs = $data;
        unset($attrs['slug'], $attrs['cover'], $attrs['sections']);
        if (! empty($data['cover']) && ($img = $images->get($data['cover']))) {
            $attrs['image'] = $img['file'];
            $attrs['image_credit'] = $img['credit'] ?? null;
            $attrs['image_source'] = $img['source'] ?? null;
        }
        $attrs['sections'] = array_map(fn ($block) => [
            'type' => $block['type'],
            'data' => $this->withImages($block['data'] ?? [], $images),
        ], $data['sections'] ?? []);

        $landmark->fill($attrs)->save();

        return true;
    }

    /** Replace every 'img' => name in a block's data with the photograph's file, size and credit. */
    private function withImages(array $data, $images): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = $this->withImages($v, $images);
            }
        }
        if (isset($data['img'])) {
            $img = $images->get($data['img']);
            unset($data['img']);
            if ($img) {
                $data = ['file' => $img['file'], 'w' => $img['w'] ?? null, 'h' => $img['h'] ?? null, 'credit' => $img['credit'] ?? null, 'source' => $img['source'] ?? null] + $data;
            }
        }

        return $data;
    }
}
