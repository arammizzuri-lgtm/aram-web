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
                'nav_menu'       => ['Menu — its name for screen readers', 'Menu', 'مێنیو'],
                'nav_index'      => ['Menu — the Index button', 'Index', 'پێڕست'],
                'nav_index_title'=> ['Menu — the index\'s title', 'Sheet index', 'پێڕستی پەڕەکان'],
                'nav_close'      => ['Menu — close', 'Close', 'داخستن'],
                'nav_here'       => ['Menu — the page you are on', 'You are here', 'تۆ لێرەیت'],
                'nav_today'      => ['Menu — the heritage sheet\'s landmark, which changes daily', 'Landmark of the day', 'شوێنەواری ئەمڕۆ'],
                'nav_studio'     => ['Menu — title block: studio', 'Studio', 'ستۆدیۆ'],
                'nav_place'      => ['Menu — title block: place', 'Erbil · Kurdistan', 'هەولێر · کوردستان'],
                'nav_language'   => ['Menu — title block: language', 'Language', 'زمان'],
                'ui_scroll'      => ['Scroll hint', 'Scroll', 'بۆ خوارەوە'],
                'ui_clock_city'  => ['Hero clock — city', 'Erbil', 'هەولێر'],
                'stat_projects'  => ['Stat label — Projects', 'Projects', 'پرۆژە'],
                'stat_clients'   => ['Stat label — Clients', 'Clients', 'کڕیار'],
                'stat_cities'    => ['Stat label — Cities', 'Cities', 'شار'],
                'stat_countries' => ['Stat label — Countries', 'Countries', 'وڵات'],
                'stat_area'      => ['Stat label — Area', 'm² Designed', 'م² دیزاینکراو'],
            ],

            // What Google shows for the homepage, and what a shared link to it
            // previews with. Project pages make their own from the project.
            'Search & sharing' => [
                'seo_title'       => ['Page title (browser tab, Google, link previews)', 'Aram Mizuri Architecture | Erbil, Kurdistan', 'تەڵارسازیی ئارام مزووری | هەولێر، کوردستان'],
                'seo_description' => ["Description (Google, link previews — about 150 characters)", "Aram Mizuri Architecture — an architecture and interior design studio in Erbil, Kurdistan Region: homes, interiors, restaurants, offices and commercial buildings.", "تەڵارسازیی ئارام مزووری — ستۆدیۆیەکی تەڵارسازی و دیزاینی ناوەوە لە هەولێر، هەرێمی کوردستان: خانوو، دیزاینی ناوەوە، چێشتخانە، ئۆفیس و بینای بازرگانی.", 'area'],
            ],

            'Hero' => [
                'hero_eyebrow'      =>['Eyebrow', 'Erbil · Kurdistan · Est. 2022', 'هەولێر · کوردستان · دامەزراوە ٢٠٢٢'],
                'hero_title_line1'  => ['Title — line 1 (append " by …" for the gold sub)', 'Architecture by', 'تەڵارسازی لەلایەن', 'area'],
                'hero_title_line2'  => ['Title — line 2 (gold accent)', 'Aram Mizuri', 'ئارام مزووری'],
                'hero_sub'          => ['Subtitle', 'Shaping the built environment of Erbil — and beyond.', 'شێوەدانی ژینگەی بنیاتنراوی هەولێر — و دوورتر.', 'area'],
                'hero_cta'          => ['Explore button', 'Explore Projects', 'پرۆژەکان ببینە'],
            ],

            'The Record (stats band)' => [
                'statbar_label' => ['Section label', 'The Record', 'تۆمارەکە'],
                'statbar_title' => ['Title (allows <em>)', 'Strength, <em>in numbers.</em>', 'هێز، <em>بە ژمارە.</em>', 'area'],
                'statbar_sub'   => ["Subtitle", "The work so far, in figures — designed across the Kurdistan Region, and in Spain.", "کارەکانمان تا ئێستا، بە ژمارە — دیزاینکراو لە سەرانسەری هەرێمی کوردستان و ئیسپانیا.", 'area'],
                'statbar_swipe' => ['Mobile swipe hint', 'swipe for more figures', 'ڕایبکێشە بۆ ژمارەی زیاتر'],
            ],

            'Map' => [
                'map_label'        => ['Section label', 'Where We Build', 'کوێ دەبینین'],
                'map_title'        => ['Title (allows <br>)', 'Projects Across<br>Kurdistan & Beyond', 'پرۆژەکان بەسەر<br>کوردستان و دوورتردا', 'area'],
                'map_view_project' => ['Popup — view project', 'View Full Project', 'بینینی پڕۆژەی تەواو'],
                'map_grow'         => ['“Watch the studio grow” button', 'Watch the studio grow', 'گەشەی ستۆدیۆکە ببینە'],
                'map_grow_stop'    => ['…while it plays', 'Stop', 'ڕاگرتن'],
                'map_grow_again'   => ['…once it has played', 'Watch again', 'دووبارە ببینە'],
                'map_grow_beyond'  => ['…a project far away lands ({place} = where)', 'And beyond: {place}', 'و دوورتر: {place}'],
            ],

            'Clients' => [
                'clients_trusted' => ['Section label', 'Trusted By', 'پشتیوانیكراو'],
                'clients_title'   => ['Title', 'Clients & Partners', 'کڕیار و هاوبەشەکان'],
                'clients_hint'    => ['Hint', 'click a logo to see all partners', 'کرتە لە لۆگۆیەک بکە بۆ بینینی هەموو هاوبەشەکان'],
            ],

            // Shown only once at least one quote is published (Client quotes).
            'Client quotes' => [
                'quotes_label' => ['Section label', 'In Their Words', 'بە وتەی خۆیان'],
                'quotes_title' => ['Title', 'What our clients say', 'کڕیارەکانمان چی دەڵێن'],
                'google_badge' => ['Google badge — reviews ({n})', '{n} reviews on Google', '{n} هەڵسەنگاندن لە گووگڵ'],
                'google_badge_read' => ['Google badge — link', 'Read them', 'بیانخوێنەوە'],
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
                'pg_all'             => ['Link to the projects page (beside the title)', 'All projects', 'هەموو پرۆژەکان'],
                'pg_empty'           => ['Empty state', 'No projects match your criteria.', 'هیچ پرۆژەیەک لەگەڵ داواکارییەکەت ناگونجێت.'],
            ],

            'Services' => [
                'services_label' => ['Section label', 'What We Do', 'ئەوەی دەیکەین'],
                'services_title' => ['Title', 'Services', 'خزمەتگوزارییەکان'],
                'services_see'   => ['Card — link to its projects ({n} = how many)', 'See {n} projects', 'بینینی {n} پرۆژە'],
                'services_see_one' => ['Card — link to its only project', 'See the project', 'بینینی پرۆژەکە'],
                'services_ask'   => ['Card — link to the contact form (no projects to show yet)', 'Ask us about it', 'پرسیارمان لێ بکە'],
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
                // the home page's heritage strip; its label, title, figures and "read more" are the Kurdistan heritage texts
                'heritage_home_desc' => ['Introduction', 'Kurdistan holds some of the oldest architecture on earth. The studio has researched, mapped and drawn its landmarks, across all four parts of the country — the ground our own work stands on.', 'کوردستان هەندێک لە کۆنترین تەلارسازییەکانی سەر زەوی لەخۆ دەگرێت. ئۆفیسەکە شوێنەوارەکانی لە هەر چوار پارچەی وڵاتدا لێکۆڵیوەتەوە، خستوویەتییە سەر نەخشە و کێشاویەتی — ئەو زەمینەی کارەکانی خۆمانی لەسەر وەستاوە.', 'area'],
                'heritage_home_cta'  => ['Button', 'Explore the heritage map', 'نەخشەی میرات بگەڕێ'],
            ],

            'About' => [
                'about_label'            => ['Section label', 'About the Practice', 'دەربارەی ئۆفیسەکە'],
                'about_heading'          => ['Heading (allows <em>/<br>)', 'A Kurdish architectural voice', 'دەنگێکی تەڵارسازیی کوردی', 'area'],
                'about_bio_1'            => ["Biography — paragraph 1", "Aram Mizuri is an architect and co-founder of Dehat Construction & Architecture, based in Erbil, the capital of the Kurdistan Region of Iraq. He graduated in architecture from Tishk International University in 2023 and is a member of the Kurdistan Engineers Union. His work draws on the spatial heritage of Kurdistan — from the Erbil Citadel to the mountain villages of Amadiya — as much as on classical and contemporary architecture, to design buildings and interiors that are contemporary in execution and shaped around the people who use them.", "ئارام مزووری تەڵارساز و هاودامەزرێنەری دێهات بۆ بیناسازی و تەڵارسازییە، لە هەولێر، پایتەختی هەرێمی کوردستانی عێراق، نیشتەجێیە. لە ساڵی ٢٠٢٣ لە زانکۆی نێودەوڵەتی تیشک لە بەشی تەڵارسازی دەرچووە و ئەندامی سەندیکای ئەندازیارانی کوردستانە. کارەکەی هەم سەرچاوە لە میراتی شوێنی کوردستان دەگرێت — لە قەڵای هەولێرەوە تا گوندە شاخاوییەکانی ئامێدی — و هەم لە تەڵارسازیی کلاسیک و هاوچەرخ، بۆ دیزاینکردنی بینا و ناوەوەیەک کە لە جێبەجێکردندا هاوچەرخن و بەپێی ئەو کەسانە شێوە دەگرن کە تێیاندا دەژین و کار دەکەن.", 'area'],
                'about_bio_2'            => ["Biography — paragraph 2", "Through his own practice and Dehat Construction & Architecture, he has designed villas and homes, interiors, restaurants and cafés, offices, clinics and showrooms across the Kurdistan Region — several of them built by Dehat — and his first project abroad, a weekend house in Madrid. He believes architecture should keep the identity of a place while making it better to live in.", "لە ڕێگەی ستۆدیۆکەی خۆی و دێهات بۆ بیناسازی و تەڵارسازییەوە، ڤێلا و خانوو، دیزاینی ناوەوە، چێشتخانە و کافێ، ئۆفیس، کلینیک و پێشانگای لە سەرانسەری هەرێمی کوردستان دیزاین کردووە — هەندێکیان دێهات دروستی کردوون — هەروەها یەکەم پرۆژەی لە دەرەوەی کوردستان، خانوویەکی کۆتایی هەفتە لە مەدرید. باوەڕی وایە تەڵارسازی دەبێت ناسنامەی شوێن بپارێزێت و لەهەمانکاتدا ژیان تێیدا باشتر بکات.", 'area'],
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
                'form_project'       => ['Form — project type label', 'Project Type', 'جۆری پڕۆژە'],
                'form_phone'         => ['Form — phone label', 'Phone / WhatsApp', 'تەلەفۆن / واتسئاپ'],
                'form_reach'         => ['Form — note under email & phone', 'An email or a phone number is enough — whichever suits you.', 'ئیمەیڵ یان ژمارەی تەلەفۆن بەسە — هەرکامیان بۆت گونجاوە.', 'area'],
                'form_city'          => ['Form — city label', 'City / site', 'شار / شوێنی زەوی'],
                'form_plot'          => ['Form — plot area label', 'Plot area (m²)', 'ڕووبەری زەوی (م²)'],
                'form_budget'        => ['Form — budget label', 'Estimated budget', 'بودجەی خەمڵێنراو'],
                'form_timeline'      => ['Form — timeline label', 'When would you like to start?', 'کەی دەتەوێت دەست پێبکەیت؟'],
                'form_choose'        => ['Form — empty choice', 'Choose…', 'هەڵبژێرە…'],
                'form_more'          => ['Form — "more details" toggle', 'Add project details (optional)', 'زانیاریی پرۆژە زیاد بکە (ئارەزوومەندانە)'],
                'form_about'         => ['Form — "about this project" chip', 'About', 'دەربارەی'],
                'wa_label'           => ['WhatsApp — label', 'WhatsApp', 'واتسئاپ'],
                'wa_cta'             => ['WhatsApp — button', 'Chat on WhatsApp', 'لە واتسئاپ قسە بکە'],
                'wa_text'            => ['WhatsApp — first message', "Hello Aram Mizuri Architecture, I'd like to talk about a project.", 'سڵاو، دەمەوێت دەربارەی پرۆژەیەک قسە بکەم.', 'area'],
                'wa_text_project'    => ['WhatsApp — first message from a project ({project} = its name)', 'Hello, I saw “{project}” on your website and would like to talk about something similar.', 'سڵاو، پرۆژەی «{project}»م لە ماڵپەڕەکەتان بینی و دەمەوێت دەربارەی شتێکی هاوشێوە قسە بکەم.', 'area'],
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

            // A project's own page (/projects/…). Its spec labels are the
            // overlay's above, so the two always name things the same way.
            'Project page' => [
                'pp_status'       => ['Spec — Status', 'Status', 'دۆخ'],
                'pp_category'     => ['Spec — Category', 'Category', 'پۆل'],
                'pp_materials'    => ['Materials heading', 'Materials', 'کەرەستەکان'],
                'pp_material_more' => ['A material other projects share — hint ({n} = how many projects)', 'See all {n} projects built with it', 'بینینی هەموو ئەو {n} پرۆژەیەی بەم کەرەستەیە دروستکراون'],
                'pp_voice_title'  => ['The architect’s voice note — heading', 'Hear the architect', 'گوێ لە تەڵارسازەکە بگرە'],
                'pp_voice_by'     => ['The architect’s voice note — who', 'Aram Mizuri on this project', 'ئارام مزووری دەربارەی ئەم پرۆژەیە'],
                'pp_voice_in_ku'  => ['…when the note is in Kurdish (on the English page)', 'in Kurdish', 'بە کوردی'],
                'pp_voice_in_en'  => ['…when the note is in English (on the Kurdish page)', 'in English', 'بە ئینگلیزی'],
                'pp_voice_play'   => ['…play button (for screen readers)', 'Listen to the architect', 'گوێ لە تەڵارسازەکە بگرە'],
                'pp_notes'        => ['Photo viewer — show or hide the notes on a photograph', 'Notes', 'تێبینییەکان'],
                'pp_room_more'    => ['Photo viewer — the room, and all photos of it ({room}, {n})', '{room} — see all {n}', '{room} — هەموو {n} وێنەکە ببینە'],
                'pp_on_map'       => ['Map heading', 'On the map', 'لەسەر نەخشە'],
                'pp_share'        => ['Share button', 'Share', 'هاوبەشکردن'],
                'pp_copied'       => ['Share — link copied', 'Link copied', 'بەستەرەکە کۆپی کرا'],
                'pp_cta_title'    => ['Call to action — title', 'Planning something similar?', 'پلانی شتێکی هاوشێوەت هەیە؟'],
                'pp_cta_text'     => ['Call to action — text', "Tell us about your site and what you have in mind — we'll get back to you.", 'دەربارەی شوێنەکەت و ئەوەی لە بیرتدایە پێمان بڵێ — بەم زووانە وەڵامت دەدەینەوە.', 'area'],
                'pp_cta_button'   => ['Call to action — button', 'Start a project', 'دەستپێکردنی پرۆژە'],
                'pp_prev'         => ['Previous project', 'Previous', 'پێشوو'],
                'pp_next'         => ['Next project', 'Next', 'دواتر'],
                'pp_more'         => ['More projects heading', 'More projects', 'پرۆژەی زیاتر'],
                'pp_compare'      => ['Render vs. built — heading', 'From render to reality', 'لە دیزاینەوە بۆ ڕاستی'],
                'pp_render'       => ['Render vs. built — render label', 'Render', 'دیزاین'],
                'pp_built'        => ['Render vs. built — photo label', 'Built', 'بنیاتنراو'],
                'pp_compare_hint' => ['Render vs. built — hint', 'Drag to compare', 'ڕایبکێشە بۆ بەراوردکردن'],
                'pp_progress'     => ['Progress — heading', 'Construction progress', 'پێشکەوتنی بنیاتنان'],
                'pp_photos_all'   => ['Opening — the button into every photograph ({n} = how many)', 'Photographs ({n})', 'وێنەکان ({n})'],
                'pp_updated'      => ['Progress — "updated" note', 'Updated', 'نوێکرایەوە'],
                'pp_film'         => ['Film & tours — heading', 'Film & 360° tours', 'ڤیدیۆ و گەشتی ٣٦٠°'],
                'pp_play'         => ['Film — play button', 'Play the film', 'ڤیدیۆکە لێبدە'],
                'pp_tour'         => ['360° tour — start button', 'Start the 360° tour', 'گەشتی ٣٦٠° دەستپێبکە'],
            ],

            // The heart on every project, the "Saved" list it builds (/saved)
            // and the share buttons. {project} is a project's name, {url} the
            // link being shared.
            'Saved & sharing' => [
                'saved_nav'          => ['Top bar — saved projects', 'Saved', 'پاشەکەوتکراو'],
                'save_add'           => ['Heart — save', 'Save', 'پاشەکەوتکردن'],
                'save_added'         => ['Heart — saved', 'Saved', 'پاشەکەوتکرا'],
                'saved_label'        => ['Saved page — label', 'Saved projects', 'پرۆژە پاشەکەوتکراوەکان'],
                'saved_title'        => ['Saved page — title (your own list)', 'Your shortlist', 'لیستە کورتەکەت'],
                'saved_title_shared' => ['Saved page — title (a list someone shared)', 'Projects shared with you', 'ئەو پرۆژانەی بۆت نێردراون'],
                'saved_intro'        => ['Saved page — introduction', 'Tap the heart on any project to keep it here. Send the list to the studio, or share it with your family on WhatsApp.', 'دڵ لەسەر هەر پرۆژەیەک دابگرە بۆ ئەوەی لێرە بمێنێتەوە. لیستەکە بۆ ستۆدیۆ بنێرە، یان لە واتسئاپ لەگەڵ خێزانەکەت هاوبەشی بکە.', 'area'],
                'saved_intro_shared' => ['Saved page — introduction (a list someone shared)', 'Someone picked these projects by Aram Mizuri Architecture for you.', 'کەسێک ئەم پرۆژانەی تەڵارسازیی ئارام مزووری بۆ تۆ هەڵبژاردووە.', 'area'],
                'saved_empty'        => ['Saved page — nothing saved', 'Nothing saved yet — tap the heart on a project you like and it will wait for you here.', 'هێشتا هیچت پاشەکەوت نەکردووە — دڵ لەسەر ئەو پرۆژەیە دابگرە کە بەدڵتە و لێرە چاوەڕێت دەکات.', 'area'],
                'saved_browse'       => ['Saved page — browse button', 'Browse projects', 'سەیری پرۆژەکان بکە'],
                'saved_ask'          => ['Saved page — ask button', 'Ask the studio about these', 'دەربارەی ئەمانە لە ستۆدیۆ بپرسە'],
                'saved_keep'         => ['Saved page — keep a shared list', 'Save these to my list', 'ئەمانە بۆ لیستەکەی خۆم پاشەکەوت بکە'],
                'saved_kept'         => ['Saved page — shared list kept', 'Added to your list', 'زیادکرا بۆ لیستەکەت'],
                'saved_wa_text'      => ['WhatsApp — sharing a saved list ({url})', 'A few projects by Aram Mizuri Architecture I like: {url}', 'چەند پرۆژەیەکی تەڵارسازیی ئارام مزووری کە بەدڵمن: {url}', 'area'],
                'share_wa'           => ['Share — on WhatsApp', 'Share on WhatsApp', 'لە واتسئاپ هاوبەشی بکە'],
                'share_wa_text'      => ['WhatsApp — sharing a project ({project}, {url})', 'Have a look at “{project}” by Aram Mizuri Architecture: {url}', 'سەیری «{project}» بکە، کارێکی تەڵارسازیی ئارام مزووری: {url}', 'area'],
                'share_copy'         => ['Share — copy link (computers, where there is no share menu)', 'Copy link', 'کۆپیکردنی بەستەر'],
                'form_saved'         => ['Enquiry form — saved projects chip', 'Saved', 'پاشەکەوتکراو'],
                'story_btn'          => ['Project page — make a picture for WhatsApp Status', 'Picture for Status', 'وێنە بۆ ستاتس'],
                'story_share'        => ['Status picture — send it (phones)', 'Share', 'هاوبەشکردن'],
                'story_hint'         => ['Status picture — how to post it', 'Share it and choose “My status” on WhatsApp — or save the picture and post it anywhere.', 'هاوبەشی بکە و لە واتسئاپ «ستاتسی من» هەڵبژێرە — یان وێنەکە پاشەکەوت بکە و لە هەر شوێنێک بڵاوی بکەرەوە.', 'area'],
            ],

            // The rooms a photograph can show (Portfolio → Photos by room), and the
            // page that shows each room from every project (/photos).
            'Rooms' => [
                'room_facade'   => ['Room — façade', 'Façade & exterior', 'ڕووکار و دەرەوە'],
                'room_entrance' => ['Room — entrance', 'Entrance & stairs', 'دەروازە و پلیکانە'],
                'room_living'   => ['Room — living room', 'Living room', 'هۆڵی دانیشتن'],
                'room_guest'    => ['Room — guest room', 'Guest room (diwan)', 'دیوەخان'],
                'room_kitchen'  => ['Room — kitchen', 'Kitchen', 'چێشتخانە'],
                'room_dining'   => ['Room — dining', 'Dining', 'ژووری نانخواردن'],
                'room_bedroom'  => ['Room — bedroom', 'Bedroom', 'ژووری نووستن'],
                'room_bathroom' => ['Room — bathroom', 'Bathroom', 'حەمام'],
                'room_office'   => ['Room — office', 'Office & workplace', 'ئۆفیس و شوێنی کار'],
                'room_outdoor'  => ['Room — garden', 'Garden & pool', 'باخچە و مەلەوانگە'],
                'ph_label'      => ['Photos page — label', 'Room by room', 'ژوور بە ژوور'],
                'ph_title'      => ['Photos page — title', 'Every room we have made', 'هەموو ئەو ژوورانەی دروستمان کردوون'],
                'ph_intro'      => ['Photos page — introduction', 'Kitchens, guest rooms, façades — the same room from every project, side by side.', 'چێشتخانە، دیوەخان، ڕووکار — هەمان ژوور لە هەموو پرۆژەکانەوە، لە تەنیشت یەکتر.', 'area'],
                'ph_all'        => ['Photos page — every room', 'All rooms', 'هەموو ژوورەکان'],
                'ph_count'      => ['Photos page — count ({n})', '{n} photographs', '{n} وێنە'],
                'ph_open'       => ['Photos page — to the project', 'See the project', 'پرۆژەکە ببینە'],
                'ph_empty'      => ['Photos page — before any photograph has its room', 'The photographs are being sorted room by room — in the meantime, see every project.', 'وێنەکان ژوور بە ژوور ڕێکدەخرێن — تا ئەو کاتە، هەموو پرۆژەکان ببینە.', 'area'],
                'ph_link'       => ['Projects page — link to the photos page', 'Browse room by room', 'ژوور بە ژوور بیبینە'],
            ],

            // "Draw it for us": the drawing paper on the projects page's blank
            // sheet and on the enquiry form (sketch.js).
            'Sketch' => [
                'sk_title'    => ['Title', 'Draw it for us', 'بۆمان بکێشە'],
                'sk_hint'     => ['Under the title', 'Your plot, a plan, the house you imagine — a rough sketch says a lot.', 'زەوییەکەت، نەخشەیەک، ئەو خانووەی لە خەیاڵتدایە — وێنەیەکی خێرا زۆر شت دەڵێت.', 'area'],
                'sk_start'    => ['On the empty paper', 'Draw here — with a finger, a pen or the mouse', 'لێرە بکێشە — بە پەنجە، قەڵەم یان ماوس'],
                'sk_close'    => ['Close button (for screen readers)', 'Close', 'داخستن'],
                'sk_pen'      => ['Tool — pencil', 'Pencil', 'قەڵەم'],
                'sk_line'     => ['Tool — straight line', 'Straight line', 'هێڵی ڕاست'],
                'sk_undo'     => ['Undo', 'Undo', 'گەڕانەوە'],
                'sk_clear'    => ['Clear', 'Clear', 'سڕینەوە'],
                'sk_send'     => ['Send with a message (projects page)', 'Send with a message', 'لەگەڵ نامەیەک بینێرە'],
                'sk_wa'       => ['Send on WhatsApp (projects page)', 'Send on WhatsApp', 'لە واتسئاپ بینێرە'],
                'sk_attach'   => ['Attach (enquiry form)', 'Attach to my message', 'بیخەرە سەر نامەکەم'],
                'sk_sending'  => ['While it is kept', 'Keeping your sketch…', 'وێنەکەت هەڵدەگیرێت…'],
                'sk_failed'   => ['If it could not be kept', 'It could not be sent — please try again.', 'نەنێردرا — تکایە دووبارە هەوڵ بدەرەوە.'],
                'sk_empty'    => ['Sent empty', 'Draw something first.', 'سەرەتا شتێک بکێشە.'],
                'sk_wa_text'  => ['WhatsApp message with the sketch ({url})', 'Hello Aram Mizuri Architecture, here is a sketch of what I have in mind: {url}', 'سڵاو، ئەمە وێنەیەکی ئەوەیە کە لە بیرمدایە: {url}', 'area'],
                'sk_open'     => ['Enquiry form — open the paper', 'Add a sketch', 'وێنەیەکی دەستی زیاد بکە'],
                'sk_attached' => ['Enquiry form — the sketch attached', 'Your sketch', 'وێنە دەستییەکەت'],
                'sk_cta'      => ['Projects page — button by “Start a project”', 'Draw it for us', 'بۆمان بکێشە'],
            ],

            // The poster for a building site's fence (Projects → a project →
            // Site board), printed in both languages at once.
            'Site boards' => [
                'board_building' => ['Headline, while the building goes up', 'Being built here', 'لێرە بنیات دەنرێت'],
                'board_scan'     => ['Beside the QR code', 'Scan to see the design', 'سکانی بکە بۆ بینینی دیزاینەکە'],
                'board_by'       => ['Along the foot', 'Designed by Aram Mizuri Architecture', 'دیزاینی تەڵارسازیی ئارام مزووری'],
            ],

            // The holiday cards (/card) — the occasions themselves, their
            // greetings and dates, are under Clients → Holiday cards.
            'Holiday cards' => [
                'card_label'     => ['Page label', 'Holiday card', 'کارتی پیرۆزبایی'],
                'card_lead'      => ['Introduction', 'Make the card with your name and send it to your family and friends on WhatsApp.', 'کارتەکە بە ناوی خۆت دروست بکە و لە واتسئاپ بۆ خێزان و هاوڕێکانتی بنێرە.', 'area'],
                'card_name'      => ['Your name — label', 'Your name', 'ناوی تۆ'],
                'card_name_ph'   => ['Your name — example', 'Shilan & family', 'شیلان و خێزانەکەی'],
                'card_from'      => ['On the card — who it is from ({name})', '— {name}', '— {name}'],
                'card_lang'      => ['Card language — label', 'The card in', 'زمانی کارتەکە'],
                'card_photo'     => ['Photograph — label', 'Photograph', 'وێنە'],
                'card_send'      => ['Send button (phones)', 'Send on WhatsApp', 'لە واتسئاپ بینێرە'],
                'card_save'      => ['Save button', 'Save the picture', 'وێنەکە پاشەکەوت بکە'],
                'card_private'   => ['Note under the buttons', 'Your name stays on your phone — it is not sent to us.', 'ناوەکەت لە مۆبایلەکەت دەمێنێتەوە — بۆ ئێمە نانێردرێت.'],
                'card_noscript'  => ['Without JavaScript', 'This page needs JavaScript to make the card.', 'ئەم پەڕەیە بۆ دروستکردنی کارتەکە پێویستی بە جاڤاسکریپتە.'],
                'card_preview'   => ['Shown to you when previewing outside its dates', 'Preview — visitors see this card only between its dates.', 'پێشبینین — سەردانکەران تەنها لە نێوان بەروارەکانیدا ئەم کارتە دەبینن.'],
                'card_brand_sub' => ['On the card — after the studio’s name', 'Architecture', 'تەڵارسازی'],
                'card_band_text' => ['Homepage band — text', 'Send a card with your name to your family and friends — made with one of our projects.', 'کارتێک بە ناوی خۆت بۆ خێزان و هاوڕێکانت بنێرە — بە یەکێک لە پرۆژەکانمان.', 'area'],
                'card_band_btn'  => ['Homepage band — button', 'Make the card', 'کارتەکە دروست بکە'],
            ],

            // The cost estimator (/estimate) and its band on the homepage. The
            // rates themselves are under Website settings → Cost estimator.
            'Cost estimate' => [
                'est_nav'          => ['Top bar / footer link', 'Cost estimate', 'خەمڵاندنی تێچوون'],
                'est_label'        => ['Page label', 'Cost estimate', 'خەمڵاندنی تێچوون'],
                'est_title'        => ['Title', 'What will your house cost?', 'خانووەکەت چەندی تێدەچێت؟'],
                'est_intro'        => ['Introduction', 'A first idea in a minute: tell us about your plot and the house you have in mind. It is an estimate, not a quote — send it to us and we will give you a proper one.', 'لە یەک خولەکدا بیرۆکەیەکی سەرەتایی: دەربارەی زەوییەکەت و ئەو خانووەی لە بیرتدایە پێمان بڵێ. ئەمە خەمڵاندنە، نرخ نییە — بۆمانی بنێرە و نرخێکی ڕاستەقینەت پێدەدەین.', 'area'],
                'est_plot'         => ['Plot area', 'Plot area', 'ڕووبەری زەوی'],
                'est_floors'       => ['Floors', 'Floors above ground', 'نهۆمەکانی سەر زەوی'],
                'est_built'        => ['Built area', 'Built area', 'ڕووبەری بنیاتنراو'],
                'est_built_auto'   => ['Built area — worked out ({coverage})', 'Worked out from your plot: {coverage}% of it on each floor.', 'لە زەوییەکەتەوە دەرهێنراوە: {coverage}%ی لە هەر نهۆمێکدا.', 'area'],
                'est_built_own'    => ['Built area — enter my own', 'I know my built area', 'ڕووبەری بنیاتنراوی خۆم دەزانم'],
                'est_basement'     => ['Basement', 'Basement', 'ژێرزەمین'],
                'est_bedrooms'     => ['Bedrooms', 'Bedrooms', 'ژووری نوستن'],
                'est_bathrooms'    => ['Bathrooms', 'Bathrooms', 'حەمام و ئاودەست'],
                'est_finish'       => ['Finish level', 'Finish', 'ئاستی تەواوکاری'],
                'est_pool'         => ['Swimming pool', 'Swimming pool', 'مەلەوانگە'],
                'est_per_m2'       => ['Finish — per m² ({low}, {high})', '{low} – {high} per m²', '{low} – {high} بۆ هەر م²'],
                'est_go'           => ['Button (without JavaScript)', 'Work it out', 'هەژماری بکە'],
                'est_result'       => ['Result — heading', 'Your estimate', 'خەمڵاندنەکەت'],
                'est_empty'        => ['Result — before a plot is given', 'Enter your plot area and the estimate appears here.', 'ڕووبەری زەوییەکەت بنووسە و خەمڵاندنەکە لێرە دەردەکەوێت.', 'area'],
                'est_building'     => ['Result — building cost', 'To build', 'بۆ بنیاتنان'],
                'est_fee'          => ['Result — design fee', 'Our design fee', 'کرێی دیزاینی ئێمە'],
                'est_fee_percent'  => ['Result — fee as % ({low}, {high})', '{low} – {high}% of the building cost', '{low} – {high}%ی تێچووی بنیاتنان'],
                'est_fee_m2'       => ['Result — fee per m² ({low}, {high})', '{low} – {high} per m² built', '{low} – {high} بۆ هەر م²ی بنیاتنراو'],
                'est_line_building' => ['Breakdown — building ({area}, {finish})', '{area} m² built, {finish} finish', '{area} م² بنیاتنراو، تەواوکاریی {finish}'],
                'est_line_basement' => ['Breakdown — basement ({area})', '{area} m² basement', '{area} م² ژێرزەمین'],
                'est_line_bathrooms' => ['Breakdown — bathrooms ({n})', '{n} bathrooms', '{n} حەمام'],
                'est_line_pool'    => ['Breakdown — pool', 'Swimming pool', 'مەلەوانگە'],
                'est_house_empty'  => ['Drawing — before a plot is given', 'Your house is drawn here as you answer', 'خانووەکەت لێرە دەکێشرێت کاتێک وەڵام دەدەیتەوە'],
                'est_house_floors' => ['Drawing — floors ({n})', '{n} floors', '{n} نهۆم'],
                'est_house_floor'  => ['Drawing — one floor', '1 floor', '1 نهۆم'],
                'est_house_plot'   => ['Drawing — the plot ({n} m²)', '{n} m² plot', 'زەوی {n} م²'],
                'est_house_built'  => ['Drawing — built area ({n} m²)', '{n} m² built', '{n} م² بنیاتنراو'],
                'est_like_label'   => ['Plot like yours — label', 'From the studio', 'لە کارەکانی ستۆدیۆ'],
                'est_like_title'   => ['Plot like yours — title', 'Built on a plot like yours', 'لەسەر زەوییەکی وەک هی تۆ'],
                'est_like_text'    => ['Plot like yours — text ({plot} = their plot)', 'Our projects on plots close to {plot} m².', 'پرۆژەکانمان لەسەر زەویی نزیک لە {plot} م².'],
                'est_like_plot'    => ['Plot like yours — each project’s plot ({area})', '{area} m² plot', 'زەوی {area} م²'],
                'est_note'         => ['Result — small print', 'A first estimate, not a quote: the real figure depends on the site, the design and prices at the time.', 'خەمڵاندنێکی سەرەتاییە، نرخ نییە: ژمارە ڕاستەقینەکە بەندە بە شوێن، دیزاین و نرخەکانی ئەو کاتە.', 'area'],
                // "Get this estimate as a PDF": a way to reach them, then the estimate on one sheet
                'est_pdf'          => ['PDF — button', 'Get this estimate as a PDF', 'ئەم خەمڵاندنە وەک PDF وەربگرە'],
                'est_pdf_title'    => ['PDF — heading', 'Your name and a phone number or e-mail: your PDF opens, and the studio keeps a copy to go through it with you.', 'ناوت و ژمارەی تەلەفۆن یان ئیمەیڵ: PDFـەکەت دەکرێتەوە، و ستۆدیۆ کۆپییەک هەڵدەگرێت بۆ ئەوەی لەگەڵت تاوتوێی بکات.'],
                'est_pdf_question' => ['PDF — question (optional)', 'A question for us? (optional)', 'پرسیارێکت بۆمان هەیە؟ (ئارەزوومەندانە)'],
                'est_pdf_btn'      => ['PDF — submit', 'Get the PDF', 'PDFـەکە وەربگرە'],
                'est_pdf_ready'    => ['PDF — ready', '✓ Ready — the studio has a copy too.', '✓ ئامادەیە — ستۆدیۆش کۆپییەکی هەیە.'],
                'est_pdf_open'     => ['PDF — open it', 'Open your PDF', 'PDFـەکەت بکەرەوە'],
                'est_sheet_for'    => ['Sheet — prepared for {name}', 'Prepared for {name}', 'ئامادەکراو بۆ {name}'],
                'est_sheet_date'   => ['Sheet — date', 'Date', 'بەروار'],
                'est_sheet_answers' => ['Sheet — the answers', 'Your answers', 'وەڵامەکانت'],
                'est_sheet_talk'   => ['Sheet — talk to the studio', 'Talk to the studio', 'لەگەڵ ستۆدیۆ قسە بکە'],
                'est_sheet_save'   => ['Sheet — save button', 'Save as PDF', 'وەک PDF پاشەکەوتی بکە'],
                'est_sheet_back'   => ['Sheet — back', 'Back to the estimate', 'گەڕانەوە بۆ خەمڵاندن'],
                'est_sheet_hint'   => ['Sheet — how to save', 'In the print window, choose "Save as PDF".', 'لە پەنجەرەی چاپکردندا "Save as PDF" هەڵبژێرە.'],
                'est_band_title'   => ['Homepage band — title', 'What will your house cost?', 'خانووەکەت چەندی تێدەچێت؟'],
                'est_band_text'    => ['Homepage band — text', 'Get a first estimate in a minute — then talk to us about the real thing.', 'لە یەک خولەکدا خەمڵاندنێکی سەرەتایی وەربگرە — پاشان دەربارەی ڕاستییەکەی لەگەڵمان قسە بکە.', 'area'],
                'est_band_btn'     => ['Homepage band — button', 'Estimate it', 'خەمڵاندنی بکە'],
            ],

            // A client's private room (/room/…), and the WhatsApp messages the
            // studio sends from the admin to invite them. {name} is the client,
            // {project} the project, {url} the room's link, {pin} its PIN,
            // {date} a date, {paid} / {total} amounts.
            'Client rooms' => [
                'room_label'          => ['Room — label', 'Your project', 'پرۆژەکەت'],
                'room_welcome'        => ['Room — greeting ({name})', 'Welcome, {name}', 'بەخێربێیت، {name}'],
                'room_pin_title'      => ['PIN — title', 'Enter your PIN', 'ژمارە نهێنییەکەت بنووسە'],
                'room_pin_text'       => ['PIN — explanation', 'The studio sent you a 4-digit PIN with this link. Your phone will remember it for 90 days.', 'ستۆدیۆ ژمارەیەکی نهێنیی ٤ ژمارەیی لەگەڵ ئەم بەستەرە بۆت ناردووە. مۆبایلەکەت بۆ ماوەی ٩٠ ڕۆژ لەبیری دەکات.', 'area'],
                'room_pin_open'       => ['PIN — button', 'Open my room', 'ژوورەکەم بکەرەوە'],
                'room_pin_wrong'      => ['PIN — wrong', 'That PIN is not right — please try again.', 'ئەو ژمارە نهێنییە دروست نییە — تکایە دووبارە هەوڵبدەرەوە.'],
                'room_pin_locked'     => ['PIN — too many tries', 'Too many tries — please wait 15 minutes, or ask the studio.', 'هەوڵی زۆر درا — تکایە ١٥ خولەک چاوەڕێ بکە، یان لە ستۆدیۆ بپرسە.', 'area'],
                'room_closed'         => ['Room — closed', 'This room is closed. Please get in touch with the studio.', 'ئەم ژوورە داخراوە. تکایە پەیوەندی بە ستۆدیۆوە بکە.', 'area'],
                'room_nav_progress'   => ['Section — progress', 'Progress', 'پێشکەوتن'],
                'room_nav_drawings'   => ['Section — drawings', 'Drawings', 'نەخشەکان'],
                'room_nav_payments'   => ['Section — payments', 'Payments', 'پارەدانەکان'],
                'room_private'        => ['Progress — private badge', 'Only in your room', 'تەنها لە ژوورەکەتدا'],
                'room_no_updates'     => ['Progress — none yet', 'No updates yet — the studio will post them here as the work goes on.', 'هێشتا هیچ نوێکارییەک نییە — ستۆدیۆ لێرە بڵاویان دەکاتەوە بەپێی پێشچوونی کارەکە.', 'area'],
                'room_comment_ph'     => ['Comments — box', 'Write a comment or a question…', 'سەرنج یان پرسیارێک بنووسە…'],
                'room_comment_send'   => ['Comments — send', 'Send', 'ناردن'],
                'room_you'            => ['Comments — the client', 'You', 'تۆ'],
                'room_studio'         => ['Comments — the studio', 'Studio', 'ستۆدیۆ'],
                'room_comment_sent'   => ['Comments — sent', 'Sent — the studio has been told.', 'نێردرا — ستۆدیۆ ئاگادار کرایەوە.'],
                'room_no_drawings'    => ['Drawings — none yet', 'No drawings yet.', 'هێشتا هیچ نەخشەیەک نییە.'],
                'room_open'           => ['Drawings — open', 'Open', 'کردنەوە'],
                'room_download'       => ['Drawings — download', 'Download', 'داگرتن'],
                'room_waiting'        => ['Drawings — waiting', 'Waiting for your decision', 'چاوەڕێی بڕیاری تۆیە'],
                'room_approve'        => ['Drawings — approve', 'Approve', 'پەسەندی دەکەم'],
                'room_approve_sure'   => ['Drawings — approve, are you sure', 'Approve this drawing? The studio will go ahead with it.', 'ئەم نەخشەیە پەسەند دەکەیت؟ ستۆدیۆ لەسەری بەردەوام دەبێت.', 'area'],
                'room_changes'        => ['Drawings — ask for changes', 'Ask for changes', 'داوای گۆڕانکاری دەکەم'],
                'room_changes_ph'     => ['Drawings — what to change', 'What would you like changed?', 'دەتەوێت چی بگۆڕدرێت؟'],
                'room_changes_send'   => ['Drawings — send the changes', 'Send to the studio', 'بۆ ستۆدیۆ بنێرە'],
                'room_approved'       => ['Drawings — approved ({date})', 'Approved on {date}', 'پەسەندکرا لە {date}'],
                'room_changes_asked'  => ['Drawings — changes asked ({date})', 'Changes asked on {date}', 'داوای گۆڕانکاری کرا لە {date}'],
                'room_decided'        => ['Drawings — decision sent', 'Thank you — the studio has been told.', 'سوپاس — ستۆدیۆ ئاگادار کرایەوە.'],
                'room_pins_open'      => ['Drawings — comment on the drawing', 'Comment on the drawing', 'سەرنج لەسەر نەخشەکە'],
                'room_pins_hint'      => ['Drawings — viewer, how to pin', 'Tap the drawing where you have a comment.', 'دەست لەو شوێنەی نەخشەکە بدە کە سەرنجت لەسەری هەیە.'],
                'room_pins_ph'        => ['Drawings — viewer, comment box', 'Your comment on this spot…', 'سەرنجەکەت لەسەر ئەم شوێنە…'],
                'room_pins_loading'   => ['Drawings — viewer, opening', 'Opening the drawing…', 'نەخشەکە دەکرێتەوە…'],
                'room_pins_error'     => ['Drawings — viewer, not sent', 'Not sent — please try again.', 'نەنێردرا — تکایە دووبارە هەوڵبدەرەوە.'],
                'room_nav_changes'    => ['Section — changes', 'Changes', 'گۆڕانکارییەکان'],
                'room_no_changes'     => ['Changes — none yet', 'No changes yet. If you would like something changed, ask here — the studio will tell you what it costs and how many days it adds.', 'هێشتا هیچ گۆڕانکارییەک نییە. ئەگەر دەتەوێت شتێک بگۆڕدرێت، لێرە داوای بکە — ستۆدیۆ پێت دەڵێت تێچووەکەی چەندە و چەند ڕۆژ زیاد دەکات.', 'area'],
                'room_change_ask'     => ['Changes — ask for one', 'Ask for a change', 'داوای گۆڕانکارییەک بکە'],
                'room_change_details_ph' => ['Changes — more details', 'More details (optional)', 'وردەکاریی زیاتر (ئارەزوومەندانە)'],
                'room_change_sent'    => ['Changes — asked', 'Sent — the studio will reply with a price.', 'نێردرا — ستۆدیۆ نرخەکەیت بۆ دەنێرێت.'],
                'room_change_wait'    => ['Changes — waiting for a price', 'Waiting for the studio’s price', 'چاوەڕێی نرخی ستۆدیۆیە'],
                'room_change_by_you'  => ['Changes — asked by the client', 'You asked', 'داواکاریی تۆ'],
                'room_change_days'    => ['Changes — days it adds ({days}: +7)', '{days} days', '{days} ڕۆژ'],
                'room_change_free'    => ['Changes — no cost', 'No extra cost', 'بێ تێچووی زیادە'],
                'room_change_accept'  => ['Changes — accept', 'Accept', 'قبوڵی دەکەم'],
                'room_change_accept_sure' => ['Changes — accept, are you sure', 'Accept this change? The studio will go ahead with it, and its price is added to your payments as a stage of its own.', 'ئەم گۆڕانکارییە قبوڵ دەکەیت؟ ستۆدیۆ دەستی پێدەکات، و نرخەکەی وەک قۆناغێکی جیا دەخرێتە سەر پارەدانەکانت.', 'area'],
                'room_change_decline' => ['Changes — decline', 'Decline', 'ڕەتی دەکەمەوە'],
                'room_change_decline_ph' => ['Changes — why declined', 'Why? So the studio knows.', 'بۆچی؟ با ستۆدیۆ بزانێت.'],
                'room_change_accepted' => ['Changes — accepted ({date})', 'Accepted on {date}', 'قبوڵکرا لە {date}'],
                'room_change_declined' => ['Changes — declined ({date})', 'Declined on {date}', 'ڕەتکرایەوە لە {date}'],
                'room_change_in_payments' => ['Changes — added to payments', 'Added to your payments', 'خرایە سەر پارەدانەکانت'],
                'room_change_pay_label' => ['Changes — its payment stage ({title})', 'Change: {title}', 'گۆڕانکاری: {title}'],
                'room_nav_choices'    => ['Section — finish choices', 'Choices', 'هەڵبژاردنەکان'],
                'room_no_choices'     => ['Choices — none yet', 'Nothing to choose yet — when the studio needs you to pick a finish (a worktop, tiles, a colour), the options will be here.', 'هێشتا هیچ شتێک نییە بۆ هەڵبژاردن — کاتێک ستۆدیۆ پێویستی بە هەڵبژاردنی تۆ بێت (ڕووی مەتبەخ، کاشی، ڕەنگێک)، بژاردەکان لێرە دەبن.', 'area'],
                'room_choice_summary' => ['Choices — your picks', 'Your choices', 'هەڵبژاردنەکانت'],
                'room_choice_included' => ['Choices — no extra cost', 'Included', 'لەناو نرخەکەدایە'],
                'room_choice_supplier' => ['Choices — supplier', 'Supplier', 'دابینکەر'],
                'room_choice_note_ph' => ['Choices — note with the pick', 'A note with your choice (optional)', 'تێبینییەک لەگەڵ هەڵبژاردنەکەت (ئارەزوومەندانە)'],
                'room_choice_confirm' => ['Choices — confirm', 'Confirm my choice', 'هەڵبژاردنەکەم پشتڕاست دەکەمەوە'],
                'room_choice_sure'    => ['Choices — confirm, are you sure', 'Is this your final choice? The studio will go ahead with it; only the studio can reopen it.', 'ئەمە هەڵبژاردنی کۆتاییتە؟ ستۆدیۆ لەسەری بەردەوام دەبێت؛ تەنها ستۆدیۆ دەتوانێت دووبارە بیکاتەوە.', 'area'],
                'room_choice_chosen'  => ['Choices — chosen ({date})', 'Chosen on {date}', 'هەڵبژێردرا لە {date}'],
                'room_choice_moved'   => ['Choices — edited meanwhile', 'The studio has just updated these options — please look at them again.', 'ستۆدیۆ ئێستا ئەم بژاردانەی نوێ کردەوە — تکایە جارێکی تر سەیریان بکەوە.', 'area'],
                'room_nav_timeline'   => ['Section — the timeline of stages', 'Timeline', 'قۆناغەکان'],
                'room_now'            => ['Timeline — the stage under way, at the top ({stage})', 'Now: {stage}', 'ئێستا: {stage}'],
                'room_stage_planned'  => ['Timeline — planned dates ({dates})', 'Planned: {dates}', 'پلان: {dates}'],
                'room_stage_started'  => ['Timeline — started ({date})', 'Started {date}', 'دەستی پێکرد {date}'],
                'room_stage_finished' => ['Timeline — finished ({date})', 'Finished {date}', 'تەواو بوو {date}'],
                'room_stage_late'     => ['Timeline — late', 'Late', 'دواکەوتووە'],
                'room_stage_pushed'   => ['Timeline — days added by changes ({days} is +N)', '{days} days from changes', '{days} ڕۆژ بەهۆی گۆڕانکارییەکان'],
                'room_nav_docs'       => ['Section — documents', 'Documents', 'بەڵگەنامەکان'],
                'room_no_docs'        => ['Documents — none yet', 'No documents yet — the contract, permits and invoices will be kept here.', 'هێشتا هیچ بەڵگەنامەیەک نییە — گرێبەست و مۆڵەت و پسوولەکان لێرە هەڵدەگیرێن.', 'area'],
                'room_doc_contract'   => ['Documents — group: contract', 'Contract', 'گرێبەست'],
                'room_doc_permits'    => ['Documents — group: permits', 'Permits', 'مۆڵەتەکان'],
                'room_doc_invoices'   => ['Documents — group: invoices', 'Invoices', 'پسوولەکان'],
                'room_doc_other'      => ['Documents — group: other', 'Other', 'هی تر'],
                'room_doc_from_you'   => ['Documents — added by the client', 'From you', 'لە تۆوە'],
                'room_doc_expires'    => ['Documents — expires ({date})', 'Expires {date}', 'بەسەردەچێت لە {date}'],
                'room_doc_expired'    => ['Documents — expired ({date})', 'Expired {date}', 'بەسەرچوو لە {date}'],
                'room_doc_please_read' => ['Documents — please read', 'Please read it and confirm', 'تکایە بیخوێنەوە و پشتڕاستی بکەرەوە'],
                'room_doc_read'       => ['Documents — I have read it', 'I have read it', 'خوێندمەوە'],
                'room_doc_read_sure'  => ['Documents — read, are you sure', 'Confirm that you have read this document?', 'پشتڕاستی دەکەیتەوە کە ئەم بەڵگەنامەیەت خوێندۆتەوە؟', 'area'],
                'room_doc_read_on'    => ['Documents — read ({date})', 'Read on {date}', 'خوێندتەوە لە {date}'],
                'room_doc_add'        => ['Documents — add one', 'Add a document', 'بەڵگەنامەیەک زیاد بکە'],
                'room_doc_title_ph'   => ['Documents — what it is', 'What is it? (e.g. the title deed)', 'چییە؟ (بۆ نموونە تاپۆ)'],
                'room_doc_file_hint'  => ['Documents — file types', 'PDF or a photo, up to 20 MB', 'PDF یان وێنە، تا ٢٠ مێگابایت'],
                'room_doc_sent'       => ['Documents — added', 'Thank you — the studio has your document.', 'سوپاس — بەڵگەنامەکەت گەیشتە ستۆدیۆ.'],
                'room_nav_meetings'   => ['Section — meeting notes', 'Meetings', 'کۆبوونەوەکان'],
                'room_no_meetings'    => ['Meetings — none yet', 'No meeting notes yet — after each meeting the studio writes down what was decided, for you to confirm.', 'هێشتا هیچ تێبینیی کۆبوونەوەیەک نییە — دوای هەر کۆبوونەوەیەک ستۆدیۆ ئەوەی بڕیاری لەسەر درا دەینووسێتەوە، بۆ ئەوەی تۆ پشتڕاستی بکەیتەوە.', 'area'],
                'room_meeting'        => ['Meetings — a meeting with no title', 'Meeting', 'کۆبوونەوە'],
                'room_meeting_who'    => ['Meetings — who came ({who})', 'Present: {who}', 'ئامادەبووان: {who}'],
                'room_meeting_wait'   => ['Meetings — please confirm', 'Please confirm these notes', 'تکایە ئەم تێبینیانە پشتڕاست بکەرەوە'],
                'room_meeting_agree'  => ['Meetings — agree', 'I agree', 'ڕازیم'],
                'room_meeting_agree_sure' => ['Meetings — agree, are you sure', 'Agree that these notes are what was decided?', 'ڕازیت کە ئەم تێبینیانە ئەوەن کە بڕیاری لەسەر درا؟', 'area'],
                'room_meeting_object' => ['Meetings — something is not right', 'Something is not right', 'شتێک دروست نییە'],
                'room_meeting_object_ph' => ['Meetings — what is not right', 'What is not right?', 'چی دروست نییە؟'],
                'room_meeting_agreed' => ['Meetings — agreed ({date})', 'Agreed on {date}', 'ڕازیبوویت لە {date}'],
                'room_meeting_objected' => ['Meetings — objected ({date})', 'On {date} you said something is not right — the studio will correct the notes.', 'لە {date} گوتت شتێک دروست نییە — ستۆدیۆ تێبینییەکان ڕاست دەکاتەوە.', 'area'],
                'room_meeting_moved'  => ['Meetings — corrected meanwhile', 'The studio has just corrected these notes — please read them again.', 'ستۆدیۆ ئێستا ئەم تێبینیانەی ڕاست کردەوە — تکایە جارێکی تر بیانخوێنەوە.', 'area'],
                'room_change_moved'   => ['Changes — edited meanwhile', 'The studio has just updated this change — please look at it again.', 'ستۆدیۆ ئێستا ئەم گۆڕانکارییەی نوێ کردەوە — تکایە جارێکی تر سەیری بکەوە.', 'area'],
                'room_paid_of'        => ['Payments — paid of total ({paid}, {total})', '{paid} paid of {total}', '{paid} دراوە لە کۆی {total}'],
                'room_pay_paid'       => ['Payments — paid ({date})', 'Paid on {date}', 'دراوە لە {date}'],
                'room_pay_due'        => ['Payments — due ({date})', 'Due on {date}', 'وادەکەی {date}'],
                'room_pay_due_open'   => ['Payments — due, no date', 'To come', 'دواتر'],
                'room_pay_overdue'    => ['Payments — overdue ({date})', 'Overdue since {date}', 'دواکەوتووە لە {date}ەوە'],
                'room_pay_receipt'    => ['Payments — receipt', 'Receipt', 'پسوولە'],
                'room_no_payments'    => ['Payments — none yet', 'No payment stages yet.', 'هێشتا هیچ قۆناغێکی پارەدان نییە.'],
                'room_wa'             => ['Room — WhatsApp button', 'Message the studio', 'نامە بۆ ستۆدیۆ بنێرە'],
                'room_wa_text'        => ['Room — WhatsApp first message ({name}, {project})', 'Hello, this is {name}, about {project}.', 'سڵاو، من {name}م، دەربارەی {project}.'],
                'room_invite_text'    => ['Admin → client: the link ({name}, {project}, {url})', "Hello {name}, this is your private room for {project} — progress, drawings and payments, all in one place: {url}\nI will send you its PIN in a separate message.", "سڵاو {name}، ئەمە ژووری تایبەتی تۆیە بۆ {project} — پێشکەوتن، نەخشەکان و پارەدانەکان، هەمووی لە یەک شوێن: {url}\nژمارە نهێنییەکەی لە نامەیەکی جیادا بۆت دەنێرم.", 'area'],
                'room_pin_msg'        => ['Admin → client: the PIN ({pin})', 'The PIN for your room: {pin}', 'ژمارە نهێنیی ژوورەکەت: {pin}'],
                'room_news_text'      => ['Admin → client: something new ({name}, {project}, {url})', 'Hello {name}, there is something new in your room for {project}: {url}', 'سڵاو {name}، شتێکی نوێ لە ژوورەکەتدا هەیە بۆ {project}: {url}', 'area'],
            ],

            // Every project on one page (/projects), opened from the
            // "Projects" heading on the homepage.
            'Projects page' => [
                'pj_label'  => ['Page label', 'All Work', 'هەموو کارەکان'],
                'pj_title'  => ['Title', 'Projects', 'پرۆژەکان'],
                'pj_intro'  => ["Introduction", "Homes, interiors, restaurants, workplaces and commercial buildings across Kurdistan — the projects we have designed, and those we have seen built.", "خانوو، دیزاینی ناوەوە، چێشتخانە، شوێنی کار و بینای بازرگانی لە سەرانسەری کوردستان — ئەو پرۆژانەی دیزاینمان کردوون، و ئەوانەی بینیومانن دروست دەکرێن.", 'area'],
                'pj_count'  => ['Count ({n} = how many are shown)', '{n} projects', '{n} پرۆژە'],
                'pj_count_one' => ['Count — just one', '1 project', '1 پرۆژە'],
                'pj_search' => ['Search box', 'Search projects…', 'گەڕان بۆ پرۆژەکان…'],
                'pj_clear'  => ['Empty state — show everything', 'Show all projects', 'هەموو پرۆژەکان پیشان بدە'],
                'pj_legend' => ['Categories — heading of the legend', 'Legend', 'نیشانەکان'],
                'pj_view'   => ['Views — label', 'View', 'شێوەی پیشاندان'],
                'pj_index'  => ['View — the list year by year', 'Index', 'پێڕست'],
                'pj_sheets' => ['View — the pictures, as drawing sheets', 'Sheets', 'پەڕەکان'],
                'pj_sheet'  => ['Title block — number', 'Sheet', 'پەڕە'],
                'pj_project' => ['Title block — name', 'Project', 'پرۆژە'],
                'pj_type'   => ['Title block — category', 'Type', 'جۆر'],
                'pj_place'  => ['Title block — place', 'Place', 'شوێن'],
                'pj_year'   => ['Title block — year', 'Year', 'ساڵ'],
                'pj_status' => ['Title block — status', 'Status', 'دۆخ'],
                'pj_undated' => ['Index — projects without a year', 'Undated', 'بێ ساڵ'],
                'pj_yours_title' => ['Blank sheet at the end — title', 'Your project', 'پرۆژەکەی تۆ'],
                'pj_yours_text' => ['Blank sheet at the end — text', 'This sheet is still blank — let’s draw it together.', 'ئەم پەڕەیە هێشتا بەتاڵە — با پێکەوە بیکێشین.', 'area'],
                'pj_disc_view' => ['Disc beside the pointer, over a project', 'View', 'بینین'],
                'pj_disc_start' => ['Disc beside the pointer, over the blank sheet', 'Start', 'دەستپێک'],
            ],

            // What an address that leads nowhere shows: a mistyped one, an old
            // link, a project taken down since it was shared.
            'Page not found' => [
                'nf_label'  => ['Label', 'Error 404', 'هەڵەی ٤٠٤'],
                'nf_title'  => ['Title', 'This sheet is missing from the set.', 'ئەم پەڕەیە لەناو نەخشەکاندا نییە.'],
                'nf_text'   => ['Text', 'The page you followed has moved, or was never drawn. Search the projects, or start from one of these.', 'ئەو پەڕەیەی بەدوایدا هاتیت گوازراوەتەوە، یان هەرگیز نەکێشراوە. لە پرۆژەکاندا بگەڕێ، یان لە یەکێک لەمانەوە دەست پێبکە.', 'area'],
                'nf_search' => ['Search button', 'Search', 'گەڕان'],
                'nf_all'    => ['Button — every project', 'See every project', 'هەموو پرۆژەکان ببینە'],
                'nf_picks'  => ['Heading over four projects', 'From the studio', 'لە کارەکانی ستۆدیۆ'],
            ],

            // The careers page (/careers). Positions themselves are managed
            // under Careers → Positions; this is the wording around them.
            'Careers' => [
                'careers_link'      => ['Footer link', 'Careers', 'هەلی کار'],
                'careers_home'      => ['Back link', 'Home', 'پەڕەی سەرەکی'],
                'careers_label'     => ['Page label', 'Careers', 'هەلی کار'],
                'careers_title'     => ['Title', 'Build with us', 'لەگەڵمان بنیات بنێ'],
                'careers_intro'     => ["Introduction", "We are a studio in Erbil designing homes, interiors and commercial spaces across Kurdistan. If you care about good architecture and want to learn by building it, we would like to hear from you.", "ئێمە ستۆدیۆیەکین لە هەولێر، خانوو، دیزاینی ناوەوە و شوێنی بازرگانی لە سەرانسەری کوردستان دیزاین دەکەین. ئەگەر گرنگی بە تەڵارسازیی باش دەدەیت و دەتەوێت لە ڕێگەی دروستکردنییەوە فێری ببیت، خۆشحاڵ دەبین گوێمان لێت بێت.", 'area'],
                'careers_open'      => ['Open positions heading', 'Open positions', 'هەلی کاری بەردەست'],
                'careers_none'      => ['No positions note', 'There are no open positions right now — but good people are always welcome. Send us an open application below.', 'لە ئێستادا هیچ هەلی کارێکی بەردەست نییە — بەڵام کەسانی باش هەمیشە بەخێرهاتنیان دەکرێت. لە خوارەوە داواکارییەکی گشتیمان بۆ بنێرە.', 'area'],
                'careers_closes'    => ['Closing date label', 'Apply by', 'دوا وادە'],
                'careers_more'      => ['Card — read more', 'Read more', 'زیاتر بخوێنەوە'],
                'careers_apply_role' => ['Card — apply button', 'Apply for this role', 'داوای ئەم کارە بکە'],
                'careers_form'      => ['Form heading', 'Apply', 'داواکاری پێشکەش بکە'],
                'careers_position'  => ['Form — position', 'Position', 'پۆست'],
                'careers_general'   => ['Form — open application', 'Open application', 'داواکاریی گشتی'],
                'careers_portfolio' => ['Form — portfolio link', 'Portfolio link (optional)', 'بەستەری پۆرتفۆلیۆ (ئارەزوومەندانە)'],
                'careers_message'   => ['Form — message', 'Tell us about yourself', 'دەربارەی خۆت پێمان بڵێ'],
                'careers_cv'        => ['Form — CV', 'Your CV — PDF, up to 8 MB', 'سیڤییەکەت — PDF، تا ٨ مێگابایت'],
                'careers_cv_choose' => ['Form — choose file', 'Choose a PDF…', 'فایلێکی PDF هەڵبژێرە…'],
                'careers_send'      => ['Form — send button', 'Send application', 'ناردنی داواکاری'],
                'careers_success'   => ['Form — received', "✓ Application received — thank you. We read every one, and will be in touch if there is a fit.", '✓ داواکارییەکەت گەیشت — سوپاس. هەموویان دەخوێنینەوە و ئەگەر گونجاو بوو پەیوەندیت پێوە دەکەین.', 'area'],
                'careers_cv_needed' => ['Form — CV missing or not a PDF', 'Please attach your CV as a PDF.', 'تکایە سیڤییەکەت وەک PDF هاوپێچ بکە.'],
                'careers_cv_big'    => ['Form — CV too large', 'That file is larger than 8 MB.', 'ئەو فایلە لە ٨ مێگابایت گەورەترە.'],

                // what search engines show — written the way job seekers search
                'careers_seo_title' => ['Search title (Google tab and result)', 'Architecture jobs in Erbil — open applications for architects', 'هەلی کاری تەلارسازی لە هەولێر — داواکاریی گشتی بۆ ئەندازیارانی تەلارساز'],
                'careers_seo_desc'  => ['Search description', 'Architect, interior designer, fresh graduate or architecture student looking for work in Erbil or Kurdistan? Aram Mizuri Architecture welcomes open applications all year — send your CV and portfolio.', 'ئەندازیاری تەلارساز، دیزاینەری ناوەوە، دەرچووی نوێ یان خوێندکاری تەلارسازیت و بەدوای کاردا دەگەڕێیت لە هەولێر یان کوردستان؟ ستۆدیۆی ئارام مزووری بە درێژایی ساڵ داواکاریی گشتی وەردەگرێت — سیڤی و پۆرتفۆلیۆکەت بنێرە.', 'area'],

                // the open-application section, shown whether or not positions are open
                'careers_oa_label'  => ['Open applications — label', 'Open applications · all year', 'داواکاریی گشتی · بە درێژایی ساڵ'],
                'careers_oa_title'  => ['Open applications — heading', 'Always looking for creative new minds', 'هەمیشە بەدوای بیری نوێ و داهێنەردا دەگەڕێین'],
                'careers_oa_text'   => ['Open applications — text', 'You do not need to wait for a position to be posted. If you are an architect or designer who wants to grow with a young studio in Erbil, send us your CV and portfolio. We keep every application and come back to it when the right project starts.', 'پێویست ناکات چاوەڕێی بڵاوکردنەوەی هەلی کار بکەیت. ئەگەر ئەندازیاری تەلارساز یان دیزاینەریت و دەتەوێت لەگەڵ ستۆدیۆیەکی گەنج لە هەولێر گەشە بکەیت، سیڤی و پۆرتفۆلیۆکەتمان بۆ بنێرە. هەموو داواکارییەک هەڵدەگرین و کاتێک پرۆژەی گونجاو دەست پێدەکات دەگەڕێینەوە سەری.', 'area'],
                'careers_who1_t'    => ['Who — 1 title', 'Architects', 'ئەندازیارانی تەلارساز'],
                'careers_who1_d'    => ['Who — 1 line', 'Design, drawings and site work — at any stage of your career.', 'دیزاین، نەخشە و کاری سەر سایت — لە هەر قۆناغێکی کارەکەتدا بیت.'],
                'careers_who2_t'    => ['Who — 2 title', 'Interior designers', 'دیزاینەرانی ناوەوە'],
                'careers_who2_d'    => ['Who — 2 line', 'Homes, cafés and restaurants, from the first idea to the last detail.', 'ماڵ، کافێ و چێشتخانە، لە یەکەم بیرۆکەوە تا دوایین وردەکاری.'],
                'careers_who3_t'    => ['Who — 3 title', 'Fresh graduates', 'دەرچووانی نوێ'],
                'careers_who3_d'    => ['Who — 3 line', 'A strong portfolio matters more to us than years of experience.', 'پۆرتفۆلیۆیەکی بەهێز لە ساڵانی ئەزموون گرنگترە بۆمان.'],
                'careers_who4_t'    => ['Who — 4 title', 'Architecture students', 'خوێندکارانی تەلارسازی'],
                'careers_who4_d'    => ['Who — 4 line', 'Looking for training? Tell us your university and when you are free.', 'بەدوای ڕاهێناندا دەگەڕێیت؟ زانکۆکەت و کاتی بەتاڵیت پێمان بڵێ.'],
                'careers_send_what' => ['What to send', 'Send: your CV as a PDF, a link to your portfolio, and a few lines on why you want to work with us.', 'بنێرە: سیڤییەکەت وەک PDF، بەستەری پۆرتفۆلیۆکەت، و چەند دێڕێک دەربارەی ئەوەی بۆچی دەتەوێت لەگەڵمان کار بکەیت.', 'area'],
                'careers_oa_btn'    => ['Open applications — button', 'Send an open application', 'داواکارییەکی گشتی بنێرە'],

                // questions job seekers ask — also read by Google and AI assistants
                'careers_faq_title' => ['Questions — heading', 'Questions', 'پرسیارەکان'],
                'careers_faq1_q'    => ['Question 1', 'Do you accept applications from fresh graduates?', 'داواکاری لە دەرچووانی نوێ وەردەگرن؟'],
                'careers_faq1_a'    => ['Answer 1', 'Yes. Show us your best student projects — we look at how you think, not only at your experience.', 'بەڵێ. پیشانمان بدە باشترین پرۆژەکانی زانکۆت — سەیری شێوازی بیرکردنەوەت دەکەین، نەک تەنها ئەزموونت.', 'area'],
                'careers_faq2_q'    => ['Question 2', 'Can architecture students apply for training?', 'خوێندکارانی تەلارسازی دەتوانن داوای ڕاهێنان بکەن؟'],
                'careers_faq2_a'    => ['Answer 2', 'Yes. Send an open application, say that it is for training, and tell us your university, your year and the months you are available.', 'بەڵێ. داواکارییەکی گشتی بنێرە، بڵێ بۆ ڕاهێنانە، و زانکۆ، قۆناغ و ئەو مانگانەی کە بەتاڵیت پێمان بڵێ.', 'area'],
                'careers_faq3_q'    => ['Question 3', 'What should my portfolio include?', 'پۆرتفۆلیۆکەم چی تێدابێت؟'],
                'careers_faq3_a'    => ['Answer 3', 'Five to ten of your best projects — university work counts. Sketches, drawings, models and renders, with a line on your part in each. A PDF link or a website both work.', 'پێنج بۆ دە لە باشترین پرۆژەکانت — کاری زانکۆش حسابە. سکێچ، نەخشە، ماکێت و ڕێندەر، لەگەڵ دێڕێک دەربارەی بەشی خۆت لە هەر یەکێکیان. بەستەری PDF یان ماڵپەڕ هەردووکیان دەبن.', 'area'],
                'careers_faq4_q'    => ['Question 4', 'What happens after I apply?', 'دوای ناردنی داواکاری چی ڕوودەدات؟'],
                'careers_faq4_a'    => ['Answer 4', 'We read every application ourselves. If there is a fit, we contact you by email or phone to meet at the studio in Erbil.', 'هەموو داواکارییەک خۆمان دەیخوێنینەوە. ئەگەر گونجاو بوو، بە ئیمەیڵ یان تەلەفۆن پەیوەندیت پێوە دەکەین بۆ دیدارێک لە ستۆدیۆ لە هەولێر.', 'area'],
            ],

            // The architect's own page (/aram-mizuri): who he is, as a person,
            // for visitors and for search engines and AI assistants, which
            // read it as the authoritative source on him. A list (one item per
            // line) left empty is not shown, and nothing is said about it to
            // search engines either — only facts written here are published.
            'Architect page' => [
                'arch_link'            => ['Link to the page (About section, footer)', 'More about Aram Mizuri', 'زیاتر دەربارەی ئارام مزووری'],
                'arch_label'           => ['Page label', 'The architect', 'تەڵارساز'],
                'arch_name'            => ['Name — the page heading', 'Aram Mizuri', 'ئارام مزووری'],
                'arch_role'            => ['Role, under the name', 'Architect · Founder of Aram Mizuri Architecture · Co-founder of Dehat Construction and Architecture', 'تەڵارساز · دامەزرێنەری تەڵارسازیی ئارام مزووری · هاودامەزرێنەری دێهات بۆ بیناسازی و تەڵارسازی'],
                'arch_based'           => ['Where he works — beside the name', 'Erbil, Kurdistan Region', 'هەولێر، هەرێمی کوردستان'],
                'arch_membership'      => ['Professional membership — one line (About page, footer); left empty, it is not shown', 'Member of the Kurdistan Engineers Union · No. 23900 · since 2023', 'ئەندامی سەندیکای ئەندازیارانی کوردستان · ژمارە ٢٣٩٠٠ · لە ٢٠٢٣ەوە'],
                'arch_membership_title' => ['Professional membership — heading', 'Membership', 'ئەندامێتی'],
                'arch_figures'         =>['Key figures — one per line, "number | what it counts"', "14 | Projects completed as a student and freelance architect\n2023 | Bachelor of Architecture, Tishk International University\n2024 | Co-founded Dehat Construction and Architecture\n2 | Countries with his work — the Kurdistan Region and Spain", "١٤ | پرۆژەی تەواوکراو لە ماوەی خوێندن و کاری سەربەخۆدا\n٢٠٢٣ | بەکالۆریۆس لە تەڵارسازی، زانکۆی نێودەوڵەتی تیشک\n٢٠٢٤ | دامەزراندنی دێهات بۆ بیناسازی و تەڵارسازی\n٢ | وڵات کە کارەکانی تێدایە — هەرێمی کوردستان و ئیسپانیا", 'area'],
                'arch_story_title'     => ['Biography — heading', 'Profile', 'پرۆفایل'],
                'arch_timeline_title'  => ['Career — heading', 'Career', 'ڕێڕەوی کار'],
                'arch_timeline'        => ["Career — one step per line, \"when | role | where | what happened\" (oldest first)", "2012 – 2018 | Secondary school | Ankawa Typical Secondary School | Graduated, and set out to study architecture.\n2018 – 2023 | Bachelor of Architecture | Tishk International University, Erbil | Studied architecture and graduated in 2023.\n2023 | Member | Kurdistan Engineers Union | Registered as an architect — membership no. 23900.\n2023 – 2024 | Freelance architect | Erbil | Worked independently for a year; by then he had completed fourteen projects as a student and freelance architect.\nMay 2024 | Architect | Bryar Ali Studio | Took part in designing several of the studio's projects.\n2024 | First project abroad | Madrid Weekend House — Madrid, Spain | His first project beyond the borders of Kurdistan.\nJune 2024 | Co-founder | Dehat Construction and Architecture | Founded with a colleague as a design and construction firm. Together they have designed and built projects in cities across the Kurdistan Region, among them So Delicious Restaurant, 404 Cafe and the Kar Group camp restaurant.", "٢٠١٢ – ٢٠١٨ | خوێندنی ئامادەیی | ئامادەیی نموونەیی عەنکاوە | دەرچوو، و ڕێگای خوێندنی تەڵارسازیی گرتەبەر.\n٢٠١٨ – ٢٠٢٣ | بەکالۆریۆس لە تەڵارسازی | زانکۆی نێودەوڵەتی تیشک، هەولێر | تەڵارسازیی خوێند و لە ساڵی ٢٠٢٣ دەرچوو.\n٢٠٢٣ | ئەندام | سەندیکای ئەندازیارانی کوردستان | وەک تەڵارساز تۆمارکرا — ژمارەی ئەندامێتی ٢٣٩٠٠.\n٢٠٢٣ – ٢٠٢٤ | تەڵارسازی سەربەخۆ | هەولێر | ساڵێک بە سەربەخۆیی کاری کرد؛ تا ئەو کاتە چواردە پرۆژەی وەک خوێندکار و تەڵارسازی سەربەخۆ تەواو کردبوو.\nئایاری ٢٠٢٤ | تەڵارساز | ستۆدیۆی بریار عەلی | بەشداری لە دیزاینی چەند پرۆژەیەکی ستۆدیۆکەدا کرد.\n٢٠٢٤ | یەکەم پرۆژە لە دەرەوە | خانووی کۆتایی هەفتە لە مەدرید — ئیسپانیا | یەکەم پرۆژەی لە دەرەوەی سنوورەکانی کوردستان.\nحوزەیرانی ٢٠٢٤ | هاودامەزرێنەر | دێهات بۆ بیناسازی و تەڵارسازی | لەگەڵ هاوکارێکی وەک کۆمپانیایەکی دیزاین و بیناسازی دایمەزراند. پێکەوە چەندین پرۆژەیان لە شارەکانی هەرێمی کوردستان دیزاین و جێبەجێ کردووە، لەوانە چێشتخانەی So Delicious، کافێی 404 و چێشتخانەی کەمپی کار گروپ.", 'area'],
                'arch_firms'           => ['Firms he founded or co-founded besides the studio, one per line — not shown, read by search engines', 'Dehat Construction and Architecture', '', 'area'],
                'arch_seo_title'       => ['Page title (browser tab, Google)', 'Aram Mizuri — Architect in Erbil, Kurdistan', 'ئارام مزووری — تەڵارساز لە هەولێر، کوردستان'],
                'arch_seo_description' => ["Description (Google, link previews — about 150 characters)", "Aram Mizuri is an architect in Erbil and a member of the Kurdistan Engineers Union, designing homes, interiors, restaurants and offices across Kurdistan.", "ئارام مزووری تەڵارسازێکە لە هەولێر و ئەندامی سەندیکای ئەندازیارانی کوردستانە، خانوو، دیزاینی ناوەوە، چێشتخانە و ئۆفیس لە سەرانسەری کوردستان دیزاین دەکات.", 'area'],
                'arch_bio'             => ['Biography — paragraphs separated by a blank line (left empty, the two About paragraphs are used)', '', '', 'area'],
                'arch_record_title'    => ['Expertise and education — section heading', 'Expertise and education', 'شارەزایی و خوێندن'],
                'arch_expertise_title' =>['Areas of work — heading', 'Areas of work', 'بوارەکانی کار'],
                'arch_expertise'       => ['Areas of work — one per line', "Residential architecture\nInterior design\nCultural and civic buildings\nCommercial and hospitality design\nUrban design and master planning", "تەڵارسازیی نیشتەجێبوون\nدیزاینی ناوەوە\nبینای کولتووری و گشتی\nدیزاینی بازرگانی و میوانداری\nدیزاینی شاری و پلاندانانی گشتی", 'area'],
                'arch_education_title' => ['Education — heading', 'Education', 'خوێندن'],
                'arch_education'       => ['Education — one per line, e.g. "Bachelor of Architecture — Tishk International University, 2023"', "Bachelor of Architecture — Tishk International University, Erbil, 2023\nAnkawa Typical Secondary School, 2012 – 2018", "بەکالۆریۆس لە تەڵارسازی — زانکۆی نێودەوڵەتی تیشک، هەولێر، ٢٠٢٣\nئامادەیی نموونەیی عەنکاوە، ٢٠١٢ – ٢٠١٨", 'area'],
                'arch_awards_title'    => ['Awards — heading', 'Awards and recognition', 'خەڵات و ناسینەوە'],
                'arch_awards'          => ['Awards — one per line, e.g. "Winner, Best Residential Design — Kurdistan Architecture Awards, 2024"', '', '', 'area'],
                'arch_press_title'     => ['Publications and talks — heading', 'Publications and talks', 'بڵاوکراوە و وتار'],
                'arch_press'           => ['Publications and talks — one per line; a line may end with its web address', '', '', 'area'],
                'arch_projects_title'  => ['Selected projects — heading', 'Selected projects', 'پرۆژە هەڵبژێردراوەکان'],
                'arch_all_projects'    => ['Link to every project', 'See every project', 'هەموو پرۆژەکان ببینە'],
                'arch_cta'             => ['Contact button', 'Start your project with Aram Mizuri', 'پرۆژەکەت لەگەڵ ئارام مزووری دەست پێ بکە'],
                'arch_credit'          => ['On each project page — label before the name', 'Architect', 'تەڵارساز'],
                'arch_profiles'        => ['His own profiles, one web address per line (LinkedIn, Instagram, ArchDaily, Wikidata…) — not shown, read by search engines', '', '', 'area'],
                'arch_other_names'     => ['Other spellings of his name, one per line — not shown, read by search engines', "Aram Mizzuri\nAram Mizouri\nآرام مزوري", '', 'area'],
            ],

            // The build journal (/journal).
            'Journal' => [
                'journal_nav'     => ['Journal — menu and footer link', 'Journal', 'ڕۆژنامە'],
                'journal_label'   => ['Journal — page label', 'Build journal', 'ڕۆژنامەی بیناسازی'],
                'journal_title'   => ['Journal — title', 'From the site', 'لە شوێنی کارەوە'],
                'journal_intro'   => ['Journal — introduction', 'Our projects as they are built — the site step by step, from the first foundations to the handover of the keys.', 'پرۆژەکانمان لە کاتی دروستکردندا — شوێنی کار هەنگاو بە هەنگاو، لە یەکەم بناغەوە تا ڕادەستکردنی کلیلەکان.', 'area'],
                'journal_empty'   => ['Journal — no entries yet', 'The first entries are on their way.', 'یەکەم بابەتەکان بەڕێوەن.'],
                'journal_read'    => ['Journal — read an entry', 'Read', 'بخوێنەوە'],
                'journal_all'     => ['Journal — back to every entry', 'All entries', 'هەموو بابەتەکان'],
                'journal_project' => ['Journal — link to the project', 'See the project', 'پرۆژەکە ببینە'],
                'journal_more'    => ['Journal — more from the same build', 'More from this build', 'زیاتر لەم بیناسازییە'],
                'journal_in'      => ['Project page — link from a progress step to its journal entry', 'In the journal', 'لە ڕۆژنامەدا'],
            ],

            // Kurdistan's architectural heritage (/kurdistan-heritage). The
            // landmarks themselves are under Portfolio → Heritage landmarks.
            'Kurdistan heritage' => [
                'landmarks_nav'       => ['Footer link', "Kurdistan's architectural heritage", 'میراتی تەلارسازیی کوردستان'],
                'landmarks_label'     => ['Page label', 'Architectural heritage of Kurdistan', 'میراتی تەلارسازیی کوردستان'],
                'landmarks_title'     => ['Title', 'Eleven thousand years of building', 'یازدە هەزار ساڵ بیناسازی'],
                'landmarks_intro'     => ['Introduction', 'From the stone circles of Göbekli Tepe to the citadel of Erbil, Kurdistan — on the northern rim of Mesopotamia, one of the cradles of civilisation — holds some of the oldest architecture on earth. These are its landmarks across all four parts of Kurdistan, chosen and introduced by architect Aram Mizuri.', 'لە بازنە بەردینەکانی گرێ مرازانەوە تا قەڵای هەولێر، کوردستان — لە لێواری باکووری میزۆپۆتامیا، یەکێک لە لانکەکانی شارستانییەت — هەندێک لە کۆنترین تەلارسازییەکانی سەر زەوی لە خۆ دەگرێت. ئەمانە شوێنەوارە دیارەکانین لە هەر چوار پارچەی کوردستان، هەڵبژاردن و ناساندنی تەلارساز ئارام مزووری.', 'area'],
                'landmarks_count'     => ['Count — {n} landmarks, {u} UNESCO sites', '{n} landmarks · {u} UNESCO World Heritage Sites', '{n} شوێنەوار · {u} میراتی جیهانیی یونسکۆ'],
                'landmarks_all'       => ['Filter — everything', 'All', 'هەمووی'],
                'landmarks_by_era'    => ['Filter — by age', 'By age', 'بەپێی سەردەم'],
                'landmarks_by_region' => ['Filter — by part of Kurdistan', 'By region', 'بەپێی پارچە'],
                // what the landmarks are built of: the filter, the section under the map, the tags on each page
                'landmarks_by_material' => ['Filter — by material', 'By material', 'بەپێی کەرەستە'],
                'landmarks_built_of'    => ['Landmark page — built of', 'Built of', 'دروستکراوە لە'],
                'landmarks_mat_label'   => ['Materials — small label', 'Materials', 'کەرەستەکان'],
                'landmarks_mat_title'   => ['Materials — title', 'Built from the land', 'لە خاکی خۆیەوە بنیاتنراوە'],
                'landmarks_mat_intro'   => ['Materials — introduction', 'Every landmark here was built from what lay closest to hand: the stone of its own hill, the clay of its own plain. Choose a material to see where it was used.', 'هەر شوێنەوارێک لێرە لەو شتە دروستکراوە کە لە نزیکترین شوێندا هەبووە: بەردی گردەکەی خۆی، قوڕی دەشتەکەی خۆی. کەرەستەیەک هەڵبژێرە بۆ ئەوەی ببینیت لە کوێ بەکارهاتووە.', 'area'],
                'landmarks_mat_show'    => ['Materials — button', 'Show on the map', 'لەسەر نەخشەکە پیشانی بدە'],
                'landmarks_mat_limestone' => ['Material — limestone', 'Limestone', 'بەردی کلسی'],
                'landmarks_mat_limestone_note' => ['Material — limestone, why here', 'The foothills of the Taurus and the Zagros are limestone: easy to quarry, soft enough to carve, and hard enough to have kept the pillars of Göbekli Tepe for eleven thousand years.', 'بناری زنجیرەچیاکانی تۆرۆس و زاگرۆس بەردی کلسییە: هەڵکەندنی ئاسانە، بۆ نەخشاندن نەرمە، و ئەوەندە ڕەقە کە ستوونەکانی گرێ مرازانی یازدە هەزار ساڵ پاراستووە.', 'area'],
                'landmarks_mat_basalt'  => ['Material — basalt', 'Basalt', 'بازاڵت'],
                'landmarks_mat_basalt_note' => ['Material — basalt, why here', 'Black volcanic stone. Amed stands on the lava that Karacadağ poured out; at Tell Halaf and Ain Dara it was kept for gods, lions and the faces of walls.', 'بەردێکی ڕەشی بورکانی. ئامەد لەسەر ئەو بەردە وەستاوە کە بورکانی قەرەجەداغ ڕشتوویەتی؛ لە گردی حەلەف و عەین دارا بۆ خوداوەندەکان، شێرەکان و ڕووی دیوارەکان هەڵگیرابوو.', 'area'],
                'landmarks_mat_sandstone' => ['Material — sandstone', 'Sandstone', 'بەردی لم'],
                'landmarks_mat_sandstone_note' => ['Material — sandstone, why here', 'Softer and warmer than limestone. On Mount Nemrut the rows of relief stelae behind the limestone gods were cut from it.', 'لە بەردی کلسی نەرمتر و ڕەنگگەرمترە. لەسەر چیای نەمرود، ڕیزی ستێلە نەخشێنراوەکانی پشت خوداوەندە کلسییەکان لەم بەردە بڕاون.', 'area'],
                'landmarks_mat_stone'   => ['Material — local stone', 'Local stone', 'بەردی ناوچەکە'],
                'landmarks_mat_stone_note' => ['Material — local stone, why here', 'Whatever the mountain gives: stone gathered and cut beside the site, from the stepped villages of Hawraman to the carved halls of Ishak Pasha Palace.', 'هەرچی چیاکە بیدات: بەردێک کە لە تەنیشت شوێنەکە کۆکراوەتەوە و بڕاوە، لە گوندە پلەپلەکانی هەورامانەوە تا هۆڵە نەخشێنراوەکانی کۆشکی ئیسحاق پاشا.', 'area'],
                'landmarks_mat_brick'   => ['Material — fired brick', 'Fired brick', 'خشتی سووتاو'],
                'landmarks_mat_brick_note' => ['Material — fired brick, why here', 'Where the plain gives clay and no stone, builders fired the earth: brick for walls and vaults, and on the Mudhafaria Minaret for the ornament too.', 'لەو شوێنەی دەشت قوڕ دەدات و بەردی نییە، وەستاکان خاکیان سووتاند: خشت بۆ دیوار و تاق، و لە منارەی موزەفەرییەدا بۆ نەخشیش.', 'area'],
                'landmarks_mat_mudbrick' => ['Material — mud brick', 'Mud brick', 'خشتی قوڕ'],
                'landmarks_mat_mudbrick_note' => ['Material — mud brick, why here', 'Earth, water, straw and sun. The first cities of the plains were built of it, and the mounds they left behind are what remains of their walls.', 'خاک، ئاو، کا و خۆر. یەکەم شارەکانی دەشت لەمە دروستکران، و ئەو گردانەی بەجێیان هێشتووە پاشماوەی دیوارەکانیانە.', 'area'],
                'landmarks_mat_rock'    => ['Material — living rock (caves, carved cliffs)', 'Living rock', 'تاشەبەرد'],
                'landmarks_mat_rock_note' => ['Material — living rock, why here', 'Not built but cut: caves lived in, and cliffs carved where they stand. Here the mountain itself is the monument.', 'دروستنەکراوە، بەڵکو هەڵکەنراوە: ئەشکەوتی تێدا ژیاو، و تاشەبەردی نەخشێنراو لە شوێنی خۆیدا. لێرە چیاکە خۆی شوێنەوارەکەیە.', 'area'],
                'landmarks_mat_timber'  => ['Material — timber', 'Timber', 'دار'],
                'landmarks_mat_timber_note' => ['Material — timber, why here', 'Scarce in this country and used sparingly: kept for roofs, posts and ceilings.', 'لەم وڵاتەدا کەمە و بە دەستپێوەگرتن بەکاردێت: بۆ سەقف، کۆڵەکە و بنمیچ هەڵدەگیرێت.', 'area'],
                // each landmark's state today: the line on its page, and the "Lost and at risk" section
                'landmarks_state'       => ['Landmark page — its state today', 'Today', 'ئەمڕۆ'],
                'landmarks_state_source' => ['State — source link', 'Source', 'سەرچاوە'],
                'landmarks_risk_label'  => ['Lost and at risk — small label', 'Lost and at risk', 'لەدەستچوو و لە مەترسیدا'],
                'landmarks_risk_title'  => ['Lost and at risk — title', 'What war, water and weather have taken', 'ئەوەی شەڕ و ئاو و کەشوهەوا بردوویانە'],
                'landmarks_risk_intro'  => ['Lost and at risk — introduction', 'Some of these places have been bombed, flooded or left to the weather. What happened to each, as the sources report it.', 'هەندێک لەم شوێنانە بۆمباران کراون، ژێر ئاو کەوتوون یان بۆ کەشوهەوا بەجێهێڵراون. ئەوەی بەسەر هەریەکەیاندا هاتووە، وەک سەرچاوەکان دەیگێڕنەوە.', 'area'],
                'landmarks_map_hint'  => ['Map — hint', 'Tap a pin to meet the landmark', 'دەست لە نیشانەیەک بدە بۆ ناسینی شوێنەوارەکە'],
                'landmarks_read'      => ['Read more link', 'Read more', 'زیاتر بخوێنەوە'],
                'landmarks_period'    => ['Facts — period', 'Period', 'سەردەم'],
                'landmarks_place'     => ['Facts — place', 'Place', 'شوێن'],
                'landmarks_region'    => ['Facts — region', 'Region', 'پارچە'],
                'landmarks_era'       => ['Facts — age', 'Age', 'قۆناغ'],
                'landmarks_unesco'    => ['UNESCO badge', 'UNESCO World Heritage Site', 'میراتی جیهانیی یونسکۆ'],
                'landmarks_directions'=> ['Directions link', 'Open in Google Maps', 'لە گووگڵ ماپ بیکەرەوە'],
                'landmarks_back'      => ['Back to the map', 'The heritage map', 'نەخشەی میرات'],
                'landmarks_prev'      => ['Previous landmark', 'Previous', 'پێشوو'],
                'landmarks_next'      => ['Next landmark', 'Next', 'دواتر'],
                'landmarks_photo_soon'=> ['A landmark with no photograph yet', 'Photograph coming', 'وێنە بەم زووانە'],
                'landmarks_by'        => ['Signature', 'Chosen and introduced by Aram Mizuri, architect in Erbil', 'هەڵبژاردن و ناساندنی ئارام مزووری، تەلارساز لە هەولێر'],
                'landmarks_cta'       => ['Closing question', 'Building on this ground today?', 'ئەمڕۆ لەسەر ئەم خاکە بنیات دەنێیت؟'],
                'landmarks_cta_btn'   => ['Closing button', 'Talk to the studio', 'لەگەڵ ستۆدیۆ قسە بکە'],
                // the landmark pages' sections
                'landmarks_facts_default'   => ['Facts — default heading', 'Did you know?', 'دەتزانی؟'],
                'landmarks_meanwhile'       => ['Facts — "meanwhile" heading', 'Meanwhile in the world', 'لە هەمان کاتدا لە جیهان'],
                'landmarks_sources_default' => ['Sources — default heading', 'Sources & further reading', 'سەرچاوەکان و خوێندنەوەی زیاتر'],
                'landmarks_related_default' => ['Related landmarks — default heading', 'Go on to', 'بڕۆ بۆ'],
                'landmarks_timeline_default'=> ['Timeline — default heading', 'Timeline', 'هێڵی کات'],
                'landmarks_gallery_default' => ['Gallery — default heading', 'In pictures', 'بە وێنە'],
                'landmarks_stats_default'   => ['Figures — default heading', 'At a glance', 'بە کورتی'],
                'landmarks_where'           => ['Map — default heading', 'Where it is', 'لە کوێیە'],
                'landmarks_unesco_inscribed'=> ['UNESCO — inscribed in {y}', 'Inscribed on the World Heritage List in {y}', 'لە ساڵی {y} لە لیستی میراتی جیهانی تۆمارکرا'],
                'landmarks_unesco_criteria' => ['UNESCO — criteria {c}', 'Criteria {c}', 'پێوەرەکان {c}'],
                'landmarks_unesco_link'     => ['UNESCO — link to the listing', 'The UNESCO listing', 'تۆمارەکە لە یونسکۆ'],
                'landmarks_unesco_official' => ['UNESCO — official name (under the landmark’s own name in Kurdish and Arabic)', 'Official name', 'ناوی فەرمی'],
                'landmarks_before'          => ['Before / after — before', 'Before', 'پێشتر'],
                'landmarks_after'           => ['Before / after — after', 'After', 'دواتر'],
                'landmarks_compare_hint'    => ['Before / after — hint', 'Drag to compare', 'ڕایبکێشە بۆ بەراوردکردن'],
                'landmarks_point_hint'      => ['Annotated photograph — hint', 'Tap a number on the photograph', 'دەست لە ژمارەیەک لەسەر وێنەکە بدە'],
                'landmarks_tq_label'        => ['How it was built — small label (and the heading, when none is written)', 'How it was built', 'چۆن دروستکراوە'],
                'landmarks_tq_replay'       => ['How it was built — play the steps again', 'Play again', 'دووبارە پیشانی بدەوە'],
                'landmarks_photo'           => ['Photo credit prefix', 'Photo', 'وێنە'],
                'landmarks_lb_close'        => ['Lightbox — close', 'Close', 'داخستن'],
                'landmarks_lb_prev'         => ['Lightbox — previous', 'Previous photograph', 'وێنەی پێشوو'],
                'landmarks_lb_next'         => ['Lightbox — next', 'Next photograph', 'وێنەی دواتر'],
                'landmarks_preview_note'    => ['Preview banner', 'Preview — this landmark is not published yet', 'پێشبینین — ئەم شوێنەوارە هێشتا بڵاونەکراوەتەوە'],
                // the collection page
                'landmarks_stat_landmarks'  => ['Figures — landmarks', 'Landmarks', 'شوێنەوار'],
                'landmarks_stat_unesco'     => ['Figures — UNESCO sites', 'UNESCO World Heritage Sites', 'میراتی جیهانیی یونسکۆ'],
                'landmarks_stat_years'      => ['Figures — years', 'Years of building', 'ساڵ بیناسازی'],
                'landmarks_stat_parts'      => ['Figures — parts of Kurdistan', 'Parts of Kurdistan', 'پارچەی کوردستان'],
                'landmarks_timeline_title'  => ['Timeline — title', 'From the first hearths to today', 'لە یەکەم ئاگردانەکانەوە تا ئەمڕۆ'],
                'landmarks_timeline_hint'   => ['Timeline — hint', 'Each mark is a landmark — tap one', 'هەر نیشانەیەک شوێنەوارێکە — دەست لە یەکێک بدە'],
                'landmarks_now'             => ['Timeline — today', 'today', 'ئەمڕۆ'],
                'landmarks_collection'      => ['The list — heading', 'The collection', 'کۆکراوەکە'],
                'landmarks_explore'         => ['Hero — button to the map', 'Explore the map', 'نەخشەکە بگەڕێ'],
            ],

            // A client's private review page (/review/…), opened from a review
            // link made under Clients → Review links. {name} and {project} are
            // filled in for each client.
            'Client review page' => [
                'review_label'      => ['Small label', 'Aram Mizuri Architecture', 'ستۆدیۆی تەلارسازیی ئارام مزووری'],
                'review_greeting'   => ['Greeting, {name}', 'Dear {name},', '{name}ی ئازیز،'],
                'review_title'      => ['Title', 'How was building with us?', 'کارکردن لەگەڵمان چۆن بوو؟'],
                'review_intro'      => ['Introduction, with a project — {project}', 'Thank you for trusting us with {project}. A few honest words from you help other families choose with confidence. It takes two minutes.', 'سوپاس بۆ متمانەت بە ئێمە بۆ {project}. چەند وشەیەکی ڕاستگۆیانەی تۆ یارمەتی خێزانەکانی تر دەدات بە دڵنیاییەوە هەڵبژێرن. تەنها دوو خولەک دەخایەنێت.', 'area'],
                'review_intro_plain'=> ['Introduction, without a project', 'Thank you for trusting us. A few honest words from you help other families choose with confidence. It takes two minutes.', 'سوپاس بۆ متمانەت پێمان. چەند وشەیەکی ڕاستگۆیانەی تۆ یارمەتی خێزانەکانی تر دەدات بە دڵنیاییەوە هەڵبژێرن. تەنها دوو خولەک دەخایەنێت.', 'area'],
                'review_words'      => ['Your words — label', 'Your words', 'وشەکانی تۆ'],
                'review_words_hint' => ['Your words — placeholder', 'What was it like to work with the studio? What do you love about the result?', 'کارکردن لەگەڵ ستۆدیۆ چۆن بوو؟ چیت لە ئەنجامەکە پێ خۆشە؟'],
                'review_name'       => ['Name', 'Your name, as it should appear', 'ناوت، وەک دەتەوێت دەربکەوێت'],
                'review_role'       => ['Role', 'Your role or company (optional)', 'پیشە یان کۆمپانیاکەت (ئارەزوومەندانە)'],
                'review_voice_title'=> ['Voice — heading', 'Your voice (optional)', 'دەنگی تۆ (ئارەزوومەندانە)'],
                'review_voice_hint' => ['Voice — explanation', 'Say your words in your own voice — up to 90 seconds. It plays beside your quote on the website.', 'وشەکانت بە دەنگی خۆت بڵێ — تا ٩٠ چرکە. لە تەنیشت وشەکانت لە ماڵپەڕەکەدا لێدەدرێت.', 'area'],
                'review_rec'        => ['Voice — record', 'Record', 'تۆمارکردن'],
                'review_stop'       => ['Voice — stop', 'Stop', 'وەستان'],
                'review_again'      => ['Voice — record again', 'Record again', 'دووبارە تۆمارکردنەوە'],
                'review_remove'     => ['Voice / photo — remove', 'Remove', 'لابردن'],
                'review_upload'     => ['Voice — choose a file instead', 'or choose a voice note', 'یان دەنگێکی تۆمارکراو هەڵبژێرە'],
                'review_mic'        => ['Voice — the microphone could not be used', 'The microphone could not be used here. You can choose a voice note instead.', 'لێرە مایکرۆفۆن بەکارنەهات. دەتوانیت دەنگێکی تۆمارکراو هەڵبژێریت.', 'area'],
                'review_photo_title'=> ['Photo — heading', 'Your photo (optional)', 'وێنەکەت (ئارەزوومەندانە)'],
                'review_photo_hint' => ['Photo — explanation', 'We draw a pencil portrait from it. The photo itself is never published.', 'وێنەیەکی قەڵەمڕەساسیی لێ دەکێشین. خودی وێنەکە هەرگیز بڵاو ناکرێتەوە.', 'area'],
                'review_photo_choose'=> ['Photo — choose', 'Choose a photo…', 'وێنەیەک هەڵبژێرە…'],
                'review_consent'    => ['Agreement', 'I agree that my words — and my voice and the portrait drawn from my photo, if I add them — may be published on arammizuri.com.', 'ڕازیم کە وشەکانم — و دەنگم و ئەو وێنە قەڵەمڕەساسییەی لە وێنەکەم دەکێشرێت، ئەگەر زیادیان بکەم — لە arammizuri.com بڵاو بکرێنەوە.', 'area'],
                'review_send'       => ['Send', 'Send my words', 'ناردنی وشەکانم'],
                'review_ok_title'   => ['Sent — title', 'Thank you!', 'زۆر سوپاس!'],
                'review_ok'         => ['Sent — text', 'Your words have reached us. We read every one personally — thank you for building with us.', 'وشەکانت گەیشتن. هەموویان خۆمان دەیانخوێنینەوە — سوپاس کە لەگەڵمان بنیاتت نا.', 'area'],
                'review_google'     => ['Sent — ask for Google too', 'Would you post your words on Google too? It helps other families find us.', 'دەتەوێت وشەکانت لە گووگڵیش بنووسیت؟ یارمەتی خێزانەکانی تر دەدات بمانبیننەوە.'],
                'review_google_btn' => ['Sent — Google button', 'Write it on Google', 'لە گووگڵ بینووسە'],
                'review_used'       => ['Link already used', 'Thank you — we have already received your words.', 'سوپاس — وشەکانمان پێشتر وەرگرتووە.'],
                'review_expired'    => ['Link expired', 'This link has expired. Please ask us for a new one.', 'ماوەی ئەم بەستەرە بەسەرچووە. تکایە داوای بەستەرێکی نوێمان لێ بکە.'],
                'review_big'        => ['File too large', 'That file is too large.', 'ئەو فایلە زۆر گەورەیە.'],
            ],

            // Book a visit (/visit): a studio visit, a site visit or a phone call,
            // asked for on the day and time that suits. The requests are under
            // Clients → Visits; the WhatsApp messages go out in the visitor's language.
            'Book a visit' => [
                'visit_link'        => ['Homepage link, next to "visits by appointment"', 'Book a visit', 'ژوانێک داوا بکە'],
                'visit_label'       => ['Page label', 'Visits by appointment', 'سەردان بە ژوان'],
                'visit_title'       => ['Title', 'When suits you?', 'کەی بۆت دەگونجێت؟'],
                'visit_text'        => ['Under the title', 'Ask for a day and a time. We confirm it on WhatsApp — or suggest another that suits us both.', 'ڕۆژ و کاتێک داوا بکە. لە واتسئاپ پشتڕاستی دەکەینەوە — یان کاتێکی تر پێشنیار دەکەین کە بۆ هەردوولامان بگونجێت.', 'area'],
                'visit_kind'        => ['Kind — label', 'What kind of visit', 'جۆری سەردان'],
                'visit_kind_studio' => ['Kind — studio', 'Studio visit', 'سەردانی ستۆدیۆ'],
                'visit_kind_site'   => ['Kind — site', 'Site visit', 'سەردانی شوێنی کار'],
                'visit_kind_call'   => ['Kind — phone call', 'Phone call', 'پەیوەندی تەلەفۆنی'],
                'visit_about'       => ['What it is about — label', 'What is it about?', 'دەربارەی چییە؟'],
                'visit_about_ph'    => ['What it is about — example', 'e.g. a house on a 300 m² plot in Ankawa', 'بۆ نموونە خانوویەک لەسەر زەوییەکی ٣٠٠ م² لە عەنکاوە'],
                'visit_day'         => ['Day — label', 'Day', 'ڕۆژ'],
                'visit_time'        => ['Time — label', 'Time', 'کات'],
                'visit_send'        => ['Send button', 'Ask for this time', 'ئەم کاتە داوا بکە'],
                'visit_sent'        => ['After sending', 'Thank you — we have your request and will confirm it on WhatsApp soon.', 'سوپاس — داواکارییەکەت گەیشت و بەم زووانە لە واتسئاپ پشتڕاستی دەکەینەوە.', 'area'],
                'visit_error'       => ['A field is missing or wrong', 'Please fill in every field — the day can be today or later.', 'تکایە هەموو خانەکان پڕ بکەرەوە — ڕۆژەکە دەبێت ئەمڕۆ یان دواتر بێت.'],
                'visit_wa_confirmed' => ['WhatsApp — confirmed ({name} {kind} {day} {time})', 'Hello {name}, this is Aram Mizuri Architecture. Confirmed — {kind} on {day} at {time}. See you then.', 'سڵاو {name}، ئارام مزووری تەڵارسازی. پشتڕاستکرایەوە — {kind} لە {day} کاتژمێر {time}. چاوەڕێتین.', 'area'],
                'visit_wa_suggested' => ['WhatsApp — another time suggested ({name} {kind} {day} {time})', 'Hello {name}, this is Aram Mizuri Architecture. About the {kind} you asked for — could we do {day} at {time} instead? Just reply to let us know.', 'سڵاو {name}، ئارام مزووری تەڵارسازی. سەبارەت بە {kind} کە داوات کردبوو — دەکرێت لە {day} کاتژمێر {time} بیکەین؟ تکایە وەڵاممان بدەرەوە.', 'area'],
            ],

            // The partners and suppliers page (/partners). The partners
            // themselves are under Clients → Partners.
            'Partners' => [
                'partners_nav'   => ['Footer link', 'Partners', 'هاوبەشەکان'],
                'partners_label' => ['Page label', 'Partners & suppliers', 'هاوبەش و دابینکەران'],
                'partners_title' => ['Title', 'Who we build with', 'لەگەڵ کێ بنیات دەنێین'],
                'partners_intro' => ['Introduction', 'Good buildings are made by many hands. These are the suppliers, brands and craftspeople whose materials and work we trust in our projects.', 'بینای باش بە دەستی زۆر دروست دەکرێت. ئەمانە ئەو دابینکەر، براند و وەستایانەن کە متمانەمان بە کەرەستە و کارەکانیان هەیە لە پرۆژەکانماندا.', 'area'],
                'partners_visit' => ['Link to a partner\'s website', 'Visit website', 'سەردانی ماڵپەڕ بکە'],
                'partners_empty' => ['No partners yet', 'Our partners will be listed here soon.', 'بەم زووانە هاوبەشەکانمان لێرە دەخرێنە ڕوو.'],
                'partners_join'  => ['Closing question', 'A supplier or brand who would like to work with us?', 'دابینکەر یان براندێکیت و دەتەوێت لەگەڵمان کار بکەیت؟'],
                'partners_join_btn' => ['Closing button — opens the application form', 'Apply to become a partner', 'داوای بوون بە هاوبەش بکە'],
                'partners_all'      => ['Homepage — link to the page', 'See all partners', 'هەموو هاوبەشەکان ببینە'],
                'partners_home_text'=> ['Homepage — line under the title', 'The suppliers, brands and craftspeople behind our projects.', 'ئەو دابینکەر، براند و وەستایانەی لە پشت پرۆژەکانمانن.', 'area'],
                'partners_open'     => ['Logo wall — open a partner (for screen readers)', 'Open', 'بیکەرەوە'],
                'partners_together' => ['Partner — projects done together', 'Projects together', 'پرۆژە هاوبەشەکان'],
                'partners_call'     => ['Partner — call button', 'Call', 'پەیوەندی'],
                'partners_whatsapp' => ['Partner — WhatsApp button', 'WhatsApp', 'واتسئاپ'],
                'partners_map'      => ['Partner — showroom on the map', 'Showroom on the map', 'پیشانگا لەسەر نەخشە'],
                'partners_close'    => ['Partner — close', 'Close', 'داخستن'],
                'partners_made_with'=> ['Project page — the partners who worked on it', 'Made with', 'بە هاوکاری'],
                'partners_tab_logos'=> ['Switch — the logo wall', 'Logos', 'لۆگۆکان'],
                'partners_tab_map'  => ['Switch — the showrooms map', 'Showrooms map', 'نەخشەی پیشانگاکان'],
                'partners_directions'=> ['Map — directions', 'Directions', 'ڕێنمایی ڕێگا'],
                'partners_view'     => ['Map — open the partner', 'View partner', 'هاوبەشەکە ببینە'],
                'partners_apply_intro' => ['Application — line under the question', 'Tell us about your company. We review every application and contact you before anything is published.', 'دەربارەی کۆمپانیاکەت پێمان بڵێ. هەموو داواکارییەک دەخوێنینەوە و پێش بڵاوکردنەوەی هەر شتێک پەیوەندیت پێوە دەکەین.', 'area'],
                'partners_f_company'=> ['Application — company', 'Company name', 'ناوی کۆمپانیا'],
                'partners_f_trade'  => ['Application — trade', 'Trade', 'بوار'],
                'partners_f_person' => ['Application — contact person', 'Contact person', 'کەسی پەیوەندی'],
                'partners_f_email'  => ['Application — email', 'Email', 'ئیمەیڵ'],
                'partners_f_phone'  => ['Application — phone', 'Phone', 'ژمارەی تەلەفۆن'],
                'partners_f_whatsapp' => ['Application — WhatsApp tick', 'This number is on WhatsApp', 'ئەم ژمارەیە واتسئاپی هەیە'],
                'partners_f_site'   => ['Application — website', 'Website (optional)', 'ماڵپەڕ (ئارەزوومەندانە)'],
                'partners_f_line'   => ['Application — one line', 'What you offer, in one line (optional)', 'ئەوەی پێشکەشی دەکەیت، بە یەک دێڕ (ئارەزوومەندانە)'],
                'partners_f_logo'   => ['Application — logo', 'Your logo — JPG, PNG or WebP, up to 4 MB (optional)', 'لۆگۆکەت — JPG، PNG یان WebP، تا ٤ مێگابایت (ئارەزوومەندانە)'],
                'partners_f_logo_choose' => ['Application — choose a logo', 'Choose an image…', 'وێنەیەک هەڵبژێرە…'],
                'partners_f_message'=> ['Application — message', 'Anything else we should know (optional)', 'هەر شتێکی تر کە پێویستە بیزانین (ئارەزوومەندانە)'],
                'partners_f_send'   => ['Application — send', 'Send application', 'ناردنی داواکاری'],
                'partners_f_ok'     => ['Application — received', '✓ Thank you — your application has arrived. We review every one and will be in touch.', '✓ سوپاس — داواکارییەکەت گەیشت. هەموویان دەخوێنینەوە و پەیوەندیت پێوە دەکەین.', 'area'],
                'partners_f_big'    => ['Application — logo too large', 'That image is larger than 4 MB.', 'ئەو وێنەیە لە ٤ مێگابایت گەورەترە.'],
            ],

            'Footer' => [
                'footer_logo'    => ['Logo text', 'ARAM MIZURI', 'ئارام مزووری'],
                'footer_tagline' => ['Tagline', 'Architecture · Erbil · Kurdistan', 'تەڵارسازی · هەولێر · کوردستان'],
            ],

            // Everything a visitor reads while they are waiting. Most of these
            // are only ever seen on a slow connection, which is exactly when a
            // Kurdish reader must not be dropped into English — so they live
            // here with the rest, not hard-coded in script.js. Shipped to the
            // browser as window.__SITE__.ui (see self::JS_TAB).
            self::JS_TAB => [
                'wait_slow'        => ['Loader — slow connection note', 'Still loading — this is taking longer than usual.', 'هێشتا باردەبێت — لەوە زیاتر کاتی دەوێت کە ئاسایی بێت.', 'area'],
                'wait_stuck'       => ['Loader — gave up note', 'This is taking too long to load.', 'ئەمە زۆر کات دەخایەنێت بۆ بارکردن.'],
                'wait_reload'      => ['Loader — reload button', 'Reload the page', 'پەڕەکە دووبارە بارکە'],
                'wait_continue'    => ['Loader — continue anyway button', 'Continue anyway', 'بەهەرحاڵ بەردەوام بە'],
                'img_failed'       => ['Image — could not load', 'Image unavailable', 'وێنە بەردەست نییە'],
                'img_retry'        => ['Image — retry button', 'Retry', 'دووبارە هەوڵبدە'],
                'dl_preparing'     => ['Full image — preparing', 'Preparing your image', 'وێنەکەت ئامادە دەکرێت'],
                'dl_downloading'   => ['Full image — downloading', 'Downloading the full-size image', 'وێنەی قەبارە تەواو دادەبەزێت'],
                'dl_stamping'      => ['Full image — watermarking', 'Adding the studio mark', 'نیشانەی ستۆدیۆ زیاد دەکرێت'],
                'dl_saving'        => ['Full image — saving', 'Saving to your device', 'پاشەکەوت دەکرێت بۆ ئامێرەکەت'],
                'dl_done'          => ['Full image — finished', 'Done', 'تەواو'],
                'dl_cancel'        => ['Full image — cancel button', 'Cancel', 'هەڵوەشاندنەوە'],
                'dl_failed'        => ['Full image — failed', 'That image could not be loaded.', 'نەتوانرا ئەو وێنەیە باربکرێت.'],
                'dl_retry'         => ['Full image — retry button', 'Try again', 'دووبارە هەوڵبدە'],
                'dl_large_note'    => ['Full image — large file note', 'These are full-size originals — they can be large.', 'ئەمانە ڕەسەنی قەبارە تەواون — دەتوانن گەورە بن.', 'area'],
                'map_loading'      => ['Map — loading', 'Loading the map', 'نەخشەکە بار دەبێت'],
                'map_failed'       => ['Map — could not load', 'The map could not be loaded.', 'نەتوانرا نەخشەکە باربکرێت.'],
                'form_sending'     => ['Form — sending', 'Sending…', 'دەنێردرێت…'],
                'form_failed'      => ['Form — failed', '⚠ Something went wrong — please email us directly.', '⚠ هەڵەیەک ڕوویدا — تکایە ڕاستەوخۆ ئیمەیڵمان بۆ بنێرە.', 'area'],
                'form_wait'        => ['Form — too many tries in a minute', '⚠ Too many tries — please wait a minute and send again.', '⚠ هەوڵی زۆر — تکایە خولەکێک چاوەڕێ بکە و دووبارە بینێرەوە.', 'area'],
                'form_need_contact' => ['Form — no email or phone given', 'Please leave an email or a phone number so we can reply.', 'تکایە ئیمەیڵ یان ژمارەی تەلەفۆنێک بنووسە بۆ ئەوەی وەڵامت بدەینەوە.', 'area'],
                'form_check'       => ['Form — a field needs fixing', 'Please check the highlighted field.', 'تکایە ئەو خانەیە بپشکنە کە دیاری کراوە.'],
            ],
        ];
    }

    /**
     * The tab whose strings are also shipped to the browser.
     *
     * These are the only strings script.js has to render itself — every other
     * bilingual string on the page is already in the markup, carried by the
     * data-en / data-ku pair that the language toggle swaps. Waiting states
     * have no markup to sit in until the wait happens, so they travel as data.
     */
    public const JS_TAB = 'Loading & waiting';

    /**
     * The JS_TAB strings resolved to both languages, for window.__SITE__.ui.
     *
     * @return array<string, array{en:string, ku:string}>
     */
    public static function jsStrings(): array
    {
        $out = [];
        // on an Arabic page the "ku" half carries the Arabic (see rtl_slot())
        $ar = site_lang() === 'ar';
        foreach (array_keys(static::tabs()[self::JS_TAB] ?? []) as $key) {
            $out[$key] = ['en' => static::en($key), 'ku' => $ar ? static::ar($key) : static::ku($key)];
        }

        return $out;
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

    /**
     * The texts with English but no Kurdish, or no Arabic of their own (an
     * Arabic page then shows the English): [key => ['ku', 'ar'] missing].
     *
     * @return array<string, list<string>>
     */
    public static function untranslated(): array
    {
        $ar = SiteTextAr::all();
        $out = [];
        foreach (static::map() as $key => $def) {
            // nothing to translate, or English only on purpose (read by search
            // engines): no Kurdish and an Arabic left empty by design
            if (static::en($key) === '' || (($def['ku'] ?? '') === '' && ($ar[$key] ?? null) === '')) {
                continue;
            }
            $missing = array_keys(array_filter([
                'ku' => static::ku($key) === '',
                'ar' => (string) SiteSetting::get("{$key}_ar") === '' && ($ar[$key] ?? '') === '',
            ]));
            if ($missing) {
                $out[$key] = $missing;
            }
        }

        return $out;
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

    /**
     * Arabic value: {key}_ar → the Arabic default (SiteTextAr) → English.
     * Never the Kurdish: an Arabic reader is better served by English.
     */
    public static function ar(string $key): string
    {
        $v = SiteSetting::get("{$key}_ar");
        if ($v !== null && $v !== '') {
            return (string) $v;
        }
        $default = SiteTextAr::all()[$key] ?? '';

        return $default !== '' ? $default : static::en($key);
    }

    /**
     * The page's second language — the text the browser holds in its "ku"
     * slot (data-ku, .ku, *_ku): Arabic on an Arabic page, Kurdish anywhere
     * else. See rtl_slot() in helpers.php.
     */
    public static function second(string $key): string
    {
        return site_lang() === 'ar' ? static::ar($key) : static::ku($key);
    }

    /** Kurdish value: {key}_ku → default. */
    public static function ku(string $key, ?string $default = null): string
    {
        $default ??= static::map()[$key]['ku'] ?? '';

        $v = SiteSetting::get("{$key}_ku");

        return ($v !== null && $v !== '') ? (string) $v : $default;
    }
}
