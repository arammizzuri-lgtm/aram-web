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
        $this->get($l->url('ku'))->assertOk()->assertSee('قەڵای تاقیکردنەوە')->assertSee('١٠٢٬٠٠٠')->assertSee('Alexander nearby.');
        $this->get($l->url('ar'))->assertOk()->assertSee('قلعة الاختبار')->assertSee('هل تعلم؟');
    }

    public function test_text_runs_the_way_its_script_does_and_dates_follow_the_page(): void
    {
        // a year or a span of years has no letters: it follows the page
        $this->assertSame('', (string) text_dir('١٦٨٥–١٧٨٤', 'ku'));
        $this->assertSame('', (string) text_dir('1685–1784', 'ar'));
        $this->assertSame('', (string) text_dir('قەڵای هەولێر', 'ku'));
        // the other script turns it; the script with more letters wins
        $this->assertSame(' dir="ltr"', (string) text_dir('Alexander nearby.', 'ku'));
        $this->assertSame(' dir="rtl"', (string) text_dir('قلعة أربيل', 'en'));
        $this->assertSame('', (string) text_dir('UNESCO لە ساڵی ٢٠١٤ تۆماری کرد', 'ku'));

        // on a Kurdish page a timeline's date stays on the page's side (dir="auto"
        // made a date of digits run left to right and sit on the wrong side)
        $html = $this->get($this->landmark()->url('ku'))->assertOk()->getContent();
        $this->assertStringContainsString('<span class="lm-tl__date">٢٠١٤</span>', $html);
        $this->assertStringContainsString('<p class="lm-tl__text" dir="ltr">Alexander nearby.</p>', $html);
    }

    public function test_kurdish_and_arabic_numbers_carry_their_own_separators(): void
    {
        $this->assertSame('٦٥٬٠٠٠', Landmark::digits('65,000', 'ku'));
        $this->assertSame('١٫٥', Landmark::digits('1.5', 'ar'));
        $this->assertSame('65,000', Landmark::digits('65,000', 'en'));
        // inside written text: only a comma or point between Eastern Arabic digits
        $this->assertSame('نزیکەی ٤٦٬٠٠٠ ساڵ، و ١٫٢ کم', Landmark::separators('نزیکەی ٤٦,٠٠٠ ساڵ، و ١.٢ کم', 'ku'));
        $this->assertSame('١٩٥١,١٩٥٣', Landmark::separators('١٩٥١,١٩٥٣', 'ku'));    // two years, not a thousands mark
        $this->assertSame('46,000 years', Landmark::separators('46,000 years', 'ku'));

        $l = $this->landmark(['sections' => [['type' => 'facts', 'data' => ['items' => [['text_en' => 'About 46,000 years old.', 'text_ku' => 'نزیکەی ٤٦,٠٠٠ ساڵ.']]]]]]);
        $this->get($l->url('ku'))->assertOk()->assertSee('نزیکەی ٤٦٬٠٠٠ ساڵ.')->assertDontSee('٤٦,٠٠٠');
    }

    public function test_the_unesco_panel_keeps_the_official_name_under_the_landmarks_own(): void
    {
        $l = $this->landmark(['unesco_name' => 'Erbil Citadel', 'sections' => [['type' => 'unesco', 'data' => []]]]);

        $this->get($l->url())->assertOk()->assertSee('<h2 class="lm-h2">Erbil Citadel</h2>', false)->assertDontSee('lm-unesco__official', false);
        $this->get($l->url('ku'))->assertOk()
            ->assertSee('<h2 class="lm-h2">قەڵای تاقیکردنەوە</h2>', false)
            ->assertSee('ناوی فەرمی: <span lang="en" dir="ltr">Erbil Citadel</span>', false);
        $this->get($l->url('ar'))->assertOk()->assertSee('<h2 class="lm-h2">قلعة الاختبار</h2>', false)->assertSee('الاسم الرسمي', false);
    }

    public function test_the_collection_counts_years_of_building_as_its_title_does(): void
    {
        // from Göbekli Tepe (9500 BC), not Shanidar's hearths (63,000 BC)
        $html = $this->get('/kurdistan-heritage')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#data-count>11,000\+</span></dd><dt>Years of building</dt>#', $html);
        $this->assertStringContainsString('From the first hearths to today', $html);
        $this->get('/ku/kurdistan-heritage')->assertOk()->assertSee('١١٬٠٠٠+')->assertSee('ساڵ بیناسازی');
    }

    public function test_the_moved_source_links_are_repaired(): void
    {
        $html = implode('', array_map(fn ($slug) => $this->get('/kurdistan-heritage/'.$slug)->getContent(),
            ['shanidar-cave', 'jerwan-aqueduct', 'amedi', 'taq-e-bostan', 'mudhafaria-minaret']));
        foreach (['humanorigins.si.edu/evidence/human-fossils/shanidar-3-neanderthal-skeleton', 'isac.uchicago.edu/publications/sennacheribs-aqueduct-jerwan',
            'mapping-mesopotamian-monuments/amadiyaamedi-citadel', 'iranicaonline.org/articles/sasanian-rock-reliefs/', 'archive.org/details/narrativeofresi02rich'] as $url) {
            $this->assertStringContainsString($url, $html);
        }
        foreach (['fossils/shanidar-3"', 'oip-24-sennacheribs', 'node/87269', 'articles/taq-e-bostan', 'narrativeofresid01rich'] as $gone) {
            $this->assertStringNotContainsString($gone, $html);
        }
    }

    public function test_the_timeline_names_each_anchor_year_and_no_mark_covers_another(): void
    {
        $html = $this->get('/kurdistan-heritage')->assertOk()->getContent();

        // one label per anchor year, evenly spaced, from the oldest landmark to today
        $this->assertSame(1, preg_match('/<div class="hr-time__axis".*?<\/div>/s', $html, $axis));
        preg_match_all('/--x: ([\d.]+)">([^<]+)</', $axis[0], $ticks);
        $this->assertSame(['65,000 BC', '10,000 BC', '5000 BC', '3000 BC', '1000 BC', 'AD 1', '1000', 'today'], $ticks[2]);
        foreach ($ticks[1] as $i => $x) {
            $this->assertEqualsWithDelta($i * 100 / 7, (float) $x, 0.02);
        }

        // every landmark has a mark; two marks in one lane are never closer than a mark's width
        preg_match_all('/<div class="hr-time__row" data-era="(\w+)"[^>]*>(.*?)<\/div>/s', $html, $rows, PREG_SET_ORDER);
        $marks = 0;
        foreach ($rows as [, $era, $row]) {
            preg_match_all('/--x: ([\d.]+); --lane: (\d+)/', $row, $found, PREG_SET_ORDER);
            $lanes = [];
            foreach ($found as [, $x, $lane]) {
                $lanes[$lane][] = (float) $x;
                $marks++;
            }
            foreach ($lanes as $xs) {
                sort($xs);
                for ($i = 1; $i < count($xs); $i++) {
                    $this->assertGreaterThanOrEqual(2.8, $xs[$i] - $xs[$i - 1], "marks touch in $era");
                }
            }
        }
        $this->assertSame(21, $marks);
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

    public function test_the_home_page_opens_onto_the_heritage_through_seven_doors(): void
    {
        $html = $this->get('/')->assertOk()->assertSee('Eleven thousand years of building')->assertSee('Explore the heritage map')->getContent();
        $section = substr($html, strpos($html, 'id="heritage"'));
        $section = substr($section, 0, strpos($section, '</section>'));
        // seven landmarks, oldest first, each a link to its page; the citadel stands open
        $this->assertSame(7, substr_count($section, 'class="hg-door__link"'));
        $this->assertLessThan(strpos($section, 'ishak-pasha-palace'), strpos($section, 'gobekli-tepe'));
        $this->assertMatchesRegularExpression('#class="hg-door is-open">\s*<a class="hg-door__link" href="[^"]*/kurdistan-heritage/erbil-citadel"#', $section);
        $this->assertStringContainsString('data-href-ku="'.url('/ku/kurdistan-heritage/erbil-citadel').'"', $section);
        $this->assertStringContainsString('9500 BC', $section);
        // the figures of the collection, and the way to its map
        $this->assertMatchesRegularExpression('#<dd data-en="21"#', $section);
        $this->assertStringContainsString('href="'.url('/kurdistan-heritage').'"', $section);

        $this->get('/ku')->assertOk()->assertSee('یازدە هەزار ساڵ بیناسازی')->assertSee('نەخشەی میرات بگەڕێ')->assertSee('قەڵای هەولێر');
        $this->get('/ar')->assertOk()->assertSee('استكشف خريطة التراث')->assertSee(url('/ar/kurdistan-heritage/erbil-citadel'), false);

        // with no landmarks published the section is left out, and the page still opens
        Landmark::query()->update(['is_published' => false]);
        $this->get('/')->assertOk()->assertDontSee('id="heritage"', false);
    }

    /* ── What they are built of ─────────────────────────────────────── */

    public function test_every_landmark_has_its_materials_and_the_map_can_be_filtered_by_them(): void
    {
        // each of the 21 has at least one, and only materials the site knows
        foreach (Landmark::all() as $l) {
            $this->assertNotEmpty($l->materialKeys(), $l->slug);
            $this->assertSame($l->materials, $l->materialKeys(), $l->slug);
        }
        $this->assertSame(['basalt', 'limestone'], Landmark::where('slug', 'amed-city-walls')->sole()->materials);

        $html = $this->get('/kurdistan-heritage')->assertOk()->assertSee('By material')->assertSee('Built from the land')->getContent();
        // a chip for each material in use, with its count and its colour
        $this->assertMatchesRegularExpression('#data-filter="material:basalt" style="--mat: \#8892A4"><i class="hr-swatch" aria-hidden="true"></i>Basalt <span class="hr-chip__n">3</span>#', $html);
        $this->assertMatchesRegularExpression('#data-filter="material:limestone"[^>]*>.*?Limestone <span class="hr-chip__n">8</span>#s', $html);
        // the cards and the timeline's marks say what they are built of, and the pins too
        $this->assertStringContainsString('data-material="basalt limestone" id="amed-city-walls"', $html);
        $this->assertStringContainsString('data-material="brick mudbrick timber" data-name="Erbil Citadel"', $html);
        $this->assertStringContainsString('"material":["rock","stone","brick"]', $html);
        // the section: why here, and where
        $this->assertStringContainsString('Black volcanic stone.', $html);
        $this->assertStringContainsString('data-material-show="basalt"', $html);

        $this->get('/ku/kurdistan-heritage')->assertOk()->assertSee('بەپێی کەرەستە')->assertSee('بازاڵت')->assertSee('لە خاکی خۆیەوە بنیاتنراوە');
        $this->get('/ar/kurdistan-heritage')->assertOk()->assertSee('حسب المادة')->assertSee('الحجر الجيري');
    }

    public function test_a_landmarks_page_names_what_it_is_built_of(): void
    {
        $this->get('/kurdistan-heritage/erbil-citadel')->assertOk()->assertSee('Built of')
            ->assertSee('href="'.url('/kurdistan-heritage').'?material=brick#map" style="--mat: #D4553B"', false)
            ->assertSeeInOrder(['Fired brick', 'Mud brick', 'Timber']);
        $this->get('/ku/kurdistan-heritage/erbil-citadel')->assertOk()->assertSee('دروستکراوە لە')->assertSee('خشتی سووتاو');

        // none chosen, or one the site does not know: no tags, and nothing breaks
        $l = $this->landmark(['materials' => ['marzipan']]);
        $this->assertSame([], $l->materialKeys());
        $this->get($l->url())->assertOk()->assertDontSee('lm-built', false);
    }

    /* ── Their state today ─────────────────────────────────────────── */

    public function test_every_landmark_says_its_state_today_and_the_lost_and_at_risk_are_gathered(): void
    {
        // each of the 21 has a known state, a line in three languages and a source
        foreach (Landmark::all() as $l) {
            $this->assertArrayHasKey($l->condition, Landmark::CONDITIONS, $l->slug);
            $this->assertTrue(filled($l->condition_en) && filled($l->condition_ku) && filled($l->condition_ar), $l->slug);
            $this->assertStringStartsWith('https://', $l->condition_url, $l->slug);
        }
        $this->assertSame('submerged', Landmark::where('slug', 'hasankeyf')->sole()->condition);

        // the heritage page lists the at-risk ones, and only those
        $html = $this->get('/kurdistan-heritage')->assertOk()->assertSee('Lost and at risk')->getContent();
        $risk = substr($html, strpos($html, 'id="at-risk"'), strpos($html, 'class="hr-end"') - strpos($html, 'id="at-risk"'));
        $this->assertStringContainsString('Under water', $risk);
        $this->assertStringContainsString('Ilısu Dam', $risk);
        $this->assertStringContainsString('earthobservatory.nasa.gov', $risk);
        $this->assertStringNotContainsString('Taq-e Bostan', $risk);
        $this->assertSame(Landmark::all()->filter->atRisk()->count(), substr_count($risk, 'class="hr-risk__item'));
        $this->get('/ku/kurdistan-heritage')->assertOk()->assertSee('لەدەستچوو و لە مەترسیدا')->assertSee('لەژێر ئاودایە');
        $this->get('/ar/kurdistan-heritage')->assertOk()->assertSee('ما فُقد وما هو مهدَّد');

        // and each page says it, with its source
        $this->get('/kurdistan-heritage/ain-dara-temple')->assertOk()
            ->assertSee('lm-state--damaged', false)->assertSee('Damaged')->assertSee('20–22 January 2018')->assertSee('href="https://www.asor.org/chi/"', false);
        $this->get('/ar/kurdistan-heritage/taq-e-bostan')->assertOk()->assertSee('سليم')->assertSee('اليوم');

        // none set: no line, and no place in the list
        $l = $this->landmark(['condition' => null]);
        $this->assertFalse($l->atRisk());
        $this->get($l->url())->assertOk()->assertDontSee('class="lm-state ', false);
    }

    /* ── How it was built ───────────────────────────────────────────── */

    public function test_six_landmarks_show_how_they_were_built_in_steps(): void
    {
        $techniques = require database_path('seeders/heritage/_techniques.php');
        $this->assertSame(array_keys(Landmark::TECHNIQUES), array_keys($techniques));

        foreach ($techniques as $slug => $data) {
            $l = Landmark::where('slug', $slug)->sole();
            // once on the page, before its timeline
            $types = array_column($l->sections(), 'type');
            $this->assertSame(1, count(array_keys($types, 'technique')), $slug);
            $this->assertSame(array_search('timeline', $types) - 1, array_search('technique', $types), $slug);

            $html = $this->get($l->url())->assertOk()->assertSee('How it was built')->assertSee($data['heading_en'])->getContent();
            // the drawing is drawn in exactly as many stages as there are steps of text, each step in three languages
            preg_match_all('/data-step="(\d+)"/', $html, $m);
            $this->assertSame(count($data['steps']), max(array_map('intval', $m[1])), $slug);
            $this->assertSame(count($data['steps']), substr_count($html, 'data-tq-step='), $slug);
            foreach ($data['steps'] as $step) {
                $this->assertStringContainsString(e($step['title_en']), $html);
                foreach (['title_ku', 'title_ar', 'text_ku', 'text_ar'] as $field) {
                    $this->assertNotEmpty($step[$field], "$slug $field");
                }
            }
        }

        $this->get('/ku/kurdistan-heritage/hawraman')->assertOk()->assertSee('چۆن دروستکراوە')->assertSee('سەربانەکەت، حەوشەی دراوسێکەت');
        $this->get('/ar/kurdistan-heritage/jerwan-aqueduct')->assertOk()->assertSee('كيف بُني')->assertSee('مليونا حجر');
    }

    public function test_a_technique_section_needs_a_drawing_the_site_has_and_is_added_only_once(): void
    {
        $steps = [['title_en' => 'First', 'text_en' => 'One.'], ['title_en' => 'Second', 'text_en' => 'Two.']];
        $l = $this->landmark(['slug' => 'with-drawing'], [['type' => 'technique', 'data' => ['drawing' => 'hawraman', 'heading_en' => 'Up the slope', 'steps' => $steps]]]);
        $this->get($l->url())->assertOk()->assertSee('Up the slope')->assertSee('class="tq__svg"', false)->assertSee('Second')->assertSee('Play again');

        // a drawing that does not exist, or no steps: no section, and nothing breaks
        $none = $this->landmark(['slug' => 'no-drawing'], [['type' => 'technique', 'data' => ['drawing' => '../../secret', 'steps' => $steps]],
            ['type' => 'technique', 'data' => ['drawing' => 'hawraman', 'steps' => []]], ['type' => 'text', 'data' => ['body_en' => 'Still here.']]]);
        $this->get($none->url())->assertOk()->assertSee('Still here.')->assertDontSee('class="tq__svg"', false);

        // the migration that added the sections to the live pages leaves a page that has one alone
        $before = Landmark::where('slug', 'gobekli-tepe')->sole()->sections;
        (require database_path('migrations/2026_11_20_000001_add_technique_sections_to_landmarks.php'))->up();
        $this->assertSame($before, Landmark::where('slug', 'gobekli-tepe')->sole()->sections);
    }

    /* ── The admin ──────────────────────────────────────────────────── */

    public function test_the_admin_pages_render(): void
    {
        $admin = $this->admin();
        $l = $this->landmark();

        $this->actingAs($admin)->get(LandmarkResource::getUrl('index'))->assertOk()->assertSee('Göbekli Tepe')->assertSee('Duplicate');
        $this->actingAs($admin)->get(LandmarkResource::getUrl('create'))->assertOk()->assertSee('Add a section')->assertSee('Built of')->assertSee('Its state today');
        $this->actingAs($admin)->get(LandmarkResource::getUrl('edit', ['record' => $l]))->assertOk()
            ->assertSee('Preview')->assertSee('The hill')->assertSee('Cover photograph');
        // a page with a "how it was built" section opens in the editor too
        $this->actingAs($admin)->get(LandmarkResource::getUrl('edit', ['record' => Landmark::where('slug', 'gobekli-tepe')->sole()]))->assertOk()
            ->assertSee('How it was built')->assertSee('How a ring was raised');
    }
}
