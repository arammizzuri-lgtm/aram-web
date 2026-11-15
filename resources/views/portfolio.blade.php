@php
    // English at /, Kurdish at /ku — the page arrives in its language, so
    // search engines and link previews read the version they were sent to.
    $isKu = site_rtl();   // right to left: Kurdish or Arabic
    $seoTitle = bival('seo_title');
    $seoDescription = bival('seo_description');
    $ogImage = asset('og-image.jpg').'?v='.filemtime(public_path('og-image.jpg'));
@endphp
<!DOCTYPE html>
<html lang="{{ site_lang() }}" dir="{{ $isKu ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}">
    @include('partials.seo-alternates', ['path' => '/'])
    @include('partials.site-verification')

    {{-- Social link preview (Facebook / WhatsApp / iMessage / LinkedIn / X).
         The image is the studio's hero mark; ?v busts the aggressive caches
         these platforms keep, whenever the file is re-generated. Absolute URLs
         come from APP_URL, so that must be the live domain in production. --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Aram Mizuri Architecture">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ site_url('/') }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:secure_url" content="{{ $ogImage }}">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $seoTitle }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    <meta name="twitter:image:alt" content="{{ $seoTitle }}">
    @include('partials.studio-schema', ['image' => $ogImage, 'description' => $seoDescription])

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- Fonts load without blocking. A stylesheet in the head holds up every
         script on the page until it answers, so a slow fonts.googleapis.com
         used to stall script.js — and with it the loader that is supposed to
         explain the wait. Loading it as `print` takes it off that critical
         path; the onload promotes it to `all` the moment it arrives. The
         faces already carry display=swap, so text is readable in the fallback
         either way, and <noscript> covers the no-JS case. --}}
    @php $fontsHref = 'https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Vazirmatn:wght@200;300;400;500;600;700&display=swap'; @endphp
    <link href="{{ $fontsHref }}" rel="stylesheet" media="print" onload="this.media='all';this.onload=null">
    <noscript><link href="{{ $fontsHref }}" rel="stylesheet"></noscript>
    {{-- Kurdish sun — same mark as the loader --}}
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- The loader plays once a visit: back on the homepage within half an
         hour of last seeing it, it is hidden before it can paint (script.js,
         initLoader, keeps the time). --}}
    <script>
        try {
            // The loader (the sun) is a welcome, shown on a visitor's first
            // visit only: once seen on this device, the page opens straight away.
            if (localStorage.getItem('am_loader_seen')) document.documentElement.classList.add('am-seen');
        } catch (e) { /* storage blocked: the loader, as on a first visit */ }
    </script>
    <link rel="stylesheet" href="{{ asset('style.css') }}?v={{ filemtime(public_path('style.css')) }}">
    <link rel="stylesheet" href="{{ asset('site-index.css') }}?v={{ filemtime(public_path('site-index.css')) }}">
    @if (site_rtl())<link rel="stylesheet" href="{{ asset('rtl-type.css') }}?v={{ filemtime(public_path('rtl-type.css')) }}">@endif
    <link rel="stylesheet" href="{{ asset('engage.css') }}?v={{ filemtime(public_path('engage.css')) }}">
    @php $quotesHaveVoices = $quotes->contains(fn ($q) => $q->showsVoiceBlock()); @endphp
    @if ($quotesHaveVoices)
        <link rel="stylesheet" href="{{ asset('voices.css') }}?v={{ filemtime(public_path('voices.css')) }}">
    @endif
    @include('partials.kurdish-font')
    {{-- Leaflet is served from our own origin, not unpkg.com.

         A stylesheet in the head blocks script execution until it resolves,
         and a <script> blocks everything after it — so with these four files
         on a third-party CDN, one unreachable host meant script.js never ran
         at all. Not a slow page: a permanently black one, with the loader
         still sitting on top of it and no code alive to take it down. That is
         not a hypothetical on the networks this practice's clients are on.

         Self-hosted, the worst case is a map that does not draw, on a page
         that works. three.min.js was already kept here for the same reason.
         To update, re-download all four files (plus vendor/images/) from
         unpkg at the matching version.

         They are no longer loaded with the page: no map is on the first
         screen, so script.js fetches them as a map comes near (leafletReady),
         from the addresses in window.__LEAFLET__ at the foot of the page. --}}
</head>
<body>

    {{-- Page Loader — the bar is driven by real progress in initLoader, not a
         fixed animation, and the note + buttons only appear if the wait runs
         long enough that silence would start to read as a broken page. --}}
    <div class="loader" id="loader">
        <div class="loader__sun" id="loaderSun"></div>
        <div class="loader__text">Aram Mizuri Architecture</div>
        <div class="loader__bar"><div class="loader__progress" id="loaderProgress"></div></div>
        <p class="loader__note" id="loaderNote" role="status" aria-live="polite"></p>
        <div class="loader__actions" id="loaderActions">
            <button class="loader__btn" id="loaderReload" type="button" {!! bitext('wait_reload') !!}>{{ bival('wait_reload') }}</button>
            <button class="loader__btn loader__btn--ghost" id="loaderSkip" type="button" {!! bitext('wait_continue') !!}>{{ bival('wait_continue') }}</button>
        </div>
    </div>

    <!-- Custom Cursor -->
    <div class="cursor" id="cursor"></div>
    <div class="cursor-follower" id="cursorFollower"></div>

