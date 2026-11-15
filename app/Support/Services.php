<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * The studio's services: the homepage's cards (home/services) and the
 * Services page's chapters (/services). Each has its name and line in the
 * three languages, a line icon in the site's drafting language, and 'to':
 * where it leads on /projects — every project ([]), a category or a status.
 * Without 'to' it has no projects to show, and leads to the contact page.
 */
class Services
{
    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        $services = [
            ['en' => 'Architectural Design', 'ar' => 'التصميم المعماري', 'dar' => 'تصميم متكامل للمبنى — من الفكرة الأولى حتى المخطّطات المعتمدة.', 'ku' => 'دیزاینی تەڵارسازی',
             'den' => 'Complete building design — from first concept to approved drawings.',
             'dku' => 'دیزاینی تەواوی بینا — لە یەکەم بیرۆکەوە تا نەخشەی پەسەندکراو.',
             'to' => [],
             'icon' => '<circle cx="18" cy="7" r="3" stroke-width="1.8"/><line x1="16.4" y1="9.8" x2="9" y2="30" stroke-width="2"/><line x1="19.6" y1="9.8" x2="27" y2="30" stroke-width="2"/><path d="M11.5 24.5a13 13 0 0 0 13 0" stroke-width="1.8"/>'],
            ['en' => 'Exterior Design', 'ar' => 'التصميم الخارجي', 'dar' => 'واجهات وبيئات خارجية تمنح المبنى وجهه أمام الناس.', 'ku' => 'دیزاینی دەرەکی',
             'den' => 'Façades and outdoor environments that give a building its public face.',
             'dku' => 'ڕووکار و ژینگەی دەرەوە کە ڕوخساری گشتیی بینا دروستدەکەن.',
             'to' => ['type' => 'exterior-design'],
             'icon' => '<rect x="4" y="8" width="15" height="24" stroke-width="1.8"/><line x1="8" y1="14" x2="15" y2="14" stroke-width="1.6"/><line x1="8" y1="20" x2="15" y2="20" stroke-width="1.6"/><line x1="8" y1="26" x2="15" y2="26" stroke-width="1.6"/><circle cx="27" cy="22" r="4" stroke-width="1.8"/><line x1="27" y1="26" x2="27" y2="32" stroke-width="1.8"/><line x1="22" y1="32" x2="32" y2="32" stroke-width="1.8"/>'],
            ['en' => 'Interior Design', 'ar' => 'التصميم الداخلي', 'dar' => 'تخطيط الفضاءات والمواد والإضاءة — تصاميم داخلية صُنعت لتُعاش.', 'ku' => 'دیزاینی ناوەکی',
             'den' => 'Spatial planning, materials and light — interiors made to be lived in.',
             'dku' => 'پلاندانانی بۆشایی، کەرەستە و ڕووناکی — ناوەوەیەک بۆ ژیان.',
             'to' => ['type' => 'interior-design'],
             'icon' => '<path d="M8 19v-7a3 3 0 0 1 3-3h14a3 3 0 0 1 3 3v7" stroke-width="1.8"/><path d="M5 19a2.5 2.5 0 0 1 5 0v2h16v-2a2.5 2.5 0 0 1 5 0v6a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2z" stroke-width="1.8"/><line x1="9" y1="29" x2="9" y2="32" stroke-width="1.8"/><line x1="27" y1="29" x2="27" y2="32" stroke-width="1.8"/>'],
            ['en' => 'Construction', 'ar' => 'الإنشاء', 'dar' => 'تنفيذ وإشراف في الموقع مع أفضل البنّائين في كوردستان.', 'ku' => 'بیناسازی',
             'den' => "On-site execution and supervision with Kurdistan's finest builders.",
             'dku' => 'جێبەجێکردن و سەرپەرشتی مەیدانی لەگەڵ باشترین بیناسازانی کوردستان.',
             'to' => ['status' => 'under-construction'],
             'icon' => '<line x1="5" y1="33" x2="31" y2="33" stroke-width="2"/><line x1="11" y1="33" x2="11" y2="5" stroke-width="2"/><line x1="11" y1="5" x2="31" y2="5" stroke-width="2"/><line x1="11" y1="12" x2="18" y2="5" stroke-width="1.8"/><line x1="31" y1="5" x2="31" y2="12" stroke-width="1.8"/><rect x="28.5" y="12" width="5" height="4.5" stroke-width="1.8"/>'],
            ['en' => 'Tendering', 'ar' => 'المناقصات', 'dar' => 'إعداد وثائق المناقصة وتقييمها واختيار المقاول الذي تثق به.', 'ku' => 'تەندەرکردن',
             'den' => 'Bid documentation, evaluation and contractor selection you can trust.',
             'dku' => 'ئامادەکردنی بەڵگەنامەی تەندەر، هەڵسەنگاندن و هەڵبژاردنی بەڵێندەر.',
             'icon' => '<path d="M9 3h13l6 6v24H9z" stroke-width="1.8"/><path d="M22 3v6h6" stroke-width="1.8"/><polyline points="14,21 17.5,24.5 23.5,16.5" stroke-width="2"/>'],
            ['en' => 'Customized Furniture', 'ar' => 'أثاث حسب الطلب', 'dar' => 'قطع خاصة تُصمَّم وتُصنع لكل مشروع.', 'ku' => 'مۆبیلیای تایبەتکراو',
             'den' => 'Bespoke pieces designed and crafted for each project.',
             'dku' => 'پارچەی تایبەت، دیزاین و دروستکراو بۆ هەر پرۆژەیەک.',
             'icon' => '<line x1="10" y1="4" x2="10" y2="22" stroke-width="2"/><path d="M10 15h15v7" stroke-width="1.8"/><line x1="8" y1="22" x2="27" y2="22" stroke-width="2"/><line x1="11" y1="22" x2="11" y2="32" stroke-width="1.8"/><line x1="24" y1="22" x2="24" y2="32" stroke-width="1.8"/>'],
            ['en' => 'International Furniture Brands', 'ar' => 'علامات أثاث عالمية', 'dar' => 'توفير أفضل علامات الأثاث العالمية وتوريدها — من أنحاء العالم.', 'ku' => 'براندە نێودەوڵەتییەکانی مۆبیلیا',
             'den' => 'Sourcing and supplying world-class furniture brands — worldwide.',
             'dku' => 'دابینکردنی براندە جیهانییەکانی مۆبیلیا — لە هەموو جیهاندا.',
             'icon' => '<circle cx="18" cy="18" r="14" stroke-width="1.8"/><ellipse cx="18" cy="18" rx="6.5" ry="14" stroke-width="1.6"/><line x1="4" y1="18" x2="32" y2="18" stroke-width="1.6"/>'],
        ];

