<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSiteContent;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Every project's own page (/projects/{slug}, /ku/projects/{slug}), the
 * Kurdish homepage at /ku, and what search engines read: the sitemap and
 * robots.txt.
 */
class ProjectPagesTest extends TestCase
{
    use RefreshDatabase;

    private function jpeg(string $path, int $w = 1600, int $h = 1000): void
    {
        $gd = imagecreatetruecolor($w, $h);
        imagefilledrectangle($gd, 0, 0, $w, $h, imagecolorallocate($gd, 90, 120, 160));
        ob_start();
        imagejpeg($gd, null, 90);
        Storage::disk('public')->put($path, (string) ob_get_clean());
        imagedestroy($gd);
    }

    private function project(array $attrs = []): Project
    {
        return Project::create($attrs + [
            'name' => 'Mountain Villa — Shaqlawa',
            'name_ku' => 'ڤیلای شاخ — شەقڵاوە',
            'category' => 'residential', 'status' => 'Completed', 'size' => 'default',
            'desc' => 'A hilltop house among the orchards.',
            'desc_ku' => 'خانوویەکی سەر گرد لەناو باخەکاندا.',
            'imgs' => ['https://example.com/cover.jpg', 'https://example.com/second.jpg'],
            'is_published' => true,
        ]);
    }

    public function test_a_slug_is_made_from_the_name_once_and_kept(): void
    {
        $first = $this->project();
        $this->assertSame('mountain-villa-shaqlawa', $first->slug);

        // the same name again takes the next free slug
        $this->assertSame('mountain-villa-shaqlawa-2', $this->project()->slug);

        // renaming does not move the page — shared links keep working
        $first->update(['name' => 'Shaqlawa House']);
        $this->assertSame('mountain-villa-shaqlawa', $first->fresh()->slug);

        // a hand-typed slug is cleaned up, not rejected
        $first->update(['slug' => 'Shaqlawa House 2024']);
        $this->assertSame('shaqlawa-house-2024', $first->fresh()->slug);
    }

    public function test_the_project_page_renders_in_english_and_kurdish(): void
    {
        $this->project();

        $en = $this->get('/projects/mountain-villa-shaqlawa')->assertOk();
        $en->assertSee('<html lang="en" dir="ltr">', false);
        $en->assertSee('<title>Mountain Villa — Shaqlawa — Aram Mizuri Architecture</title>', false);
        $en->assertSee('A hilltop house among the orchards.');
        $en->assertSee('<link rel="canonical" href="'.url('/projects/mountain-villa-shaqlawa').'">', false);
        $en->assertSee('hreflang="ku" href="'.url('/ku/projects/mountain-villa-shaqlawa').'"', false);
        $en->assertSee('application/ld+json', false);

        $ku = $this->get('/ku/projects/mountain-villa-shaqlawa')->assertOk();
        $ku->assertSee('<html lang="ku" dir="rtl">', false);
        $ku->assertSee('ڤیلای شاخ — شەقڵاوە');
        $ku->assertSee('خانوویەکی سەر گرد لەناو باخەکاندا.');
        $ku->assertSee('<link rel="canonical" href="'.url('/ku/projects/mountain-villa-shaqlawa').'">', false);
        // the language switch points at the same project in English
        $ku->assertSee('href="'.url('/projects/mountain-villa-shaqlawa').'" hreflang="en"', false);
    }

    public function test_projects_without_a_page_are_not_found(): void
    {
        $this->project(['name' => 'Hidden', 'is_published' => false]);
        $this->project(['name' => 'Pin Only', 'map_only' => true]);
        $this->project(['name' => 'No Photos', 'imgs' => []]);

        $this->get('/projects/hidden')->assertNotFound();
        $this->get('/projects/pin-only')->assertNotFound();
        $this->get('/projects/no-photos')->assertNotFound();
        $this->get('/ku/projects/no-such-project')->assertNotFound();
    }

    public function test_a_shared_link_previews_with_a_cropped_card_of_the_cover(): void
    {
        Storage::fake('public');
        $this->jpeg('projects/house.jpg', 1600, 2000);   // portrait: has to be cropped

        $project = $this->project(['imgs' => ['projects/house.jpg'], 'cover_x' => 50, 'cover_y' => 20]);

        $card = 'projects/og/house-50-20.jpg';
        $this->assertTrue(Storage::disk('public')->exists($card));
        [$w, $h, $type] = getimagesize(Storage::disk('public')->path($card));
        $this->assertSame([1200, 630, IMAGETYPE_JPEG], [$w, $h, $type]);

        $this->get('/projects/'.$project->slug)
            ->assertSee('<meta property="og:image" content="'.asset('storage/'.$card).'">', false)
            ->assertSee('<meta property="og:image:width" content="1200">', false);

        // moving the focal point makes a new card under a new address, so the
        // platforms that cache previews pick up the change
        $project->update(['cover_y' => 70]);
        $this->assertTrue(Storage::disk('public')->exists('projects/og/house-50-70.jpg'));
    }

    public function test_the_homepage_is_kurdish_under_ku_and_links_to_project_pages(): void
    {
        $this->project();

        $en = $this->get('/')->assertOk();
        $en->assertSee('<html lang="en" dir="ltr">', false);
        $en->assertSee('class="pgc__link" href="'.url('/projects/mountain-villa-shaqlawa').'"', false);
        $en->assertSee('hreflang="ku" href="'.url('/ku').'"', false);

        $ku = $this->get('/ku')->assertOk();
        $ku->assertSee('<html lang="ku" dir="rtl">', false);
        $ku->assertSee('<title>تەڵارسازیی ئارام مزووری | هەولێر، کوردستان</title>', false);
        $ku->assertSee('>پرۆژەکان</a>', false);                       // nav, server-rendered in Kurdish
        $ku->assertSee('class="pgc__link" href="'.url('/ku/projects/mountain-villa-shaqlawa').'"', false);
    }

    public function test_the_sitemap_lists_every_page_in_both_languages(): void
    {
        $this->project();
        $this->project(['name' => 'Hidden', 'is_published' => false]);

        $xml = $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->getContent();

        foreach (['/', '/ku', '/projects/mountain-villa-shaqlawa', '/ku/projects/mountain-villa-shaqlawa'] as $path) {
            $this->assertStringContainsString('<loc>'.url($path).'</loc>', $xml);
        }
        $this->assertStringContainsString('hreflang="x-default" href="'.url('/projects/mountain-villa-shaqlawa').'"', $xml);
        $this->assertStringContainsString('<image:loc>https://example.com/second.jpg</image:loc>', $xml);
        $this->assertStringNotContainsString('/projects/hidden', $xml);
        $this->assertNotFalse(simplexml_load_string($xml), 'sitemap must be well-formed XML');
    }

    public function test_the_admin_can_edit_the_address_and_open_the_page(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']);
        $project = $this->project();

        $this->actingAs($admin)->get(ProjectResource::getUrl('edit', ['record' => $project]))
            ->assertOk()
            ->assertSee('Page address')
            ->assertSee('mountain-villa-shaqlawa')
            ->assertSee(url('/projects/mountain-villa-shaqlawa'), false);   // "View page"

        // the new strings are editable alongside the rest of the site's text
        $this->actingAs($admin)->get(ManageSiteContent::getUrl())
            ->assertOk()
            ->assertSee('Search &amp; sharing', false)
            ->assertSee('Project page');
    }

    public function test_robots_txt_points_at_the_sitemap(): void
    {
        $this->get('/robots.txt')->assertOk()
            ->assertSee('Sitemap: '.url('/sitemap.xml'), false)
            ->assertDontSee('studio-aks-panel');
    }
}
