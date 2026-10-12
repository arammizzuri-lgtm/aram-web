<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Things a site check turned up, kept fixed: what the homepage sends, what
 * it asks the browser for, and markup the browser rejects.
 */
class SiteHealthTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $html): array
    {
        preg_match('/<script>window\.__SITE__ = (.*?);<\/script>/s', $html, $m);

        return json_decode($m[1] ?? 'null', true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_the_homepage_sends_only_what_its_scripts_use(): void
    {
        Project::create([
            'name' => 'Erbil Tower', 'name_ku' => 'تاوەری هەولێر', 'narrative' => 'Private words.',
            'category' => 'commercial', 'status' => 'Completed', 'size' => 'default',
            'imgs' => ['https://example.com/a.jpg'], 'is_published' => true,
        ]);

        $html = $this->get('/')->assertOk()->getContent();
        $site = $this->payload($html);

        // plain JSON, Kurdish as Kurdish (not six-byte \u escapes)
        $this->assertStringContainsString('تاوەری هەولێر', $html);
        $this->assertArrayNotHasKey('content', $site, 'the settings list is not used by the page');
        $this->assertArrayNotHasKey('narrative', $site['projects'][0]);
        $this->assertMatchesRegularExpression('#/kurdistan-data\.js\?v=\d+$#', $site['outlineUrl']);
        // one main heading — the overlay's project name is a section heading
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_the_optional_kurdish_font_is_only_asked_for_when_it_exists(): void
    {
        $this->assertFileDoesNotExist(public_path('fonts/kurdish.woff2'));
        $this->get('/ku')->assertOk()->assertDontSee('fonts/kurdish', false);
        $this->get('/careers')->assertOk()->assertDontSee('fonts/kurdish', false);
    }

    public function test_client_marks_with_path_commands_in_polygon_points_are_repaired(): void
    {
        $client = Client::create(['name' => 'Airport', 'mark' => '<polygon points="3,21 33,7 23,30 16,23 Z" stroke="#fff"/><polyline points="1,1 2,2 z"/>']);

        (require database_path('migrations/2026_10_12_000008_fix_svg_polygons_in_client_marks.php'))->up();

        $this->assertSame('<polygon points="3,21 33,7 23,30 16,23" stroke="#fff"/><polyline points="1,1 2,2"/>', $client->fresh()->mark);
    }
}
