{{-- The journal's essay on the houses of Erbil Citadel: seven houses and one block, redrawn in one hand from
     the published surveys (resources/journal/citadel-drawings.json: plans, sections, elevations), with what the
     nomination file says about how such a house was planned and built. Every fact has its source below.
     Texts are written here in the three languages: $t(English, Kurdish, Arabic). --}}
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
        'about' => ['@type' => 'LandmarksOrHistoricalBuildings', 'name' => 'Erbil Citadel'],
        'isPartOf' => ['@type' => 'Blog', 'name' => 'Journal', 'url' => site_url('/journal')]];

    $src = [
        'nom' => $t('Nomination file, vol. I', 'دۆسیەی پاڵاوتن، بەرگی یەکەم', 'ملف الترشيح، المجلد الأول'),
        'annex' => $t('Nomination file, vol. II (annexes)', 'دۆسیەی پاڵاوتن، بەرگی دووەم (پاشکۆکان)', 'ملف الترشيح، المجلد الثاني (الملاحق)'),
        'asej' => 'Mustafa & Ali, 2024',
        'mdpi' => 'Goriel, Molnár & Zoltán, 2025',
    ];
    $noScale = $t('Not given in the source', 'لە سەرچاوەکەدا نەدراوە', 'غير مذكور في المصدر');
    $L = [   // the words the legends share
        'court' => $t('Courtyard', 'حەوشە', 'الفناء'),
        'entry' => $t('Entrance from the alley', 'دەروازە لە کۆڵانەوە', 'المدخل من الزقاق'),
        'room' => $t('Room', 'ژوور', 'غرفة'),
        'stair' => $t('Stair', 'پەیژە', 'درج'),
    ];
    $level = [
        'g' => $t('Ground floor', 'نهۆمی زەوی', 'الطابق الأرضي'),
        'b' => $t('Basement (sirdab)', 'ژێرزەمین (سەرداب)', 'السرداب'),
        'f' => $t('First floor', 'نهۆمی یەکەم', 'الطابق الأول'),
    ];
    $view = [
        'el' => $t('Courtyard elevation', 'ڕووکاری حەوشە', 'واجهة الفناء'),
        'sec' => $t('Long section', 'بڕگەی درێژ', 'مقطع طولي'),
        'cross' => $t('Cross section', 'بڕگەی پانی', 'مقطع عرضي'),
    ];

    $plates = [
        [
            'no' => '19/2',
            'name' => $t('House 19/2', 'خانووی ١٩/٢', 'البيت ١٩/٢'),
            'type' => $t('The two-room plan', 'نەخشەی دوو ژووری', 'مخطط الغرفتين'),
            'facts' => [
                [$t('Plan', 'نەخشە', 'المخطط'), $t('Two rooms on one side of a courtyard', 'دوو ژوور لە لایەکی حەوشەدا', 'غرفتان على جهة واحدة من الفناء')],
                [$t('Size', 'قەبارە', 'القياس'), $noScale],
                [$t('Drawn from', 'لەمەوە کێشراوە', 'مرسوم عن'), $src['nom'].', fig. 2.50'],
            ],
            'plans' => [['key' => 'h192', 'level' => $level['g']]],
            'legend' => [
                ['1', $t('The main wing: two rooms, each with its own door', 'باڵی سەرەکی: دوو ژوور، هەر یەکەی بە دەرگای خۆی', 'الجناح الرئيسي: غرفتان، لكلٍّ منهما بابها')],
                ['2', $L['court']], ['3', $L['entry']],
                ['4', $t('Stair to the roof', 'پەیژە بۆ سەربان', 'الدرج إلى السطح')], ['5', $L['room']],
            ],
            'text' => [
                $t('The master plan for the Citadel prints this house as its example of the typical plan, and it is hard to draw a house with less. Two rooms stand side by side across the back of the plot. Each has its own door to the courtyard; neither opens into the other. The entrance is at the far end, straight off the alley.',
                    'ماستەرپلانی قەڵا ئەم خانووە وەک نموونەی نەخشەی باو چاپ دەکات، و ئەستەمە خانوویەک بە کەمتر لەمە بکێشرێت. دوو ژوور شان بە شانی یەک لە پشتەوەی پارچە زەوییەکە وەستاون. هەر یەکەیان دەرگای خۆی هەیە بۆ حەوشە؛ هیچیان بەسەر ئەوی تردا ناکرێتەوە. دەروازەکە لە سەری ئەوبەرەوەیە، ڕاستەوخۆ لە کۆڵانەوە.',
                    'يطبع المخطط الرئيسي للقلعة هذا البيت مثالاً على المخطط الشائع، ويصعب أن يُرسم بيتٌ بأقل منه. غرفتان متجاورتان في آخر القطعة، لكلٍّ منهما بابها على الفناء، ولا تنفتح إحداهما على الأخرى. والمدخل في الطرف المقابل، من الزقاق مباشرة.'),
                $t('Look at the walls. The notches cut into them are niches: a shelf, an ornament, and fewer bricks to buy.',
                    'سەیری دیوارەکان بکە. ئەو بڕینانەی تێیاندا کراون تاقن: ڕەفەیەک، نەخشێک، و خشتی کەمتر بۆ کڕین.',
                    'انظر إلى الجدران. التجاويف المقطوعة فيها كُوًى: رفٌّ وزخرفة، وطابوق أقل يُشترى.'),
            ],
        ],
        [
            'no' => '31/8',
            'name' => $t('Sayid Abdullah Pasha’s house', 'خانووی سەید عەبدوڵڵا پاشا', 'بيت السيد عبد الله باشا'),
            'type' => $t('Three rooms behind a colonnade', 'سێ ژوور لە پشت ڕیزە کۆڵەکەیەک', 'ثلاث غرف خلف صف أعمدة'),
            'facts' => [
                [$t('Plan', 'نەخشە', 'المخطط'), $t('“Three-room plan, one colonnade” (survey form, 2008)', '«نەخشەی سێ ژووری، یەک ڕیزە کۆڵەکە» (فۆرمی ڕووپێو، ٢٠٠٨)', '«مخطط الغرف الثلاث، صف أعمدة واحد» (استمارة المسح، ٢٠٠٨)')],
                [$t('Date', 'مێژوو', 'التاريخ'), $t('Before 1918; one of only two houses dated by an inscription', 'پێش ١٩١٨؛ یەکێک لە تەنها دوو خانوو کە بە نووسراوێک مێژوویان دیاری کراوە', 'قبل ١٩١٨؛ أحد بيتين فقط مؤرَّخين بكتابة')],
                [$t('Size', 'قەبارە', 'القياس'), '12.91 × 14.27 m'],
                [$t('Storeys', 'نهۆم', 'الطوابق'), $t('One', 'یەک', 'واحد')],
                [$t('Drawn from', 'لەمەوە کێشراوە', 'مرسوم عن'), $src['annex'].', annex 9'],
            ],
            'plans' => [['key' => 'h318', 'level' => $level['g']]],
            'legend' => [
                ['1–3', $t('The three rooms, each with its own door to the portico', 'سێ ژوورەکە، هەر یەکەی بە دەرگای خۆی بۆ تارمەکە', 'الغرف الثلاث، لكلٍّ منها بابها على الطارمة')],
                ['4', $t('Portico (tarma) on three columns', 'تارمە لەسەر سێ کۆڵەکە', 'الطارمة على ثلاثة أعمدة')],
                ['5', $L['court']], ['6', $L['entry']],
                ['7', $t('Stairs; one goes to the roof through a segmental arch', 'پەیژەکان؛ یەکێکیان لە ژێر کەوانەیەکەوە دەچێتە سەربان', 'الدرجان؛ أحدهما يصعد إلى السطح عبر قوس')],
                ['8', $t('Bath', 'گەرماو', 'الحمّام')], ['9', $t('WC', 'ئاودەست', 'المرحاض')], ['10', $L['room']],
            ],
            'text' => [
                $t('The same idea with one room more, and a portico. Three rooms in a row fill the far side; in front of them three columns carry a tarma, a roofed strip that shades their doors and windows. You enter from the alley opposite and see the whole house at once.',
                    'هەمان بیرۆکە بە ژوورێکی زیاتر، و تارمەیەک. سێ ژوور بە ڕیز لای ئەوبەر پڕ دەکەنەوە؛ لەبەردەمیاندا سێ کۆڵەکە تارمەیەک هەڵدەگرن، شریتێکی سەرداپۆشراو کە سێبەر دەخاتە سەر دەرگا و پەنجەرەکانیان. لە کۆڵانی بەرامبەرەوە دێیتە ژوورەوە و هەموو خانووەکە بە یەک جار دەبینیت.',
                    'الفكرة نفسها بغرفة زائدة وطارمة. ثلاث غرف في صف تملأ الجهة البعيدة، وأمامها ثلاثة أعمدة تحمل الطارمة، شريطاً مسقوفاً يظلّل أبوابها ونوافذها. تدخل من الزقاق المقابل فترى البيت كله دفعةً واحدة.'),
                $t('The survey notes an inscription inside a niche of the third room, and a lemon tree in the courtyard.',
                    'ڕووپێوەکە تێبینی نووسراوێک دەکات لەناو تاقێکی ژووری سێیەمدا، و درەختێکی لیمۆ لە حەوشەکەدا.',
                    'يسجّل المسح كتابةً داخل كوّة في الغرفة الثالثة، وشجرة ليمون في الفناء.'),
            ],
        ],
        [
            'no' => '30/5',
            'name' => $t('Abdulla Agha’s diwakhana', 'دیوەخانی عەبدوڵڵا ئاغا', 'ديوخانة عبد الله آغا'),
            'type' => $t('Three arcades', 'سێ ڕیزە کەوانە', 'ثلاثة أروقة'),
            'facts' => [
                [$t('Plan', 'نەخشە', 'المخطط'), $t('“Three-arcade plan”; houses 30/5 and 30/4 together', '«نەخشەی سێ ڕیزە کەوانە»؛ خانووەکانی ٣٠/٥ و ٣٠/٤ پێکەوە', '«مخطط الأروقة الثلاثة»؛ البيتان ٣٠/٥ و٣٠/٤ معاً')],
                [$t('Date', 'مێژوو', 'التاريخ'), $t('Before 1918', 'پێش ١٩١٨', 'قبل ١٩١٨')],
                [$t('Grade', 'پلە', 'الدرجة'), $t('1, the highest: eleven buildings in all', '١، بەرزترین: تەنها یازدە بینا', '١، وهي الأعلى: أحد عشر مبنى فقط')],
                [$t('Brickwork', 'خشتکاری', 'البناء بالطابوق'), $t('Irregular bond', 'ڕیزبەندی ناڕێک', 'رباط غير منتظم')],
                [$t('Drawn from', 'لەمەوە کێشراوە', 'مرسوم عن'), $src['annex'].', annex 10'],
            ],
            'plans' => [['key' => 'h305', 'level' => $level['g'].' · '.$t('as existing', 'وەک ئێستا', 'كما هو قائم')]],
            'legend' => [
                ['1', $L['court']], ['2', $t('Entrance', 'دەروازە', 'المدخل')],
                ['3', $t('East arcade', 'ڕیزە کەوانەی ڕۆژهەڵات', 'الرواق الشرقي')], ['4', $t('South arcade', 'ڕیزە کەوانەی باشوور', 'الرواق الجنوبي')],
                ['5', $t('The main room (qa‘a)', 'ژووری سەرەکی (قاعە)', 'الغرفة الرئيسية (القاعة)')],
                ['6', $t('Rooms behind the arcades', 'ژوورەکانی پشت ڕیزە کەوانەکان', 'الغرف خلف الأروقة')],
                ['7', $L['room']],
                ['8', $t('Modern, roofless room', 'ژوورێکی نوێی بێ سەربان', 'غرفة حديثة بلا سقف')],
                ['9', $t('Second, smaller courtyard', 'حەوشەی دووەم، بچووکتر', 'فناء ثانٍ أصغر')],
            ],
            'text' => [
                $t('A diwakhana was a house for receiving. After the afternoon prayer its door stood open, and twenty or thirty people at a time came in to sit, talk and settle matters. The nomination file calls the type “very specific to Erbil”.',
                    'دیوەخان خانوویەک بوو بۆ پێشوازی. دوای نوێژی عەسر دەرگاکەی کراوە دەبوو، و بیست تا سی کەس پێکەوە دەهاتنە ژوورەوە بۆ دانیشتن و قسەکردن و یەکلاکردنەوەی کاروبار. دۆسیەی پاڵاوتن ئەم جۆرە بە «زۆر تایبەت بە هەولێر» ناو دەبات.',
                    'الديوخانة بيتٌ للاستقبال. بعد صلاة العصر يُفتح بابها فيدخل عشرون أو ثلاثون شخصاً في المرة ليجلسوا ويتحدثوا ويفصلوا في الأمور. يصف ملف الترشيح هذا النوع بأنه «خاص جداً بأربيل».'),
                $t('So the plan is the small house grown large. The rooms still line the edges and still open one by one, but now arcades run in front of them on the east and the south, and the best room, the qa‘a, sits behind the south arcade.',
                    'بۆیە نەخشەکە هەمان خانووە بچووکەکەیە کە گەورە بووە. ژوورەکان هێشتا بە لێوارەکاندا ڕیز بوون و هێشتا یەک بە یەک دەکرێنەوە، بەڵام ئێستا ڕیزە کەوانە لەبەردەمیاندا لە ڕۆژهەڵات و باشوور دەڕوات، و باشترین ژوور، قاعەکە، لە پشت ڕیزە کەوانەی باشوورە.',
                    'فالمخطط إذن هو البيت الصغير وقد كبر. ما زالت الغرف تصطف على الأطراف وتنفتح واحدةً واحدة، لكنّ الأروقة تمتد الآن أمامها شرقاً وجنوباً، وأفضل الغرف، القاعة، تقع خلف الرواق الجنوبي.'),
            ],
        ],
        [
            'no' => '2/2',
            'name' => $t('Rashid Agha’s diwakhana', 'دیوەخانی ڕەشید ئاغا', 'ديوخانة رشيد آغا'),
            'type' => $t('A mansion over its basement', 'کۆشکێک لەسەر سەردابەکەی', 'قصر فوق سردابه'),
            'facts' => [
                [$t('Date', 'مێژوو', 'التاريخ'), $t('Built 1903–5; dated by an inscription', 'لە ١٩٠٣–١٩٠٥ دروست کراوە؛ بە نووسراوێک مێژووی دیاری کراوە', 'بُني ١٩٠٣–١٩٠٥؛ مؤرَّخ بكتابة')],
                [$t('Size', 'قەبارە', 'القياس'), '12.8 × 23.0 m'],
                [$t('Levels drawn', 'ئەو نهۆمانەی کێشراون', 'الطوابق المرسومة'), $level['g'].', '.$level['b']],
                [$t('Drawn from', 'لەمەوە کێشراوە', 'مرسوم عن'), $src['asej'].', table 4 (C1)'],
            ],
            'plans' => [
                ['key' => 'rashid-g', 'level' => $level['g']],
                ['key' => 'rashid-b', 'level' => $level['b']],
            ],
            'secs' => [['key' => 'rashid-el', 'level' => $view['el']], ['key' => 'rashid-sec', 'level' => $view['sec']]],
            'legend' => [
                ['1', $L['court']], ['2', $t('Basin', 'حەوز', 'الحوض')],
                ['3', $t('Portico before the main rooms, reached by steps at either end', 'تارمە لەبەردەم ژوورە سەرەکییەکان، بە پلە لە هەردوو سەرەوە دەچیتە سەری', 'طارمة أمام الغرف الرئيسية، يُصعد إليها بدرجات عند طرفيها')],
                ['4', $t('Sirdab under the main range', 'سەرداب لە ژێر باڵی سەرەکی', 'السرداب تحت الجناح الرئيسي')],
            ],
            'text' => [
                $t('One of the five best-known diwakhanas of the Citadel, and one of the two houses that carry their own date. The plot is long and tapers, so the courtyard does too; a basin marks its middle.',
                    'یەکێک لە پێنج دیوەخانە ناسراوەکەی قەڵا، و یەکێک لەو دوو خانووەی کە مێژووی خۆیان هەڵگرتووە. پارچە زەوییەکە درێژە و باریک دەبێتەوە، بۆیە حەوشەکەش هەروا؛ حەوزێک ناوەڕاستەکەی دیاری دەکات.',
                    'إحدى أشهر خمس ديوخانات في القلعة، وأحد البيتين اللذين يحملان تاريخهما. القطعة طويلة وتضيق، وكذلك الفناء؛ وفي وسطه حوض.'),
                $t('The main rooms stand at the head of the court, above a sirdab. Compare the two plans: the basement repeats only the main range, a cool room for summer directly beneath it.',
                    'ژوورە سەرەکییەکان لە سەری حەوشەکەدان، لەسەر سەردابێک. دوو نەخشەکە بەراورد بکە: ژێرزەمینەکە تەنها باڵی سەرەکی دووبارە دەکاتەوە، ژوورێکی فێنک بۆ هاوین ڕاستەوخۆ لە ژێریدا.',
                    'الغرف الرئيسية في رأس الفناء، فوق سرداب. قارن بين المسقطين: السرداب يكرر الجناح الرئيسي وحده، غرفة باردة للصيف تحته مباشرة.'),
            ],
        ],
        [
            'no' => 'C2',
            'name' => $t('Salim Qaqa’s house', 'خانووی سەلیم قاقە', 'بيت سليم قاقا'),
            'type' => $t('Thick walls, a court in the middle', 'دیواری ئەستوور، حەوشەیەک لە ناوەڕاست', 'جدران سميكة وفناء في الوسط'),
            'facts' => [
                [$t('Date', 'مێژوو', 'التاريخ'), $t('Late Ottoman period', 'سەردەمی کۆتایی عوسمانی', 'أواخر العهد العثماني')],
                [$t('Size', 'قەبارە', 'القياس'), '11.4 × 18.1 m'],
                [$t('Levels drawn', 'ئەو نهۆمانەی کێشراون', 'الطوابق المرسومة'), $level['g'].', '.$level['b']],
                [$t('Drawn from', 'لەمەوە کێشراوە', 'مرسوم عن'), $src['asej'].', table 4 (C2)'],
            ],
            'plans' => [
                ['key' => 'salim-g', 'level' => $level['g']],
                ['key' => 'salim-b', 'level' => $level['b']],
            ],
            'secs' => [['key' => 'salim-long', 'level' => $view['sec']], ['key' => 'salim-cross', 'level' => $view['cross']]],
            'legend' => [
                ['1', $L['court']],
                ['2', $t('The two large rooms at the head of the plot', 'دوو ژوورە گەورەکەی سەری پارچە زەوییەکە', 'الغرفتان الكبيرتان في رأس القطعة')],
            ],
            'text' => [
                $t('Here the walls say the most. One side wall is far thicker than any other, and the long wall at the head of the plot is cut by a row of niches, one beside the next.',
                    'لێرەدا دیوارەکان زۆرترین شت دەڵێن. دیوارێکی لاتەنیشت زۆر لە هەموو ئەوانی تر ئەستوورترە، و دیوارە درێژەکەی سەری پارچە زەوییەکە بە ڕیزێک تاق بڕاوە، یەک لە تەنیشت ئەوی تر.',
                    'هنا تقول الجدران أكثر من غيرها. أحد الجدران الجانبية أسمك كثيراً من سواه، والجدار الطويل في رأس القطعة مقطوع بصف من الكُوى، واحدة إلى جانب الأخرى.'),
                $t('Two large rooms take the far end, the two-room plan again. The courtyard sits in the middle of the plot with rooms on three sides, and the basement runs under nearly all of it.',
                    'دوو ژووری گەورە سەری ئەوبەریان گرتووە، دیسان نەخشەی دوو ژووری. حەوشەکە لە ناوەڕاستی پارچە زەوییەکەدایە و لە سێ لاوە ژووری لێیە، و ژێرزەمینەکە بە ژێر نزیکەی هەموویدا دەڕوات.',
                    'غرفتان كبيرتان تحتلان الطرف البعيد، مخطط الغرفتين مرة أخرى. الفناء في وسط القطعة تحيط به الغرف من ثلاث جهات، والسرداب يمتد تحت معظمه.'),
            ],
        ],
        [
            'no' => 'C3',
            'name' => $t('Salih Chalabi’s house', 'خانووی ساڵح چەلەبی', 'بيت صالح چلبي'),
            'type' => $t('Three rooms, an arcade, two wings', 'سێ ژوور، ڕیزە کەوانەیەک، دوو باڵ', 'ثلاث غرف ورواق وجناحان'),
            'facts' => [
                [$t('Date', 'مێژوو', 'التاريخ'), $t('Late Ottoman period', 'سەردەمی کۆتایی عوسمانی', 'أواخر العهد العثماني')],
                [$t('Size', 'قەبارە', 'القياس'), $noScale],
                [$t('Levels drawn', 'ئەو نهۆمانەی کێشراون', 'الطوابق المرسومة'), $level['b'].', '.$level['g'].', '.$level['f']],
                [$t('Drawn from', 'لەمەوە کێشراوە', 'مرسوم عن'), $src['asej'].', table 4 (C3)'],
            ],
            'plans' => [
                ['key' => 'salih-b', 'level' => $level['b']],
                ['key' => 'salih-g', 'level' => $level['g']],
                ['key' => 'salih-f', 'level' => $level['f']],
            ],
            'secs' => [['key' => 'salih-long', 'level' => $view['sec']], ['key' => 'salih-cross', 'level' => $view['cross']]],
            'legend' => [
                ['1', $t('The main range: three rooms', 'باڵی سەرەکی: سێ ژوور', 'الجناح الرئيسي: ثلاث غرف')],
                ['2', $t('Arcade before the main range', 'ڕیزە کەوانە لەبەردەم باڵی سەرەکی', 'الرواق أمام الجناح الرئيسي')],
                ['3', $L['court']],
                ['4', $t('The wings, one on each side of the court', 'باڵەکان، یەک لە هەر لایەکی حەوشە', 'الجناحان، واحد على كل جانب من الفناء')],
                ['5', $t('Curved stair', 'پەیژەی چەماوە', 'درج منحنٍ')],
            ],
            'text' => [
                $t('This is the mansion plan the nomination file describes: a main range of three rooms, with a wing down each side of the courtyard. An arcade stands in front of the three rooms, and the court sits at the centre.',
                    'ئەمە ئەو نەخشەی کۆشکەیە کە دۆسیەی پاڵاوتن باسی دەکات: باڵێکی سەرەکی لە سێ ژوور، و باڵێک بە هەر لایەکی حەوشەدا. ڕیزە کەوانەیەک لەبەردەم سێ ژوورەکە وەستاوە، و حەوشەکە لە ناوەڕاستدایە.',
                    'هذا هو مخطط القصر الذي يصفه ملف الترشيح: جناح رئيسي من ثلاث غرف، وجناح على كل جانب من الفناء. يقف رواق أمام الغرف الثلاث، والفناء في الوسط.'),
                $t('Read the three levels together. The basement lies under the main range and one wing only. The first floor repeats the ground floor almost wall for wall, because every wall above needs a wall below.',
                    'سێ نهۆمەکە پێکەوە بخوێنەوە. ژێرزەمینەکە تەنها لە ژێر باڵی سەرەکی و یەکێک لە باڵەکاندایە. نهۆمی یەکەم نزیکەی دیوار بە دیوار نهۆمی زەوی دووبارە دەکاتەوە، چونکە هەر دیوارێکی سەرەوە پێویستی بە دیوارێکە لە خوارەوە.',
                    'اقرأ الطوابق الثلاثة معاً. السرداب تحت الجناح الرئيسي وأحد الجناحين فقط. والطابق الأول يكرر الأرضي جداراً بجدار تقريباً، فكل جدار في الأعلى يحتاج إلى جدار تحته.'),
            ],
        ],
        [
            'no' => 'HC',
            'name' => $t('Hashim Chalabi’s house', 'خانووی هاشم چەلەبی', 'بيت هاشم چلبي'),
            'type' => $t('A house on the edge', 'خانوویەک لەسەر لێوار', 'بيت على الحافة'),
            'facts' => [
                [$t('Today', 'ئەمڕۆ', 'اليوم'), $t('A museum', 'مۆزەخانەیەک', 'متحف')],
                [$t('Size', 'قەبارە', 'القياس'), $noScale],
                [$t('Drawn from', 'لەمەوە کێشراوە', 'مرسوم عن'), $src['mdpi'].', fig. 8b'],
                [$t('Note', 'تێبینی', 'ملاحظة'), $t('A diagram of spaces, not a wall survey: the solid shows everything the study did not map as a room.', 'هێڵکارییەکی بۆشاییەکانە، نەک ڕووپێوی دیوار: بەشە پڕەکە هەموو ئەوە پیشان دەدات کە توێژینەوەکە وەک ژوور نەیکێشاوە.', 'مخطط للفراغات لا مسحٌ للجدران: الجزء المصمت يمثل كل ما لم تُدرجه الدراسة غرفةً.')],
            ],
            'plans' => [['key' => 'hashim', 'level' => $level['g'], 'wide' => true]],
            'legend' => [
                ['1', $t('Entrance passage: you turn before you see the court', 'ڕاڕەوی دەروازە: پێش بینینی حەوشە دەسووڕێیتەوە', 'ممر المدخل: تنعطف قبل أن ترى الفناء')],
                ['2', $L['court']],
                ['3', $t('Rooms, as the study maps them', 'ژوورەکان، وەک توێژینەوەکە کێشاونی', 'الغرف كما رسمتها الدراسة')],
            ],
            'text' => [
                $t('Not every plot is a rectangle. This house stands where the alleys bend toward the rim of the mound, and its outline follows them. Inside, the rule holds anyway: one open court, and the rooms pressed against the boundary around it.',
                    'هەموو پارچە زەوییەک لاکێشە نییە. ئەم خانووە لەو شوێنەدایە کە کۆڵانەکان بەرەو لێواری گردەکە دەچەمێنەوە، و شێوەکەی بەدوایاندا دەڕوات. لە ناوەوە، یاساکە هەر دەمێنێت: یەک حەوشەی کراوە، و ژوورەکان بە دەوریدا بە سنوورەکەوە نووساون.',
                    'ليست كل قطعة مستطيلاً. يقف هذا البيت حيث تنحني الأزقة نحو حافة التل، وحدوده تتبعها. وفي الداخل تبقى القاعدة: فناء واحد مفتوح، والغرف ملتصقة بالحدود حوله.'),
                $t('The entrance is at the narrow tip, and it does not lead straight in.',
                    'دەروازەکە لە نووکە باریکەکەدایە، و ڕاستەوخۆ ناچێتە ژوورەوە.',
                    'المدخل عند الطرف الضيق، ولا يقود إلى الداخل مباشرة.'),
            ],
        ],
    ];
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
                <div><dt>{{ $t('Houses', 'خانوو', 'بيوت') }}</dt><dd>7</dd></div>
                <div><dt>{{ $t('Plans', 'نەخشە', 'مساقط') }}</dt><dd>12</dd></div>
                <div><dt>{{ $t('One block of', 'یەک بلۆک لە', 'بلوك واحد من') }}</dt><dd>13</dd></div>
                <div><dt>{{ $t('Built', 'دروستکراو', 'بُنيت') }}</dt><dd dir="ltr">c. 1750–1930</dd></div>
            </dl>
        </header>

        <section class="ja-text">
            <h2 class="ja-h">{{ $t('A town of courtyards', 'شارێک لە حەوشە', 'مدينة من الأفنية') }}</h2>
            <p>{{ $t('Erbil Citadel is a town standing on its own past. People have lived on the same spot for thousands of years, and what they left behind is the mound itself, some thirty metres high, with houses on its flat top. The inventory begun there in 2008 with UNESCO counted 588 buildings and plots. Of these, 320 are graded historic houses and eleven are grand mansions. Almost every one of them has a courtyard.',
                'قەڵای هەولێر شارێکە لەسەر ڕابردووی خۆی وەستاوە. خەڵک هەزاران ساڵە لە هەمان شوێن ژیاون، و ئەوەی بەجێیان هێشتووە خودی گردەکەیە، نزیکەی سی مەتر بەرز، و خانوو لەسەر ڕووە تەختەکەی. ئەو تۆمارکردنەی لە ٢٠٠٨ لەگەڵ یونسکۆ دەستی پێکرد ٥٨٨ بینا و پارچە زەویی ژمارد. لەمانە ٣٢٠ خانووی مێژوویی پلەبەندکراون و یازدەیان کۆشکی گەورەن. نزیکەی هەموویان حەوشەیان هەیە.',
                'قلعة أربيل مدينة تقوم على ماضيها. سكن الناس الموضع نفسه آلاف السنين، وما خلّفوه هو التل نفسه، بارتفاع نحو ثلاثين متراً، وعلى سطحه المستوي البيوت. الجرد الذي بدأ هناك عام ٢٠٠٨ مع اليونسكو أحصى ٥٨٨ مبنى وقطعة. منها ٣٢٠ بيتاً تاريخياً مصنّفاً وأحد عشر قصراً كبيراً. ويكاد لا يخلو بيت منها من فناء.') }}</p>
            <p>{{ $t('Most of what you can see was built between the middle of the nineteenth century and the first half of the twentieth. The oldest houses may go back to the middle of the eighteenth, when the town had to be rebuilt after Nader Shah’s siege of 1743. No house belongs to a single moment: neighbours share their walls, and each side has been rebuilt again and again.',
                'زۆربەی ئەوەی دەیبینیت لە نێوان ناوەڕاستی سەدەی نۆزدە و نیوەی یەکەمی سەدەی بیستدا دروست کراوە. کۆنترین خانووەکان لەوانەیە بگەڕێنەوە بۆ ناوەڕاستی سەدەی هەژدە، کاتێک شارەکە دوای گەمارۆی نادر شا لە ١٧٤٣ دەبوو دووبارە بنیات بنرێتەوە. هیچ خانوویەک هی یەک ساتە نییە: دراوسێکان دیواریان هاوبەشە، و هەر لایەک جار لە دوای جار دووبارە دروست کراوەتەوە.',
                'معظم ما تراه بُني بين منتصف القرن التاسع عشر والنصف الأول من القرن العشرين. وقد تعود أقدم البيوت إلى منتصف القرن الثامن عشر، حين وجب إعادة بناء المدينة بعد حصار نادر شاه عام ١٧٤٣. ولا ينتمي بيتٌ إلى لحظة واحدة: الجيران يتقاسمون الجدران، وكل جانب أُعيد بناؤه مرة بعد مرة.') }}</p>
            <p class="ja-note">{{ $t('A word on the drawings. These plans were not measured by us. They were surveyed by the High Commission for Erbil Citadel Revitalization and its consultants, and by researchers who published them. We traced the walls from those published drawings and redrew them in one hand, so the houses can be read side by side. Each plate names its source.',
                'وشەیەک دەربارەی نەخشەکان. ئەم نەخشانە ئێمە نەمانپێواون. دەستەی باڵای زیندووکردنەوەی قەڵای هەولێر و ڕاوێژکارەکانی، و ئەو توێژەرانەی بڵاویان کردوونەتەوە، ڕووپێویان کردوون. ئێمە دیوارەکانمان لەو نەخشە بڵاوکراوانەوە هەڵگرتووەتەوە و بە یەک دەست دووبارە کێشاومانەتەوە، تا خانووەکان شان بە شانی یەک بخوێنرێنەوە. هەر تابلۆیەک سەرچاوەکەی ناو دەبات.',
                'كلمة عن الرسومات. هذه المخططات لم نقِسها نحن. مسحتها الهيئة العليا لإحياء قلعة أربيل ومستشاروها، والباحثون الذين نشروها. نحن نقلنا الجدران عن تلك الرسومات المنشورة وأعدنا رسمها بيد واحدة، لتُقرأ البيوت جنباً إلى جنب. وكل لوحة تذكر مصدرها.') }}</p>
        </section>

        <section class="ja-text">
            <h2 class="ja-h">{{ $t('One idea, many houses', 'یەک بیرۆکە، چەندین خانوو', 'فكرة واحدة وبيوت كثيرة') }}</h2>
            <blockquote class="ja-quote">
                <p>{{ $t('“The most common plan, for houses of whatever date, features a main wing with two or three rooms entered by separate doors.”',
                    '«باوترین نەخشە، بۆ خانووی هەر سەردەمێک، باڵێکی سەرەکیی هەیە لە دوو یان سێ ژوور کە بە دەرگای جیا دەچیتە ناویان.»',
                    '«المخطط الأكثر شيوعاً، للبيوت من أي تاريخ كانت، جناحٌ رئيسي من غرفتين أو ثلاث يُدخل إليها من أبواب منفصلة.»') }}</p>
                <cite>{{ $t('Nomination of Erbil Citadel to the World Heritage List', 'پاڵاوتنی قەڵای هەولێر بۆ لیستی میراتی جیهانی', 'ترشيح قلعة أربيل لقائمة التراث العالمي') }}</cite>
            </blockquote>
            <p>{{ $t('That one sentence explains almost every plan below. The rooms do not lead into each other. Each has its own door, and every door opens to the same open space. A family could add a room without disturbing the ones it already had, and the courtyard did the work of a corridor, a living room and a garden.',
                'ئەو یەک ڕستەیە نزیکەی هەموو نەخشەکانی خوارەوە ڕوون دەکاتەوە. ژوورەکان ناچنە ناو یەکتر. هەر یەکەیان دەرگای خۆی هەیە، و هەموو دەرگاکان بەسەر هەمان بۆشایی کراوەدا دەکرێنەوە. خێزان دەیتوانی ژوورێک زیاد بکات بێ ئەوەی دەست لە ژوورەکانی پێشووی بدات، و حەوشەکە کاری ڕاڕەو و ژووری دانیشتن و باخچەی دەکرد.',
                'هذه الجملة الواحدة تفسّر تقريباً كل مخطط في الأسفل. الغرف لا تفضي إحداها إلى الأخرى. لكلٍّ بابها، وكل الأبواب تنفتح على الفراغ المفتوح نفسه. تستطيع الأسرة أن تضيف غرفة دون أن تمسّ ما لديها، والفناء يقوم مقام الممر وغرفة الجلوس والحديقة.') }}</p>

            {{-- four diagrams of the idea growing: ours, drawn to explain, not surveys (journal/type) --}}
            <ol class="ja-types">
                <li>
                    @include('journal.type', ['id' => 'ty1', 'tp' => ['holes' => [[22, 20, 75, 40], [103, 20, 75, 40], [22, 66, 156, 64]], 'court' => [22, 66, 156, 64],
                        'doors' => [[52, 60, 12, 'h', -1], [127, 60, 12, 'h', -1], [94, 130, 12, 'h', -1]], 'wins' => [[30, 60, 14, 'h'], [158, 60, 14, 'h']],
                        'words' => [[59.5, 43, 'room'], [140.5, 43, 'room'], [100, 101, 'court']]]])
                    <strong>{{ $t('Two rooms', 'دوو ژوور', 'غرفتان') }}</strong>
                    <span>{{ $t('The plan in its simplest form.', 'نەخشەکە لە سادەترین شێوەیدا.', 'المخطط في أبسط صوره.') }}</span>
                </li>
                <li>
                    @include('journal.type', ['id' => 'ty2', 'tp' => ['holes' => [[22, 20, 48, 36], [76, 20, 48, 36], [130, 20, 48, 36], [22, 62, 156, 68]], 'court' => [22, 86, 156, 44],
                        'doors' => [[40, 56, 12, 'h', -1], [94, 56, 12, 'h', -1], [148, 56, 12, 'h', -1], [94, 130, 12, 'h', -1]],
                        'wins' => [[26, 56, 10, 'h'], [56, 56, 10, 'h'], [80, 56, 10, 'h'], [110, 56, 10, 'h'], [134, 56, 10, 'h'], [164, 56, 10, 'h']],
                        'cols' => [[49, 86], [73, 86], [127, 86], [151, 86]], 'over' => 'M22 86H178', 'words' => [[100, 77, 'tarma'], [100, 112, 'court']]]])
                    <strong>{{ $t('Three rooms and a tarma', 'سێ ژوور و تارمەیەک', 'ثلاث غرف وطارمة') }}</strong>
                    <span>{{ $t('Once rooms had large windows, the front needed shade.', 'کە ژوورەکان پەنجەرەی گەورەیان بوو، ڕووکارەکە پێویستی بە سێبەر بوو.', 'حين صارت للغرف نوافذ كبيرة احتاجت الواجهة إلى الظل.') }}</span>
                </li>
                <li>
                    @include('journal.type', ['id' => 'ty3', 'tp' => ['holes' => [[22, 20, 56, 40], [122, 20, 56, 40], [[22, 66], [84, 66], [84, 20], [116, 20], [116, 66], [178, 66], [178, 130], [22, 130]]], 'court' => [22, 66, 156, 64],
                        'doors' => [[44, 60, 12, 'h', -1], [144, 60, 12, 'h', -1], [94, 130, 12, 'h', -1]], 'wins' => [[28, 60, 10, 'h'], [162, 60, 10, 'h']],
                        'over' => 'M84 66H116M84 58Q100 46 116 58', 'words' => [[100, 39, 'iwan'], [100, 101, 'court']]]])
                    <strong>{{ $t('An iwan between two rooms', 'ئەیوانێک لە نێوان دوو ژوور', 'إيوان بين غرفتين') }}</strong>
                    <span>{{ $t('The larger early houses: a room with no front wall.', 'خانووە گەورە کۆنەکان: ژوورێک بێ دیواری پێشەوە.', 'البيوت الكبيرة المبكرة: غرفة بلا جدار أمامي.') }}</span>
                </li>
                <li>
                    @include('journal.type', ['id' => 'ty4', 'tp' => ['holes' => [[22, 20, 48, 32], [76, 20, 48, 32], [130, 20, 48, 32], [22, 58, 34, 72], [144, 58, 34, 72], [62, 58, 76, 72]], 'court' => [62, 74, 76, 56],
                        'doors' => [[40, 52, 12, 'h', -1], [94, 52, 12, 'h', -1], [148, 52, 12, 'h', -1], [56, 92, 12, 'v', -1], [138, 92, 12, 'v', 1], [94, 130, 12, 'h', -1]],
                        'wins' => [[26, 52, 10, 'h'], [56, 52, 10, 'h'], [80, 52, 10, 'h'], [110, 52, 10, 'h'], [134, 52, 10, 'h'], [164, 52, 10, 'h'], [56, 70, 12, 'v'], [56, 112, 12, 'v'], [138, 70, 12, 'v'], [138, 112, 12, 'v']],
                        'cols' => [[81, 74], [100, 74], [119, 74]], 'over' => 'M62 74H138', 'words' => [[100, 66.5, 'arcade'], [100, 106, 'court']]]])
                    <strong>{{ $t('A main range with wings', 'باڵێکی سەرەکی لەگەڵ دوو باڵ', 'جناح رئيسي بجناحين') }}</strong>
                    <span>{{ $t('The late-Ottoman mansion.', 'کۆشکی کۆتایی عوسمانی.', 'القصر العثماني المتأخر.') }}</span>
                </li>
            </ol>
            <p class="ja-cap">{{ $t('Diagrams, not surveys: the same idea at four sizes.', 'هێڵکاری، نەک ڕووپێو: هەمان بیرۆکە بە چوار قەبارە.', 'رسوم توضيحية لا مسوحات: الفكرة نفسها بأربعة أحجام.') }}</p>

            <dl class="ja-terms">
                <div><dt><a href="{{ site_url('/journal/glossary') }}#iwan">{{ $t('Iwan', 'ئەیوان', 'الإيوان') }}</a></dt><dd>{{ $t('A room without a front wall. The larger early houses have two rooms on either side of one.', 'ژوورێک بێ دیواری پێشەوە. خانووە گەورە کۆنەکان دوو ژووریان لە هەردوو لای یەکێکیان هەیە.', 'غرفة بلا جدار أمامي. في البيوت الكبيرة المبكرة غرفتان على جانبيه.') }}</dd></div>
                <div><dt><a href="{{ site_url('/journal/glossary') }}#tarma">{{ $t('Tarma', 'تارمە', 'الطارمة') }}</a></dt><dd>{{ $t('A portico along the front of the rooms. Early houses had only small vents and stayed dark, so their fronts needed no shade. When large windows came in the late-Ottoman period, the tarma became essential: on timber columns with carved capitals, on arcades of Mosul alabaster, and later on iron columns.', 'ڕاڕەوێکی سەرداپۆشراو بە درێژایی پێشەوەی ژوورەکان. خانووە کۆنەکان تەنها کونی هەواگۆڕکێی بچووکیان هەبوو و تاریک بوون، بۆیە ڕووکاریان پێویستی بە سێبەر نەبوو. کاتێک پەنجەرەی گەورە لە کۆتایی سەردەمی عوسمانیدا هات، تارمە پێویست بوو: لەسەر کۆڵەکەی دار بە سەرکۆڵەکەی هەڵکۆڵراو، لەسەر ڕیزە کەوانەی مەڕمەڕی مووسڵ، و دواتر لەسەر کۆڵەکەی ئاسن.', 'رواق مسقوف على امتداد واجهة الغرف. لم يكن للبيوت المبكرة سوى فتحات تهوية صغيرة وكانت معتمة، فلم تحتج واجهاتها إلى الظل. وحين جاءت النوافذ الكبيرة في أواخر العهد العثماني صارت الطارمة ضرورة: على أعمدة خشبية بتيجان منحوتة، أو على عقود من مرمر الموصل، ثم على أعمدة حديدية.') }}</dd></div>
                <div><dt><a href="{{ site_url('/journal/glossary') }}#sirdab">{{ $t('Sirdab', 'سەرداب', 'السرداب') }}</a></dt><dd>{{ $t('A basement. Commonest in the houses on the rim, which stand on the slope and get one almost for free. Inside the town only the later houses have them, and sometimes only under the tarma.', 'ژێرزەمین. زۆرتر لە خانووەکانی لێواردا هەیە، کە لەسەر لێژییەکە وەستاون و نزیکەی بەخۆڕایی دەستیان دەکەوێت. لە ناو شارەکەدا تەنها خانووە درەنگترەکان هەیانە، و هەندێک جار تەنها لە ژێر تارمەکە.', 'طابق تحت الأرض. أكثر شيوعاً في بيوت الحافة القائمة على المنحدر، فتحصل عليه بلا كلفة تقريباً. وفي داخل المدينة لا يوجد إلا في البيوت المتأخرة، وأحياناً تحت الطارمة فقط.') }}</dd></div>
                <div><dt><a href="{{ site_url('/journal/glossary') }}#diwakhana">{{ $t('Diwakhana', 'دیوەخان', 'الديوخانة') }}</a></dt><dd>{{ $t('A house, or a room in a house, kept for receiving guests.', 'خانوویەک، یان ژوورێک لە خانوویەکدا، کە بۆ پێشوازی لە میوان تەرخان کراوە.', 'بيت، أو غرفة في بيت، مخصصة لاستقبال الضيوف.') }}</dd></div>
            </dl>
        </section>

        <section class="ja-plates">
            <h2 class="ja-h">{{ $t('The plates', 'تابلۆکان', 'اللوحات') }}</h2>
            <p class="ja-plates__key">{{ $t('Every plan, section and elevation here is redrawn by us from the published surveys: walls, doors with their swings, windows, stairs and fixtures, at one scale per house. Areas are measured off the drawings and are approximate. Where a survey gives no scale or north, we draw none.', 'هەموو نەخشە و بڕگە و ڕووکارێک لێرەدا لە ڕووپێوە بڵاوکراوەکانەوە لەلایەن ئێمەوە دووبارە کێشراوەتەوە: دیوار، دەرگا لەگەڵ ڕێڕەوی کرانەوەیان، پەنجەرە، پەیژە و کەلوپەل، بە یەک پێوەر بۆ هەر خانوویەک. ڕووبەرەکان لەسەر نەخشەکان پێوراون و نزیکەیین. لە شوێنێک ڕووپێوەکە پێوەر یان باکووری نەدابێت، ئێمەش نایکێشین.', 'كلّ مسقط ومقطع وواجهة هنا أعدنا رسمه عن المسوح المنشورة: الجدران والأبواب بمسار فتحها والنوافذ والدرج والتجهيزات، بمقياس واحد لكلّ بيت. المساحات مقيسة على الرسوم وتقريبية. وحيث لا يعطي المسح مقياسًا أو اتجاه الشمال لا نرسمهما.') }}</p>
            <button type="button" class="pp-btn pp-btn--line ja-print" data-print-plates>{{ $t('Print the plates (A3, or save as PDF)', 'چاپی تابلۆکان (A3، یان پاشەکەوت وەک PDF)', 'اطبع اللوحات (A3، أو احفظها PDF)') }}</button>

            @foreach ($plates as $i => $plate)
                <article class="ja-plate" id="plate-{{ $i + 1 }}">
                    <header class="ja-plate__head">
                        <span class="ja-plate__code" dir="ltr">CH-{{ sprintf('%02d', $i + 1) }}</span>
                        <h3 class="ja-plate__name">{{ $plate['name'] }}</h3>
                        <p class="ja-plate__type">{{ $plate['type'] }}</p>
                    </header>
                    <div class="ja-plate__sheet ja-plate__sheet--{{ count($plate['plans']) }}">
                        @php $widest = max(array_map(fn ($p) => $dwgs[$p['key']]['vb'][2], $plate['plans'])); @endphp
                        @foreach ($plate['plans'] as $p)
                            <figure class="ja-plate__fig{{ ! empty($p['wide']) ? ' ja-plate__fig--wide' : '' }}">
                                @include('journal.drawing', ['dwg' => $dwgs[$p['key']], 'id' => 'dw-'.$p['key'], 'name' => $plate['name'].' — '.$p['level'], 'rel' => $dwgs[$p['key']]['vb'][2] / $widest])
                                <figcaption>{{ $p['level'] }}</figcaption>
                            </figure>
                        @endforeach
                    </div>
                    @if (! empty($plate['secs']))
                        {{-- sections and elevations redrawn from the same survey; columns as wide as the drawings, so all are at one scale --}}
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
                            <div><dt>{{ $t('House', 'خانوو', 'البيت') }}</dt><dd dir="ltr">{{ $plate['no'] }}</dd></div>
                            @foreach ($plate['facts'] as [$k, $v])
                                <div><dt>{{ $k }}</dt><dd><bdi>{{ $v }}</bdi></dd></div>
                            @endforeach
                        </dl>
                    </div>
                    <p class="ja-plate__foot"><span>Aram Mizuri Architecture — {{ $headline }}</span><span dir="ltr">{{ site_url($path) }} · {{ $i + 1 }} / {{ count($plates) + 1 }}</span></p>
                </article>
            @endforeach

            {{-- the block: thirteen houses in one drawing --}}
            <article class="ja-plate" id="plate-8">
                <header class="ja-plate__head">
                    <span class="ja-plate__code" dir="ltr">CH-08</span>
                    <h3 class="ja-plate__name">{{ $t('Block 45', 'بلۆکی ٤٥', 'البلوك ٤٥') }}</h3>
                    <p class="ja-plate__type">{{ $t('Thirteen houses, one fabric', 'سێزدە خانوو، یەک پێکهاتە', 'ثلاثة عشر بيتاً ونسيج واحد') }}</p>
                </header>
                <div class="ja-plate__sheet ja-plate__sheet--1">
                    <figure class="ja-plate__fig ja-plate__fig--wide">
                        @include('journal.drawing', ['dwg' => $dwgs['b45'], 'id' => 'dw-b45', 'name' => $t('Block 45', 'بلۆکی ٤٥', 'البلوك ٤٥')])
                        <figcaption>{{ $level['g'] }}</figcaption>
                    </figure>
                </div>
                <div class="ja-plate__body">
                    <div class="ja-plate__text">
                        <p>{{ $t('No house on the Citadel stands alone. This is one block out of fifty-eight, drawn as the master plan surveyed it: thirteen houses with shared walls, their courtyards locked together like the pieces of a puzzle. The alleys run around the block and never through it.',
                            'هیچ خانوویەک لە قەڵادا بە تەنیا نەوەستاوە. ئەمە یەک بلۆکە لە پەنجا و هەشت، وەک ماستەرپلانەکە ڕووپێوی کردووە: سێزدە خانوو بە دیواری هاوبەش، حەوشەکانیان وەک پارچەکانی مەتەڵێک تێکەڵ بە یەک بوون. کۆڵانەکان بە دەوری بلۆکەکەدا دەڕۆن و هەرگیز بە ناویدا ناڕۆن.',
                            'لا يقف بيتٌ في القلعة وحده. هذا بلوك واحد من ثمانية وخمسين، مرسوم كما مسحه المخطط الرئيسي: ثلاثة عشر بيتاً بجدران مشتركة، أفنيتها متداخلة كقطع الأحجية. الأزقة تدور حول البلوك ولا تخترقه أبداً.') }}</p>
                        <p>{{ $t('House 45/3 is the mansion of the block. The nomination file cites it three times: for a main range of two rooms with a wing on either side of the court, for a tarma carried on an arcade of Mosul alabaster, and for a sirdab, rare this far from the rim.',
                            'خانووی ٤٥/٣ کۆشکی بلۆکەکەیە. دۆسیەی پاڵاوتن سێ جار ئاماژەی پێ دەکات: بۆ باڵێکی سەرەکی لە دوو ژوور لەگەڵ باڵێک لە هەر لایەکی حەوشە، بۆ تارمەیەک کە لەسەر ڕیزە کەوانەیەکی مەڕمەڕی مووسڵ هەڵگیراوە، و بۆ سەردابێک، کە ئەوەندە دوور لە لێوار دەگمەنە.',
                            'البيت ٤٥/٣ هو قصر البلوك. يستشهد به ملف الترشيح ثلاث مرات: لجناح رئيسي من غرفتين مع جناح على كل جانب من الفناء، ولطارمة محمولة على عقود من مرمر الموصل، ولسرداب نادر على هذا البعد من الحافة.') }}</p>
                        <p>{{ $t('The survey went further than we show: it dated every wall in the block, and found each house a patchwork of periods.',
                            'ڕووپێوەکە لەوە زیاتر ڕۆیشت کە ئێمە پیشانی دەدەین: مێژووی هەموو دیوارێکی بلۆکەکەی دیاری کرد، و هەر خانوویەکی وەک تێکەڵەیەک لە سەردەمەکان دۆزییەوە.',
                            'وذهب المسح أبعد مما نعرض: أرّخ كل جدار في البلوك، فوجد كل بيت رقعةً من عصور مختلفة.') }}</p>
                    </div>
                    <dl class="ja-facts">
                        <div><dt>{{ $t('Houses', 'خانوو', 'البيوت') }}</dt><dd dir="ltr">45/1 – 45/13</dd></div>
                        <div><dt>{{ $t('Size', 'قەبارە', 'القياس') }}</dt><dd>{{ $noScale }}</dd></div>
                        <div><dt>{{ $t('Drawn from', 'لەمەوە کێشراوە', 'مرسوم عن') }}</dt><dd>{{ $src['nom'] }}, fig. 2.49</dd></div>
                    </dl>
                </div>
                <p class="ja-plate__foot"><span>Aram Mizuri Architecture — {{ $headline }}</span><span dir="ltr">{{ site_url($path) }} · {{ count($plates) + 1 }} / {{ count($plates) + 1 }}</span></p>
            </article>
        </section>

        <section class="ja-text">
            <h2 class="ja-h">{{ $t('How they were built', 'چۆن دروست کراون', 'كيف بُنيت') }}</h2>
            <p>{{ $t('The plans show thick walls, and the nomination file explains what is inside them.', 'نەخشەکان دیواری ئەستوور پیشان دەدەن، و دۆسیەی پاڵاوتن ڕوونی دەکاتەوە چی لە ناویاندایە.', 'تُظهر المخططات جدراناً سميكة، ويشرح ملف الترشيح ما في داخلها.') }}</p>
            <dl class="ja-build">
                <div><dt>{{ $t('Brick and mud', 'خشت و قوڕ', 'الطابوق والطين') }}</dt><dd>{{ $t('Almost everything is fired brick laid in mud mortar, sometimes with a little lime or gypsum. A wall laid in mud comes apart easily, so the same bricks were used many times over. That is why brick sizes cannot date a house.', 'نزیکەی هەموو شتێک خشتی سوورکراوەیە کە بە قوڕ دانراوە، هەندێک جار بە کەمێک قسڵ یان گەچ. دیوارێک کە بە قوڕ دانرابێت بە ئاسانی هەڵدەوەشێتەوە، بۆیە هەمان خشت چەندین جار بەکار هاتووەتەوە. هەر بۆیە قەبارەی خشت ناتوانێت مێژووی خانوویەک دیاری بکات.', 'يكاد كل شيء يكون طابوقاً مشوياً مبنياً بمونة الطين، وأحياناً مع قليل من الكلس أو الجص. الجدار المبني بالطين يُفكّ بسهولة، فاستُعمل الطابوق نفسه مرات كثيرة. ولهذا لا تصلح قياسات الطابوق لتأريخ بيت.') }}</dd></div>
                <div><dt>{{ $t('The wall', 'دیوار', 'الجدار') }}</dt><dd>{{ $t('Two faces of brick with a core of rubble between them. The bonding is irregular; wider, tile-like bricks reach back to tie each face to the core. Bands of timber are hidden in the walls, above all at the corners, to hold them together in an earthquake.', 'دوو ڕووی خشت و ناوکێک لە وردە بەرد لە نێوانیاندا. ڕیزبەندییەکە ناڕێکە؛ خشتی پانتر، وەک کاشی، دەچنە دواوە تا هەر ڕوویەک بە ناوکەکەوە ببەستن. پشتێنی دار لە دیوارەکاندا شاردراوەتەوە، بەتایبەتی لە گۆشەکان، تا لە بوومەلەرزەدا پێکەوە بیانگرێت.', 'وجهان من الطابوق بينهما حشوة من الدبش. الرباط غير منتظم؛ وطابوق أعرض يشبه البلاط يمتد إلى الخلف ليربط كل وجه بالحشوة. وتُخفى في الجدران أحزمة خشبية، خصوصاً عند الزوايا، لتمسكها في الزلازل.') }}</dd></div>
                <div><dt>{{ $t('The foundation', 'بناغە', 'الأساس') }}</dt><dd>{{ $t('Usually no more than the wall itself made a little wider, and no deeper than half a metre.', 'زۆربەی جار تەنها خودی دیوارەکەیە کە کەمێک پانتر کراوە، و لە نیو مەتر قووڵتر نییە.', 'في الغالب ليس أكثر من الجدار نفسه وقد عُرِّض قليلاً، ولا يزيد عمقه على نصف متر.') }}</dd></div>
                <div><dt>{{ $t('The roof', 'سەربان', 'السقف') }}</dt><dd>{{ $t('Every roof is flat. Round poplar beams span the room. Across them lie oak poles, close together, then reeds and woven matting, then a layer of mud and lime. Each layer was rolled flat with a stone cylinder.', 'هەموو سەربانێک تەختە. کاریتەی خڕی سپیدار ژوورەکە دەبڕێت. بەسەریاندا داری بەڕوو نزیک بە یەک دادەنرێت، پاشان قامیش و حەسیر، پاشان چینێک لە قوڕ و قسڵ. هەر چینێک بە لوولەیەکی بەردین تەخت دەکرا.', 'كل السقوف مستوية. جذوع الحور المدوّرة تمتد فوق الغرفة. وفوقها أعواد البلوط متقاربة، ثم القصب والحصير المنسوج، ثم طبقة من الطين والكلس. وكانت كل طبقة تُدحى بأسطوانة حجرية.') }}</dd></div>
                <div><dt>{{ $t('Vaults and iron', 'تاق و ئاسن', 'القبوات والحديد') }}</dt><dd>{{ $t('Brick vaults in lime mortar belong to the better houses and mostly to their basements. From the late-Ottoman years small iron beams appear, carrying shallow brick arches, and iron columns hold up the porticoes.', 'تاقی خشتی بە قسڵ هی خانووە باشترەکانە و زۆرتر لە ژێرزەمینەکانیاندایە. لە ساڵانی کۆتایی عوسمانییەوە کاریتەی ئاسنی بچووک دەردەکەون کە کەوانەی خشتی نزم هەڵدەگرن، و کۆڵەکەی ئاسن تارمەکان ڕادەگرن.', 'القبوات الطابوقية بمونة الكلس تخص البيوت الأفضل، ومعظمها في السراديب. ومنذ السنوات العثمانية الأخيرة تظهر عوارض حديدية صغيرة تحمل عقوداً طابوقية واطئة، وأعمدة حديدية ترفع الطارمات.') }}</dd></div>
                <div><dt>{{ $t('The niche', 'تاق', 'الكوّة') }}</dt><dd>{{ $t('Found in houses of every period. A niche is decoration and storage, and the file adds a third reason: niches “reduced the number of bricks required”.', 'لە خانووی هەموو سەردەمێکدا هەیە. تاق ڕازاندنەوە و شوێنی هەڵگرتنە، و دۆسیەکە هۆکارێکی سێیەم زیاد دەکات: تاقەکان «ژمارەی خشتی پێویستیان کەم دەکردەوە».', 'توجد في بيوت كل العصور. الكوّة زخرفة وخزانة، ويضيف الملف سبباً ثالثاً: الكُوى «قلّلت عدد الطابوق اللازم».') }}</dd></div>
                <div><dt>{{ $t('The court floor', 'زەویی حەوشە', 'أرضية الفناء') }}</dt><dd>{{ $t('Square brick paving, often laid on the diagonal.', 'خشتی چوارگۆشە، زۆر جار بە لاری دانراوە.', 'بلاط طابوقي مربع، يُفرش غالباً بشكل قطري.') }}</dd></div>
                <div><dt>{{ $t('The corner', 'گۆشە', 'الزاوية') }}</dt><dd>{{ $t('Street corners are rounded in brick, then brought back to a right angle higher up.', 'گۆشەی کۆڵانەکان بە خشت خڕ کراون، پاشان لە سەرەوەتر دەگەڕێنەوە بۆ گۆشەی وەستاو.', 'زوايا الأزقة مدوّرة بالطابوق، ثم تعود إلى الزاوية القائمة في الأعلى.') }}</dd></div>
            </dl>
        </section>

        <section class="ja-text">
            <h2 class="ja-h">{{ $t('Telling the age of a house', 'ناسینەوەی تەمەنی خانوویەک', 'معرفة عمر البيت') }}</h2>
            <p>{{ $t('With so few inscriptions, the houses are dated by their details. The file sets out three periods, and warns that many houses mix them.', 'لەبەر کەمیی نووسراو، خانووەکان بە وردەکارییەکانیان مێژوویان دیاری دەکرێت. دۆسیەکە سێ سەردەم دادەنێت، و ئاگادار دەکاتەوە کە زۆر خانوو تێکەڵیان دەکەن.', 'لقلة الكتابات، تُؤرَّخ البيوت بتفاصيلها. يحدد الملف ثلاث فترات، وينبّه إلى أن بيوتاً كثيرة تخلط بينها.') }}</p>
            <div class="ja-ages">
                <div class="ja-age">
                    <p class="ja-age__when" dir="ltr">c. 1750 – c. 1880</p>
                    <h3>{{ $t('Early Ottoman', 'عوسمانیی سەرەتا', 'العثماني المبكر') }}</h3>
                    <p>{{ $t('Dark rooms with small upright vents closed by wooden grilles. Because there are no windows, the whole front is free for patterned brickwork. Doorways sit in rectangular recesses. Niches are square, in two tiers. Wall fireplaces have hoods.', 'ژووری تاریک بە کونی هەواگۆڕکێی بچووکی ستوونی کە بە تۆڕی دار داخراون. چونکە پەنجەرە نییە، هەموو ڕووکارەکە ئازادە بۆ خشتکاریی نەخشدار. دەرگاکان لە ناو چاڵی لاکێشەییدان. تاقەکان چوارگۆشەن، لە دوو ڕیزدا. ئاگردانی دیوار سەرپۆشی هەیە.', 'غرف معتمة بفتحات تهوية صغيرة قائمة تسدّها شبابيك خشبية. ولأنه لا نوافذ، تبقى الواجهة كلها للطابوق المزخرف. الأبواب داخل تجاويف مستطيلة. الكُوى مربعة في صفّين. ومواقد الجدار لها أغطية.') }}</p>
                </div>
                <div class="ja-age">
                    <p class="ja-age__when" dir="ltr">c. 1880 – 1918</p>
                    <h3>{{ $t('Late Ottoman', 'عوسمانیی کۆتایی', 'العثماني المتأخر') }}</h3>
                    <p>{{ $t('Large arched windows with iron grilles, and so a tarma on timber columns or alabaster arcades. Parapets step in and out and are plastered in lime. Doorcases are carved alabaster. Niches gain arched heads. Inside: stencilled blue-on-white painting, mirror-work and wooden ceilings.', 'پەنجەرەی گەورەی کەوانەدار بە تۆڕی ئاسن، و بۆیە تارمەیەک لەسەر کۆڵەکەی دار یان ڕیزە کەوانەی مەڕمەڕ. لێواری سەربان دەچێتە ژوورەوە و دێتە دەرەوە و بە قسڵ سواغ دراوە. چوارچێوەی دەرگا مەڕمەڕی هەڵکۆڵراوە. تاقەکان سەری کەوانەیی وەردەگرن. لە ناوەوە: نیگاری شین لەسەر سپی، ئاوێنەکاری و بنمیچی دار.', 'نوافذ كبيرة معقودة بشبابيك حديدية، ولذلك طارمة على أعمدة خشبية أو عقود مرمرية. الستائر العلوية تتراجع وتبرز وتُكسى بالكلس. إطارات الأبواب مرمر منحوت. الكُوى تكتسب رؤوساً معقودة. وفي الداخل: رسم أزرق على أبيض بالقوالب، ومرايا، وسقوف خشبية.') }}</p>
                </div>
                <div class="ja-age">
                    <p class="ja-age__when" dir="ltr">1918 – c. 1960</p>
                    <h3>{{ $t('After the Ottomans', 'دوای عوسمانییەکان', 'بعد العثمانيين') }}</h3>
                    <p>{{ $t('Parapets with panels of bricks set on the diagonal. Tarmas on iron columns, often roofed with shallow brick arches. Tall glazed windows. Street doors set in deep splayed reveals. Ceilings painted on tin.', 'لێواری سەربان بە تابلۆی خشتی بە لاری دانراو. تارمە لەسەر کۆڵەکەی ئاسن، زۆر جار بە کەوانەی خشتی نزم سەرداپۆشراوە. پەنجەرەی بەرزی شووشەدار. دەرگای کۆڵان لە ناو چاڵی قووڵی لاردا. بنمیچی نیگارکراو لەسەر تەنەکە.', 'ستائر علوية بألواح من طابوق موضوع قطرياً. طارمات على أعمدة حديدية، مسقوفة غالباً بعقود طابوقية واطئة. نوافذ عالية مزججة. أبواب الأزقة داخل تجاويف عميقة مشطوفة. وسقوف مرسومة على الصفيح.') }}</p>
                </div>
            </div>
        </section>

        <section class="ja-text">
            <h2 class="ja-h">{{ $t('What we take from them', 'ئەوەی لێیان وەردەگرین', 'ما نأخذه منها') }}</h2>
            <p>{{ $t('Three things stay with us at the drawing board. A room that opens to a courtyard needs no corridor. A thick wall can hold the cupboard, the shelf and the lamp. And shade came before glass: the builders of the Citadel gave a house a portico in the same years they gave it windows.',
                'سێ شت لەسەر مێزی نەخشەکێشان لەگەڵمان دەمێننەوە. ژوورێک کە بەسەر حەوشەدا بکرێتەوە پێویستی بە ڕاڕەو نییە. دیوارێکی ئەستوور دەتوانێت دۆڵاب و ڕەفە و چرا لە خۆ بگرێت. و سێبەر پێش شووشە هات: وەستاکانی قەڵا لە هەمان ئەو ساڵانەدا تارمەیان دا بە خانوو کە پەنجەرەیان پێدا.',
                'ثلاثة أشياء تبقى معنا عند طاولة الرسم. الغرفة التي تنفتح على فناء لا تحتاج إلى ممر. والجدار السميك يتّسع للخزانة والرف والمصباح. والظل سبق الزجاج: بنّاؤو القلعة أعطوا البيت طارمة في السنوات نفسها التي أعطوه فيها النوافذ.') }}</p>
        </section>

        <section class="ja-sources">
            <h2 class="ja-h">{{ $t('Sources', 'سەرچاوەکان', 'المصادر') }}</h2>
            <ol dir="ltr">
                <li>High Commission for Erbil Citadel Revitalization (HCECR), <cite>Nomination of Erbil Citadel (Kurdistan Region, Iraq) for Inscription on the UNESCO World Heritage List</cite>, vol. I (text) and vol. II (annexes), 2012. Drawings there are from the Conservation and Rehabilitation Master Plan (2011) and the 2008 survey by HBA – Euronet Consulting. <a href="https://whc.unesco.org/en/list/1437/documents/" rel="noopener" target="_blank">whc.unesco.org/en/list/1437/documents</a></li>
                <li>Mustafa, F. A. and Ali, L. A. (2024), ‘Common architectural characteristics of traditional courtyard houses in Erbil city’, <cite>Ain Shams Engineering Journal</cite> 15(11), 103003. <a href="https://doi.org/10.1016/j.asej.2024.103003" rel="noopener" target="_blank">doi.org/10.1016/j.asej.2024.103003</a></li>
                <li>Goriel, W. A. S., Molnár, T. and Zoltán, E. S. (2025), ‘Examining the Hashim Chalbi House’s Spatial Aspects in Erbil Citadel’, <cite>Buildings</cite> 15(12), 2134. <a href="https://doi.org/10.3390/buildings15122134" rel="noopener" target="_blank">doi.org/10.3390/buildings15122134</a></li>
            </ol>
            <p class="ja-note">{{ $t('The walls are traced from the published figures and are as exact as those figures allow; they are not for measuring from. Rooms are named only where the source names them.',
                'دیوارەکان لە وێنە بڵاوکراوەکانەوە هەڵگیراونەتەوە و ئەوەندە وردن کە ئەو وێنانە ڕێگە دەدەن؛ بۆ پێوانە لێوەرگرتن نین. ژوورەکان تەنها لەو شوێنانە ناو نراون کە سەرچاوەکە ناوی ناون.',
                'الجدران منقولة عن الأشكال المنشورة، ودقتها بقدر ما تسمح به تلك الأشكال؛ وليست للقياس منها. ولم تُسمَّ الغرف إلا حيث سمّاها المصدر.') }}</p>
        </section>

        <div class="jr-post__actions">
            <a class="pp-btn" href="{{ site_url(\App\Models\Landmark::PATH) }}">{{ $t('Kurdistan’s architectural heritage', 'میراتی تەلارسازیی کوردستان', 'التراث المعماري لكوردستان') }} <span class="pp-arrow" aria-hidden="true">→</span></a>
            @include('partials.share-link', ['shareClass' => 'pp-btn pp-btn--line', 'shareAt' => 'journal', 'shareUrl' => site_url($path), 'shareTitle' => $headline])
        </div>
        <p class="jr-post__back"><a href="{{ site_url('/journal') }}"><span class="pp-arrow pp-arrow--back" aria-hidden="true">←</span> {{ bival('journal_all') }}</a></p>
    </main>

    @include('partials.page-foot')
    <script src="{{ asset('engage.js') }}?v={{ filemtime(public_path('engage.js')) }}" defer></script>
    <script src="{{ asset('journal-drawing.js') }}?v={{ filemtime(public_path('journal-drawing.js')) }}" defer></script>
</body>
</html>
