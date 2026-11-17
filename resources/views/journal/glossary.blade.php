{{-- The journal's glossary: architecture in Kurdish. First the words of any building, scene by scene, each lit on a
     small drawing with its Kurdish name (resources/journal/glossary.php, drawn by journal/word), and a game to test
     yourself on them (glossary.js); then the old words of the Kurdish house, each with its own drawing (plans through
     journal/type, sections and elevations through journal/term), what it means, and where to see it.
     The essays link their words here by anchor (#iwan), so an id, once published, stays.
     Texts are written here in the three languages: $t(English, Kurdish, Arabic). --}}
@php
    use App\Models\Landmark;
    use App\Models\Essay;
    $lang = site_lang();
    $t = fn (string $en, string $ku, string $ar) => bi($en, $ku, $ar);
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $headline = $t('A dictionary of Kurdish architecture', 'فەرهەنگی تەلارسازی کوردی', 'معجم العمارة الكوردية');
    $title = $headline.' — '.$brand;
    $scenes = require resource_path('journal/glossary.php');
    $count = collect($scenes)->sum(fn ($s) => count($s['parts'])) + 12;
    $lead = $t('The words of a building in Kurdish, from the foundation to the dome: '.$count.' of them, each lit on a drawing so the name stays with the thing, with the Arabic and English beside it. Then the old words of the Kurdish house, and their history.',
        'وشەکانی بینا بە کوردی، لە بناغەوە تا گومبەز: '.Landmark::digits($count).' وشە، هەر یەکەیان لەسەر نەخشەیەک ڕووناک کراوەتەوە تا ناوەکە لەگەڵ شتەکە لە بیر بمێنێت، و عەرەبی و ئینگلیزییەکەی لە تەنیشتی. پاشان وشە کۆنەکانی خانووی کوردی، و مێژوویان.',
        'مفردات المبنى بالكوردية، من الأساس إلى القبّة: '.Landmark::digits($count).' كلمة، كلٌّ منها مضاءة على رسم كي يبقى الاسم مقروناً بالشيء، والعربية والإنكليزية إلى جانبها. ثم المفردات القديمة للبيت الكردي، وتاريخها.');
    $description = $lead;
    $path = '/journal/glossary';
    $essays = Essay::published()->whereIn('slug', ['citadel-house-plans', 'qaysari-bazaar'])->get()->keyBy('slug');
    $essay = fn (string $slug) => ($e = $essays->get($slug)) ? [$e->tr('title'), $e->url()] : null;
    $landmarks = Landmark::where('is_published', true)->get()->keyBy('slug');
    $place = fn (string $slug) => ($l = $landmarks->get($slug)) ? [$l->tr('name'), $l->url()] : null;

    // the court of the essay's simplest house, and its tarma and iwan houses (the Citadel essay's first three diagrams)
    $plans = [
        'hawsh' => ['holes' => [[22, 20, 75, 40], [103, 20, 75, 40], [22, 66, 156, 64]], 'court' => [22, 66, 156, 64],
            'doors' => [[52, 60, 12, 'h', -1], [127, 60, 12, 'h', -1], [94, 130, 12, 'h', -1]], 'wins' => [[30, 60, 14, 'h'], [158, 60, 14, 'h']],
            'words' => [[59.5, 43, 'room'], [140.5, 43, 'room'], [100, 101, 'court']]],
        'tarma' => ['holes' => [[22, 20, 48, 36], [76, 20, 48, 36], [130, 20, 48, 36], [22, 62, 156, 68]], 'court' => [22, 86, 156, 44],
            'doors' => [[40, 56, 12, 'h', -1], [94, 56, 12, 'h', -1], [148, 56, 12, 'h', -1], [94, 130, 12, 'h', -1]],
            'wins' => [[26, 56, 10, 'h'], [56, 56, 10, 'h'], [80, 56, 10, 'h'], [110, 56, 10, 'h'], [134, 56, 10, 'h'], [164, 56, 10, 'h']],
            'cols' => [[49, 86], [73, 86], [127, 86], [151, 86]], 'over' => 'M22 86H178', 'words' => [[100, 77, 'tarma'], [100, 112, 'court']]],
        'iwan' => ['holes' => [[22, 20, 56, 40], [122, 20, 56, 40], [[22, 66], [84, 66], [84, 20], [116, 20], [116, 66], [178, 66], [178, 130], [22, 130]]], 'court' => [22, 66, 156, 64],
            'doors' => [[44, 60, 12, 'h', -1], [144, 60, 12, 'h', -1], [94, 130, 12, 'h', -1]], 'wins' => [[28, 60, 10, 'h'], [162, 60, 10, 'h']],
            'over' => 'M84 66H116M84 58Q100 46 116 58', 'words' => [[100, 39, 'iwan'], [100, 101, 'court']]],
        // one long room over the court, seats along three of its walls
        'diwakhana' => ['holes' => [[22, 20, 156, 70], [22, 96, 156, 34]], 'court' => [22, 96, 156, 34],
            'doors' => [[94, 90, 12, 'h', -1], [94, 130, 12, 'h', -1]], 'wins' => [[34, 90, 16, 'h'], [62, 90, 16, 'h'], [122, 90, 16, 'h'], [150, 90, 16, 'h']],
            'fix' => 'M22 28H178M30 28V90M170 28V90'.implode('', array_map(fn ($x) => 'M'.$x.' 20v8', range(36, 164, 16))),
            'words' => [[100, 62, 'diwakhana'], [100, 117, 'court']]],
        // rooms all round a court, one gate
        'khan' => ['holes' => [[22, 20, 26, 26], [54, 20, 28, 26], [88, 20, 24, 26], [118, 20, 28, 26], [152, 20, 26, 26],
            [22, 52, 26, 46], [152, 52, 26, 46], [22, 104, 26, 26], [152, 104, 26, 26], [54, 104, 34, 26], [112, 104, 34, 26],
            [[54, 52], [146, 52], [146, 98], [106, 98], [106, 130], [94, 130], [94, 98], [54, 98]]], 'court' => [54, 52, 92, 46],
            'doors' => [[63, 46, 10, 'h', -1], [95, 46, 10, 'h', -1], [127, 46, 10, 'h', -1], [48, 70, 10, 'v', -1], [146, 70, 10, 'v', 1],
                [66, 98, 10, 'h', 1], [124, 98, 10, 'h', 1], [48, 28, 10, 'v', -1], [146, 28, 10, 'v', 1], [48, 112, 10, 'v', -1], [146, 112, 10, 'v', 1], [94, 130, 12, 'h', -1]],
            'words' => [[100, 79, 'court']]],
    ];

    // grouped: the house, how it is built, the town and the road
    $groups = [
        [$t('The house', 'خانوو', 'البيت'), [
            ['id' => 'hawsh', 'term' => $t('Courtyard', 'حەوشە', 'الحوش'), 'ku' => 'حەوشە', 'ar' => 'الحوش', 'lat' => 'hewşe · ḥawsh',
                'def' => [$t('The open court a house is built around. The rooms open onto it and not onto each other, so it is corridor, sitting room and garden at once, and a family could add a room without touching the rooms it had. Most of the houses on Erbil’s Citadel are built around one.',
                    'ئەو بۆشاییە کراوەیەی خانوو بە دەوریدا دروست دەکرێت. ژوورەکان بەسەریدا دەکرێنەوە نەک بەسەر یەکتردا، بۆیە هەم ڕاڕەوە و هەم ژووری دانیشتن و هەم باخچە، و خێزان دەیتوانی ژوورێک زیاد بکات بێ ئەوەی دەست لە ژوورەکانی پێشووی بدات. زۆربەی خانووەکانی قەڵای هەولێر بە دەوری حەوشەیەکدا دروست کراون.',
                    'الفناء المكشوف الذي يُبنى البيت حوله. تنفتح الغرف عليه لا على بعضها، فهو الممر وغرفة الجلوس والحديقة معاً، وتستطيع الأسرة أن تضيف غرفة دون أن تمسّ غرفها. ومعظم بيوت قلعة أربيل مبنية حول فناء.')],
                'cap' => $t('Plan: two rooms on a court, the house in its simplest form.', 'نەخشە: دوو ژوور بەسەر حەوشەیەکەوە، خانوو لە سادەترین شێوەیدا.', 'مخطط: غرفتان على فناء، البيت في أبسط صوره.'),
                'see' => [$essay('citadel-house-plans'), $place('erbil-citadel')]],
            ['id' => 'iwan', 'term' => $t('Iwan', 'ئەیوان', 'الإيوان'), 'ku' => 'ئەیوان', 'ar' => 'الإيوان', 'lat' => 'eywan · īwān',
                'def' => [$t('A room without a front wall, open to the court under a high arch or beam: shade in summer, and a place to sit outdoors under a roof. The form is very old. The Sasanian kings built great vaulted iwans as the halls of their palaces, and at Taq-e Bostan two are cut into the rock. In Erbil the larger early houses set one between two rooms.',
                    'ژوورێک بێ دیواری پێشەوە، کە لە ژێر کەوانەیەکی بەرز یان دارێکدا بەسەر حەوشەدا کراوەیە: سێبەرە لە هاویندا، و شوێنێکە بۆ دانیشتن لە دەرەوە بەڵام لە ژێر سەربان. ئەم شێوەیە زۆر کۆنە. پاشاکانی ساسانی ئەیوانی گەورەی کەوانەداریان وەک هۆڵی کۆشکەکانیان دروست دەکرد، و لە تاقی وەسان دوو ئەیوان لە بەرددا هەڵکۆڵراون. لە هەولێردا خانووە گەورە کۆنەکان یەکێکیان لە نێوان دوو ژووردا داناوە.',
                    'غرفة بلا جدار أمامي، تنفتح على الفناء تحت قوس عالٍ أو جسر خشبي: ظلّ في الصيف، ومجلس في الهواء الطلق تحت سقف. والشكل قديم جداً: بنى الملوك الساسانيون إيوانات معقودة كبيرة قاعاتٍ لقصورهم، وفي طاق بستان نُحت إيوانان في الصخر. وفي أربيل تضع البيوت الكبيرة المبكرة إيواناً بين غرفتين.')],
                'cap' => $t('Plan: an iwan between two rooms; the dashed line is its arch.', 'نەخشە: ئەیوانێک لە نێوان دوو ژووردا؛ هێڵە پچڕپچڕەکە کەوانەکەیەتی.', 'مخطط: إيوان بين غرفتين؛ والخط المتقطع قوسه.'),
                'see' => [$essay('citadel-house-plans'), $place('taq-e-bostan')]],
            ['id' => 'tarma', 'term' => $t('Tarma', 'تارمە', 'الطارمة'), 'ku' => 'تارمە', 'ar' => 'الطارمة', 'lat' => 'tarme · ṭārma',
                'def' => [$t('A portico along the front of the rooms, roofed and open to the court. Early houses had only small vents and stayed dark, so their fronts needed no shade. When large windows came in the late-Ottoman period, the tarma became essential: on timber columns with carved capitals, on arcades of Mosul alabaster, and later on iron columns.',
                    'ڕاڕەوێکی سەرداپۆشراو بە درێژایی پێشەوەی ژوورەکان، کە بەسەر حەوشەدا کراوەیە. خانووە کۆنەکان تەنها کونی هەواگۆڕکێی بچووکیان هەبوو و تاریک بوون، بۆیە ڕووکاریان پێویستی بە سێبەر نەبوو. کاتێک پەنجەرەی گەورە لە کۆتایی سەردەمی عوسمانیدا هات، تارمە پێویست بوو: لەسەر کۆڵەکەی دار بە سەرکۆڵەکەی هەڵکۆڵراو، لەسەر ڕیزە کەوانەی مەڕمەڕی مووسڵ، و دواتر لەسەر کۆڵەکەی ئاسن.',
                    'رواق مسقوف على امتداد واجهة الغرف، مفتوح على الفناء. لم يكن للبيوت المبكرة سوى فتحات تهوية صغيرة وكانت معتمة، فلم تحتج واجهاتها إلى الظل. وحين جاءت النوافذ الكبيرة في أواخر العهد العثماني صارت الطارمة ضرورة: على أعمدة خشبية بتيجان منحوتة، أو على عقود من مرمر الموصل، ثم على أعمدة حديدية.')],
                'cap' => $t('Plan: three rooms behind a tarma on four columns.', 'نەخشە: سێ ژوور لە پشت تارمەیەک لەسەر چوار کۆڵەکە.', 'مخطط: ثلاث غرف خلف طارمة على أربعة أعمدة.'),
                'see' => [$essay('citadel-house-plans')]],
            ['id' => 'diwakhana', 'term' => $t('Diwakhana', 'دیوەخان', 'الديوخانة'), 'ku' => 'دیوەخان', 'ar' => 'الديوخانة', 'lat' => 'dîwexan · dīwakhāna',
                'def' => [$t('A house, or a room in a house, kept for receiving guests. In Erbil a diwakhana’s door stood open after the afternoon prayer, and twenty or thirty people at a time came in to sit, talk and settle matters. The Citadel’s nomination file calls the type “very specific to Erbil”.',
                    'خانوویەک، یان ژوورێک لە خانوویەکدا، کە بۆ پێشوازی لە میوان تەرخان کراوە. لە هەولێردا دوای نوێژی عەسر دەرگای دیوەخان کراوە دەبوو، و بیست تا سی کەس پێکەوە دەهاتنە ژوورەوە بۆ دانیشتن و قسەکردن و یەکلاکردنەوەی کاروبار. دۆسیەی پاڵاوتنی قەڵا ئەم جۆرە بە «زۆر تایبەت بە هەولێر» ناو دەبات.',
                    'بيت، أو غرفة في بيت، مخصصة لاستقبال الضيوف. في أربيل كان باب الديوخانة يُفتح بعد صلاة العصر فيدخل عشرون أو ثلاثون شخصاً في المرة ليجلسوا ويتحدثوا ويفصلوا في الأمور. ويصف ملف ترشيح القلعة هذا النوع بأنه «خاص جداً بأربيل».')],
                'cap' => $t('Plan: a long room over the court, seats along three walls. A diagram, not a survey.', 'نەخشە: ژوورێکی درێژ بەسەر حەوشەوە، شوێنی دانیشتن بە درێژایی سێ دیوار. هێڵکارییە، نەک ڕووپێو.', 'مخطط: غرفة طويلة على الفناء، والمقاعد على ثلاثة جدران. رسم توضيحي لا مسح.'),
                'see' => [$essay('citadel-house-plans')]],
            ['id' => 'sirdab', 'term' => $t('Sirdab', 'سەرداب', 'السرداب'), 'ku' => 'سەرداب', 'ar' => 'السرداب', 'lat' => 'serdab · sirdāb',
                'def' => [$t('A room below ground. The earth around it keeps it cool, and across Iraq families went down to the sirdab in the heat of a summer afternoon. On Erbil’s Citadel it is commonest in the houses on the rim, which stand on the slope and get one almost for free; inside the town only the later houses have one, sometimes only under the tarma.',
                    'ژوورێک لە ژێر زەویدا. ئەو گڵەی دەوری فێنکی دەهێڵێتەوە، و لە سەرانسەری عێراقدا خێزانەکان لە گەرمای نیوەڕۆی هاویندا دادەبەزینە سەرداب. لە قەڵای هەولێردا زۆرتر لە خانووەکانی لێواردا هەیە، کە لەسەر لێژییەکە وەستاون و نزیکەی بەخۆڕایی دەستیان دەکەوێت؛ لە ناو شارەکەدا تەنها خانووە درەنگترەکان هەیانە، و هەندێک جار تەنها لە ژێر تارمەکە.',
                    'غرفة تحت الأرض. تحفظ التربة من حولها برودتها، وفي أنحاء العراق كانت الأسر تنزل إلى السرداب في قيظ ظهيرة الصيف. وفي قلعة أربيل هو أكثر شيوعاً في بيوت الحافة القائمة على المنحدر، فتحصل عليه بلا كلفة تقريباً؛ أما في داخل المدينة فلا يوجد إلا في البيوت المتأخرة، وأحياناً تحت الطارمة فقط.')],
                'cap' => $t('Section: a room above, the sirdab below, a grille at the ground to let air in.', 'بڕگە: ژوورێک لە سەرەوە، سەرداب لە خوارەوە، شیشبەندێک لە ئاستی زەوی بۆ هاتنە ژوورەوەی هەوا.', 'مقطع: غرفة في الأعلى والسرداب تحتها، وشبكة عند مستوى الأرض لدخول الهواء.'),
                'see' => [$essay('citadel-house-plans')]],
            ['id' => 'sarban', 'term' => $t('Flat roof', 'سەربان', 'السطح'), 'ku' => 'سەربان', 'ar' => 'السطح', 'lat' => 'serban · saṭḥ',
                'def' => [$t('The flat roof, and a room of the house in its own right. Poplar poles span the walls, a layer of reeds or brushwood goes over them, then packed earth, rolled smooth with a stone roller after rain to keep the water out. In summer families slept on it. In the stepped villages of Hawraman one house’s roof is the yard of the house above.',
                    'سەربانی تەخت، کە خۆی ژوورێکی خانووەکەیە. داری سپیندار بەسەر دیوارەکاندا ڕادەخرێت، چینێک قامیش یان چڵ و پووش دەخرێتە سەری، پاشان گڵی کوتراو، کە دوای باران بە بانگلێرێکی بەردین ساف دەکرێتەوە تا ئاو نەچێتە خوارەوە. لە هاویندا خێزانەکان لەسەری دەخەوتن. لە گوندە پلەپلەکانی هەوراماندا سەربانی ماڵێک حەوشەی ماڵەکەی سەرووترە.',
                    'السطح المستوي، وهو غرفة من غرف البيت بحدّ ذاته. تُمدّ جذوع الحَوْر على الجدران، وفوقها طبقة من القصب أو الأغصان، ثم طين مرصوص يُسوّى بمدحلة حجرية بعد المطر كي لا يتسرّب الماء. وفي الصيف كانت الأسر تنام عليه. وفي قرى هورامان المدرّجة يكون سطح البيت فناءً للبيت الذي فوقه.')],
                'cap' => $t('Section: poles, reeds and earth between two walls, and the roller on top.', 'بڕگە: دار و قامیش و گڵ لە نێوان دوو دیواردا، و بانگلێرەکە لە سەرەوە.', 'مقطع: جذوع وقصب وطين بين جدارين، والمدحلة فوقها.'),
                'see' => [$place('hawraman')]],
        ]],
        [$t('How it is built', 'چۆن دروست دەکرێت', 'كيف يُبنى'), [
            ['id' => 'khisht', 'term' => $t('Brick', 'خشت', 'الطابوق واللبن'), 'ku' => 'خشت', 'ar' => 'الطابوق · اللبن', 'lat' => 'xişt · ṭābūq, labin',
                'def' => [$t('Sun-dried mud brick for ordinary walls; fired brick for what had to last or be seen, such as vaults, minarets and gates. Fired brick can be laid in patterns, and the Mudhafaria Minaret in Erbil, thirty-six metres of it, is covered in them. After the fire of 2024 the Qaysari’s bricks were made again in the old brickworks, with the old raw materials and moulds.',
                    'خشتی کاڵ کە لە بەر خۆردا وشک دەکرێتەوە بۆ دیواری ئاسایی؛ خشتی سوورەوەکراو بۆ ئەوەی دەبوو بمێنێتەوە یان ببینرێت، وەک تاق و منارە و دەروازە. خشتی سوورەوەکراو دەکرێت بە نەخش دابنرێت، و منارەی موزەفەرییە لە هەولێر، سی و شەش مەتر، هەمووی بە نەخش داپۆشراوە. دوای ئاگری ٢٠٢٤ خشتەکانی قەیسەری لە کۆرە خشتە کۆنەکاندا، بە هەمان کەرەستەی خاو و قاڵب، دووبارە دروست کرانەوە.',
                    'اللبن المجفف في الشمس للجدران العادية، والطابوق المفخور لما يجب أن يدوم أو يُرى، كالقبوات والمآذن والبوابات. ويمكن رصف الطابوق المفخور بنقوش، ومنارة المظفرية في أربيل، بارتفاع ستة وثلاثين متراً، مكسوّة بها. وبعد حريق ٢٠٢٤ صُنع طابوق القيصرية من جديد في معامل الطابوق القديمة، بموادها الخام وقوالبها.')],
                'cap' => $t('Elevation: brick in courses, and a band laid in a pattern.', 'ڕووکار: خشت بە ڕیز، و پشتێنەیەک بە نەخش دانراو.', 'واجهة: طابوق في مداميك، وحزام مرصوف بنقش.'),
                'see' => [$place('mudhafaria-minaret'), $essay('qaysari-bazaar')]],
            ['id' => 'taq', 'term' => $t('Arch, vault', 'تاق', 'الطاق'), 'ku' => 'تاق', 'ar' => 'الطاق', 'lat' => 'taq · ṭāq',
                'def' => [$t('An arch, or a vault, which is an arch drawn out over a room. Where long timber is scarce, brick roofs the wider spaces: the Qaysari’s passages stand under brick vaults, and on the Citadel brick vaults in lime mortar belong to the better houses, mostly to their basements. The word is in the name of Taq-e Bostan, the “arch of the garden”, where the Sasanian kings had arches cut into the rock.',
                    'کەوانەیەک، یان سەقفێکی کەوانەیی، کە کەوانەیەکە بەسەر ژوورێکدا درێژ کراوەتەوە. لەو شوێنانەی داری درێژ کەمە، خشت بۆشاییە پانەکان دادەپۆشێت: ڕاڕەوەکانی قەیسەری لە ژێر تاقی خشتیندان، و لە قەڵادا تاقی خشت بە گەچ و گیرە هی خانووە باشترەکانە، زۆرتر هی ژێرزەمینەکانیان. وشەکە لە ناوی تاقی وەسان دایە، «کەوانەی باخ»، کە پاشاکانی ساسانی کەوانەیان لەوێ لە بەرددا هەڵکۆڵی.',
                    'القوس، أو القبو، وهو قوس ممتد فوق غرفة. وحيث يندر الخشب الطويل يسقف الطابوقُ الفراغاتِ الواسعة: ممرات القيصرية تحت أقبية من الطابوق، وفي القلعة تعود أقبية الطابوق بمونة الجير إلى البيوت الأحسن، وأكثرها في سراديبها. والكلمة في اسم طاق بستان، «قوس الحديقة»، حيث نحت الملوك الساسانيون أقواساً في الصخر.')],
                'cap' => $t('Elevation: an arch, every joint pointing at its centre.', 'ڕووکار: کەوانەیەک، هەموو جومگەکانی ڕوو لە ناوەڕاستەکەی.', 'واجهة: قوس، وكل فواصله تتجه إلى مركزه.'),
                'see' => [$place('taq-e-bostan'), $essay('qaysari-bazaar')]],
            ['id' => 'mosul-alabaster', 'term' => $t('Mosul alabaster', 'مەڕمەڕی مووسڵ', 'مرمر الموصل'), 'ku' => 'مەڕمەڕی مووسڵ', 'ar' => 'مرمر الموصل', 'lat' => 'meřmeřî Mûsiḻ · marmar al-Mawṣil',
                'def' => [$t('A grey-blue gypsum stone from around Mosul, soft enough to carve when freshly cut. Across northern Iraq it made door frames, column bases, arcades and floors. On Erbil’s Citadel it carried the arcades of the late-Ottoman tarmas, and the Qaysari’s repair after the fire brought much of its stone from Mosul.',
                    'بەردێکی گەچی شینی خۆڵەمێشی لە دەوروبەری مووسڵ، کە کاتێک تازە دەبڕدرێت ئەوەندە نەرمە هەڵبکۆڵرێت. لە سەرانسەری باکووری عێراقدا چوارچێوەی دەرگا و بنکەی کۆڵەکە و ڕیزە کەوانە و زەوی لێ دروست دەکرا. لە قەڵای هەولێردا ڕیزە کەوانەی تارمەکانی کۆتایی عوسمانی هەڵدەگرت، و چاککردنەوەی قەیسەری دوای ئاگرەکە زۆربەی بەردەکەی لە مووسڵەوە هێنا.',
                    'حجر جصّي رمادي مزرقّ من نواحي الموصل، ليّن يُنحت حين يُقطع حديثاً. صُنعت منه في شمال العراق أطر الأبواب وقواعد الأعمدة والعقود والأرضيات. وفي قلعة أربيل حمل عقود الطارمات العثمانية المتأخرة، وجاء ترميم القيصرية بعد الحريق بكثير من حجره من الموصل.')],
                'cap' => $t('Elevation: a carved doorcase, rosettes on the lintel. A diagram, not a survey.', 'ڕووکار: چوارچێوەی دەرگایەکی هەڵکۆڵراو، گوڵ لەسەر سەردەرگاکە. هێڵکارییە، نەک ڕووپێو.', 'واجهة: إطار باب منحوت، وورود على العتبة. رسم توضيحي لا مسح.'),
                'see' => [$essay('citadel-house-plans'), $essay('qaysari-bazaar')]],
        ]],
        [$t('The town and the road', 'شار و ڕێگا', 'المدينة والطريق'), [
            ['id' => 'qaysari', 'term' => $t('Qaysari', 'قەیسەری', 'القيصرية'), 'ku' => 'قەیسەری', 'ar' => 'القيصرية', 'lat' => 'qeyserî · qayṣariyya',
                'def' => [$t('A covered market building for the more valuable trades, locked at night. The word goes back, through Arabic, to Caesar. In Mamluk Cairo a qaysariyya was a courtyard ringed with shops; Erbil’s two are knots of vaulted passages, with shops below and rooms above, reached from a timber gallery.',
                    'بینایەکی بازاڕی سەرداپۆشراو بۆ بازرگانییە بەنرخترەکان، کە شەوان دادەخرا. وشەکە لە ڕێگەی عەرەبییەوە دەگەڕێتەوە بۆ قەیسەر. لە قاهیرەی مەملووکیدا قەیسەری حەوشەیەک بوو کە دوکان دەوری دابوو؛ دووانەکەی هەولێر گرێیەکن لە ڕاڕەوی کەوانەدار، دوکان لە خوارەوە و ژوور لە سەرەوە، کە لە باڵەخانەیەکی دارینەوە دەگەیتە ناویان.',
                    'مبنى سوق مسقوف للتجارات الأثمن، يُقفل ليلاً. تعود الكلمة عبر العربية إلى قيصر. في القاهرة المملوكية كانت القيصرية فناءً تحيط به الدكاكين؛ أما قيصريتا أربيل فعقدتان من الممرات المعقودة، الدكاكين في الأسفل والغرف في الأعلى يُوصل إليها من رواق خشبي.')],
                'cap' => $t('Section: across a passage, shops and rooms either side, the vault over the street.', 'بڕگە: بە پانیی ڕاڕەوێکدا، دوکان و ژوور لە هەردوو لا، تاقەکە بەسەر شەقامەکەوە.', 'مقطع: عرض ممر، دكاكين وغرف على جانبيه، والقبو فوق الطريق.'),
                'see' => [$essay('qaysari-bazaar')]],
            ['id' => 'khan', 'term' => $t('Khan, caravanserai', 'خان', 'الخان'), 'ku' => 'خان', 'ar' => 'الخان', 'lat' => 'xan · khān',
                'def' => [$t('An inn for merchants: rooms around a court, and one gate that closed at night. Travellers slept in the rooms, and their animals and goods stayed in the court or in stables behind. On the roads they stood about a day’s journey apart; in towns they stood by the bazaar and served as warehouses and offices too.',
                    'میوانخانەی بازرگانان: ژوور بە دەوری حەوشەیەکدا، و یەک دەروازە کە شەوان دادەخرا. ڕێبواران لە ژوورەکاندا دەخەوتن، و ئاژەڵ و کاڵاکانیان لە حەوشەکە یان لە تەویلەی پشتەوەدا دەمانەوە. لەسەر ڕێگاکان نزیکەی ڕۆژێک ڕێگا لە یەکترەوە دوور بوون؛ لە شارەکاندا لە تەنیشت بازاڕ بوون و کاری کۆگا و نووسینگەشیان دەکرد.',
                    'نُزُل للتجار: غرف حول فناء، وبوابة واحدة تُغلق ليلاً. ينام المسافرون في الغرف، وتبقى دوابّهم وبضائعهم في الفناء أو في إسطبلات خلفه. على الطرق كانت تقوم على مسيرة يوم تقريباً بعضها من بعض، وفي المدن تقوم بجوار السوق وتكون مخازن ومكاتب أيضاً.')],
                'cap' => $t('Plan: rooms all round a court, and the one gate.', 'نەخشە: ژوور بە دەوری حەوشەیەکدا، و یەک دەروازە.', 'مخطط: غرف حول فناء، والبوابة الوحيدة.'),
                'see' => [$essay('qaysari-bazaar')]],
            ['id' => 'kahrez', 'term' => $t('Kahrez, qanat', 'کارێز', 'الكهريز'), 'ku' => 'کارێز', 'ar' => 'الكهريز', 'lat' => 'karêz · kahrīz',
                'def' => [$t('A tunnel that brings groundwater to the surface without a pump. It starts at a mother well dug down to the water at the foot of the hills, and runs out under the plain at a gentler slope than the ground, until it comes out among the fields. Shafts every few dozen metres let the diggers in and the spoil out. The plain around Erbil was watered by many of them; by the 2000s most had run dry, as drought and pumped wells drew the water table down.',
                    'تونێلێک کە ئاوی ژێرزەوی بێ پەمپ دەهێنێتە سەر زەوی. لە بیری دایکەوە دەست پێ دەکات، کە لە بنی گردەکاندا تا ئاو هەڵکەنراوە، و بە لێژییەکی نەرمتر لە زەوییەکە بە ژێر دەشتەکەدا دەڕوات، تا لە نێو کێڵگەکاندا دەردەچێت. هەر چەند دەیان مەترێک بیرێک هەیە کە کرێکاران پێیدا دادەبەزن و گڵەکەی پێدا دەردەهێنرێت. دەشتی دەوروبەری هەولێر بە زۆرێک لەمانە ئاو دەدرا؛ تا ساڵانی ٢٠٠٠ زۆربەیان وشک بوون، چونکە وشکەساڵی و بیرە پەمپدارەکان ئاوی ژێرزەوییان دابەزاند.',
                    'نفق يأتي بالمياه الجوفية إلى السطح بلا مضخة. يبدأ ببئر أم تُحفر حتى الماء عند سفح التلال، ويمتد تحت السهل بانحدار أخفّ من انحدار الأرض حتى يخرج بين الحقول. وكل بضع عشرات من الأمتار بئر ينزل منها الحفّارون ويُخرج منها التراب. وكان سهل أربيل يُروى بكثير منها؛ وبحلول العقد الأول من الألفية جفّ أكثرها، إذ خفض الجفاف والآبار المضخّة منسوب المياه الجوفية.')],
                'cap' => $t('Section: from the mother well to the fields, and the shafts it was dug from.', 'بڕگە: لە بیری دایکەوە تا کێڵگەکان، و ئەو بیرانەی لێیانەوە هەڵکەنرا.', 'مقطع: من البئر الأم إلى الحقول، والآبار التي حُفر منها.'),
                'see' => []],
        ]],
    ];

    // every word for search engines: the scene words first, then the house's
    $terms = collect($scenes)->flatMap(fn ($sc) => collect($sc['parts'])->map(fn ($p, $k) => ['id' => $k, 'term' => bi(...$p['w']),
        'alt' => [$p['w'][1], $p['w'][2], $p['w'][0]], 'def' => bi(...$p['def'])]))->values()
        ->concat(collect($groups)->flatMap(fn ($g) => $g[1])->map(fn ($e) => ['id' => $e['id'], 'term' => $e['term'], 'alt' => [$e['ku'], $e['ar']], 'def' => $e['def'][0]]));
    $schema = ['@context' => 'https://schema.org', '@type' => 'DefinedTermSet', '@id' => site_url($path).'#set', 'name' => $headline,
        'description' => $description, 'url' => site_url($path), 'inLanguage' => $lang, 'author' => \App\Support\Architect::reference(),
        'hasDefinedTerm' => $terms->map(fn ($e) => ['@type' => 'DefinedTerm', '@id' => site_url($path).'#'.$e['id'], 'name' => $e['term'],
            'alternateName' => array_values(array_diff(array_unique($e['alt']), [$e['term']])), 'description' => $e['def'],
            'url' => site_url($path).'#'.$e['id'], 'inDefinedTermSet' => site_url($path).'#set'])->all()];
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ site_rtl() ? 'rtl' : 'ltr' }}">
<head>
    @include('partials.page-head', ['path' => $path, 'css' => 'journal.css', 'type' => 'article'])
    <link rel="stylesheet" href="{{ asset('journal-essay.css') }}?v={{ filemtime(public_path('journal-essay.css')) }}">
