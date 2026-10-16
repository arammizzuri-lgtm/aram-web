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
                'nav_menu'       => ['Menu button (other pages, on a tablet or phone)', 'Menu', 'مێنیو'],
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
                'seo_description' => ['Description (Google, link previews — about 150 characters)', 'Aram Mizuri Architecture — a leading architectural practice based in Erbil, Kurdistan Region. Residential, cultural, commercial and urban design.', 'تەڵارسازیی ئارام مزووری — ئۆفیسێکی پێشەنگی تەڵارسازی لە هەولێر، هەرێمی کوردستان. دیزاینی نیشتەجێبوون، کولتووری، بازرگانی و شارستانی.', 'area'],
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
                'statbar_sub'   => ['Subtitle', 'Years of building across Kurdistan and beyond — every figure here is carried by standing architecture.', 'ساڵانێک لە بیناسازی لە کوردستان و دەرەوەی — هەر ژمارەیەکی ئێرە بە تەڵارسازیی وەستاو هەڵگیراوە.', 'area'],
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

            // The poster for a building site's fence (Projects → a project →
            // Site board), printed in both languages at once.
            'Site boards' => [
                'board_building' => ['Headline, while the building goes up', 'Being built here', 'لێرە بنیات دەنرێت'],
                'board_scan'     => ['Beside the QR code', 'Scan to see the design', 'سکانی بکە بۆ بینینی دیزاینەکە'],
                'board_by'       => ['Along the foot', 'Designed by Aram Mizuri Architecture', 'دیزاینی تەڵارسازیی ئارام مزووری'],
            ],

            // The holiday cards (/card) — the occasions themselves, their
            // greetings and dates, are under Manage → Holiday cards.
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
            // rates themselves are under Manage → Cost estimator.
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
                'est_send'         => ['Send — button', 'Send this to the studio', 'ئەمە بۆ ستۆدیۆ بنێرە'],
                'est_send_title'   => ['Send — heading', 'We will look at it and get back to you', 'سەیری دەکەین و وەڵامت دەدەینەوە'],
                'est_send_btn'     => ['Send — submit', 'Send', 'ناردن'],
                'est_sent'         => ['Send — sent', '✓ Sent — we will be in touch soon.', '✓ نێردرا — بەم زووانە پەیوەندیت پێوە دەکەین.'],
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
                'pj_intro'  => ['Introduction', 'Homes, interiors, workplaces and public buildings across Kurdistan — every project we have drawn, and the many we have seen built.', 'خانوو، دیزاینی ناوەوە، شوێنی کار و بینای گشتی لە سەرانسەری کوردستان — هەموو ئەو پرۆژانەی کێشاومانن، و زۆرێک لەوانەی بینیومانن کە بنیات نراون.', 'area'],
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
                'careers_intro'     => ['Introduction', 'We are a studio in Erbil designing homes, towers and public places across Kurdistan. If you care about good architecture and want to learn by building it, we would like to hear from you.', 'ئێمە ستۆدیۆیەکین لە هەولێر کە خانوو، تاوەر و شوێنی گشتی لە سەرانسەری کوردستان دیزاین دەکەین. ئەگەر تەڵارسازیی باش بۆت گرنگە و دەتەوێت بە بنیاتنانی فێر ببیت، حەز دەکەین گوێمان لێت بێت.', 'area'],
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
        foreach (array_keys(static::tabs()[self::JS_TAB] ?? []) as $key) {
            $out[$key] = ['en' => static::en($key), 'ku' => static::ku($key)];
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
