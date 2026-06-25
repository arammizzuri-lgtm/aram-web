<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

/**
 * Ports the 21 projects that used to live in the hard-coded PROJECT_DATA array
 * (extracted verbatim to database/seeders/data/projects.json). The bento "size"
 * rhythm of the original static grid is reproduced so the layout is identical;
 * it is editable per project afterwards.
 */
class ProjectSeeder extends Seeder
{
    /** Original layout rhythm: featured-large and wide cards by position. */
    private const LARGE = [0, 6, 13];

    private const WIDE = [3, 9, 19];

    public function run(): void
    {
        $path = database_path('seeders/data/projects.json');
        $rows = json_decode(file_get_contents($path), true);

        foreach ($rows as $i => $row) {
            $size = in_array($i, self::LARGE, true) ? 'large'
                  : (in_array($i, self::WIDE, true) ? 'wide' : 'default');

            Project::firstOrCreate(['name' => $row['name']], [
                'num' => $row['num'],
                'name' => $row['name'],
                'category' => $row['category'],
                'status' => $row['status'],
                'size' => $size,
                'area' => $row['area'],
                'typology' => $row['typology'],
                'location' => $row['location'],
                'year' => $row['year'],
                'desc' => $row['desc'],
                'narrative' => $row['narrative'],
                'materials' => $row['materials'],
                'related' => $row['related'],
                'imgs' => $row['imgs'],
                'sort_order' => $row['sort_order'] ?? ($i + 1),
                'is_published' => true,
            ]);
        }

        $this->command->info('Seeded '.count($rows).' projects.');
    }
}
