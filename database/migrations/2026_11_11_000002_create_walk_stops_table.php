<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Erbil architecture walk (/erbil-walk): its stops, in order. Seeded
 * with a first walk through the old centre — a draft for the studio to
 * check: the pins are close but meant to be dragged to the exact spot in
 * the admin, and every stop waits for the studio's own photograph.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('walk_stops', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->string('name_en');
            $table->string('name_ku')->nullable();
            $table->string('name_ar')->nullable();
            $table->string('built_en')->nullable();
            $table->string('built_ku')->nullable();
            $table->string('built_ar')->nullable();
            $table->text('note_en')->nullable();
            $table->text('note_ku')->nullable();
            $table->text('note_ar')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('image')->nullable();
            $table->string('image_credit')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        $now = now();
        foreach (self::FIRST_WALK as $i => $s) {
            DB::table('walk_stops')->insert($s + ['sort_order' => $i + 1, 'is_published' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('walk_stops');
    }

    private const FIRST_WALK = [
        [
            'name_en' => 'Erbil Citadel', 'name_ku' => 'قەڵای هەولێر', 'name_ar' => 'قلعة أربيل',
            'built_en' => 'Inhabited for over 6,000 years', 'built_ku' => 'زیاتر لە ٦٬٠٠٠ ساڵە ئاوەدانە', 'built_ar' => 'مأهولة منذ أكثر من ٦٬٠٠٠ عام',
            'note_en' => 'A mound raised by thousands of years of houses built on houses, and among the oldest continuously inhabited places on earth — a UNESCO World Heritage Site since 2014. Its ring of tall house façades, joined wall to wall, is the city\'s first and finest piece of architecture: a whole town that works as a single defensive wall.',
            'note_ku' => 'تەپۆڵکەیەک کە هەزاران ساڵ خانوو لەسەر خانوو بەرزی کردووەتەوە، و یەکێکە لە کۆنترین شوێنە ئاوەدانەکانی سەر زەوی — لە ٢٠١٤ەوە لە لیستی میراتی جیهانیی یونسکۆدایە. ئەڵقەی ڕووکاری بەرزی خانووەکانی، دیوار بە دیوار، یەکەم و جوانترین بەرهەمی تەڵارسازیی شارەکەیە: شارێکی تەواو کە وەک یەک دیواری بەرگری کار دەکات.',
            'note_ar' => 'تلّ ارتفع عبر آلاف السنين ببيوت شُيّدت فوق بيوت، وهو من أقدم الأماكن المأهولة باستمرار على وجه الأرض — ومُدرج في قائمة التراث العالمي لليونسكو منذ عام ٢٠١٤. حلقة واجهات بيوته العالية، المتلاصقة جداراً بجدار، هي أول عمارة في المدينة وأجملها: بلدة كاملة تعمل كسور دفاعي واحد.',
            'lat' => 36.1911400, 'lng' => 44.0092000,
        ],
        [
            'name_en' => 'Kurdish Textile Museum', 'name_ku' => 'مۆزەخانەی چنراوی کوردی', 'name_ar' => 'متحف النسيج الكوردي',
            'built_en' => 'A traditional Citadel house', 'built_ku' => 'خانوویەکی کۆنی ناو قەڵا', 'built_ar' => 'بيت تقليدي داخل القلعة',
            'note_en' => 'Inside the Citadel, in one of its traditional courtyard houses: a lesson in how the old town lived — rooms turned inward around a courtyard, thick walls against the summer heat, and the kilims and felts of Kurdish weavers on display.',
            'note_ku' => 'لە ناو قەڵادا، لە یەکێک لە خانووە حەوشەدارە کۆنەکانیدا: وانەیەک لەوەی شاری کۆن چۆن دەژیا — ژوورەکان بەرەو ناو حەوشە، دیواری ئەستوور دژی گەرمای هاوین، و بەڕە و لبادی چنەرانی کورد کە پیشان دراون.',
            'note_ar' => 'داخل القلعة، في أحد بيوتها التقليدية ذات الفناء: درس في كيف عاشت البلدة القديمة — غرف تتّجه إلى الداخل حول فناء، وجدران سميكة تقي من حرّ الصيف، وتُعرض فيه سجّادات ولبّادات النسّاجين الكورد.',
            'lat' => 36.1913500, 'lng' => 44.0098500,
        ],
        [
            'name_en' => 'Qaysari Bazaar', 'name_ku' => 'بازاڕی قەیسەری', 'name_ar' => 'سوق القيصرية',
            'built_en' => 'A historic covered market', 'built_ku' => 'بازاڕێکی داپۆشراوی مێژوویی', 'built_ar' => 'سوق مسقوف تاريخي',
            'note_en' => 'At the foot of the Citadel, the old covered market: narrow vaulted lanes that stay cool and shaded at noon, trades grouped lane by lane. Notice how the roofs, openings and alleys are made for the climate long before air conditioning.',
            'note_ku' => 'لە بنی قەڵادا، بازاڕە داپۆشراوە کۆنەکە: کۆڵانی تەسکی کەوانەدار کە لە نیوەڕۆدا فێنک و سێبەردار دەمێننەوە، و پیشەکان کۆڵان بە کۆڵان کۆکراونەتەوە. سەرنج بدە چۆن سەربان و کونەکان و کۆڵانەکان بۆ کەشوهەوا دروستکراون، زۆر پێش ئامێری فێنککەرەوە.',
            'note_ar' => 'عند سفح القلعة، السوق المسقوف القديم: أزقّة ضيّقة مقبّبة تبقى باردة ومظلّلة عند الظهيرة، والحِرف مجمّعة زقاقاً زقاقاً. لاحظ كيف صُمّمت الأسقف والفتحات والأزقّة للمناخ قبل التكييف بزمن طويل.',
            'lat' => 36.1889000, 'lng' => 44.0098000,
        ],
        [
            'name_en' => 'Shar Square', 'name_ku' => 'گۆڕەپانی شار', 'name_ar' => 'ساحة شار',
            'built_en' => 'Redeveloped in the 2010s', 'built_ku' => 'لە ساڵانی ٢٠١٠دا نوێکرایەوە', 'built_ar' => 'أُعيد تطويرها في العقد الثاني من الألفية',
            'note_en' => 'The city\'s main public square, opened up below the Citadel: fountains, shade and long views up to the mound. A good place to see how a historic edge and a new public space meet — and the best view of the Citadel\'s walls at dusk.',
            'note_ku' => 'گۆڕەپانی گشتیی سەرەکیی شار، لە خوار قەڵاوە کراوەتەوە: فوارە، سێبەر و دیمەنی درێژ بەرەو تەپۆڵکەکە. شوێنێکی باشە بۆ بینینی ئەوەی چۆن لێواری مێژوویی و بۆشاییەکی گشتیی نوێ بەیەک دەگەن — و جوانترین دیمەنی دیوارەکانی قەڵا لە کاتی ئێوارەدا.',
            'note_ar' => 'الساحة العامة الرئيسية في المدينة، مفتوحة أسفل القلعة: نوافير وظلال ومشاهد ممتدّة نحو التلّ. مكان جيد لرؤية كيف يلتقي حدٌّ تاريخي بفضاء عام جديد — وأجمل منظر لأسوار القلعة عند الغروب.',
            'lat' => 36.1891000, 'lng' => 44.0078000,
        ],
        [
            'name_en' => 'Mudhafaria Minaret', 'name_ku' => 'منارەی موزەفەرییە', 'name_ar' => 'منارة المظفرية',
            'built_en' => 'Late 12th century', 'built_ku' => 'کۆتایی سەدەی دوازدەهەم', 'built_ar' => 'أواخر القرن الثاني عشر',
            'note_en' => 'Also called the Choli Minaret: the surviving tower of a mosque built under Muzaffar al-Din Gökböri, ruler of Erbil. Look closely at the brickwork — an octagonal base, a cylindrical shaft and bands of patterned glazed brick, all made from small fired bricks laid with great care.',
            'note_ku' => 'ناسراو بە منارەی چۆلی: بورجی ماوەی مزگەوتێک کە لە سەردەمی موزەفەرەدینی کۆکبۆری، فەرمانڕەوای هەولێر، دروستکرا. سەرنجی کاری خشتەکان بدە — بنکەیەکی هەشتگۆشە، قەدێکی لوولەیی و پشتێنەی خشتی ڕەنگکراوی نەخشدار، هەمووی لە خشتی سووتاوی بچووک بە وردی چنراون.',
            'note_ar' => 'وتُعرف أيضاً بمنارة الچولي: البرج الباقي من جامع شُيّد في عهد مظفر الدين كوكبري، حاكم أربيل. تأمّل عمل الآجر — قاعدة مثمّنة، وبدن أسطواني، وأحزمة من الآجر المزجّج المزخرف، كلّها من آجر صغير مشوي رُصّ بعناية كبيرة.',
            'lat' => 36.1847000, 'lng' => 44.0015000,
        ],
        [
            'name_en' => 'Sami Abdulrahman Park', 'name_ku' => 'پارکی سامی عەبدولڕەحمان', 'name_ar' => 'حديقة سامي عبد الرحمن',
            'built_en' => 'A modern city park', 'built_ku' => 'پارکێکی نوێی شار', 'built_ar' => 'حديقة حديثة في المدينة',
            'note_en' => 'One of Erbil\'s largest parks, a short drive from the old centre: lakes, long avenues of trees and open lawns. It shows what landscape does for a hot, dry city — shade, water and green space where the town comes out in the evening.',
            'note_ku' => 'یەکێک لە گەورەترین پارکەکانی هەولێر، کەمێک دوور لە ناوەندی کۆن: دەریاچە، ڕێڕەوی درێژی دار و چیمەنی کراوە. پیشانی دەدات دیزاینی سروشت چی بۆ شارێکی گەرم و وشک دەکات — سێبەر، ئاو و سەوزایی کە خەڵکی شار ئێواران تێیدا کۆدەبنەوە.',
            'note_ar' => 'من أكبر حدائق أربيل، على مسافة قصيرة بالسيارة من المركز القديم: بحيرات وممرّات طويلة من الأشجار ومروج مفتوحة. تُظهر ما يفعله تنسيق المواقع لمدينة حارّة جافة — ظلّ وماء ومساحات خضراء يخرج إليها الناس في المساء.',
            'lat' => 36.1922000, 'lng' => 43.9858000,
        ],
    ];
};
