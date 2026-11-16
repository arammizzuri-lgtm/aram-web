<?php

namespace Tests\Feature;

use App\Filament\Resources\Essays\EssayResource;
use App\Filament\Resources\Essays\Pages\CreateEssay;
use App\Models\Essay;
use App\Models\User;
use App\Support\Articles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    public function test_the_essay_has_a_link_preview_in_each_language_and_prints_its_plates_one_to_a_sheet(): void
    {
        foreach (['' => 'en', '/ku' => 'ku', '/ar' => 'ar'] as $prefix => $lang) {
            $html = $this->get($prefix.'/journal/citadel-house-plans')->assertOk()
                ->assertSee('<meta property="og:image" content="'.url('/images/journal/citadel-house-plans-'.$lang.'.jpg'), false)
                ->assertSee('data-print-plates', false)->getContent();
            // each printed sheet carries its title strip, numbered n / 8
            $this->assertSame(8, substr_count($html, '<p class="ja-plate__foot">'));
            $this->assertStringContainsString('· 8 / 8</span>', $html);
        }
        $this->assertNull(Articles::image(['slug' => 'no-such-essay'], 'en'));
    }

    /** An essay written in the admin, its page composed of the heritage sections. */
    private function essay(array $attrs = []): Essay
    {
        return Essay::create($attrs + [
            'date' => '2026-11-20', 'title_en' => 'Building with earth', 'title_ku' => 'بیناسازی بە گڵ',
            'summary_en' => 'Why mud brick still works in Erbil.', 'is_published' => true,
            'sections' => [
                ['type' => 'text', 'data' => ['layout' => 'standard', 'heading_en' => 'The wall', 'body_en' => "A wall of **mud brick**.\n\n- Cheap\n- Cool"]],
                ['type' => 'quote', 'data' => ['text_en' => 'Earth breathes.', 'source_en' => 'A mason']],
                ['type' => 'map', 'data' => []],   // a kind essays do not have: left out
            ],
        ]);
    }

    public function test_an_essay_from_the_admin_is_listed_with_the_others_and_has_its_page(): void
    {
        $e = $this->essay();
        $this->assertSame('building-with-earth', $e->slug);
        $this->assertSame(['text', 'quote'], array_column($e->sections(), 'type'));

        $this->get('/journal')->assertOk()->assertSee('Building with earth')->assertSee('href="'.url('/journal/building-with-earth').'"', false)
            ->assertSee('The houses of the Citadel, in plan, section and elevation');
        $this->get('/journal/building-with-earth')->assertOk()->assertSee('Why mud brick still works in Erbil.')
            ->assertSee('<strong>mud brick</strong>', false)->assertSee('<li>Cool</li>', false)->assertSee('Earth breathes.')
            ->assertDontSee('lm-map', false);
        // the Kurdish title where written, the English where not
        $this->get('/ku/journal/building-with-earth')->assertOk()->assertSee('بیناسازی بە گڵ');
        $this->get('/ar/journal/building-with-earth')->assertOk()->assertSee('Building with earth');

        $this->get('/sitemap.xml')->assertOk()->assertSee(url('/ku/journal/building-with-earth'), false);
        $this->get('/llms.txt')->assertOk()->assertSee('[Building with earth]('.url('/journal/building-with-earth').')', false);
    }

    public function test_a_draft_essay_is_hidden_but_can_be_previewed_and_no_two_journal_pages_share_an_address(): void
    {
        $e = $this->essay(['is_published' => false]);
        $this->get('/journal')->assertDontSee('Building with earth');
        $this->get($e->url())->assertNotFound();
        $this->get($e->previewUrl('ku'))->assertOk()->assertSee('بیناسازی بە گڵ')->assertSee('هێشتا بڵاونەکراوەتەوە')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        // the code essay keeps its address: one written in the admin under the same title gets another
        $this->assertSame('building-with-earth-2', $this->essay()->slug);
        $this->assertFalse(Essay::slugIsFree('citadel-house-plans'));
        $this->assertTrue(Essay::slugIsFree('building-with-earth', $e->id));
    }

    public function test_the_admin_writes_essays(): void
    {
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']));
        $e = $this->essay();

        $this->get(EssayResource::getUrl('index'))->assertOk()->assertSee('Building with earth');
        $this->get(EssayResource::getUrl('create'))->assertOk()->assertSee('Cover photograph')->assertSee('Add a section');
        $this->get(EssayResource::getUrl('edit', ['record' => $e]))->assertOk()->assertSee('Preview')->assertSee('The wall');

        Livewire::test(CreateEssay::class)
            ->fillForm(['title_en' => 'On the tarma', 'date' => '2026-11-21', 'slug' => 'citadel-house-plans'])
            ->call('create')->assertHasFormErrors(['slug']);
        Livewire::test(CreateEssay::class)
            ->fillForm(['title_en' => 'On the tarma', 'date' => '2026-11-21', 'is_published' => true])
            ->call('create')->assertHasNoFormErrors();
        $this->get('/journal/on-the-tarma')->assertOk()->assertSee('On the tarma');
    }

    public function test_the_qaysari_essay_has_its_plates_in_every_language_and_says_what_its_drawings_are(): void
    {
        $this->get('/journal')->assertOk()
            ->assertSee('href="'.url('/journal/qaysari-bazaar').'"', false)
            ->assertSee('2 PLANS · 2 SECTIONS · 3 ELEVATIONS')->assertSee('QB-01 — QB-03')
            ->assertSee('12 PLANS · 5 SECTIONS · 1 ELEVATION');   // each card its own title block

        foreach (['' => 'The Qaysari: Erbil’s covered bazaar, in plan and section', '/ku' => 'قەیسەری: بازاڕە سەرداپۆشراوەکەی هەولێر، لە نەخشە و بڕگەدا', '/ar' => 'القيصرية: سوق أربيل المسقوف، مسقطًا ومقطعًا'] as $lang => $title) {
            $html = $this->get($lang.'/journal/qaysari-bazaar')->assertOk()->assertSee($title)->getContent();
            // three plates: a stretch of passage (two plans, two sections), one bay before and after, a gate
            $this->assertSame(3, substr_count($html, '<article class="ja-plate"'), $lang);
            $this->assertSame(7, substr_count($html, '<svg class="ja-dwg"'), $lang);
            $this->assertSame(3, substr_count($html, '<p class="ja-plate__foot">'), $lang);
            $this->assertStringContainsString('<span class="ja-plate__code" dir="ltr">QB-03</span>', $html);
            // drawn from a description, not traced from the survey, whose licence does not allow it: the page says so and links it
            $this->assertStringContainsString('https://doi.org/10.34780/0r26-1i7a', $html);
            $this->assertStringContainsString('https://doi.org/10.48494/REALCORP2026.1081', $html);
            $this->assertStringContainsString('data-print-plates', $html);
        }
        $this->get('/journal/qaysari-bazaar')->assertSee('A word on the drawings.')->assertSee('SHOP')->assertSee('PASSAGE')
            ->assertSee('<meta property="og:image" content="'.url('/images/journal/qaysari-bazaar-en.jpg'), false);
        $this->get('/ku/journal/qaysari-bazaar')->assertSee('دوکان');   // the drawings' words in the page's language
        $this->get('/sitemap.xml')->assertOk()->assertSee(url('/ar/journal/qaysari-bazaar'), false);
    }

    public function test_every_drawing_an_essay_shows_has_its_walls(): void
    {
        $counts = ['citadel-house-plans' => 18, 'qaysari-bazaar' => 7];
        foreach (Articles::all() as $article) {
            $dwgs = Articles::drawings($article);
            $this->assertCount($counts[$article['slug']], $dwgs, $article['slug']);
            foreach ($dwgs as $key => $dwg) {
                $this->assertStringStartsWith('M', $dwg['d']['wall'], $key);
                // the box round what is built, which the journal card frames by
                $this->assertCount(4, $dwg['bb'], $key);
                $this->assertGreaterThan(0, $dwg['bb'][2] * $dwg['bb'][3], $key);
            }
            foreach ($article['cover'] as $key) {
                $this->assertArrayHasKey($key, $dwgs);
            }
            $this->assertTrue(view()->exists($article['view']));
            $this->assertNotNull(Articles::image($article, 'ar'), $article['slug']);
        }
    }
}
