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
        $ku->assertSee('<span class="sx__label">پرۆژەکان</span>', false);   // the menu, server-rendered in Kurdish
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

    /* ── The page on white paper: plates, and chapters between them ───── */

    public function test_the_page_opens_as_a_drawing_sheet_and_shows_every_photograph_as_a_plate(): void
    {
        Storage::fake('public');
        // an upright photograph, a level one and a panorama, after the cover
        foreach (['cover' => [1600, 1000], 'tall' => [800, 1200], 'level' => [1500, 1000], 'pano' => [2400, 1000]] as $name => [$w, $h]) {
            $this->jpeg("projects/$name.jpg", $w, $h);
        }
        $this->project(['imgs' => ['projects/cover.jpg', 'projects/tall.jpg', 'projects/level.jpg', 'projects/pano.jpg'], 'year' => '2024']);
        $this->assertSame(0.667, Project::ratioOf('projects/tall.jpg'));
        $this->assertSame(1.5, Project::ratioOf('https://example.com/elsewhere.jpg'));

        $html = $this->get('/projects/mountain-villa-shaqlawa')->assertOk()
            ->assertSee('<body class="pp pp--paper">', false)->assertSee('project-paper.css')
            ->assertSee('<h1 class="pj-title__h"><bdi>Mountain Villa — Shaqlawa</bdi></h1>', false)
            ->assertSee('Photographs (4)')
            ->getContent();
        // the cover on the sheet, opening the viewer at the first photograph; the key holds all four small
        $this->assertMatchesRegularExpression('#<div class="pj-cover">\s*<button class="pj-cover__open" type="button" data-open="0"#', $html);
        $this->assertSame(4, preg_match_all('#<ol class="pj-key">.*?</ol>#s', $html, $key) ? substr_count($key[0][0], '<li>') : 0);
        // the title strip's particulars: status first, then the facts, then the architect
        $this->assertMatchesRegularExpression('#<dl class="pj-block">.*?<dt>Status</dt>.*?Completed.*?<dt>Year</dt>\s*<dd><bdi>2024</bdi></dd>.*?<dt>Architect</dt>#s', $html);
        // the upright one stands beside the level one at one height, each in its own shape; the panorama has a row alone
        $this->assertMatchesRegularExpression('#pj-row--just">\s*<figure class="pp-shot pj-plate"\s+style="--r: 0\.667">.*?data-open="1".*?<figure class="pp-shot pj-plate"\s+style="--r: 1\.5">.*?data-open="2"#s', $html);
        $this->assertMatchesRegularExpression('#pj-row--full">\s*<figure class="pp-shot pj-plate"\s+style="--r: 2\.4">.*?data-open="3"#s', $html);
        // the cover is among the plates too, first
        $this->assertMatchesRegularExpression('#pj-row--full">\s*<figure class="pp-shot pj-plate"\s+style="--r: 1\.6">.*?data-open="0"#s', $html);
        $this->assertSame(4, substr_count($html, 'class="pp-shot pj-plate"'));
    }

    public function test_the_neighbouring_projects_are_sheets_either_side_of_every_project(): void
    {
        $this->project();
        $this->project(['name' => 'Erbil Tower Residences', 'name_ku' => null]);
        $this->project(['name' => 'Torre Mizuri — Madrid', 'name_ku' => null]);

        $html = $this->get('/projects/mountain-villa-shaqlawa')->assertOk()->getContent();
        // the one before, every project, the one after — in that order, each named with its sheet number
        $this->assertMatchesRegularExpression('#<nav class="pj-nav"[^>]*>.*?pj-nav__item--prev.*?<b>\d+</b>.*?class="pj-nav__all" href="[^"]*/projects">.*?pj-nav__item--next.*?<b>\d+</b>.*?</nav>#s', $html);
        $this->assertSame(2, substr_count($html, 'class="pj-nav__item '));

        // with only two projects the other one is said once, as the next
        Project::where('name', 'Torre Mizuri — Madrid')->delete();
        $html = $this->get('/projects/mountain-villa-shaqlawa')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'class="pj-nav__item '));
        $this->assertStringContainsString('pj-nav__item--next', $html);
    }

    public function test_sections_added_in_the_admin_are_chapters_with_the_photographs_between_them(): void
    {
        Storage::fake('public');
        $this->jpeg('projects/sections/plan.jpg', 1400, 1000);
        $this->project([
            'imgs' => ['https://example.com/0.jpg', 'https://example.com/1.jpg', 'https://example.com/2.jpg', 'https://example.com/3.jpg'],
            'sections' => [
                ['type' => 'text', 'data' => ['heading_en' => 'The brief', 'heading_ku' => 'داواکاری', 'body_en' => "A house for **three** generations.\n\n- stone\n- shade", 'body_ku' => 'خانوویەک بۆ سێ نەوە.']],
                ['type' => 'photos', 'data' => ['count' => 1]],
                ['type' => 'quote', 'data' => ['text_en' => 'Shade first.', 'by_en' => 'The architect']],
                ['type' => 'figures', 'data' => ['heading_en' => 'In numbers', 'items' => [['value' => '420 m²', 'label_en' => 'Built area'], ['value' => '', 'label_en' => 'Left empty']]]],
                ['type' => 'image', 'data' => ['image' => 'projects/sections/plan.jpg', 'caption_en' => 'Ground floor plan', 'sheet' => true]],
                ['type' => 'marquee', 'data' => ['text_en' => 'A kind the page does not know']],
            ],
        ]);

        $html = $this->get('/projects/mountain-villa-shaqlawa')->assertOk()->getContent();
        $this->assertStringContainsString('<strong>three</strong>', $html);
        $this->assertStringContainsString('<li>shade</li>', $html);
        $this->assertStringNotContainsString('Left empty', $html);
        $this->assertStringNotContainsString('A kind the page does not know', $html);
        // in the order given: the chapter, one photograph, the statement, the figures, the drawing — then the photographs left over
        $at = fn (string $needle) => strpos($html, $needle);
        $plate = fn (int $n) => $at('alt="Mountain Villa — Shaqlawa — '.$n.'"');
        $order = [$at('>The brief</h2>'), $plate(1), $at('<p>Shade first.</p>'), $at('<dd><bdi>420 m²</bdi></dd>'), $at('Ground floor plan</figcaption>'), $plate(2), $plate(3), $plate(4)];
        $this->assertNotContains(false, $order);
        $sorted = $order;
        sort($sorted);
        $this->assertSame($sorted, $order);
        // chapters are numbered as they come; the drawing opens in the viewer as a set of its own
        $this->assertMatchesRegularExpression('#class="pj-chapter__n" aria-hidden="true">01</span>\s*<h2 class="pj-chapter__title">The brief#', $html);
        $this->assertMatchesRegularExpression('#class="pj-chapter__n" aria-hidden="true">02</span>\s*<h2 class="pj-chapter__title">In numbers#', $html);
        $this->assertStringContainsString('<figure class="pj-sheet" data-images=', $html);

        // in Kurdish, the Kurdish words — the English where none were written
        $this->get('/ku/projects/mountain-villa-shaqlawa')->assertOk()->assertSee('داواکاری')->assertSee('خانوویەک بۆ سێ نەوە.')->assertSee('Shade first.');

        // and the admin builds them in the project's form
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']);
        $this->actingAs($admin)->get(ProjectResource::getUrl('edit', ['record' => Project::first()]))->assertOk()
            ->assertSee('More on the page')->assertSee('Add a section')->assertSee('Text · The brief');
    }

    public function test_robots_txt_points_at_the_sitemap(): void
    {
        $this->get('/robots.txt')->assertOk()
            ->assertSee('Sitemap: '.url('/sitemap.xml'), false)
            ->assertDontSee('studio-aks-panel');
    }
}
