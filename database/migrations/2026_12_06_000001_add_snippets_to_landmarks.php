<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each landmark's search snippet: the page's description for Google and for
 * link previews, a whole sentence or two of at most about 155 characters, in
 * English, Kurdish and Arabic. Until now the description was the introduction
 * cut at 200 characters, mid-sentence. The 21 landmarks get theirs here; a
 * snippet the admin has already written is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landmarks', function (Blueprint $table) {
            $table->text('snippet_en')->nullable()->after('summary_ar');
            $table->text('snippet_ku')->nullable()->after('snippet_en');
            $table->text('snippet_ar')->nullable()->after('snippet_ku');
        });

        foreach (self::SNIPPETS as $slug => [$en, $ku, $ar]) {
            DB::table('landmarks')->where('slug', $slug)->whereNull('snippet_en')
                ->update(['snippet_en' => $en, 'snippet_ku' => $ku, 'snippet_ar' => $ar]);
        }
    }

    public function down(): void
    {
        Schema::table('landmarks', function (Blueprint $table) {
            $table->dropColumn(['snippet_en', 'snippet_ku', 'snippet_ar']);
        });
    }

    private const SNIPPETS = [
        'gobekli-tepe' => [
            'The oldest monumental architecture known: rings of carved T-shaped pillars raised some 11,000 years ago, before farming, pottery or writing.',
            'کۆنترین تەلارسازیی گەورەی ناسراو لە جیهاندا: بازنەی ستوونی بەردی T-شێوەی نەخشێنراو، نزیکەی ١١ هەزار ساڵ لەمەوبەر، پێش کشتوکاڵ و نووسین.',
            'أقدم عمارة ضخمة معروفة على الأرض: حلقات من أعمدة منحوتة على شكل حرف T شُيّدت قبل نحو ١١ ألف عام، قبل الزراعة والكتابة.',
        ],
        'shanidar-cave' => [
            'A great cave in the Zagros, in Erbil province, where Neanderthals lived, cared for their injured and buried their dead some 65,000 to 35,000 years ago.',
            'ئەشکەوتێکی گەورە لە زاگرۆس، لە پارێزگای هەولێر، کە نیاندەرتاڵەکان تێیدا ژیاون، چاودێریی بریندارەکانیان کردووە و مردووەکانیان ناشتووە.',
            'كهف كبير في زاغروس بمحافظة أربيل عاش فيه إنسان نياندرتال واعتنى بجرحاه ودفن موتاه، قبل ٦٥ إلى ٣٥ ألف عام.',
        ],
        'tell-brak' => [
            'One of the first cities on earth: a mound forty metres high on the Khabur plain, with the Eye Temple and a fortress of the kings of Akkad.',
            'یەکێک لە یەکەمین شارەکانی جیهان: گردێکی چل مەتری لەسەر دەشتی خابوور، بە پەرستگای چاو و قەڵایەکی پاشاکانی ئەکەد.',
            'من أوائل المدن على الأرض: تلّ بارتفاع أربعين متراً على سهل الخابور، فيه معبد العيون وحصن لملوك أكّاد.',
        ],
        'urkesh' => [
            'Capital of the Hurrians, a mountain people: a temple on a monumental terrace, a royal palace, and the oldest words written in their language.',
            'پایتەختی حۆرییەکان، گەلێکی چیاکان: پەرستگایەک لەسەر سەکۆیەکی گەورە، کۆشکێکی پاشایەتی، و کۆنترین وشەکان کە بە زمانەکەیان نووسراون.',
            'عاصمة الحوريين، شعب الجبال: معبد على مصطبة ضخمة، وقصر ملكي، وأقدم كلمات كُتبت بلغتهم.',
        ],
        'erbil-citadel' => [
            'A town on a mound made of its own past: houses built on houses for thousands of years, one of the oldest continuously inhabited places on earth.',
            'شارێک لەسەر گردێک کە لە ڕابردووی خۆی دروستبووە: خانوو لەسەر خانوو بە هەزاران ساڵ، یەکێک لە کۆنترین شوێنە بەردەوام ئاوەدانەکانی سەر زەوی.',
            'مدينة على تلّ صنعه ماضيها: بيوت فوق بيوت عبر آلاف السنين، من أقدم الأماكن المأهولة باستمرار على الأرض، ولا تزال قلب أربيل.',
        ],
        'tell-halaf' => [
            'The mound that named the Halaf culture of painted pottery, and later the Aramaean city of Guzana, whose palace was entered between gods standing on lions.',
            'ئەو گردەی ناوی دا بە کولتووری حەلەفی گۆزەی ڕەنگکراو، و دواتر شاری ئارامیی گوزانا، کە لە نێوان خوداوەندانی سەر شێردا دەچوویتە ناو کۆشکەکەی.',
            'التلّ الذي أعطى اسمه لثقافة حلف ذات الفخار الملوّن، ثم مدينة غوزانا الآرامية التي يُدخل إلى قصرها بين آلهة تقف على أسود.',
        ],
        'ain-dara-temple' => [
            'A Syro-Hittite temple near Afrin, guarded by carved lions and sphinxes, with giant footprints cut into its threshold. Badly damaged in 2018.',
            'پەرستگایەکی سووری-حیتی لە نزیک عەفرین، شێر و ئەبولهەولی هەڵکەنراو پاسەوانین و شوێنپێی زەبەلاح لە بەردەرگاکەیدایە. لە ٢٠١٨ بە سەختی زیانی پێگەیشت.',
            'معبد سوري-حثّي قرب عفرين تحرسه أسود وأبو الهول المنحوتة، وفي عتبته آثار أقدام عملاقة. تضرّر بشدة عام ٢٠١٨.',
        ],
        'jerwan-aqueduct' => [
            'One of the world\'s oldest aqueducts: a bridge of two million limestone blocks that carried a canal to Nineveh, four centuries before Rome built hers.',
            'یەکێک لە کۆنترین جۆگە ئاوییە پردئاساکانی جیهان: پردێک لە دوو ملیۆن بلۆکی بەردی کلسی کە جۆگەیەکی بۆ نەینەوا دەگواستەوە.',
            'من أقدم القنوات المائية المعلّقة في العالم: جسر من مليوني حجر جيري حمل قناة إلى نينوى، قبل أربعة قرون من قنوات روما.',
        ],
        'khinis-reliefs' => [
            'Where Sennacherib\'s great canal began: kings and gods carved into the cliffs of the Gomel gorge, including the largest Assyrian sculpture that exists.',
            'ئەو شوێنەی جۆگە گەورەکەی سەنحاریب لێیەوە دەستی پێدەکرد: پاشا و خوداوەندەکان لە زنارەکانی گەلیی گۆمەل هەڵکەنراون.',
            'حيث بدأت قناة سنحاريب الكبرى: ملوك وآلهة منحوتون في صخور مضيق الكومل، ومنهم أكبر منحوتة آشورية موجودة.',
        ],
        'bisotun' => [
            'A relief and inscription carved a hundred metres up a cliff near Kermanshah: the key that unlocked the cuneiform writing of the ancient Near East.',
            'نەخش و نووسینێک سەد مەتر بە بەرزی لەسەر زنارێک لە نزیک کرماشان: ئەو کلیلەی نووسینی بزماریی ڕۆژهەڵاتی نزیکی کۆنی کردەوە.',
            'نقش وكتابة منحوتان على ارتفاع مئة متر على جرف قرب كرمانشاه: المفتاح الذي فكّ رموز الخط المسماري للشرق الأدنى القديم.',
        ],
        'mount-nemrut' => [
            'A king\'s tomb on a mountain summit: an artificial peak of crushed stone flanked by colossal seated gods, their fallen heads facing sunrise and sunset.',
            'گۆڕی پاشایەک لەسەر لووتکەی چیایەک: لووتکەیەکی دەستکرد لە بەردی وردکراو، لە نێوان پەیکەری گەورەی خوداوەندەکاندا کە ڕوویان لە خۆرهەڵات و خۆرئاوایە.',
            'قبر ملك على قمة جبل: قمة اصطناعية من الحجارة المهشّمة تحفّ بها آلهة جالسة ضخمة، رؤوسها المتساقطة تواجه الشروق والغروب.',
        ],
        'taq-e-bostan' => [
            'Sasanian royal reliefs carved into arched grottoes beside a spring-fed pool in Kermanshah: an armoured knight, a king between two gods, the royal hunt.',
            'نەخشی شاهانەی ساسانی لە ئەشکەوتی کەوانەییدا لە تەنیشت حەوزێکی ئاوی کانی لە کرماشان: سوارێکی زرێپۆش، پاشایەک لە نێوان دوو خوداوەنددا، و ڕاوی شاهانە.',
            'نقوش ملكية ساسانية في كهوف مقوّسة بجانب بركة يغذّيها نبع في كرمانشاه: فارس مدرّع، وملك بين إلهين، والصيد الملكي.',
        ],
        'takht-e-soleyman' => [
            'A walled Sasanian sanctuary around a spring-fed lake on a hilltop: home of one of the three great fires of Zoroastrian Iran, later a Mongol palace.',
            'پیرۆزگایەکی ساسانیی شووراکراو لە دەوری دەریاچەیەکی ئاوی کانی لەسەر گردێک: شوێنی یەکێک لە سێ ئاگرە گەورەکەی ئێرانی زەردەشتی.',
            'حرم ساساني مسوّر حول بحيرة يغذّيها نبع على قمة تلّ: موطن إحدى النيران الثلاث الكبرى لإيران الزرادشتية، ثم قصر صيفي مغولي.',
        ],
        'amed-city-walls' => [
            'Nearly six kilometres of black basalt walls and 82 towers around old Amed (Diyarbakır), above the Tigris: the longest city walls in the region.',
            'نزیکەی شەش کیلۆمەتر شوورای بازاڵتی ڕەش و ٨٢ بورج لە دەوری شاری کۆنی ئامەد، بەسەر دیجلەدا: درێژترین شوورای شار لە ناوچەکەدا.',
            'نحو ستة كيلومترات من سور البازلت الأسود و٨٢ برجاً حول مدينة آمد (ديار بكر) القديمة فوق دجلة: أطول أسوار مدينة في المنطقة.',
        ],
        'lalish' => [
            'The holiest place of the Yazidis: a sanctuary of springs, courtyards and fluted conical spires in a quiet valley near Shekhan, in Duhok province.',
            'پیرۆزترین شوێنی ئێزدییەکان: پیرۆزگایەک لە کانی و حەوشە و گومبەزی قووچەکی لە دۆڵێکی ئارامدا لە نزیک شێخان.',
            'أقدس مكان لدى الإيزيديين: حرم من ينابيع وأفنية وقباب مخروطية مضلّعة في وادٍ هادئ قرب الشيخان.',
        ],
        'mudhafaria-minaret' => [
            'A brick minaret from Erbil\'s golden age under Gökböri: 36 metres of patterned brickwork, standing alone in a park since its mosque disappeared.',
            'منارەیەکی خشتی لە سەردەمی زێڕینی هەولێر لە ژێر دەسەڵاتی کۆکبۆریدا: ٣٦ مەتر خشتکاریی نەخشدار، کە بە تەنها لە پارکێکدا ڕاوەستاوە.',
            'منارة من الآجر من عصر أربيل الذهبي في عهد كوكبري: ستة وثلاثون متراً من الآجر المزخرف تقف وحدها في حديقة منذ اختفى جامعها.',
        ],
        'hasankeyf' => [
            'A medieval capital on cliffs above the Tigris, with cave dwellings, a great bridge and minarets. Most of it has been under a dam lake since 2020.',
            'پایتەختێکی سەدەکانی ناوەڕاست لەسەر زنارەکانی سەر دیجلە، بە ماڵی ئەشکەوتی و پردێکی گەورە و منارە. زۆربەی لە ٢٠٢٠ەوە لە ژێر ئاوی بەنداوێکدایە.',
            'عاصمة من العصور الوسطى على جروف فوق دجلة، بمساكن كهفية وجسر عظيم ومآذن. معظمها تحت بحيرة سدّ منذ ٢٠٢٠.',
        ],
        'mardin-old-town' => [
            'A city of honey-coloured stone stepping down a mountainside above the Mesopotamian plain, with madrasas, mosques, churches and monasteries side by side.',
            'شارێکی بەردی هەنگوینی ڕەنگ کە پلە بە پلە بەسەر دەشتی میزۆپۆتامیادا دادەبەزێت، مەدرەسە و مزگەوت و کڵێسا و دێر تێیدا شان بە شان ڕاوەستاون.',
            'مدينة من الحجر العسلي اللون تنحدر درجاتٍ على سفح جبل فوق سهل بلاد الرافدين، تتجاور فيها المدارس والجوامع والكنائس والأديرة.',
        ],
        'amedi' => [
            'A town on a flat-topped rock above the Great Zab valley, entered past Parthian reliefs: for five centuries the seat of the Kurdish emirate of Bahdinan.',
            'شارۆچکەیەکی تەواو لەسەر بەردێکی سەر-تەخت لە سەرووی دۆڵی زێی گەورە، پێنج سەدە تەختگای میرنشینی کوردیی بادینان.',
            'بلدة كاملة على صخرة مسطّحة القمة فوق وادي الزاب الكبير، كانت خمسة قرون مقرّ إمارة بهدينان الكردية.',
        ],
        'ishak-pasha-palace' => [
            'A palace of 366 rooms on a rock terrace below Mount Ararat, where Ottoman plan, Seljuk stone, Persian ornament and Armenian carving meet.',
            'کۆشکێکی ٣٦٦ ژووری لەسەر سەکۆیەکی بەردین لە ژێر چیای ئاگری، کە نەخشەی عوسمانی و بەردی سەلجووقی و ڕازاندنەوەی فارسی و هەڵکەندنی ئەرمەنی یەکتر دەبینن.',
            'قصر من ٣٦٦ غرفة على مصطبة صخرية تحت جبل أرارات، تلتقي فيه الخطة العثمانية والحجر السلجوقي والزخرفة الفارسية والنحت الأرمني.',
        ],
        'hawraman' => [
            'Stepped stone villages on the steep slopes of the Zagros, where one house\'s roof is the next one\'s yard. UNESCO World Heritage since 2021.',
            'گوندی بەردینی پلەپلە لەسەر لێژاییەکانی زاگرۆس، کە سەربانی ماڵێک حەوشەی ماڵی دواترە. لە ٢٠٢١ەوە میراتی جیهانیی یونسکۆیە.',
            'قرى حجرية مدرّجة على منحدرات زاغروس الشديدة، حيث سطح البيت فناءُ البيت الذي يليه — طريقة للعيش مع جبل سجّلتها اليونسكو عام ٢٠٢١.',
        ],
    ];
};
