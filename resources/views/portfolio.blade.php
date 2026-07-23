<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aram Mizuri Architecture | Erbil, Kurdistan</title>
    <meta name="description" content="Aram Mizuri Architecture — a leading architectural practice based in Erbil, Kurdistan Region. Residential, cultural, commercial and urban design.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Vazirmatn:wght@200;300;400;500;600;700&display=swap" rel="stylesheet">
    {{-- Kurdish sun — same mark as the loader --}}
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('style.css') }}?v={{ filemtime(public_path('style.css')) }}">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet-gesture-handling@1.2.2/dist/leaflet-gesture-handling.min.css">
</head>
<body>

    <!-- Page Loader -->
    <div class="loader" id="loader">
        <div class="loader__sun" id="loaderSun"></div>
        <div class="loader__text">Aram Mizuri Architecture</div>
        <div class="loader__bar"><div class="loader__progress"></div></div>
    </div>

    <!-- Custom Cursor -->
    <div class="cursor" id="cursor"></div>
    <div class="cursor-follower" id="cursorFollower"></div>

    <!-- Navigation -->
    <nav class="nav" id="nav">
        <a href="#home" class="nav__logo">
            <span class="logo-name" {!! bitext('brand_name') !!}>{{ bival('brand_name') }}</span>
            <span class="logo-sub" {!! bitext('brand_sub') !!}>{{ bival('brand_sub') }}</span>
        </a>

        <div class="nav__bar">
            <a href="#projects" class="nav__link" {!! bitext('nav_projects') !!}>{{ bival('nav_projects') }}</a>
            <a href="#map"      class="nav__link" {!! bitext('nav_map') !!}>{{ bival('nav_map') }}</a>
            <a href="#about"    class="nav__link" {!! bitext('nav_about') !!}>{{ bival('nav_about') }}</a>
            <a href="#services" class="nav__link" {!! bitext('nav_services') !!}>{{ bival('nav_services') }}</a>
            <a href="#process"  class="nav__link" {!! bitext('nav_process') !!}>{{ bival('nav_process') }}</a>
            <a href="#contact"  class="nav__link" {!! bitext('nav_contact') !!}>{{ bival('nav_contact') }}</a>
            <span class="nav__sep"></span>
            <button class="lang-toggle" id="langToggle" title="Switch to Kurdish / گۆڕانی زمان">کوردی</button>
        </div>

        <button class="nav__hamburger" id="hamburger" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
    </nav>

    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <a href="#projects" class="mobile-menu__link" {!! bitext('nav_projects') !!}>{{ bival('nav_projects') }}</a>
        <a href="#map"      class="mobile-menu__link" {!! bitext('nav_map') !!}>{{ bival('nav_map') }}</a>
        <a href="#about"    class="mobile-menu__link" {!! bitext('nav_about') !!}>{{ bival('nav_about') }}</a>
        <a href="#services" class="mobile-menu__link" {!! bitext('nav_services') !!}>{{ bival('nav_services') }}</a>
        <a href="#process"  class="mobile-menu__link" {!! bitext('nav_process') !!}>{{ bival('nav_process') }}</a>
        <a href="#contact"  class="mobile-menu__link" {!! bitext('nav_contact') !!}>{{ bival('nav_contact') }}</a>
    </div>

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
                [$l1MainEn, $l1ByEn] = $splitBy(setting('hero_title_line1_en'));
                [$l1MainKu, $l1ByKu] = $splitBy(setting('hero_title_line1_ku'));
            @endphp
            <h1 class="hero__title">
                <span class="hero__line"><span class="hero__line-in"><span data-en="{{ $l1MainEn }}" data-ku="{{ $l1MainKu }}">{{ $l1MainEn }}</span><span class="hero__by" data-en="{{ $l1ByEn }}" data-ku="{{ $l1ByKu }}">{{ $l1ByEn }}</span></span></span>
                <span class="hero__line"><span class="hero__line-in hero__line-in--accent" data-en="{{ setting('hero_title_line2_en') }}" data-ku="{{ setting('hero_title_line2_ku') }}">{{ setting('hero_title_line2_en') }}</span></span>
            </h1>

            {{-- Practice figures — rendered as the 3D glass card ring in
                 hero3d.js. This hidden list is the single source of truth
                 (and what screen readers announce); the canvas is decorative. --}}
            @php
                $areaNum = (int) $stats['area_short'];
                $areaSuf = trim(preg_replace('/[\d.,]/', '', $stats['area_short']));
            @endphp
            <div id="heroStatsData" class="sr-only">
                <span data-value="{{ $stats['projects'] }}" data-en="Projects" data-ku="پرۆژە">{{ $stats['projects'] }} Projects</span>
                <span data-value="{{ (int) setting('about_stat2_num') }}" data-en="Clients" data-ku="کڕیار">{{ (int) setting('about_stat2_num') }} Clients</span>
                <span data-value="{{ $stats['cities'] }}" data-en="Cities" data-ku="شار">{{ $stats['cities'] }} Cities</span>
                <span data-value="{{ $stats['countries'] }}" data-en="Countries" data-ku="وڵات">{{ $stats['countries'] }} Countries</span>
                <span data-value="{{ $areaNum }}" data-suffix="{{ $areaSuf }}" data-en="m² Designed" data-ku="م² دیزاینکراو">{{ $stats['area_short'] }} m² Designed</span>
            </div>
        </div>

        {{-- Caption + CTA + drag hint live at the sheet's bottom centre on
             desktop (clear of the ring); on phones they stay in the flow
             right under the headline. --}}
        <div class="hero__foot">
            <p class="hero__sub" data-en="{{ setting('hero_sub_en') }}" data-ku="{{ setting('hero_sub_ku') }}">{{ setting('hero_sub_en') }}</p>
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

        {{-- Mobile-only language switch — a small fixed KU/EN badge in the
             top-left corner (the nav bar and its کوردی pill are hidden
             below 768px). Label managed by initLang in script.js. --}}
        <button class="hero__lang" id="heroLang" type="button" aria-label="Switch language / گۆڕینی زمان">KU</button>
    </section>

    <!-- ========== STATS BAND ========== -->
    {{-- Practice figures — lifted out of the 3D hero ring into their own
         full-width band (matches the Clients section width). Counts up on
         scroll via initStatbar; finger-drag to swipe on mobile. --}}
    @php
        $sbAreaNum = (int) $stats['area_short'];
        $sbAreaSuf = trim(preg_replace('/[\d.,]/', '', $stats['area_short']));
        $statbar = [
            ['v' => (int) $stats['projects'],         's' => '',         'en' => 'Projects',    'ku' => 'پرۆژە'],
            ['v' => (int) setting('about_stat2_num'), 's' => '',         'en' => 'Clients',     'ku' => 'کڕیار'],
            ['v' => (int) $stats['cities'],           's' => '',         'en' => 'Cities',      'ku' => 'شار'],
            ['v' => (int) $stats['countries'],        's' => '',         'en' => 'Countries',   'ku' => 'وڵات'],
            ['v' => $sbAreaNum,                       's' => $sbAreaSuf, 'en' => 'm² Designed', 'ku' => 'م² دیزاینکراو'],
        ];
    @endphp
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
                        <span class="statbar__label" data-en="{{ $st['en'] }}" data-ku="{{ $st['ku'] }}">{{ $st['en'] }}</span>
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

    <!-- ========== PROJECTS MAP ========== -->
    <section class="proj-map" id="map">

        <!-- Edge-darkening veil -->
        <div class="proj-map__veil"></div>

        <!-- Leaflet map canvas -->
        <div id="projectMapEl"></div>

        <!-- Section label (top-left overlay) -->
        <div class="proj-map__label">
            <p class="section-label" {!! bitext('map_label') !!}>{{ bival('map_label') }}</p>
            <h2 class="proj-map__title" {!! bitext('map_title') !!}>{!! bival('map_title') !!}</h2>
        </div>

        <!-- Floating project card — liquid glass; JS positions it near the pin.
             Two modes: a photo card, or (for map-only projects) a spec card
             with no image, toggled via .map-card--specs. -->
        <div class="map-card" id="mapCard" role="dialog" aria-label="Project preview">
            <span class="map-card__glow" aria-hidden="true"></span>
            <span class="map-card__sheen" aria-hidden="true"></span>
            <button class="map-card__close" id="mapCardClose" aria-label="Close">✕</button>

            <div class="map-card__img-wrap">
                <img class="map-card__img" id="mapCardImg" src="" alt="">
                <span class="map-card__num" id="mapCardNum"></span>
            </div>

            <div class="map-card__body">
                <span class="map-card__badge" id="mapCardBadge" hidden></span>
                <h3 class="map-card__name" id="mapCardName"></h3>
                <p class="map-card__meta" id="mapCardMeta"></p>
                <p class="map-card__excerpt" id="mapCardExcerpt"></p>

                {{-- spec grid shown only for map-only (image-less) projects --}}
                <dl class="map-card__specs" id="mapCardSpecs">
                    <div class="map-card__spec">
                        <dt data-en="Type" data-ku="جۆر">Type</dt>
                        <dd id="mcSpecType"></dd>
                    </div>
                    <div class="map-card__spec">
                        <dt data-en="Year" data-ku="ساڵ">Year</dt>
                        <dd id="mcSpecYear"></dd>
                    </div>
                    <div class="map-card__spec">
                        <dt data-en="Plot Area" data-ku="ڕووبەری زەوی">Plot Area</dt>
                        <dd id="mcSpecArea"></dd>
                    </div>
                    <div class="map-card__spec">
                        <dt data-en="Location" data-ku="شوێن">Location</dt>
                        <dd id="mcSpecLoc"></dd>
                    </div>
                </dl>

                <button class="map-card__cta" id="mapCardCta">
                    <span {!! bitext('map_view_project') !!}>{{ bival('map_view_project') }}</span>
                    <svg width="14" height="10" viewBox="0 0 14 10" fill="none"><path d="M1 5h12M8 1l5 4-5 4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </div>
        </div>

        <!-- Zoom controls -->
        <div class="proj-map__zoom">
            <button class="map-zoom-btn" id="mapZoomIn"  aria-label="Zoom in">+</button>
            <button class="map-zoom-btn" id="mapZoomOut" aria-label="Zoom out">−</button>
        </div>

        <!-- Bottom stat bar -->
        <div class="proj-map__stats">
            <div class="map-stat"><span class="map-stat__val">{{ $stats['projects'] }}</span><span class="map-stat__lbl" {!! bitext('stat_projects') !!}>{{ bival('stat_projects') }}</span></div>
            <div class="map-stat__div"></div>
            <div class="map-stat"><span class="map-stat__val">{{ $stats['cities'] }}</span><span class="map-stat__lbl" {!! bitext('stat_cities') !!}>{{ bival('stat_cities') }}</span></div>
            <div class="map-stat__div"></div>
            <div class="map-stat"><span class="map-stat__val">{{ $stats['area_short'] }}</span><span class="map-stat__lbl" {!! bitext('stat_area') !!}>{{ bival('stat_area') }}</span></div>
            <div class="map-stat__div"></div>
            <div class="map-stat"><span class="map-stat__val">{{ $stats['countries'] }}</span><span class="map-stat__lbl" {!! bitext('stat_countries') !!}>{{ bival('stat_countries') }}</span></div>
        </div>

    </section>

    <section class="clients" id="clients">
        {{-- Clients & Partners come from the database (managed in the admin).
             Each shows either an uploaded logo auto-converted to a one-colour
             mask (client-logo__mark--img, painted by CSS) or a built-in
             line-art SVG mark. --}}
        @php
            $clientMark = function ($c) {
                if ($c->monoUrl()) {
                    return '<img class="client-logo__mark client-logo__mark--img" src="'
                        .e($c->monoUrl()).'" alt="'.e($c->name).' logo" loading="lazy" decoding="async">';
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
                            <span class="client-logo__sub" data-en="{{ $c->sub_en }}" data-ku="{{ $c->sub_ku }}">{{ $c->sub_en }}</span>
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
                            <span class="client-logo__sub" data-en="{{ $c->sub_en }}" data-ku="{{ $c->sub_ku }}">{{ $c->sub_en }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <!-- ========== SELECTED WORK ========== -->
    <section class="pg" id="projects">

        <div class="pg__head">
            <div class="pg__head-left">
                <p class="section-label" {!! bitext('pg_label') !!}>{{ bival('pg_label') }}</p>
                <h2 class="pg__title" {!! bitext('pg_title') !!}>{{ bival('pg_title') }}</h2>
            </div>
            <div class="pg__head-right">
                <div class="pg__search-wrap">
                    <input class="pg__search" id="pgSearch" type="search" placeholder="Search projects…" data-en-ph="Search projects…" data-ku-ph="گەڕان بۆ پرۆژەکان…" autocomplete="off" aria-label="Search projects">
                    <span class="pg__search-icon" aria-hidden="true">↗&#xFE0E;</span>
                </div>
                {{-- Filter row is generated from the database-managed categories
                     (Manage → Project Categories), so add / remove / reorder there
                     is reflected here. "All" stays put. --}}
                <div class="pg__filter" role="group" aria-label="Filter by typology">
                    <button class="pgf-btn active" data-filter="all" {!! bitext('filter_all') !!}>{{ bival('filter_all') }}</button>
                    @foreach($categories as $cat)
                    <button class="pgf-btn" data-filter="{{ $cat->key }}" data-en="{{ $cat->name }}" data-ku="{{ $cat->name_ku ?: $cat->name }}">{{ $cat->name }}</button>
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
                        <div class="pgc__status pgc__status--{{ $project->statusClass() }}">{{ $project->statusBadge() }}</div>
                        <div class="pgc__info">
                            <div class="pgc__num">{{ $project->num }}</div>
                            <h3 class="pgc__name" data-en="{{ $project->name }}" data-ku="{{ $project->name_ku ?: $project->name }}">{{ $project->name }}</h3>
                            <p class="pgc__meta">{{ $project->metaLabel() }}</p>
                        </div>
                        <span class="pgc__cta"><span {!! bitext('pg_view_project') !!}>{{ bival('pg_view_project') }}</span> <span class="pgc__cta-arrow">↗&#xFE0E;</span></span>
                    </div>
                </div>
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
                  data-en="slide for more — {{ $galleryCount }} projects"
                  data-ku="ڕایبکێشە بۆ زیاتر — {{ $galleryCount }} پرۆژە">slide for more — {{ $galleryCount }} projects</span>
            <span class="pg__more-arrow">⟶</span>
        </p>

        <div class="pg__empty" id="pgEmpty" hidden>
            <p class="pg__empty-text" {!! bitext('pg_empty') !!}>{{ bival('pg_empty') }}</p>
        </div>

    </section>

    <!-- ========== ABOUT ========== -->
    <section class="about" id="about">
        <div class="about__bg" aria-hidden="true">
            <span class="about__aurora"></span>
            <div class="about__pattern"></div>
        </div>

        <div class="about__inner">

            <div class="about__top">
                <div class="about__text">
                    <p class="section-label" data-en="{{ setting('about_label_en') }}" data-ku="{{ setting('about_label_ku') }}">{{ setting('about_label_en') }}</p>
                    <h2 class="about__heading" data-en="{{ setting('about_heading_en') }}" data-ku="{{ setting('about_heading_ku') }}">
                        {!! setting('about_heading_en') !!}
                    </h2>
                    <p class="about__bio" {!! bitext('about_bio_1') !!}>
                        {{ bival('about_bio_1') }}
                    </p>
                    <p class="about__bio" {!! bitext('about_bio_2') !!}>
                        {{ bival('about_bio_2') }}
                    </p>
                    <blockquote class="about__quote">
                        <p {!! bitext('about_quote') !!}>{{ bival('about_quote') }}</p>
                        <cite {!! bitext('about_quote_cite') !!}>{{ bival('about_quote_cite') }}</cite>
                    </blockquote>
                </div>

                <div class="about__side">
                    <div class="about__portrait">
                        <div class="portrait__sun" id="portraitSun"></div>
                        <img class="portrait__photo" src="{{ \App\Models\Project::resolveImage(setting('about_portrait_img')) }}" alt="Aram Mizuri, Principal Architect" style="position:absolute;inset:0;z-index:1;">
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

    <!-- ========== SERVICES ========== -->
    <section class="services" id="services">
        @php
            // stroke-based line icons in the site's drafting language
            $services = [
                ['en' => 'Architectural Design', 'ku' => 'دیزاینی تەڵارسازی',
                 'den' => 'Complete building design — from first concept to approved drawings.',
                 'dku' => 'دیزاینی تەواوی بینا — لە یەکەم بیرۆکەوە تا نەخشەی پەسەندکراو.',
                 'icon' => '<circle cx="18" cy="7" r="3" stroke-width="1.8"/><line x1="16.4" y1="9.8" x2="9" y2="30" stroke-width="2"/><line x1="19.6" y1="9.8" x2="27" y2="30" stroke-width="2"/><path d="M11.5 24.5a13 13 0 0 0 13 0" stroke-width="1.8"/>'],
                ['en' => 'Exterior Design', 'ku' => 'دیزاینی دەرەکی',
                 'den' => 'Façades and outdoor environments that give a building its public face.',
                 'dku' => 'ڕووکار و ژینگەی دەرەوە کە ڕوخساری گشتیی بینا دروستدەکەن.',
                 'icon' => '<rect x="4" y="8" width="15" height="24" stroke-width="1.8"/><line x1="8" y1="14" x2="15" y2="14" stroke-width="1.6"/><line x1="8" y1="20" x2="15" y2="20" stroke-width="1.6"/><line x1="8" y1="26" x2="15" y2="26" stroke-width="1.6"/><circle cx="27" cy="22" r="4" stroke-width="1.8"/><line x1="27" y1="26" x2="27" y2="32" stroke-width="1.8"/><line x1="22" y1="32" x2="32" y2="32" stroke-width="1.8"/>'],
                ['en' => 'Interior Design', 'ku' => 'دیزاینی ناوەکی',
                 'den' => 'Spatial planning, materials and light — interiors made to be lived in.',
                 'dku' => 'پلاندانانی بۆشایی، کەرەستە و ڕووناکی — ناوەوەیەک بۆ ژیان.',
                 'icon' => '<path d="M8 19v-7a3 3 0 0 1 3-3h14a3 3 0 0 1 3 3v7" stroke-width="1.8"/><path d="M5 19a2.5 2.5 0 0 1 5 0v2h16v-2a2.5 2.5 0 0 1 5 0v6a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2z" stroke-width="1.8"/><line x1="9" y1="29" x2="9" y2="32" stroke-width="1.8"/><line x1="27" y1="29" x2="27" y2="32" stroke-width="1.8"/>'],
                ['en' => 'Construction', 'ku' => 'بیناسازی',
                 'den' => "On-site execution and supervision with Kurdistan's finest builders.",
                 'dku' => 'جێبەجێکردن و سەرپەرشتی مەیدانی لەگەڵ باشترین بیناسازانی کوردستان.',
                 'icon' => '<line x1="5" y1="33" x2="31" y2="33" stroke-width="2"/><line x1="11" y1="33" x2="11" y2="5" stroke-width="2"/><line x1="11" y1="5" x2="31" y2="5" stroke-width="2"/><line x1="11" y1="12" x2="18" y2="5" stroke-width="1.8"/><line x1="31" y1="5" x2="31" y2="12" stroke-width="1.8"/><rect x="28.5" y="12" width="5" height="4.5" stroke-width="1.8"/>'],
                ['en' => 'Tendering', 'ku' => 'تەندەرکردن',
                 'den' => 'Bid documentation, evaluation and contractor selection you can trust.',
                 'dku' => 'ئامادەکردنی بەڵگەنامەی تەندەر، هەڵسەنگاندن و هەڵبژاردنی بەڵێندەر.',
                 'icon' => '<path d="M9 3h13l6 6v24H9z" stroke-width="1.8"/><path d="M22 3v6h6" stroke-width="1.8"/><polyline points="14,21 17.5,24.5 23.5,16.5" stroke-width="2"/>'],
                ['en' => 'Customized Furniture', 'ku' => 'مۆبیلیای تایبەتکراو',
                 'den' => 'Bespoke pieces designed and crafted for each project.',
                 'dku' => 'پارچەی تایبەت، دیزاین و دروستکراو بۆ هەر پرۆژەیەک.',
                 'icon' => '<line x1="10" y1="4" x2="10" y2="22" stroke-width="2"/><path d="M10 15h15v7" stroke-width="1.8"/><line x1="8" y1="22" x2="27" y2="22" stroke-width="2"/><line x1="11" y1="22" x2="11" y2="32" stroke-width="1.8"/><line x1="24" y1="22" x2="24" y2="32" stroke-width="1.8"/>'],
                ['en' => 'International Furniture Brands', 'ku' => 'براندە نێودەوڵەتییەکانی مۆبیلیا',
                 'den' => 'Sourcing and supplying world-class furniture brands — worldwide.',
                 'dku' => 'دابینکردنی براندە جیهانییەکانی مۆبیلیا — لە هەموو جیهاندا.',
                 'icon' => '<circle cx="18" cy="18" r="14" stroke-width="1.8"/><ellipse cx="18" cy="18" rx="6.5" ry="14" stroke-width="1.6"/><line x1="4" y1="18" x2="32" y2="18" stroke-width="1.6"/>'],
            ];
        @endphp

        <div class="services__inner">
            <div class="services__head">
                <p class="section-label section-label--center" {!! bitext('services_label') !!}>{{ bival('services_label') }}</p>
                <h2 class="services__title" {!! bitext('services_title') !!}>{{ bival('services_title') }}</h2>
            </div>
            <div class="services__grid">
                @foreach ($services as $i => $s)
                    <div class="service{{ $i >= 4 ? ' service--third' : '' }}">
                        <div class="service__top">
                            <svg class="service__icon" viewBox="0 0 36 36" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $s['icon'] !!}</svg>
                            <span class="service__num">0{{ $i + 1 }}</span>
                        </div>
                        <h3 class="service__name" data-en="{{ $s['en'] }}" data-ku="{{ $s['ku'] }}">{{ $s['en'] }}</h3>
                        <p class="service__desc" data-en="{{ $s['den'] }}" data-ku="{{ $s['dku'] }}">{{ $s['den'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- ========== PROCESS ========== -->
    <section class="process" id="process">
        <div class="process__bg" aria-hidden="true">
            <span class="process__aurora process__aurora--a"></span>
            <span class="process__aurora process__aurora--b"></span>
            <span class="process__grid"></span>
        </div>

        <div class="process__inner">

            <!-- Sticky narrative rail -->
            <aside class="process__aside">
                <p class="section-label" data-en="{{ setting('process_label_en') }}" data-ku="{{ setting('process_label_ku') }}">{{ setting('process_label_en') }}</p>
                <h2 class="process__title" data-en="{{ setting('process_title_en') }}" data-ku="{{ setting('process_title_ku') }}">{{ setting('process_title_en') }}</h2>

                <div class="process__meter">
                    <div class="process__count">
                        <span class="process__count-now" id="procNow">01</span>
                        <span class="process__count-total">/ 04</span>
                    </div>
                    <div class="process__bar"><span class="process__bar-fill" id="procBar"></span></div>
                </div>

                {{-- Liquid-glass design showcase — cycles through every
                     project cover every 3s (initProcessShowcase in script.js).
                     Desktop only: hidden ≤1100px where the aside stacks. --}}
                <div class="process__showcase" id="processShowcase" role="button" tabindex="0" aria-label="Selected designs — open project">
                    <p class="pshow__label" {!! bitext('process_selected') !!}>{{ bival('process_selected') }}</p>
                    <div class="pshow__frame">
                        <img class="pshow__img" alt="" draggable="false">
                        <img class="pshow__img" alt="" draggable="false">
                    </div>
                    <div class="pshow__foot">
                        <span class="pshow__num" id="pshowNum"></span>
                        <span class="pshow__name" id="pshowName"></span>
                        <span class="pshow__go" aria-hidden="true">→</span>
                    </div>
                </div>
            </aside>

            <!-- Scroll-told timeline -->
            <ol class="process__list" id="procList">
                <span class="process__rail" aria-hidden="true"><span class="process__rail-fill" id="procRailFill"></span></span>

                <li class="process__item" data-step="0">
                    <div class="process__node"><span class="process__orb"></span></div>
                    <article class="process__card">
                        <span class="process__ghost" aria-hidden="true" data-en="01" data-ku="٠١">01</span>
                        <div class="process__card-body">
                            <span class="process__kicker" {!! bitext('process_phase1') !!}>{{ bival('process_phase1') }}</span>
                            <h3 class="process__card-title" {!! bitext('process_step1_title') !!}>{{ bival('process_step1_title') }}</h3>
                            <p class="process__card-desc" {!! bitext('process_step1_desc') !!}>{{ bival('process_step1_desc') }}</p>
                        </div>
                    </article>
                </li>

                <li class="process__item" data-step="1">
                    <div class="process__node"><span class="process__orb"></span></div>
                    <article class="process__card">
                        <span class="process__ghost" aria-hidden="true" data-en="02" data-ku="٠٢">02</span>
                        <div class="process__card-body">
                            <span class="process__kicker" {!! bitext('process_phase2') !!}>{{ bival('process_phase2') }}</span>
                            <h3 class="process__card-title" {!! bitext('process_step2_title') !!}>{{ bival('process_step2_title') }}</h3>
                            <p class="process__card-desc" {!! bitext('process_step2_desc') !!}>{{ bival('process_step2_desc') }}</p>
                        </div>
                    </article>
                </li>

                <li class="process__item" data-step="2">
                    <div class="process__node"><span class="process__orb"></span></div>
                    <article class="process__card">
                        <span class="process__ghost" aria-hidden="true" data-en="03" data-ku="٠٣">03</span>
                        <div class="process__card-body">
                            <span class="process__kicker" {!! bitext('process_phase3') !!}>{{ bival('process_phase3') }}</span>
                            <h3 class="process__card-title" {!! bitext('process_step3_title') !!}>{{ bival('process_step3_title') }}</h3>
                            <p class="process__card-desc" {!! bitext('process_step3_desc') !!}>{{ bival('process_step3_desc') }}</p>
                        </div>
                    </article>
                </li>

                <li class="process__item" data-step="3">
                    <div class="process__node"><span class="process__orb"></span></div>
                    <article class="process__card">
                        <span class="process__ghost" aria-hidden="true" data-en="04" data-ku="٠٤">04</span>
                        <div class="process__card-body">
                            <span class="process__kicker" {!! bitext('process_phase4') !!}>{{ bival('process_phase4') }}</span>
                            <h3 class="process__card-title" {!! bitext('process_step4_title') !!}>{{ bival('process_step4_title') }}</h3>
                            <p class="process__card-desc" {!! bitext('process_step4_desc') !!}>{{ bival('process_step4_desc') }}</p>
                        </div>
                    </article>
                </li>
            </ol>
        </div>
    </section>

    <!-- ========== HERITAGE ========== -->
    <section class="heritage">
        <div class="heritage__bg" aria-hidden="true">
            <span class="heritage__aurora"></span>
            <span class="heritage__strata"></span>
        </div>
        <div class="heritage__inner">
            <div class="heritage__text reveal">
                <p class="section-label section-label--light" data-en="{{ setting('heritage_label_en') }}" data-ku="{{ setting('heritage_label_ku') }}">{{ setting('heritage_label_en') }}</p>
                <h2 class="heritage__title" {!! bitext('heritage_title') !!}>
                    {!! nl2br(e(bival('heritage_title'))) !!}
                </h2>
                <p class="heritage__desc" {!! bitext('heritage_desc') !!}>
                    {{ bival('heritage_desc') }}
                </p>
                <div class="heritage__age">
                    <span class="heritage__age-num">7,000</span>
                    <span class="heritage__age-meta">
                        <span class="heritage__age-unit" {!! bitext('heritage_years') !!}>{{ bival('heritage_years') }}</span>
                        <span class="heritage__age-lbl" {!! bitext('heritage_age_label') !!}>{{ bival('heritage_age_label') }}</span>
                    </span>
                </div>
                <a href="#projects" class="heritage__cta">
                    <span {!! bitext('heritage_cta') !!}>{{ bival('heritage_cta') }}</span>
                    <span class="heritage__cta-arrow">→</span>
                </a>
            </div>
            <div class="heritage__visual reveal reveal-delay-2">
                <img class="citadel-img"
                     src="{{ asset('heritage-citadel.webp') }}?v={{ filemtime(public_path('heritage-citadel.webp')) }}"
                     alt="Illustration of the Erbil Citadel crowned by the Kurdish sun"
                     loading="lazy" decoding="async">
            </div>
        </div>
    </section>

    <!-- ========== CONTACT ========== -->
    <section class="contact" id="contact">
        <div class="contact__bg" aria-hidden="true">
            <span class="contact__aurora"></span>
            <span class="contact__grid"></span>
        </div>

        <div class="contact__inner">

            <div class="contact__info">
                <p class="section-label" data-en="{{ setting('contact_label_en') }}" data-ku="{{ setting('contact_label_ku') }}">{{ setting('contact_label_en') }}</p>
                <h2 class="contact__title" data-en="{{ setting('contact_title_en') }}" data-ku="{{ setting('contact_title_ku') }}">{!! setting('contact_title_en') !!}</h2>

                <ul class="contact__methods">
                    {{-- one email, two lines — the address itself lives on the map card below --}}
                    <li class="cmethod cmethod--copy" data-copy="{{ setting('contact_email_new', 'Info@arammizzuri.com') }}" tabindex="0" role="button" aria-label="Copy {{ setting('contact_email_new', 'Info@arammizzuri.com') }}">
                        <span class="cmethod__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                        </span>
                        <div class="cmethod__text">
                            <span class="cmethod__label" {!! bitext('contact_email_lbl') !!}>{{ bival('contact_email_lbl') }}</span>
                            <span class="cmethod__value">{{ setting('contact_email_new', 'Info@arammizzuri.com') }}</span>
                        </div>
                        <span class="cmethod__hint" {!! bitext('contact_copy') !!}>{{ bival('contact_copy') }}</span>
                    </li>
                    <li class="cmethod cmethod--copy" data-copy="{{ str_replace('(0) ', '', setting('contact_phone', '+964 (0) 782 445 4414')) }}" tabindex="0" role="button" aria-label="Copy {{ setting('contact_phone', '+964 (0) 782 445 4414') }}">
                        <span class="cmethod__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg>
                        </span>
                        <div class="cmethod__text">
                            <span class="cmethod__label" {!! bitext('contact_phone_lbl') !!}>{{ bival('contact_phone_lbl') }}</span>
                            <span class="cmethod__value">{{ setting('contact_phone', '+964 (0) 782 445 4414') }}</span>
                        </div>
                        <span class="cmethod__hint" {!! bitext('contact_copy') !!}>{{ bival('contact_copy') }}</span>
                    </li>
                    <li class="cmethod cmethod--copy" data-copy="{{ str_replace('(0) ', '', setting('contact_phone2', '+964 (0) 750 408 6367')) }}" tabindex="0" role="button" aria-label="Copy {{ setting('contact_phone2', '+964 (0) 750 408 6367') }}">
                        <span class="cmethod__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg>
                        </span>
                        <div class="cmethod__text">
                            <span class="cmethod__label" {!! bitext('contact_phone2_lbl') !!}>{{ bival('contact_phone2_lbl') }}</span>
                            <span class="cmethod__value">{{ setting('contact_phone2', '+964 (0) 750 408 6367') }}</span>
                        </div>
                        <span class="cmethod__hint" {!! bitext('contact_copy') !!}>{{ bival('contact_copy') }}</span>
                    </li>
                </ul>

                {{-- Office locator — dark map, pulsing gold pin, and the
                     visiting policy. Initialised in script.js (initOfficeMap). --}}
                <div class="contact__map">
                    <div class="contact__map-canvas" id="officeMapEl" role="link" tabindex="0" aria-label="Open office location in Google Maps — Italian Village 2, Erbil"></div>
                    <span class="contact__map-open" aria-hidden="true">
                        <span {!! bitext('contact_open_maps') !!}>{{ bival('contact_open_maps') }}</span> ↗&#xFE0E;
                    </span>
                    <div class="contact__map-foot">
                        <p class="contact__map-addr" {!! bitext('contact_map_addr') !!}>{{ bival('contact_map_addr') }}</p>
                        <p class="contact__map-note">
                            <span class="contact__map-dot" aria-hidden="true"></span>
                            <span {!! bitext('contact_visits') !!}>{{ bival('contact_visits') }}</span>
                        </p>
                    </div>
                </div>
            </div>

            <form class="contact__form" id="contactForm" novalidate>
                <div class="form-field">
                    <input type="text" id="cf-name" name="name" placeholder=" " required autocomplete="off">
                    <label for="cf-name" {!! bitext('form_name') !!}>{{ bival('form_name') }}</label>
                </div>
                <div class="form-field">
                    <input type="email" id="cf-email" name="email" placeholder=" " required autocomplete="off">
                    <label for="cf-email" {!! bitext('form_email') !!}>{{ bival('form_email') }}</label>
                </div>
                <div class="form-field">
                    <input type="text" id="cf-project" name="project" placeholder=" " autocomplete="off">
                    <label for="cf-project" {!! bitext('form_project') !!}>{{ bival('form_project') }}</label>
                </div>
                <div class="form-field form-field--grow">
                    <textarea id="cf-message" name="message" placeholder=" " rows="4"></textarea>
                    <label for="cf-message" {!! bitext('form_message') !!}>{{ bival('form_message') }}</label>
                </div>
                <button type="submit" class="form-submit">
                    <span class="form-submit__label" {!! bitext('form_send') !!}>{{ bival('form_send') }}</span>
                    <span class="form-submit__arrow">→</span>
                </button>
                <p class="form-success" id="formSuccess" hidden {!! bitext('form_success') !!}>
                    {{ bival('form_success') }}
                </p>
            </form>
        </div>
    </section>

    <!-- ========== FOOTER ========== -->
    <footer class="footer">
        <div class="footer__inner">
            <div class="footer__col footer__col--brand">
                <div class="footer__logo" {!! bitext('footer_logo') !!}>{{ bival('footer_logo') }}</div>
                <div class="footer__tagline" {!! bitext('footer_tagline') !!}>{{ bival('footer_tagline') }}</div>
                <div class="footer__kurdish">{{ setting('footer_kurdish') }}</div>
            </div>

            <div class="footer__col footer__col--sun">
                <div class="footer__sun-wrap" id="footerSun"></div>
            </div>

            <div class="footer__col footer__col--links">
                <nav class="footer__social">
                    @foreach (socials() as $s)
                        @php $u = trim($s['url']); $ext = $u !== '' && $u !== '#'; @endphp
                        <a href="{{ $u !== '' ? $u : '#' }}" class="footer__link"@if($ext) target="_blank" rel="noopener"@endif>{{ $s['label'] }}</a>
                    @endforeach
                </nav>
                <p class="footer__copy">{!! nl2br(e(setting('footer_copy'))) !!}</p>
            </div>
        </div>
    </footer>

    <!-- ========== PROJECT OVERLAY ========== -->
    <div class="proj-overlay" id="projOverlay" aria-hidden="true" role="dialog" aria-label="Project details">

      <!-- Location map — a separate window beside the main detail window -->
      <aside class="od-sidemap" id="overlayMap" aria-hidden="true">
          <div class="od-sidemap__canvas" id="overlayMapCanvas"></div>
          <div class="od-sidemap__bar">
              <span class="od-sidemap__dot"></span>
              <span class="od-sidemap__city" id="overlayMapCity"></span>
          </div>
      </aside>

      <div class="od-modal">

        <!-- Fixed top bar -->
        <div class="proj-overlay__topbar">
            <button class="proj-overlay__back" id="projOverlayClose" aria-label="Back to projects" {!! bitext('overlay_back') !!}>{{ bival('overlay_back') }}</button>
            <div class="proj-overlay__proj-nav">
                <button class="proj-overlay__proj-btn" id="overlayProjPrev" aria-label="Previous project">←</button>
                <span class="proj-overlay__counter" id="overlayCounter">1 / 10</span>
                <button class="proj-overlay__proj-btn" id="overlayProjNext" aria-label="Next project">→</button>
            </div>
        </div>

        <!-- Full-bleed body: image fills all, panel floats on top -->
        <div class="proj-overlay__body" id="overlayBody">

            <!-- Image — full image floats over a blurred ambient copy of itself -->
            <div class="od-bg">
                <div class="od-bg__ambient od-ambient"></div>
                <img class="od-bg__img" id="overlayHeroImg" src="" alt="">
                <div class="od-bg__veil"></div>

                <!-- Full-resolution view + download controls -->
                <div class="od-fullres">
                    <a class="od-fullres__view" id="overlayFullRes" href="#" target="_blank" rel="noopener">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 3H5a2 2 0 0 0-2 2v4M15 3h4a2 2 0 0 1 2 2v4M21 15v4a2 2 0 0 1-2 2h-4M3 15v4a2 2 0 0 0 2 2h4"/>
                        </svg>
                        <span {!! bitext('overlay_fullres') !!}>{{ bival('overlay_fullres') }}</span>
                    </a>
                    <a class="od-fullres__dl" id="overlayDownload" href="#" download title="Download image" aria-label="Download image">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 3v12M7 10l5 5 5-5M5 21h14"/>
                        </svg>
                    </a>
                </div>
            </div>

            <!-- HUD bracket decorations (image side) -->
            <div class="od-hud" aria-hidden="true">
                <div class="od-hud__c od-hud__c--tl"></div>
                <div class="od-hud__c od-hud__c--bl"></div>
            </div>

            <!-- Image nav -->
            <button class="od-img-btn od-img-btn--prev" id="overlayImgPrev" aria-label="Previous image">←</button>
            <button class="od-img-btn od-img-btn--next" id="overlayImgNext" aria-label="Next image">→</button>

            <!-- Thumbstrip -->
            <div class="od-thumbs" id="overlayThumbs"></div>

            <!-- Floating frosted-glass data panel (ambient-tinted) -->
            <div class="od-panel">
                <div class="od-panel__ambient od-ambient"></div>
                <div class="od-panel__scrim"></div>
                <div class="od-panel__scroll">

                    <div class="od-status-row">
                        <span class="od-dot" id="overlayDot"></span>
                        <span class="od-status-text" id="overlayStatusBadge"></span>
                    </div>

                    <div class="od-num" id="overlayNum"></div>
                    <h1 class="od-name" id="overlayName"></h1>

                    <div class="od-rule"></div>

                    <dl class="od-specs">
                        <div class="od-spec">
                            <dt {!! bitext('overlay_location') !!}>{{ bival('overlay_location') }}</dt>
                            <dd id="overlayLocation"></dd>
                        </div>
                        <div class="od-spec">
                            <dt {!! bitext('overlay_year') !!}>{{ bival('overlay_year') }}</dt>
                            <dd id="overlayYear"></dd>
                        </div>
                        <div class="od-spec">
                            <dt {!! bitext('overlay_typology') !!}>{{ bival('overlay_typology') }}</dt>
                            <dd id="overlayTypology"></dd>
                        </div>
                        <div class="od-spec">
                            <dt {!! bitext('overlay_plot') !!}>{{ bival('overlay_plot') }}</dt>
                            <dd id="overlayArea"></dd>
                        </div>
                    </dl>

                    <div class="od-tags" id="overlayTags"></div>

                    <p class="od-desc" id="overlayDesc"></p>

                </div>
            </div>

        </div>
      </div><!-- /.od-modal -->

    </div>

    <!-- SVG filter: extracts only the outer border of all Kurdistan polygons,
         hiding internal province seam lines and producing one united outline -->
    <svg style="position:absolute;width:0;height:0;overflow:hidden" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <filter id="kurdOutline" x="-4%" y="-4%" width="108%" height="108%" color-interpolation-filters="sRGB">
                <!-- Closing (dilate 4 → erode 4) bridges data-source seams ≤8px wide.
                     Radius 4 is ~12× cheaper than radius 14 (cost scales as radius²). -->
                <feMorphology in="SourceAlpha" operator="dilate" radius="4" result="expanded"/>
                <feMorphology in="expanded"    operator="erode"  radius="4" result="closed"/>
                <!-- Extract 2px outer ring -->
                <feMorphology in="closed" operator="dilate" radius="2" result="bordered"/>
                <feComposite in="bordered" in2="closed" operator="out" result="ring"/>
                <!-- Yellow border -->
                <feFlood flood-color="#F5C518" flood-opacity="1" result="yellow"/>
                <feComposite in="yellow" in2="ring" operator="in" result="yellowBorder"/>
                <!-- Subtle fill -->
                <feFlood flood-color="#F5C518" flood-opacity="0.06" result="subtleFill"/>
                <feComposite in="subtleFill" in2="closed" operator="in" result="fill"/>
                <feMerge>
                    <feMergeNode in="fill"/>
                    <feMergeNode in="yellowBorder"/>
                </feMerge>
            </filter>
        </defs>
    </svg>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-gesture-handling@1.2.2/dist/leaflet-gesture-handling.min.js"></script>
    <script>window.__SITE__ = {!! \Illuminate\Support\Js::from($payload) !!};</script>
    <script src="{{ asset('script.js') }}?v={{ filemtime(public_path('script.js')) }}"></script>
    <script src="{{ asset('vendor/three.min.js') }}" defer></script>
    <script src="{{ asset('hero3d.js') }}?v={{ filemtime(public_path('hero3d.js')) }}" defer></script>
</body>
</html>
