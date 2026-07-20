<?php

namespace App\Support;

use App\Models\SiteSetting;

/**
 * SiteText — the single source of truth for every bilingual (English/Kurdish)
 * string on the public site.
 *
 * Each entry is a base key with an English + Kurdish default and a label for
 * the admin panel. At render time the live value is resolved as:
 *     setting("{key}_en")  →  legacy setting("{key}")  →  the default here.
 * so nothing changes visually until an editor overrides it, and older
 * single-key settings (e.g. about_bio_1) keep working.
 *
 * The admin "Site Content" screen builds its EN/KU fields from this list, so
 * adding a new editable string is a one-line change here + a bival()/bitext()
 * call in the blade — no migration required.
 */
class SiteText
{
    /** @var array<string, array<string, array{0:string,1:string,2:string,3?:string}>> */
    public static function tabs(): array
    {
        return [
            'Navigation & UI' => [
                'brand_name'     => ['Navbar — studio name', 'ARAM MIZURI', 'ئارام مزووری'],
                'brand_sub'      => ['Navbar — subtitle', 'Architecture · Erbil', 'تەڵارسازی · هەولێر'],
                'nav_projects'   => ['Nav — Projects', 'Projects', 'پرۆژەکان'],
                'nav_map'        => ['Nav — Map', 'Map', 'نەخشە'],
                'nav_about'      => ['Nav — About', 'About', 'دەربارەی'],
                'nav_services'   => ['Nav — Services', 'Services', 'خزمەتگوزاری'],
                'nav_process'    => ['Nav — Process', 'Process', 'ڕێگا'],
                'nav_contact'    => ['Nav — Contact', 'Contact', 'پەیوەندی'],
                'ui_scroll'      => ['Scroll hint', 'Scroll', 'بۆ خوارەوە'],
                'ui_clock_city'  => ['Hero clock — city', 'Erbil', 'هەولێر'],
                'stat_projects'  => ['Stat label — Projects', 'Projects', 'پرۆژە'],
                'stat_clients'   => ['Stat label — Clients', 'Clients', 'کڕیار'],
                'stat_cities'    => ['Stat label — Cities', 'Cities', 'شار'],
                'stat_countries' => ['Stat label — Countries', 'Countries', 'وڵات'],
                'stat_area'      => ['Stat label — Area', 'm² Designed', 'م² دیزاینکراو'],
            ],

            'Hero' => [
                'hero_eyebrow'      => ['Eyebrow', 'Erbil · Kurdistan · Est. 2022', 'هەولێر · کوردستان · دامەزراوە ٢٠٢٢'],
                'hero_title_line1'  => ['Title — line 1 (append " by …" for the gold sub)', 'Architecture by', 'تەڵارسازی لەلایەن', 'area'],
                'hero_title_line2'  => ['Title — line 2 (gold accent)', 'Aram Mizuri', 'ئارام مزووری'],
                'hero_sub'          => ['Subtitle', 'Shaping the built environment of Erbil — and beyond.', 'شێوەدانی ژینگەی بنیاتنراوی هەولێر — و دوورتر.', 'area'],
                'hero_cta'          => ['Explore button', 'Explore Projects', 'پرۆژەکان ببینە'],
            ],

            'The Record (stats band)' => [
                'statbar_label' => ['Section label', 'The Record', 'تۆمارەکە'],
                'statbar_title' => ['Title (allows <em>)', 'Strength, <em>in numbers.</em>', 'هێز، <em>بە ژمارە.</em>', 'area'],
                'statbar_sub'   => ['Subtitle', 'Years of building across Kurdistan and beyond — every figure here is carried by standing architecture.', 'ساڵانێک لە بیناسازی لە کوردستان و دەرەوەی — هەر ژمارەیەکی ئێرە بە تەڵارسازیی وەستاو هەڵگیراوە.', 'area'],
                'statbar_swipe' => ['Mobile swipe hint', 'swipe for more figures', 'ڕایبکێشە بۆ ژمارەی زیاتر'],
            ],

            'Map' => [
                'map_label'        => ['Section label', 'Where We Build', 'کوێ دەبینین'],
                'map_title'        => ['Title (allows <br>)', 'Projects Across<br>Kurdistan & Beyond', 'پرۆژەکان بەسەر<br>کوردستان و دوورتردا', 'area'],
                'map_view_project' => ['Popup — view project', 'View Full Project', 'بینینی پڕۆژەی تەواو'],
            ],

            'Clients' => [
                'clients_trusted' => ['Section label', 'Trusted By', 'پشتیوانیكراو'],
                'clients_title'   => ['Title', 'Clients & Partners', 'کڕیار و هاوبەشەکان'],
                'clients_hint'    => ['Hint', 'click a logo to see all partners', 'کرتە لە لۆگۆیەک بکە بۆ بینینی هەموو هاوبەشەکان'],
            ],

            'Projects gallery' => [
                'pg_label'           => ['Section label', 'Selected Work', 'کارە هەڵبژێردراوەکان'],
                'pg_title'           => ['Title', 'Projects', 'پرۆژەکان'],
                'filter_all'         => ['Filter — All', 'All', 'هەمووی'],
                'filter_residential' => ['Filter — Residential', 'Residential', 'نیشتەجێبوون'],
                'filter_commercial'  => ['Filter — Commercial', 'Commercial', 'بازرگانی'],
                'filter_hospitality' => ['Filter — Hospitality', 'Hospitality', 'میوانپەروەری'],
                'filter_mixeduse'    => ['Filter — Mixed-Use', 'Mixed-Use', 'تێکەڵ'],
                'filter_cultural'    => ['Filter — Cultural', 'Cultural', 'کولتووری'],
                'filter_urban'       => ['Filter — Master Planning', 'Master Planning', 'شارستانی'],
                'pg_view_project'    => ['Card CTA', 'View Project', 'بینینی پرۆژە'],
                'pg_empty'           => ['Empty state', 'No projects match your criteria.', 'هیچ پرۆژەیەک لەگەڵ داواکارییەکەت ناگونجێت.'],
            ],

            'Services' => [
                'services_label' => ['Section label', 'What We Do', 'ئەوەی دەیکەین'],
                'services_title' => ['Title', 'Services', 'خزمەتگوزارییەکان'],
            ],

            'Process' => [
                'process_label'       => ['Section label', 'How We Work', 'چۆن کار دەکەین'],
                'process_title'       => ['Title', 'Our Process', 'پرۆسەکەمان'],
                'process_selected'    => ['Showcase label', 'Selected Designs', 'دیزاینە هەڵبژێردراوەکان'],
                'process_phase1'      => ['Phase 1 kicker', 'Phase 01', 'قۆناغی ٠١'],
                'process_step1_title' => ['Step 1 — title', 'Listen & Understand', 'گوێگرتن و تێگەیشتن'],
                'process_step1_desc'  => ['Step 1 — description', 'Deep listening — understanding the client\'s vision, the cultural fabric of the site, and the spatial traditions of Kurdistan.', 'گوێگرتنی قووڵ — تێگەیشتن لە بینینی کڕیار، پێکهاتەی کولتووریی شوێنەکە، و نەریتە شوێنییەکانی کوردستان.', 'area'],
                'process_phase2'      => ['Phase 2 kicker', 'Phase 02', 'قۆناغی ٠٢'],
                'process_step2_title' => ['Step 2 — title', 'Design & Iterate', 'دیزاین و دووبارەکردنەوە'],
                'process_step2_desc'  => ['Step 2 — description', 'Iterative design drawn from Kurdish spatial traditions, climate-responsive strategy, and contemporary architectural dialogue.', 'دیزاینی دووبارەبووەوە کە لە نەریتە شوێنییە کوردییەکان، ستراتیژی گونجاو لەگەڵ کەشوهەوا، و گفتوگۆی تەڵارسازیی هاوچەرخەوە سەرچاوە دەگرێت.', 'area'],
                'process_phase3'      => ['Phase 3 kicker', 'Phase 03', 'قۆناغی ٠٣'],
                'process_step3_title' => ['Step 3 — title', 'Detail & Refine', 'وردەکاری و پاڵێوان'],
                'process_step3_desc'  => ['Step 3 — description', 'Technical precision and material research ensuring every detail respects the ideas and craft of Kurdistan\'s builders.', 'وردیی تەکنیکی و توێژینەوەی کەرەستە کە دڵنیایی دەدات هەر وردەکارییەک ڕێز لە بیرۆکە و پیشەوەریی بیناسازانی کوردستان بگرێت.', 'area'],
                'process_phase4'      => ['Phase 4 kicker', 'Phase 04', 'قۆناغی ٠٤'],
                'process_step4_title' => ['Step 4 — title', 'Build & Deliver', 'بنیاتنان و گەیاندن'],
                'process_step4_desc'  => ['Step 4 — description', 'On-site construction oversight and quality assurance — building relationships with Kurdistan\'s finest local firms and craftspeople.', 'چاودێریی بنیاتنان لە شوێن و دڵنیایی جۆرایەتی — دروستکردنی پەیوەندی لەگەڵ باشترین کۆمپانیا و پیشەوەرە خۆماڵییەکانی کوردستان.', 'area'],
            ],

            'Heritage' => [
                'heritage_label'     => ['Section label', 'Rooted in Heritage', 'ڕەگ لە میرات'],
                'heritage_title'     => ['Title', "Shaped by\n7,000 years of\nKurdish civilization", 'شێوەپێدراو بە ٧٬٠٠٠ ساڵ شارستانیەتی کوردی', 'area'],
                'heritage_desc'      => ['Description', 'The Erbil Citadel — one of the oldest continuously inhabited settlements on Earth — stands as testament to the enduring spirit of Kurdistan. Our architecture draws from this deep well of history while building boldly for the future.', 'قەڵای هەولێر — یەکێک لە کۆنترین شوێنە نیشتەجێبووە بەردەوامەکانی سەر زەوی — شایەتی ڕۆحی نەمری کوردستانە. تەڵارسازیمان لەم کانگا مێژووییە قووڵەوە سەرچاوە دەگرێت و بە بوێرییەوە بۆ داهاتوو بنیات دەنێین.', 'area'],
                'heritage_years'     => ['Age — unit', 'years', 'ساڵ'],
                'heritage_age_label' => ['Age — caption', "of unbroken life atop the Erbil Citadel — the world's oldest continuously inhabited settlement.", 'ژیانی نەبڕاوە لەسەر قەڵای هەولێر — کۆنترین شوێنی نیشتەجێبوونی بەردەوام لە جیهاندا.', 'area'],
                'heritage_cta'       => ['Button', 'View our projects', 'پرۆژەکانمان ببینە'],
            ],

            'About' => [
                'about_label'            => ['Section label', 'About the Practice', 'دەربارەی ئۆفیسەکە'],
                'about_heading'          => ['Heading (allows <em>/<br>)', 'A Kurdish architectural voice', 'دەنگێکی تەڵارسازیی کوردی', 'area'],
                'about_bio_1'            => ['Biography — paragraph 1', 'Aram Mizuri is a leading architect based in Erbil, the capital of the Kurdistan Region of Iraq. His practice bridges the ancient and the contemporary — drawing from the rich spatial heritage of the Kurdish highlands, from the 7,000-year-old Erbil Citadel to the mountain villages of Amadiyah — to create buildings that are distinctly Kurdish in sensibility while rigorous in their modernity.', 'ئارام مزووری تەڵارسازێکی پێشەنگە کە لە هەولێر، پایتەختی هەرێمی کوردستانی عێراق، نیشتەجێیە. کارەکەی پردێکە لەنێوان کۆن و هاوچەرخدا — سەرچاوە دەگرێت لە میراتی دەوڵەمەندی شوێنی بەرزاییەکانی کوردستان، لە قەڵای ٧٬٠٠٠ ساڵەی هەولێرەوە تا گوندە شاخاوییەکانی ئامێدی — بۆ دروستکردنی بینایەک کە بە ڕوونی کوردییە لە هەستەوە و لەهەمانکاتدا توند و هاوچەرخە.', 'area'],
                'about_bio_2'            => ['Biography — paragraph 2', 'With completed projects across Kurdistan, including cultural institutions, urban towers, mountain retreats, and civic spaces, Mizuri Architecture has become a defining voice in the rapidly evolving built landscape of the region.', 'بە پرۆژە تەواوکراوەکان لە سەرانسەری کوردستان، لەوانە دامەزراوە کولتوورییەکان، بورجە شارییەکان، پەناگا شاخاوییەکان و بۆشایی گشتییەکان، تەڵارسازیی مزووری بووەتە دەنگێکی دیاریکەر لە دیمەنی بنیاتنراوی خێراگۆڕی ناوچەکەدا.', 'area'],
                'about_quote'            => ['Pull quote', '"Architecture is the bridge between a people\'s memory and the dreams of their future."', '«تەڵارسازی ئەو پردەیە کە یادەوەریی گەلێک بە خەونەکانی داهاتوویانەوە دەبەستێتەوە.»', 'area'],
                'about_quote_cite'       => ['Quote attribution', '— Aram Mizuri', '— ئارام مزووری'],
                'about_portrait_caption' => ['Portrait caption', 'Aram Mizuri — Principal Architect', 'ئارام مزووری · تەڵارسازی سەرەکی'],
            ],

            'Contact' => [
                'contact_label'      => ['Section label', 'Get in Touch', 'پەیوەندیمان پێوە بکە'],
                'contact_title'      => ['Heading (allows <br>)', "Let's Build<br>Together", 'با پێکەوە<br>بنیات بنێین', 'area'],
                'contact_email_lbl'  => ['Email — label', 'Email', 'ئیمەیڵ'],
                'contact_phone_lbl'  => ['Phone 01 — label', 'Phone 01', 'تەلەفۆن ٠١'],
                'contact_phone2_lbl' => ['Phone 02 — label', 'Phone 02', 'تەلەفۆن ٠٢'],
                'contact_copy'       => ['Copy tooltip', 'Copy', 'کۆپی'],
                'contact_open_maps'  => ['Map — open button', 'Open in Google Maps', 'کردنەوە لە گووگڵ ماپ'],
                'contact_map_addr'   => ['Map — address', 'Nº 592 (2nd Floor), Italian Village 2, Erbil, Kurdistan Region of Iraq', 'ژمارە ٥٩٢ (نهۆمی ٢)، گوندی ئیتاڵی ٢، هەولێر، هەرێمی کوردستانی عێراق', 'area'],
                'contact_visits'     => ['Map — visiting note', 'Visits are by appointment only', 'سەردانکردن تەنها بە ژوانی پێشوەختەیە'],
                'form_name'          => ['Form — name label', 'Your Name', 'ناوت'],
                'form_email'         => ['Form — email label', 'Email Address', 'ناونیشانی ئیمەیڵ'],
                'form_project'       => ['Form — project label', 'Project Type', 'جۆری پڕۆژە'],
                'form_message'       => ['Form — message label', 'Tell us about your project', 'دەربارەی پڕۆژەکەت پێمان بڵێ'],
                'form_send'          => ['Form — send button', 'Send Message', 'ناردنی نامە'],
                'form_success'       => ['Form — success message', "✓ Message received — we'll be in touch soon.", '✓ نامەکەت گەیشت — بەم زووانە پەیوەندیت پێوە دەکەین.', 'area'],
            ],

            'Project overlay' => [
                'overlay_back'     => ['Back button', 'Back to Projects', 'گەڕانەوە بۆ پرۆژەکان'],
                'overlay_fullres'  => ['Full-resolution button', 'View Full Resolution Size', 'بینین بە قەبارەی تەواو'],
                'overlay_location' => ['Spec — Location', 'Location', 'شوێن'],
                'overlay_year'     => ['Spec — Year', 'Year', 'ساڵ'],
                'overlay_typology' => ['Spec — Typology', 'Typology', 'جۆر'],
                'overlay_plot'     => ['Spec — Plot Area', 'Plot Area', 'ڕووبەری زەوی'],
            ],

            'Footer' => [
                'footer_logo'    => ['Logo text', 'ARAM MIZURI', 'ئارام مزووری'],
                'footer_tagline' => ['Tagline', 'Architecture · Erbil · Kurdistan', 'تەڵارسازی · هەولێر · کوردستان'],
            ],
        ];
    }

