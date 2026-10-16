<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Project;
use App\Support\CostEstimate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The studio's work shown in new ways: the map playing the projects on year
 * by year, and the cost estimator drawing the house and showing projects
 * built on plots like the visitor's.
 */
class CreativeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Category::updateOrCreate(['key' => 'residential'], ['name' => 'Residential', 'name_ku' => 'نیشتەجێبوون', 'sort_order' => 1]);
    }

    private function project(array $attrs): Project
    {
        return Project::create($attrs + [
            'category' => 'residential', 'status' => 'Completed', 'size' => 'default',
            'imgs' => ['https://example.com/'.uniqid().'.jpg'], 'is_published' => true,
        ]);
    }

    /* ---- Watch the studio grow ----------------------------------------- */

    public function test_the_map_offers_to_play_the_projects_on_year_by_year(): void
    {
        $this->project(['name' => 'First House', 'year' => '2022', 'lat' => 36.19, 'lng' => 44.01]);
        $this->project(['name' => 'Long Job', 'year' => '2021 – 2024', 'lat' => 36.2, 'lng' => 44.02]);
        $this->project(['name' => 'Undated', 'year' => null, 'lat' => 36.21, 'lng' => 44.03]);

        $html = $this->get('/')->assertOk()
            ->assertSee('map-growth.js')
            ->assertSee('data-play-en="Watch the studio grow" data-play-ku="گەشەی ستۆدیۆکە ببینە"', false)
            ->assertSee('data-beyond-en="And beyond: {place}"', false)
            ->getContent();

        // a tick for each year a project ended in, oldest first
        $this->assertMatchesRegularExpression('#<li data-year="2022"><span>2022</span></li>\s*<li data-year="2024"><span>2024</span></li>#', $html);
        $this->assertStringNotContainsString('data-year="2021"', $html);
    }

    public function test_one_year_of_work_is_not_played(): void
    {
        $this->project(['name' => 'Only One', 'year' => '2025']);
        $this->get('/')->assertOk()->assertDontSee('id="mapGrow"', false);
    }

    /* ---- The estimator draws the house --------------------------------- */

    public function test_the_estimator_draws_the_house_from_the_answers(): void
    {
        $html = $this->get('/estimate?plot=600&floors=3&basement=1&pool=1')->assertOk()
            ->assertSee('estimate-house.js')
            ->getContent();

        preg_match("#data-house='([^']*)'#", $html, $m);
        $house = json_decode(html_entity_decode($m[1]), true);
        // 60% of 600 m² on each of three floors
        $this->assertSame(['plot' => 600, 'plot_known' => true, 'footprint' => 360, 'built' => 1080, 'floors' => 3, 'basement' => true, 'pool' => true], $house);

        // the drawing's words travel with the page, in its language
        $this->assertStringContainsString('"est_house_floors":"{n} floors"', $html);
        $this->get('/ku/estimate?plot=600')->assertSee('"est_house_floors":"{n} نهۆم"', false);

        // nothing to draw yet: the plot alone, empty
        $this->get('/estimate')->assertOk()->assertSee("data-house='null'", false);
    }

    public function test_a_built_area_without_a_plot_is_drawn_on_the_plot_it_would_need(): void
    {
        $house = CostEstimate::house(CostEstimate::calculate(CostEstimate::inputs(['built' => 300, 'floors' => 2])));
        $this->assertSame(150, $house['footprint']);
        $this->assertSame(250, $house['plot']);         // 150 m² is 60% of 250
        $this->assertFalse($house['plot_known']);
    }

    public function test_each_answer_sends_the_drawing_its_new_numbers(): void
    {
        $this->getJson('/estimate/calc?plot=500&floors=2')->assertOk()
            ->assertJsonPath('house.footprint', 300)
            ->assertJsonPath('house.floors', 2)
            ->assertJsonPath('house.pool', false);
        $this->getJson('/estimate/calc')->assertOk()->assertJsonPath('house', null);
    }

    /* ---- Built on a plot like yours ------------------------------------ */

    public function test_projects_on_plots_like_the_visitors_are_shown_under_the_estimate(): void
    {
        $this->project(['name' => 'Exact Villa', 'area' => '250 m²']);
        $this->project(['name' => 'Near House', 'area' => '280 m²']);
        $this->project(['name' => 'Big Block', 'area' => '1,200 m²']);
        $this->project(['name' => 'Hidden Villa', 'area' => '250 m²', 'is_published' => false]);
        $this->project(['name' => 'No Area', 'area' => null]);

        $this->get('/estimate?plot=250')->assertOk()
            ->assertSee('Built on a plot like yours')
            ->assertSee('Our projects on plots close to 250 m².')
            ->assertSeeInOrder(['Exact Villa', '250 m² plot', 'Near House', '280 m² plot'])
            ->assertDontSee('Big Block')->assertDontSee('Hidden Villa');

        // and as the plot changes
        $similar = $this->getJson('/estimate/calc?plot=1100')->assertOk()->json('similar');
        $this->assertStringContainsString('Big Block', $similar);
        $this->assertStringNotContainsString('Exact Villa', $similar);

        // in Kurdish, to the Kurdish pages
        $this->get('/ku/estimate?plot=250')->assertSee('لەسەر زەوییەکی وەک هی تۆ')->assertSee(url('/ku/projects/exact-villa'), false);

        // nothing near: nothing shown
        $this->getJson('/estimate/calc?plot=5000')->assertJsonPath('similar', '');
    }

    public function test_a_plot_area_is_read_as_a_number(): void
    {
        $this->assertSame(1200, (new Project(['area' => '1,200 m²']))->plotArea());
        $this->assertSame(250, (new Project(['area' => '250 m2']))->plotArea());
        $this->assertNull((new Project(['area' => '']))->plotArea());
        $this->assertNull((new Project(['area' => null]))->plotArea());
    }
}