@include('home.nav')
    @php $menu = \App\Support\SiteMenu::items(); @endphp
    @include('partials.site-index', ['sheets' => $menu])

    {{-- The page's own content, between the menu and the footer — the
         landmark screen readers (and AI agents) jump to. --}}
    <main id="main">

    <!-- ========== HERO ========== -->
    <section class="hero" id="home">
        <div class="hero__bg" aria-hidden="true">
            <span class="hero__aurora"></span>
            <span class="hero__grid"></span>
            <canvas id="heroCanvas" class="hero__canvas"></canvas>
            <span class="hero__grain"></span>
        </div>

        <div class="hero__center">
            <div class="hero__eyebrow">
                <span {!! bitext('hero_eyebrow') !!}>{{ bival('hero_eyebrow') }}</span>
                <span class="hero__clock"><span class="hero__clock-dot"></span> <span {!! bitext('ui_clock_city') !!}>{{ bival('ui_clock_city') }}</span>&nbsp;<time id="heroClock">--:--:--</time></span>
            </div>
            @php
                // Line 1's last word ("by" / "لەلایەن") renders as a tiny gold
                // conjunction so the two big words sit close together. Split
                // here so the CMS settings stay the single source of truth.
                $splitBy = function ($s) {
                    $w = preg_split('/\s+/u', trim((string) $s)) ?: [];
                    $by = count($w) > 1 ? array_pop($w) : '';
                    return [implode(' ', $w), $by];
                };
                [$l1MainEn, $l1ByEn] = $splitBy(\App\Support\SiteText::en('hero_title_line1'));
                // the second slot: Kurdish, or Arabic on /ar (SiteText::second)
                [$l1MainKu, $l1ByKu] = $splitBy(\App\Support\SiteText::second('hero_title_line1'));
            @endphp
            <h1 class="hero__title">
                <span class="hero__line"><span class="hero__line-in"><span dir="auto" data-en="{{ $l1MainEn }}" data-ku="{{ $l1MainKu }}">{{ slot($l1MainEn, $l1MainKu) }}</span><span class="hero__by" dir="auto" data-en="{{ $l1ByEn }}" data-ku="{{ $l1ByKu }}">{{ site_lang() === 'en' ? $l1ByEn : $l1ByKu }}</span></span></span>
                <span class="hero__line"><span class="hero__line-in hero__line-in--accent" {!! bitext('hero_title_line2') !!}>{{ bival('hero_title_line2') }}</span></span>
            </h1>

            {{-- Practice figures — rendered as the 3D glass card ring in
                 hero3d.js. This hidden list is the single source of truth
                 (and what screen readers announce); the canvas is decorative. --}}
            @php
                $areaNum = (int) $stats['area_short'];
                $areaSuf = trim(preg_replace('/[\d.,]/', '', $stats['area_short']));
            @endphp
            <div id="heroStatsData" class="sr-only">
                <span data-value="{{ $stats['projects'] }}" {!! bitext('stat_projects') !!}>{{ $stats['projects'] }} {{ bival('stat_projects') }}</span>
                <span data-value="{{ (int) setting('about_stat2_num') }}" {!! bitext('stat_clients') !!}>{{ (int) setting('about_stat2_num') }} {{ bival('stat_clients') }}</span>
                <span data-value="{{ $stats['cities'] }}" {!! bitext('stat_cities') !!}>{{ $stats['cities'] }} {{ bival('stat_cities') }}</span>
                <span data-value="{{ $stats['countries'] }}" {!! bitext('stat_countries') !!}>{{ $stats['countries'] }} {{ bival('stat_countries') }}</span>
                <span data-value="{{ $areaNum }}" data-suffix="{{ $areaSuf }}" {!! bitext('stat_area') !!}>{{ $stats['area_short'] }} {{ bival('stat_area') }}</span>
            </div>
        </div>

        {{-- Caption + CTA + drag hint live at the sheet's bottom centre on
             desktop (clear of the ring); on phones they stay in the flow
             right under the headline. --}}
        <div class="hero__foot">
            <p class="hero__sub" {!! bitext('hero_sub') !!}>{{ bival('hero_sub') }}</p>
            <div class="hero__actions">
                <a href="#projects" class="hero__go" id="heroGo">
                    <span class="hero__go-label" {!! bitext('hero_cta') !!}>{{ bival('hero_cta') }}</span>
                    <span class="hero__go-arrow">→</span>
                </a>
            </div>
        </div>

        <a href="#projects" class="hero__scroll" aria-label="Scroll to projects">
            <span {!! bitext('ui_scroll') !!}>{{ bival('ui_scroll') }}</span>
            <span class="hero__scroll-line"></span>
        </a>

    </section>

    <!-- ========== STATS BAND ========== -->
    {{-- Practice figures — lifted out of the 3D hero ring into their own
         full-width band (matches the Clients section width). Counts up on
         scroll via initStatbar; finger-drag to swipe on mobile. --}}
    @php
        $sbAreaNum = (int) $stats['area_short'];
        $sbAreaSuf = trim(preg_replace('/[\d.,]/', '', $stats['area_short']));
        $statbar = [
            ['v' => (int) $stats['projects'],         's' => '',         'en' => \App\Support\SiteText::en('stat_projects'),  'ku' => \App\Support\SiteText::second('stat_projects')],
            ['v' => (int) setting('about_stat2_num'), 's' => '',         'en' => \App\Support\SiteText::en('stat_clients'),   'ku' => \App\Support\SiteText::second('stat_clients')],
            ['v' => (int) $stats['cities'],           's' => '',         'en' => \App\Support\SiteText::en('stat_cities'),    'ku' => \App\Support\SiteText::second('stat_cities')],
            ['v' => (int) $stats['countries'],        's' => '',         'en' => \App\Support\SiteText::en('stat_countries'), 'ku' => \App\Support\SiteText::second('stat_countries')],
            ['v' => $sbAreaNum,                       's' => $sbAreaSuf, 'en' => \App\Support\SiteText::en('stat_area'),      'ku' => \App\Support\SiteText::second('stat_area')],
        ];
    @endphp
    {{-- Between a holiday card's dates (Clients → Holiday cards): a band
         offering it — Newroz, Eid, the New Year — to send with one's name. --}}
    @if ($greeting ?? null)
        <section class="gc-band" aria-label="{{ $greeting->title(site_lang()) }}">
            <div class="gc-band__inner">
                <span class="gc-band__sun" aria-hidden="true"></span>
                <div class="gc-band__words">
                    <p class="gc-band__title" dir="auto" data-en="{{ $greeting->title('en') }}" data-ku="{{ rtl_slot($greeting->title("en"), $greeting->title("ku"), $greeting->title("ar")) }}">{{ $greeting->title(site_lang()) }}</p>
                    <p class="gc-band__text" dir="auto" {!! bitext('card_band_text') !!}>{{ bival('card_band_text') }}</p>
                </div>
                <a class="gc-band__btn" href="{{ site_url('/card') }}" data-href-en="{{ site_url('/card', 'en') }}" data-href-ku="{{ site_url('/card', 'ku') }}" {!! bitext('card_band_btn') !!}>{{ bival('card_band_btn') }}</a>
            </div>
        </section>
    @endif

    <section class="statbar" aria-label="Practice figures">
        <div class="statbar__inner">
            <div class="statbar__caption">
                <p class="section-label" {!! bitext('statbar_label') !!}>{{ bival('statbar_label') }}</p>
                <h2 class="statbar__title" {!! bitext('statbar_title') !!}>{!! bival('statbar_title') !!}</h2>
                <p class="statbar__sub" {!! bitext('statbar_sub') !!}>{{ bival('statbar_sub') }}</p>
            </div>
            <div class="statbar__track" id="statbarTrack">
                @foreach ($statbar as $st)
                    <div class="statbar__item" style="--i: {{ $loop->index }}">
                        <span class="statbar__num" data-n="0{{ $st['s'] }}" data-value="{{ $st['v'] }}" data-suffix="{{ $st['s'] }}">0{{ $st['s'] }}</span>
                        <span class="statbar__rule" aria-hidden="true"></span>
                        <span class="statbar__label" dir="auto" data-en="{{ $st['en'] }}" data-ku="{{ $st['ku'] }}">{{ slot($st["en"], $st["ku"]) }}</span>
                    </div>
                @endforeach
            </div>

            {{-- mobile-only: tells the thumb there are more figures off-screen;
                 initStatbar dismisses it after the first real swipe --}}
            <p class="statbar__hint" id="statbarHint" aria-hidden="true">
                <span {!! bitext('statbar_swipe') !!}>{{ bival('statbar_swipe') }}</span>
                <span class="statbar__hint-arrow">→</span>
            </p>
        </div>
    </section>

@include('home.map')

    <section class="clients" id="clients">
        {{-- Clients & Partners come from the database (managed in the admin).
             Each shows either an uploaded logo auto-converted to a one-colour
             mask (client-logo__mark--img, painted by CSS) or a built-in
             line-art SVG mark. --}}
        @php
            $clientMark = function ($c) {
                if ($c->monoUrl()) {
                    // its own proportions, so the row keeps its place while it loads
                    $size = $c->monoSize();
                    return '<img class="client-logo__mark client-logo__mark--img" src="'
                        .e($c->monoUrl()).'"'.($size ? ' width="'.$size[0].'" height="'.$size[1].'"' : '')
                        .' alt="'.e($c->name).' logo" loading="lazy" decoding="async">';
                }
                if ($c->mark) {
                    return '<svg class="client-logo__mark" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">'.$c->mark.'</svg>';
                }
                return '<span class="client-logo__mark client-logo__mark--text" aria-hidden="true">'.e(mb_substr($c->name, 0, 1)).'</span>';
            };
        @endphp

        <div class="clients__head">
            <p class="section-label section-label--center" {!! bitext('clients_trusted') !!}>{{ bival('clients_trusted') }}</p>
            <h2 class="clients__title" {!! bitext('clients_title') !!}>{!! bival('clients_title') !!}</h2>
            <p class="clients__hint" {!! bitext('clients_hint') !!}>{{ bival('clients_hint') }}</p>
        </div>
        <div class="clients__track-wrap">
            <div class="clients__track">
                {{-- two identical sets = seamless marquee loop --}}
                @foreach ([false, true] as $dup)
                    @foreach ($clients as $c)
                        <div class="client-logo" @if($dup) aria-hidden="true" @endif>
                            {!! $clientMark($c) !!}
                            <span class="client-logo__name">{{ $c->name }}</span>
                            <span class="client-logo__sub" dir="auto" data-en="{{ $c->sub_en }}" data-ku="{{ rtl_slot($c->sub_en, $c->sub_ku, $c->sub_ar) }}">{{ bi($c->sub_en, $c->sub_ku, $c->sub_ar) }}</span>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>

        {{-- Liquid-glass roster: opens from any marquee logo and lays out
             every client & partner at once, so the moving strip is never
             the only way to read the list. --}}
        <div class="clients-modal" id="clientsModal" role="dialog" aria-modal="true" aria-label="All clients and partners">
            <div class="clients-modal__panel">
                <button class="clients-modal__close" id="clientsModalClose" aria-label="Close">✕</button>
                <p class="section-label section-label--center" {!! bitext('clients_trusted') !!}>{{ bival('clients_trusted') }}</p>
                <h3 class="clients-modal__title" {!! bitext('clients_title') !!}>{!! bival('clients_title') !!}</h3>
                <div class="clients-modal__grid">
                    @foreach ($clients as $c)
                        <div class="clients-modal__tile" style="--i: {{ $loop->index }}">
                            {!! $clientMark($c) !!}
                            <span class="client-logo__name">{{ $c->name }}</span>
                            <span class="client-logo__sub" dir="auto" data-en="{{ $c->sub_en }}" data-ku="{{ rtl_slot($c->sub_en, $c->sub_ku, $c->sub_ar) }}">{{ bi($c->sub_en, $c->sub_ku, $c->sub_ar) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <!-- ========== SELECTED WORK ========== -->

    <section class="pg" id="projects">

        <div class="pg__head">
            {{-- The heading opens every project on a page of its own (/projects). --}}
            @php $pageCount = $projects->filter(fn ($p) => $p->hasPage())->count(); @endphp
            <a class="pg__head-left pg__head-link" href="{{ site_url('/projects') }}"
               data-href-en="{{ site_url('/projects', 'en') }}" data-href-ku="{{ site_url('/projects', 'ku') }}">
                <p class="section-label" {!! bitext('pg_label') !!}>{{ bival('pg_label') }}</p>
                <h2 class="pg__title"><span {!! bitext('pg_title') !!}>{{ bival('pg_title') }}</span></h2>
                <span class="pg__all">
                    <span {!! bitext('pg_all') !!}>{{ bival('pg_all') }}</span>
                    <span class="pg__all-n">{{ $pageCount }}</span>
                    <span class="pg__all-arrow" aria-hidden="true">↗&#xFE0E;</span>
                </span>
            </a>
            <div class="pg__head-right">
                <div class="pg__search-wrap">
                    <input class="pg__search" id="pgSearch" type="search" placeholder="{{ bi('Search projects…', 'گەڕان بۆ پرۆژەکان…', 'ابحث في المشاريع…') }}" data-en-ph="Search projects…" data-ku-ph="{{ rtl_slot('Search projects…', 'گەڕان بۆ پرۆژەکان…', 'ابحث في المشاريع…') }}" autocomplete="off" aria-label="Search projects">
                    <span class="pg__search-icon" aria-hidden="true">↗&#xFE0E;</span>
                </div>
                {{-- Filter row is generated from the database-managed categories
                     (Portfolio → Projects → Categories), so add / remove / reorder there
                     is reflected here. "All" stays put. --}}
                <div class="pg__filter" role="group" aria-label="Filter by typology">
                    <button class="pgf-btn active" data-filter="all" {!! bitext('filter_all') !!}>{{ bival('filter_all') }}</button>
                    @foreach($categories as $cat)
                    <button class="pgf-btn" data-filter="{{ $cat->key }}" dir="auto" data-en="{{ $cat->name }}" data-ku="{{ rtl_slot($cat->name, $cat->name_ku ?: $cat->name, $cat->name_ar) }}">{{ bi($cat->name, $cat->name_ku, $cat->name_ar) }}</button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="pg__slider">
            <button class="pg__nav pg__nav--prev" id="pgPrev" aria-label="Previous projects" disabled>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M15 5l-7 7 7 7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>

            <div class="pg__grid" id="pgGrid">

            @foreach ($projects as $i => $project)
            {{-- Map-only projects — and anything still without a photo — get a pin and
                 a line in the statistics but no gallery card. The overlay skips them
                 too, so "next project" never lands on an empty frame. The loop index
                 is kept so data-index still lines up with the map data. --}}
            @if ($project->map_only || ! $project->coverThumbUrl()) @continue @endif
            {{-- data-categories holds every category key (a project can be e.g. residential
                 *and* exterior); data-search is the haystack for the search box. --}}
            <article class="pgc{{ $project->size === 'large' ? ' pgc--large' : ($project->size === 'wide' ? ' pgc--wide' : '') }}" data-index="{{ $i }}" data-category="{{ $project->category }}" data-categories="{{ implode(' ', $project->categoryKeys()) }}" data-name="{{ $project->name }}" data-search="{{ $project->searchText() }}">
                <div class="pgc__inner">
                    <img class="pgc__img" data-src="{{ $project->coverThumbUrl() }}" src="" alt="{{ $project->name }}" loading="lazy" decoding="async" style="object-position: {{ $project->coverPosition() }}">
                    <div class="pgc__overlay">
                        {{-- in the page's language, and both, for the in-place language switch --}}
                        @php $pgcSecond = site_lang() === 'ar' ? 'ar' : 'ku'; @endphp
                        <div class="pgc__status pgc__status--{{ $project->statusClass() }}"
                             data-en="{{ $project->statusBadge() }}" data-ku="{{ $project->statusLabel($pgcSecond) }}">{{ site_lang() === 'en' ? $project->statusBadge() : $project->statusLabel($pgcSecond) }}</div>
                        <div class="pgc__info">
                            <div class="pgc__num">{{ $project->num }}</div>
                            <h3 class="pgc__name" dir="auto" data-en="{{ $project->name }}" data-ku="{{ rtl_slot($project->name, $project->name_ku ?: $project->name, $project->name_ar) }}">{{ bi($project->name, $project->name_ku, $project->name_ar) }}</h3>
                            <p class="pgc__meta" data-en="{{ $project->metaLabel() }}" data-ku="{{ $project->metaLabelIn($pgcSecond) }}">{{ site_lang() === 'en' ? $project->metaLabel() : $project->metaLabelIn($pgcSecond) }}</p>
                        </div>
                        <span class="pgc__cta"><span {!! bitext('pg_view_project') !!}>{{ bival('pg_view_project') }}</span> <span class="pgc__cta-arrow">↗&#xFE0E;</span></span>
                    </div>
                </div>
                {{-- The card's own address. A plain click still opens the overlay
                     (which then shows this same address); the link is what lets a
                     visitor open the project in a new tab or copy it, and what
                     lets search engines find every project page from here.
                     Not draggable, or a mouse drag along the row would pick up the
                     link instead of scrolling it. --}}
                <a class="pgc__link" href="{{ $project->pageUrl(site_lang()) }}" draggable="false" aria-label="{{ bi($project->name, $project->name_ku, $project->name_ar) }}"
                   data-href-en="{{ $project->pageUrl('en') }}" data-href-ku="{{ $project->pageUrl('ku') }}"></a>
                {{-- Save it to the visitor's list (saved.js) — above the link, and
                     handled before the card, so it never opens the project too. --}}
                @if ($project->hasPage())
                    <button class="pgc__save" type="button" data-save="{{ $project->slug }}" data-save-at="grid" aria-pressed="false" aria-label="{{ bival('save_add') }}">
                        @include('partials.icon-heart')
                    </button>
                @endif
            </article>
            @endforeach

            </div><!-- /.pg__grid (slider track) -->

            <button class="pg__nav pg__nav--next" id="pgNext" aria-label="Next projects">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M9 5l7 7-7 7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
        </div><!-- /.pg__slider -->

        <div class="pg__progress" aria-hidden="true"><div class="pg__progress-bar" id="pgProgress"></div></div>

        {{-- Horizontal-scroll affordance: tells visitors the row continues
             sideways (and how much is there). Dismisses itself after the
             first real scroll. On touch, where the arrows are hidden, this
             chip + the edge fade + the peek nudge are the only cues. --}}
        @php $galleryCount = $projects->where('map_only', false)->count(); @endphp
        <p class="pg__more" id="pgMore" aria-hidden="true">
            <span class="pg__more-label"
                  dir="auto" data-en="slide for more — {{ $galleryCount }} projects"
                  data-ku="{{ rtl_slot('', 'ڕایبکێشە بۆ زیاتر — '.$galleryCount.' پرۆژە', 'اسحب للمزيد — '.$galleryCount.' مشروعاً') }}">{{ bi('slide for more — '.$galleryCount.' projects', 'ڕایبکێشە بۆ زیاتر — '.$galleryCount.' پرۆژە', 'اسحب للمزيد — '.$galleryCount.' مشروعاً') }}</span>
            <span class="pg__more-arrow">⟶</span>
        </p>

        <div class="pg__empty" id="pgEmpty" hidden>
            <p class="pg__empty-text" {!! bitext('pg_empty') !!}>{{ bival('pg_empty') }}</p>
        </div>

    </section>

    {{-- ========== CLIENT QUOTES ==========
         One quote at a time, turning over on its own (engage.js). Each quote
         is shown only in the language it was given in — the language toggle
         hides the rest — and the whole band only exists in a language that
         has at least one. --}}
    @php
        $quotesEn = $quotes->filter(fn ($q) => filled($q->quote_en));
        $quotesKu = $quotes->filter(fn ($q) => filled($q->quote_ku));
    @endphp
    @if ($quotes->isNotEmpty())
        <section class="quotes" id="quotes" @if ($quotesEn->isNotEmpty()) data-has-en @endif @if ((site_lang() === "ar" ? $quotesEn : $quotesKu)->isNotEmpty()) data-has-ku @endif>
            <p class="section-label section-label--center" {!! bitext('quotes_label') !!}>{{ bival('quotes_label') }}</p>
            <h2 class="quotes__title" {!! bitext('quotes_title') !!}>{{ bival('quotes_title') }}</h2>
            {{-- the studio's rating on Google, once it is set in the admin: a link to read the reviews there --}}
            @if (($google = google_profile()) && $google['rating'])
                <a class="gbadge" href="{{ $google['url'] }}" target="_blank" rel="noopener">
                    <span class="gbadge__stars" style="--r: {{ $google['rating'] / 5 * 100 }}%" role="img" aria-label="{{ $google['rating'] }} / 5"></span>
                    <b class="gbadge__rating">{{ \App\Models\Landmark::digits(number_format($google['rating'], 1)) }}</b>
                    @if ($google['count'])<span class="gbadge__count">{{ str_replace('{n}', \App\Models\Landmark::digits($google['count']), bival('google_badge')) }}</span>@endif
                    <span class="gbadge__read">{{ bival('google_badge_read') }} <span aria-hidden="true">↗&#xFE0E;</span></span>
                </a>
            @endif
            <div class="quotes__stage" id="quotesStage" aria-live="polite">
                @foreach ($quotes as $q)
                    <figure class="quote" @if (filled($q->quote_en)) data-has-en @endif @if (filled(rtl_slot($q->quote_en, $q->quote_ku, $q->quote_ar))) data-has-ku @endif>
                        @include('partials.client-voice', ['cvQuote' => $q])
                        <blockquote class="quote__text" dir="auto" data-en="{{ $q->quote('en') }}" data-ku="{{ rtl_slot($q->quote("en"), $q->quote("ku"), $q->quote("ar")) }}">{{ $q->quote(site_lang()) ?: $q->quote_en ?: $q->quote_ku }}</blockquote>
                        <figcaption class="quote__by">
                            <span class="quote__name" data-en="{{ $q->personName('en') }}" data-ku="{{ rtl_slot($q->personName("en"), $q->personName("ku"), $q->personName("ar")) }}">{{ $q->personName(site_lang()) }}</span>
                            @php $roleEn = $q->attribution('en'); $roleKu = rtl_slot($roleEn, $q->attribution("ku"), $q->attribution("ar")); @endphp
                            @if (filled($roleEn) || filled($roleKu))
                                <span class="quote__role" data-en="{{ $roleEn }}" data-ku="{{ $roleKu }}">{{ slot($roleEn, $roleKu) }}</span>
                            @endif
                            @if ($q->project?->hasPage())
                                <a class="quote__project" href="{{ $q->project->pageUrl(site_lang()) }}"
                                   data-href-en="{{ $q->project->pageUrl('en') }}" data-href-ku="{{ $q->project->pageUrl('ku') }}">
                                    <span data-en="{{ $q->project->name }}" data-ku="{{ rtl_slot($q->project->name, $q->project->name_ku ?: $q->project->name, $q->project->name_ar) }}">{{ bi($q->project->name, $q->project->name_ku, $q->project->name_ar) }}</span>
                                    <span class="quote__arrow" aria-hidden="true">↗&#xFE0E;</span>
                                </a>
                            @endif
                        </figcaption>
                    </figure>
                @endforeach
            </div>
            <div class="quotes__dots" id="quotesDots" aria-hidden="true"></div>
        </section>
    @endif

    <!-- ========== ABOUT ========== -->
    <section class="about" id="about">
        <div class="about__bg" aria-hidden="true">
            <span class="about__aurora"></span>
            <div class="about__pattern"></div>
        </div>

        <div class="about__inner">

            <div class="about__top">
                <div class="about__text">
                    <p class="section-label" {!! bitext('about_label') !!}>{{ bival('about_label') }}</p>
                    <h2 class="about__heading" {!! bitext('about_heading') !!}>
                        {!! bival('about_heading') !!}
                    </h2>
                    <p class="about__bio" {!! bitext('about_bio_1') !!}>
                        {{ bival('about_bio_1') }}
                    </p>
                    <p class="about__bio" {!! bitext('about_bio_2') !!}>
                        {{ bival('about_bio_2') }}
                    </p>
                    {{-- on to the architect's own page --}}
                    <a class="about__more" href="{{ site_url(\App\Support\Architect::PATH) }}">
                        <span {!! bitext('arch_link') !!}>{{ bival('arch_link') }}</span>
                        <span class="about__more-arrow" aria-hidden="true">→</span>
                    </a>
                    <blockquote class="about__quote">
                        <p {!! bitext('about_quote') !!}>{{ bival('about_quote') }}</p>
                        <cite {!! bitext('about_quote_cite') !!}>{{ bival('about_quote_cite') }}</cite>
                    </blockquote>
                </div>

                <div class="about__side">
                    <div class="about__portrait">
                        <div class="portrait__sun" id="portraitSun"></div>
                        {{-- .img-ph again — the portrait frame's ::after is the
                             caption gradient. --}}
                        <span class="img-ph is-loading" aria-hidden="true"></span>
                        {{-- the 800px WebP for this frame where it exists (Architect::portraitSrcset) --}}
                        @php $portraitSet = \App\Support\Architect::portraitSrcset(); @endphp
                        <img class="portrait__photo" src="{{ \App\Models\Project::resolveImage(setting('about_portrait_img')) }}" @if ($portraitSet) srcset="{{ $portraitSet }}" sizes="(max-width: 900px) 90vw, 440px" @endif loading="lazy" decoding="async" alt="Aram Mizuri, Principal Architect" style="position:absolute;inset:0;z-index:1;">
                        <span class="portrait__corner portrait__corner--tl"></span>
                        <span class="portrait__corner portrait__corner--br"></span>
                        <div class="portrait__caption" {!! bitext('about_portrait_caption') !!}>{{ bival('about_portrait_caption') }}</div>
                    </div>
                </div>
            </div>

            {{-- Figures moved to the hero stat band — About now closes on the
                 portrait + quote, keeping the section personal, not numeric. --}}
        </div>
    </section>

@include('home.services')

@include('home.process')

    <!-- ========== PARTNERS ========== -->
    {{-- Who we build with: the partners' one-colour logos, each opening its
         partner on /partners. Only once a partner is shown on the website. --}}
    @php $homePartners = rescue(fn () => \App\Models\Partner::publishedOrdered()->limit(12)->get(), collect(), false); @endphp
    @if ($homePartners->isNotEmpty())
        <section class="hp-partners" id="partners">
            <div class="hp-partners__head">
                <p class="section-label" {!! bitext('partners_label') !!}>{{ bival('partners_label') }}</p>
                <h2 class="hp-partners__title" {!! bitext('partners_title') !!}>{{ bival('partners_title') }}</h2>
                <p class="hp-partners__text" {!! bitext('partners_home_text') !!}>{{ bival('partners_home_text') }}</p>
            </div>
            <ul class="hp-partners__wall">
                @foreach ($homePartners as $p)
                    <li>
                        <a class="hp-partners__tile" href="{{ site_url('/partners') }}#{{ $p->anchor() }}"
                           data-href-en="{{ site_url('/partners', 'en') }}#{{ $p->anchor() }}" data-href-ku="{{ site_url('/partners', 'ku') }}#{{ $p->anchor() }}">
                            @if ($p->monoUrl())
                                <span class="hp-partners__logo" style="--logo: url('{{ $p->monoUrl() }}')" role="img" aria-label="{{ $p->name }}"></span>
                            @else
                                <span class="hp-partners__word">{{ $p->name }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
            <a class="hp-partners__all" href="{{ site_url('/partners') }}"
               data-href-en="{{ site_url('/partners', 'en') }}" data-href-ku="{{ site_url('/partners', 'ku') }}">
                <span {!! bitext('partners_all') !!}>{{ bival('partners_all') }}</span> <span class="hp-partners__arrow" aria-hidden="true">→</span>
            </a>
        </section>
    @endif

    {{-- ========== HERITAGE ==========
         Kurdistan's architectural heritage (/kurdistan-heritage), as seven
         doors in order of age: tall photographs standing side by side, the
         one under the pointer opening wide. Each leads to its landmark's
         page; the figures and the button lead to the collection and its map. --}}
    @php
        use App\Models\Landmark;
        // shortcut: the seven are picked here by hand, upgrade to a "featured" switch in the admin if the studio wants to choose
        $doorSlugs = ['gobekli-tepe', 'erbil-citadel', 'mount-nemrut', 'taq-e-bostan', 'amed-city-walls', 'mudhafaria-minaret', 'ishak-pasha-palace'];
        $withPhoto = $heritage->filter(fn ($l) => filled($l->image));
        $doors = $withPhoto->whereIn('slug', $doorSlugs);
        // any of the seven unpublished or renamed: the oldest of the others stand in
        $doors = $doors->concat($withPhoto->whereNotIn('slug', $doorSlugs)->sortBy('year_from')->take(7 - $doors->count()))->sortBy('year_from')->values();
        $open = max(0, (int) $doors->search(fn ($l) => $l->slug === 'erbil-citadel'));
        // the page's second language: Kurdish, or Arabic on /ar (see rtl_slot)
        $hl2 = site_lang() === 'ar' ? 'ar' : 'ku';
        $oldestBuilt = $heritage->whereNotNull('year_from')->where('year_from', '>=', -12000)->min('year_from');
        $hYears = $oldestBuilt !== null ? number_format(floor(((int) date('Y') - $oldestBuilt) / 1000) * 1000).'+' : null;
        $hFigures = array_filter([
            'landmarks_stat_landmarks' => (string) $heritage->count(),
            'landmarks_stat_unesco' => (string) $heritage->where('unesco', true)->count(),
            'landmarks_stat_years' => $hYears,
            'landmarks_stat_parts' => (string) $heritage->pluck('region')->unique()->count(),
        ]);
        $hUrl = fn (string $lang) => site_url(Landmark::PATH, $lang);
    @endphp
    @if ($doors->count() >= 3)
        <section class="heritage" id="heritage" aria-labelledby="heritageTitle">
            <div class="heritage__inner">
                <header class="heritage__head reveal">
                    <div>
                        <p class="section-label section-label--light" {!! bitext('landmarks_label') !!}>{{ bival('landmarks_label') }}</p>
                        <h2 class="heritage__title" id="heritageTitle" {!! bitext('landmarks_title') !!}>{{ bival('landmarks_title') }}</h2>
                    </div>
                    <div class="heritage__lede">
                        <p class="heritage__desc" {!! bitext('heritage_home_desc') !!}>{{ bival('heritage_home_desc') }}</p>
                        <a class="heritage__cta" href="{{ $hUrl(site_lang()) }}" data-href-en="{{ $hUrl('en') }}" data-href-ku="{{ $hUrl(site_lang() === 'en' ? 'ku' : site_lang()) }}">
                            <span {!! bitext('heritage_home_cta') !!}>{{ bival('heritage_home_cta') }}</span>
                            <span class="heritage__cta-arrow" aria-hidden="true">→</span>
                        </a>
                    </div>
                </header>

                <dl class="heritage__figures reveal">
                    @foreach ($hFigures as $key => $n)
                        <div class="heritage__figure">
                            <dt {!! bitext($key) !!}>{{ bival($key) }}</dt>
                            <dd data-en="{{ $n }}" data-ku="{{ Landmark::digits($n, $hl2) }}">{{ Landmark::digits($n) }}</dd>
                        </div>
                    @endforeach
                </dl>

                <ol class="heritage__doors reveal">
                    @foreach ($doors as $i => $l)
                        @php $n = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT); @endphp
                        <li class="hg-door{{ $i === $open ? ' is-open' : '' }}">
                            <a class="hg-door__link" href="{{ $l->url() }}" data-href-en="{{ $l->url('en') }}" data-href-ku="{{ $l->url(site_lang() === 'en' ? 'ku' : site_lang()) }}">
                                <img class="hg-door__img" src="{{ $l->imageUrl('md') }}" alt="" loading="lazy" decoding="async">
                                <span class="hg-door__spine" aria-hidden="true">
                                    <span dir="auto" data-en="{{ $l->tr('name', 'en') }}" data-ku="{{ $l->tr('name', $hl2) }}">{{ $l->tr('name') }}</span>
                                </span>
                                <span class="hg-door__card">
                                    <span class="hg-door__n" data-en="{{ $n }}" data-ku="{{ Landmark::digits($n, $hl2) }}">{{ Landmark::digits($n) }}</span>
                                    <span class="hg-door__name" dir="auto" data-en="{{ $l->tr('name', 'en') }}" data-ku="{{ $l->tr('name', $hl2) }}">{{ $l->tr('name') }}</span>
                                    <span class="hg-door__place" dir="auto" data-en="{{ $l->tr('place', 'en') }}" data-ku="{{ $l->tr('place', $hl2) }}">{{ $l->tr('place') }}</span>
                                    <span class="hg-door__more"><span {!! bitext('landmarks_read') !!}>{{ bival('landmarks_read') }}</span> <span class="heritage__cta-arrow" aria-hidden="true">→</span></span>
                                </span>
                                <span class="hg-door__year" dir="auto" data-en="{{ Landmark::yearLabel($l->year_from, 'en') }}" data-ku="{{ Landmark::yearLabel($l->year_from, $hl2) }}">{{ Landmark::yearLabel($l->year_from) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

@include('home.estimate')

@include('home.contact')

    </main>

@include('home.footer')

@include('home.overlay')

    {{-- The #kurdOutline SVG filter that used to live here (dilate/erode to
         dissolve the province seams into one united border) is gone: its work
         area scaled with the geometry, not the screen, so it froze pinch-zoom on
         iPad and iPhone. The same outline is now composited on a viewport-sized
         canvas — see KurdistanOutline in script.js. --}}

    @include('partials.whatsapp-fab', ['text' => ['en' => \App\Support\SiteText::en('wa_text'), 'ku' => \App\Support\SiteText::second('wa_text')], 'hideOver' => '#map, #contact'])

    {{-- Leaflet, self-hosted (see the note in the head) and fetched on
         demand by script.js (leafletReady) as a map comes near. --}}
    @php $v = fn (string $f) => asset($f).'?v='.filemtime(public_path($f)); @endphp
    <script>window.__LEAFLET__ = {!! json_encode([
        'css' => $v('vendor/leaflet.css'), 'gestureCss' => $v('vendor/leaflet-gesture-handling.min.css'),
        'js' => $v('vendor/leaflet.js'), 'gesture' => $v('vendor/leaflet-gesture-handling.min.js'),
        // the hero's 3D stage, also fetched after the page is up (below)
        'three' => asset('vendor/three.min.js'), 'hero3d' => $v('hero3d.js'),
    ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!};</script>
    {{-- Plain JSON, Kurdish left as Kurdish: Js::from() wrapped it in a string
         and escaped every letter as \uXXXX, which made this block 60% larger.
         The HEX flags keep it inert inside <script> (no "</script>", no quotes). --}}
    <script>window.__SITE__ = {!! json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!};</script>
    <script src="{{ asset('script.js') }}?v={{ filemtime(public_path('script.js')) }}" defer></script>
    <script src="{{ asset('site-index.js') }}?v={{ filemtime(public_path('site-index.js')) }}" defer></script>
    <script src="{{ asset('map-growth.js') }}?v={{ filemtime(public_path('map-growth.js')) }}" defer></script>
    @include('partials.sketch-assets')
    <script src="{{ asset('engage.js') }}?v={{ filemtime(public_path('engage.js')) }}" defer></script>
    @if ($quotesHaveVoices)
        <script src="{{ asset('pencil.js') }}?v={{ filemtime(public_path('pencil.js')) }}" defer></script>
        <script src="{{ asset('voices.js') }}?v={{ filemtime(public_path('voices.js')) }}" defer></script>
    @endif
    @include('partials.saved-script')
    {{-- The hero's 3D stage (three.js, ~600 KB, and hero3d.js) used to load
         with the page, and the loader could not leave until it had been
         built — so it decided how soon anyone saw the site at all. It now
         loads once the page is up and the browser has a moment to spare
         (startHero3d in script.js); until then, the hero's own background
         stands where it will be. --}}
</body>
</html>
