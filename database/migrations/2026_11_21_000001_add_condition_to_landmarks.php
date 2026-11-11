<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each landmark's state today (App\Models\Landmark::CONDITIONS), one plain
 * line on what happened to it, and where that is reported. Causes are named
 * as the sources name them. Set only where a landmark has none yet, so what
 * the admin has written since is kept.
 */
return new class extends Migration
{
    private const STATE = [
        'gobekli-tepe' => ['intact', 'https://www.al-monitor.com/originals/2023/02/prehistoric-world-heritage-site-gobekli-tepe-survives-turkey-earthquakes',
            'Under a protective roof since the 2010s; the February 2023 earthquakes left it undamaged.',
            'لە ساڵانی ٢٠١٠ەوە لەژێر سەربانێکی پارێزەردایە؛ بوومەلەرزەکانی شوباتی ٢٠٢٣ هیچ زیانێکیان پێ نەگەیاند.',
            'تحت سقف واقٍ منذ العقد الثاني من الألفية؛ ولم تُلحق به زلازل شباط ٢٠٢٣ أيّ ضرر.'],
        'shanidar-cave' => ['intact', 'https://en.wikipedia.org/wiki/Shanidar_Cave',
            'The cave is intact and open to visitors; some of the Neanderthal remains kept in Baghdad may have been lost in the 2003 looting of the Iraq Museum.',
            'ئەشکەوتەکە ساغە و بۆ سەردانکەران کراوەیە؛ هەندێک لە پاشماوەی نیاندەرتاڵەکان کە لە بەغدا پارێزرابوون لەوانەیە لە تاڵانکردنی مۆزەخانەی عێراق لە ٢٠٠٣ ونبووبن.',
            'الكهف سليم ومفتوح للزوّار؛ وربما فُقد بعض بقايا إنسان نياندرتال المحفوظة في بغداد خلال نهب المتحف العراقي عام ٢٠٠٣.'],
        'tell-brak' => ['at_risk', 'https://www.arch.cam.ac.uk/research/projects/tell-brak',
            'Excavation stopped in 2011 with the war in Syria, and the expedition house was looted.',
            'هەڵکۆڵین لە ٢٠١١ بە هۆی شەڕی سووریا ڕاگیرا، و خانووی تیمی هەڵکۆڵینەکە تاڵان کرا.',
            'توقّف التنقيب عام ٢٠١١ مع الحرب في سوريا، ونُهب بيت البعثة.'],
        'urkesh' => ['at_risk', 'https://www.urkesh.org/',
            'In a war zone since 2011; local workers have kept the site, covering its mudbrick walls again every season.',
            'لە ٢٠١١ەوە لە ناوچەی شەڕدایە؛ کرێکارە خۆجێییەکان شوێنەکەیان پاراستووە و هەموو وەرزێک دیوارە خشتە کاڵەکانی دادەپۆشنەوە.',
            'في منطقة حرب منذ ٢٠١١؛ وقد حافظ عليه العمّال المحليون، يغطّون جدرانه اللبنية من جديد كل موسم.'],
        'erbil-citadel' => ['restoring', 'https://www.wmf.org/project/erbil-citadel',
            'Its last families were moved out in 2007 so the houses could be restored; work by the High Commission (HCECR) is still going on.',
            'دوایین خێزانەکانی لە ٢٠٠٧ گوازرانەوە بۆ ئەوەی خانووەکان نۆژەن بکرێنەوە؛ کاری دەستەی باڵای نۆژەنکردنەوەی قەڵا هێشتا بەردەوامە.',
            'نُقلت آخر عائلاته عام ٢٠٠٧ لترميم البيوت؛ ولا يزال عمل الهيئة العليا لإحياء قلعة أربيل مستمرًا.'],
        'tell-halaf' => ['partly_lost', 'https://en.wikipedia.org/wiki/Tell_Halaf',
            'Its sculptures, taken to Berlin, were shattered when bombing set the museum alight in November 1943; more than 30 were pieced back together from about 27,000 fragments in 2001–2010.',
            'پەیکەرەکانی کە بردرابوونە بەرلین، لە تشرینی دووەمی ١٩٤٣ کاتێک بۆمبارانەکە مۆزەخانەکەی سووتاند وردوخاش بوون؛ لە ٢٠٠١–٢٠١٠ زیاتر لە ٣٠ پەیکەر لە نزیکەی ٢٧٬٠٠٠ پارچە دووبارە پێکەوە نرانەوە.',
            'تحطّمت منحوتاته، التي نُقلت إلى برلين، حين أشعل القصف المتحف في تشرين الثاني ١٩٤٣؛ وأُعيد تجميع أكثر من ٣٠ منها من نحو ٢٧٬٠٠٠ قطعة بين ٢٠٠١ و٢٠١٠.'],
        'ain-dara-temple' => ['damaged', 'https://www.asor.org/chi/',
            'Hit by Turkish airstrikes on 20–22 January 2018, during the Afrin offensive; satellite images show much of the temple destroyed, and its basalt lion was removed in 2019.',
            'لە ٢٠–٢٢ی کانوونی دووەمی ٢٠١٨، لە کاتی هێرشی سەر عەفرین، کەوتە بەر بۆمبارانی فڕۆکەی تورکیا؛ وێنەی مانگی دەستکرد دەریدەخەن بەشێکی زۆری پەرستگاکە وێران بووە، و شێرە بازاڵتەکەی لە ٢٠١٩ لابرا.',
            'أصابته غارات جوية تركية في ٢٠–٢٢ كانون الثاني ٢٠١٨ خلال الهجوم على عفرين؛ وتُظهر صور الأقمار الصناعية دمار جزء كبير من المعبد، وأُزيل أسده البازلتي عام ٢٠١٩.'],
        'jerwan-aqueduct' => ['at_risk', 'https://mcid.mcah.columbia.edu/mapping-mesopotamian-monuments/jerwan-aqueduct',
            'Unfenced and open to the weather; people and vehicles climb on the 2,700-year-old stones.',
            'بێ پەرژینە و ڕووبەڕووی کەشوهەوا؛ خەڵک و ئۆتۆمبێل بەسەر بەردە ٢٬٧٠٠ ساڵییەکاندا سەردەکەون.',
            'غير مسيّج ومعرّض للطقس؛ يصعد الناس والسيارات على حجارته التي يبلغ عمرها ٢٬٧٠٠ عام.'],
        'khinis-reliefs' => ['restored', 'https://www.terradininive.com/',
            'The Great Relief was cleaned and consolidated in 2023 by the Land of Nineveh project with the Duhok Directorate of Antiquities.',
            'هەڵکەندراوە گەورەکە لە ٢٠٢٣ لەلایەن پڕۆژەی خاکی نەینەوا لەگەڵ بەڕێوەبەرایەتی شوێنەوارەکانی دهۆک پاککرایەوە و قایمکرا.',
            'نُظّف النقش الكبير ودُعّم عام ٢٠٢٣ على يد مشروع أرض نينوى مع مديرية آثار دهوك.'],
        'bisotun' => ['damaged', 'https://www.livius.org/articles/place/behistun/',
            'Rain and seeping water have worn the inscription, and the rock face carries bullet scars from use as a target.',
            'باران و ئاوی دزەکردوو نووسینەکەیان داخوراندووە، و ڕووی بەردەکە شوێنی گوللەی پێوەیە لەو کاتەی وەک ئامانج بەکارهات.',
            'أبلت الأمطار والمياه المتسرّبة النقش، وتحمل واجهة الصخر آثار رصاص من استخدامها هدفًا للرماية.'],
        'mount-nemrut' => ['at_risk', 'https://www.hurriyetdailynews.com/nemruts-ancient-statues-undergo-nanolime-treatment-for-preservation-225896',
            'The great heads survived the 2023 earthquakes, but frost and sun crack the stone; conservators are treating the statues with nanolime.',
            'سەرە گەورەکان لە بوومەلەرزەکانی ٢٠٢٣ ڕزگاریان بوو، بەڵام سەهۆڵبەندان و خۆر بەردەکە دەدڕێنن؛ پارێزەران پەیکەرەکان بە نانۆ-ئاهەک چارەسەر دەکەن.',
            'نجت الرؤوس الكبيرة من زلازل ٢٠٢٣، لكن الصقيع والشمس يشقّقان الحجر؛ ويعالج المرمّمون التماثيل بالجير النانوي.'],
        'taq-e-bostan' => ['intact', 'https://en.wikipedia.org/wiki/Taq-e_Bostan',
            'Intact, in a public park in Kermanshah, with its pool still fed by the spring.',
            'ساغە، لە باخچەیەکی گشتیی کرماشان، و حەوزەکەی هێشتا لە کانییەکەوە ئاو وەردەگرێت.',
            'سليم، في حديقة عامة في كرمانشاه، ولا يزال حوضه يتغذّى من النبع.'],
        'takht-e-soleyman' => ['intact', 'https://whc.unesco.org/en/list/1077/',
            'A World Heritage Site since 2003, its ruins conserved and parts of them under protective shelters.',
            'لە ٢٠٠٣ەوە شوێنی میراتی جیهانییە؛ کەلاوەکانی پارێزراون و بەشێکیان لەژێر سەربانی پارێزەردان.',
            'موقع تراث عالمي منذ ٢٠٠٣، أطلاله محفوظة وبعضها تحت سقائف واقية.'],
        'amed-city-walls' => ['damaged', 'https://heritagesciencejournal.springeropen.com/articles/10.1186/s40494-023-01072-6',
            'Fighting in Sur in 2015–16 destroyed houses, a mosque and churches inside the walls; the February 2023 earthquakes damaged sections of the walls themselves.',
            'شەڕی سوور لە ٢٠١٥–١٦ خانوو و مزگەوتێک و کڵێساکانی ناو شوورەکەی وێران کرد؛ بوومەلەرزەکانی شوباتی ٢٠٢٣ زیانیان بە بەشێک لە خودی شوورەکان گەیاند.',
            'دمّر القتال في سور عامَي ٢٠١٥–١٦ بيوتًا ومسجدًا وكنائس داخل الأسوار؛ وألحقت زلازل شباط ٢٠٢٣ أضرارًا بأجزاء من الأسوار نفسها.'],
        'lalish' => ['intact', 'https://en.wikipedia.org/wiki/Lalish',
            'A living sanctuary: attacked many times over the centuries and each time rebuilt and kept by the Yazidi community.',
            'پیرۆزگایەکی زیندووە: بە درێژایی سەدەکان چەندین جار هێرشی کراوەتە سەر و هەر جارە کۆمەڵگەی ئێزدی دووبارە دروستیان کردووەتەوە و پاراستوویانە.',
            'مزار حيّ: تعرّض لهجمات كثيرة عبر القرون، وفي كل مرة أعاد المجتمع الإيزيدي بناءه وحافظ عليه.'],
        'mudhafaria-minaret' => ['at_risk', 'https://www.kurdistan24.net/en/story/855359/erbils-iconic-choli-minaret-to-undergo-major-renovation',
            'It leans and its top is broken; stabilised in 2008–09, it is watched closely, and a new renovation was announced in 2025.',
            'لار بووەتەوە و سەرەکەی شکاوە؛ لە ٢٠٠٨–٠٩ قایمکرا، بە وردی چاودێری دەکرێت، و لە ٢٠٢٥ نۆژەنکردنەوەیەکی نوێ ڕاگەیەندرا.',
            'مائلة ورأسها مكسور؛ دُعّمت في ٢٠٠٨–٠٩ وتخضع لمراقبة دقيقة، وأُعلن عن ترميم جديد لها عام ٢٠٢٥.'],
        'hasankeyf' => ['submerged', 'https://earthobservatory.nasa.gov/images/146439/slowly-flooding-history',
            'Flooded by the Ilısu Dam: the reservoir began filling in 2019 and covered the old town in 2020. Eight monuments, among them the Zeynel Bey tomb, were moved to a new site first.',
            'بە بەنداوی ئیلیسو ژێر ئاو کەوت: ئاوگرەکە لە ٢٠١٩ دەستی بە پڕبوون کرد و لە ٢٠٢٠ شاری کۆنی داپۆشی. پێشتر هەشت شوێنەوار، لەوانە گۆڕی زەینەل بەگ، گوازرانەوە بۆ شوێنێکی نوێ.',
            'أغرقه سدّ إليسو: بدأ الخزان يمتلئ عام ٢٠١٩ وغمر البلدة القديمة عام ٢٠٢٠. ونُقلت قبل ذلك ثمانية معالم، منها ضريح زينل بك، إلى موقع جديد.'],
        'mardin-old-town' => ['restored', 'https://en.wikipedia.org/wiki/Mardin',
            'A protected area since the 1970s; from the 2000s, later concrete was stripped away and the stone façades of the old town restored.',
            'لە ساڵانی ١٩٧٠ەوە ناوچەیەکی پارێزراوە؛ لە ساڵانی ٢٠٠٠ەوە کۆنکرێتی دواتر لابرا و ڕووکارە بەردینەکانی شاری کۆن نۆژەن کرانەوە.',
            'منطقة محمية منذ السبعينيات؛ ومنذ العقد الأول من الألفية أُزيل الإسمنت المضاف وأُعيد ترميم واجهات البلدة القديمة الحجرية.'],
        'amedi' => ['restored', 'https://rudaw.net/english/kurdistan/070520242',
            'The Bahdinan Gate was restored in 2019–2024 with the Duhok Directorate of Antiquities; the minaret\'s top, brought down in the 1961 air raids, was rebuilt from its own stones.',
            'دەروازەی بادینان لە ٢٠١٩–٢٠٢٤ لەگەڵ بەڕێوەبەرایەتی شوێنەوارەکانی دهۆک نۆژەن کرایەوە؛ سەری منارەکە کە لە بۆمبارانی ١٩٦١ ڕووخا، بە بەردەکانی خۆی دووبارە دروستکرایەوە.',
            'رُمّمت بوابة بهدينان بين ٢٠١٩ و٢٠٢٤ مع مديرية آثار دهوك؛ وأُعيد بناء رأس المئذنة، الذي أسقطته الغارات الجوية عام ١٩٦١، من حجارته نفسها.'],
        'ishak-pasha-palace' => ['restored', 'https://en.wikipedia.org/wiki/Ishak_Pasha_Palace',
            'Its gilded doors were carried off during the Russian occupation of 1828 and earthquakes have shaken it; it was given a glass roof in 2009 and restored.',
            'دەرگا زێڕینەکانی لە کاتی داگیرکاریی ڕووسیا لە ١٨٢٨ بردران و بوومەلەرزە هەژاندوویەتی؛ لە ٢٠٠٩ سەربانێکی شووشەیی بۆ کرا و نۆژەن کرایەوە.',
            'حُملت أبوابه المذهّبة خلال الاحتلال الروسي عام ١٨٢٨ وهزّته الزلازل؛ ووُضع له سقف زجاجي عام ٢٠٠٩ ورُمّم.'],
        'hawraman' => ['at_risk', 'https://en.wikipedia.org/wiki/Cultural_Landscape_of_Uramanat',
            'The Darian Dam, filled from 2015, drowned the valley\'s lowest terraces and early cave sites; in the villages, concrete is replacing dry stone.',
            'بەنداوی داریان، کە لە ٢٠١٥ەوە پڕکرا، نزمترین تەڕاسەکانی دۆڵەکە و ئەشکەوتە کۆنەکانی ژێر ئاو خست؛ لە گوندەکاندا کۆنکرێت شوێنی بەردی وشک دەگرێتەوە.',
            'أغرق سدّ داريان، الذي مُلئ منذ ٢٠١٥، أدنى مدرّجات الوادي ومواقع كهوف قديمة؛ وفي القرى يحلّ الإسمنت محلّ الحجر الجاف.'],
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('landmarks', 'condition')) {
            Schema::table('landmarks', function (Blueprint $table) {
                $table->string('condition', 20)->nullable();
                $table->text('condition_en')->nullable();
                $table->text('condition_ku')->nullable();
                $table->text('condition_ar')->nullable();
                $table->string('condition_url')->nullable();
            });
        }

        foreach (self::STATE as $slug => [$state, $url, $en, $ku, $ar]) {
            DB::table('landmarks')->where('slug', $slug)->whereNull('condition')->update([
                'condition' => $state, 'condition_url' => $url,
                'condition_en' => $en, 'condition_ku' => $ku, 'condition_ar' => $ar,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('landmarks', function (Blueprint $table) {
            $table->dropColumn(['condition', 'condition_en', 'condition_ku', 'condition_ar', 'condition_url']);
        });
    }
};
