<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kurdistan's architectural heritage (/kurdistan-heritage): the landmarks on
 * the map of Greater Kurdistan, each with a page of its own. Replaces the
 * Erbil walk (its table is left as it was, unused).
 *
 * Seeded with a first set of 21 landmarks across the four parts. Every pin
 * was checked to fall inside the outline drawn on the map; they are close,
 * not surveyed — drag one in the admin to correct it. Photographs are left
 * for the studio to add (its own, or with a credit).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landmarks', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->string('region', 20)->index();
            $table->string('era', 20)->index();
            $table->string('name_en');
            $table->string('name_ku')->nullable();
            $table->string('name_ar')->nullable();
            $table->string('place_en')->nullable();
            $table->string('place_ku')->nullable();
            $table->string('place_ar')->nullable();
            $table->string('period_en')->nullable();
            $table->string('period_ku')->nullable();
            $table->string('period_ar')->nullable();
            $table->text('summary_en')->nullable();
            $table->text('summary_ku')->nullable();
            $table->text('summary_ar')->nullable();
            $table->text('body_en')->nullable();
            $table->text('body_ku')->nullable();
            $table->text('body_ar')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('image')->nullable();
            $table->string('image_credit')->nullable();
            $table->boolean('unesco')->default(false);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        $now = now();
        foreach (self::LANDMARKS as $i => $l) {
            $row = ['slug' => $l['slug'], 'region' => $l['region'], 'era' => $l['era'], 'lat' => $l['at'][0], 'lng' => $l['at'][1],
                'unesco' => $l['unesco'] ?? false, 'sort_order' => $i + 1, 'is_published' => true, 'created_at' => $now, 'updated_at' => $now];
            foreach (['name', 'place', 'period', 'summary', 'body'] as $f) {
                [$row[$f.'_en'], $row[$f.'_ku'], $row[$f.'_ar']] = $l[$f];
            }
            DB::table('landmarks')->insert($row);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('landmarks');
    }

    /** Oldest first — the order of the list under the map. */
    private const LANDMARKS = [
        [
            'slug' => 'gobekli-tepe', 'region' => 'bakur', 'era' => 'prehistory', 'at' => [37.2232, 38.9224], 'unesco' => true,
            'name' => ['Göbekli Tepe (Girê Mirazan)', 'گرێ مرازان (گۆبەکلی تەپە)', 'غوبكلي تبه (كري مرازان)'],
            'place' => ['Near Riha (Şanlıurfa)', 'نزیک ڕیها (شانلیئورفا)', 'قرب الرها (شانلي أورفا)'],
            'period' => ['c. 9600–8200 BC', 'نزیکەی ٩٦٠٠–٨٢٠٠ پ.ز', 'نحو ٩٦٠٠–٨٢٠٠ ق.م'],
            'summary' => [
                'The oldest monumental architecture known on earth — stone circles raised some 11,000 years ago, before farming, pottery or writing.',
                'کۆنترین تەلارسازیی گەورەی ناسراو لە جیهاندا — بازنەی بەردین کە نزیکەی ١١ هەزار ساڵ لەمەوبەر دروستکران، پێش کشتوکاڵ و گۆزە و نووسین.',
                'أقدم عمارة ضخمة معروفة على وجه الأرض — دوائر حجرية شُيّدت قبل نحو ١١ ألف عام، قبل الزراعة والفخار والكتابة.',
            ],
            'body' => [
                "On a hilltop above the Harran plain, people who still lived by hunting and gathering carved T-shaped limestone pillars, some more than five metres tall, and set them in great rings. Foxes, boars, snakes and vultures are cut into the stone in relief; some pillars have arms and hands, as if they were figures.\n\nGöbekli Tepe is about six thousand years older than Stonehenge. Its discovery turned an old idea around: here, it seems, the gathering place came first, and settled life followed. It has been a UNESCO World Heritage Site since 2018.",
                "لەسەر گردێک بەسەر دەشتی حەڕاندا، خەڵکانێک کە هێشتا بە ڕاو و کۆکردنەوە دەژیان، ستوونی بەردی کلسی بە شێوەی T یان تاشی — هەندێکیان لە پێنج مەتر بەرزترن — و لە بازنەی گەورەدا دایانناون. ڕێوی، بەراز، مار و داڵ لەسەر بەردەکان هەڵکەنراون؛ هەندێک لە ستوونەکان دەست و قۆڵیان هەیە، وەک ئەوەی پەیکەر بن.\n\nگرێ مرازان نزیکەی شەش هەزار ساڵ لە ستۆنهێنج کۆنترە. دۆزینەوەی بیرۆکەیەکی کۆنی پێچەوانە کردەوە: لێرەدا وا دیارە شوێنی کۆبوونەوە یەکەم جار هات و ژیانی نیشتەجێبوون دوای ئەو. لە ساڵی ٢٠١٨ەوە لە لیستی میراتی جیهانیی یونسکۆدایە.",
                "على تلّ يطلّ على سهل حرّان، نحت أناس كانوا لا يزالون يعيشون على الصيد والالتقاط أعمدةً من الحجر الجيري على شكل حرف T، يزيد ارتفاع بعضها على خمسة أمتار، وأقاموها في حلقات كبيرة. نُحتت على الحجر ثعالب وخنازير برية وأفاعٍ ونسور، ولبعض الأعمدة أذرع وأيدٍ كأنها تماثيل.\n\nيسبق غوبكلي تبه ستونهنج بنحو ستة آلاف عام. وقد قلب اكتشافه فكرة قديمة: يبدو أن مكان التجمّع جاء هنا أولاً، ثم تبعته الحياة المستقرة. وهو على قائمة التراث العالمي لليونسكو منذ عام ٢٠١٨.",
            ],
        ],
        [
            'slug' => 'shanidar-cave', 'region' => 'bashur', 'era' => 'prehistory', 'at' => [36.833, 44.217],
            'name' => ['Shanidar Cave', 'ئەشکەوتی شانەدەر', 'كهف شانيدار'],
            'place' => ['Bradost mountain, Erbil province', 'چیای برادۆست، پارێزگای هەولێر', 'جبل برادوست، محافظة أربيل'],
            'period' => ['Tens of thousands of years', 'دەیان هەزار ساڵ', 'عشرات آلاف السنين'],
            'summary' => [
                'The first shelter: a great cave in the Zagros where Neanderthals lived and buried their dead, and where some of the earliest villagers later came.',
                'یەکەم پەناگە: ئەشکەوتێکی گەورە لە زاگرۆس کە نیاندەرتاڵەکان تێیدا ژیاون و مردووەکانیان ناشتووە، و دواتر هەندێک لە یەکەمین گوندنشینەکان هاتوونەتە ئەوێ.',
                'المأوى الأول: كهف كبير في زاغروس عاش فيه إنسان نياندرتال ودفن موتاه، ولجأ إليه لاحقاً بعض أوائل سكان القرى.',
            ],
            'body' => [
                "Architecture begins with shelter, and Shanidar is one of the most famous shelters in human history. Excavations from the 1950s found the remains of ten Neanderthals here — among them a man who had lived for years with serious injuries, which means others cared for him. A new skeleton was found in 2018.\n\nMuch later, around the end of the last Ice Age, people of the Zagros used the cave and the slope below it as they moved toward settled life. The cave sits high above the Great Zab valley, and can be visited.",
                "تەلارسازی بە پەناگە دەست پێدەکات، و شانەدەر یەکێکە لە بەناوبانگترین پەناگەکانی مێژووی مرۆڤ. هەڵکۆڵینەکانی سەرەتای پەنجاکان پاشماوەی دە نیاندەرتاڵیان لێرە دۆزییەوە — لەناویاندا پیاوێک کە ساڵانێک لەگەڵ برینی سەخت ژیاوە، واتە کەسانی تر چاودێرییان کردووە. ساڵی ٢٠١٨ ئێسکەپەیکەرێکی نوێ دۆزرایەوە.\n\nزۆر دواتر، نزیکەی کۆتایی دوایین سەردەمی سەهۆڵبەندان، خەڵکی زاگرۆس ئەشکەوت و لێژاییەکەی خوارەوەیان بەکارهێنا لە کاتی گواستنەوەیان بەرەو ژیانی نیشتەجێبوون. ئەشکەوتەکە بە بەرزی بەسەر دۆڵی زێی گەورەدا دەڕوانێت و دەکرێت سەردانی بکرێت.",
                "تبدأ العمارة بالمأوى، وشانيدار من أشهر المآوي في تاريخ البشر. عثرت التنقيبات منذ خمسينيات القرن الماضي على بقايا عشرة من إنسان نياندرتال — بينهم رجل عاش سنوات مع إصابات بالغة، ما يعني أن غيره اعتنى به. وعُثر على هيكل جديد عام ٢٠١٨.\n\nوبعد ذلك بزمن طويل، قرب نهاية العصر الجليدي الأخير، استخدم سكان زاغروس الكهف والمنحدر أسفله وهم ينتقلون نحو الحياة المستقرة. يقع الكهف عالياً فوق وادي الزاب الكبير، ويمكن زيارته.",
            ],
        ],
        [
            'slug' => 'tell-brak', 'region' => 'rojava', 'era' => 'mesopotamia', 'at' => [36.6672, 41.0583],
            'name' => ['Tell Brak (Nagar)', 'گردی براک (ناگار)', 'تل براك (ناغار)'],
            'place' => ['Near Hasakah, Jazira', 'نزیک حەسەکە، جەزیرە', 'قرب الحسكة، الجزيرة'],
            'period' => ['c. 6000–1300 BC', 'نزیکەی ٦٠٠٠–١٣٠٠ پ.ز', 'نحو ٦٠٠٠–١٣٠٠ ق.م'],
            'summary' => [
                'One of the first cities on earth: a mound forty metres high, home to the "Eye Temple" and a palace of the kings of Akkad.',
                'یەکێک لە یەکەمین شارەکانی جیهان: گردێکی چل مەتری، شوێنی «پەرستگای چاو» و کۆشکێکی پاشاکانی ئەکەد.',
                'من أوائل المدن على الأرض: تلّ بارتفاع أربعين متراً، يضمّ «معبد العيون» وقصراً لملوك أكّاد.',
            ],
            'body' => [
                "Nagar grew into a city in the fourth millennium BC — as early as the cities of southern Mesopotamia, which overturned the idea that urban life began only in the south. Its Eye Temple, built around 3500 BC, was decorated with stone cones and coloured panels, and held hundreds of small idols with large staring eyes.\n\nAround 2250 BC the Akkadian king Naram-Sin built a fortified palace here of mudbrick, with walls up to ten metres thick. The mound covers more than forty hectares.",
                "ناگار لە هەزارەی چوارەمی پێش زاییندا بوو بە شار — هێندەی شارەکانی باشووری میزۆپۆتامیا کۆن، ئەمەش ئەو بیرۆکەیەی پووچەڵ کردەوە کە ژیانی شارنشینی تەنها لە باشوور دەستی پێکرد. پەرستگای چاو، کە نزیکەی ٣٥٠٠ پ.ز دروستکراوە، بە قووچەکی بەردین و تابلۆی ڕەنگاوڕەنگ ڕازێنرابووەوە، و سەدان بتی بچووکی چاوزەقی تێدا بوو.\n\nنزیکەی ٢٢٥٠ پ.ز نەرام سینی پاشای ئەکەدی کۆشکێکی قەڵابەندی لە خشتی قوڕ لێرە دروستکرد، دیوارەکانی تا دە مەتر ئەستوور بوون. گردەکە زیاتر لە چل هێکتار دەگرێتەوە.",
                "نمت ناغار مدينةً في الألف الرابع قبل الميلاد — مبكراً مثل مدن جنوب بلاد الرافدين، ما قلب فكرة أن الحياة الحضرية بدأت في الجنوب وحده. زُيّن معبد العيون، الذي بُني نحو ٣٥٠٠ ق.م، بمخاريط حجرية وألواح ملوّنة، وضمّ مئات التماثيل الصغيرة ذات العيون الكبيرة المحدّقة.\n\nونحو ٢٢٥٠ ق.م بنى الملك الأكّادي نرام سين هنا قصراً محصّناً من اللبن، بجدران يصل سمكها إلى عشرة أمتار. ويغطي التلّ أكثر من أربعين هكتاراً.",
            ],
        ],
        [
            'slug' => 'urkesh', 'region' => 'rojava', 'era' => 'mesopotamia', 'at' => [37.057, 40.997],
            'name' => ['Urkesh (Tell Mozan)', 'ئورکێش (گردی مۆزان)', 'أوركيش (تل موزان)'],
            'place' => ['Near Amûdê, Jazira', 'نزیک عامودا، جەزیرە', 'قرب عامودا، الجزيرة'],
            'period' => ['c. 4000–1300 BC', 'نزیکەی ٤٠٠٠–١٣٠٠ پ.ز', 'نحو ٤٠٠٠–١٣٠٠ ق.م'],
            'summary' => [
                'Capital of the Hurrians, a mountain people of the third millennium BC, with a temple raised on a monumental terrace above the plain.',
                'پایتەختی حۆرییەکان، گەلێکی چیانشینی هەزارەی سێیەمی پێش زایین، بە پەرستگایەکەوە کە لەسەر سەکۆیەکی گەورە بەسەر دەشتدا بەرزکراوەتەوە.',
                'عاصمة الحوريين، شعب جبلي من الألف الثالث قبل الميلاد، بمعبد مرفوع على مصطبة ضخمة فوق السهل.',
            ],
            'body' => [
                "The Hurrians came from the highlands at the edge of Mesopotamia, and Urkesh was their holy city. Its temple stood on a great stepped terrace, with a monumental stair leading up to it — a building meant to be seen from far across the plain.\n\nThe royal palace of King Tupkish, around 2250 BC, and a deep stone-lined underground shaft used for rituals of the underworld were found here. The excavation, ongoing since 1984, is known for working with the people of the nearby villages to protect the site.",
                "حۆرییەکان لە بەرزاییەکانی لێواری میزۆپۆتامیاوە هاتبوون و ئورکێش شاری پیرۆزیان بوو. پەرستگاکەی لەسەر سەکۆیەکی پلەپلەی گەورە بوو، و پەیژەیەکی مەزن بەرەو سەرەوە دەچوو — بینایەک بۆ ئەوەی لە دوورەوە بە درێژایی دەشت ببینرێت.\n\nکۆشکی پاشا توپکیش، نزیکەی ٢٢٥٠ پ.ز، و چاڵێکی قووڵی ژێرزەوی کە بە بەرد داپۆشراوە و بۆ ڕێوڕەسمی جیهانی ژێرەوە بەکارهاتووە، لێرە دۆزرانەوە. هەڵکۆڵینەکە کە لە ١٩٨٤ەوە بەردەوامە، بە هاوکاری لەگەڵ خەڵکی گوندەکانی دەوروبەر بۆ پاراستنی شوێنەکە ناسراوە.",
                "جاء الحوريون من المرتفعات على حافة بلاد الرافدين، وكانت أوركيش مدينتهم المقدسة. قام معبدها على مصطبة متدرّجة كبيرة يصعد إليها درج ضخم — بناء أُريد له أن يُرى من بعيد عبر السهل.\n\nعُثر هنا على القصر الملكي للملك توبكيش، نحو ٢٢٥٠ ق.م، وعلى بئر عميقة تحت الأرض مبطّنة بالحجر استُخدمت في طقوس العالم السفلي. وتُعرف التنقيبات الجارية منذ عام ١٩٨٤ بعملها مع أهالي القرى المجاورة لحماية الموقع.",
            ],
        ],
        [
            'slug' => 'erbil-citadel', 'region' => 'bashur', 'era' => 'mesopotamia', 'at' => [36.1912, 44.0092], 'unesco' => true,
            'name' => ['Erbil Citadel', 'قەڵای هەولێر', 'قلعة أربيل'],
            'place' => ['Erbil', 'هەولێر', 'أربيل'],
            'period' => ['Inhabited since at least the 5th millennium BC', 'لانیکەم لە هەزارەی پێنجەمی پ.ز ەوە ئاوەدانە', 'مأهولة منذ الألف الخامس ق.م على الأقل'],
            'summary' => [
                'A city on a hill made of its own past — one of the oldest continuously inhabited places in the world, still the heart of Erbil.',
                'شارێک لەسەر گردێک کە لە ڕابردووی خۆی دروستبووە — یەکێک لە کۆنترین شوێنە بەردەوام ئاوەدانەکانی جیهان، و هێشتا دڵی هەولێرە.',
                'مدينة على تلّ صنعه ماضيها — من أقدم الأماكن المأهولة باستمرار في العالم، ولا تزال قلب أربيل.',
            ],
            'body' => [
                "The citadel mound rises around thirty metres above the city, built up layer by layer by thousands of years of houses rebuilt on the ruins of the ones before. In Assyrian times this was Arbela, famous for its temple of the goddess Ishtar.\n\nThe houses and alleys on top today are mostly from the late Ottoman era, wrapped by a continuous wall of house fronts that reads, from below, as a single fortress. The citadel became a UNESCO World Heritage Site in 2014 and is being restored house by house.",
                "گردی قەڵاکە نزیکەی سی مەتر بەسەر شاردا بەرز دەبێتەوە، چین لەسەر چین بە هەزاران ساڵ لە خانوو کە لەسەر وێرانەی خانووەکانی پێشوو دووبارە دروستکراونەتەوە، پێکهاتووە. لە سەردەمی ئاشوورییەکاندا ئەمە ئەربێلا بوو، بە پەرستگای خوداوەند ئیشتار بەناوبانگ بوو.\n\nخانوو و کۆڵانەکانی سەرەوەی ئەمڕۆ زۆربەیان هی کۆتایی سەردەمی عوسمانین، و دیوارێکی بەردەوام لە ڕووکاری خانووەکان دەوریان داوە کە لە خوارەوە وەک یەک قەڵا دەردەکەوێت. ساڵی ٢٠١٤ قەڵاکە بوو بە میراتی جیهانیی یونسکۆ و خانوو بە خانوو نۆژەن دەکرێتەوە.",
                "يرتفع تلّ القلعة نحو ثلاثين متراً فوق المدينة، وقد تكوّن طبقةً فوق طبقة عبر آلاف السنين من بيوت أُعيد بناؤها على أنقاض ما سبقها. وفي العصر الآشوري كانت هذه أربائيلو، الشهيرة بمعبد الإلهة عشتار.\n\nمعظم البيوت والأزقّة في أعلاها اليوم تعود إلى أواخر العهد العثماني، يلفّها سور متصل من واجهات البيوت يبدو من الأسفل حصناً واحداً. أُدرجت القلعة على قائمة التراث العالمي لليونسكو عام ٢٠١٤، ويجري ترميمها بيتاً بيتاً.",
            ],
        ],
        [
            'slug' => 'tell-halaf', 'region' => 'rojava', 'era' => 'mesopotamia', 'at' => [36.8267, 40.0394],
            'name' => ['Tell Halaf (Guzana)', 'گردی حەلەف (گوزانا)', 'تل حلف (غوزانا)'],
            'place' => ['Serê Kaniyê (Ras al-Ayn)', 'سەرێ کانیێ (ڕەئس عەین)', 'سري كانيه (رأس العين)'],
            'period' => ['c. 6000 BC; palace 10th–9th century BC', 'نزیکەی ٦٠٠٠ پ.ز؛ کۆشک سەدەی ١٠–٩ پ.ز', 'نحو ٦٠٠٠ ق.م؛ القصر القرن ١٠–٩ ق.م'],
            'summary' => [
                'The site that gave its name to the Halaf culture of painted pottery, and later the Aramaean city of Guzana with its palace of stone giants.',
                'ئەو شوێنەی ناوی خۆی دا بە کولتووری حەلەفی گۆزەی ڕەنگکراو، و دواتر شاری ئارامی گوزانا بە کۆشکی پەیکەرە بەردینە گەورەکانییەوە.',
                'الموقع الذي أعطى اسمه لثقافة حلف ذات الفخار الملوّن، ثم صار مدينة غوزانا الآرامية بقصرها ذي العمالقة الحجريين.',
            ],
            'body' => [
                "Around 6000 BC, farming villages across northern Mesopotamia shared a style of finely painted pottery and round houses, now called the Halaf culture after this mound at the head of the Khabur river.\n\nThousands of years later it was Guzana, an Aramaean capital. Its Western Palace, from the 10th–9th century BC, was entered between colossal basalt statues and lined with carved stone slabs. Many finds were taken to Berlin, shattered by bombing in the Second World War, and painstakingly pieced back together.",
                "نزیکەی ٦٠٠٠ پ.ز، گوندە کشتوکاڵییەکانی سەرانسەری باکووری میزۆپۆتامیا شێوازێکی هاوبەشی گۆزەی ناسکی ڕەنگکراو و خانووی بازنەیییان هەبوو، کە ئێستا بە ناوی ئەم گردەی سەرچاوەی ڕووباری خابوورەوە پێی دەوترێت کولتووری حەلەف.\n\nهەزاران ساڵ دواتر ئێرە گوزانا بوو، پایتەختێکی ئارامی. کۆشکی ڕۆژاوای، لە سەدەی ١٠–٩ی پ.ز، لە نێوان پەیکەری گەورەی بازاڵتدا دەچوویتە ژوورەوە و دیوارەکانی بە تابلۆی بەردی نەخشێنراو داپۆشرابوون. زۆر لە دۆزراوەکان بران بۆ بەرلین، لە بۆمبارانی جەنگی دووەمی جیهانیدا وردوخاش بوون، و بە ورەیەکی زۆرەوە دووبارە کۆکرانەوە.",
                "نحو ٦٠٠٠ ق.م، تقاسمت القرى الزراعية في شمال بلاد الرافدين أسلوباً من الفخار الملوّن الدقيق والبيوت المستديرة، يُعرف اليوم بثقافة حلف نسبةً إلى هذا التلّ عند منابع نهر الخابور.\n\nوبعد آلاف السنين صار غوزانا، عاصمة آرامية. كان يُدخل إلى قصرها الغربي، من القرنين العاشر والتاسع ق.م، بين تماثيل بازلتية ضخمة، وكانت جدرانه مكسوّة بألواح حجرية منحوتة. نُقل كثير من المكتشفات إلى برلين، وتحطّمت بالقصف في الحرب العالمية الثانية، ثم جُمعت قطعها من جديد بصبر كبير.",
            ],
        ],
        [
            'slug' => 'ain-dara-temple', 'region' => 'rojava', 'era' => 'mesopotamia', 'at' => [36.4586, 36.8519],
            'name' => ['Ain Dara Temple', 'پەرستگای عەین دارا', 'معبد عين دارة'],
            'place' => ['Near Efrîn (Afrin)', 'نزیک عەفرین', 'قرب عفرين'],
            'period' => ['c. 1300–740 BC', 'نزیکەی ١٣٠٠–٧٤٠ پ.ز', 'نحو ١٣٠٠–٧٤٠ ق.م'],
            'summary' => [
                'A Syro-Hittite temple guarded by carved lions, with giant footprints cut into its threshold, as if a god had walked in.',
                'پەرستگایەکی سووری-حیتی کە شێرە هەڵکەنراوەکان پاسەوانین، و شوێنپێی گەورە لە بەردەم دەرگاکەیدا هەڵکەنراون، وەک ئەوەی خوداوەندێک بەناویدا چووبێتە ژوورەوە.',
                'معبد سوري-حثّي تحرسه أسود منحوتة، وفي عتبته آثار أقدام عملاقة كأن إلهاً دخل منها.',
            ],
            'body' => [
                "The temple stood on a high platform faced with basalt reliefs of lions and sphinxes. Pressed into the stone of its entrance are enormous bare footprints — two side by side at the door, then a single one further in, as though the god had stepped inside.\n\nIts plan of porch, hall and inner sanctuary is close to the description of Solomon's temple in the Bible, which made it famous among archaeologists. The temple was badly damaged in 2018; what survives is a reminder of how fragile this heritage is.",
                "پەرستگاکە لەسەر سەکۆیەکی بەرز بوو کە بە نەخشی بازاڵتی شێر و ئەبولهەول داپۆشرابوو. لە بەردی دەروازەکەیدا شوێنپێی ڕووتی زۆر گەورە هەڵکەنراون — دوو دانە لە تەنیشت یەک لە بەردەم دەرگا، پاشان یەکێکی تر لە ناوەوەتر، وەک ئەوەی خوداوەندەکە هەنگاوی نابێتە ژوورەوە.\n\nنەخشەکەی، کە لە هەیوان و هۆڵ و پیرۆزگای ناوەوە پێکهاتووە، نزیکە لە وەسفی پەرستگای سلێمان لە ئینجیلدا، ئەمەش لە نێو شوێنەوارناساندا بەناوبانگی کرد. ساڵی ٢٠١٨ پەرستگاکە بە سەختی زیانی پێگەیشت؛ ئەوەی ماوەتەوە بیرهێنانەوەیە کە ئەم میراتە چەندە ناسکە.",
                "قام المعبد على منصة عالية مكسوّة بنقوش بازلتية لأسود وأبي الهول. وفي حجر مدخله آثار أقدام حافية هائلة — اثنتان متجاورتان عند الباب، ثم واحدة أبعد في الداخل، كأن الإله خطا إلى الداخل.\n\nيقترب مخططه، من رواق وقاعة وحَرَم داخلي، من وصف هيكل سليمان في الكتاب المقدس، وهذا ما جعله مشهوراً بين علماء الآثار. تضرّر المعبد بشدة عام ٢٠١٨، وما بقي منه تذكير بمدى هشاشة هذا التراث.",
            ],
        ],
        [
            'slug' => 'jerwan-aqueduct', 'region' => 'bashur', 'era' => 'mesopotamia', 'at' => [36.667, 43.40],
            'name' => ['Jerwan Aqueduct', 'جۆگەی ئاوی جەروان', 'قناة جروانة'],
            'place' => ['Near Shekhan, Duhok province', 'نزیک شێخان، پارێزگای دهۆک', 'قرب الشيخان، محافظة دهوك'],
            'period' => ['c. 690 BC', 'نزیکەی ٦٩٠ پ.ز', 'نحو ٦٩٠ ق.م'],
            'summary' => [
                'One of the oldest aqueducts in the world — a stone bridge for water, built by the Assyrian king Sennacherib to carry mountain streams to Nineveh.',
                'یەکێک لە کۆنترین جۆگە ئاوییە پردئاساکانی جیهان — پردێکی بەردین بۆ ئاو، سەنحاریبی پاشای ئاشووری دروستی کرد بۆ گەیاندنی ئاوی چیا بۆ نەینەوا.',
                'من أقدم القنوات المائية المعلّقة في العالم — جسر حجري للماء بناه الملك الآشوري سنحاريب لنقل مياه الجبال إلى نينوى.',
            ],
            'body' => [
                "Sennacherib's engineers cut a canal through the hills from the Zagros foothills toward his capital. Where it crossed a valley at Jerwan they raised an aqueduct about 280 metres long, made of an estimated two million cut limestone blocks, with pointed arches for the stream below.\n\nInscriptions on the stones name the king and the work. Nearly four centuries before the Romans began building theirs, this is engineering as architecture: water carried on a bridge across the landscape.",
                "ئەندازیارەکانی سەنحاریب جۆگەیەکیان بە ناو گردەکاندا لە داوێنی زاگرۆسەوە بەرەو پایتەختەکەی لێدا. لەو شوێنەی لە جەروان دۆڵێکی دەبڕی، جۆگەیەکی پردئاسای نزیکەی ٢٨٠ مەتر درێژیان بەرزکردەوە، کە بە نزیکەی دوو ملیۆن بلۆکی تاشراوی بەردی کلسی دروستکراوە، بە کەوانەی نووکتیژ بۆ ئاوی جۆگەکەی خوارەوە.\n\nنووسینەکانی سەر بەردەکان ناوی پاشا و کارەکە دەهێنن. نزیکەی چوار سەدە پێش ئەوەی ڕۆمانییەکان دەست بە دروستکردنی هی خۆیان بکەن، ئەمە ئەندازیارییە وەک تەلارسازی: ئاو لەسەر پردێک بە ناو سروشتدا دەگوازرێتەوە.",
                "شقّ مهندسو سنحاريب قناة عبر التلال من سفوح زاغروس نحو عاصمته. وحيث عبرت وادياً في جروانة أقاموا قناة معلّقة طولها نحو ٢٨٠ متراً، من نحو مليوني حجر جيري منحوت، بأقواس مدبّبة للجدول في الأسفل.\n\nتذكر النقوش على الحجارة اسم الملك والعمل. وقبل نحو أربعة قرون من بدء الرومان ببناء قنواتهم، هذه هندسة بوصفها عمارة: ماء محمول على جسر عبر الطبيعة.",
            ],
        ],
        [
            'slug' => 'khinis-reliefs', 'region' => 'bashur', 'era' => 'mesopotamia', 'at' => [36.789, 43.366],
            'name' => ['Khinis Reliefs (Bavian)', 'نەخشەکانی خەنس (باڤیان)', 'منحوتات خنس (بافيان)'],
            'place' => ['Near Atrush, Duhok province', 'نزیک ئەتروش، پارێزگای دهۆک', 'قرب أتروش، محافظة دهوك'],
            'period' => ['c. 690 BC', 'نزیکەی ٦٩٠ پ.ز', 'نحو ٦٩٠ ق.م'],
            'summary' => [
                'Where Sennacherib\'s great canal began: kings and gods carved into the cliffs above a mountain gorge.',
                'ئەو شوێنەی جۆگە گەورەکەی سەنحاریب لێیەوە دەستی پێدەکرد: پاشا و خوداوەندەکان لە زنارەکانی سەر گەلییەکی چیاییدا هەڵکەنراون.',
                'حيث بدأت قناة سنحاريب الكبرى: ملوك وآلهة منحوتون في الصخور فوق مضيق جبلي.',
            ],
            'body' => [
                "At the mouth of the Gomel gorge, where water was diverted into the canal that fed Nineveh and crossed the Jerwan aqueduct, the Assyrians carved the rock face itself. The largest panel shows the king before the gods; smaller niches with the royal figure line the cliff, and a huge carved block lies broken in the stream.\n\nIt is architecture at the scale of the landscape: a mountain marked as the source of a city's life.",
                "لە دەمی گەلیی گۆمەل، لەو شوێنەی ئاو دەگۆڕدرا بۆ ناو ئەو جۆگەیەی ئاوی نەینەوای دەدا و بەسەر جۆگەی جەرواندا تێدەپەڕی، ئاشوورییەکان ڕووی زنارەکەیان خۆی هەڵکەند. گەورەترین تابلۆ پاشا لە بەردەم خوداوەندەکاندا نیشان دەدات؛ تاقی بچووکتر بە وێنەی پاشاوە بە درێژایی زنارەکە ڕیزکراون، و بلۆکێکی گەورەی هەڵکەنراو شکاو لەناو ئاوەکەدا کەوتووە.\n\nئەمە تەلارسازییە بە قەبارەی سروشت: چیایەک وەک سەرچاوەی ژیانی شارێک نیشانە کراوە.",
                "عند مدخل مضيق الكومل، حيث حُوّل الماء إلى القناة التي غذّت نينوى وعبرت قناة جروانة، نحت الآشوريون وجه الصخر نفسه. تُظهر أكبر لوحة الملك أمام الآلهة، وتصطفّ على الجرف كوى أصغر فيها صورة الملك، وتقبع كتلة منحوتة ضخمة مكسورة في مجرى الماء.\n\nإنها عمارة بحجم الطبيعة: جبل وُسم بوصفه منبع حياة مدينة.",
            ],
        ],
        [
            'slug' => 'bisotun', 'region' => 'rojhelat', 'era' => 'empires', 'at' => [34.3897, 47.4361], 'unesco' => true,
            'name' => ['Bisotun Inscription', 'بەردەنووسی بێستوون', 'نقش بيستون'],
            'place' => ['Near Kirmaşan (Kermanshah)', 'نزیک کرماشان', 'قرب كرمانشاه'],
            'period' => ['c. 520 BC', 'نزیکەی ٥٢٠ پ.ز', 'نحو ٥٢٠ ق.م'],
            'summary' => [
                'A relief and inscription carved high on a cliff on the ancient road from Mesopotamia to Iran — the key that unlocked cuneiform.',
                'نەخش و نووسینێک بە بەرزی لەسەر زنارێک لە ڕێگا کۆنەکەی نێوان میزۆپۆتامیا و ئێران هەڵکەنراوە — ئەو کلیلەی نووسینی بزماریی کردەوە.',
                'نقش وكتابة منحوتان عالياً على جرف عند الطريق القديم من بلاد الرافدين إلى إيران — المفتاح الذي فكّ رموز الخط المسماري.',
            ],
            'body' => [
                "Around 520 BC the Persian king Darius I had his victory carved into Mount Bisotun, some sixty metres above the road, in three languages: Old Persian, Elamite and Babylonian. In the nineteenth century scholars copied it at great risk from the cliff face and used it, like the Rosetta Stone, to read the cuneiform script of ancient Mesopotamia.\n\nAround the mountain lie remains from prehistory to the Sasanians and later. Bisotun has been a UNESCO World Heritage Site since 2006.",
                "نزیکەی ٥٢٠ پ.ز داریوشی یەکەمی پاشای فارس سەرکەوتنەکەی لە چیای بێستووندا، نزیکەی شەست مەتر بەسەر ڕێگاکەوە، بە سێ زمان هەڵکەند: فارسیی کۆن، ئیلامی و بابلی. لە سەدەی نۆزدەدا زانایان بە مەترسییەکی زۆرەوە لە ڕووی زنارەکەوە لەبەریان گرتەوە و، وەک بەردی ڕەشید، بۆ خوێندنەوەی نووسینی بزماریی میزۆپۆتامیای کۆن بەکاریان هێنا.\n\nلە دەوری چیاکە پاشماوەی سەردەمانی پێش مێژوو تا ساسانییەکان و دواتر هەن. بێستوون لە ساڵی ٢٠٠٦ەوە میراتی جیهانیی یونسکۆیە.",
                "نحو ٥٢٠ ق.م أمر الملك الفارسي داريوس الأول بنحت انتصاره في جبل بيستون، على ارتفاع نحو ستين متراً فوق الطريق، بثلاث لغات: الفارسية القديمة والعيلامية والبابلية. وفي القرن التاسع عشر نسخه العلماء من وجه الجرف معرّضين أنفسهم لخطر كبير، واستخدموه، كحجر رشيد، لقراءة الخط المسماري لبلاد الرافدين القديمة.\n\nوحول الجبل بقايا من عصور ما قبل التاريخ حتى الساسانيين وما بعدهم. وبيستون على قائمة التراث العالمي لليونسكو منذ عام ٢٠٠٦.",
            ],
        ],
        [
            'slug' => 'mount-nemrut', 'region' => 'bakur', 'era' => 'empires', 'at' => [37.9806, 38.7408], 'unesco' => true,
            'name' => ['Mount Nemrut', 'چیای نەمرود', 'جبل نمرود'],
            'place' => ['Near Semsûr (Adıyaman)', 'نزیک سەمسوور (ئادیامان)', 'قرب سمسور (أديامان)'],
            'period' => ['1st century BC', 'سەدەی یەکەمی پ.ز', 'القرن الأول ق.م'],
            'summary' => [
                'A king\'s tomb on a mountain summit: an artificial peak of stone flanked by colossal seated gods, their fallen heads facing sunrise and sunset.',
                'گۆڕی پاشایەک لەسەر لووتکەی چیایەک: لووتکەیەکی دەستکردی بەرد کە پەیکەرە دانیشتووە گەورەکانی خوداوەندەکان لە دوو لای ڕاوەستاون، سەرە کەوتووەکانیان ڕوویان لە خۆرهەڵات و خۆرئاوایە.',
                'قبر ملك على قمة جبل: قمة اصطناعية من الحجارة تحيط بها آلهة جالسة ضخمة، رؤوسها المتساقطة تواجه الشروق والغروب.',
            ],
            'body' => [
                "Antiochus I, king of Commagene, built his tomb-sanctuary at more than 2,100 metres: a cone of loose stone some fifty metres high, with terraces to the east and west lined with statues eight to nine metres tall of gods, lions and eagles — and the king himself among them.\n\nThe statues mix Greek and Persian features, as Commagene did. Earthquakes toppled the heads, which now stand on the ground in rows. Visitors climb to see the sun rise over them. It has been a UNESCO World Heritage Site since 1987.",
                "ئەنتیۆخۆسی یەکەم، پاشای کۆماژین، گۆڕ-پیرۆزگاکەی لە بەرزایی زیاتر لە ٢١٠٠ مەتر دروستکرد: قووچەکێک لە بەردی شل نزیکەی پەنجا مەتر بەرز، لەگەڵ سەکۆی ڕۆژهەڵات و ڕۆژاوا کە پەیکەری هەشت بۆ نۆ مەتری خوداوەندەکان و شێر و هەڵۆی لەسەر ڕیزکراوە — و پاشا خۆی لەناویاندا.\n\nپەیکەرەکان تایبەتمەندیی یۆنانی و فارسی تێکەڵ دەکەن، هەروەک کۆماژین خۆی. بوومەلەرزە سەرەکانی خستە خوارەوە، کە ئێستا بە ڕیز لەسەر زەوی ڕاوەستاون. سەردانکەران سەردەکەون بۆ بینینی خۆرهەڵاتن بەسەریاندا. لە ساڵی ١٩٨٧ەوە میراتی جیهانیی یونسکۆیە.",
                "بنى أنطيوخوس الأول، ملك كوماجيني، ضريحه المقدس على ارتفاع يزيد على ٢١٠٠ متر: مخروط من الحجارة المفككة بارتفاع نحو خمسين متراً، تحفّ به مصطبتان شرقية وغربية تصطفّ عليهما تماثيل بارتفاع ثمانية إلى تسعة أمتار لآلهة وأسود ونسور — والملك نفسه بينها.\n\nتمزج التماثيل بين الملامح الإغريقية والفارسية، كما كانت كوماجيني. أسقطت الزلازل الرؤوس، فهي اليوم قائمة على الأرض صفوفاً. ويصعد الزوار ليروا الشمس تشرق فوقها. وهو على قائمة التراث العالمي لليونسكو منذ عام ١٩٨٧.",
            ],
        ],
        [
            'slug' => 'taq-e-bostan', 'region' => 'rojhelat', 'era' => 'empires', 'at' => [34.3875, 47.1322],
            'name' => ['Taq-e Bostan', 'تاقی وەسان (تاق بوستان)', 'طاق بستان'],
            'place' => ['Kirmaşan (Kermanshah)', 'کرماشان', 'كرمانشاه'],
            'period' => ['4th–7th century AD', 'سەدەی ٤–٧ی زایینی', 'القرن ٤–٧ م'],
            'summary' => [
                'Sasanian royal reliefs carved into arched grottoes beside a spring-fed pool, at the foot of a mountain.',
                'نەخشی شاهانەی ساسانی کە لە ئەشکەوتی کەوانەییدا هەڵکەنراون، لە تەنیشت حەوزێکی ئاوی کانی، لە داوێنی چیایەک.',
                'نقوش ملكية ساسانية منحوتة في كهوف مقوّسة بجانب بركة يغذّيها نبع، عند سفح جبل.',
            ],
            'body' => [
                "The Sasanian kings chose this spot, where a spring rises from the foot of the mountain, for a royal garden and for reliefs cut into the rock. The largest grotto is a great vaulted iwan: on its back wall a king in armour sits on horseback, and its side walls show royal hunts of deer and boar in marshes, carved in remarkable detail.\n\nThe arch of the iwan, framed by winged figures, is one of the finest surviving pieces of Sasanian architecture and carving.",
                "پاشا ساسانییەکان ئەم شوێنەیان هەڵبژارد، کە کانییەک لە داوێنی چیاکەوە هەڵدەقوڵێت، بۆ باخچەیەکی شاهانە و بۆ نەخشی هەڵکەنراو لە بەرددا. گەورەترین ئەشکەوت ئەیوانێکی گەورەی گومبەزییە: لەسەر دیواری پشتەوەی پاشایەکی زرێپۆش سواری ئەسپە، و دیوارەکانی لاتەنیشت ڕاوی شاهانەی ئاسک و بەراز لە زۆنگاوەکاندا بە وردەکارییەکی سەرسوڕهێنەر نیشان دەدەن.\n\nکەوانەی ئەیوانەکە، کە بە پەیکەری باڵدار چوارچێوە دراوە، یەکێکە لە جوانترین بەرهەمە ماوەکانی تەلارسازی و هەڵکەندنی ساسانی.",
                "اختار الملوك الساسانيون هذا الموضع، حيث ينبع الماء من سفح الجبل، لحديقة ملكية ولنقوش منحوتة في الصخر. أكبر الكهوف إيوان مقبّب كبير: على جداره الخلفي ملك مدرّع على صهوة جواده، وتُظهر جدرانه الجانبية مشاهد صيد ملكي للأيائل والخنازير البرية في الأهوار، منحوتة بدقة لافتة.\n\nوقوس الإيوان، الذي تؤطّره شخوص مجنّحة، من أجمل ما بقي من العمارة والنحت الساسانيين.",
            ],
        ],
        [
            'slug' => 'takht-e-soleyman', 'region' => 'rojhelat', 'era' => 'empires', 'at' => [36.6036, 47.2356], 'unesco' => true,
            'name' => ['Takht-e Soleyman', 'تەختی سلێمان', 'تخت سليمان'],
            'place' => ['Near Takab', 'نزیک تەکاب', 'قرب تكاب'],
            'period' => ['5th–7th century AD; rebuilt 13th century', 'سەدەی ٥–٧ی زایینی؛ سەدەی ١٣ نۆژەنکرایەوە', 'القرن ٥–٧ م؛ أُعيد بناؤه في القرن ١٣'],
            'summary' => [
                'A walled Sasanian sanctuary around a deep, spring-fed lake on a hilltop — home of one of the three great fire temples of the empire.',
                'پیرۆزگایەکی ساسانیی شووراکراو لە دەوری دەریاچەیەکی قووڵی ئاوی کانی لەسەر گردێک — شوێنی یەکێک لە سێ ئاگرگا گەورەکەی ئیمپراتۆرییەتەکە.',
                'حرم ساساني مسوّر حول بحيرة عميقة يغذّيها نبع على قمة تلّ — موطن أحد بيوت النار الثلاثة الكبرى في الإمبراطورية.',
            ],
            'body' => [
                "The Sasanians built a fortified oval wall with thirty-eight towers around a small, very deep lake fed by a spring, and inside it the fire temple of Adur Gushnasp, where kings came on pilgrimage before taking the throne. Water and fire — the lake and the temple — were set side by side by design.\n\nIn the 13th century the Ilkhanid Mongols built a summer palace on the ruins, decorated with glazed tiles. The site has been a UNESCO World Heritage Site since 2003.",
                "ساسانییەکان شوورایەکی هێلکەیی قەڵابەندیان بە سی و هەشت بورجەوە لە دەوری دەریاچەیەکی بچووک و زۆر قووڵ کە کانییەک ئاوی دەدات دروستکرد، و لە ناوەوەیدا ئاگرگای ئادور گوشنەسپ، کە پاشاکان پێش دانیشتن لەسەر تەخت بۆ زیارەت دەهاتنە ئەوێ. ئاو و ئاگر — دەریاچە و پەرستگا — بە مەبەست لە تەنیشت یەکتر دانرابوون.\n\nلە سەدەی سێزدەدا مەغۆلە ئیلخانییەکان کۆشکێکی هاوینەیان لەسەر وێرانەکان دروستکرد، کە بە کاشیی بریقەدار ڕازێنرابووەوە. شوێنەکە لە ساڵی ٢٠٠٣ەوە میراتی جیهانیی یونسکۆیە.",
                "بنى الساسانيون سوراً بيضوياً محصّناً بثمانية وثلاثين برجاً حول بحيرة صغيرة عميقة جداً يغذّيها نبع، وفي داخله بيت نار آذر گشنسب، الذي كان الملوك يحجّون إليه قبل اعتلاء العرش. وُضع الماء والنار — البحيرة والمعبد — جنباً إلى جنب عن قصد.\n\nوفي القرن الثالث عشر بنى المغول الإيلخانيون قصراً صيفياً على الأطلال، زُيّن بالقرميد المزجّج. والموقع على قائمة التراث العالمي لليونسكو منذ عام ٢٠٠٣.",
            ],
        ],
        [
            'slug' => 'amed-city-walls', 'region' => 'bakur', 'era' => 'empires', 'at' => [37.9105, 40.2363], 'unesco' => true,
            'name' => ['City Walls of Amed (Diyarbakır)', 'شووراکانی ئامەد (دیاربەکر)', 'أسوار آمد (ديار بكر)'],
            'place' => ['Amed (Diyarbakır)', 'ئامەد (دیاربەکر)', 'آمد (ديار بكر)'],
            'period' => ['4th century AD, rebuilt through the Middle Ages', 'سەدەی ٤ی زایینی، بە درێژایی سەدەکانی ناوەڕاست نۆژەنکراوەتەوە', 'القرن ٤ م، أُعيد بناؤها عبر العصور الوسطى'],
            'summary' => [
                'Nearly six kilometres of black basalt walls and towers around the old city, above the gardens of the Tigris.',
                'نزیکەی شەش کیلۆمەتر شوورا و بورجی بازاڵتی ڕەش لە دەوری شاری کۆن، بەسەر باخچەکانی دیجلەدا.',
                'نحو ستة كيلومترات من الأسوار والأبراج البازلتية السوداء حول المدينة القديمة، فوق بساتين دجلة.',
            ],
            'body' => [
                "The walls of Amed were built in their present line under the Roman emperor Constantius II around 349 AD, and rebuilt and extended by every power that held the city after. Their dark basalt, more than eighty towers and gates carry inscriptions and carvings of the Marwanid Kurdish dynasty, the Artuqids and others — a history of the city written in stone.\n\nBelow the walls, the Hevsel Gardens run down to the Tigris and have fed the city for centuries. Walls and gardens became a UNESCO World Heritage Site together in 2015.",
                "شووراکانی ئامەد لە هێڵە ئێستاکەیاندا لە سەردەمی ئیمپراتۆری ڕۆمانی کۆنستانتیوسی دووەم نزیکەی ساڵی ٣٤٩ی زایینی دروستکران، و هەموو ئەو دەسەڵاتانەی دواتر شارەکەیان گرت نۆژەن و درێژیان کردنەوە. بازاڵتی تاریکیان، زیاتر لە هەشتا بورج و دەروازەکانیان نووسین و نەخشی خانەدانی کوردیی مەڕوانی، ئەرتوقییەکان و ئەوانی تریان لەسەرە — مێژووی شارەکە بە بەرد نووسراوە.\n\nلە خوار شووراکانەوە باخچەکانی هەڤسێل تا دیجلە درێژ دەبنەوە و بە سەدان ساڵ خۆراکی شارەکەیان داوە. شووراکان و باخچەکان پێکەوە ساڵی ٢٠١٥ بوون بە میراتی جیهانیی یونسکۆ.",
                "بُنيت أسوار آمد على خطّها الحالي في عهد الإمبراطور الروماني قسطنطيوس الثاني نحو عام ٣٤٩ م، وأعاد بناءها ووسّعها كل من حكم المدينة بعده. وتحمل حجارتها البازلتية الداكنة وأبراجها التي تزيد على الثمانين وبواباتها نقوشاً وزخارف للدولة المروانية الكردية والأرتقيين وغيرهم — تاريخ المدينة مكتوباً بالحجر.\n\nوتحت الأسوار تمتدّ بساتين هفسل حتى دجلة، وقد أطعمت المدينة قروناً. وأُدرجت الأسوار والبساتين معاً على قائمة التراث العالمي لليونسكو عام ٢٠١٥.",
            ],
        ],
        [
            'slug' => 'lalish', 'region' => 'bashur', 'era' => 'medieval', 'at' => [36.767, 43.30],
            'name' => ['Lalish', 'لالش', 'لالش'],
            'place' => ['Shekhan, Duhok province', 'شێخان، پارێزگای دهۆک', 'الشيخان، محافظة دهوك'],
            'period' => ['12th century AD and earlier', 'سەدەی ١٢ی زایینی و پێشتر', 'القرن ١٢ م وما قبله'],
            'summary' => [
                'The holiest place of the Yazidis — a sanctuary in a quiet valley, known for its fluted conical spires.',
                'پیرۆزترین شوێنی ئێزدییەکان — پیرۆزگایەک لە دۆڵێکی ئارامدا، بە گومبەزە قووچەکییە شیاوییەکانی ناسراوە.',
                'أقدس مكان لدى الإيزيديين — حرم في وادٍ هادئ، يُعرف بقبابه المخروطية المضلّعة.',
            ],
            'body' => [
                "Lalish holds the tomb of Sheikh Adi ibn Musafir, who died here in the 12th century, and is the centre of the Yazidi faith. Its shrines are crowned by tall conical spires with deep vertical ribs, which catch the light and make the sanctuary recognisable from across the valley.\n\nPilgrims walk barefoot inside the valley as a sign of respect, and the stone courtyards, springs and small buildings form a whole that is both architecture and landscape. It is a living holy place, not a ruin.",
                "لالش گۆڕی شێخ عەدی کوڕی موسافیری تێدایە، کە لە سەدەی دوازدەدا لێرە کۆچی دوایی کرد، و ناوەندی ئایینی ئێزدییە. پیرۆزگاکانی گومبەزی قووچەکیی بەرز بە هێڵی ستوونیی قووڵەوە لەسەرە، کە ڕووناکی دەگرن و پیرۆزگاکە لە سەرانسەری دۆڵەکەوە ناسراو دەکەن.\n\nزیارەتکەران وەک ڕێزلێنان بە پێی پەتی لە ناو دۆڵەکەدا دەڕۆن، و حەوشە بەردینەکان، کانییەکان و بینا بچووکەکان یەکەیەک پێکدەهێنن کە هەم تەلارسازییە و هەم سروشت. شوێنێکی پیرۆزی زیندووە، نەک وێرانە.",
                "تضمّ لالش ضريح الشيخ عدي بن مسافر، الذي توفي هنا في القرن الثاني عشر، وهي مركز الديانة الإيزيدية. تعلو مزاراتها قباب مخروطية عالية بأضلاع عمودية عميقة، تلتقط الضوء وتجعل الحرم معروفاً من أطراف الوادي.\n\nيسير الزوار حفاة داخل الوادي احتراماً، وتشكّل الأفنية الحجرية والينابيع والمباني الصغيرة كلاً واحداً هو عمارة وطبيعة معاً. إنه مكان مقدس حيّ، لا أطلال.",
            ],
        ],
        [
            'slug' => 'mudhafaria-minaret', 'region' => 'bashur', 'era' => 'medieval', 'at' => [36.1873, 43.9990],
            'name' => ['Mudhafaria Minaret (Choli Minaret)', 'منارەی موزەفەرییە (منارەی چۆلی)', 'منارة المظفرية (منارة الچولي)'],
            'place' => ['Minaret Park, Erbil', 'پارکی منارە، هەولێر', 'حديقة المنارة، أربيل'],
            'period' => ['Late 12th – early 13th century', 'کۆتایی سەدەی ١٢ – سەرەتای سەدەی ١٣', 'أواخر القرن ١٢ – أوائل القرن ١٣'],
            'summary' => [
                'A brick minaret from Erbil\'s golden age under Muzaffar al-Din Gökböri, standing alone in a park since its mosque disappeared.',
                'منارەیەکی خشتی لە سەردەمی زێڕینی هەولێر لە ژێر دەسەڵاتی موزەفەرەدین کۆکبۆریدا، کە لەو کاتەوەی مزگەوتەکەی نەماوە بە تەنها لە پارکێکدا ڕاوەستاوە.',
                'منارة من الآجر من عصر أربيل الذهبي في عهد مظفر الدين كوكبري، تقف وحدها في حديقة منذ اختفى جامعها.',
            ],
            'body' => [
                "Under Muzaffar al-Din Gökböri, who ruled Erbil from 1190 to 1232, the city flourished with schools, hospitals and mosques. This minaret is what remains of one of them. An octagonal base, decorated with panels of patterned brickwork and traces of glazed tile, carries a tall cylindrical shaft with a double staircase inside.\n\nIt is around 36 metres high and leans slightly. For centuries it was a landmark outside the old city, and it is one of the finest examples of decorative brick architecture in the region.",
                "لە ژێر دەسەڵاتی موزەفەرەدین کۆکبۆریدا، کە لە ١١٩٠ تا ١٢٣٢ فەرمانڕەوای هەولێر بوو، شارەکە بە قوتابخانە و نەخۆشخانە و مزگەوت گەشەی کرد. ئەم منارەیە ئەوەیە کە لە یەکێکیان ماوەتەوە. بنکەیەکی هەشتپاڵوو، کە بە تابلۆی خشتی نەخشدار و شوێنەواری کاشیی بریقەدار ڕازێنراوەتەوە، لاشەیەکی بەرزی لوولەیی هەڵگرتووە کە پەیژەیەکی دووانەی تێدایە.\n\nنزیکەی ٣٦ مەتر بەرزە و کەمێک لار بووەتەوە. بە سەدان ساڵ نیشانەیەک بووە لە دەرەوەی شاری کۆن، و یەکێکە لە باشترین نموونەکانی تەلارسازیی خشتی ڕازاوە لە ناوچەکەدا.",
                "في عهد مظفر الدين كوكبري، الذي حكم أربيل من ١١٩٠ إلى ١٢٣٢، ازدهرت المدينة بالمدارس والمستشفيات والجوامع. وهذه المنارة هي ما بقي من أحدها. قاعدة مثمّنة، مزيّنة بألواح من الآجر المزخرف وآثار من القرميد المزجّج، تحمل بدناً أسطوانياً عالياً بداخله درج مزدوج.\n\nيبلغ ارتفاعها نحو ٣٦ متراً وتميل قليلاً. كانت لقرون معلماً خارج المدينة القديمة، وهي من أجمل أمثلة عمارة الآجر الزخرفية في المنطقة.",
            ],
        ],
        [
            'slug' => 'hasankeyf', 'region' => 'bakur', 'era' => 'medieval', 'at' => [37.713, 41.414],
            'name' => ['Hasankeyf (Heskîf)', 'حەسەنکێف (هەسکیف)', 'حصن كيفا (حسنكيف)'],
            'place' => ['On the Tigris, Êlih (Batman) province', 'لەسەر دیجلە، پارێزگای ئێلح (باتمان)', 'على دجلة، محافظة باطمان'],
            'period' => ['12th–15th century, on a far older site', 'سەدەی ١٢–١٥، لەسەر شوێنێکی زۆر کۆنتر', 'القرن ١٢–١٥، على موقع أقدم بكثير'],
            'summary' => [
                'A medieval capital on cliffs above the Tigris, with cave dwellings, a great bridge and minarets — largely lost under a dam lake in 2020.',
                'پایتەختێکی سەدەکانی ناوەڕاست لەسەر زنارەکانی سەر دیجلە، بە ماڵی ئەشکەوتی، پردێکی گەورە و منارەوە — زۆربەی لە ساڵی ٢٠٢٠دا لە ژێر دەریاچەی بەنداوێکدا نوقم بوو.',
                'عاصمة من العصور الوسطى على جروف فوق دجلة، بمساكن كهفية وجسر عظيم ومآذن — غُمر معظمها تحت بحيرة سدّ عام ٢٠٢٠.',
            ],
            'body' => [
                "People lived in the caves of Hasankeyf for thousands of years. In the 12th century it became a capital of the Artuqids, who built a bridge across the Tigris with one of the widest stone arches of its time; later the Ayyubids added mosques and the tall El Rizk minaret, and the Aq Qoyunlu the tiled tomb of Zeynel Bey.\n\nIn 2020 the waters of the Ilısu Dam covered most of the old town. Some monuments were moved to a new site before the flooding. Hasankeyf is remembered here as a loss, and as a reason to protect what remains.",
                "خەڵک بە هەزاران ساڵ لە ئەشکەوتەکانی حەسەنکێفدا ژیاون. لە سەدەی دوازدەدا بوو بە پایتەختی ئەرتوقییەکان، کە پردێکیان بەسەر دیجلەدا دروستکرد بە یەکێک لە پانترین کەوانە بەردینەکانی سەردەمی خۆی؛ دواتر ئەیووبییەکان مزگەوت و منارە بەرزەکەی ئەلڕزقیان زیاد کرد، و ئاق قۆیۆنلووەکان گۆڕە کاشیکراوەکەی زەینەل بەگ.\n\nساڵی ٢٠٢٠ ئاوی بەنداوی ئیلیسو زۆربەی شارە کۆنەکەی داپۆشی. هەندێک لە بیناکان پێش نوقمبوون گوازرانەوە بۆ شوێنێکی نوێ. حەسەنکێف لێرە وەک لەدەستدانێک یاد دەکرێتەوە، و وەک هۆیەک بۆ پاراستنی ئەوەی ماوە.",
                "عاش الناس في كهوف حصن كيفا آلاف السنين. وفي القرن الثاني عشر صارت عاصمة للأرتقيين، الذين بنوا جسراً على دجلة بأحد أعرض الأقواس الحجرية في زمنه؛ ثم أضاف الأيوبيون جوامع ومنارة الرزق العالية، وأضاف الآق قويونلو ضريح زينل بك المكسوّ بالقرميد.\n\nوفي عام ٢٠٢٠ غمرت مياه سدّ إليسو معظم البلدة القديمة. ونُقلت بعض المعالم إلى موقع جديد قبل الغمر. تُذكر حصن كيفا هنا خسارةً، وسبباً لحماية ما بقي.",
            ],
        ],
        [
            'slug' => 'mardin-old-town', 'region' => 'bakur', 'era' => 'medieval', 'at' => [37.3129, 40.7339],
            'name' => ['Mardin Old Town', 'شاری کۆنی مێردین', 'ماردين القديمة'],
            'place' => ['Mêrdîn (Mardin)', 'مێردین', 'ماردين'],
            'period' => ['12th–15th century and later', 'سەدەی ١٢–١٥ و دواتر', 'القرن ١٢–١٥ وما بعده'],
            'summary' => [
                'A city of honey-coloured stone stepping down a mountainside above the Mesopotamian plain, with madrasas, mosques and churches side by side.',
                'شارێکی بەردی هەنگوینی ڕەنگ کە پلە بە پلە بە لێژایی چیایەکدا بەسەر دەشتی میزۆپۆتامیادا دادەبەزێت، مەدرەسە و مزگەوت و کڵێسا تێیدا لە تەنیشت یەکترن.',
                'مدينة من الحجر العسلي اللون تنحدر درجاتٍ على سفح جبل فوق سهل بلاد الرافدين، تتجاور فيها المدارس والجوامع والكنائس.',
            ],
            'body' => [
                "Mardin is built of a soft local limestone that is easy to carve, which is why its doorways, windows and courtyards are so richly worked. Houses are stacked on terraces so that one roof is the next house's view, and the whole town looks south over the plain.\n\nThe Artuqid Great Mosque, the Zinciriye Madrasa of 1385 with its ribbed domes, and the Kasımiye Madrasa stand among old churches and monasteries of the Syriac Christians. Kurds, Arabs, Syriacs and Armenians have all shaped this city.",
                "مێردین لە بەردێکی کلسیی نەرمی ناوخۆیی دروستکراوە کە هەڵکەندنی ئاسانە، بۆیە دەرگا، پەنجەرە و حەوشەکانی ئەوەندە بە وردی کاریان لەسەر کراوە. خانووەکان لەسەر سەکۆکان لەسەر یەک ڕیزکراون بە شێوەیەک کە سەربانی یەکێک دیمەنی خانووی دواترە، و هەموو شارەکە بەرەو باشوور بەسەر دەشتدا دەڕوانێت.\n\nمزگەوتی گەورەی ئەرتوقی، مەدرەسەی زنجیرییە لە ساڵی ١٣٨٥ بە گومبەزە هێڵدارەکانییەوە، و مەدرەسەی قاسمییە لە نێو کڵێسا و دێرە کۆنەکانی مەسیحییە سریانییەکاندا ڕاوەستاون. کورد، عەرەب، سریان و ئەرمەن هەموویان ئەم شارەیان شێوە پێداوە.",
                "بُنيت ماردين من حجر جيري محلي ليّن سهل النحت، ولهذا جاءت أبوابها ونوافذها وأفنيتها غنية بالزخارف. البيوت متراكبة على مصاطب بحيث يكون سطح البيت إطلالةَ البيت الذي يليه، والمدينة كلها تنظر جنوباً إلى السهل.\n\nيقوم الجامع الكبير الأرتقي، والمدرسة الزنجيرية من عام ١٣٨٥ بقبابها المضلّعة، والمدرسة القاسمية بين كنائس وأديرة قديمة للمسيحيين السريان. وقد شارك الكرد والعرب والسريان والأرمن جميعاً في تشكيل هذه المدينة.",
            ],
        ],
        [
            'slug' => 'amedi', 'region' => 'bashur', 'era' => 'principalities', 'at' => [37.0925, 43.4875],
            'name' => ['Amedi', 'ئامێدی', 'العمادية'],
            'place' => ['Duhok province', 'پارێزگای دهۆک', 'محافظة دهوك'],
            'period' => ['Ancient; capital of Bahdinan, 14th–19th century', 'کۆن؛ پایتەختی بادینان، سەدەی ١٤–١٩', 'قديمة؛ عاصمة بهدينان، القرن ١٤–١٩'],
            'summary' => [
                'A town on a flat-topped mountain, entered through the carved Bab Zebar gate — once the capital of the Kurdish emirate of Bahdinan.',
                'شارۆچکەیەک لەسەر چیایەکی سەرتەخت، لە دەروازەی نەخشێنراوی بابا زێبارەوە دەچیتە ژوورەوە — جاران پایتەختی میرنشینی کوردیی بادینان بوو.',
                'بلدة على جبل مسطّح القمة، يُدخل إليها من بوابة باب زيبار المنحوتة — كانت عاصمة الإمارة الكردية بهدينان.',
            ],
            'body' => [
                "Amedi sits on a natural fortress: a plateau about a kilometre long with cliffs on every side, high above the valleys of Bahdinan. People have lived here since antiquity, and for centuries it was the seat of the emirs of Bahdinan, a Kurdish principality.\n\nThe western gate, Bab Zebar, still carries carved figures in its stone. The town's old mosque and minaret, and the ruins of the Qubahan school, a famous centre of learning, recall when Amedi was one of the leading cities of Kurdistan.",
                "ئامێدی لەسەر قەڵایەکی سروشتی دانیشتووە: دەشتەبەرزێکی نزیکەی یەک کیلۆمەتر درێژ بە زنار لە هەموو لایەکەوە، بە بەرزی بەسەر دۆڵەکانی بادینانەوە. لە سەردەمانی کۆنەوە خەڵک لێرە ژیاون، و بە سەدان ساڵ بنکەی میرەکانی بادینان بوو، میرنشینێکی کوردی.\n\nدەروازەی ڕۆژاوا، بابا زێبار، هێشتا پەیکەری هەڵکەنراو لە بەردەکەیدا هەڵدەگرێت. مزگەوت و منارە کۆنەکەی شارۆچکەکە، و وێرانەکانی قوتابخانەی قوبهان، ناوەندێکی بەناوبانگی زانست، ئەو سەردەمە بیردەخەنەوە کە ئامێدی یەکێک لە شارە پێشەنگەکانی کوردستان بوو.",
                "تقع العمادية على حصن طبيعي: هضبة طولها نحو كيلومتر تحيط بها الجروف من كل جانب، عالياً فوق وديان بهدينان. سكنها الناس منذ القدم، وكانت لقرون مقرّ أمراء بهدينان، وهي إمارة كردية.\n\nلا تزال البوابة الغربية، باب زيبار، تحمل شخوصاً منحوتة في حجرها. ويذكّر جامع البلدة القديم ومنارته، وأطلال مدرسة قبهان، المركز العلمي الشهير، بزمن كانت فيه العمادية من أبرز مدن كوردستان.",
            ],
        ],
        [
            'slug' => 'ishak-pasha-palace', 'region' => 'bakur', 'era' => 'principalities', 'at' => [39.5208, 44.1311],
            'name' => ['Ishak Pasha Palace', 'کۆشکی ئیسحاق پاشا', 'قصر إسحاق باشا'],
            'place' => ['Near Bazîd (Doğubayazıt)', 'نزیک بازید (دۆغوبایەزید)', 'قرب بايزيد (دوغوبايزيد)'],
            'period' => ['1685–1784', '١٦٨٥–١٧٨٤', '١٦٨٥–١٧٨٤'],
            'summary' => [
                'A palace on a terrace below Mount Ararat, a century in the building, mixing Ottoman, Persian, Armenian and Seljuk traditions in one carved whole.',
                'کۆشکێک لەسەر سەکۆیەک لە خوار چیای ئاگری، سەدەیەک دروستکردنی خایاند، نەریتی عوسمانی، فارسی، ئەرمەنی و سەلجووقی لە یەک تەواوەتیی نەخشێنراودا تێکەڵ دەکات.',
                'قصر على مصطبة أسفل جبل آرارات، استغرق بناؤه قرناً، يمزج التقاليد العثمانية والفارسية والأرمنية والسلجوقية في كلّ منحوت واحد.',
            ],
            'body' => [
                "Begun in 1685 and finished in 1784 under Ishak Pasha, a local ruler of the Bayazid region, the palace stands on a rocky spur above the plain, on the old road between Anatolia and Iran. It held a harem and selamlık, a mosque, baths, kitchens, a dungeon and a tomb, organised around courtyards.\n\nIts carved portals, with stalactite niches and twisting floral ornament, are among the richest stonework of their age. Seen against the mountains, it is one of the most photographed buildings in Kurdistan.",
                "لە ١٦٨٥ دەستی پێکرد و لە ١٧٨٤ لە سەردەمی ئیسحاق پاشادا، فەرمانڕەوایەکی ناوچەی بایەزید، تەواو بوو. کۆشکەکە لەسەر لووتکەیەکی بەردین بەسەر دەشتدا ڕاوەستاوە، لەسەر ڕێگا کۆنەکەی نێوان ئەنادۆڵ و ئێران. حەرەمسەرا و دیوەخان، مزگەوت، حەمام، چێشتخانە، زیندان و گۆڕێکی لەخۆ گرتبوو، کە لە دەوری حەوشەکان ڕێکخرابوون.\n\nدەروازە نەخشێنراوەکانی، بە تاقی شێوە ستالاکتیت و نەخشی گوڵ و گیای پێچاوپێچ، لە دەوڵەمەندترین کاری بەردی سەردەمی خۆیانن. لە بەرامبەر چیاکاندا، یەکێکە لەو بینایانەی کوردستان کە زۆرترین وێنەیان گیراوە.",
                "بدأ بناؤه عام ١٦٨٥ واكتمل عام ١٧٨٤ في عهد إسحاق باشا، حاكم محلي لمنطقة بايزيد. يقوم القصر على نتوء صخري فوق السهل، على الطريق القديم بين الأناضول وإيران. وضمّ حرملكاً وسلاملكاً وجامعاً وحمامات ومطابخ وسجناً وضريحاً، تنتظم حول أفنية.\n\nبواباته المنحوتة، بمقرنصاتها وزخارفها النباتية الملتفّة، من أغنى أعمال الحجر في عصرها. وأمام الجبال، هو من أكثر مباني كوردستان تصويراً.",
            ],
        ],
        [
            'slug' => 'hawraman', 'region' => 'rojhelat', 'era' => 'living', 'at' => [35.247, 46.385], 'unesco' => true,
            'name' => ['Hawraman (Uraman)', 'هەورامان', 'هورامان (أورامان)'],
            'place' => ['Hawraman Takht and the villages of Hawraman', 'هەورامانی تەخت و گوندەکانی هەورامان', 'هورامان تخت وقرى هورامان'],
            'period' => ['A living tradition, thousands of years old', 'نەریتێکی زیندوو، هەزاران ساڵ کۆن', 'تقليد حيّ عمره آلاف السنين'],
            'summary' => [
                'Stepped stone villages on steep Zagros slopes, where the roof of one house is the yard of the house above.',
                'گوندە بەردینە پلەپلەکان لەسەر لێژاییە سەختەکانی زاگرۆس، کە سەربانی خانوویەک حەوشەی خانووی سەرەوەیەتی.',
                'قرى حجرية متدرّجة على منحدرات زاغروس الشديدة، حيث سطح البيت فناءٌ للبيت الذي فوقه.',
            ],
            'body' => [
                "In Hawraman the mountain decides the plan. Houses of dry-laid stone and timber are built into slopes so steep that each flat roof becomes the courtyard, path and meeting place of the house above. Terraced gardens of walnut, mulberry and pomegranate are watered by channels from the springs.\n\nThis is architecture without architects, refined over many generations by people who farmed and herded these valleys and kept their own language and customs. In 2021 UNESCO inscribed the Cultural Landscape of Hawraman as a World Heritage Site — a heritage that is still lived in.",
                "لە هەوراماندا چیاکە نەخشەکە دیاری دەکات. خانووەکان لە بەردی وشکەچن و دار، لە لێژاییەکی ئەوەندە سەختدا دروستکراون کە سەربانی تەختی هەر یەکێکیان دەبێتە حەوشە، ڕێگا و شوێنی کۆبوونەوەی خانووی سەرەوە. باخە پلەپلەکانی گوێز، توو و هەنار بە جۆگەی ئاوی کانییەکان ئاو دەدرێن.\n\nئەمە تەلارسازییە بێ تەلارساز، کە بە نەوەی زۆر لە لایەن خەڵکانێکەوە پوخت کراوە کە لەم دۆڵانەدا کشتوکاڵ و ئاژەڵداریان کردووە و زمان و نەریتی خۆیان پاراستووە. ساڵی ٢٠٢١ یونسکۆ دیمەنی کولتووریی هەورامانی وەک میراتی جیهانی تۆمار کرد — میراتێک کە هێشتا تێیدا دەژیترێت.",
                "في هورامان، الجبل هو من يقرّر المخطط. بيوت من الحجر المرصوف بلا ملاط ومن الخشب، مبنية في منحدرات شديدة الانحدار حتى يصير سطح كل بيت فناءً وطريقاً ومكان لقاء للبيت الذي فوقه. وتُروى البساتين المدرّجة من الجوز والتوت والرمان بسواقٍ من الينابيع.\n\nإنها عمارة بلا معماريين، صقلتها أجيال كثيرة من أناس زرعوا هذه الوديان ورعوا فيها وحافظوا على لغتهم وعاداتهم. وفي عام ٢٠٢١ أدرجت اليونسكو المشهد الثقافي لهورامان على قائمة التراث العالمي — تراث لا يزال مسكوناً.",
            ],
        ],
    ];
};
