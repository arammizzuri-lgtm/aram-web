<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ClientRoom;
use App\Models\Project;
use App\Models\SiteEvent;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Finding one's way round the site: the menu's "Projects" opening every
 * project; the same menu on every other page; a page-not-found that leads
 * somewhere; services, materials and statuses that each open the projects
 * they stand for; and the loader that plays once a visit.
 */
class ExploringTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/131.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        Category::updateOrCreate(['key' => 'residential'], ['name' => 'Residential', 'name_ku' => 'نیشتەجێبوون', 'sort_order' => 1]);
        Category::updateOrCreate(['key' => 'interior-design'], ['name' => 'Interior Design', 'name_ku' => 'دیزاینی ناوەوە', 'sort_order' => 2]);
        Category::updateOrCreate(['key' => 'exterior-design'], ['name' => 'Exterior Design', 'name_ku' => 'دیزاینی دەرەوە', 'sort_order' => 3]);

        $this->project(['name' => 'Stone House', 'categories' => ['residential', 'exterior-design'], 'neighbourhood' => 'Future City',
            'location' => 'Future City, Erbil, Kurdistan', 'materials' => ['Basalt Stone', 'Walnut Joinery'], 'year' => '2024']);
        $this->project(['name' => 'Garden Villa', 'categories' => ['residential', 'interior-design'], 'status' => 'Under Construction',
            'materials' => ['Basalt Stone Floor', 'Lime Render'], 'year' => '2025']);
        $this->project(['name' => 'Glass Office', 'categories' => ['interior-design'], 'status' => 'Concept / Planning', 'materials' => ['Glass']]);
        $this->project(['name' => 'Hidden Draft', 'categories' => ['interior-design'], 'is_published' => false, 'materials' => ['Glass']]);
    }

    private function project(array $attrs): Project
    {
        return Project::create($attrs + [
            'category' => $attrs['categories'][0] ?? 'residential', 'status' => 'Completed', 'size' => 'default',
            'imgs' => ['https://example.com/'.uniqid().'.jpg'], 'is_published' => true,
        ]);
    }

    /* ---- 1. The menu's "Projects" opens every project -------------------- */

    public function test_the_homepage_menu_opens_the_projects_page_in_either_language(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(url('/projects'), '#').'" class="nav__link"\s+data-href-en="'.preg_quote(url('/projects'), '#').'" data-href-ku="'.preg_quote(url('/ku/projects'), '#').'"#', $html);
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(url('/projects'), '#').'" class="mobile-menu__link"#', $html);
        $this->assertStringNotContainsString('<a href="#projects" class="nav__link"', $html);

        $this->get('/ku')->assertOk()->assertSee('<a href="'.url('/ku/projects').'" class="nav__link"', false);
    }

    /* ---- 5. The same menu on every other page ----------------------------- */

    public function test_every_other_page_carries_the_site_menu_with_its_own_place_marked(): void
    {
        $html = $this->get('/projects/stone-house')->assertOk()
            ->assertSee('site-menu.js')
            ->assertSee('<a class="pp-menu__link" href="'.url('/projects').'" aria-current="page">Projects</a>', false)
            ->assertSee('<a class="pp-menu__link" href="'.url('/').'#contact">Contact</a>', false)
            ->assertSee('<a class="pp-menu__link" href="'.url('/estimate').'">Cost estimate</a>', false)
            ->getContent();

        // the folded menu on a phone has Home and Careers too, numbered as sheets
        $this->assertMatchesRegularExpression('#<span class="pp-sheet__num">01</span>\s*<span class="pp-sheet__label">Home</span>#', $html);
        $this->assertMatchesRegularExpression('#<span class="pp-sheet__num">09</span>\s*<span class="pp-sheet__label">Careers</span>#', $html);

        $this->get('/ku/estimate')->assertOk()
            ->assertSee('<a class="pp-menu__link" href="'.url('/ku/estimate').'" aria-current="page">', false)
            ->assertSee('<a class="pp-menu__link" href="'.url('/ku').'#about">دەربارەی</a>', false);

        // the estimator switched off leaves the menu too
        SiteSetting::updateOrCreate(['key' => 'est_enabled'], ['value' => '0']);
        $this->get('/careers')->assertOk()
            ->assertSee('<a class="pp-sheet__link" href="'.url('/careers').'" aria-current="page">', false)
            ->assertDontSee('href="'.url('/estimate').'"', false);
    }

    public function test_a_clients_room_keeps_its_single_way_back_and_no_menu(): void
    {
        $room = ClientRoom::create(['project_id' => Project::first()->id, 'client_name' => 'Shilan', 'client_phone' => '0750 123 4567', 'lang' => 'en']);

        // before the PIN, and after it
        $this->get('/room/'.$room->token)->assertOk()
            ->assertDontSee('pp-menu__link', false)
            ->assertDontSee('site-menu.js')
            ->assertSee('pp-arrow--back', false);
        $in = $this->post('/room/'.$room->token.'/pin', ['pin' => $room->pin])->assertRedirect();
        $this->withCookie($room->accessCookie(), $in->getCookie($room->accessCookie())->getValue())
            ->get('/room/'.$room->token)->assertOk()
            ->assertSee('Shilan')
            ->assertDontSee('pp-menu__link', false)
            ->assertSee('pp-arrow--back', false);
    }

    /* ---- 2. A page not found leads somewhere ------------------------------ */

    public function test_an_address_that_leads_nowhere_offers_a_search_projects_and_the_way_home(): void
    {
        $this->get('/no-such-page')->assertNotFound()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('This sheet is missing from the set.')
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertSee('<form class="nf-find" action="'.url('/projects').'" method="get" role="search">', false)
            ->assertSee(url('/projects/stone-house'), false)
            ->assertDontSee('Hidden Draft')
            // nothing marked as the page the visitor is on
            ->assertDontSee('aria-current="page"', false);

        // a project taken down since it was shared, in Kurdish
        $this->get('/ku/projects/hidden-draft')->assertNotFound()
            ->assertSee('<html lang="ku" dir="rtl">', false)
            ->assertSee('ئەم پەڕەیە لەناو نەخشەکاندا نییە.')
            ->assertSee(url('/ku/projects/stone-house'), false)
            ->assertSee('<a class="pp-lang" href="'.url('/').'"', false);

        // asked for JSON, it is still answered in JSON
        $this->getJson('/no-such-page')->assertNotFound()->assertJsonStructure(['message']);
    }

    /* ---- 3. Services open the projects they stand for --------------------- */

    public function test_each_service_opens_its_projects_or_the_contact_form(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $card = fn (string $href, string $label) => '#<a class="service[^"]*" href="'.preg_quote($href, '#').'"[^>]*>.*?<span data-en="'.preg_quote($label, '#').'"#s';
        $this->assertMatchesRegularExpression($card(url('/projects'), 'See 3 projects'), $html);
        $this->assertMatchesRegularExpression($card(url('/projects?type=exterior-design'), 'See the project'), $html);
        $this->assertMatchesRegularExpression($card(url('/projects?type=interior-design'), 'See 2 projects'), $html);
        $this->assertMatchesRegularExpression($card(url('/projects?status=under-construction'), 'See the project'), $html);
        // Tendering and the furniture have nothing to show: they ask
        $this->assertSame(3, preg_match_all('#<a class="service[^"]*" href="\#contact"#', $html));
        $this->assertStringContainsString('data-ku="بینینی 2 پرۆژە"', $html);
    }

    /* ---- 4. A material shared by others opens them all -------------------- */

    public function test_a_material_other_projects_share_opens_them_on_the_projects_page(): void
    {
        $html = $this->get('/projects/stone-house')->assertOk()->getContent();

        // "basalt" and "stone" are in the Garden Villa's floor too: two projects
        $this->assertStringContainsString('<a class="pp-chip-link" href="'.url('/projects').'?q=Basalt+Stone" title="See all 2 projects built with it">', $html);
        $this->assertDoesNotMatchRegularExpression('#<a class="pp-chip-link" href="[^"]*Walnut#', $html);
        $this->assertStringContainsString('<bdi>Walnut Joinery</bdi>', $html);

        // in Kurdish, to the Kurdish projects page; a draft never counts
        $this->get('/ku/projects/glass-office')->assertOk()
            ->assertDontSee('pp-chip-link', false)
            ->assertSee('<bdi>Glass</bdi>', false);
    }

    /* ---- 7. The projects page: by status, and searched from the address --- */

    public function test_the_projects_page_filters_by_status_with_each_count(): void
    {
        $html = $this->get('/projects?status=under-construction')->assertOk()->assertSee('1 project')->getContent();

        $this->assertMatchesRegularExpression('#class="px-key px-key--st px-st--build is-on" href="[^"]*status=under-construction" data-status="under-construction"\s+aria-current="true"\s*>\s*<bdi>Under Construction</bdi><sup>1</sup>#', $html);
        $this->assertMatchesRegularExpression('#data-status="completed"[^>]*hidden#', $html);
        $this->assertDoesNotMatchRegularExpression('#data-status="under-construction" data-search[^>]*hidden#', $html);
        // a category picked keeps the status, and the other language keeps both
        $this->assertStringContainsString('href="'.url('/projects').'?type=residential&amp;status=under-construction"', $html);
        $this->assertStringContainsString('<a class="pp-lang" href="'.url('/ku/projects').'?status=under-construction"', $html);

        // the statuses in Kurdish; one that does not exist shows everything
        $this->get('/ku/projects')->assertOk()->assertSee('<bdi>لەژێر بنیاتنان</bdi>', false);
        $this->get('/projects?status=demolished')->assertOk()->assertSee('3 projects');
    }

    public function test_the_projects_page_is_searched_from_the_address(): void
    {
        // a neighbourhood, a material, a status — and Kurdish digits
        $this->get('/projects?q=future+city')->assertOk()->assertSee('1 project')->assertSee('value="future city"', false);
        $this->get('/projects?q=lime+render')->assertOk()->assertSee('1 project');
        $this->get('/projects?q=construction')->assertOk()->assertSee('1 project');
        $this->get('/projects?q=٢٠٢٥')->assertOk()->assertSee('1 project');
        $this->get('/projects?q=nothing+like+this')->assertOk()->assertSee('0 projects')
            ->assertSee('<div class="px-empty" id="pxEmpty" >', false);

        // with a category too, and kept by the views
        $html = $this->get('/projects?type=residential&q=stone')->assertOk()->assertSee('2 projects')->getContent();
        $this->assertStringContainsString('href="'.url('/projects').'?type=residential&amp;q=stone&amp;view=sheets"', $html);
    }

    /* ---- 6. The loader, once a visit -------------------------------------- */

    public function test_the_homepage_hides_its_loader_for_a_visitor_back_within_the_half_hour(): void
    {
        $this->get('/')->assertOk()
            ->assertSee("localStorage.getItem('am_loader_seen')", false)
            ->assertSee("document.documentElement.classList.add('am-seen')", false);

        $this->assertStringContainsString('.am-seen .loader { display: none; }', file_get_contents(public_path('style.css')));
        $this->assertStringContainsString("localStorage.setItem(SEEN_KEY, String(Date.now()))", file_get_contents(public_path('script.js')));
    }

    /* ---- Counting the taps where they now happen -------------------------- */

    public function test_taps_on_the_projects_page_the_menu_and_the_not_found_page_are_counted(): void
    {
        foreach (['projects', 'menu', 'not-found'] as $at) {
            $this->withHeaders(['User-Agent' => self::BROWSER])
                ->post('/t', ['type' => 'whatsapp_click', 'placement' => $at])->assertNoContent();
        }
        $this->withHeaders(['User-Agent' => self::BROWSER])
            ->post('/t', ['type' => 'save', 'placement' => 'projects', 'project' => 'stone-house'])->assertNoContent();

        $this->assertSame(['menu', 'not-found', 'projects', 'projects'], SiteEvent::query()->orderBy('placement')->pluck('placement')->all());
    }
}