        // On /ar the second slot (ku / dku, read as the page's right-to-left
        // language) carries the Arabic — see rtl_slot().
        if (site_lang() === 'ar') {
            $services = array_map(fn ($s) => ['ku' => $s['ar'] ?? $s['en'], 'dku' => $s['dar'] ?? $s['den']] + $s, $services);
        }

        return $services;
    }

    /** The projects with a page of their own that a service leads to on /projects. */
    public static function projects(array $service, Collection $projects): Collection
    {
        $to = $service['to'] ?? null;
        if ($to === null) {
            return collect();
        }

        return $projects->filter(fn ($p) => $p->hasPage() && match (true) {
            isset($to['type']) => in_array($to['type'], $p->categoryKeys(), true),
            isset($to['status']) => $p->statusKey() === $to['status'],
            default => true,
        })->values();
    }

    /**
     * Where a service leads, in both of the page's languages: its projects
     * on /projects, or — with none to show yet — the contact page.
     *
     * @return array{href: string, en: string, ku: string, arrow: string, href_en?: string, href_ku?: string}
     */
    public static function link(array $service, Collection $projects): array
    {
        $text = fn (string $lang, string $key) => $lang === 'ku' ? SiteText::second($key) : SiteText::en($key);
        $n = static::projects($service, $projects)->count();
        if ($n === 0) {
            return ['href' => site_url('/contact'), 'en' => $text('en', 'services_ask'), 'ku' => $text('ku', 'services_ask'), 'arrow' => '→'];
        }
        $to = $service['to'];
        $path = '/projects'.($to ? '?'.http_build_query($to) : '');
        $say = fn (string $lang) => $n === 1
            ? $text($lang, 'services_see_one')
            : str_replace('{n}', (string) $n, $text($lang, 'services_see'));

        return ['href' => site_url($path), 'href_en' => site_url($path, 'en'), 'href_ku' => site_url($path, 'ku'), 'en' => $say('en'), 'ku' => $say('ku'), 'arrow' => '↗'];
    }
}
