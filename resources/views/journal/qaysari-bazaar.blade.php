{{-- The journal's essay on Erbil's Qaysari bazaar: the two late-Ottoman Qaysari buildings under the Citadel,
     drawn as a type (resources/journal/qaysari-drawings.json): one stretch of passage in plan and section, one bay
     before and after repair, one gate. Not surveys: the DAI's measured plans may not be redrawn (CC BY-NC-ND), so
     the drawings are built from what its report says in words, and the heights are ours. Every fact has its source
     below. Texts are written here in the three languages: $t(English, Kurdish, Arabic). --}}
@php
    /** @var array $article */
    $lang = site_lang();
    $t = fn (string $en, string $ku, string $ar) => bi($en, $ku, $ar);
    $dwgs = \App\Support\Articles::drawings($article);
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $headline = $t(...$article['title']);
    $title = $headline.' — '.$brand;
    $description = \Illuminate\Support\Str::limit($t(...$article['summary']), 160);
    $path = '/journal/'.$article['slug'];
    $date = \Illuminate\Support\Carbon::parse($article['date']);
    $schema = ['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $headline,
        'datePublished' => $date->toDateString(), 'url' => site_url($path), 'inLanguage' => $lang, 'description' => $description,
        'author' => \App\Support\Architect::reference(), 'publisher' => ['@id' => url('/').'#studio'],
        'about' => ['@type' => 'LandmarksOrHistoricalBuildings', 'name' => 'Qaysari Bazaar, Erbil'],
        'isPartOf' => ['@type' => 'Blog', 'name' => 'Journal', 'url' => site_url('/journal')]];

    $src = $t('Kurapkat 2014, in words: a type, not a survey; the heights are ours', 'کوراپکات ٢٠١٤، بە وشە: جۆرێک، نەک ڕووپێو؛ بەرزییەکان هی خۆمانن', 'كورابكات ٢٠١٤، وصفاً: نموذج لا مسح؛ والارتفاعات من عندنا');

    $plates = [
        [
            'name' => $t('A stretch of passage', 'پارچەیەک لە ڕاڕەو', 'مقطع من الممر'),
            'type' => $t('Shops below, rooms above, a vault over the street', 'دوکان لە خوارەوە، ژوور لە سەرەوە، کەوانەیەک بەسەر شەقامەکەوە', 'دكاكين في الأسفل وغرف في الأعلى وقبو فوق الطريق'),
            'plans' => [['key' => 'q-g', 'level' => $t('Ground floor', 'نهۆمی زەوی', 'الطابق الأرضي')], ['key' => 'q-f', 'level' => $t('Upper floor', 'نهۆمی سەرەوە', 'الطابق العلوي')]],
            'secs' => [['key' => 'q-cross', 'level' => $t('Cross section', 'بڕگەی پانی', 'مقطع عرضي')], ['key' => 'q-long', 'level' => $t('Long section, looking north', 'بڕگەی درێژ، بەرەو باکوور', 'مقطع طولي، باتجاه الشمال')]],
            'facts' => [
                [$t('Passages', 'ڕاڕەوەکان', 'الممرات'), $t('3–4 m wide, up to 60 m long', '٣–٤ مەتر پان، تا ٦٠ مەتر درێژ', 'بعرض ٣–٤ م وطول يصل إلى ٦٠ م')],
                [$t('Shops', 'دوکانەکان', 'الدكاكين'), $t('Over 130 in the East Qaysari, 107 in the West; most no more than 4 m²', 'زیاتر لە ١٣٠ لە قەیسەریی ڕۆژهەڵات، ١٠٧ لە ڕۆژئاوا؛ زۆربەیان لە ٤ م² زیاتر نین', 'أكثر من ١٣٠ في القيصرية الشرقية و١٠٧ في الغربية؛ معظمها لا يتجاوز ٤ م²')],
                [$t('Walls between shops', 'دیواری نێوان دوکانەکان', 'الجدران بين الدكاكين'), $t('About 40 cm; some cut down to 10 cm', 'نزیکەی ٤٠ سم؛ هەندێکیان تا ١٠ سم تاشراون', 'نحو ٤٠ سم؛ قُصّ بعضها حتى ١٠ سم')],
                [$t('Gates, stair houses', 'دەروازە، پەیژەخانە', 'البوابات وبيوت الدرج'), $t('Seven of each in the East Qaysari', 'لە هەر یەکەیان حەوت لە قەیسەریی ڕۆژهەڵات', 'سبعة من كلٍّ منهما في القيصرية الشرقية')],
                [$t('Drawn from', 'لەمەوە کێشراوە', 'مرسوم عن'), $src],
            ],
            'legend' => [
                ['1', $t('Shops, about 2 × 2 m, each open to the passage under a stone arch', 'دوکان، نزیکەی ٢ × ٢ م، هەر یەکەیان لە ژێر کەوانەیەکی بەردیندا بەسەر ڕاڕەوەکەدا کراوەیە', 'دكاكين بنحو ٢ × ٢ م، ينفتح كلٌّ منها على الممر تحت قوس حجري')],
                ['2', $t('The passage, two storeys high, under a barrel vault', 'ڕاڕەوەکە، دوو نهۆم بەرز، لە ژێر کەوانەیەکی بەرمیلیدا', 'الممر، بارتفاع طابقين، تحت قبو أسطواني')],
                ['3', $t('Gate to a bazaar lane; it could be locked', 'دەروازە بۆ کۆڵانێکی بازاڕ؛ دەکرا دابخرێت', 'بوابة إلى زقاق السوق؛ كانت تُقفل')],
                ['4', $t('Stair house', 'پەیژەخانە', 'بيت الدرج')],
                ['5', $t('Cellar hatch: every shop had its own store below; all but one were filled in', 'دەرچەی ژێرزەمین: هەر دوکانێک کۆگای خۆی لە خوارەوە هەبوو؛ جگە لە یەکێک هەموویان پڕ کرانەوە', 'فتحة القبو: كان لكل دكان مخزنه تحته؛ رُدمت كلها إلا واحداً')],
                ['6', $t('Timber gallery on beams cantilevered from the wall', 'باڵەخانەی دارین لەسەر دارەڕای دەرپەڕیو لە دیوارەکەوە', 'رواق خشبي على روافد ناتئة من الجدار')],
                ['7', $t('Upper rooms, each over two shops, entered only from the gallery', 'ژووری سەرەوە، هەر یەکەیان لەسەر دوو دوکان، تەنها لە باڵەخانەکەوە دەچیتە ژوورەوە', 'غرف علوية، كلٌّ منها فوق دكانين، لا تُدخل إلا من الرواق')],
                ['8', $t('Two rooms joined; the door no longer needed became a grilled window', 'دوو ژوور پێکەوە لکێنراون؛ ئەو دەرگایەی پێویست نەما بوو بە پەنجەرەیەکی شیشبەند', 'غرفتان وُصلتا؛ وصار الباب الذي لم يعد لازماً نافذة مشبّكة')],
            ],
            'text' => [
                $t('Behind each gate runs a passage three to four metres wide, once vaulted from end to end. Shops line both sides, each opening to the passage under a stone arch, and most are no larger than two metres by two. In the East Qaysari there are more than 130 of them, nearly all alike. Most belong to tailors, who make and sell Kurdish men’s clothes to measure.',
                    'لە پشت هەر دەروازەیەکەوە ڕاڕەوێک بە پانیی سێ تا چوار مەتر درێژ دەبێتەوە، کە پێشتر لە سەرەوە تا خوارەوە کەوانەدار بوو. دوکانەکان بە هەردوو لایدا ڕیز بوون، هەر یەکەیان لە ژێر کەوانەیەکی بەردیندا بەسەر ڕاڕەوەکەدا دەکرێتەوە، و زۆربەیان لە دوو مەتر بە دوو مەتر گەورەتر نین. لە قەیسەریی ڕۆژهەڵاتدا زیاتر لە ١٣٠ دوکان هەن، کە نزیکەی هەموویان وەک یەکن. زۆربەیان هی بەرگدرووەکانن، کە جلی پیاوانی کوردی بە پێوانە دەدورن و دەفرۆشن.',
                    'خلف كل بوابة يمتد ممر بعرض ثلاثة إلى أربعة أمتار، كان معقوداً من أوله إلى آخره. تصطف الدكاكين على جانبيه، ينفتح كلٌّ منها على الممر تحت قوس حجري، ومعظمها لا يزيد على مترين في مترين. في القيصرية الشرقية أكثر من ١٣٠ دكاناً متشابهة تقريباً، معظمها للخياطين الذين يفصّلون الزي الكردي الرجالي ويبيعونه.'),
                $t('Upstairs the rhythm changes. Each room spans two shops and cannot be reached from either. You climb one of seven stair houses, walk along a timber gallery cantilevered from the passage wall, and enter each room by its own door. The survey team calls this a rarity among building types.',
                    'لە سەرەوە ڕیتمەکە دەگۆڕێت. هەر ژوورێک بەسەر دوو دوکاندا درێژ دەبێتەوە و لە هیچیانەوە ناگەیتە ناوی. بە یەکێک لە حەوت پەیژەخانەکاندا سەردەکەویت، بە باڵەخانەیەکی دارینی دەرپەڕیو لە دیواری ڕاڕەوەکەدا دەڕۆیت، و بە دەرگای تایبەتی هەر ژوورێک دەچیتە ناوی. تیمی ڕووپێوەکە ئەمە بە دەگمەنییەک لە نێو جۆرەکانی بینادا ناو دەبات.',
                    'في الطابق العلوي يتغيّر الإيقاع. تمتد كل غرفة فوق دكانين ولا يُوصل إليها من أيٍّ منهما. تصعد أحد بيوت الدرج السبعة، وتمشي على رواق خشبي ناتئ من جدار الممر، وتدخل كل غرفة من بابها. ويعدّ فريق المسح هذا نادراً بين أنماط الأبنية.'),
            ],
        ],
        [
            'name' => $t('One bay, before and after', 'یەک بڕگە، پێش و دوای', 'فتحة واحدة، قبل وبعد'),
            'type' => $t('The sample bay of 2012', 'بڕگەی نموونەیی ٢٠١٢', 'الفتحة النموذجية لعام ٢٠١٢'),
            'plans' => [['key' => 'q-bay-0', 'level' => $t('As found, 2012', 'وەک ئەوەی دۆزرایەوە، ٢٠١٢', 'كما وُجدت، ٢٠١٢')], ['key' => 'q-bay-1', 'level' => $t('As the project proposed', 'وەک پڕۆژەکە پێشنیاری کرد', 'كما اقترح المشروع')]],
            'facts' => [
                [$t('The arch', 'کەوانەکە', 'القوس'), $t('So-called Mosul marble, hidden under paint for decades', 'ئەوەی پێی دەڵێن مەڕمەڕی مووسڵ، بۆ چەندین دەیە لە ژێر بۆیەدا شاردرابووەوە', 'ما يُسمّى المرمر الموصلي، مخفياً تحت الطلاء لعقود')],
                [$t('Shutters', 'داخەرەکان', 'المغالق'), $t('Rolling steel, as found; folding, a steel frame filled with timber, as proposed', 'پۆڵای لوولدراو، وەک دۆزرایەوە؛ قەدکراو، چوارچێوەی پۆڵا پڕکراو بە دار، وەک پێشنیار', 'فولاذ ملفوف كما وُجد؛ قابلة للطي، إطار فولاذي محشو بالخشب، كما اقتُرح')],
                [$t('Gallery', 'باڵەخانە', 'الرواق'), $t('Mostly lost; one stretch restored', 'زۆربەی لەناوچووە؛ پارچەیەکی نوێکرایەوە', 'ضاع معظمه؛ ورُمّم جزء منه')],
                [$t('Fanlight', 'پەنجەرەی سەر دەرگا', 'النافذة العلوية'), $t('Our guess: the report does not say', 'گومانی ئێمەیە: ڕاپۆرتەکە نایڵێت', 'تخمين منا: التقرير لا يذكرها')],
                [$t('Drawn from', 'لەمەوە کێشراوە', 'مرسوم عن'), $src],
            ],
            'legend' => [
                ['1', $t('Stone arch: jamb stones and nine voussoirs', 'کەوانەی بەردین: بەردی لاتەنیشت و نۆ بەردی کەوانە', 'قوس حجري: أحجار الكتفين وتسع صنجات')],
                ['2', $t('Folding shutter, closed', 'داخەری قەدکراو، داخراو', 'مغلاق قابل للطي، مغلق')],
                ['3', $t('Folding shutter, open: the leaves stand against the jambs', 'داخەری قەدکراو، کراوە: پەڕەکان بە لاتەنیشتەکانەوە دەوەستن', 'مغلاق قابل للطي، مفتوح: تقف درفاته عند الكتفين')],
                ['4', $t('The gallery: beam ends, deck, rail', 'باڵەخانەکە: سەری دارەڕاکان، تەختە، دەستەگرە', 'الرواق: رؤوس الروافد والأرضية والدرابزين')],
                ['5', $t('Rolling shutter and its box; a sign over the arch', 'داخەری لوولدراو و سندووقەکەی؛ تابلۆیەک بەسەر کەوانەکەوە', 'مغلاق ملفوف وصندوقه؛ ولافتة فوق القوس')],
                ['6', $t('The room’s door, made a grilled window', 'دەرگای ژوورەکە، کراوە بە پەنجەرەی شیشبەند', 'باب الغرفة وقد صار نافذة مشبّكة')],
            ],
            'text' => [
                $t('In the autumn of 2012 the project repaired one bay as a sample, to show the shopkeepers what repair could mean. Under thick coats of paint the conservators found an arch of so-called Mosul marble. Its stonework had been forgotten by the very people who worked under it every day.',
                    'لە پاییزی ٢٠١٢دا پڕۆژەکە بڕگەیەکی وەک نموونە چاک کردەوە، بۆ ئەوەی نیشانی دوکاندارەکانی بدات چاککردنەوە چ دەگەیەنێت. لە ژێر چەند چینێکی ئەستووری بۆیەدا پارێزەرەکان کەوانەیەکیان لە ئەوەی پێی دەڵێن مەڕمەڕی مووسڵ دۆزییەوە. بەردکارییەکەی لە یادی ئەو کەسانەش چووبووەوە کە هەموو ڕۆژێک لە ژێریدا کاریان دەکرد.',
                    'في خريف ٢٠١٢ رمّم المشروع فتحة واحدة نموذجاً، ليُري أصحاب الدكاكين ما يعنيه الترميم. وتحت طبقات سميكة من الطلاء وجد المرمّمون قوساً مما يُسمّى المرمر الموصلي، نسي حجارتَه حتى الذين يعملون تحته كل يوم.'),
                $t('Nobody objected when the gallery was mended and the gypsum plaster renewed. The shutters were another matter. Everyone agreed the folding shutters looked better than the rolling steel ones, but not everyone believed they would keep out a thief. The report draws the lesson: bring the users into the decisions, which was then unusual in Iraqi Kurdistan.',
                    'کاتێک باڵەخانەکە چاک کرایەوە و گەچەکە نوێ کرایەوە کەس ناڕەزایی دەرنەبڕی. داخەرەکان شتێکی تر بوون. هەمووان کۆک بوون کە داخەرە قەدکراوەکان لە داخەرە پۆڵاییە لوولدراوەکان جوانترن، بەڵام هەمووان باوەڕیان نەبوو کە دز ڕابگرن. ڕاپۆرتەکە وانەکە دەردەهێنێت: بەکارهێنەران بهێنە ناو بڕیارەکانەوە، کە ئەو کات لە کوردستانی عێراق نائاسایی بوو.',
                    'لم يعترض أحد حين أُصلح الرواق وجُدّد الجص. أما المغالق فأمر آخر. اتفق الجميع على أن المغالق القابلة للطي أجمل من الفولاذية الملفوفة، لكن لم يصدّق الجميع أنها تردّ اللص. ويستخلص التقرير الدرس: أشرِك المستعملين في القرار، وهو ما كان غير مألوف آنذاك في كوردستان العراق.'),
            ],
        ],
        [
            'name' => $t('A gate', 'دەروازەیەک', 'بوابة'),
            'type' => $t('Where a passage meets the lane', 'ئەو شوێنەی ڕاڕەوێک دەگاتە کۆڵانەکە', 'حيث يلتقي الممر بالزقاق'),
            'plans' => [['key' => 'q-gate', 'level' => $t('Elevation from the lane', 'ڕووکار لە کۆڵانەوە', 'الواجهة من الزقاق')]],
            'facts' => [
                [$t('Restored', 'نوێکرایەوە', 'رُمّمت'), $t('2013, on the West Qaysari’s east front', '٢٠١٣، لە ڕووی ڕۆژهەڵاتی قەیسەریی ڕۆژئاوا', '٢٠١٣، في الواجهة الشرقية للقيصرية الغربية')],
                [$t('Work', 'کار', 'الأعمال'), $t('Stone gateway freed and completed; brick repaired; iron grille in its timber frame restored', 'دەروازە بەردینەکە ڕزگار کرا و تەواو کرا؛ خشتەکە چاک کرایەوە؛ شیشبەندی ئاسنین لە چوارچێوە دارینەکەیدا نوێ کرایەوە', 'حُرّرت البوابة الحجرية وأُكملت؛ وأُصلح الطابوق؛ ورُمّمت الشبكة الحديدية في إطارها الخشبي')],
                [$t('Drawn from', 'لەمەوە کێشراوە', 'مرسوم عن'), $src],
            ],
            'legend' => [
                ['1', $t('Stone gateway', 'دەروازەی بەردین', 'بوابة حجرية')],
                ['2', $t('The two gate leaves', 'دوو پەڕەی دەروازەکە', 'درفتا البوابة')],
                ['3', $t('Window with a wrought-iron grille in a timber frame', 'پەنجەرە بە شیشبەندی ئاسنی کوتراو لە چوارچێوەی داریندا', 'نافذة بشبكة من الحديد المطروق في إطار خشبي')],
                ['4', $t('Bare brick', 'خشتی ڕووت', 'طابوق ظاهر')],
            ],
            'text' => [
                $t('Each passage ends in a gate to the bazaar lanes, and at night the gates closed the whole building. At the north gate of the East Qaysari stands the Mam Khalil teahouse, the meeting place of the whole bazaar.',
                    'هەر ڕاڕەوێک بە دەروازەیەک کۆتایی دێت بۆ کۆڵانەکانی بازاڕ، و شەوان دەروازەکان هەموو بیناکەیان دادەخست. لە دەروازەی باکووری قەیسەریی ڕۆژهەڵاتدا چایخانەی مام خەلیل هەیە، شوێنی کۆبوونەوەی هەموو بازاڕەکە.',
                    'ينتهي كل ممر ببوابة إلى أزقة السوق، وفي الليل كانت البوابات تغلق المبنى كله. وعند البوابة الشمالية للقيصرية الشرقية يقع مقهى مام خليل، ملتقى السوق كله.'),
                $t('In 2013 the team restored one gate on the West Qaysari’s east front, with local craftsmen working under a conservator. It was reopened with officials of the city and the province looking on.',
                    'لە ساڵی ٢٠١٣دا تیمەکە دەروازەیەکی لە ڕووی ڕۆژهەڵاتی قەیسەریی ڕۆژئاوادا نوێ کردەوە، بە وەستای ناوخۆیی لە ژێر سەرپەرشتی پارێزەرێکدا. بە ئامادەبوونی بەرپرسانی شار و پارێزگا دووبارە کرایەوە.',
                    'في عام ٢٠١٣ رمّم الفريق بوابة في الواجهة الشرقية للقيصرية الغربية، بحرفيين محليين بإشراف مرمّم، وأُعيد افتتاحها بحضور مسؤولي المدينة والمحافظة.'),
            ],
        ],
    ];
    $count = count($plates);
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ site_rtl() ? 'rtl' : 'ltr' }}">
<head>
    @include('partials.page-head', ['path' => $path, 'css' => 'journal.css', 'type' => 'article', 'image' => \App\Support\Articles::image($article, $lang)])
    <link rel="stylesheet" href="{{ asset('journal-essay.css') }}?v={{ filemtime(public_path('journal-essay.css')) }}">