</head>
<body class="pp pp--journal">
    @include('partials.page-top', ['otherUrl' => null])

    <main class="ja jg">
        <nav class="jr-crumbs" aria-label="Breadcrumb">
            <a href="{{ site_url('/journal') }}">{{ bival('journal_label') }}</a>
            <span aria-hidden="true">/</span>
            <span>{{ $t('Glossary', 'فەرهەنگ', 'مسرد') }}</span>
        </nav>

        <header class="ja-head">
            <h1 class="jr-post__title">{{ $headline }}</h1>
            <p class="ja-head__lead">{{ $lead }}</p>
        </header>

        {{-- the game: a lit part, three Kurdish names; shown once glossary.js has the words --}}
        <section class="jg-quiz" data-quiz hidden aria-labelledby="jg-quiz-h" data-digits="{{ Landmark::digits('0123456789') }}"
            data-right="{{ $t('Right.', 'ڕاستە.', 'صحيح.') }}" data-wrong="{{ $t('Not this one. It is', 'ئەمە نا. ئەوە', 'ليست هذه. إنها') }}">
            <div class="jg-quiz__stage" data-quiz-stage></div>
            <div class="jg-quiz__side">
                <h2 class="ja-h" id="jg-quiz-h">{{ $t('Test yourself', 'خۆت تاقی بکەرەوە', 'اختبر نفسك') }}</h2>
                <p class="jg-quiz__ask">{{ $t('What is the gold part called in Kurdish?', 'ناوی بەشە زەردەکە بە کوردی چییە؟', 'ما اسم الجزء الذهبي بالكوردية؟') }}</p>
                <div class="jg-quiz__opts" data-quiz-opts></div>
                <p class="jg-quiz__out" data-quiz-out aria-live="polite"></p>
                <p class="jg-quiz__foot">
                    <button type="button" class="pp-btn pp-btn--line" data-quiz-next>{{ $t('Another word', 'وشەیەکی تر', 'كلمة أخرى') }}</button>
                    <span class="jg-quiz__score" data-quiz-score></span>
                </p>
            </div>
        </section>

        <nav class="jg-index" aria-label="{{ $t('The words', 'وشەکان', 'الكلمات') }}">
            @foreach ($scenes as $sk => $sc)
                <div>
                    <p class="jg-index__group"><a href="#g-{{ $sk }}">{{ bi(...$sc['name']) }}</a></p>
                    <ul>
                        @foreach ($sc['parts'] as $k => $p)
                            <li><a href="#{{ $k }}">{{ bi(...$p['w']) }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
            @foreach ($groups as [$name, $entries])
                <div>
                    <p class="jg-index__group">{{ $name }}</p>
                    <ul>
                        @foreach ($entries as $e)
                            <li><a href="#{{ $e['id'] }}">{{ $e['term'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        @foreach ($scenes as $sk => $sc)
            <section class="jg-group" id="g-{{ $sk }}">
                <h2 class="ja-h">{{ bi(...$sc['name']) }}</h2>
                <div class="jg-words">
                    @foreach ($sc['parts'] as $k => $p)
                        <article class="jg-word" id="{{ $k }}" data-scene="{{ $sk }}" data-ku="{{ $p['w'][1] }}" data-lat="{{ $p['lat'] }}"
                            data-name="{{ $lang === 'ku' ? $p['w'][0] : bi(...$p['w']) }}">
                            @include('journal.word', ['sc' => $sc, 'keys' => [$k], 'cls' => 'ja-dwg jg-ill', 'sid' => 'w-'.$k])
                            <h3 class="jg-word__ku" lang="ckb" dir="rtl"><a href="#{{ $k }}">{{ $p['w'][1] }}</a></h3>
                            <p class="jg-word__lat" dir="ltr">{{ $p['lat'] }}</p>
                            <p class="jg-word__names">{{ $p['w'][0] }} · <span lang="ar" dir="rtl">{{ $p['w'][2] }}</span></p>
                            <p class="jg-word__def">{{ bi(...$p['def']) }}</p>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach

        <header class="jg-part">
            <h2 class="jr-post__title">{{ $t('The old words of the Kurdish house', 'وشە کۆنەکانی خانووی کوردی', 'المفردات القديمة للبيت الكردي') }}</h2>
            <p class="ja-head__lead">{{ $t('Twelve words for the parts of old houses, markets and waterworks in Kurdistan, each with its history and where to see it.',
                'دوازدە وشە بۆ بەشەکانی خانوو و بازاڕ و ئاوەڕۆ کۆنەکانی کوردستان، هەر یەکەیان بە مێژووەکەی و ئەو شوێنەی دەیبینیت.',
                'اثنتا عشرة كلمة لأجزاء البيوت والأسواق ومنشآت الماء القديمة في كوردستان، كلٌّ منها بتاريخها وأين تُرى.') }}</p>
        </header>

        @foreach ($groups as [$name, $entries])
            <section class="jg-group">
                <h2 class="ja-h">{{ $name }}</h2>
                @foreach ($entries as $e)
                    <article class="jg-term" id="{{ $e['id'] }}">
                        <figure class="jg-term__fig">
                            @if (isset($plans[$e['id']]))
                                @include('journal.type', ['id' => 'g-'.$e['id'], 'tp' => $plans[$e['id']]])
                            @else
                                @include('journal.term', ['id' => 'g-'.$e['id'], 'key' => $e['id'] === 'mosul-alabaster' ? 'marble' : $e['id']])
                            @endif
                            <figcaption>{{ $e['cap'] }}</figcaption>
                        </figure>
                        <div class="jg-term__text">
                            <h3 class="jg-term__name"><a href="#{{ $e['id'] }}">{{ $e['term'] }}</a></h3>
                            <p class="jg-term__words">
                                <span lang="ckb" dir="rtl">{{ $e['ku'] }}</span>
                                <span lang="ar" dir="rtl">{{ $e['ar'] }}</span>
                                <span dir="ltr">{{ $e['lat'] }}</span>
                            </p>
                            @foreach ($e['def'] as $para)
                                <p>{{ $para }}</p>
                            @endforeach
                            @if ($see = array_filter($e['see']))
                                <p class="jg-term__see">{{ $t('See', 'بڕوانە', 'انظر') }}:
                                    @foreach ($see as [$label, $url])
                                        <a href="{{ $url }}">{{ $label }}</a>@if (! $loop->last) · @endif
                                    @endforeach
                                </p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </section>
        @endforeach

        <section class="ja-sources">
            <p class="ja-note">{{ $t('The drawings are ours, made to explain the word, not surveys of any one building. The Kurdish is Sorani, in Arabic script; the Latin line gives the Kurdish and the Arabic as they sound. The facts come from the essays and landmark pages linked under each word, and their sources are listed there.',
                'کێشانەکان هی خۆمانن، بۆ ڕوونکردنەوەی وشەکە کێشراون، نەک ڕووپێوی بینایەکی دیاریکراو. کوردییەکە سۆرانییە، بە ئەلفوبێی عەرەبی؛ دێڕە لاتینییەکە کوردی و عەرەبییەکە وەک دەخوێنرێنەوە دەنووسێت. زانیارییەکان لە وتار و پەڕەکانی شوێنەوارەوە وەرگیراون کە لە ژێر هەر وشەیەکدا بەستەریان هەیە، و سەرچاوەکانیان لەوێدا ڕیز کراون.',
                'الرسوم من عندنا، لتوضيح الكلمة، لا مسوحات لمبنى بعينه. والكوردية سورانية بالحرف العربي؛ والسطر اللاتيني يكتب الكوردية والعربية كما تُلفظان. والمعلومات من المقالات وصفحات المعالم المرتبطة تحت كل كلمة، ومصادرها مدرجة هناك.') }}</p>
        </section>

        <div class="jr-post__actions">
            @include('partials.share-link', ['shareClass' => 'pp-btn pp-btn--line', 'shareAt' => 'journal', 'shareUrl' => site_url($path), 'shareTitle' => $headline])
        </div>
        <p class="jr-post__back"><a href="{{ site_url('/journal') }}"><span class="pp-arrow pp-arrow--back" aria-hidden="true">←</span> {{ bival('journal_all') }}</a></p>
    </main>

    @include('partials.page-foot')
    <script src="{{ asset('engage.js') }}?v={{ filemtime(public_path('engage.js')) }}" defer></script>
    <script src="{{ asset('journal-drawing.js') }}?v={{ filemtime(public_path('journal-drawing.js')) }}" defer></script>
    <script src="{{ asset('glossary.js') }}?v={{ filemtime(public_path('glossary.js')) }}" defer></script>
</body>
</html>
