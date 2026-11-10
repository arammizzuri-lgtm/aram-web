<?php

namespace Tests\Feature;

use App\Filament\Resources\Landmarks\LandmarkResource;
use App\Models\Landmark;
use App\Models\User;
use App\Support\HeritageImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Kurdistan's architectural heritage: the collection page, a landmark's
 * editorial page built from sections, its photographs, preview of a draft,
 * the admin, and what search engines are told.
 */
class HeritageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']);
    }

    /** A landmark whose page uses every kind of section. */
    private function landmark(array $attrs = [], array $sections = []): Landmark
    {
        return Landmark::create($attrs + [
            'name_en' => 'Test Citadel', 'name_ku' => 'قەڵای تاقیکردنەوە', 'name_ar' => 'قلعة الاختبار',
            'place_en' => 'Erbil', 'period_en' => 'c. 6000 BC', 'region' => 'bashur', 'era' => 'mesopotamia',
            'summary_en' => 'A mound of its own past.', 'intro_en' => 'Thirty metres of houses on houses.',
            'lat' => 36.19, 'lng' => 44.01, 'unesco' => true, 'unesco_year' => 2014, 'unesco_ref' => 1437, 'unesco_criteria' => '(iv)',
            'year_from' => -6000, 'hero_style' => 'editorial', 'is_published' => true, 'sort_order' => 99,
            'image' => 'https://example.test/cover.jpg',
            'sections' => $sections ?: self::everySection(),
        ]);
    }

    private static function everySection(): array
    {
        return [
            ['type' => 'text', 'data' => ['layout' => 'lead', 'heading_en' => 'The hill', 'body_en' => "First **paragraph** here.\n\n- one\n- two"]],
            ['type' => 'statement', 'data' => ['big_en' => '6,000 years', 'text_en' => 'of houses']],
            ['type' => 'stats', 'data' => ['layout' => 'row', 'items' => [['value' => '30', 'unit_en' => 'm', 'label_en' => 'Height', 'note_en' => 'estimate'], ['value' => '102,000', 'unit_en' => 'm²', 'label_en' => 'Area']]]],
            ['type' => 'facts', 'data' => ['layout' => 'cards', 'items' => [['text_en' => 'One family stayed in 2007.'], ['label_en' => 'Gates', 'text_en' => 'Three ramps lead up.']]]],
            ['type' => 'timeline', 'data' => ['layout' => 'vertical', 'events' => [['date_en' => '331 BC', 'title_en' => 'Gaugamela', 'text_en' => 'Alexander nearby.'], ['date_en' => '2014', 'title_en' => 'UNESCO']]]],
            ['type' => 'quote', 'data' => ['text_en' => 'A city on a hill made of its own past.', 'source_en' => 'The studio']],
            ['type' => 'unesco', 'data' => ['text_en' => 'For its continuity.']],
            ['type' => 'map', 'data' => ['text_en' => 'In the middle of the city.']],
            ['type' => 'related', 'data' => ['slugs' => ['gobekli-tepe']]],
            ['type' => 'sources', 'data' => ['items' => [['title' => 'UNESCO listing', 'url' => 'https://whc.unesco.org/en/list/1437/', 'note' => 'UNESCO']]]],
        ];
    }

    /* ── The collection ─────────────────────────────────────────────── */

    public function test_the_collection_page_lists_the_seeded_landmarks_in_three_languages(): void
    {
        $this->get('/kurdistan-heritage')->assertOk()->assertSee('Göbekli Tepe')->assertSee('Hawraman')->assertSee('hrMap', false);
        $this->get('/ku/kurdistan-heritage')->assertOk()->assertSee('قەڵای هەولێر');
        $this->get('/ar/kurdistan-heritage')->assertOk()->assertSee('قلعة أربيل');
    }

    public function test_every_seeded_landmark_page_renders_in_every_language(): void
    {
        $this->assertSame(21, Landmark::count());
        foreach (Landmark::publishedOrdered()->get() as $l) {
            foreach (['en', 'ku', 'ar'] as $lang) {
                $this->get($l->url($lang))->assertOk()->assertSee(e($l->tr('name', $lang)), false);
            }
        }
    }

    /* ── A landmark's page ──────────────────────────────────────────── */

    public function test_a_page_renders_every_kind_of_section(): void
    {
        $l = $this->landmark();
        $r = $this->get($l->url())->assertOk();

        $r->assertSee('lm-hero--editorial', false)
            ->assertSee('Thirty metres of houses on houses.')
            ->assertSee('<strong>paragraph</strong>', false)->assertSee('<li>one</li>', false)
            ->assertSee('lm-statement__big', false)->assertSee('6,000 years')
            ->assertSee('102,000')->assertSee('estimate')
            ->assertSee('Did you know?')->assertSee('One family stayed in 2007.')
            ->assertSee('Gaugamela')->assertSee('Alexander nearby.')
            ->assertSee('A city on a hill made of its own past.')
            ->assertSee('Inscribed on the World Heritage List in 2014')->assertSee('whc.unesco.org/en/list/1437/', false)
            ->assertSee('In the middle of the city.')->assertSee('google.com/maps/search/?api=1&query=36.19,44.01', false)
            ->assertSee('Göbekli Tepe')                        // the related landmark named
            ->assertSee('Sources &amp; further reading', false)->assertSee('UNESCO listing')
            ->assertSee('Chosen and introduced by Aram Mizuri', false)
            ->assertSee('"@type":"LandmarksOrHistoricalBuildings"', false)->assertSee('"@type":"Article"', false);
    }

    public function test_sections_fall_back_to_english_and_digits_follow_the_language(): void
    {
        $l = $this->landmark();
        $this->get($l->url('ku'))->assertOk()->assertSee('قەڵای تاقیکردنەوە')->assertSee('١٠٢,٠٠٠')->assertSee('Alexander nearby.');
        $this->get($l->url('ar'))->assertOk()->assertSee('قلعة الاختبار')->assertSee('هل تعلم؟');
    }

    public function test_every_opening_style_renders_and_a_page_without_a_photograph_opens_dark(): void
    {
        foreach (array_keys(Landmark::HERO_STYLES) as $style) {
            $l = $this->landmark(['hero_style' => $style, 'slug' => 'style-'.$style, 'image' => $style === 'dark' ? null : 'https://example.test/cover.jpg']);
            $this->get($l->url())->assertOk()->assertSee('lm-hero--'.$style, false);
        }
        $l = $this->landmark(['hero_style' => 'cinematic', 'slug' => 'no-photo', 'image' => null]);
        $this->get($l->url())->assertOk()->assertSee('lm-hero--dark', false)->assertDontSee('lm-hero--cinematic', false);
    }

    public function test_an_old_landmark_with_only_a_body_still_shows_it(): void
    {
        $l = $this->landmark(['body_en' => "Old first paragraph.\n\nOld second paragraph."], [[]]);
        $this->get($l->url())->assertOk()->assertSee('<p>Old first paragraph.</p>', false)->assertSee('Old second paragraph.');
    }

    public function test_rich_text_formats_and_escapes(): void
    {
        $html = Landmark::rich("## Small\n\nA **bold** and *soft* line <script>x</script>\nnext line\n\n- one\n- two\n\n> said so\n\n[UNESCO](https://whc.unesco.org/)");

        $this->assertStringContainsString('<h3>Small</h3>', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<em>soft</em>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString("&lt;/script&gt;<br>\nnext line", $html);
        $this->assertStringContainsString('<ul><li>one</li><li>two</li></ul>', $html);
        $this->assertStringContainsString('<blockquote>said so</blockquote>', $html);
        $this->assertStringContainsString('<a href="https://whc.unesco.org/" target="_blank" rel="noopener">UNESCO</a>', $html);
    }

    public function test_a_draft_is_hidden_but_can_be_previewed_with_its_signed_link(): void
    {
        $l = $this->landmark(['is_published' => false]);

        $this->get($l->url())->assertNotFound();
        $this->get('/kurdistan-heritage')->assertDontSee('Test Citadel');

        $this->get($l->previewUrl())->assertOk()->assertSee('Test Citadel')->assertSee('not published yet')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get(str_replace('signature=', 'signature=0', $l->previewUrl()))->assertNotFound();
    }

    public function test_related_landmarks_fall_back_to_the_same_age_and_part(): void
    {
        $l = $this->landmark([], [['type' => 'text', 'data' => ['body_en' => 'x']]]);
        $related = $l->related(3);

        $this->assertCount(3, $related);
        $this->assertTrue($related->every(fn ($r) => $r->era === 'mesopotamia'));
        $this->assertTrue($related->first()->region === 'bashur');
    }

    /* ── Photographs ────────────────────────────────────────────────── */

    public function test_photographs_are_measured_and_resized_on_save(): void
    {
        Storage::fake('public');
        $im = imagecreatetruecolor(1200, 800);
        ob_start();
        imagejpeg($im, null, 80);
        Storage::disk('public')->put('heritage/test-photo.jpg', ob_get_clean());

        $l = $this->landmark(['image' => 'heritage/test-photo.jpg'], [
            ['type' => 'image', 'data' => ['layout' => 'wide', 'file' => 'heritage/test-photo.jpg', 'caption_en' => 'The wall', 'credit' => 'Someone, CC BY 4.0']],
            ['type' => 'gallery', 'data' => ['layout' => 'collage', 'images' => [['file' => 'heritage/test-photo.jpg', 'alt_en' => 'Alt text']]]],
        ]);

        $data = $l->fresh()->sections()[0]['data'];
        $this->assertSame([1200, 800], [$data['w'], $data['h']]);
        $this->assertSame(1200, $l->fresh()->sections()[1]['data']['images'][0]['w']);
        Storage::disk('public')->assertExists('heritage/_v/sm/test-photo.webp');
        Storage::disk('public')->assertExists('heritage/_v/md/test-photo.webp');
        $this->assertSame('/storage/heritage/_v/sm/test-photo.webp', HeritageImage::url('heritage/test-photo.jpg', 'sm'));
        $this->assertStringContainsString('480w', HeritageImage::srcset('heritage/test-photo.jpg', 1200));

        $this->get($l->url())->assertOk()
            ->assertSee('width="1200" height="800"', false)
            ->assertSee('data-lightbox', false)->assertSee('The wall')->assertSee('Someone, CC BY 4.0')->assertSee('alt="Alt text"', false)
            ->assertSee('"@type":"ImageObject"', false);
        $this->assertCount(3, $l->images());        // the cover and the two photographs
    }

    /* ── Search engines ─────────────────────────────────────────────── */

    public function test_the_sitemap_and_llms_txt_carry_the_landmarks(): void
    {
        $l = $this->landmark(['image' => 'https://example.test/cover.jpg']);
        $this->get('/sitemap.xml')->assertOk()->assertSee($l->url('en'), false)->assertSee('https://example.test/cover.jpg', false);
        $this->get('/llms.txt')->assertOk()->assertSee('Test Citadel')->assertSee('UNESCO World Heritage Site');
    }

    /* ── The admin ──────────────────────────────────────────────────── */

    public function test_the_admin_pages_render(): void
    {
        $admin = $this->admin();
        $l = $this->landmark();

        $this->actingAs($admin)->get(LandmarkResource::getUrl('index'))->assertOk()->assertSee('Göbekli Tepe')->assertSee('Duplicate');
        $this->actingAs($admin)->get(LandmarkResource::getUrl('create'))->assertOk()->assertSee('Add a section');
        $this->actingAs($admin)->get(LandmarkResource::getUrl('edit', ['record' => $l]))->assertOk()
            ->assertSee('Preview')->assertSee('The hill')->assertSee('Cover photograph');
    }
}