</head>
<body class="pp pp--journal">
    @include('partials.page-top', ['otherUrl' => null])

    <main class="ja">
        <nav class="jr-crumbs" aria-label="Breadcrumb">
            <a href="{{ site_url('/journal') }}">{{ bival('journal_label') }}</a>
            <span aria-hidden="true">/</span>
            <span>{{ $t(...$article['kind']) }}</span>
        </nav>

        <header class="ja-head">
            <p class="jr-post__meta">
                <span>{{ $t(...$article['kind']) }}</span>
                <span>· <time datetime="{{ $date->toDateString() }}">{{ site_date($date, $lang) }}</time></span>
            </p>
            <h1 class="jr-post__title">{{ $headline }}</h1>
            <p class="ja-head__lead">{{ $t(...$article['summary']) }}</p>
            <dl class="ja-count">
                <div><dt>{{ $t('Qaysaris', 'قەیسەری', 'قيصريتان') }}</dt><dd>2</dd></div>
                <div><dt>{{ $t('Shops in the East', 'دوکان لە ڕۆژهەڵات', 'دكاكين الشرقية') }}</dt><dd dir="ltr">130+</dd></div>
                <div><dt>{{ $t('A shop, m²', 'دوکانێک، م²', 'الدكان، م²') }}</dt><dd>4</dd></div>
                <div><dt>{{ $t('Built', 'دروستکراو', 'بُنيت') }}</dt><dd dir="ltr">c. 1850–1900</dd></div>
            </dl>
        </header>

        <section class="ja-text">
            <h2 class="ja-h">{{ $t('A market under the Citadel', 'بازاڕێک لە ژێر قەڵاوە', 'سوق تحت القلعة') }}</h2>
            <p>{{ $t('People have traded at the foot of the Citadel for centuries; local accounts trace the market south of the mound to the early thirteenth century and Sultan Muzaffar al-Din Gökböri. The bazaar that stands there now grew in the late Ottoman years, when the town spread again beyond the mound, at its south-western foot.',
                'خەڵک بە سەدان ساڵە لە بنی قەڵادا بازرگانی دەکەن؛ گێڕانەوە ناوخۆییەکان بازاڕی باشووری گردەکە دەگەڕێننەوە بۆ سەرەتای سەدەی سێزدە و سوڵتان موزەفەرەدینی گۆکبۆری. ئەو بازاڕەی ئێستا لەوێیە لە ساڵانی کۆتایی عوسمانیدا گەشەی کرد، کاتێک شارەکە دووبارە لە گردەکە تێپەڕی، لە بنی باشووری ڕۆژئاوای.',
                'يتاجر الناس عند سفح القلعة منذ قرون؛ وتردّ الروايات المحلية السوق جنوب التل إلى مطلع القرن الثالث عشر وإلى السلطان مظفر الدين كوكبري. أما السوق القائم اليوم فنما في أواخر العهد العثماني، حين امتدت المدينة ثانيةً خارج التل، عند سفحه الجنوبي الغربي.') }}</p>
            <p>{{ $t('Its first shops were wooden huts that did not last, and the lanes between them were shaded with mats of reed and straw. In the second half of the nineteenth century some of them were replaced by solid buildings that could be locked, with vaulted passages inside. These are the Qaysaris. Two survive, the East Qaysari, also called the tailors’ Qaysari, and the West Qaysari. They are among the oldest buildings in Erbil’s lower town.',
                'یەکەم دوکانەکانی کوخی دارین بوون کە نەدەمانەوە، و کۆڵانەکانی نێوانیان بە حەسیری قامیش و پووش سێبەریان بۆ کرابوو. لە نیوەی دووەمی سەدەی نۆزدەدا هەندێکیان بە بینای پتەو گۆڕدران کە دەکرا دابخرێن، و ڕاڕەوی کەوانەداریان لە ناودا بوو. ئەمانە قەیسەرییەکانن. دوانیان ماونەتەوە، قەیسەریی ڕۆژهەڵات، کە پێی دەوترێت قەیسەریی بەرگدرووەکان، و قەیسەریی ڕۆژئاوا. ئەمانە لە کۆنترین بیناکانی شاری خوارەوەی هەولێرن.',
                'كانت دكاكينه الأولى أكواخاً خشبية لا تدوم، وظُلّلت الأزقة بينها بحُصر القصب والقش. وفي النصف الثاني من القرن التاسع عشر حلّت محل بعضها أبنية متينة تُقفل، في داخلها ممرات معقودة. تلك هي القيصريات. بقيت منها اثنتان، القيصرية الشرقية، وتُسمّى قيصرية الخياطين، والقيصرية الغربية. وهما من أقدم أبنية المدينة السفلى في أربيل.') }}</p>
            <p class="ja-note">{{ $t('A word on the drawings. A team from the German Archaeological Institute and TU Berlin measured both buildings in 2012 and 2013, and their plans are the real thing. Their licence lets us point to them but not redraw them. So these plates are a type: one stretch of passage, built from what the report says in words about widths, shops, walls and how the rooms upstairs are reached. The heights are ours. To see the buildings themselves, read the report, linked below.',
                'وشەیەک دەربارەی کێشانەکان. تیمێک لە پەیمانگای ئەرکیۆلۆجیی ئەڵمانی و زانکۆی تەکنیکیی بەرلین لە ٢٠١٢ و ٢٠١٣دا هەردوو بیناکەیان پێوا، و نەخشەکانیان ڕاستەقینەن. مۆڵەتەکەیان ڕێگەمان دەدات ئاماژەیان پێ بکەین بەڵام دووبارە نەیانکێشینەوە. بۆیە ئەم تابلۆیانە جۆرێکن: پارچەیەک لە ڕاڕەو، کە لەوەوە دروست کراوە کە ڕاپۆرتەکە بە وشە دەیڵێت دەربارەی پانی و دوکان و دیوار و چۆنیەتی گەیشتن بە ژوورەکانی سەرەوە. بەرزییەکان هی خۆمانن. بۆ بینینی خودی بیناکان، ڕاپۆرتەکە بخوێنەوە، کە لە خوارەوە بەستەری هەیە.',
                'كلمة عن الرسوم. قاس فريق من المعهد الألماني للآثار وجامعة برلين التقنية المبنيين عامي ٢٠١٢ و٢٠١٣، ومخططاتهم هي الأصل. ترخيصهم يسمح لنا بالإشارة إليها لا بإعادة رسمها. لذلك فهذه اللوحات نموذج: مقطع واحد من ممر، مبني مما يقوله التقرير وصفاً عن العروض والدكاكين والجدران وطريقة الوصول إلى الغرف العلوية. والارتفاعات من عندنا. ولرؤية المبنيين نفسيهما اقرأ التقرير، ورابطه أدناه.') }}</p>
        </section>

        <section class="ja-text">
            <h2 class="ja-h">{{ $t('What a Qaysari is', 'قەیسەری چییە', 'ما القيصرية') }}</h2>
            <p>{{ $t('The word goes back, through Arabic, to Caesar. Across the Islamic world a qaysariyya was a covered market building for the more valuable trades, closed at night. In Mamluk Cairo it was a courtyard ringed with shops, with homes on the floors above. Erbil’s are different. There is no courtyard. The building is a knot of passages, and the street itself runs inside under a vault.',
                'وشەکە لە ڕێگەی عەرەبییەوە دەگەڕێتەوە بۆ قەیسەر. لە سەرانسەری جیهانی ئیسلامیدا قەیسەری بینایەکی بازاڕی سەرداپۆشراو بوو بۆ بازرگانییە بەنرخترەکان، کە شەوان دادەخرا. لە قاهیرەی مەملووکیدا حەوشەیەک بوو کە دوکان دەوری دابوو و ماڵ لە نهۆمەکانی سەرەوەدا بوون. هی هەولێر جیاوازن. حەوشە نییە. بیناکە گرێیەکە لە ڕاڕەو، و خودی شەقامەکە لە ناوەوە لە ژێر کەوانەیەکدا تێدەپەڕێت.',
                'تعود الكلمة عبر العربية إلى قيصر. وفي أرجاء العالم الإسلامي كانت القيصرية مبنى سوق مسقوفاً للتجارات الأثمن، يُغلق ليلاً. في القاهرة المملوكية كانت فناءً تحيط به الدكاكين وفوقها مساكن. أما قيصريتا أربيل فمختلفتان: لا فناء فيهما، بل عقدة من الممرات، والطريق نفسه يجري في الداخل تحت قبو.') }}</p>
        </section>

        <section class="ja-plates">
            <h2 class="ja-h">{{ $t('The plates', 'تابلۆکان', 'اللوحات') }}</h2>
            <button type="button" class="pp-btn pp-btn--line ja-print" data-print-plates>{{ $t('Print the plates (A3, or save as PDF)', 'چاپی تابلۆکان (A3، یان پاشەکەوت وەک PDF)', 'اطبع اللوحات (A3، أو احفظها PDF)') }}</button>

            @foreach ($plates as $i => $plate)
                <article class="ja-plate" id="plate-{{ $i + 1 }}">
                    <header class="ja-plate__head">
                        <span class="ja-plate__code" dir="ltr">QB-{{ sprintf('%02d', $i + 1) }}</span>
                        <h3 class="ja-plate__name">{{ $plate['name'] }}</h3>
                        <p class="ja-plate__type">{{ $plate['type'] }}</p>
                    </header>
                    <div class="ja-plate__sheet ja-plate__sheet--{{ count($plate['plans']) }}">
                        @php $widest = max(array_map(fn ($p) => $dwgs[$p['key']]['vb'][2], $plate['plans'])); @endphp
                        @foreach ($plate['plans'] as $p)
                            <figure class="ja-plate__fig">
                                @include('journal.drawing', ['dwg' => $dwgs[$p['key']], 'id' => 'dw-'.$p['key'], 'name' => $plate['name'].' — '.$p['level'], 'rel' => $dwgs[$p['key']]['vb'][2] / $widest])
                                <figcaption>{{ $p['level'] }}</figcaption>
                            </figure>
                        @endforeach
                    </div>
                    @if (! empty($plate['secs']))
                        <div class="ja-plate__sheet ja-plate__sheet--sec" style="--cols: {{ implode(' ', array_map(fn ($p) => round($dwgs[$p['key']]['vb'][2]).'fr', $plate['secs'])) }}">
                            @foreach ($plate['secs'] as $p)
                                <figure class="ja-plate__fig">
                                    @include('journal.drawing', ['dwg' => $dwgs[$p['key']], 'id' => 'dw-'.$p['key'], 'name' => $plate['name'].' — '.$p['level']])
                                    <figcaption>{{ $p['level'] }}</figcaption>
                                </figure>
                            @endforeach
                        </div>
                    @endif
                    <div class="ja-plate__body">
                        <div class="ja-plate__text">
                            @foreach ($plate['text'] as $para)
                                <p>{{ $para }}</p>
                            @endforeach
                            <ol class="ja-legend">
                                @foreach ($plate['legend'] as [$n, $label])
                                    <li><span class="ja-legend__n" dir="ltr">{{ $n }}</span><span>{{ $label }}</span></li>
                                @endforeach
                            </ol>
                        </div>
                        <dl class="ja-facts">
                            @foreach ($plate['facts'] as [$k, $v])
                                <div><dt>{{ $k }}</dt><dd><bdi>{{ $v }}</bdi></dd></div>
                            @endforeach
                        </dl>
                    </div>
                    <p class="ja-plate__foot"><span>Aram Mizuri Architecture — {{ $headline }}</span><span dir="ltr">{{ site_url($path) }} · {{ $i + 1 }} / {{ $count }}</span></p>
                </article>
            @endforeach
        </section>

        <section class="ja-text">
            <h2 class="ja-h">{{ $t('How it aged', 'چۆن پیر بوو', 'كيف شاخت') }}</h2>
            <p>{{ $t('The East Qaysari was built at the end of the nineteenth century by the Chalabi family, Turkmen merchants, on an irregular plot; a small single-storey part in the south-west is older. The West Qaysari followed soon after. Both still belonged to the family when they were surveyed.',
                'قەیسەریی ڕۆژهەڵات لە کۆتایی سەدەی نۆزدەدا لەلایەن بنەماڵەی چەلەبی، بازرگانە تورکمانەکان، لەسەر پارچە زەوییەکی ناڕێک دروست کرا؛ بەشێکی بچووکی یەک نهۆمی لە باشووری ڕۆژئاوادا کۆنترە. قەیسەریی ڕۆژئاوا زوو دوای ئەو هات. کاتێک ڕووپێو کران هەردووکیان هێشتا هی بنەماڵەکە بوون.',
                'بنت القيصريةَ الشرقية أواخرَ القرن التاسع عشر أسرةُ الچلبي، وهم تجار تركمان، على قطعة غير منتظمة؛ وجزء صغير من طابق واحد في جنوبها الغربي أقدم منها. وتلتها القيصرية الغربية بعد قليل. وكانت الاثنتان ما تزالان ملك الأسرة حين مُسحتا.') }}</p>
            <dl class="ja-build">
                <div><dt>{{ $t('Early 1900s', 'سەرەتای ١٩٠٠', 'مطلع القرن العشرين') }}</dt><dd>{{ $t('Upper rooms joined into larger suites, doors made windows. Then the upper floor was largely given up.', 'ژوورەکانی سەرەوە پێکەوە لکێنران بۆ کۆمەڵە ژووری گەورەتر، دەرگا کران بە پەنجەرە. پاشان نهۆمی سەرەوە بە زۆری وازی لێ هێنرا.', 'وُصلت الغرف العلوية في أجنحة أكبر وصارت الأبواب نوافذ. ثم هُجر الطابق العلوي في معظمه.') }}</dd></div>
                <div><dt dir="ltr">1970s</dt><dd>{{ $t('The vault over the East Qaysari’s eastern passage fell in. Sheet metal on a makeshift steel frame has covered it since.', 'کەوانەی سەر ڕاڕەوی ڕۆژهەڵاتی قەیسەریی ڕۆژهەڵات ڕووخا. لەو کاتەوە تەنەکە لەسەر چوارچێوەیەکی پۆڵای کاتی دایپۆشیوە.', 'انهار القبو فوق الممر الشرقي للقيصرية الشرقية، ويغطيه منذ ذلك الحين صفيح على هيكل فولاذي مؤقت.') }}</dd></div>
                <div><dt dir="ltr">1980s →</dt><dd>{{ $t('No more repairs in common: each tenant mends his own shop, sometimes doing more harm than good. Walls between shops are cut from 40 cm to as little as 10 to win space, and goods spread into the passage until only a path is left.', 'چیتر چاککردنەوەی هاوبەش نییە: هەر کرێچییەک دوکانی خۆی چاک دەکاتەوە، هەندێک جار زیانی زیاتر لە سوود. دیواری نێوان دوکانەکان لە ٤٠ سم تا ١٠ سم دەتاشرێن بۆ بەدەستهێنانی شوێن، و کاڵاکان بە ناو ڕاڕەوەکەدا بڵاو دەبنەوە تا تەنها ڕێڕەوێک دەمێنێتەوە.', 'لا إصلاحات مشتركة بعد ذلك: كل مستأجر يصلح دكانه وحده، وأحياناً يضرّ أكثر مما ينفع. تُقصّ الجدران بين الدكاكين من ٤٠ سم حتى ١٠ لكسب المساحة، وتمتد البضائع في الممر حتى لا يبقى إلا ممشى.') }}</dd></div>
                <div><dt dir="ltr">2011–2013</dt><dd>{{ $t('The DAI and TU Berlin survey both buildings, train staff of Erbil’s Directorate of Antiquities, repair sample bays, and hand a conservation master plan to the Governor.', 'پەیمانگای ئەرکیۆلۆجیی ئەڵمانی و زانکۆی تەکنیکیی بەرلین هەردوو بیناکە ڕووپێو دەکەن، کارمەندانی بەڕێوەبەرایەتیی شوێنەوارەکانی هەولێر ڕادەهێنن، بڕگەی نموونەیی چاک دەکەنەوە، و ماستەرپلانێکی پاراستن دەدەنە پارێزگار.', 'يمسح المعهد الألماني للآثار وجامعة برلين التقنية المبنيين، ويدرّبان موظفي مديرية آثار أربيل، ويرمّمان فتحات نموذجية، ويسلّمان المحافظ مخططاً رئيسياً للصون.') }}</dd></div>
                <div><dt dir="ltr">5.5.2024</dt><dd>{{ $t('Fire. 227 shops and seven storerooms destroyed, some 270 units hit, more than a hundred people hurt. Goods and timber fed it.', 'ئاگر. ٢٢٧ دوکان و حەوت کۆگا وێران بوون، نزیکەی ٢٧٠ یەکە زیانیان پێ گەیشت، زیاتر لە سەد کەس بریندار بوون. کاڵا و دار خۆراکی بوون.', 'حريق. دُمّر ٢٢٧ دكاناً وسبعة مخازن، وأصاب الضرر نحو ٢٧٠ وحدة، وجُرح أكثر من مئة شخص. غذّته البضائع والأخشاب.') }}</dd></div>
                <div><dt dir="ltr">2024–2025</dt><dd>{{ $t('The governorate pays for the repair, about 3.6 million dollars. In April 2025 the shops go back to their owners.', 'پارێزگا پارەی چاککردنەوەکە دەدات، نزیکەی ٣.٦ ملیۆن دۆلار. لە نیسانی ٢٠٢٥دا دوکانەکان دەگەڕێنەوە بۆ خاوەنەکانیان.', 'تموّل المحافظة الترميم بنحو ٣٫٦ مليون دولار. وفي نيسان ٢٠٢٥ تعود الدكاكين إلى أصحابها.') }}</dd></div>
            </dl>
        </section>

        <section class="ja-text">
            <h2 class="ja-h">{{ $t('After the fire', 'دوای ئاگرەکە', 'بعد الحريق') }}</h2>
            <p>{{ $t('The repair sorted the damage before it chose a method. Shops that had collapsed were rebuilt in their old form; those that stood got new surfaces. The brick was made to match: old brickworks were brought back into use, with the old raw materials and moulds. Much of the stone came from Mosul, and the timber from nearby. The older Qaysari was repaired without changing its structure, and its half-ruined upper floor was rebuilt in its nineteenth-century form.',
                'چاککردنەوەکە پێش هەڵبژاردنی ڕێگا زیانەکەی پۆلێن کرد. ئەو دوکانانەی ڕووخابوون بە شێوە کۆنەکەیان دووبارە دروست کرانەوە؛ ئەوانەی مابوون ڕووی نوێیان بۆ کرا. خشتەکە بە گونجاوی دروست کرا: کۆرە خشتە کۆنەکان دووبارە خرانەوە کار، بە هەمان کەرەستەی خاو و قاڵب. زۆربەی بەردەکە لە مووسڵەوە هات، و دارەکە لە نزیکەوە. قەیسەرییە کۆنەکە بەبێ گۆڕینی پێکهاتەکەی چاک کرایەوە، و نهۆمی سەرەوەی نیوە وێرانی بە شێوەی سەدەی نۆزدەی دووبارە دروست کرایەوە.',
                'صنّف الترميم الضرر قبل أن يختار الطريقة. أُعيد بناء الدكاكين المنهارة على هيئتها القديمة، ونالت القائمة سطوحاً جديدة. وصُنع الطابوق مطابقاً: أُعيدت معامل الطابوق القديمة إلى العمل بموادها الخام وقوالبها. وجاء كثير من الحجر من الموصل، والخشب من الجوار. ورُمّمت القيصرية الأقدم دون تغيير إنشائها، وأُعيد بناء طابقها العلوي نصف المهدّم على هيئته في القرن التاسع عشر.') }}</p>
            <p>{{ $t('Floors were levelled, leaks stopped, and new ventilation, drains and lighting run through the old fabric. Sprinklers are still being laid. One new rule may do more than any of these: goods must now stay inside the shops, and nothing may be left out after closing. Twelve years before the fire, the survey had described how far the displays reached into the passages.',
                'زەوییەکان ڕێک کران، دڵۆپە ڕاگیرا، و هەواگۆڕکێ و ئاوەڕۆ و ڕووناکیی نوێ بە ناو پێکهاتە کۆنەکەدا ڕاکێشران. سپرینکلەر هێشتا دادەنرێن. یاسایەکی نوێ لەوانەیە لە هەموو ئەمانە زیاتر بکات: کاڵا ئێستا دەبێت لە ناو دوکانەکاندا بمێنێتەوە، و دوای داخستن هیچ شتێک نابێت لە دەرەوە بمێنێتەوە. دوازدە ساڵ پێش ئاگرەکە، ڕووپێوەکە باسی کردبوو کە پیشانگاکان تا چەند بە ناو ڕاڕەوەکاندا درێژ دەبوونەوە.',
                'سُوّيت الأرضيات وأوقف التسرّب، ومُدّت تهوية وصرف وإنارة جديدة عبر النسيج القديم، وما زالت المرشّات قيد التركيب. وقد تفعل قاعدة جديدة واحدة أكثر من ذلك كله: يجب الآن أن تبقى البضائع داخل الدكاكين، ولا يُترك شيء خارجها بعد الإغلاق. وقبل الحريق باثني عشر عاماً كان المسح قد وصف إلى أي حدٍّ تمتد المعروضات في الممرات.') }}</p>
            <p>{{ $t('The shopkeepers asked in a later study were mostly pleased with the result and felt the bazaar had kept its character. They had also spent a year without income, and few had received any help.',
                'زۆربەی ئەو دوکاندارانەی لە توێژینەوەیەکی دواتردا پرسیاریان لێ کرا لە ئەنجامەکە ڕازی بوون و هەستیان دەکرد بازاڕەکە کەسایەتیی خۆی پاراستووە. هەروەها ساڵێک بێ داهات مابوونەوە، و کەمێکیان هیچ یارمەتییەکیان وەرگرتبوو.',
                'كان معظم أصحاب الدكاكين الذين سُئلوا في دراسة لاحقة راضين عن النتيجة، ورأوا أن السوق حفظ طابعه. لكنهم قضوا عاماً بلا دخل، وقليل منهم تلقّى أي عون.') }}</p>
        </section>

        <section class="ja-text">
            <h2 class="ja-h">{{ $t('What we take from it', 'ئەوەی لێی وەردەگرین', 'ما نأخذه منها') }}</h2>
            <p>{{ $t('Three things stay with us at the drawing board. A small bay, repeated, makes a building that can be mended one bay at a time: the sample bay of 2012 and the repair after the fire both worked that way. A floor lives only as long as its way in: when the galleries rotted, the rooms upstairs emptied. And the people who use a building decide whether a repair lasts, so the shutters are as much their decision as ours.',
                'سێ شت لای مێزی کێشان لەگەڵمان دەمێننەوە. بڕگەیەکی بچووک کە دووبارە دەبێتەوە بینایەک دروست دەکات کە دەکرێت بڕگە بە بڕگە چاک بکرێتەوە: بڕگە نموونەییەکەی ٢٠١٢ و چاککردنەوەی دوای ئاگرەکە هەردووکیان بەو شێوەیە کاریان کرد. نهۆمێک تەنها هێندەی ڕێگای چوونە ژوورەوەی دەژی: کاتێک باڵەخانەکان ڕزین، ژوورەکانی سەرەوە چۆڵ بوون. و ئەو کەسانەی بینایەک بەکاردەهێنن بڕیار دەدەن ئایا چاککردنەوەیەک دەمێنێت، بۆیە داخەرەکان هێندەی ئێمە بڕیاری ئەوانیشە.',
                'ثلاثة أشياء تبقى معنا عند لوحة الرسم. الفتحة الصغيرة المكرّرة تصنع مبنىً يمكن إصلاحه فتحةً فتحة: هكذا عملت الفتحة النموذجية عام ٢٠١٢ والترميم بعد الحريق. والطابق لا يعيش إلا ما عاش مدخله: حين تعفّنت الأروقة فرغت الغرف العلوية. ومن يستعمل المبنى هو من يقرّر إن كان الترميم سيدوم، فالمغالق قرارهم بقدر ما هي قرارنا.') }}</p>
        </section>

        <section class="ja-sources">
            <h2 class="ja-h">{{ $t('Sources', 'سەرچاوەکان', 'المصادر') }}</h2>
            <ol dir="ltr">
                <li>Kurapkat, D. (2014), ‘Erbil, Irak. Die osmanischen Qaisariya-Gebäude im Basar. Die Arbeiten der Jahre 2012 und 2013’, <cite>e-Forschungsberichte des DAI</cite> 2014(1), 58–66. The measured plans are its figs. 4, 5, 16 and 17. <a href="https://doi.org/10.34780/0r26-1i7a" rel="noopener" target="_blank">doi.org/10.34780/0r26-1i7a</a></li>
                <li>Shehab, M. A. and Faraj, R. S. (2024), ‘Contextual Preservation and Contemporary Facilitation of Qaisaria in Iraqi Kurdistan Region’, <cite>Journal of Studies in Science and Engineering</cite> 4(2), 190–212. <a href="https://doi.org/10.53898/josse2024430" rel="noopener" target="_blank">doi.org/10.53898/josse2024430</a></li>
                <li>Khayyat, M., Abdollah, I., Al-Baldawi, H. and Husham, Z. (2026), ‘Post-Disaster Restoration of Erbil’s Qaysariya Bazaar’, <cite>REAL CORP 2026 Proceedings</cite>, 1285–1290. <a href="https://doi.org/10.48494/REALCORP2026.1081" rel="noopener" target="_blank">doi.org/10.48494/REALCORP2026.1081</a></li>
            </ol>
            <p class="ja-note">{{ $t('The drawings are ours: a type built from the description, to explain how such a building works. They are not a record of either Qaysari and not for measuring from. The figures for the fire and the repair are those the 2026 paper reports.',
                'کێشانەکان هی خۆمانن: جۆرێک کە لە وەسفەکەوە دروست کراوە، بۆ ڕوونکردنەوەی ئەوەی بینایەکی وا چۆن کار دەکات. تۆمارێکی هیچ کام لە قەیسەرییەکان نین و بۆ پێوانە لێیانەوە نین. ژمارەکانی ئاگرەکە و چاککردنەوەکە ئەوانەن کە توێژینەوەکەی ٢٠٢٦ ڕاپۆرتیان دەکات.',
                'الرسوم من عملنا: نموذج مبني على الوصف لشرح كيف يعمل مبنى كهذا. ليست سجلاً لأيٍّ من القيصريتين ولا يُقاس عليها. وأرقام الحريق والترميم هي ما تورده دراسة ٢٠٢٦.') }}</p>
        </section>

        <div class="jr-post__actions">
            <a class="pp-btn" href="{{ \App\Support\Articles::url(\App\Support\Articles::find('citadel-house-plans')) }}">{{ $t('The houses of the Citadel', 'خانووەکانی قەڵا', 'بيوت القلعة') }} <span class="pp-arrow" aria-hidden="true">→</span></a>
            @include('partials.share-link', ['shareClass' => 'pp-btn pp-btn--line', 'shareAt' => 'journal', 'shareUrl' => site_url($path), 'shareTitle' => $headline])
        </div>
        <p class="jr-post__back"><a href="{{ site_url('/journal') }}"><span class="pp-arrow pp-arrow--back" aria-hidden="true">←</span> {{ bival('journal_all') }}</a></p>
    </main>

    @include('partials.page-foot')
    <script src="{{ asset('engage.js') }}?v={{ filemtime(public_path('engage.js')) }}" defer></script>
    <script src="{{ asset('journal-drawing.js') }}?v={{ filemtime(public_path('journal-drawing.js')) }}" defer></script>
</body>
</html>
