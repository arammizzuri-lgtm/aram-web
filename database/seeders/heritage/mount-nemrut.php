<?php

/**
 * Mount Nemrut — a king's tomb on a summit. Cinematic opening on the east
 * terrace, a statement of the altitude, the lead text, the figures on a gold
 * band, the heads in a sideways strip, the sanctuary as an aside, the facts
 * as a list, the king's own words, a compact timeline, the UNESCO panel.
 *
 * Sources: UNESCO World Heritage Centre (ref. 448); Wikipedia, "Mount
 * Nemrut" and "Antiochus I of Commagene"; T. Goell & F. K. Dörner's
 * excavation reports; the Commagene Nemrut Conservation and Development
 * Program (METU, 2006–2010).
 */
return [
    'slug' => 'mount-nemrut',
    'hero_style' => 'cinematic',
    'cover' => '01-east-terrace',
    'year_from' => -62, 'year_to' => -31,
    'unesco' => true, 'unesco_year' => 1987, 'unesco_ref' => 448, 'unesco_criteria' => '(i)(iii)(iv)', 'unesco_name' => 'Nemrut Dağ',
    'period_en' => '1st century BC', 'period_ku' => 'سەدەی یەکەمی پ.ز', 'period_ar' => 'القرن الأول ق.م',
    'summary_en' => 'A king\'s tomb on a mountain summit: an artificial peak of crushed stone flanked by colossal seated gods, their fallen heads facing the sunrise and the sunset.',
    'summary_ku' => 'گۆڕی پاشایەک لەسەر لووتکەی چیایەک: لووتکەیەکی دەستکرد لە بەردی وردکراو کە پەیکەرە دانیشتووە گەورەکانی خوداوەندەکان لە دوو لایەوە دەوریان داوە، سەرە کەوتووەکانیان ڕوویان لە خۆرهەڵات و خۆرئاوایە.',
    'summary_ar' => 'قبر ملك على قمة جبل: قمة اصطناعية من الحجارة المهشّمة تحفّ بها آلهة جالسة ضخمة، رؤوسها المتساقطة تواجه الشروق والغروب.',
    'intro_en' => 'Antiochus of Commagene ruled a small kingdom squeezed between Rome and Parthia, and decided to be buried where neither could overlook him: on the highest peak of his country, under a mountain he built himself, between the gods — in whose company, his inscription says, he had every right to sit.',
    'intro_ku' => 'ئەنتیۆخۆسی کۆماژین فەرمانڕەوایی شانشینێکی بچووکی دەکرد کە لە نێوان ڕۆما و پارتدا گوشرابوو، و بڕیاری دا لەو شوێنە بنێژرێت کە هیچیان نەتوانن بەسەریدا بڕوانن: لەسەر بەرزترین لووتکەی وڵاتەکەی، لە ژێر چیایەک کە خۆی دروستی کرد، لە نێوان خوداوەندەکاندا — کە بەپێی نووسینەکەی، هەموو مافێکی هەبوو لە نێویاندا دابنیشێت.',
    'intro_ar' => 'حكم أنطيوخوس الكوماجيني مملكة صغيرة محشورة بين روما وبارثيا، وقرر أن يُدفن حيث لا يطلّ عليه أيّ منهما: على أعلى قمة في بلاده، تحت جبل بناه بنفسه، بين الآلهة — التي كان له، بحسب نقشه، كل الحق في الجلوس بينها.',

    'sections' => [
        ['type' => 'statement', 'data' => [
            'align' => 'center',
            'kicker_en' => 'The summit stands at', 'kicker_ku' => 'لووتکەکە لە بەرزیی', 'kicker_ar' => 'ترتفع القمة إلى',
            'big_en' => '2,150 metres', 'big_ku' => '٢,١٥٠ مەتر', 'big_ar' => '٢,١٥٠ متراً',
            'text_en' => 'The highest point of Commagene, in the Anti-Taurus. The tumulus adds another 49 metres of hand-broken stone on top of the natural peak.',
            'text_ku' => 'بەرزترین خاڵی کۆماژین، لە ئەنتی-تۆرۆس. گردە گۆڕەکە ٤٩ مەتری تری بەردی بە دەست شکێنراو بەسەر لووتکە سروشتییەکەدا زیاد دەکات.',
            'text_ar' => 'أعلى نقطة في كوماجيني، في طوروس المضادّ. ويضيف التلّ الجنائزي ٤٩ متراً أخرى من الحجارة المكسورة باليد فوق القمة الطبيعية.',
        ]],

        ['type' => 'text', 'data' => [
            'layout' => 'lead',
            'heading_en' => 'A kingdom between two empires', 'heading_ku' => 'شانشینێک لە نێوان دوو ئیمپراتۆرییەتدا', 'heading_ar' => 'مملكة بين إمبراطوريتين',
            'body_en' => "Commagene was a sliver of land on the upper Euphrates, Greek-speaking at court and Persian in its dynasty, which claimed descent from both Alexander\'s generals and the kings of Persia. Antiochus I, who ruled from about 70 to 31 BC, made that double inheritance his religion. On Nemrut he set up Zeus beside Oromasdes, Apollo beside Mithras, Heracles beside Artagnes — each god with two names and one body — and placed himself among them.\n\nThe sanctuary he built, which he called a hierothesion, has two great terraces on the east and west of the summit, each with a row of five seated figures eight to nine metres high: the king, the goddess of Commagene, and the three composite gods, guarded at each end by a lion and an eagle. Between the terraces rises the tumulus, a cone of limestone chips fifty metres high and a hundred and fifty across, under which his tomb is believed to lie. No one has found it.\n\nThe heads have fallen — earthquakes, not iconoclasts, though some noses were broken deliberately — and stand now in rows on the ground, looking out over the mountains. Visitors climb before dawn to watch the sun come up over them, as the king intended his priests to do on the tenth and sixteenth of every month.",
            'body_ku' => "کۆماژین پارچەیەک زەوی بوو لەسەر فوراتی سەروو، لە دیواندا یۆنانی قسەکەر و لە خانەدانەکەیدا فارسی، کە داوای ڕەچەڵەکی لە هەردوو سەرکردەکانی ئەسکەندەر و پاشاکانی فارس دەکرد. ئەنتیۆخۆسی یەکەم، کە لە نزیکەی ٧٠ تا ٣١ پ.ز فەرمانڕەوایی کرد، ئەو میراتە دووانەیەی کردە ئایینی خۆی. لەسەر نەمرود زێوسی لە تەنیشت ئۆرۆمازدێس دانا، ئەپۆڵۆ لە تەنیشت میترا، هێراکلێس لە تەنیشت ئارتاگنێس — هەر خوداوەندێک بە دوو ناو و یەک لاشە — و خۆی لە نێویاندا دانا.\n\nئەو پیرۆزگایەی دروستی کرد، کە پێی دەوت هیەرۆتێسیۆن، دوو سەکۆی گەورەی لە ڕۆژهەڵات و ڕۆژئاوای لووتکەکە هەیە، هەریەکەیان بە ڕیزێک لە پێنج پەیکەری دانیشتووی هەشت بۆ نۆ مەتر بەرز: پاشا، خواوەندی کۆماژین، و سێ خوداوەندە تێکەڵەکە، لە هەر کۆتاییەک بە شێرێک و هەڵۆیەک پاسەوانی کراون. لە نێوان سەکۆکاندا گردە گۆڕەکە بەرز دەبێتەوە، قووچەکێک لە پارچە بەردی کلسی پەنجا مەتر بەرز و سەد و پەنجا مەتر پان، کە وا باوەڕ دەکرێت گۆڕەکەی لە ژێریدا بێت. کەس نەیدۆزیوەتەوە.\n\nسەرەکان کەوتوون — بوومەلەرزە، نەک وێنەشکێنەکان، هەرچەندە هەندێک لووت بە مەبەست شکێنراون — و ئێستا بە ڕیز لەسەر زەوی ڕاوەستاون، بەسەر چیاکاندا دەڕوانن. سەردانکەران پێش بەرەبەیان سەردەکەون بۆ تەماشاکردنی خۆر کە بەسەریاندا هەڵدێت، وەک پاشا مەبەستی بوو کاهینەکانی لە دە و شازدەی هەر مانگێکدا بیکەن.",
            'body_ar' => "كانت كوماجيني شريحة من الأرض على الفرات الأعلى، يونانية اللسان في البلاط وفارسية السلالة، تدّعي النسب إلى قادة الإسكندر وملوك فارس معاً. وجعل أنطيوخوس الأول، الذي حكم من نحو ٧٠ إلى ٣١ ق.م، ذلك الإرث المزدوج ديانته. على نمرود أقام زيوس إلى جانب أورومازدس، وأبولو إلى جانب ميثرا، وهرقل إلى جانب أرتاغنس — كل إله باسمين وجسد واحد — ووضع نفسه بينهم.\n\nللحرم الذي بناه، وسمّاه هييروثيسيون، مصطبتان كبيرتان شرقي القمة وغربيها، على كل منهما صفّ من خمسة شخوص جالسة بارتفاع ثمانية إلى تسعة أمتار: الملك، وإلهة كوماجيني، والآلهة المركّبة الثلاثة، يحرسها عند كل طرف أسد ونسر. وبين المصطبتين يرتفع التلّ الجنائزي، مخروط من فتات الحجر الجيري ارتفاعه خمسون متراً وقطره مئة وخمسون، يُعتقد أن قبره يرقد تحته. ولم يجده أحد.\n\nسقطت الرؤوس — بفعل الزلازل لا محطّمي الأيقونات، وإن كُسرت بعض الأنوف عمداً — وتقف اليوم صفوفاً على الأرض، ناظرةً إلى الجبال. يصعد الزوّار قبل الفجر ليشاهدوا الشمس تشرق فوقها، كما أراد الملك لكهنته أن يفعلوا في العاشر والسادس عشر من كل شهر.",
        ]],

        ['type' => 'stats', 'data' => [
            'layout' => 'band',
            'items' => [
                ['value' => '49', 'unit_en' => 'm', 'unit_ku' => 'م', 'unit_ar' => 'م', 'label_en' => 'The tumulus', 'label_ku' => 'گردە گۆڕەکە', 'label_ar' => 'التلّ الجنائزي', 'note_en' => 'high, 152 m across, of fist-sized stones', 'note_ku' => 'بەرز، ١٥٢ م پان، لە بەردی قەبارە مشت', 'note_ar' => 'ارتفاعاً، ١٥٢ م قطراً، من حجارة بحجم القبضة'],
                ['value' => '9', 'unit_en' => 'm', 'unit_ku' => 'م', 'unit_ar' => 'م', 'label_en' => 'The seated statues', 'label_ku' => 'پەیکەرە دانیشتووەکان', 'label_ar' => 'التماثيل الجالسة', 'note_en' => '8–9 m, the heads alone about 2 m', 'note_ku' => '٨–٩ م، سەرەکان بە تەنها نزیکەی ٢ م', 'note_ar' => '٨–٩ م، والرؤوس وحدها نحو ٢ م'],
                ['value' => '62', 'unit_en' => 'BC', 'unit_ku' => 'پ.ز', 'unit_ar' => 'ق.م', 'label_en' => 'The sanctuary begun', 'label_ku' => 'پیرۆزگاکە دەستی پێکرا', 'label_ar' => 'بدء الحرم', 'note_en' => 'the date most read from the lion horoscope', 'note_ku' => 'ئەو مێژووەی زۆرترین جار لە فاڵی شێرەکەوە دەخوێنرێتەوە', 'note_ar' => 'التاريخ الأكثر قراءة من طالع الأسد'],
                ['value' => '2', 'label_en' => 'Terraces', 'label_ku' => 'سەکۆ', 'label_ar' => 'مصطبتان', 'note_en' => 'east and west, each with five gods', 'note_ku' => 'ڕۆژهەڵات و ڕۆژئاوا، هەریەکە بە پێنج خوداوەند', 'note_ar' => 'شرقية وغربية، في كل منهما خمسة آلهة'],
                ['value' => '1987', 'label_en' => 'World Heritage', 'label_ku' => 'میراتی جیهانی', 'label_ar' => 'التراث العالمي', 'note_en' => 'UNESCO, criteria (i)(iii)(iv)', 'note_ku' => 'یونسکۆ، پێوەرەکانی (i)(iii)(iv)', 'note_ar' => 'اليونسكو، المعايير (i)(iii)(iv)'],
            ],
        ]],

        ['type' => 'gallery', 'data' => [
            'layout' => 'strip',
            'heading_en' => 'The heads', 'heading_ku' => 'سەرەکان', 'heading_ar' => 'الرؤوس',
            'text_en' => 'Greek faces, Persian caps and hair: the portrait of a kingdom that belonged to both worlds.',
            'text_ku' => 'ڕووخساری یۆنانی، کڵاو و قژی فارسی: وێنەی شانشینێک کە هی هەردوو جیهان بوو.',
            'text_ar' => 'وجوه يونانية، وقلنسوات وشعر فارسيان: صورة مملكة انتمت إلى العالمين.',
            'images' => [
                ['img' => '04-antiochus', 'alt_en' => 'The colossal stone head of King Antiochus I on the west terrace of Mount Nemrut', 'alt_ku' => 'سەرە بەردینە گەورەکەی پاشا ئەنتیۆخۆسی یەکەم لەسەر سەکۆی ڕۆژئاوای چیای نەمرود', 'alt_ar' => 'رأس الملك أنطيوخوس الأول الحجري الضخم على المصطبة الغربية لجبل نمرود', 'caption_en' => 'Antiochus himself, west terrace, in the Armenian tiara of his Persian ancestors.', 'caption_ku' => 'ئەنتیۆخۆس خۆی، سەکۆی ڕۆژئاوا، بە تاجی ئەرمەنیی باوباپیرانی فارسی.', 'caption_ar' => 'أنطيوخوس نفسه، المصطبة الغربية، بتاج أجداده الفرس الأرمني.'],
                ['img' => '05-heracles', 'alt_en' => 'The bearded stone head of Heracles-Artagnes-Ares on the east terrace of Mount Nemrut', 'alt_ku' => 'سەرە بەردینە ڕیشدارەکەی هێراکلێس-ئارتاگنێس-ئارێس لەسەر سەکۆی ڕۆژهەڵاتی چیای نەمرود', 'alt_ar' => 'رأس هرقل-أرتاغنس-آريس الحجري الملتحي على المصطبة الشرقية لجبل نمرود', 'caption_en' => 'Heracles-Artagnes-Ares: a Greek hero, a Persian god of victory and a Greek god of war in one bearded head.', 'caption_ku' => 'هێراکلێس-ئارتاگنێس-ئارێس: پاڵەوانێکی یۆنانی، خوداوەندێکی فارسیی سەرکەوتن و خوداوەندێکی یۆنانیی جەنگ لە یەک سەری ڕیشداردا.', 'caption_ar' => 'هرقل-أرتاغنس-آريس: بطل يوناني وإله نصر فارسي وإله حرب يوناني في رأس ملتحٍ واحد.'],
                ['img' => '03-the-heads', 'alt_en' => 'A row of fallen colossal heads lit by low sun on the east terrace of Mount Nemrut', 'alt_ku' => 'ڕیزێک لە سەرە گەورە کەوتووەکان بە خۆری نزم ڕووناککراون لەسەر سەکۆی ڕۆژهەڵاتی چیای نەمرود', 'alt_ar' => 'صفّ من الرؤوس الضخمة المتساقطة تضيئها شمس منخفضة على المصطبة الشرقية لجبل نمرود', 'caption_en' => 'The east terrace in the first light, the bodies still enthroned behind their heads.', 'caption_ku' => 'سەکۆی ڕۆژهەڵات لە یەکەم ڕووناکیدا، لاشەکان هێشتا لەسەر تەخت لە پشت سەرەکانیانەوە.', 'caption_ar' => 'المصطبة الشرقية في أول الضوء، والأجساد لا تزال متوّجة على عروشها خلف رؤوسها.'],
                ['img' => '07-thrones', 'alt_en' => 'The seated statues of the east terrace at Mount Nemrut, headless on their thrones, with the heads below', 'alt_ku' => 'پەیکەرە دانیشتووەکانی سەکۆی ڕۆژهەڵات لە چیای نەمرود، بێ سەر لەسەر تەختەکانیان، سەرەکان لە خوارەوە', 'alt_ar' => 'التماثيل الجالسة في المصطبة الشرقية لجبل نمرود، بلا رؤوس على عروشها، والرؤوس في الأسفل', 'caption_en' => 'The thrones. Each god\'s names are carved on the back of his seat, in Greek.', 'caption_ku' => 'تەختەکان. ناوەکانی هەر خوداوەندێک لە پشتی کورسییەکەیدا بە یۆنانی هەڵکەنراون.', 'caption_ar' => 'العروش. أسماء كل إله منحوتة على ظهر مقعده، باليونانية.'],
                ['img' => '02-sunrise', 'alt_en' => 'Sunrise over the east terrace and tumulus of Mount Nemrut', 'alt_ku' => 'خۆرهەڵاتن بەسەر سەکۆی ڕۆژهەڵات و گردە گۆڕەکەی چیای نەمروددا', 'alt_ar' => 'شروق الشمس فوق المصطبة الشرقية والتلّ الجنائزي لجبل نمرود', 'caption_en' => 'Sunrise, seen the way the sanctuary was built to be seen.', 'caption_ku' => 'خۆرهەڵاتن، بەو شێوەیە دەبینرێت کە پیرۆزگاکە بۆ بینینی دروستکرا.', 'caption_ar' => 'الشروق، كما بُني الحرم ليُرى.'],
            ],
        ]],

        ['type' => 'text', 'data' => [
            'layout' => 'aside',
            'heading_en' => 'How a mountain was made', 'heading_ku' => 'چیایەک چۆن دروستکرا', 'heading_ar' => 'كيف صُنع جبل',
            'body_en' => "The sanctuary is an exercise in moving stone to the one place it is hardest to move it. The summit was cut down to make the two terraces, and the rubble, broken to the size of a fist, was heaped over the peak into a cone that has not settled in two thousand years — which is why no one has been able to tunnel into it without it pouring back. The statues were built up from limestone blocks, each course set on the next without mortar, the heads carved as single pieces and lifted into place.\n\nBehind the seated gods ran a line of sandstone stelae: the king\'s Persian ancestors on one side, his Greek ancestors on the other, and the dexiosis reliefs in which Antiochus shakes hands with each god as an equal — among the oldest images of a handshake known. On a slab beside them a lion wears nineteen stars and a crescent, with the planets Jupiter, Mercury and Mars above its back: a horoscope, most scholars read, for 7 July 62 BC, though others have argued for 49 BC.\n\nA processional way linked the terraces around the mountain; the king\'s long Greek inscription on the backs of the thrones set out the festivals, the priests\' Persian robes, and the endowments of villages that were to pay for it all, for ever.",
            'body_ku' => "پیرۆزگاکە ڕاهێنانێکە لە گواستنەوەی بەرد بۆ ئەو تاکە شوێنەی سەختترینە بۆ گواستنەوەی. لووتکەکە بڕدرا بۆ دروستکردنی دوو سەکۆکە، و وردەبەردەکە، کە بە قەبارەی مشت شکێنرابوو، بەسەر لووتکەکەدا کەڵەکە کرا بۆ قووچەکێک کە لە دوو هەزار ساڵدا دانەنیشتووە — ئەمەش هۆکارە کە کەس نەیتوانیوە تونێلی تێدا لێبدات بێ ئەوەی بگەڕێتەوە و بڕژێتە سەری. پەیکەرەکان لە بلۆکی بەردی کلسی بەرزکرانەوە، هەر ڕیزێک بێ قوڕ لەسەر ئەوی تر دانرا، سەرەکان وەک تاکە پارچە هەڵکەنران و بۆ شوێنی خۆیان هەڵگیران.\n\nلە پشت خوداوەندە دانیشتووەکانەوە ڕیزێک ستێلەی بەردی لم دەڕۆیشت: باوباپیرە فارسییەکانی پاشا لە لایەک، باوباپیرە یۆنانییەکانی لە لایەکەی تر، و نەخشەکانی دێکسیۆسیس کە تێیاندا ئەنتیۆخۆس وەک هاوتا دەست لەگەڵ هەر خوداوەندێک دەگوشێت — لە کۆنترین وێنەکانی دەستگوشینی ناسراو. لەسەر تابلۆیەک لە تەنیشتیان شێرێک نۆزدە ئەستێرە و مانگێکی نوێ لەبەر دەکات، بە هەسارەکانی موشتەری و عوتارد و مەریخ لە سەرووی پشتییەوە: فاڵێک، وەک زۆربەی زانایان دەیخوێننەوە، بۆ ٧ی تەمووزی ٦٢ پ.ز، هەرچەندە هەندێکی تر مشتومڕیان لەسەر ٤٩ پ.ز کردووە.\n\nڕێگایەکی ڕێوڕەسم سەکۆکانی بە دەوری چیاکەدا پێکەوە دەبەستەوە؛ نووسینە درێژە یۆنانییەکەی پاشا لە پشتی تەختەکاندا جەژنەکان، جلوبەرگی فارسیی کاهینەکان، و بەخشینی ئەو گوندانەی دیاری کرد کە دەبوو هەتاهەتایە پارەی هەموو ئەمە بدەن.",
            'body_ar' => "الحرم تمرين في نقل الحجر إلى المكان الوحيد الذي يصعب نقله إليه أكثر من أي مكان. قُطعت القمة لصنع المصطبتين، وكُوّم الركام، مكسوراً إلى حجم القبضة، فوق القمة مخروطاً لم يهبط في ألفي عام — ولهذا لم يستطع أحد أن يحفر نفقاً فيه دون أن ينهال عليه. بُنيت التماثيل من كتل الحجر الجيري، كل مدماك فوق الذي يليه بلا ملاط، ونُحتت الرؤوس قطعاً مفردة ورُفعت إلى مواضعها.\n\nخلف الآلهة الجالسة جرى صفّ من مسلّات الحجر الرملي: أجداد الملك الفرس من جهة، وأجداده اليونان من الجهة الأخرى، ونقوش الديكسيوسيس التي يصافح فيها أنطيوخوس كل إله نداً لندّ — وهي من أقدم صور المصافحة المعروفة. وعلى لوح بجانبها يرتدي أسد تسع عشرة نجمة وهلالاً، وفوق ظهره كواكب المشتري وعطارد والمريخ: طالع، كما يقرأه معظم الباحثين، ليوم ٧ تموز ٦٢ ق.م، وإن جادل آخرون لصالح ٤٩ ق.م.\n\nربط طريق موكبي المصطبتين حول الجبل؛ وحدّد نقش الملك اليوناني الطويل على ظهور العروش الأعياد، وأثواب الكهنة الفارسية، وأوقاف القرى التي كان عليها أن تدفع ثمن كل ذلك، إلى الأبد.",
        ]],

        ['type' => 'facts', 'data' => [
            'layout' => 'list',
            'items' => [
                ['label_en' => 'Two feasts a month', 'label_ku' => 'دوو جەژن لە مانگێکدا', 'label_ar' => 'عيدان في الشهر', 'text_en' => 'Antiochus ordered his birthday celebrated on the sixteenth of every month and his coronation on the tenth, with feasts on the terraces paid for by the villages he endowed.', 'text_ku' => 'ئەنتیۆخۆس فەرمانی دا لەدایکبوونی لە شازدەی هەر مانگێک و تاجگوزارییەکەی لە دەیەم ئاهەنگ بگێڕدرێت، بە خوان لەسەر سەکۆکان کە پارەکەی لەلایەن ئەو گوندانەوە دەدرا کە تەرخانی کردبوون.', 'text_ar' => 'أمر أنطيوخوس بالاحتفال بميلاده في السادس عشر من كل شهر وبتتويجه في العاشر، بولائم على المصطبتين تدفع ثمنها القرى التي وقفها.'],
                ['label_en' => 'The tomb', 'label_ku' => 'گۆڕەکە', 'label_ar' => 'القبر', 'text_en' => 'Every attempt to tunnel into the tumulus has failed: the loose stone flows back like sand. The king is still believed to be inside, undisturbed.', 'text_ku' => 'هەموو هەوڵێک بۆ لێدانی تونێل لە گردە گۆڕەکەدا شکستی هێناوە: بەردە شلەکە وەک لم دەگەڕێتەوە. هێشتا وا باوەڕ دەکرێت پاشا لە ناوەوەیە، دەست لێنەدراو.', 'text_ar' => 'فشلت كل محاولة لحفر نفق في التلّ: فالحجارة السائبة تنساب عائدة كالرمل. ولا يزال يُعتقد أن الملك في الداخل، لم يُمسّ.'],
                ['label_en' => 'A handshake', 'label_ku' => 'دەستگوشینێک', 'label_ar' => 'مصافحة', 'text_en' => 'The dexiosis stelae, in which the king clasps the right hand of Zeus, Apollo and Heracles in turn, are among the oldest known images of two figures shaking hands.', 'text_ku' => 'ستێلەکانی دێکسیۆسیس، کە تێیاندا پاشا بە نۆرە دەستی ڕاستی زێوس و ئەپۆڵۆ و هێراکلێس دەگوشێت، لە کۆنترین وێنە ناسراوەکانی دوو کەسن کە دەست دەگوشن.', 'text_ar' => 'مسلّات الديكسيوسيس، التي يصافح فيها الملك اليد اليمنى لزيوس وأبولو وهرقل بالتناوب، من أقدم الصور المعروفة لشخصين يتصافحان.'],
                ['label_en' => 'A new calendar', 'label_ku' => 'ساڵنامەیەکی نوێ', 'label_ar' => 'تقويم جديد', 'text_en' => 'Antiochus reset the Commagenian year, which had followed the moon, to the Egyptian Sothic cycle — a king who wanted even time to run on his terms.', 'text_ku' => 'ئەنتیۆخۆس ساڵی کۆماژینی، کە شوێن مانگ کەوتبوو، لەسەر خولی سۆسیی میسری ڕێکخستەوە — پاشایەک کە دەیویست تەنانەت کاتیش بەپێی مەرجەکانی ئەو بڕوات.', 'text_ar' => 'أعاد أنطيوخوس ضبط السنة الكوماجينية، التي كانت تتبع القمر، على الدورة السوثية المصرية — ملك أراد حتى للزمن أن يجري على شروطه.'],
                ['label_en' => 'Found by an engineer', 'label_ku' => 'لەلایەن ئەندازیارێکەوە دۆزرایەوە', 'label_ar' => 'اكتشفه مهندس', 'text_en' => 'The sanctuary was reported in 1881 by Karl Sester, a German engineer surveying transport routes for the Ottomans. The first full excavation waited until Theresa Goell and Friedrich Karl Dörner in the 1950s.', 'text_ku' => 'پیرۆزگاکە لە ١٨٨١ لەلایەن کارل سێستەرەوە ڕاپۆرت کرا، ئەندازیارێکی ئەڵمانی کە ڕێگاکانی گواستنەوەی بۆ عوسمانییەکان ڕووپێو دەکرد. یەکەم هەڵکۆڵینی تەواو تا تێرێزا گۆێڵ و فریدریش کارل دۆرنەر لە پەنجاکاندا چاوەڕێی کرد.', 'text_ar' => 'أبلغ عن الحرم عام ١٨٨١ كارل زستر، مهندس ألماني كان يمسح طرق النقل للعثمانيين. وانتظر أول تنقيب كامل حتى تيريزا غويل وفريدريش كارل دورنر في الخمسينيات.'],
                ['label_en' => 'The photographer', 'label_ku' => 'وێنەگرەکە', 'label_ar' => 'المصوّر', 'text_en' => 'Ara Güler\'s photographs of the heads in the late 1950s, made for a magazine, carried Nemrut around the world and are counted among his finest work.', 'text_ku' => 'وێنەکانی ئارا گولەر لە سەرەکان لە کۆتایی پەنجاکاندا، کە بۆ گۆڤارێک گیرابوون، نەمرودیان بە جیهاندا گەڕاند و لە باشترین کارەکانی دادەنرێن.', 'text_ar' => 'صور آرا غولر للرؤوس في أواخر الخمسينيات، التي التُقطت لمجلة، حملت نمرود حول العالم وتُعدّ من أجمل أعماله.'],
            ],
        ]],

        ['type' => 'quote', 'data' => [
            'text_en' => 'I decorated it with representations of their forms by all the kinds of art.',
            'text_ku' => 'بە هەموو جۆرەکانی هونەر بە وێنەی شێوەکانیان ڕازاندمەوە.',
            'text_ar' => 'زيّنته بتمثيل أشكالهم بكل أنواع الفن.',
            'source_en' => 'Antiochus I, in the Greek inscription on the backs of the thrones, on the gods he set up beside himself', 'source_ku' => 'ئەنتیۆخۆسی یەکەم، لە نووسینە یۆنانییەکەی پشتی تەختەکاندا، لەسەر ئەو خوداوەندانەی لە تەنیشت خۆی داینان', 'source_ar' => 'أنطيوخوس الأول، في النقش اليوناني على ظهور العروش، عن الآلهة التي أقامها إلى جانب نفسه',
        ]],

        ['type' => 'gallery', 'data' => [
            'layout' => 'pairs',
            'images' => [
                ['img' => '06-the-tumulus', 'alt_en' => 'The conical tumulus of Mount Nemrut, a pale heap of broken stone against the sky', 'alt_ku' => 'گردە گۆڕە قووچەکییەکەی چیای نەمرود، کۆمەڵێکی کاڵ لە بەردی شکاو بەرامبەر ئاسمان', 'alt_ar' => 'التلّ الجنائزي المخروطي لجبل نمرود، كومة شاحبة من الحجارة المكسورة في مواجهة السماء', 'caption_en' => 'The tumulus: 49 metres of limestone chips over the natural summit.', 'caption_ku' => 'گردە گۆڕەکە: ٤٩ مەتر پارچە بەردی کلسی بەسەر لووتکە سروشتییەکەدا.', 'caption_ar' => 'التلّ الجنائزي: ٤٩ متراً من فتات الحجر الجيري فوق القمة الطبيعية.'],
                ['img' => '08-towards-the-euphrates', 'alt_en' => 'The view south-east from the summit of Mount Nemrut over ridges towards the Euphrates', 'alt_ku' => 'دیمەنی باشووری ڕۆژهەڵات لە لووتکەی چیای نەمرودەوە بەسەر گردەکاندا بەرەو فورات', 'alt_ar' => 'المنظر جنوب شرقاً من قمة جبل نمرود فوق السلاسل نحو الفرات', 'caption_en' => 'From the summit, south-east towards the Euphrates — all of it once Commagene.', 'caption_ku' => 'لە لووتکەکەوە، باشووری ڕۆژهەڵات بەرەو فورات — هەمووی جارێک کۆماژین بوو.', 'caption_ar' => 'من القمة، جنوب شرقاً نحو الفرات — كان كل ذلك كوماجيني يوماً.'],
            ],
        ]],

        ['type' => 'timeline', 'data' => [
            'layout' => 'compact',
            'events' => [
                ['date_en' => 'c. 70 BC', 'date_ku' => 'نزیکەی 70 پ.ز', 'date_ar' => 'نحو 70 ق.م', 'title_en' => 'Antiochus I becomes king of Commagene', 'title_ku' => 'ئەنتیۆخۆسی یەکەم دەبێتە پاشای کۆماژین', 'title_ar' => 'أنطيوخوس الأول يصبح ملك كوماجيني'],
                ['date_en' => '62 BC', 'date_ku' => '62 پ.ز', 'date_ar' => '62 ق.م', 'title_en' => 'The hierothesion is built', 'title_ku' => 'هیەرۆتێسیۆن دروستدەکرێت', 'title_ar' => 'يُبنى الهييروثيسيون'],
                ['date_en' => 'c. 31 BC', 'date_ku' => 'نزیکەی 31 پ.ز', 'date_ar' => 'نحو 31 ق.م', 'title_en' => 'The king dies', 'title_ku' => 'پاشا کۆچی دوایی دەکات', 'title_ar' => 'وفاة الملك'],
                ['date_en' => 'AD 72', 'date_ku' => '72 ز', 'date_ar' => '72 م', 'title_en' => 'Rome annexes Commagene', 'title_ku' => 'ڕۆما کۆماژین داگیر دەکات', 'title_ar' => 'روما تضمّ كوماجيني'],
                ['date_en' => '1881', 'date_ku' => '1881', 'date_ar' => '1881', 'title_en' => 'Karl Sester reports the summit', 'title_ku' => 'کارل سێستەر لووتکەکە ڕاپۆرت دەکات', 'title_ar' => 'كارل زستر يبلغ عن القمة'],
                ['date_en' => '1953', 'date_ku' => '1953', 'date_ar' => '1953', 'title_en' => 'Goell and Dörner begin excavation', 'title_ku' => 'گۆێڵ و دۆرنەر دەست بە هەڵکۆڵین دەکەن', 'title_ar' => 'غويل ودورنر يبدآن التنقيب'],
                ['date_en' => '1987', 'date_ku' => '1987', 'date_ar' => '1987', 'title_en' => 'World Heritage', 'title_ku' => 'میراتی جیهانی', 'title_ar' => 'التراث العالمي'],
                ['date_en' => '2006–2010', 'date_ku' => '2006–2010', 'date_ar' => '2006–2010', 'title_en' => 'Conservation programme', 'title_ku' => 'بەرنامەی پاراستن', 'title_ar' => 'برنامج الصون'],
            ],
        ]],

        ['type' => 'unesco', 'data' => [
            'text_en' => 'Inscribed as a unique artistic achievement of the Hellenistic period — "one of the most ambitious constructions" of its age — and as the testimony of a civilisation that fused Greek and Persian religion, art and kingship on one summit.',
            'text_ku' => 'تۆمارکراوە وەک دەستکەوتێکی هونەریی بێهاوتای سەردەمی هێلێنیستی — «یەکێک لە مەزنترین بیناکان»ی سەردەمەکەی — و وەک شایەتیی شارستانییەتێک کە ئایین و هونەر و پاشایەتیی یۆنانی و فارسی لەسەر یەک لووتکە تێکەڵ کرد.',
            'text_ar' => 'أُدرج بوصفه إنجازاً فنياً فريداً من العصر الهلنستي — «أحد أكثر الإنشاءات طموحاً» في عصره — وشهادةً على حضارة صهرت الدين والفن والملكية اليونانية والفارسية على قمة واحدة.',
        ]],

        ['type' => 'map', 'data' => [
            'text_en' => 'The summit of Nemrut Dağı in the Anti-Taurus, Adıyaman province, about 40 km north of Kahta; reached by road from Adıyaman or Malatya, usually for sunrise between April and October.',
            'text_ku' => 'لووتکەی چیای نەمرود لە ئەنتی-تۆرۆس، پارێزگای ئادیامان، نزیکەی ٤٠ کم لە باکووری کاهتا؛ بە ڕێگای ئۆتۆمبێل لە ئادیامان یان مەلەتیاوە پێی دەگەیت، زۆرجار بۆ خۆرهەڵاتن لە نێوان نیسان و تشرینی یەکەمدا.',
            'text_ar' => 'قمة جبل نمرود في طوروس المضادّ، محافظة أديامان، على نحو ٤٠ كم شمال كاهتا؛ يُوصل إليها بالطريق من أديامان أو ملطية، عادةً لمشاهدة الشروق بين نيسان وتشرين الأول.',
        ]],

        ['type' => 'related', 'data' => ['slugs' => ['bisotun', 'amed-city-walls', 'ishak-pasha-palace']]],

        ['type' => 'sources', 'data' => [
            'items' => [
                ['title' => 'Nemrut Dağ — UNESCO World Heritage Centre', 'url' => 'https://whc.unesco.org/en/list/448/', 'note' => 'The listing and criteria'],
                ['title' => 'Mount Nemrut — Wikipedia', 'url' => 'https://en.wikipedia.org/wiki/Mount_Nemrut', 'note' => 'With the excavation history and dimensions'],
                ['title' => 'Antiochus I of Commagene — Wikipedia', 'url' => 'https://en.wikipedia.org/wiki/Antiochus_I_of_Commagene', 'note' => 'The king, his cult and his inscription'],
                ['title' => 'D. H. Sanders (ed.), Nemrud Dağı: The Hierothesion of Antiochus I of Commagene (1996)', 'url' => '', 'note' => 'Theresa Goell\'s excavation results'],
            ],
        ]],
    ],
];
