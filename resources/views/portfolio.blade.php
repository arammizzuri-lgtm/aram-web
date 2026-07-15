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
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('style.css') }}">
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
            <span class="logo-name" data-en="{{ setting('brand_name') }}" data-ku="ئارام مزووری">{{ setting('brand_name') }}</span>
            <span class="logo-sub" data-en="{{ setting('brand_sub') }}" data-ku="تەڵارسازی · هەولێر">{{ setting('brand_sub') }}</span>
        </a>

        <div class="nav__bar">
            <a href="#projects" class="nav__link" data-en="Projects" data-ku="پرۆژەکان">Projects</a>
            <a href="#map"      class="nav__link" data-en="Map"      data-ku="نەخشە">Map</a>
            <a href="#about"    class="nav__link" data-en="About"    data-ku="دەربارەی">About</a>
            <a href="#services" class="nav__link" data-en="Services" data-ku="خزمەتگوزاری">Services</a>
            <a href="#process"  class="nav__link" data-en="Process"  data-ku="ڕێگا">Process</a>
            <a href="#contact"  class="nav__link" data-en="Contact"  data-ku="پەیوەندی">Contact</a>
            <span class="nav__sep"></span>
            <button class="lang-toggle" id="langToggle" title="Switch to Kurdish / گۆڕانی زمان">کوردی</button>
        </div>

        <button class="nav__hamburger" id="hamburger" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
    </nav>

    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <a href="#projects" class="mobile-menu__link" data-en="Projects" data-ku="پرۆژەکان">Projects</a>
        <a href="#map"      class="mobile-menu__link" data-en="Map"      data-ku="نەخشە">Map</a>
        <a href="#about"    class="mobile-menu__link" data-en="About"    data-ku="دەربارەی">About</a>
        <a href="#services" class="mobile-menu__link" data-en="Services" data-ku="خزمەتگوزاری">Services</a>
        <a href="#process"  class="mobile-menu__link" data-en="Process"  data-ku="ڕێگا">Process</a>
        <a href="#contact"  class="mobile-menu__link" data-en="Contact"  data-ku="پەیوەندی">Contact</a>
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
                <span data-en="{{ setting('hero_eyebrow') }}" data-ku="هەولێر · کوردستان · دامەزراوە ٢٠٠٩">{{ setting('hero_eyebrow') }}</span>
                <span class="hero__clock"><span class="hero__clock-dot"></span> <span data-en="Erbil" data-ku="هەولێر">Erbil</span>&nbsp;<time id="heroClock">--:--:--</time></span>
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
                    <span class="hero__go-label" data-en="Explore Projects" data-ku="پرۆژەکان ببینە">Explore Projects</span>
                    <span class="hero__go-arrow">→</span>
                </a>
            </div>
            <p class="hero__hint" aria-hidden="true">
                <span class="hero__hint-orb">⟲</span>
                <span data-en="drag to spin the record" data-ku="ڕایبکێشە بۆ سووڕاندنی تۆمارەکە">drag to spin the record</span>
            </p>
        </div>

        <a href="#projects" class="hero__scroll" aria-label="Scroll to projects">
            <span data-en="Scroll" data-ku="بۆ خوارەوە">Scroll</span>
            <span class="hero__scroll-line"></span>
        </a>

        {{-- Mobile-only language switch, pinned centre-bottom above the
             marquee: the nav bar (and its کوردی pill) is hidden below
             768px, so without this, phone visitors have no way to reach
             the Kurdish version. Label managed by initLang in script.js. --}}
        <button class="hero__lang" id="heroLang" type="button" aria-label="Switch language / گۆڕینی زمان">وەشانی کوردی · Kurdish Version</button>
    </section>

    <!-- ========== PROJECTS MAP ========== -->
    <section class="proj-map" id="map">

        <!-- Edge-darkening veil -->
        <div class="proj-map__veil"></div>

        <!-- Leaflet map canvas -->
        <div id="projectMapEl"></div>

        <!-- Section label (top-left overlay) -->
        <div class="proj-map__label">
            <p class="section-label" data-en="Where We Build" data-ku="کوێ دەبینین">Where We Build</p>
            <h2 class="proj-map__title" data-en="Projects Across<br>Kurdistan & Beyond" data-ku="پرۆژەکان بەسەر<br>کوردستان و دوورتردا">Projects Across<br>Kurdistan &amp; Beyond</h2>
        </div>

        <!-- Floating project card — JS positions this near the clicked pin -->
        <div class="map-card" id="mapCard" role="dialog" aria-label="Project preview">
            <button class="map-card__close" id="mapCardClose" aria-label="Close">✕</button>
            <div class="map-card__img-wrap">
                <img class="map-card__img" id="mapCardImg" src="" alt="">
                <span class="map-card__num" id="mapCardNum"></span>
            </div>
            <div class="map-card__body">
                <h3 class="map-card__name" id="mapCardName"></h3>
                <p class="map-card__meta" id="mapCardMeta"></p>
                <p class="map-card__excerpt" id="mapCardExcerpt"></p>
                <button class="map-card__cta" id="mapCardCta">
                    <span data-en="View Full Project" data-ku="بینینی پڕۆژەی تەواو">View Full Project</span>
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
            <div class="map-stat"><span class="map-stat__val">{{ $stats['projects'] }}</span><span class="map-stat__lbl" data-en="Projects" data-ku="پرۆژە">Projects</span></div>
            <div class="map-stat__div"></div>
            <div class="map-stat"><span class="map-stat__val">{{ $stats['cities'] }}</span><span class="map-stat__lbl" data-en="Cities" data-ku="شار">Cities</span></div>
            <div class="map-stat__div"></div>
            <div class="map-stat"><span class="map-stat__val">{{ $stats['area_short'] }}</span><span class="map-stat__lbl" data-en="m² Designed" data-ku="م² دیزاینکراو">m² Designed</span></div>
            <div class="map-stat__div"></div>
            <div class="map-stat"><span class="map-stat__val">{{ $stats['countries'] }}</span><span class="map-stat__lbl" data-en="Countries" data-ku="وڵات">Countries</span></div>
        </div>

    </section>

    <section class="clients" id="clients">
        @php
            // Real clients & partners. Marks are bespoke line-art monograms
            // drawn in the site's geometric language — swap `mark` for an
            // <image> tag when official logo files are supplied.
            $clients = [
                ['name' => 'Kar Group', 'sub_en' => 'Energy & Infrastructure', 'sub_ku' => 'وزە و ژێرخان',
                 'mark' => '<polygon points="18,2 32,10 32,26 18,34 4,26 4,10" stroke="#fff" stroke-width="2" stroke-linejoin="round"/><path d="M13 24 V12 M13 18 L23 12 M13 18 L23 24" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>'],
                ['name' => '404 Cafe', 'sub_en' => 'Café · Erbil', 'sub_ku' => 'کافێ · هەولێر',
                 'mark' => '<path d="M8 15 h16 v7 a8 8 0 0 1 -16 0 Z" stroke="#fff" stroke-width="2" stroke-linejoin="round"/><path d="M24 16 h3 a4 4 0 0 1 0 8 h-3" stroke="#fff" stroke-width="2"/><path d="M13 11 c0-2 2-2 2-4 M19 11 c0-2 2-2 2-4" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>'],
                ['name' => 'KRG', 'sub_en' => "Kurdistan Regional Gov't", 'sub_ku' => 'حکومەتی هەرێمی کوردستان',
                 'mark' => '<circle cx="18" cy="18" r="5.5" fill="#fff"/><circle cx="18" cy="18" r="10" stroke="#fff" stroke-width="1.2"/><line x1="18" y1="2" x2="18" y2="6.5" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/><line x1="18" y1="29.5" x2="18" y2="34" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/><line x1="2" y1="18" x2="6.5" y2="18" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/><line x1="29.5" y1="18" x2="34" y2="18" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/><line x1="5.5" y1="5.5" x2="8.8" y2="8.8" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/><line x1="27.2" y1="27.2" x2="30.5" y2="30.5" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/><line x1="30.5" y1="5.5" x2="27.2" y2="8.8" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/><line x1="5.5" y1="30.5" x2="8.8" y2="27.2" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>'],
                ['name' => 'Rightmove', 'sub_en' => 'Real Estate', 'sub_ku' => 'خانووبەرە',
                 'mark' => '<polyline points="4,22 13,13 22,22" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 24 v8 h12 v-8" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="21" y1="19" x2="32" y2="8" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/><polyline points="26,8 32,8 32,14" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>'],
                ['name' => 'Darin Group', 'sub_en' => 'Business Group', 'sub_ku' => 'گروپی بازرگانی',
                 'mark' => '<line x1="18" y1="3" x2="18" y2="33" stroke="#fff" stroke-width="2" stroke-linecap="round"/><line x1="3" y1="18" x2="33" y2="18" stroke="#fff" stroke-width="2" stroke-linecap="round"/><line x1="7.2" y1="7.2" x2="28.8" y2="28.8" stroke="#fff" stroke-width="2" stroke-linecap="round"/><line x1="28.8" y1="7.2" x2="7.2" y2="28.8" stroke="#fff" stroke-width="2" stroke-linecap="round"/>'],
                ['name' => "Erbil Int'l Airport", 'sub_en' => 'Aviation', 'sub_ku' => 'فڕۆکەوانی',
                 'mark' => '<polygon points="3,21 33,7 23,30 16,23 Z" stroke="#fff" stroke-width="2" stroke-linejoin="round"/><line x1="16" y1="23" x2="33" y2="7" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/><line x1="6" y1="32" x2="28" y2="32" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-dasharray="4 4"/>'],
                ['name' => 'Future City', 'sub_en' => 'Urban Development', 'sub_ku' => 'گەشەپێدانی شاری',
                 'mark' => '<line x1="4" y1="32" x2="32" y2="32" stroke="#fff" stroke-width="2" stroke-linecap="round"/><rect x="7" y="18" width="6" height="14" stroke="#fff" stroke-width="1.8"/><rect x="15" y="10" width="6" height="22" stroke="#fff" stroke-width="1.8"/><rect x="23" y="14" width="6" height="18" stroke="#fff" stroke-width="1.8"/><line x1="18" y1="4" x2="18" y2="10" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>'],
                ['name' => 'So Delicious', 'sub_en' => 'Café & Restaurant', 'sub_ku' => 'کافێ و چێشتخانە',
                 'mark' => '<path d="M6 24 a12 11 0 0 1 24 0" stroke="#fff" stroke-width="2" stroke-linecap="round"/><line x1="4" y1="24" x2="32" y2="24" stroke="#fff" stroke-width="2" stroke-linecap="round"/><circle cx="18" cy="10.5" r="1.8" fill="#fff"/><line x1="12" y1="30" x2="24" y2="30" stroke="#fff" stroke-width="2" stroke-linecap="round"/>'],
                ['name' => 'Halat Group', 'sub_en' => 'Development', 'sub_ku' => 'گەشەپێدان',
                 'mark' => '<polyline points="6,14 18,6 30,14" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><polyline points="6,22 18,14 30,22" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" opacity=".65"/><polyline points="6,30 18,22 30,30" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" opacity=".35"/>'],
                ['name' => 'GK Architects', 'sub_en' => 'Architecture Studio', 'sub_ku' => 'ستۆدیۆی تەڵارسازی',
                 'mark' => '<circle cx="18" cy="7" r="3" stroke="#fff" stroke-width="1.8"/><line x1="16.4" y1="9.8" x2="9" y2="30" stroke="#fff" stroke-width="2" stroke-linecap="round"/><line x1="19.6" y1="9.8" x2="27" y2="30" stroke="#fff" stroke-width="2" stroke-linecap="round"/><path d="M11.5 24.5 a13 13 0 0 0 13 0" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>'],
            ];
        @endphp

        <div class="clients__head">
            <p class="section-label section-label--center" data-en="Trusted By" data-ku="پشتیوانیكراو">Trusted By</p>
            <h2 class="clients__title" data-en="Clients & Partners" data-ku="کڕیار و هاوبەشەکان">Clients &amp; Partners</h2>
            <p class="clients__hint" data-en="click a logo to see all partners" data-ku="کرتە لە لۆگۆیەک بکە بۆ بینینی هەموو هاوبەشەکان">click a logo to see all partners</p>
        </div>
        <div class="clients__track-wrap">
            <div class="clients__track">
                {{-- two identical sets = seamless marquee loop --}}
                @foreach ([false, true] as $dup)
                    @foreach ($clients as $c)
                        <div class="client-logo" @if($dup) aria-hidden="true" @endif>
                            <svg class="client-logo__mark" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">{!! $c['mark'] !!}</svg>
                            <span class="client-logo__name">{{ $c['name'] }}</span>
                            <span class="client-logo__sub" data-en="{{ $c['sub_en'] }}" data-ku="{{ $c['sub_ku'] }}">{{ $c['sub_en'] }}</span>
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
                <p class="section-label section-label--center" data-en="Trusted By" data-ku="پشتیوانیكراو">Trusted By</p>
                <h3 class="clients-modal__title" data-en="Clients & Partners" data-ku="کڕیار و هاوبەشەکان">Clients &amp; Partners</h3>
                <div class="clients-modal__grid">
                    @foreach ($clients as $c)
                        <div class="clients-modal__tile" style="--i: {{ $loop->index }}">
                            <svg class="client-logo__mark" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">{!! $c['mark'] !!}</svg>
                            <span class="client-logo__name">{{ $c['name'] }}</span>
                            <span class="client-logo__sub" data-en="{{ $c['sub_en'] }}" data-ku="{{ $c['sub_ku'] }}">{{ $c['sub_en'] }}</span>
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
                <p class="section-label" data-en="Selected Work" data-ku="کارە هەڵبژێردراوەکان">Selected Work</p>
                <h2 class="pg__title" data-en="Projects" data-ku="پرۆژەکان">Projects</h2>
            </div>
            <div class="pg__head-right">
                <div class="pg__search-wrap">
                    <input class="pg__search" id="pgSearch" type="search" placeholder="Search projects…" data-en-ph="Search projects…" data-ku-ph="گەڕان بۆ پرۆژەکان…" autocomplete="off" aria-label="Search projects">
                    <span class="pg__search-icon" aria-hidden="true">↗</span>
                </div>
                <div class="pg__filter" role="group" aria-label="Filter by typology">
                    <button class="pgf-btn active" data-filter="all"         data-en="All"            data-ku="هەمووی">All</button>
                    <button class="pgf-btn"        data-filter="residential" data-en="Residential"    data-ku="نیشتەجێبوون">Residential</button>
                    <button class="pgf-btn"        data-filter="commercial"  data-en="Commercial"     data-ku="بازرگانی">Commercial</button>
                    <button class="pgf-btn"        data-filter="hospitality" data-en="Hospitality"    data-ku="میوانپەروەری">Hospitality</button>
                    <button class="pgf-btn"        data-filter="mixed-use"   data-en="Mixed-Use"      data-ku="تێکەڵ">Mixed-Use</button>
                    <button class="pgf-btn"        data-filter="cultural"    data-en="Cultural"       data-ku="کولتووری">Cultural</button>
                    <button class="pgf-btn"        data-filter="urban"       data-en="Master Planning" data-ku="شارستانی">Master Planning</button>
                </div>
            </div>
        </div>

        <div class="pg__slider">
            <button class="pg__nav pg__nav--prev" id="pgPrev" aria-label="Previous projects" disabled>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M15 5l-7 7 7 7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>

            <div class="pg__grid" id="pgGrid">

            @foreach ($projects as $i => $project)
            <article class="pgc{{ $project->size === 'large' ? ' pgc--large' : ($project->size === 'wide' ? ' pgc--wide' : '') }}" data-index="{{ $i }}" data-category="{{ $project->category }}" data-name="{{ $project->name }}">
                <div class="pgc__inner">
                    <img class="pgc__img" data-src="{{ $project->coverUrl() }}" src="" alt="{{ $project->name }}">
                    <div class="pgc__overlay">
                        <div class="pgc__status pgc__status--{{ $project->statusClass() }}">{{ $project->statusBadge() }}</div>
                        <div class="pgc__info">
                            <div class="pgc__num">{{ $project->num }}</div>
                            <h3 class="pgc__name" data-en="{{ $project->name }}" data-ku="{{ $project->name_ku ?: $project->name }}">{{ $project->name }}</h3>
                            <p class="pgc__meta">{{ $project->metaLabel() }}</p>
                        </div>
                        <span class="pgc__cta"><span data-en="View Project" data-ku="بینینی پرۆژە">View Project</span> <span class="pgc__cta-arrow">↗</span></span>
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
        <p class="pg__more" id="pgMore" aria-hidden="true">
            <span class="pg__more-label"
                  data-en="slide for more — {{ $projects->count() }} projects"
                  data-ku="ڕایبکێشە بۆ زیاتر — {{ $projects->count() }} پرۆژە">slide for more — {{ $projects->count() }} projects</span>
            <span class="pg__more-arrow">⟶</span>
        </p>

        <div class="pg__empty" id="pgEmpty" hidden>
            <p class="pg__empty-text" data-en="No projects match your criteria." data-ku="هیچ پرۆژەیەک لەگەڵ داواکارییەکەت ناگونجێت.">No projects match your criteria.</p>
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
                    <p class="about__bio" data-en="{{ setting('about_bio_1') }}" data-ku="ئارام مزووری تەڵارسازێکی پێشەنگە کە لە هەولێر، پایتەختی هەرێمی کوردستانی عێراق، نیشتەجێیە. کارەکەی پردێکە لەنێوان کۆن و هاوچەرخدا — سەرچاوە دەگرێت لە میراتی دەوڵەمەندی شوێنی بەرزاییەکانی کوردستان، لە قەڵای ٧٬٠٠٠ ساڵەی هەولێرەوە تا گوندە شاخاوییەکانی ئامێدی — بۆ دروستکردنی بینایەک کە بە ڕوونی کوردییە لە هەستەوە و لەهەمانکاتدا توند و هاوچەرخە.">
                        {{ setting('about_bio_1') }}
                    </p>
                    <p class="about__bio" data-en="{{ setting('about_bio_2') }}" data-ku="بە پرۆژە تەواوکراوەکان لە سەرانسەری کوردستان، لەوانە دامەزراوە کولتوورییەکان، بورجە شارییەکان، پەناگا شاخاوییەکان و بۆشایی گشتییەکان، تەڵارسازیی مزووری بووەتە دەنگێکی دیاریکەر لە دیمەنی بنیاتنراوی خێراگۆڕی ناوچەکەدا.">
                        {{ setting('about_bio_2') }}
                    </p>
                    <blockquote class="about__quote">
                        <p data-en="{{ setting('about_quote') }}" data-ku="«تەڵارسازی ئەو پردەیە کە یادەوەریی گەلێک بە خەونەکانی داهاتوویانەوە دەبەستێتەوە.»">{{ setting('about_quote') }}</p>
                        <cite data-en="{{ setting('about_quote_cite') }}" data-ku="— ئارام مزووری">{{ setting('about_quote_cite') }}</cite>
                    </blockquote>
                </div>

                <div class="about__side">
                    <div class="about__portrait">
                        <div class="portrait__sun" id="portraitSun"></div>
                        <img class="portrait__photo" src="{{ \App\Models\Project::resolveImage(setting('about_portrait_img')) }}" alt="Aram Mizuri, Principal Architect" style="position:absolute;inset:0;z-index:1;">
                        <span class="portrait__corner portrait__corner--tl"></span>
                        <span class="portrait__corner portrait__corner--br"></span>
                        <div class="portrait__caption" data-en="{{ setting('about_portrait_caption') }}" data-ku="ئارام مزووری · تەڵارسازی سەرەکی">{{ setting('about_portrait_caption') }}</div>
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
                <p class="section-label section-label--center" data-en="What We Do" data-ku="ئەوەی دەیکەین">What We Do</p>
                <h2 class="services__title" data-en="Services" data-ku="خزمەتگوزارییەکان">Services</h2>
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
                    <p class="pshow__label" data-en="Selected Designs" data-ku="دیزاینە هەڵبژێردراوەکان">Selected Designs</p>
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
                        <span class="process__ghost" aria-hidden="true">01</span>
                        <div class="process__card-body">
                            <span class="process__kicker" data-en="Phase 01" data-ku="قۆناغی ٠١">Phase 01</span>
                            <h3 class="process__card-title" data-en="{{ setting('process_step1_title_en') }}" data-ku="{{ setting('process_step1_title_ku') }}">{{ setting('process_step1_title_en') }}</h3>
                            <p class="process__card-desc" data-en="{{ setting('process_step1_desc') }}" data-ku="گوێگرتنی قووڵ — تێگەیشتن لە بینینی کڕیار، پێکهاتەی کولتووریی شوێنەکە، و نەریتە شوێنییەکانی کوردستان.">{{ setting('process_step1_desc') }}</p>
                        </div>
                    </article>
                </li>

                <li class="process__item" data-step="1">
                    <div class="process__node"><span class="process__orb"></span></div>
                    <article class="process__card">
                        <span class="process__ghost" aria-hidden="true">02</span>
                        <div class="process__card-body">
                            <span class="process__kicker" data-en="Phase 02" data-ku="قۆناغی ٠٢">Phase 02</span>
                            <h3 class="process__card-title" data-en="{{ setting('process_step2_title_en') }}" data-ku="{{ setting('process_step2_title_ku') }}">{{ setting('process_step2_title_en') }}</h3>
                            <p class="process__card-desc" data-en="{{ setting('process_step2_desc') }}" data-ku="دیزاینی دووبارەبووەوە کە لە نەریتە شوێنییە کوردییەکان، ستراتیژی گونجاو لەگەڵ کەشوهەوا، و گفتوگۆی تەڵارسازیی هاوچەرخەوە سەرچاوە دەگرێت.">{{ setting('process_step2_desc') }}</p>
                        </div>
                    </article>
                </li>

                <li class="process__item" data-step="2">
                    <div class="process__node"><span class="process__orb"></span></div>
                    <article class="process__card">
                        <span class="process__ghost" aria-hidden="true">03</span>
                        <div class="process__card-body">
                            <span class="process__kicker" data-en="Phase 03" data-ku="قۆناغی ٠٣">Phase 03</span>
                            <h3 class="process__card-title" data-en="{{ setting('process_step3_title_en') }}" data-ku="{{ setting('process_step3_title_ku') }}">{{ setting('process_step3_title_en') }}</h3>
                            <p class="process__card-desc" data-en="{{ setting('process_step3_desc') }}" data-ku="وردیی تەکنیکی و توێژینەوەی کەرەستە کە دڵنیایی دەدات هەر وردەکارییەک ڕێز لە بیرۆکە و پیشەوەریی بیناسازانی کوردستان بگرێت.">{{ setting('process_step3_desc') }}</p>
                        </div>
                    </article>
                </li>

                <li class="process__item" data-step="3">
                    <div class="process__node"><span class="process__orb"></span></div>
                    <article class="process__card">
                        <span class="process__ghost" aria-hidden="true">04</span>
                        <div class="process__card-body">
                            <span class="process__kicker" data-en="Phase 04" data-ku="قۆناغی ٠٤">Phase 04</span>
                            <h3 class="process__card-title" data-en="{{ setting('process_step4_title_en') }}" data-ku="{{ setting('process_step4_title_ku') }}">{{ setting('process_step4_title_en') }}</h3>
                            <p class="process__card-desc" data-en="{{ setting('process_step4_desc') }}" data-ku="چاودێریی بنیاتنان لە شوێن و دڵنیایی جۆرایەتی — دروستکردنی پەیوەندی لەگەڵ باشترین کۆمپانیا و پیشەوەرە خۆماڵییەکانی کوردستان.">{{ setting('process_step4_desc') }}</p>
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
                <h2 class="heritage__title" data-en="{{ setting('heritage_title') }}" data-ku="شێوەپێدراو بە ٧٬٠٠٠ ساڵ شارستانیەتی کوردی">
                    {!! nl2br(e(setting('heritage_title'))) !!}
                </h2>
                <p class="heritage__desc" data-en="{{ setting('heritage_desc') }}" data-ku="قەڵای هەولێر — یەکێک لە کۆنترین شوێنە نیشتەجێبووە بەردەوامەکانی سەر زەوی — شایەتی ڕۆحی نەمری کوردستانە. تەڵارسازیمان لەم کانگا مێژووییە قووڵەوە سەرچاوە دەگرێت و بە بوێرییەوە بۆ داهاتوو بنیات دەنێین.">
                    {{ setting('heritage_desc') }}
                </p>
                <div class="heritage__age">
                    <span class="heritage__age-num">7,000</span>
                    <span class="heritage__age-meta">
                        <span class="heritage__age-unit" data-en="years" data-ku="ساڵ">years</span>
                        <span class="heritage__age-lbl" data-en="of unbroken life atop the Erbil Citadel — the world's oldest continuously inhabited settlement." data-ku="ژیانی نەبڕاوە لەسەر قەڵای هەولێر — کۆنترین شوێنی نیشتەجێبوونی بەردەوام لە جیهاندا.">of unbroken life atop the Erbil Citadel — the world's oldest continuously inhabited settlement.</span>
                    </span>
                </div>
                <a href="#projects" class="heritage__cta">
                    <span data-en="{{ setting('heritage_cta') }}" data-ku="پرۆژەکانمان ببینە">{{ setting('heritage_cta') }}</span>
                    <span class="heritage__cta-arrow">→</span>
                </a>
            </div>
            <div class="heritage__visual reveal reveal-delay-2">
                <svg class="citadel-svg" viewBox="0 0 420 320" xmlns="http://www.w3.org/2000/svg">
                    <!-- ground -->
                    <rect x="0" y="270" width="420" height="50" fill="#1a1008" rx="0"/>
                    <!-- hill/mound -->
                    <ellipse cx="210" cy="275" rx="200" ry="30" fill="#1f1508"/>
                    <!-- level 4 base -->
                    <rect x="50"  y="220" width="320" height="58" fill="#2a1c0a" rx="1"/>
                    <!-- level 3 -->
                    <rect x="80"  y="168" width="260" height="56" fill="#362311" rx="1"/>
                    <!-- level 2 -->
                    <rect x="110" y="118" width="200" height="54" fill="#422b14" rx="1"/>
                    <!-- level 1 top -->
                    <rect x="140" y="76"  width="140" height="46" fill="#503416" rx="1"/>
                    <!-- battlements level 4 -->
                    <rect x="55"  y="212" width="14" height="12" fill="#1f1508" rx="1"/>
                    <rect x="80"  y="212" width="14" height="12" fill="#1f1508" rx="1"/>
                    <rect x="105" y="212" width="14" height="12" fill="#1f1508" rx="1"/>
                    <rect x="300" y="212" width="14" height="12" fill="#1f1508" rx="1"/>
                    <rect x="325" y="212" width="14" height="12" fill="#1f1508" rx="1"/>
                    <rect x="350" y="212" width="14" height="12" fill="#1f1508" rx="1"/>
                    <!-- windows level 4 -->
                    <rect x="95"  y="233" width="14" height="22" fill="#F5C518" rx="1" opacity="0.55"/>
                    <rect x="122" y="233" width="14" height="22" fill="#F5C518" rx="1" opacity="0.55"/>
                    <rect x="150" y="233" width="14" height="22" fill="#F5C518" rx="1" opacity="0.55"/>
                    <rect x="200" y="233" width="20" height="22" fill="#F5C518" rx="1" opacity="0.7"/>
                    <rect x="256" y="233" width="14" height="22" fill="#F5C518" rx="1" opacity="0.55"/>
                    <rect x="283" y="233" width="14" height="22" fill="#F5C518" rx="1" opacity="0.55"/>
                    <rect x="310" y="233" width="14" height="22" fill="#F5C518" rx="1" opacity="0.55"/>
                    <!-- windows level 3 -->
                    <rect x="100" y="180" width="12" height="18" fill="#F5C518" rx="1" opacity="0.6"/>
                    <rect x="126" y="180" width="12" height="18" fill="#F5C518" rx="1" opacity="0.6"/>
                    <rect x="202" y="180" width="16" height="18" fill="#F5C518" rx="1" opacity="0.75"/>
                    <rect x="280" y="180" width="12" height="18" fill="#F5C518" rx="1" opacity="0.6"/>
                    <rect x="306" y="180" width="12" height="18" fill="#F5C518" rx="1" opacity="0.6"/>
                    <!-- windows level 2 -->
                    <rect x="128" y="130" width="11" height="16" fill="#F5C518" rx="1" opacity="0.65"/>
                    <rect x="153" y="130" width="11" height="16" fill="#F5C518" rx="1" opacity="0.65"/>
                    <rect x="204" y="130" width="14" height="16" fill="#F5C518" rx="1" opacity="0.8"/>
                    <rect x="256" y="130" width="11" height="16" fill="#F5C518" rx="1" opacity="0.65"/>
                    <rect x="281" y="130" width="11" height="16" fill="#F5C518" rx="1" opacity="0.65"/>
                    <!-- windows level 1 -->
                    <rect x="158" y="88"  width="10" height="14" fill="#F5C518" rx="1" opacity="0.7"/>
                    <rect x="205" y="88"  width="12" height="14" fill="#F5C518" rx="1" opacity="0.9"/>
                    <rect x="252" y="88"  width="10" height="14" fill="#F5C518" rx="1" opacity="0.7"/>
                    <!-- sun above citadel -->
                    <circle cx="210" cy="38" r="22" fill="#F5C518" opacity="0.9"/>
                    <circle cx="210" cy="38" r="14" fill="#1a0800" opacity="0.25"/>
                    <!-- sun glow -->
                    <circle cx="210" cy="38" r="30" fill="none" stroke="#F5C518" stroke-width="1" opacity="0.3"/>
                    <circle cx="210" cy="38" r="40" fill="none" stroke="#F5C518" stroke-width="0.5" opacity="0.15"/>
                    <!-- simple rays -->
                    <line x1="210" y1="10" x2="210" y2="3"  stroke="#F5C518" stroke-width="1.5" opacity="0.7"/>
                    <line x1="210" y1="66" x2="210" y2="73" stroke="#F5C518" stroke-width="1.5" opacity="0.7"/>
                    <line x1="182" y1="38" x2="175" y2="38" stroke="#F5C518" stroke-width="1.5" opacity="0.7"/>
                    <line x1="238" y1="38" x2="245" y2="38" stroke="#F5C518" stroke-width="1.5" opacity="0.7"/>
                    <line x1="190" y1="18" x2="185" y2="12" stroke="#F5C518" stroke-width="1.5" opacity="0.5"/>
                    <line x1="230" y1="18" x2="235" y2="12" stroke="#F5C518" stroke-width="1.5" opacity="0.5"/>
                    <line x1="190" y1="58" x2="185" y2="64" stroke="#F5C518" stroke-width="1.5" opacity="0.5"/>
                    <line x1="230" y1="58" x2="235" y2="64" stroke="#F5C518" stroke-width="1.5" opacity="0.5"/>
                </svg>
                <div class="heritage__geo-pattern">
                    <div class="geo-cell"></div><div class="geo-cell"></div>
                    <div class="geo-cell"></div><div class="geo-cell"></div>
                </div>
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
                    <li class="cmethod">
                        <span class="cmethod__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.5-7-11a7 7 0 0 1 14 0c0 4.5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                        </span>
                        <div class="cmethod__text">
                            <span class="cmethod__label" data-en="{{ setting('contact_studio_label') }}" data-ku="ستۆدیۆ">{{ setting('contact_studio_label') }}</span>
                            <span class="cmethod__value">{!! setting('contact_studio_value') !!}</span>
                        </div>
                    </li>
                    <li class="cmethod cmethod--copy" data-copy="{{ setting('contact_email_new') }}" tabindex="0" role="button" aria-label="Copy {{ setting('contact_email_new') }}">
                        <span class="cmethod__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                        </span>
                        <div class="cmethod__text">
                            <span class="cmethod__label" data-en="{{ setting('contact_newwork_label') }}" data-ku="کاری نوێ">{{ setting('contact_newwork_label') }}</span>
                            <span class="cmethod__value">{{ setting('contact_email_new') }}</span>
                        </div>
                        <span class="cmethod__hint" data-en="Copy" data-ku="کۆپی">Copy</span>
                    </li>
                    <li class="cmethod cmethod--copy" data-copy="{{ setting('contact_email_general') }}" tabindex="0" role="button" aria-label="Copy {{ setting('contact_email_general') }}">
                        <span class="cmethod__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                        </span>
                        <div class="cmethod__text">
                            <span class="cmethod__label" data-en="{{ setting('contact_general_label') }}" data-ku="گشتی">{{ setting('contact_general_label') }}</span>
                            <span class="cmethod__value">{{ setting('contact_email_general') }}</span>
                        </div>
                        <span class="cmethod__hint" data-en="Copy" data-ku="کۆپی">Copy</span>
                    </li>
                    <li class="cmethod cmethod--copy" data-copy="{{ setting('contact_phone') }}" tabindex="0" role="button" aria-label="Copy {{ setting('contact_phone') }}">
                        <span class="cmethod__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg>
                        </span>
                        <div class="cmethod__text">
                            <span class="cmethod__label" data-en="{{ setting('contact_phone_label') }}" data-ku="تەلەفۆن">{{ setting('contact_phone_label') }}</span>
                            <span class="cmethod__value">{{ setting('contact_phone') }}</span>
                        </div>
                        <span class="cmethod__hint" data-en="Copy" data-ku="کۆپی">Copy</span>
                    </li>
                </ul>

                {{-- Office locator — dark map, pulsing gold pin, and the
                     visiting policy. Initialised in script.js (initOfficeMap). --}}
                <div class="contact__map">
                    <div class="contact__map-canvas" id="officeMapEl" aria-label="Office location — Italian Village 2, Erbil"></div>
                    <div class="contact__map-foot">
                        <p class="contact__map-addr" data-en="Nº 592 (2nd Floor), Italian Village 2, Erbil, Kurdistan Region of Iraq" data-ku="ژمارە ٥٩٢ (نهۆمی ٢)، گوندی ئیتاڵی ٢، هەولێر، هەرێمی کوردستانی عێراق">Nº 592 (2nd Floor), Italian Village 2, Erbil, Kurdistan Region of Iraq</p>
                        <p class="contact__map-note">
                            <span class="contact__map-dot" aria-hidden="true"></span>
                            <span data-en="Visits are by appointment only" data-ku="سەردانکردن تەنها بە ژوانی پێشوەختەیە">Visits are by appointment only</span>
                        </p>
                    </div>
                </div>
            </div>

            <form class="contact__form" id="contactForm" novalidate>
                <div class="form-field">
                    <input type="text" id="cf-name" name="name" placeholder=" " required autocomplete="off">
                    <label for="cf-name" data-en="Your Name" data-ku="ناوت">Your Name</label>
                </div>
                <div class="form-field">
                    <input type="email" id="cf-email" name="email" placeholder=" " required autocomplete="off">
                    <label for="cf-email" data-en="Email Address" data-ku="ناونیشانی ئیمەیڵ">Email Address</label>
                </div>
                <div class="form-field">
                    <input type="text" id="cf-project" name="project" placeholder=" " autocomplete="off">
                    <label for="cf-project" data-en="Project Type" data-ku="جۆری پڕۆژە">Project Type</label>
                </div>
                <div class="form-field form-field--grow">
                    <textarea id="cf-message" name="message" placeholder=" " rows="4"></textarea>
                    <label for="cf-message" data-en="Tell us about your project" data-ku="دەربارەی پڕۆژەکەت پێمان بڵێ">Tell us about your project</label>
                </div>
                <button type="submit" class="form-submit">
                    <span class="form-submit__label" data-en="Send Message" data-ku="ناردنی نامە">Send Message</span>
                    <span class="form-submit__arrow">→</span>
                </button>
                <p class="form-success" id="formSuccess" hidden data-en="✓ Message received — we'll be in touch soon." data-ku="✓ نامەکەت گەیشت — بەم زووانە پەیوەندیت پێوە دەکەین.">
                    ✓ Message received — we'll be in touch soon.
                </p>
            </form>
        </div>
    </section>

    <!-- ========== FOOTER ========== -->
    <footer class="footer">
        <div class="footer__inner">
            <div class="footer__col footer__col--brand">
                <div class="footer__logo" data-en="{{ setting('footer_logo') }}" data-ku="ئارام مزووری">{{ setting('footer_logo') }}</div>
                <div class="footer__tagline" data-en="{{ setting('footer_tagline') }}" data-ku="تەڵارسازی · هەولێر · کوردستان">{{ setting('footer_tagline') }}</div>
                <div class="footer__kurdish">{{ setting('footer_kurdish') }}</div>
            </div>

            <div class="footer__col footer__col--sun">
                <div class="footer__sun-wrap" id="footerSun"></div>
            </div>

            <div class="footer__col footer__col--links">
                <nav class="footer__social">
                    <a href="{{ setting('footer_instagram_url') }}" class="footer__link">Instagram</a>
                    <a href="{{ setting('footer_linkedin_url') }}" class="footer__link">LinkedIn</a>
                    <a href="{{ setting('footer_behance_url') }}" class="footer__link">Behance</a>
                    <a href="{{ setting('footer_archello_url') }}" class="footer__link">Archello</a>
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
            <button class="proj-overlay__back" id="projOverlayClose" aria-label="Back to projects" data-en="Back to Projects" data-ku="گەڕانەوە بۆ پرۆژەکان">Back to Projects</button>
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
                        <span data-en="View Full Resolution Size" data-ku="بینین بە قەبارەی تەواو">View Full Resolution Size</span>
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
                            <dt data-en="Location" data-ku="شوێن">Location</dt>
                            <dd id="overlayLocation"></dd>
                        </div>
                        <div class="od-spec">
                            <dt data-en="Year" data-ku="ساڵ">Year</dt>
                            <dd id="overlayYear"></dd>
                        </div>
                        <div class="od-spec">
                            <dt data-en="Typology" data-ku="جۆر">Typology</dt>
                            <dd id="overlayTypology"></dd>
                        </div>
                        <div class="od-spec">
                            <dt data-en="Plot Area" data-ku="ڕووبەری زەوی">Plot Area</dt>
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
    <script src="{{ asset('script.js') }}"></script>
    <script src="{{ asset('vendor/three.min.js') }}" defer></script>
    <script src="{{ asset('hero3d.js') }}" defer></script>
</body>
</html>
