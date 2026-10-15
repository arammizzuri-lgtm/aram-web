<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every project on one page (/projects, /ku/projects), opened from the
 * "Projects" heading on the homepage: the studio's work as a set of
 * drawings — a register year by year (Index) and framed sheets with title
 * blocks (Sheets), the categories as a legend of hatches.
 */
class ProjectsIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Category::updateOrCreate(['key' => 'residential'], ['name' => 'Residential', 'name_ku' => 'نیشتەجێبوون', 'sort_order' => 1]);
        Category::updateOrCreate(['key' => 'commercial'], ['name' => 'Commercial', 'name_ku' => 'بازرگانی', 'sort_order' => 2]);
        Category::updateOrCreate(['key' => 'cultural'], ['name' => 'Cultural', 'name_ku' => 'کولتووری', 'sort_order' => 3]);

        $this->project(['name' => 'Mountain Villa', 'name_ku' => 'ڤیلای شاخ', 'category' => 'residential', 'city' => 'Shaqlawa', 'year' => '2021–2024']);
        $this->project(['name' => 'So Delicious Cafe', 'category' => 'commercial', 'status' => 'Under Construction']);
        $this->project(['name' => 'Hidden Draft', 'category' => 'residential', 'is_published' => false]);
        $this->project(['name' => 'Map Pin Only', 'category' => 'residential', 'map_only' => true]);
    }

    private function project(array $attrs): Project
    {
        return Project::create($attrs + [
            'status' => 'Completed', 'size' => 'default',
            'imgs' => ['https://example.com/'.uniqid().'.jpg'], 'is_published' => true,
        ]);
    }

    public function test_every_project_with_a_page_is_listed_with_a_link_to_it(): void
    {
        $this->get('/projects')->assertOk()
            ->assertSee('<h1 class="px-title">Projects</h1>', false)
            ->assertSee('Mountain Villa')->assertSee('So Delicious Cafe')
            ->assertSee(url('/projects/mountain-villa'), false)
            ->assertSee('2 projects')
            ->assertDontSee('Hidden Draft')->assertDontSee('Map Pin Only')
            ->assertSee('"@type":"ItemList"', false)
            ->assertSee('projects.js');
    }

    public function test_only_categories_with_projects_are_offered_each_with_its_count(): void
    {
        $html = $this->get('/projects')->getContent();

        // each with the hatch it is drawn in
        $this->assertMatchesRegularExpression('#\?type=residential" data-type="residential"\s*>\s*<span class="px-swatch hatch-0" aria-hidden="true"></span><bdi>Residential</bdi><sup>1</sup>#', $html);
        $this->assertStringContainsString('?type=commercial', $html);
        $this->assertStringNotContainsString('?type=cultural', $html);
    }

    public function test_a_category_in_the_address_is_shown_on_its_own(): void
    {
        $html = $this->get('/projects?type=commercial')->assertOk()->assertSee('1 project')->getContent();

        $this->assertMatchesRegularExpression('#data-categories="residential"[^>]*hidden#', $html);
        $this->assertDoesNotMatchRegularExpression('#data-categories="commercial"[^>]*hidden#', $html);
        $this->assertMatchesRegularExpression('#class="px-key is-on"[^>]*data-type="commercial"#', $html);

        // something that is not a category shows everything
        $this->get('/projects?type=<script>')->assertOk()->assertSee('2 projects')->assertDontSee('&lt;script', false)->assertDontSee('%3Cscript', false);
    }

    public function test_the_index_runs_year_by_year_and_the_sheets_carry_title_blocks(): void
    {
        $html = $this->get('/projects')->assertOk()
            // the newest year first (a span counts by the year it ended), undated work last
            ->assertSeeInOrder(['<bdi>2024</bdi>', 'Mountain Villa', '<bdi>Undated</bdi>', 'So Delicious Cafe'], false)
            ->getContent();

        // the dimension: how many, over which years
        $this->assertMatchesRegularExpression('#id="pxCount"[^>]*>2 projects</span>\s*<span class="px-dim__span">· <bdi>2024</bdi>#', $html);
        // each sheet's title block: number, name, place, year, status
        $this->assertMatchesRegularExpression('#<small>Sheet</small><b>\d+</b>.*?<bdi>Mountain Villa</bdi>.*?<bdi>Shaqlawa</bdi>.*?<bdi>2024</bdi>.*?Completed#s', $html);
        // the Index is shown first; the Sheets wait
        $this->assertMatchesRegularExpression('#id="pxSheets"[^>]*hidden#', $html);
        $this->assertDoesNotMatchRegularExpression('#id="pxIndex"[^>]*hidden#', $html);
    }

    public function test_the_sheets_can_be_asked_for_in_the_address(): void
    {
        $html = $this->get('/projects?view=sheets&type=residential')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#id="pxIndex"[^>]*hidden#', $html);
        $this->assertDoesNotMatchRegularExpression('#id="pxSheets"[^>]*hidden#', $html);
        // the legend keeps the view, the views keep the category
        $this->assertStringContainsString('href="'.url('/projects').'?type=commercial&amp;view=sheets"', $html);
        $this->assertStringContainsString('href="'.url('/projects').'?type=residential" data-view="index"', $html);
    }

    public function test_the_kurdish_page_links_to_the_kurdish_project_pages(): void
    {
        $this->get('/ku/projects')->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('پرۆژەکان')
            ->assertSee('ڤیلای شاخ')
            ->assertSee(url('/ku/projects/mountain-villa'), false)
            ->assertSee('hreflang="en" href="'.url('/projects').'"', false);
    }

    public function test_the_homepage_heading_opens_the_projects_page(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('class="pg__head-left pg__head-link" href="'.url('/projects').'"', false)
            ->assertSee('All projects');
        $this->get('/ku')->assertSee('href="'.url('/ku/projects').'"', false);
    }

    public function test_search_engines_are_told_of_the_page(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertSee('<loc>'.url('/projects').'</loc>', false);
    }
}
