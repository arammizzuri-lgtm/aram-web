<?php

use App\Support\SiteText;
use App\Support\SiteTextAr;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Words the studio can stand behind, and the typos out of the projects.
 *
 * 1. Site texts that claimed more than the work shows ("a leading
 *    practice", "cultural institutions … urban landmarks", "every figure
 *    carried by standing architecture") are replaced by the new wording in
 *    SiteText / SiteTextAr — but only where the stored text is still one of
 *    the old versions below. A text an editor has since rewritten is left as
 *    it is. The career gains the Kurdistan Engineers Union (2023) the same way.
 *
 * 2. Typos in the projects' English (descriptions, names, typologies and
 *    places), each fixed only where the old wording is still there.
 */
return new class extends Migration
{
    /** key => lang => the old texts it may still hold */
    private const OLD = array (
  'seo_description' => 
  array (
    'en' => 
    array (
      0 => 'Aram Mizuri Architecture — a leading architectural practice based in Erbil, Kurdistan Region. Residential, cultural, commercial and urban design.',
    ),
    'ku' => 
    array (
      0 => 'تەڵارسازیی ئارام مزووری — ئۆفیسێکی پێشەنگی تەڵارسازی لە هەولێر، هەرێمی کوردستان. دیزاینی نیشتەجێبوون، کولتووری، بازرگانی و شارستانی.',
    ),
    'ar' => 
    array (
      0 => 'آرام مزوري للعمارة — مكتب معماري رائد في أربيل، إقليم كوردستان. تصميم سكني وثقافي وتجاري وحضري.',
    ),
  ),
  'about_bio_1' => 
  array (
    'en' => 
    array (
      0 => 'Aram Mizuri is a leading architect based in Erbil, the capital of the Kurdistan Region of Iraq. His practice bridges the ancient and the contemporary — drawing from the rich spatial heritage of the Kurdish highlands, from the 7,000-year-old Erbil Citadel to the mountain villages of Amadiyah — to create buildings that are distinctly Kurdish in sensibility while rigorous in their modernity.',
      1 => 'Aram Mizuri is an architect and Co-Founder of Dehat Construction & Architecture, based in Erbil, the capital of the Kurdistan Region of Iraq. His work bridges the ancient and the contemporary, drawing inspiration from the rich spatial heritage of the Kurdish highlands, from the 7,000-year-old Erbil Citadel to the mountain villages of Amadiya, to create architecture that is distinctly Kurdish in character while contemporary in execution.',
    ),
    'ku' => 
    array (
      0 => 'ئارام مزووری تەڵارسازێکی پێشەنگە کە لە هەولێر، پایتەختی هەرێمی کوردستانی عێراق، نیشتەجێیە. کارەکەی پردێکە لەنێوان کۆن و هاوچەرخدا — سەرچاوە دەگرێت لە میراتی دەوڵەمەندی شوێنی بەرزاییەکانی کوردستان، لە قەڵای ٧٬٠٠٠ ساڵەی هەولێرەوە تا گوندە شاخاوییەکانی ئامێدی — بۆ دروستکردنی بینایەک کە بە ڕوونی کوردییە لە هەستەوە و لەهەمانکاتدا توند و هاوچەرخە.',
    ),
    'ar' => 
    array (
      0 => 'آرام مزوري معماريّ رائد مقيم في أربيل، عاصمة إقليم كوردستان العراق. تربط ممارسته بين القديم والمعاصر — مستلهمةً الإرث المكاني الغني لمرتفعات كوردستان، من قلعة أربيل ذات السبعة آلاف عام إلى قرى العمادية الجبلية — لتصنع مباني كوردية الروح بوضوح، ومتقنة في حداثتها.',
    ),
  ),
  'about_bio_2' => 
  array (
    'en' => 
    array (
      0 => 'With completed projects across Kurdistan, including cultural institutions, urban towers, mountain retreats, and civic spaces, Mizuri Architecture has become a defining voice in the rapidly evolving built landscape of the region.',
      1 => 'Through both his independent architectural practice and Dehat Construction & Architecture, he has delivered projects across the Kurdistan Region, including cultural institutions, residential developments, commercial spaces, urban landmarks, and civic environments. His work is driven by the belief that architecture should preserve identity while shaping the region\'s future.',
    ),
    'ku' => 
    array (
      0 => 'بە پرۆژە تەواوکراوەکان لە سەرانسەری کوردستان، لەوانە دامەزراوە کولتوورییەکان، بورجە شارییەکان، پەناگا شاخاوییەکان و بۆشایی گشتییەکان، تەڵارسازیی مزووری بووەتە دەنگێکی دیاریکەر لە دیمەنی بنیاتنراوی خێراگۆڕی ناوچەکەدا.',
    ),
    'ar' => 
    array (
      0 => 'بمشاريع منجزة في أنحاء كوردستان، تشمل مؤسسات ثقافية وأبراجاً حضرية وملاجئ جبلية وفضاءات عامة، أصبح مكتب مزوري للعمارة صوتاً بارزاً في المشهد العمراني المتسارع في المنطقة.',
    ),
  ),
  'statbar_sub' => 
  array (
    'en' => 
    array (
      0 => 'Years of building across Kurdistan and beyond — every figure here is carried by standing architecture.',
    ),
    'ku' => 
    array (
      0 => 'ساڵانێک لە بیناسازی لە کوردستان و دەرەوەی — هەر ژمارەیەکی ئێرە بە تەڵارسازیی وەستاو هەڵگیراوە.',
    ),
    'ar' => 
    array (
      0 => 'سنوات من البناء في كوردستان وخارجها — كل رقم هنا تحمله عمارة قائمة.',
    ),
  ),
  'pj_intro' => 
  array (
    'en' => 
    array (
      0 => 'Homes, interiors, workplaces and public buildings across Kurdistan — every project we have drawn, and the many we have seen built.',
    ),
    'ku' => 
    array (
      0 => 'خانوو، دیزاینی ناوەوە، شوێنی کار و بینای گشتی لە سەرانسەری کوردستان — هەموو ئەو پرۆژانەی کێشاومانن، و زۆرێک لەوانەی بینیومانن کە بنیات نراون.',
    ),
    'ar' => 
    array (
      0 => 'بيوت، تصاميم داخلية، أماكن عمل ومبانٍ عامة في أنحاء كوردستان — كل مشروع رسمناه، والكثير مما رأيناه يُبنى.',
    ),
  ),
  'careers_intro' => 
  array (
    'en' => 
    array (
      0 => 'We are a studio in Erbil designing homes, towers and public places across Kurdistan. If you care about good architecture and want to learn by building it, we would like to hear from you.',
    ),
    'ku' => 
    array (
      0 => 'ئێمە ستۆدیۆیەکین لە هەولێر کە خانوو، تاوەر و شوێنی گشتی لە سەرانسەری کوردستان دیزاین دەکەین. ئەگەر تەڵارسازیی باش بۆت گرنگە و دەتەوێت بە بنیاتنانی فێر ببیت، حەز دەکەین گوێمان لێت بێت.',
    ),
    'ar' => 
    array (
      0 => 'نحن مكتب في أربيل نصمّم البيوت والأبراج والأماكن العامة في أنحاء كوردستان. إن كنت تهتم بالعمارة الجيدة وتريد أن تتعلّم ببنائها، فيسعدنا أن نسمع منك.',
    ),
  ),
  'arch_seo_description' => 
  array (
    'en' => 
    array (
      0 => 'Aram Mizuri is a Kurdish architect in Erbil, founder of Aram Mizuri Architecture: homes, interiors, cultural and commercial buildings across Kurdistan.',
    ),
    'ku' => 
    array (
      0 => 'ئارام مزووری تەڵارسازێکی کوردە لە هەولێر و دامەزرێنەری تەڵارسازیی ئارام مزوورییە: خانوو، ناوەوە، بینای کولتووری و بازرگانی لە سەرانسەری کوردستان.',
    ),
    'ar' => 
    array (
      0 => 'آرام مزوري معماري كوردي في أربيل، مؤسس آرام مزوري للعمارة: بيوت وتصاميم داخلية ومبانٍ ثقافية وتجارية في أنحاء كوردستان.',
    ),
  ),
  'arch_timeline' => 
  array (
    'en' => 
    array (
      0 => '2012 – 2018 | Secondary school | Ankawa Typical Secondary School | Graduated, and set out to study architecture.
2018 – 2023 | Bachelor of Architecture | Tishk International University, Erbil | Studied architecture and graduated in 2023.
2023 – 2024 | Freelance architect | Erbil | Worked independently for a year; by then he had completed fourteen projects as a student and freelance architect.
May 2024 | Architect | Bryar Ali Studio | Took part in designing several of the studio\'s projects.
2024 | First project abroad | Madrid Weekend House — Madrid, Spain | His first project beyond the borders of Kurdistan.
June 2024 | Co-founder | Dehat Construction and Architecture | Founded with a colleague as a design and construction firm. Together they have designed and built projects in cities across the Kurdistan Region, among them So Delicious Restaurant, 404 Cafe and the Kar Group camp restaurant.',
    ),
    'ku' => 
    array (
      0 => '٢٠١٢ – ٢٠١٨ | خوێندنی ئامادەیی | ئامادەیی نموونەیی عەنکاوە | دەرچوو، و ڕێگای خوێندنی تەڵارسازیی گرتەبەر.
٢٠١٨ – ٢٠٢٣ | بەکالۆریۆس لە تەڵارسازی | زانکۆی نێودەوڵەتی تیشک، هەولێر | تەڵارسازیی خوێند و لە ساڵی ٢٠٢٣ دەرچوو.
٢٠٢٣ – ٢٠٢٤ | تەڵارسازی سەربەخۆ | هەولێر | ساڵێک بە سەربەخۆیی کاری کرد؛ تا ئەو کاتە چواردە پرۆژەی وەک خوێندکار و تەڵارسازی سەربەخۆ تەواو کردبوو.
ئایاری ٢٠٢٤ | تەڵارساز | ستۆدیۆی بریار عەلی | بەشداری لە دیزاینی چەند پرۆژەیەکی ستۆدیۆکەدا کرد.
٢٠٢٤ | یەکەم پرۆژە لە دەرەوە | خانووی کۆتایی هەفتە لە مەدرید — ئیسپانیا | یەکەم پرۆژەی لە دەرەوەی سنوورەکانی کوردستان.
حوزەیرانی ٢٠٢٤ | هاودامەزرێنەر | دێهات بۆ بیناسازی و تەڵارسازی | لەگەڵ هاوکارێکی وەک کۆمپانیایەکی دیزاین و بیناسازی دایمەزراند. پێکەوە چەندین پرۆژەیان لە شارەکانی هەرێمی کوردستان دیزاین و جێبەجێ کردووە، لەوانە چێشتخانەی So Delicious، کافێی 404 و چێشتخانەی کەمپی کار گروپ.',
    ),
    'ar' => 
    array (
      0 => '٢٠١٢ – ٢٠١٨ | الدراسة الإعدادية | إعدادية عنكاوا النموذجية | تخرّج، وانطلق لدراسة العمارة.
٢٠١٨ – ٢٠٢٣ | بكالوريوس العمارة | جامعة تيشك الدولية، أربيل | درس العمارة وتخرّج عام ٢٠٢٣.
٢٠٢٣ – ٢٠٢٤ | معماري مستقل | أربيل | عمل مستقلاً لمدة عام؛ وكان قد أنجز حينها أربعة عشر مشروعاً طالباً ومعمارياً مستقلاً.
أيار ٢٠٢٤ | معماري | ستوديو بريار علي | شارك في تصميم عدد من مشاريع الاستوديو.
٢٠٢٤ | أول مشروع خارج كوردستان | بيت عطلة نهاية الأسبوع في مدريد — مدريد، إسبانيا | أول مشاريعه خارج حدود كوردستان.
حزيران ٢٠٢٤ | شريك مؤسس | دهات للإنشاءات والعمارة | أسّسها مع زميل له شركةً للتصميم والإنشاء. صمّما معاً ونفّذا مشاريع في مدن إقليم كوردستان، منها مطعم So Delicious ومقهى 404 ومطعم مخيّم مجموعة كار.',
    ),
  ),
  'arch_bio' => 
  array (
    'en' => 
    array (
    ),
    'ku' => 
    array (
    ),
    'ar' => 
    array (
      0 => 'آرام مزوري معماريّ وشريك مؤسس في دهات للإنشاءات والعمارة، مقيم في أربيل، عاصمة إقليم كوردستان العراق. يربط عمله بين القديم والمعاصر، مستلهماً الإرث المكاني الغني لمرتفعات كوردستان، من قلعة أربيل ذات السبعة آلاف عام إلى قرى العمادية الجبلية، ليصنع عمارة كوردية الطابع بوضوح ومعاصرة في تنفيذها.

من خلال ممارسته المعمارية المستقلة ومن خلال دهات للإنشاءات والعمارة، أنجز مشاريع في أنحاء إقليم كوردستان، تشمل مؤسسات ثقافية ومجمّعات سكنية وفضاءات تجارية ومعالم حضرية وبيئات عامة. يقود عمله إيمانٌ بأن العمارة يجب أن تحفظ الهوية وهي تصنع مستقبل المنطقة.',
    ),
  ),
);

    public function up(): void
    {
        $this->texts();
        $this->projects();
    }

    public function down(): void
    {
        // Leaves the corrections in place.
    }

    private function texts(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }
        $norm = fn ($s) => trim(preg_replace('/\s+/u', ' ', (string) $s));
        $map = SiteText::map();
        $ar = SiteTextAr::all();

        foreach (self::OLD as $key => $langs) {
            foreach ($langs as $lang => $olds) {
                $new = match ($lang) { 'en' => $map[$key]['en'] ?? null, 'ku' => $map[$key]['ku'] ?? null, 'ar' => $ar[$key] ?? null };
                if ($new === null) {
                    continue;
                }
                $known = array_map($norm, $olds);
                // the language's own setting, and for English the older unsuffixed one
                foreach ($lang === 'en' ? ["{$key}_en", $key] : ["{$key}_{$lang}"] as $settingKey) {
                    $row = DB::table('site_settings')->where('key', $settingKey)->first();
                    if ($row && in_array($norm($row->value), $known, true)) {
                        DB::table('site_settings')->where('id', $row->id)->update(['value' => $new, 'updated_at' => now()]);
                    }
                }
            }
        }
    }

    /** English name => [field => [[wrong, right], …]] ('name' fixes the name itself) */
    private const PROJECT_FIXES = [
        'B17 Bedroom' => ['desc' => [['providing a peaceful time from everyday life', 'offering a peaceful retreat from everyday life']]],
        'Guest Room XB121 | Future City' => ['desc' => [['defined proportions, details , and', 'defined proportions, refined details and'], ['contemporary comfort. ', 'contemporary comfort.']]],
        'So Delicious Restaurant | Gulan Street Branch' => ['desc' => [['before the first meal is served, it starts', 'before the first meal is served: it starts']]],
        'Hair Transplant Clinic | Soran' => ['desc' => [['both inviting and distinctive', 'both inviting and distinctive.'], ['distinctive..', 'distinctive.']]],
        'AK47 - AK48 Villa | Ainkawa' => ['desc' => [['When a house became a lifestyle The AK47-48 villas Designed as', 'When a house becomes a lifestyle: the AK47–AK48 villas. Designed as'], ['enhance both elegance and everyday living environment.', 'enhance both elegance and everyday living.']]],
        "Kar Group | WTS Camp's Restaurant" => ['desc' => [['large- scale', 'large-scale']]],
        'VITRA Bathroom' => ['desc' => [['to create a comfort space.', 'to create a space of comfort.']]],
        'Italian Village 2' => ['desc' => [['The facde of clean', 'Its façade of clean'], ['naturalmaterials', 'natural materials']]],
        'VILLA XB 121 | Future City' => ['desc' => [['to create a residence that creates a living architecture ,and luxurious touches.', 'to create a residence of living architecture and luxurious detail.']]],
        'B732 House | Soran' => ['desc' => [['The face of warm wood finishes', 'Its façade of warm wood finishes']]],
        'Vittoria Cafê | Empire Pearl' => ['name' => [['Vittoria Cafê', 'Vittoria Café']], 'location' => [['Empire Perl', 'Empire Pearl']], 'neighbourhood' => [['Empire Perl', 'Empire Pearl']]],
        'Royal Rahmani Showroom' => ['desc' => [['display products , it is', 'display products; it is'], ['refined material elements , and', 'refined material elements and']]],
        'Bedroom B29 | Greenland' => ['desc' => [['Clean lines, furnishings, and refined detailing', 'Clean lines, tailored furnishings and refined detailing']]],
        'A105 Villa | Spanish Village' => ['desc' => [["everyday life,\ncomfort", 'everyday life, comfort'], ["clean\narchitectural", 'clean architectural'], ["warm wood\ntextures", 'warm wood textures'], ["colors,\ncarefully", 'colors, carefully'], ["interior that is\nelegant", 'interior that is elegant'], ["come together.\nDesigned", "come together.\n\nDesigned"]]],
        'Office XB121 | Future City' => ['desc' => [['elegant wall moldings, the dark wood cabinets,', 'elegant wall moldings, dark wood cabinets,'], ['Balanced proportions, and detailed materials', 'Balanced proportions and finely detailed materials']]],
        'B276 Apartment | The Boulevard' => ['desc' => [['carefully  layered', 'carefully layered'], ['a warm and a living environment', 'a warm, inviting living environment']], 'typology' => [['Interior Design ', 'Interior Design']]],
        'Villa 218 | Light City' => ['desc' => [['beyond changing trends through time', 'beyond changing trends'], ['relaxation , harmony', 'relaxation, harmony']]],
        'Waziran Pergula' => ['name' => [['Waziran Pergula', 'Waziran Pergola']], 'desc' => [['Waziran Pergula is designed', 'Waziran Pergola is designed']]],
        'A278 House | Bahirka' => ['desc' => [['and glazings that fills the interiors', 'and glazing that fills the interiors']]],
        'XS172 House | Massif' => ['desc' => [['through the building architecture and environment .', 'through its architecture and setting.']]],
        'Vadar Organization Office | The Boulevard' => ['desc' => [['A workplace should do more than just working, it should', 'A workplace should be more than a place to work; it should']]],
        'Yanar Botique' => ['name' => [['Yanar Botique', 'Yanar Boutique']]],
        'Hiran City | Commercial Zone' => ['typology' => [['Urban Planing', 'Urban Planning']]],
        'Stope Weekend House' => ['name' => [['Stope Weekend House ', 'Stopê Weekend House'], ['Stope Weekend House', 'Stopê Weekend House']], 'typology' => [['Design ', 'Design']]],
        'Pendro Weekend House' => ['name' => [['Pendro Weekend House ', 'Pendro Weekend House']]],
    ];

    /** Typologies written with a stray space or misspelt, on any project */
    private const TYPOLOGY_FIXES = [['Exterior ', 'Exterior Design'], ['Interior Design ', 'Interior Design'], ['Interior', 'Interior Design'], ['Urban Planing', 'Urban Planning']];

    private function projects(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }
        $norm = fn ($s) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $s)));
        $fixes = [];
        foreach (self::PROJECT_FIXES as $name => $fields) {
            $fixes[$norm($name)] = $fields;
        }

        foreach (DB::table('projects')->get() as $p) {
            $update = [];
            foreach ($fixes[$norm($p->name)] ?? [] as $field => $pairs) {
                $value = $update[$field] ?? (string) $p->{$field};
                foreach ($pairs as [$wrong, $right]) {
                    // line endings as stored, so a fix across a line break still matches
                    $value = str_replace([$wrong, str_replace("\n", "\r\n", $wrong)], $right, $value);
                }
                if ($value !== (string) $p->{$field}) {
                    $update[$field] = $value;
                }
            }
            foreach (self::TYPOLOGY_FIXES as [$wrong, $right]) {
                if ((string) $p->typology === $wrong && ! isset($update['typology'])) {
                    $update['typology'] = $right;
                }
            }
            if ($update) {
                DB::table('projects')->where('id', $p->id)->update($update + ['updated_at' => now()]);
            }
        }
    }
};