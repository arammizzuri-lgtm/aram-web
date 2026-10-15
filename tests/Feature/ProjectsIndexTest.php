<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every project on one page (/projects, /ku/projects), opened from the
 * "Projects" heading on the homepage.
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

        $this->project(['name' => 'Mountain Villa', 'name_ku' => 'ڤیلای شاخ', 'category' => 'residential', 'city' => 'Shaqlawa']);
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
            ->assertSee('<h1 class="pj-title">Projects</h1>', false)
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

        $this->assertMatchesRegularExpression('#\?type=residential" data-type="residential"\s*><bdi>Residential</bdi> <span class="pj-chip__n">1</span>#', $html);
        $this->assertStringContainsString('?type=commercial', $html);
        $this->assertStringNotContainsString('?type=cultural', $html);
    }

    public function test_a_category_in_the_address_is_shown_on_its_own(): void
    {
        $html = $this->get('/projects?type=commercial')->assertOk()->assertSee('1 project')->getContent();

        $this->assertMatchesRegularExpression('#data-categories="residential"[^>]*hidden#', $html);
        $this->assertDoesNotMatchRegularExpression('#data-categories="commercial"[^>]*hidden#', $html);
        $this->assertMatchesRegularExpression('#class="pj-chip is-active"[^>]*data-type="commercial"#', $html);

        // something that is not a category shows everything
        $this->get('/projects?type=<script>')->assertOk()->assertSee('2 projects')->assertDontSee('<script>', false);
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
