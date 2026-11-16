<?php

namespace Tests\Feature;

use App\Support\Articles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The journal's essays (App\Support\Articles): listed on /journal among the
 * build entries, each a page of its own in the three languages — the first,
 * the houses of Erbil Citadel, with its plates of redrawn plans.
 */
class JournalEssaysTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_journal_lists_an_essay_and_gives_it_a_page_in_every_language(): void
    {
        $article = Articles::find('citadel-house-plans');
        $this->assertNotNull($article);

        // on the journal, with no build entry published, the essay is the lead card, its cover a sheet of its drawings
        $this->get('/journal')->assertOk()
            ->assertDontSee('The first entries are on their way.')
            ->assertSee('<a class="jr-card" href="'.url('/journal/citadel-house-plans').'">', false)
            ->assertSee('<span class="jr-card__img jr-card__img--plan">', false)
            ->assertSee('<svg class="jr-cover"', false)->assertSee('12 PLANS · 5 SECTIONS · 1 ELEVATION')
            ->assertSee('The houses of the Citadel, in plan, section and elevation');
        $this->get('/ku/journal')->assertOk()->assertSee('href="'.url('/ku/journal/citadel-house-plans').'"', false);

        foreach (['' => 'The houses of the Citadel, in plan, section and elevation', '/ku' => 'خانووەکانی قەڵا، لە نەخشە و بڕگە و ڕووکاردا', '/ar' => 'بيوت القلعة، مسقطًا ومقطعًا وواجهة'] as $lang => $title) {
            $html = $this->get($lang.'/journal/citadel-house-plans')->assertOk()->assertSee($title)->getContent();
            // eight plates: seven houses with eleven floor plans between them, and the block: twelve plans,
            // and a long and a cross section (or courtyard elevation) for three of the houses: eighteen drawings
            $this->assertSame(8, substr_count($html, '<article class="ja-plate"'), $lang);
            $this->assertSame(18, substr_count($html, '<svg class="ja-dwg"'), $lang);
            $this->assertStringContainsString('<span class="ja-plate__code" dir="ltr">CH-08</span>', $html);
            // every plate names what it was drawn from, and the sources close the page
            $this->assertStringContainsString('https://doi.org/10.1016/j.asej.2024.103003', $html);
            $this->assertStringContainsString('https://whc.unesco.org/en/list/1437/documents/', $html);
        }

        // an unknown entry is still not found, and the essay is in the sitemap
        $this->get('/journal/no-such-entry')->assertNotFound();
        $this->get('/sitemap.xml')->assertOk()->assertSee(url('/ar/journal/citadel-house-plans'), false);
    }

    public function test_every_drawing_an_essay_shows_has_its_walls(): void
    {
        $dwgs = json_decode(file_get_contents(resource_path('journal/citadel-drawings.json')), true);
        $this->assertCount(18, $dwgs);
        foreach ($dwgs as $key => $dwg) {
            $this->assertStringStartsWith('M', $dwg['d']['wall'], $key);
            // the box round what is built, which the journal card frames by
            $this->assertCount(4, $dwg['bb'], $key);
            $this->assertGreaterThan(0, $dwg['bb'][2] * $dwg['bb'][3], $key);
        }
        foreach (Articles::all() as $article) {
            foreach ($article['cover'] as $key) {
                $this->assertArrayHasKey($key, $dwgs);
            }
            $this->assertTrue(view()->exists($article['view']));
        }
    }
}