    /**
     * Flat memoised map: key => ['label','en','ku','type','tab'].
     *
     * @return array<string, array{label:string,en:string,ku:string,type:string,tab:string}>
     */
    public static function map(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }

        $map = [];
        foreach (static::tabs() as $tab => $entries) {
            foreach ($entries as $key => $def) {
                $map[$key] = [
                    'label' => $def[0],
                    'en'    => $def[1],
                    'ku'    => $def[2],
                    'type'  => $def[3] ?? 'text',
                    'tab'   => $tab,
                ];
            }
        }

        return $map;
    }

    /** English value: {key}_en → legacy {key} → default. */
    public static function en(string $key, ?string $default = null): string
    {
        $default ??= static::map()[$key]['en'] ?? '';

        $v = SiteSetting::get("{$key}_en");
        if ($v !== null && $v !== '') {
            return (string) $v;
        }
        $legacy = SiteSetting::get($key);
        if ($legacy !== null && $legacy !== '') {
            return (string) $legacy;
        }

        return $default;
    }

    /** Kurdish value: {key}_ku → default. */
    public static function ku(string $key, ?string $default = null): string
    {
        $default ??= static::map()[$key]['ku'] ?? '';

        $v = SiteSetting::get("{$key}_ku");

        return ($v !== null && $v !== '') ? (string) $v : $default;
    }
}
