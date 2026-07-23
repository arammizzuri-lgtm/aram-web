<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Map-only projects (a pin and a line in the statistics, no photos) must stay
 * out of the Selected Works grid *and* out of the project overlay's next /
 * previous sequence — while still reaching the map and the statistics.
 */
class ProjectGridExclusionTest extends TestCase
{
    use RefreshDatabase;

    private function project(array $attrs): Project
    {
        return Project::create($attrs + [
            'categories' => ['residential'], 'status' => 'Completed',
            'size' => 'default', 'city' => 'Madrid', 'country' => 'Spain',
        ]);
    }

    public function test_map_only_and_image_less_projects_get_no_gallery_card(): void
    {
        $this->project(['name' => 'Photographed House', 'imgs' => ['https://ex.com/a.jpg']]);
        $this->project(['name' => 'Pin Only House', 'map_only' => true, 'lat' => 40.4, 'lng' => -3.7]);
        $this->project(['name' => 'No Photos Yet', 'imgs' => []]);

        $res = $this->get('/')->assertOk();

        $res->assertSee('data-name="Photographed House"', false);
        $res->assertDontSee('data-name="Pin Only House"', false);
        $res->assertDontSee('data-name="No Photos Yet"', false);
    }

    public function test_they_still_reach_the_map_and_the_statistics(): void
    {
        $this->project(['name' => 'Photographed House', 'imgs' => ['https://ex.com/a.jpg'], 'area' => '1,000 m²']);
        $this->project(['name' => 'Pin Only House', 'map_only' => true, 'lat' => 40.4, 'lng' => -3.7, 'area' => '500 m²']);

        $res = $this->get('/')->assertOk();

        // both are in the payload the map reads…
        $res->assertSee('Pin Only House', false);
        // …and both count toward the statistics
        $stats = $res->viewData('stats');
        $this->assertSame(2, $stats['projects']);
        $this->assertSame(1500, $stats['area']);
    }

    public function test_a_map_only_project_carries_no_images_into_the_payload(): void
    {
        // The overlay decides what to skip from this: no imgs means no card and
        // no place in the next / previous walk.
        $this->project([
            'name' => 'Pin Only House', 'map_only' => true,
            'imgs' => ['https://ex.com/a.jpg'], 'lat' => 40.4, 'lng' => -3.7,
        ]);

        $payload = $this->get('/')->assertOk()->viewData('payload');
        $entry = collect($payload['projects'])->firstWhere('name', 'Pin Only House');

        $this->assertTrue($entry['map_only']);
        $this->assertSame([], $entry['imgs']);
    }
}
