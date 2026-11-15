<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The homepage's sections, each a sheet of its own: /map, /services,
 * /process and /contact — in every language, marked in the index, and
 * leading on to the sheets before and after them.
 */
class SheetPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_section_has_a_page_of_its_own_in_every_language(): void
    {
        $sheets = ['map' => 'A-02', 'services' => 'A-04', 'process' => 'A-05', 'contact' => 'A-09'];
        foreach ($sheets as $sheet => $code) {
            foreach (['', '/ku', '/ar'] as $lang) {
                $html = $this->get($lang.'/'.$sheet)->assertOk()->getContent();
                $this->assertStringContainsString('<span class="sheet-hero__code" dir="ltr">'.$code.'</span>', $html, $lang.'/'.$sheet);
                // the index marks this sheet as the page the visitor is on
                $this->assertStringContainsString('<a class="sx__link" href="'.url($lang.'/'.$sheet).'" data-sheet="'.$sheet.'" aria-current="page">', $html, $lang.'/'.$sheet);
            }
        }

        // the contact page carries the form; the next sheet after it is home again
        $this->get('/contact')->assertSee('id="contactForm"', false)
            ->assertSee('<a class="sheet-next__link sheet-next__link--next" href="'.url('/').'" rel="next">', false);
        $this->get('/process')->assertSee('<a class="sheet-next__link sheet-next__link--prev" href="'.url('/services').'" rel="prev">', false);

        // a sent message is stamped, in the page's language (script.js fills in the day and the sheet)
        $this->get('/contact')->assertSee('<span class="form-stamp__word" dir="auto" data-en="Received" data-ku="وەرگیرا">Received</span>', false);
        $this->get('/ar/contact')->assertSee('>وارد</span>', false);
    }

    public function test_the_map_lists_its_projects_place_by_place_and_finds_them_on_the_map(): void
    {
        Category::updateOrCreate(['key' => 'residential'], ['name' => 'Residential', 'sort_order' => 1]);
        $base = ['category' => 'residential', 'categories' => ['residential'], 'status' => 'Completed', 'size' => 'default',
            'imgs' => ['https://example.com/a.jpg'], 'is_published' => true, 'city' => 'Erbil', 'location' => 'Erbil, Kurdistan'];
        Project::create(['name' => 'Pinned House', 'lat' => 36.19, 'lng' => 44.01] + $base);
        Project::create(['name' => 'Unpinned House'] + $base);

        $html = $this->get('/map')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<button type="button" class="sheet-chip" data-pin="\d+">\s*<span class="sheet-chip__num" dir="ltr">[^<]*</span><span dir="auto">Pinned House</span>#', $html);
        // without a pin, the chip opens the project's own page instead
        $this->assertMatchesRegularExpression('#<a class="sheet-chip" href="[^"]+/projects/[^"]+">\s*<span class="sheet-chip__num" dir="ltr">[^<]*</span><span dir="auto">Unpinned House</span>#', $html);
    }

    public function test_a_service_with_nothing_to_show_asks_on_the_contact_page(): void
    {
        // no projects at all: every service asks rather than links to an empty list
        $html = $this->get('/services')->assertOk()->getContent();
        $this->assertSame(7, substr_count($html, 'class="svc-ch__go" href="'.url('/contact').'"'));
        // and the chapters' index beside them, one link to each
        $this->assertSame(7, preg_match_all('#<a class="svc-toc__link" href="\#service-\d">#', $html));
    }

    public function test_the_homepage_sections_lead_to_their_own_pages(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        foreach (['map' => 'A-02', 'services' => 'A-04', 'process' => 'A-05', 'contact' => 'A-09'] as $sheet => $code) {
            $this->assertMatchesRegularExpression('#<a class="sheet-ref" href="'.preg_quote(url('/'.$sheet), '#').'" aria-label="[^"]+ — See the full sheet">\s*<span class="sheet-ref__mark" aria-hidden="true"><span class="sheet-ref__arrow">→</span><span class="sheet-ref__code" dir="ltr">'.$code.'</span>#', $html, $sheet);
        }
        $this->get('/ku')->assertSee('<a class="sheet-ref" href="'.url('/ku/map').'"', false);

        // a page of its own has the drawing behind its title — a house of the
        // Erbil Citadel, lit by the sun now (sheet-plan.js) — and no link to itself
        $this->get('/process')->assertSee('class="sheet-plan"', false)->assertSee('CITADEL HOUSE, HAWLÊR', false)
            ->assertSee('/sheet-plan.js?v=', false)->assertDontSee('class="sheet-ref"', false);

        // the selection box and the measure (cad.js) wherever the index is: home, the sheets, the other pages
        foreach (['/', '/process', '/projects', '/ar/careers'] as $path) {
            $this->get($path)->assertOk()->assertSee('/cad.js?v=', false);
        }
    }
}
