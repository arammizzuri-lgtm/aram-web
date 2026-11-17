<?php

namespace App\Support;

/**
 * The journal's essays: pieces written for the site itself, unlike the build
 * entries, which come from a project's progress in the admin. Each has a view
 * of its own under views/journal/ and is listed on /journal among the entries.
 * Texts are [English, Kurdish, Arabic], as bi() takes them.
 */
class Articles
{
    /**
     * drawings: the file under resources/journal/ its plates are drawn from; cover: a plan and a section from it
     * for its card on /journal, and sheet: that card's title block.
     *
     * @return list<array{slug: string, view: string, date: string, kind: array, title: array, summary: array, drawings: string, cover: array{string, string}, sheet: array}>
     */
    public static function all(): array
    {
        return [
            [
                'slug' => 'citadel-house-plans',
                'view' => 'journal.citadel-houses',
                'date' => '2026-11-15',
                'kind' => ['Essay', 'وتار', 'مقال'],
                'title' => ['The houses of the Citadel, in plan, section and elevation', 'خانووەکانی قەڵا، لە نەخشە و بڕگە و ڕووکاردا', 'بيوت القلعة، مسقطًا ومقطعًا وواجهة'],
                'summary' => [
                    'Seven houses and one whole block of Erbil Citadel, redrawn from the published surveys: twelve plans, six sections and elevations, and what they show about how a house was planned and built here.',
                    'حەوت خانوو و یەک بلۆکی تەواوی قەڵای هەولێر، لە ڕووپێوە بڵاوکراوەکانەوە دووبارە کێشراونەتەوە: دوازدە نەخشە و شەش بڕگە و ڕووکار، و ئەوەی دەیڵێن دەربارەی چۆنیەتی نەخشەدانان و دروستکردنی خانوو لێرە.',
                    'سبعة بيوت وبلوك كامل من قلعة أربيل، أُعيد رسمها عن المسوحات المنشورة: اثنا عشر مسقطاً وستة مقاطع وواجهات، وما تكشفه عن طريقة تخطيط البيت وبنائه هنا.',
                ],
                'drawings' => 'citadel-drawings',
                'cover' => ['salih-g', 'salih-cross'],
                'sheet' => [
                    'kicker' => ['ERBIL CITADEL', 'قەڵای هەولێر', 'قلعة أربيل'],
                    'title' => [['Plans, sections', 'نەخشە، بڕگە', 'مساقط ومقاطع'], ['and elevations', 'و ڕووکار', 'وواجهات']],
                    'note' => ['12 PLANS · 5 SECTIONS · 1 ELEVATION', '12 نەخشە · 5 بڕگە · 1 ڕووکار', '12 مسقطًا · 5 مقاطع · 1 واجهة'],
                    'code' => 'CH-01 — CH-08',
                ],
            ],
            [
                'slug' => 'qaysari-bazaar',
                'view' => 'journal.qaysari-bazaar',
                'date' => '2026-11-16',
                'kind' => ['Essay', 'وتار', 'مقال'],
                'title' => ['The Qaysari: Erbil’s covered bazaar, in plan and section', 'قەیسەری: بازاڕە سەرداپۆشراوەکەی هەولێر، لە نەخشە و بڕگەدا', 'القيصرية: سوق أربيل المسقوف، مسقطًا ومقطعًا'],
                'summary' => [
                    'Under the Citadel, two late-Ottoman Qaysaris still trade: vaulted passages lined with shops of four square metres, rooms above reached by timber galleries. How they are built, drawn as a type, and what the fire of 2024 and the repairs after it changed.',
                    'لە ژێر قەڵاوە، دوو قەیسەریی کۆتایی سەردەمی عوسمانی هێشتا بازرگانییان تێدا دەکرێت: ڕاڕەوی کەوانەدار کە دوکانی چوار مەتر چوارگۆشە بە درێژاییاندا ڕیز بوون، و ژووری سەرەوە کە بە باڵەخانەی دارین دەگەیشتنە سەری. چۆن دروست کراون، وەک جۆرێک کێشراون، و ئەوەی ئاگرەکەی ٢٠٢٤ و چاککردنەوەکانی دوای ئەو گۆڕییان.',
                    'تحت القلعة ما زالت قيصريتان من أواخر العهد العثماني تعجّان بالتجارة: ممرات معقودة تصطف عليها دكاكين بمساحة أربعة أمتار مربعة، وغرف علوية يُصعد إليها عبر أروقة خشبية. كيف بُنيتا، مرسومتين نموذجاً، وما غيّره حريق ٢٠٢٤ والترميم الذي تلاه.',
                ],
                'drawings' => 'qaysari-drawings',
                'cover' => ['q-g', 'q-long'],
                'sheet' => [
                    'kicker' => ['ERBIL · THE QAYSARI', 'هەولێر · قەیسەری', 'أربيل · القيصرية'],
                    'title' => [['A covered passage,', 'ڕاڕەوێکی سەرداپۆشراو،', 'ممرّ مسقوف،'], ['in plan and section', 'لە نەخشە و بڕگەدا', 'مسقطًا ومقطعًا']],
                    'note' => ['1 SITE · 2 PLANS · 2 SECTIONS · 3 ELEVATIONS', '1 شوێن · 2 نەخشە · 2 بڕگە · 3 ڕووکار', '1 موقع · 2 مسقطان · 2 مقطعان · 3 واجهات'],
                    'code' => 'QB-01 — QB-04',
                ],
            ],
        ];
    }

    public static function find(string $slug): ?array
    {
        foreach (self::all() as $article) {
            if ($article['slug'] === $slug) {
                return $article;
            }
        }

        return null;
    }

    /** The link preview: public/images/journal/{slug}-{lang}.jpg, the card's cover at 1200 x 630 (rendered once, by a script), or null for the site's default */
    public static function image(array $article, string $lang): ?string
    {
        $file = 'images/journal/'.$article['slug'].'-'.$lang.'.jpg';

        return is_file(public_path($file)) ? asset($file).'?v='.filemtime(public_path($file)) : null;
    }

    /** The essay's drawings, by key (all in cm), with the walls corrected in the admin (Portfolio → Drawings) where one is live */
    public static function drawings(array $article): array
    {
        return \App\Models\Drawing::corrected(self::raw($article));
    }

    /** The essay's drawings as its file has them: built by a script from its specs, see the file's essay view */
    public static function raw(array $article): array
    {
        static $read = [];

        return $read[$article['drawings']] ??= json_decode(file_get_contents(resource_path('journal/'.$article['drawings'].'.json')), true);
    }

    public static function url(array $article, ?string $lang = null): string
    {
        return site_url('/journal/'.$article['slug'], $lang);
    }
}
