<?php

namespace Tests\Feature;

use App\Filament\Resources\Greetings\GreetingResource;
use App\Models\Category;
use App\Models\Greeting;
use App\Models\Project;
use App\Models\SiteEvent;
use App\Models\User;
use App\Support\CostEstimate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The studio's work shown in new ways: the map playing the projects on year
 * by year; the cost estimator drawing the house and showing projects built
 * on plots like the visitor's; a project as a picture for WhatsApp Status;
 * holiday cards made with the visitor's name.
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

    /* ---- A picture for WhatsApp Status --------------------------------- */

    public function test_a_project_page_offers_its_picture_for_status(): void
    {
        $this->project(['name' => 'Stone House', 'num' => '07', 'city' => 'Erbil', 'year' => '2024', 'status' => 'Under Construction']);

        $html = $this->get('/projects/stone-house')->assertOk()
            ->assertSee('cards.js')->assertSee('cards.css')
            ->assertSee('Picture for Status')
            ->getContent();
        preg_match("#data-story='([^']*)'#", $html, $m);
        $card = json_decode(html_entity_decode($m[1]), true);
        $this->assertSame('stone-house', $card['slug']);
        $this->assertSame('07', $card['num']);
        $this->assertSame('Under Construction', $card['status']);
        $this->assertSame('build', $card['tone']);
        $this->assertSame(['sheet' => 'Sheet', 'project' => 'Project', 'place' => 'Place', 'year' => 'Year', 'status' => 'Status'], $card['labels']);
        $this->assertSame('stone-house-status.jpg', $card['file']);

        // in Kurdish, the card is drawn in Kurdish
        $html = $this->get('/ku/projects/stone-house')->assertOk()->getContent();
        preg_match("#data-story='([^']*)'#", $html, $m);
        $card = json_decode(html_entity_decode($m[1]), true);
        $this->assertSame('ku', $card['lang']);
        $this->assertSame('لەژێر بنیاتنان', $card['status']);
    }

    /* ---- Holiday cards --------------------------------------------------- */

    public function test_a_yearly_card_comes_back_on_its_dates_even_over_new_year(): void
    {
        $newYear = Greeting::where('name', 'New Year')->firstOrFail();          // 26 Dec – 3 Jan, yearly
        $this->assertTrue($newYear->isOn(Carbon::parse('2027-12-31')));
        $this->assertTrue($newYear->isOn(Carbon::parse('2031-01-02')));
        $this->assertFalse($newYear->isOn(Carbon::parse('2027-12-25')));
        $this->assertFalse($newYear->isOn(Carbon::parse('2028-01-04')));

        $eid = Greeting::where('name', 'Eid al-Fitr')->firstOrFail();          // no dates yet, not offered
        $this->assertFalse($eid->isOn(Carbon::parse('2027-03-10')));
        $eid->update(['starts_on' => '2027-03-08', 'ends_on' => '2027-03-12', 'is_published' => true]);
        $this->assertTrue($eid->fresh()->isOn(Carbon::parse('2027-03-10')));
        $this->assertFalse($eid->fresh()->isOn(Carbon::parse('2028-03-10')));  // not yearly: only that year

        Carbon::setTestNow('2027-03-20');
        $this->assertSame('Newroz', Greeting::current()->name);
        Carbon::setTestNow();
    }

    public function test_the_card_page_is_offered_between_its_dates_only(): void
    {
        $this->project(['name' => 'Garden Villa', 'imgs' => ['https://example.com/a.jpg', 'https://example.com/b.jpg']]);

        Carbon::setTestNow('2027-03-20');                                    // Newroz
        $html = $this->get('/card')->assertOk()
            ->assertSee('Happy Newroz')
            ->assertSee('Make the card with your name')
            ->assertSee('Your name stays on your phone')
            ->assertSee('greeting.js')
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->getContent();
        preg_match("#data-card='([^']*)'#", $html, $m);
        $card = json_decode(html_entity_decode($m[1]), true);
        $this->assertSame(['en' => 'Happy Newroz', 'ku' => 'نەورۆزتان پیرۆز بێت'], $card['title']);
        $this->assertSame('newroz-card.jpg', $card['file']);
        $this->assertNotEmpty($card['photos']);

        $this->get('/ku/card')->assertOk()->assertSee('<h1 class="gc-title">نەورۆزتان پیرۆز بێت</h1>', false);

        // and the homepage offers it
        $this->get('/')->assertSee('class="gc-band"', false)->assertSee(url('/card'), false);

        // outside every card's dates: no card, no band
        Carbon::setTestNow('2027-06-01');
        $this->get('/card')->assertNotFound();
        $this->get('/')->assertDontSee('class="gc-band"', false);
        Carbon::setTestNow();
    }

    public function test_the_studio_can_preview_a_card_ahead_of_its_dates(): void
    {
        $this->project(['name' => 'Garden Villa']);
        $eid = Greeting::where('name', 'Eid al-Adha')->firstOrFail();
        Carbon::setTestNow('2027-06-01');

        $this->get('/card?greeting='.$eid->id)->assertNotFound();               // not for visitors

        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']);
        $this->actingAs($admin)->get('/card?greeting='.$eid->id)->assertOk()
            ->assertSee('Eid Mubarak')
            ->assertSee('Preview — visitors see this card only between its dates.');

        $this->actingAs($admin)->get(GreetingResource::getUrl())->assertOk()->assertSee('Newroz')->assertSee('No dates yet');
        $this->actingAs($admin)->get(GreetingResource::getUrl('edit', ['record' => $eid]))->assertOk()->assertSee('Preview the card page');
        Carbon::setTestNow();
    }

    public function test_cards_made_and_status_pictures_are_counted(): void
    {
        $browser = 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/131.0 Safari/537.36';
        $this->project(['name' => 'Stone House']);
        $this->withHeaders(['User-Agent' => $browser])->post('/t', ['type' => 'share', 'placement' => 'story', 'project' => 'stone-house'])->assertNoContent();
        $this->withHeaders(['User-Agent' => $browser])->post('/t', ['type' => 'share', 'placement' => 'card'])->assertNoContent();

        $this->assertSame(['card', 'story'], SiteEvent::query()->orderBy('placement')->pluck('placement')->all());
    }

    /* ---- Site boards ------------------------------------------------------ */

    public function test_a_site_boards_qr_code_opens_the_project_in_the_phones_language(): void
    {
        $p = $this->project(['name' => 'Garden Villa', 'status' => 'Under Construction']);

        $this->withHeaders(['Accept-Language' => 'ckb-IQ,ckb;q=0.9,ar;q=0.8'])->get('/b/'.$p->id)
            ->assertRedirect(url('/ku/projects/garden-villa').'?utm_source=board');
        $this->withHeaders(['Accept-Language' => 'en-GB,en;q=0.9'])->get('/b/'.$p->id)
            ->assertRedirect(url('/projects/garden-villa').'?utm_source=board');
        // a project since taken down: the studio's homepage (English, where the phone does not say)
        $this->get('/b/999999')->assertRedirect(url('/').'?utm_source=board');
        $this->withHeaders(['Accept-Language' => 'ar-IQ,ar;q=0.9'])->get('/b/999999')->assertRedirect(url('/ku').'?utm_source=board');

        // and the visit is counted as come from a board
        $this->assertSame('board', \App\Support\TrafficSource::classify(null, null, 'board'));
        $this->assertSame('Site boards (QR)', \App\Support\TrafficSource::label('board'));
    }

    public function test_the_studio_prints_a_site_board_with_its_qr_code(): void
    {
        $p = $this->project(['name' => 'Garden Villa', 'name_ku' => 'ڤێلای باخچە', 'num' => '07', 'status' => 'Under Construction']);
        $url = route('filament.studio-aks-panel.project-board', $p);

        $this->get($url)->assertRedirect();                                    // the studio's only

        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']);
        $this->actingAs($admin)->get($url)->assertOk()
            ->assertSee('@page { size: 420mm 594mm; margin: 0; }', false)       // A2 unless asked
            ->assertSee('<svg xmlns="http://www.w3.org/2000/svg" class="qr-svg qr"', false)
            ->assertSee(preg_replace('#^https?://#', '', url('/b/'.$p->id)))
            ->assertSee('Being built here')->assertSee('لێرە بنیات دەنرێت')
            ->assertSee('Garden Villa')->assertSee('ڤێلای باخچە')
            ->assertSee('Scan to see the design');

        $this->actingAs($admin)->get($url.'?size=a0&theme=light')->assertOk()
            ->assertSee('@page { size: 841mm 1189mm; margin: 0; }', false)
            ->assertSee('board board--light', false);

        // a finished building's board says who designed it, not that it is going up
        $done = $this->project(['name' => 'Old House', 'status' => 'Completed']);
        $this->actingAs($admin)->get(route('filament.studio-aks-panel.project-board', $done))->assertOk()
            ->assertDontSee('Being built here')->assertSee('Designed by Aram Mizuri Architecture');

        // from the project in the admin
        $this->actingAs($admin)->get(\App\Filament\Resources\Projects\ProjectResource::getUrl('edit', ['record' => $p]))
            ->assertOk()->assertSee('Site board');
    }
}
