<?php

/**
 * Bisotun — the inscription that unlocked cuneiform. Split opening on the
 * relief, a lead text, a statement of how high it is, the figures, the relief
 * read as an annotated photograph, the decipherment as a vertical timeline,
 * Darius's own words, the facts, a grid of the mountain's other monuments,
 * the UNESCO panel.
 *
 * Sources: UNESCO World Heritage Centre (ref. 1222); Wikipedia, "Behistun
 * Inscription" and "Bisotun"; Livius.org, "Behistun"; L. W. King & R. C.
 * Thompson, The Sculptures and Inscription of Darius the Great on the Rock
 * of Behistûn (British Museum, 1907).
 */
return [
    'slug' => 'bisotun',
    'hero_style' => 'split',
    'cover' => '01-the-relief',
    'year_from' => -522, 'year_to' => -486,
    'unesco' => true, 'unesco_year' => 2006, 'unesco_ref' => 1222, 'unesco_criteria' => '(ii)(iii)', 'unesco_name' => 'Bisotun',
    'period_en' => 'c. 520 BC', 'period_ku' => 'نزیکەی ٥٢٠ پ.ز', 'period_ar' => 'نحو ٥٢٠ ق.م',
    'summary_en' => 'A relief and inscription carved a hundred metres up a cliff on the ancient road from Babylon to Media — the key that unlocked the cuneiform writing of the ancient Near East.',
    'summary_ku' => 'نەخش و نووسینێک کە سەد مەتر بە بەرزی لەسەر زنارێک لە ڕێگا کۆنەکەی بابلەوە بۆ میدیا هەڵکەنراوە — ئەو کلیلەی نووسینی بزماریی ڕۆژهەڵاتی نزیکی کۆنی کردەوە.',
    'summary_ar' => 'نقش وكتابة منحوتان على ارتفاع مئة متر على جرف عند الطريق القديم من بابل إلى ميديا — المفتاح الذي فكّ رموز الخط المسماري للشرق الأدنى القديم.',
    'intro_en' => 'In 1835 a young British officer climbed a cliff in the Zagros, stood on a ledge a hundred metres above the road, and began to copy, letter by letter, a text no one had read for two thousand years. It took him twelve years. When he had finished, the libraries of Assyria and Babylon could be read again.',
    'intro_ku' => 'لە ساڵی ١٨٣٥ ئەفسەرێکی گەنجی بەریتانی لە زنارێکی زاگرۆس سەرکەوت، لەسەر ڕەفێک سەد مەتر بەسەر ڕێگاکەوە ڕاوەستا، و دەستی کرد بە لەبەرگرتنەوە، پیت بە پیت، دەقێک کە دوو هەزار ساڵ بوو کەس نەیخوێندبووەوە. دوازدە ساڵی لێ خایاند. کاتێک تەواو بوو، کتێبخانەکانی ئاشوور و بابل دووبارە دەخوێنرانەوە.',
    'intro_ar' => 'في عام ١٨٣٥ تسلّق ضابط بريطاني شاب جرفاً في زاغروس، ووقف على حافة ترتفع مئة متر فوق الطريق، وشرع ينسخ، حرفاً حرفاً، نصاً لم يقرأه أحد منذ ألفي عام. استغرق ذلك اثني عشر عاماً. وحين انتهى، أمكن من جديد قراءة مكتبات آشور وبابل.',

    'sections' => [
        ['type' => 'text', 'data' => [
            'layout' => 'lead',
            'heading_en' => 'The king who wrote on a mountain', 'heading_ku' => 'ئەو پاشایەی لەسەر چیایەک نووسی', 'heading_ar' => 'الملك الذي كتب على جبل',
            'body_en' => "When Darius seized the Persian throne in 522 BC he spent a year fighting nineteen battles against nine rebel kings. Then he had the story carved where everyone travelling between Babylon and Ecbatana would pass beneath it: on the limestone face of Mount Bisotun, the \"place of the gods\", high above the royal road.\n\nThe panel shows Darius life-size, a bow in his hand, his foot on the chest of the usurper Gaumata, facing a line of nine captive kings roped together at the neck; above them floats the winged figure of Ahura Mazda. Around and below, in three languages and three scripts — Old Persian, Elamite and Babylonian — some 1,200 lines tell how he won and why the god gave him the kingdom.\n\nThe height was the point. No one could read it from the road, and once the sculptors had finished the ledge beneath was cut away so that no one could climb up and alter it. For twenty-two centuries travellers guessed at what it showed: Christ and his apostles, said one; a schoolmaster and his pupils, said another.",
            'body_ku' => "کاتێک داریوش لە ٥٢٢ پ.ز تەختی فارسی گرتە دەست، ساڵێکی بەسەر برد بە جەنگانی نۆزدە شەڕ دژی نۆ پاشای یاخی. پاشان فەرمانی دا چیرۆکەکە لەو شوێنە هەڵبکەنرێت کە هەموو ئەوانەی لە نێوان بابل و ئێکباتانا گەشت دەکەن لە ژێریدا تێدەپەڕن: لەسەر ڕووی بەردی کلسیی چیای بێستوون، «شوێنی خوداوەندان»، بە بەرزی لە سەرووی ڕێگای شاهانە.\n\nتابلۆکە داریوش بە قەبارەی ڕاستەقینە نیشان دەدات، کەوانێک لە دەستیدا، پێی لەسەر سنگی داگیرکەر گەئوماتا، ڕووبەڕووی ڕیزێک لە نۆ پاشای دیل کە لە مل بە گوریس پێکەوە بەستراون؛ لە سەرووی ئەوانەوە وێنەی باڵداری ئاهورامەزدا هەڵواسراوە. لە دەور و خوارەوە، بە سێ زمان و سێ نووسین — فارسیی کۆن، ئیلامی و بابلی — نزیکەی ١,٢٠٠ دێڕ دەگێڕنەوە چۆن سەرکەوت و بۆچی خوداوەند شانشینەکەی پێ بەخشی.\n\nبەرزییەکە مەبەست بوو. کەس نەیدەتوانی لە ڕێگاوە بیخوێنێتەوە، و کاتێک پەیکەرتاشەکان تەواو بوون ڕەفی ژێری بڕدرا بۆ ئەوەی کەس نەتوانێت سەربکەوێت و بیگۆڕێت. بیست و دوو سەدە گەشتیاران خەمڵاندنیان دەکرد چی نیشان دەدات: مەسیح و حەوارییەکانی، یەکێک وتی؛ مامۆستایەک و قوتابییەکانی، یەکێکی تر وتی.",
            'body_ar' => "حين استولى داريوس على العرش الفارسي عام ٥٢٢ ق.م قضى عاماً يخوض تسع عشرة معركة ضد تسعة ملوك متمردين. ثم أمر بنحت القصة حيث يمرّ تحتها كل مسافر بين بابل وإكباتانا: على وجه الحجر الجيري لجبل بيستون، «مكان الآلهة»، عالياً فوق الطريق الملكي.\n\nتُظهر اللوحة داريوس بحجمه الطبيعي، قوس في يده، وقدمه على صدر المغتصب غوماتا، مواجهاً صفاً من تسعة ملوك أسرى مربوطين من أعناقهم بحبل؛ وفوقهم تحلّق صورة أهورا مزدا المجنّحة. وحولها وتحتها، بثلاث لغات وثلاثة خطوط — الفارسية القديمة والعيلامية والبابلية — نحو ١,٢٠٠ سطر تروي كيف انتصر ولماذا منحه الإله الملك.\n\nكان الارتفاع هو المقصود. لم يكن أحد يستطيع قراءته من الطريق، وبعد أن أتمّ النحّاتون عملهم قُطعت الحافة التي تحته حتى لا يصعد أحد ويغيّره. واثنين وعشرين قرناً ظلّ المسافرون يخمّنون ما يُظهره: المسيح وحواريّوه، قال أحدهم؛ ومعلّم وتلاميذه، قال آخر.",
        ]],

        ['type' => 'statement', 'data' => [
            'align' => 'start',
            'kicker_en' => 'Carved on the cliff', 'kicker_ku' => 'لەسەر زنارەکە هەڵکەنراوە', 'kicker_ar' => 'منحوت على الجرف',
            'big_en' => '100 metres up', 'big_ku' => '١٠٠ مەتر بە بەرزی', 'big_ar' => 'على ارتفاع ١٠٠ متر',
            'text_en' => 'The panel measures about 15 metres high by 25 wide. Reaching it still means climbing the rock face and edging along a narrow ledge — which is how Henry Rawlinson copied it in the 1830s, and how the restorers of today still get there.',
            'text_ku' => 'تابلۆکە نزیکەی ١٥ مەتر بەرز و ٢٥ مەتر پانە. گەیشتن پێی هێشتا بە مانای سەرکەوتن لە ڕووی بەردەکە و خۆخشاندنە بە درێژایی ڕەفێکی تەسکدا — ئەوەش ئەو شێوەیەیە کە هێنری ڕاولینسۆن لە سییەکانی سەدەی نۆزدە لەبەری گرتەوە، و ئەو شێوەیەی نۆژەنکەرانی ئەمڕۆ هێشتا پێی دەگەن.',
            'text_ar' => 'تبلغ اللوحة نحو ١٥ متراً ارتفاعاً و٢٥ متراً عرضاً. والوصول إليها لا يزال يعني تسلّق وجه الصخر والزحف على حافة ضيقة — وهكذا نسخها هنري رولينسون في ثلاثينيات القرن التاسع عشر، وهكذا لا يزال مرمّمو اليوم يصلون إليها.',
        ]],

        ['type' => 'annotated', 'data' => [
            'heading_en' => 'Reading the relief', 'heading_ku' => 'خوێندنەوەی نەخشەکە', 'heading_ar' => 'قراءة النقش',
            'img' => '01-the-relief',
            'alt_en' => 'The Bisotun relief: Darius the Great standing over the fallen Gaumata before a line of nine bound captives, the winged Ahura Mazda above, columns of cuneiform around them',
            'alt_ku' => 'نەخشی بێستوون: داریوشی گەورە بەسەر گەئوماتای کەوتوودا ڕاوەستاوە لە بەردەم ڕیزێک لە نۆ دیلی بەستراو، ئاهورامەزدای باڵدار لە سەرووەوە، ستوونی نووسینی بزماری لە دەوریان',
            'alt_ar' => 'نقش بيستون: داريوس الكبير واقفاً فوق غوماتا الساقط أمام صفّ من تسعة أسرى مقيّدين، وأهورا مزدا المجنّح في الأعلى، وأعمدة من الكتابة المسمارية حولهم',
            'caption_en' => 'The scene is a single sentence in stone: the god approves, the king triumphs, the liars are bound. Each captive wears the dress of his people and is named in the caption above him — "This is Nidintu-Bêl. He lied, saying: I am Nebuchadnezzar, king of Babylon."',
            'caption_ku' => 'دیمەنەکە یەک ڕستەیە بە بەرد: خوداوەند ڕەزامەندە، پاشا سەردەکەوێت، درۆزنەکان بەستراون. هەر دیلێک جلوبەرگی گەلەکەی خۆی لەبەردایە و لە سەرنووسەی سەرووی ناوی هاتووە — «ئەمە نیدینتو-بێلە. درۆی کرد، وتی: من نەبوخەدنەسەرم، پاشای بابل.»',
            'caption_ar' => 'المشهد جملة واحدة بالحجر: الإله يرضى، والملك ينتصر، والكذّابون مقيّدون. يرتدي كل أسير لباس قومه ويُسمّى في العنوان فوقه — «هذا نيدينتو-بيل. كذب قائلاً: أنا نبوخذنصّر، ملك بابل.»',
            'points' => [
                ['x' => 50, 'y' => 24, 'label_en' => 'Ahura Mazda', 'label_ku' => 'ئاهورامەزدا', 'label_ar' => 'أهورا مزدا', 'text_en' => 'The god in a winged disc, raising a hand to the king and holding out a ring — the sign that the kingdom is given.', 'text_ku' => 'خوداوەند لە پەپکەیەکی باڵداردا، دەستێک بۆ پاشا بەرز دەکاتەوە و ئەڵقەیەک درێژ دەکات — نیشانەی ئەوەی شانشینەکە بەخشراوە.', 'text_ar' => 'الإله في قرص مجنّح، يرفع يداً إلى الملك ويمدّ حلقة — علامة أن الملك ممنوح.'],
                ['x' => 22, 'y' => 55, 'label_en' => 'Darius', 'label_ku' => 'داریوش', 'label_ar' => 'داريوس', 'text_en' => 'Life-size, crowned, a bow in his left hand, his right raised to the god. His beard was carved separately and fixed with iron pins and lead.', 'text_ku' => 'بە قەبارەی ڕاستەقینە، تاجدار، کەوانێک لە دەستی چەپیدا، ڕاستەکەی بۆ خوداوەند بەرزکراوەتەوە. ڕیشەکەی جیا هەڵکەنراوە و بە مێخی ئاسن و قورقوشم چەسپێنراوە.', 'text_ar' => 'بحجمه الطبيعي، متوّجاً، قوس في يده اليسرى، ويمناه مرفوعة إلى الإله. نُحتت لحيته على حدة وثُبّتت بمسامير من حديد ورصاص.'],
                ['x' => 30, 'y' => 70, 'label_en' => 'Gaumata', 'label_ku' => 'گەئوماتا', 'label_ar' => 'غوماتا', 'text_en' => 'The magus who claimed to be the king\'s brother, flat on his back under Darius\'s foot, hands raised.', 'text_ku' => 'ئەو مۆغەی داوای کرد برای پاشایە، بە پشت لە ژێر پێی داریوشدا، دەستەکانی بەرزکراونەتەوە.', 'text_ar' => 'المجوسي الذي زعم أنه أخو الملك، مستلقٍ على ظهره تحت قدم داريوس، رافعاً يديه.'],
                ['x' => 62, 'y' => 58, 'label_en' => 'Nine captive kings', 'label_ku' => 'نۆ پاشای دیل', 'label_ar' => 'تسعة ملوك أسرى', 'text_en' => 'Each about a metre tall, hands tied behind them, a rope through their necks: the rebels of 522–521 BC, one from each land.', 'text_ku' => 'هەریەکەیان نزیکەی مەترێک بەرز، دەستەکانیان لە پشتەوە بەستراو، گوریسێک بە ناو ملیاندا: یاخییەکانی ٥٢٢–٥٢١ پ.ز، یەک لە هەر وڵاتێک.', 'text_ar' => 'طول كل منهم نحو متر، أيديهم مقيّدة خلفهم، وحبل يمرّ بأعناقهم: متمردو ٥٢٢–٥٢١ ق.م، واحد من كل أرض.'],
                ['x' => 90, 'y' => 58, 'label_en' => 'Skunkha the Scythian', 'label_ku' => 'سکونخای سکیسی', 'label_ar' => 'سكونخا السكيثي', 'text_en' => 'The tall pointed hat marks the last captive, added after a later campaign — part of the finished inscription had to be chiselled away to fit him in.', 'text_ku' => 'کڵاوە نووکتیژە بەرزەکە دوایین دیل نیشانە دەکات، کە دوای هەڵمەتێکی دواتر زیادکراوە — بەشێک لە نووسینە تەواوکراوەکە دەبوو بتاشرێت بۆ ئەوەی جێی بێتەوە.', 'text_ar' => 'القبعة المدبّبة العالية تميّز الأسير الأخير، الذي أُضيف بعد حملة لاحقة — واضطُرّ إلى إزالة جزء من النقش المكتمل بالإزميل لإفساح مكان له.'],
                ['x' => 50, 'y' => 90, 'label_en' => 'The columns of text', 'label_ku' => 'ستوونەکانی دەق', 'label_ar' => 'أعمدة النص', 'text_en' => 'Old Persian in 414 lines, Elamite in 260, Babylonian in 112 — the same words three times, which is what made decipherment possible.', 'text_ku' => 'فارسیی کۆن لە ٤١٤ دێڕدا، ئیلامی لە ٢٦٠، بابلی لە ١١٢ — هەمان وشەکان سێ جار، ئەوەش ئەوەیە کە کردنەوەکەی کردە شتێکی مومکین.', 'text_ar' => 'الفارسية القديمة في ٤١٤ سطراً، والعيلامية في ٢٦٠، والبابلية في ١١٢ — الكلمات نفسها ثلاث مرات، وهذا ما جعل فكّ الرموز ممكناً.'],
            ],
        ]],

        ['type' => 'stats', 'data' => [
            'layout' => 'row',
            'items' => [
                ['value' => '15', 'unit_en' => '× 25 m', 'unit_ku' => '× ٢٥ م', 'unit_ar' => '× ٢٥ م', 'label_en' => 'The panel', 'label_ku' => 'تابلۆکە', 'label_ar' => 'اللوحة', 'note_en' => 'about — relief and inscription together', 'note_ku' => 'نزیکەی — نەخش و نووسین پێکەوە', 'note_ar' => 'نحو — النقش والكتابة معاً'],
                ['value' => '3', 'label_en' => 'Languages', 'label_ku' => 'زمان', 'label_ar' => 'لغات', 'note_en' => 'Old Persian, Elamite, Babylonian', 'note_ku' => 'فارسیی کۆن، ئیلامی، بابلی', 'note_ar' => 'الفارسية القديمة والعيلامية والبابلية'],
                ['value' => '19', 'label_en' => 'Battles in one year', 'label_ku' => 'شەڕ لە یەک ساڵدا', 'label_ar' => 'معركة في عام واحد', 'note_en' => 'against nine rebel kings, 522–521 BC', 'note_ku' => 'دژی نۆ پاشای یاخی، ٥٢٢–٥٢١ پ.ز', 'note_ar' => 'ضد تسعة ملوك متمردين، ٥٢٢–٥٢١ ق.م'],
                ['value' => '12', 'unit_en' => 'years', 'unit_ku' => 'ساڵ', 'unit_ar' => 'عاماً', 'label_en' => 'To copy it', 'label_ku' => 'بۆ لەبەرگرتنەوەی', 'label_ar' => 'لنسخه', 'note_en' => 'Rawlinson, 1835–1847', 'note_ku' => 'ڕاولینسۆن، ١٨٣٥–١٨٤٧', 'note_ar' => 'رولينسون، ١٨٣٥–١٨٤٧'],
                ['value' => '2006', 'label_en' => 'World Heritage', 'label_ku' => 'میراتی جیهانی', 'label_ar' => 'التراث العالمي', 'note_en' => 'UNESCO, criteria (ii)(iii)', 'note_ku' => 'یونسکۆ، پێوەرەکانی (ii)(iii)', 'note_ar' => 'اليونسكو، المعياران (ii)(iii)'],
            ],
        ]],

        ['type' => 'timeline', 'data' => [
            'layout' => 'vertical',
            'heading_en' => 'Two thousand years to read it', 'heading_ku' => 'دوو هەزار ساڵ بۆ خوێندنەوەی', 'heading_ar' => 'ألفا عام لقراءته',
            'events' => [
                ['date_en' => 'c. 520 BC', 'date_ku' => 'نزیکەی 520 پ.ز', 'date_ar' => 'نحو 520 ق.م', 'title_en' => 'Darius has it carved', 'title_ku' => 'داریوش فەرمانی هەڵکەندنی دەدات', 'title_ar' => 'داريوس يأمر بنحته', 'text_en' => 'The relief first; then the Elamite text; then the Babylonian; then Old Persian, in a script devised, it seems, for this very monument.', 'text_ku' => 'سەرەتا نەخشەکە؛ پاشان دەقی ئیلامی؛ پاشان بابلی؛ پاشان فارسیی کۆن، بە نووسینێک کە وا دیارە بۆ هەر ئەم یادگارە داهێنرابێت.', 'text_ar' => 'النقش أولاً؛ ثم النص العيلامي؛ ثم البابلي؛ ثم الفارسية القديمة، بخط يبدو أنه ابتُكر لهذا النصب بعينه.'],
                ['date_en' => '1598', 'date_ku' => '1598', 'date_ar' => '1598', 'title_en' => 'An Englishman passes by', 'title_ku' => 'ئینگلیزێک بە لایدا تێدەپەڕێت', 'title_ar' => 'إنكليزي يمرّ به', 'text_en' => 'Robert Sherley\'s party sees the relief and decides it must be Christian.', 'text_ku' => 'دەستەی ڕۆبەرت شێرلی نەخشەکە دەبینێت و بڕیار دەدات کە دەبێت مەسیحی بێت.', 'text_ar' => 'ترى بعثة روبرت شيرلي النقش وتقرر أنه لا بد مسيحي.'],
                ['date_en' => '1764', 'date_ku' => '1764', 'date_ar' => '1764', 'title_en' => 'Niebuhr\'s copy', 'title_ku' => 'لەبەرگرتنەوەکەی نیبور', 'title_ar' => 'نسخة نيبور', 'text_en' => 'Carsten Niebuhr draws part of the inscription; from his copy Grotefend identifies ten Old Persian signs by 1802.', 'text_ku' => 'کارستن نیبور بەشێک لە نووسینەکە دەکێشێت؛ لە لەبەرگرتنەوەکەیەوە گرۆتفێند تا ١٨٠٢ دە نیشانەی فارسیی کۆن دەناسێتەوە.', 'text_ar' => 'يرسم كارستن نيبور جزءاً من النقش؛ ومن نسخته يتعرّف غروتفند على عشر علامات فارسية قديمة بحلول ١٨٠٢.'],
                ['date_en' => '1835–1847', 'date_ku' => '1835–1847', 'date_ar' => '1835–1847', 'title_en' => 'Rawlinson on the ledge', 'title_ku' => 'ڕاولینسۆن لەسەر ڕەفەکە', 'title_ar' => 'رولينسون على الحافة', 'text_en' => 'Henry Rawlinson climbs to the Old Persian with the help of a local boy, then has the Babylonian — four metres higher, across a chasm — copied on papier-mâché squeezes. He sends the full text to Europe in 1847.', 'text_ku' => 'هێنری ڕاولینسۆن بە یارمەتیی کوڕێکی ناوخۆیی سەردەکەوێتە لای فارسیی کۆن، پاشان بابلییەکە — چوار مەتر بەرزتر، لە ئەوبەری کەلێنێک — لەسەر قاڵبی کاغەزی لەبەر دەگرێتەوە. لە ١٨٤٧ دەقە تەواوەکە دەنێرێتە ئەورووپا.', 'text_ar' => 'يتسلّق هنري رولينسون إلى الفارسية القديمة بمساعدة فتى محلي، ثم يستنسخ البابلية — على ارتفاع أربعة أمتار أعلى، عبر هوّة — بقوالب من عجينة الورق. ويرسل النص الكامل إلى أوروبا عام ١٨٤٧.'],
                ['date_en' => '1857', 'date_ku' => '1857', 'date_ar' => '1857', 'title_en' => 'Cuneiform is read', 'title_ku' => 'بزماری دەخوێنرێتەوە', 'title_ar' => 'تُقرأ المسمارية', 'text_en' => 'Four scholars independently translate the same Assyrian text in London; the decipherment is accepted. Behistun had done for cuneiform what the Rosetta Stone did for hieroglyphs.', 'text_ku' => 'چوار زانا بە سەربەخۆیی هەمان دەقی ئاشووری لە لەندەن وەردەگێڕن؛ کردنەوەکە پەسەند دەکرێت. بێستوون بۆ بزماری ئەوەی کرد کە بەردی ڕەشید بۆ هیرۆگلیف کردی.', 'text_ar' => 'يترجم أربعة علماء النص الآشوري نفسه مستقلّين في لندن؛ ويُقبل فكّ الرموز. فعلت بيستون للمسمارية ما فعله حجر رشيد للهيروغليفية.'],
                ['date_en' => '1904', 'date_ku' => '1904', 'date_ar' => '1904', 'title_en' => 'The British Museum expedition', 'title_ku' => 'سەفەری مۆزەخانەی بەریتانی', 'title_ar' => 'بعثة المتحف البريطاني', 'text_en' => 'King and Thompson re-examine every line from a scaffold and publish the standard edition.', 'text_ku' => 'کینگ و تۆمسۆن هەموو دێڕێک لەسەر داربەستێکەوە دووبارە لێکۆڵینەوەی لێدەکەن و وەشانی ستاندارد بڵاودەکەنەوە.', 'text_ar' => 'يعيد كينغ وتومسون فحص كل سطر من على سقالة وينشران الطبعة المعتمدة.'],
                ['date_en' => '2006', 'date_ku' => '2006', 'date_ar' => '2006', 'title_en' => 'World Heritage', 'title_ku' => 'میراتی جیهانی', 'title_ar' => 'التراث العالمي', 'text_en' => 'UNESCO inscribes Bisotun — the inscription and the monuments of 187 hectares of mountainside around it.', 'text_ku' => 'یونسکۆ بێستوون تۆمار دەکات — نووسینەکە و یادگارەکانی ١٨٧ هێکتار لێژاییی چیای دەوری.', 'text_ar' => 'اليونسكو تُدرج بيستون — النقش ومعالم ١٨٧ هكتاراً من سفح الجبل حوله.'],
            ],
        ]],

        ['type' => 'quote', 'data' => [
            'text_en' => 'King Darius says: By the grace of Ahuramazda am I king; Ahuramazda has granted me the kingdom.',
            'text_ku' => 'داریوشی پاشا دەڵێت: بە بەخششی ئاهورامەزدا من پاشام؛ ئاهورامەزدا شانشینی پێ بەخشیم.',
            'text_ar' => 'يقول الملك داريوس: بفضل أهورا مزدا أنا ملك؛ أهورا مزدا منحني الملك.',
            'source_en' => 'The Bisotun inscription, column 1 — the first lines any modern reader could understand', 'source_ku' => 'نووسینی بێستوون، ستوونی ١ — یەکەم دێڕەکان کە هەر خوێنەرێکی هاوچەرخ توانی لێی تێبگات', 'source_ar' => 'نقش بيستون، العمود ١ — أول سطور استطاع أي قارئ حديث فهمها',
        ]],

        ['type' => 'facts', 'data' => [
            'layout' => 'cards',
            'items' => [
                ['text_en' => 'The Old Persian script — a simple alphabet of about forty signs — seems to have been invented in Darius\'s reign, and Bisotun is its first great text.', 'text_ku' => 'نووسینی فارسیی کۆن — ئەلفوبێیەکی سادە لە نزیکەی چل نیشانە — وا دیارە لە سەردەمی داریوشدا داهێنرابێت، و بێستوون یەکەم دەقی گەورەیەتی.', 'text_ar' => 'يبدو أن الخط الفارسي القديم — أبجدية بسيطة من نحو أربعين علامة — اختُرع في عهد داريوس، وبيستون أول نصوصه الكبرى.'],
                ['text_en' => 'Darius had copies sent across the empire. One, on papyrus in Aramaic, turned up among the Jewish garrison of Elephantine in Egypt.', 'text_ku' => 'داریوش کۆپی بۆ سەرانسەری ئیمپراتۆرییەتەکە نارد. یەکێکیان، لەسەر پاپیرۆس بە ئارامی، لە نێو پاسەوانە جوولەکەکانی ئێلێفانتین لە میسردا دەرکەوت.', 'text_ar' => 'أرسل داريوس نسخاً في أنحاء الإمبراطورية. وظهرت إحداها، على بردية بالآرامية، بين الحامية اليهودية في إلفنتين في مصر.'],
                ['text_en' => 'Rain running down the cliff has both eaten the limestone and sealed parts of the text under a new skin of mineral deposit — damage and protection from the same water.', 'text_ku' => 'بارانی سەر زنارەکە هەم بەردی کلسییەکەی خواردووە و هەم بەشەکانی دەقەکەی لە ژێر پێستێکی نوێی تەپوتۆزی کانزاییدا داخستووە — زیان و پاراستن لە هەمان ئاوەوە.', 'text_ar' => 'ماء المطر المنساب على الجرف أكل الحجر الجيري وختم في الوقت نفسه أجزاء من النص تحت قشرة جديدة من الرواسب المعدنية — ضرر وحماية من الماء نفسه.'],
                ['text_en' => 'During the Second World War soldiers used the relief for target practice; the scars are still there.', 'text_ku' => 'لە کاتی جەنگی دووەمی جیهانیدا سەربازان نەخشەکەیان بۆ مەشقی نیشانەگرتن بەکارهێنا؛ برینەکان هێشتا لەوێن.', 'text_ar' => 'أثناء الحرب العالمية الثانية استخدم جنود النقش للتدريب على الرماية؛ ولا تزال الندوب هناك.'],
                ['text_en' => 'The unfinished Sasanian wall at the foot of the mountain — Farhad Tarash, some 200 metres long — is tied by legend to Farhad, who carved the mountain for love of Shirin.', 'text_ku' => 'دیوارە ساسانییە تەواونەکراوەکەی بنی چیاکە — فەرهاد تاراش، نزیکەی ٢٠٠ مەتر درێژ — بە ئەفسانە بە فەرهادەوە بەستراوەتەوە، کە لە خۆشەویستیی شیرین چیاکەی هەڵکەند.', 'text_ar' => 'الجدار الساساني غير المكتمل عند سفح الجبل — فرهاد تراش، بطول نحو ٢٠٠ متر — تربطه الأسطورة بفرهاد، الذي نحت الجبل حباً بشيرين.'],
                ['text_en' => 'The same mountain holds a Seleucid Heracles of 148 BC, Parthian reliefs, a Sasanian palace and a Safavid caravanserai: two thousand years of travellers leaving their mark on one cliff.', 'text_ku' => 'هەمان چیا هێراکلێسێکی سەلەوکیی ١٤٨ پ.ز، نەخشی پارتی، کۆشکێکی ساسانی و کاروانسەرایەکی سەفەوی لەخۆ دەگرێت: دوو هەزار ساڵ گەشتیار کە شوێنەواری خۆیان لەسەر یەک زنار بەجێدەهێڵن.', 'text_ar' => 'يحوي الجبل نفسه هرقلاً سلوقياً من عام ١٤٨ ق.م، ونقوشاً بارثية، وقصراً ساسانياً، وخاناً صفوياً: ألفا عام من المسافرين يتركون أثرهم على جرف واحد.'],
            ],
        ]],

        ['type' => 'gallery', 'data' => [
            'layout' => 'grid',
            'heading_en' => 'The mountain of the gods', 'heading_ku' => 'چیای خوداوەندان', 'heading_ar' => 'جبل الآلهة',
            'images' => [
                ['img' => '03-the-cliff', 'alt_en' => 'The Bisotun relief seen from below, a small carved panel high on the limestone cliff', 'alt_ku' => 'نەخشی بێستوون لە خوارەوە، تابلۆیەکی بچووکی هەڵکەنراو بە بەرزی لەسەر زنارە کلسییەکە', 'alt_ar' => 'نقش بيستون من الأسفل، لوحة منحوتة صغيرة عالياً على الجرف الجيري', 'caption_en' => 'From the road the panel is a pale patch on the cliff. That was the intention.', 'caption_ku' => 'لە ڕێگاوە تابلۆکە پەڵەیەکی کاڵە لەسەر زنارەکە. ئەمە مەبەست بوو.', 'caption_ar' => 'من الطريق تبدو اللوحة بقعة باهتة على الجرف. وذلك كان المقصود.'],
                ['img' => '02-darius-and-gaumata', 'alt_en' => 'Detail of the Bisotun relief: Darius with his foot on the chest of the fallen Gaumata', 'alt_ku' => 'وردەکاریی نەخشی بێستوون: داریوش بە پێی لەسەر سنگی گەئوماتای کەوتوو', 'alt_ar' => 'تفصيل من نقش بيستون: داريوس وقدمه على صدر غوماتا الساقط', 'caption_en' => 'Darius and Gaumata. The king\'s raised hand answers the god\'s.', 'caption_ku' => 'داریوش و گەئوماتا. دەستی بەرزکراوەی پاشا وەڵامی هی خوداوەند دەداتەوە.', 'caption_ar' => 'داريوس وغوماتا. يد الملك المرفوعة تجيب يد الإله.'],
                ['img' => '08-darius', 'alt_en' => 'Detail of the Bisotun relief showing the figure of Darius the Great', 'alt_ku' => 'وردەکاریی نەخشی بێستوون کە پەیکەری داریوشی گەورە نیشان دەدات', 'alt_ar' => 'تفصيل من نقش بيستون يُظهر شخص داريوس الكبير', 'caption_en' => 'The king, with the cuneiform of his own captions beside him.', 'caption_ku' => 'پاشا، بە نووسینی بزماریی سەرنووسەکانی خۆی لە تەنیشتی.', 'caption_ar' => 'الملك، وإلى جانبه مسمارية عناوينه.'],
                ['img' => '05-qajar-photograph', 'alt_en' => 'A nineteenth-century photograph of the Bisotun relief from the Golestan Palace albums', 'alt_ku' => 'وێنەیەکی سەدەی نۆزدەی نەخشی بێستوون لە ئەلبوومەکانی کۆشکی گوڵستان', 'alt_ar' => 'صورة من القرن التاسع عشر لنقش بيستون من ألبومات قصر غولستان', 'caption_en' => 'The relief photographed in the Qajar period, from the albums of the Golestan Palace in Tehran.', 'caption_ku' => 'نەخشەکە لە سەردەمی قاجاردا وێنەگیراوە، لە ئەلبوومەکانی کۆشکی گوڵستان لە تاران.', 'caption_ar' => 'النقش مصوَّراً في العهد القاجاري، من ألبومات قصر غولستان في طهران.'],
                ['img' => '06-farhad-tarash', 'alt_en' => 'Farhad Tarash, the great smoothed rock face at the foot of Mount Bisotun', 'alt_ku' => 'فەرهاد تاراش، ڕووە بەردە گەورە ساوراوەکەی بنی چیای بێستوون', 'alt_ar' => 'فرهاد تراش، الوجه الصخري الكبير المصقول عند سفح جبل بيستون', 'caption_en' => 'Farhad Tarash: a Sasanian wall some 200 metres long, dressed smooth and never carved — the largest prepared rock face in Iran.', 'caption_ku' => 'فەرهاد تاراش: دیوارێکی ساسانیی نزیکەی ٢٠٠ مەتر درێژ، ساف تاشراوە و هەرگیز هەڵنەکەنراوە — گەورەترین ڕووە بەردی ئامادەکراو لە ئێران.', 'caption_ar' => 'فرهاد تراش: جدار ساساني طوله نحو ٢٠٠ متر، مصقول ولم يُنحت قط — أكبر وجه صخري مهيّأ في إيران.'],
                ['img' => '07-caravanserai', 'alt_en' => 'The Safavid caravanserai at Bisotun with the ruins of the Sasanian palace', 'alt_ku' => 'کاروانسەرای سەفەویی بێستوون لەگەڵ وێرانەکانی کۆشکی ساسانی', 'alt_ar' => 'الخان الصفوي في بيستون مع أطلال القصر الساساني', 'caption_en' => 'The Safavid caravanserai, built on the road the inscription watched over for two thousand years.', 'caption_ku' => 'کاروانسەرای سەفەوی، لەسەر ئەو ڕێگایە دروستکراوە کە نووسینەکە دوو هەزار ساڵ چاودێریی کرد.', 'caption_ar' => 'الخان الصفوي، المبني على الطريق الذي راقبه النقش ألفي عام.'],
                ['img' => '04-mount-bisotun', 'alt_en' => 'A wide view of Mount Bisotun rising above the caravanserai and the plain', 'alt_ku' => 'دیمەنێکی پانی چیای بێستوون کە بەسەر کاروانسەرا و دەشتدا بەرز دەبێتەوە', 'alt_ar' => 'منظر واسع لجبل بيستون مرتفعاً فوق الخان والسهل', 'caption_en' => 'Mount Bisotun above the plain of Kermanshah. The road from Babylon to Ecbatana still runs along its foot.', 'caption_ku' => 'چیای بێستوون بەسەر دەشتی کرماشاندا. ڕێگای بابلەوە بۆ ئێکباتانا هێشتا بە بنیدا دەڕوات.', 'caption_ar' => 'جبل بيستون فوق سهل كرمانشاه. لا يزال الطريق من بابل إلى إكباتانا يجري عند سفحه.'],
            ],
        ]],

        ['type' => 'unesco', 'data' => [
            'text_en' => 'Inscribed as an outstanding testimony to the interchange of influences in monumental art and writing within the Persian Empire, and as a unique witness to the development of royal inscription — the model, for a thousand years after, of how a king should address the world.',
            'text_ku' => 'تۆمارکراوە وەک شایەتییەکی بەرچاو بۆ ئاڵوگۆڕی کاریگەرییەکان لە هونەری گەورە و نووسیندا لە ناو ئیمپراتۆرییەتی فارسیدا، و وەک شایەتێکی بێهاوتای گەشەی نووسینی شاهانە — نموونە، بۆ هەزار ساڵی دواتر، کە پاشایەک چۆن قسە لەگەڵ جیهان بکات.',
            'text_ar' => 'أُدرج بوصفه شهادة بارزة على تبادل التأثيرات في الفن الضخم والكتابة داخل الإمبراطورية الفارسية، وشاهداً فريداً على تطور النقش الملكي — النموذج، لألف عام بعده، لكيف ينبغي لملك أن يخاطب العالم.',
        ]],

        ['type' => 'map', 'data' => [
            'text_en' => 'On the cliff of Mount Bisotun above the Kermanshah–Hamadan road, about 30 km east of Kermanshah (Kirmaşan), on the ancient route from Babylon to Ecbatana.',
            'text_ku' => 'لەسەر زناری چیای بێستوون لە سەرووی ڕێگای کرماشان–هەمەدان، نزیکەی ٣٠ کم لە ڕۆژهەڵاتی کرماشان، لەسەر ڕێگا کۆنەکەی بابلەوە بۆ ئێکباتانا.',
            'text_ar' => 'على جرف جبل بيستون فوق طريق كرمانشاه–همدان، على نحو ٣٠ كم شرقي كرمانشاه، على الطريق القديم من بابل إلى إكباتانا.',
        ]],

        ['type' => 'related', 'data' => ['slugs' => ['taq-e-bostan', 'takht-e-soleyman', 'khinis-reliefs']]],

        ['type' => 'sources', 'data' => [
            'items' => [
                ['title' => 'Bisotun — UNESCO World Heritage Centre', 'url' => 'https://whc.unesco.org/en/list/1222/', 'note' => 'The listing and criteria'],
                ['title' => 'Behistun Inscription — Wikipedia', 'url' => 'https://en.wikipedia.org/wiki/Behistun_Inscription', 'note' => 'With the history of decipherment'],
                ['title' => 'Behistun — Livius.org', 'url' => 'https://www.livius.org/articles/place/behistun/', 'note' => 'Translation and photographs, by Jona Lendering'],
                ['title' => 'L. W. King & R. C. Thompson, The Sculptures and Inscription of Darius the Great on the Rock of Behistûn (1907)', 'url' => '', 'note' => 'British Museum — the standard edition'],
            ],
        ]],
    ],
];
