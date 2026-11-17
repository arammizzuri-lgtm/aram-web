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
            ->assertSee('1 SITE · 2 PLANS · 2 SECTIONS · 3 ELEVATIONS')->assertSee('QB-01 — QB-04')
            ->assertSee('12 PLANS · 5 SECTIONS · 1 ELEVATION');   // each card its own title block

        foreach (['' => 'The Qaysari: Erbil’s covered bazaar, in plan and section', '/ku' => 'قەیسەری: بازاڕە سەرداپۆشراوەکەی هەولێر، لە نەخشە و بڕگەدا', '/ar' => 'القيصرية: سوق أربيل المسقوف، مسقطًا ومقطعًا'] as $lang => $title) {
            $html = $this->get($lang.'/journal/qaysari-bazaar')->assertOk()->assertSee($title)->getContent();
            // four plates: the site, a stretch of passage (two plans, two sections), one bay before and after, a gate
            $this->assertSame(4, substr_count($html, '<article class="ja-plate"'), $lang);
            $this->assertSame(8, substr_count($html, '<svg class="ja-dwg"'), $lang);
            $this->assertSame(4, substr_count($html, '<p class="ja-plate__foot">'), $lang);
            $this->assertStringContainsString('<span class="ja-plate__code" dir="ltr">QB-04</span>', $html);
            // the site plan is OpenStreetMap's, and says so on the drawing and in the sources
            $this->assertSame(2, substr_count($html, '© OpenStreetMap contributors'), $lang);
            $this->assertStringContainsString('https://www.openstreetmap.org/copyright', $html);
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

    public function test_the_journal_has_a_feed_in_every_language_with_its_entries_newest_first(): void
    {
        $project = \App\Models\Project::create(['name' => 'Erbil Tower', 'category' => 'commercial', 'status' => 'Under Construction',
            'size' => 'default', 'imgs' => ['https://example.com/cover.jpg'], 'is_published' => true]);
        $project->updates()->create(['date' => '2026-11-30', 'title_en' => 'Roof slab cast', 'note_en' => 'The last pour & the first rain.', 'in_journal' => true]);
        $this->essay(['title_en' => 'A draft on lime', 'is_published' => false]);
        $this->essay();   // Building with earth, 2026-11-20

        $this->get('/journal')->assertSee('<link rel="alternate" type="application/atom+xml"', false)->assertSee(url('/journal/feed'), false);
        $res = $this->get('/journal/feed')->assertOk()->assertHeader('Content-Type', 'application/atom+xml; charset=UTF-8');
        $feed = simplexml_load_string($res->getContent());
        $this->assertNotFalse($feed);
        $titles = array_map(fn ($e) => (string) $e->title, iterator_to_array($feed->entry, false));
        $this->assertSame(['Roof slab cast', 'Building with earth', 'The Qaysari: Erbil’s covered bazaar, in plan and section',
            'The houses of the Citadel, in plan, section and elevation'], $titles);   // the draft left out
        $this->assertSame('The last pour & the first rain.', (string) $feed->entry[0]->summary);
        $this->assertSame('2026-11-30T00:00:00+00:00', (string) $feed->updated);

        $ar = simplexml_load_string($this->get('/ar/journal/feed')->assertOk()->getContent());
        $this->assertSame(url('/ar/journal/qaysari-bazaar'), (string) $ar->entry[2]->link['href']);
        $this->assertSame('القيصرية: سوق أربيل المسقوف، مسقطًا ومقطعًا', (string) $ar->entry[2]->title);

        $this->assertFalse(Essay::slugIsFree('feed'));
    }

    public function test_every_drawing_an_essay_shows_has_its_walls(): void
    {
        $counts = ['citadel-house-plans' => 18, 'qaysari-bazaar' => 8];
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

    public function test_the_glossary_draws_every_word_in_three_languages_and_the_essays_link_to_it(): void
    {
        $words = ['hawsh', 'iwan', 'tarma', 'diwakhana', 'sirdab', 'sarban', 'khisht', 'taq', 'mosul-alabaster', 'qaysari', 'khan', 'kahrez'];
        $scenes = require resource_path('journal/glossary.php');
        $lit = collect($scenes)->flatMap(fn ($s) => array_keys($s['parts']));
        $this->assertGreaterThanOrEqual(50, $lit->count());
        $this->assertSame($lit->count(), $lit->unique()->count(), 'a word id is used twice');
        $this->assertEmpty($lit->intersect($words)->all(), 'a building word takes the anchor of a house word');
        foreach (['' => 'A dictionary of Kurdish architecture', '/ku' => 'فەرهەنگی تەلارسازی کوردی', '/ar' => 'معجم العمارة الكوردية'] as $lang => $title) {
            $html = $this->get($lang.'/journal/glossary')->assertOk()->assertSee($title)->getContent();
            foreach ($words as $id) {
                $this->assertStringContainsString('<article class="jg-term" id="'.$id.'">', $html, $lang.' '.$id);
            }
            // each building word on its own card, its part of the scene lit and its Kurdish name on it
            foreach ($scenes as $sc) {
                foreach ($sc['parts'] as $id => $p) {
                    $this->assertStringContainsString('<article class="jg-word" id="'.$id.'"', $html, $lang.' '.$id);
                    $this->assertStringContainsString('lang="ckb">'.$p['w'][1].'</text>', $html, $lang.' '.$id);
                }
            }
            $this->assertSame($lit->count(), substr_count($html, '<svg class="ja-dwg jg-ill"'), $lang);
            // a drawing for each house word, the game, and every word for search engines as a set of defined terms
            $this->assertSame(12, substr_count($html, '<svg class="ja-dwg ja-type"'), $lang);
            $this->assertStringContainsString('data-quiz hidden', $html);
            $this->assertStringContainsString('"@type":"DefinedTermSet"', $html);
            $this->assertSame($lit->count() + 12, substr_count($html, '"@type":"DefinedTerm"'), $lang);
        }
        // each word points to where it is seen: an essay or a landmark
        $this->get('/journal/glossary')->assertSee('href="'.url('/kurdistan-heritage/taq-e-bostan').'"', false)
            ->assertSee('href="'.url('/journal/qaysari-bazaar').'"', false);

        // the essays link their words to it, and the journal and the sitemap point to it
        $this->get('/journal/citadel-house-plans')->assertSee(url('/journal/glossary').'#iwan', false)->assertSee(url('/journal/glossary').'#diwakhana', false);
        $this->get('/ar/journal/qaysari-bazaar')->assertSee(url('/ar/journal/glossary').'#qaysari', false);
        // pinned on the journal: the house in section, each of its parts named in turn
        $journal = $this->get('/journal')->assertSee('href="'.url('/journal/glossary').'"', false)->assertSee('class="jr-pin"', false)->getContent();
        $this->assertSame(count($scenes['section']['parts']), substr_count($journal, 'class="jp-step"'));
        $this->get('/sitemap.xml')->assertSee(url('/ku/journal/glossary'), false);
        $this->get('/llms.txt')->assertSee(url('/journal/glossary'), false);
        // and no essay written in the admin can take its address
        $this->assertFalse(Essay::slugIsFree('glossary'));
    }
}
