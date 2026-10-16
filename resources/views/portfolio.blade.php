@php
    // English at /, Kurdish at /ku — the page arrives in its language, so
    // search engines and link previews read the version they were sent to.
    $isKu = site_lang() === 'ku';
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
            var amSeen = +localStorage.getItem('am_loader_seen');
            if (amSeen && Date.now() - amSeen < 30 * 60 * 1000) document.documentElement.classList.add('am-seen');
        } catch (e) { /* storage blocked: the loader, as on a first visit */ }
    </script>
    <link rel="stylesheet" href="{{ asset('style.css') }}?v={{ filemtime(public_path('style.css')) }}">
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
         unpkg at the matching version. --}}
    <link rel="stylesheet" href="{{ asset('vendor/leaflet.css') }}?v={{ filemtime(public_path('vendor/leaflet.css')) }}">
    <link rel="stylesheet" href="{{ asset('vendor/leaflet-gesture-handling.min.css') }}?v={{ filemtime(public_path('vendor/leaflet-gesture-handling.min.css')) }}">
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

    <!-- Navigation -->
    <nav class="nav" id="nav">
        <a href="#home" class="nav__logo">
            <span class="logo-name" {!! bitext('brand_name') !!}>{{ bival('brand_name') }}</span>
            <span class="logo-sub" {!! bitext('brand_sub') !!}>{{ bival('brand_sub') }}</span>
        </a>

        <div class="nav__bar">
            {{-- "Projects" opens every project on its own page; the hero's arrow still scrolls to the grid below --}}
            <a href="{{ site_url('/projects') }}" class="nav__link"
               data-href-en="{{ site_url('/projects', 'en') }}" data-href-ku="{{ site_url('/projects', 'ku') }}" {!! bitext('nav_projects') !!}>{{ bival('nav_projects') }}</a>
            <a href="#map"      class="nav__link" {!! bitext('nav_map') !!}>{{ bival('nav_map') }}</a>
            <a href="#about"    class="nav__link" {!! bitext('nav_about') !!}>{{ bival('nav_about') }}</a>
            <a href="#services" class="nav__link" {!! bitext('nav_services') !!}>{{ bival('nav_services') }}</a>
            <a href="#process"  class="nav__link" {!! bitext('nav_process') !!}>{{ bival('nav_process') }}</a>
            <a href="#contact"  class="nav__link" {!! bitext('nav_contact') !!}>{{ bival('nav_contact') }}</a>
            @if (\App\Support\CostEstimate::enabled())
                <a href="{{ site_url('/estimate') }}" class="nav__link"
                   data-href-en="{{ site_url('/estimate', 'en') }}" data-href-ku="{{ site_url('/estimate', 'ku') }}" {!! bitext('est_nav') !!}>{{ bival('est_nav') }}</a>
            @endif
            {{-- The visitor's hearted projects (saved.js), once there are any --}}
            <a href="{{ site_url('/saved') }}" class="nav__link nav__saved" data-saved-link hidden>
                @include('partials.icon-heart')
                <span {!! bitext('saved_nav') !!}>{{ bival('saved_nav') }}</span>
                <span class="nav__saved-n" data-saved-count></span>
            </a>
            <span class="nav__sep"></span>
            {{-- label flips between "کوردی" and "English", so it needs its own direction --}}
            <button class="lang-toggle" id="langToggle" dir="auto" title="Switch to Kurdish / گۆڕانی زمان">{{ site_lang() === 'ku' ? 'English' : 'کوردی' }}</button>
        </div>

        <a href="{{ site_url('/saved') }}" class="nav__saved-m" data-saved-link hidden aria-label="{{ bival('saved_nav') }}">
            @include('partials.icon-heart')
            <span data-saved-count></span>
        </a>
        <button class="nav__hamburger" id="hamburger" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
    </nav>

    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <a href="{{ site_url('/projects') }}" class="mobile-menu__link"
           data-href-en="{{ site_url('/projects', 'en') }}" data-href-ku="{{ site_url('/projects', 'ku') }}" {!! bitext('nav_projects') !!}>{{ bival('nav_projects') }}</a>
        <a href="#map"      class="mobile-menu__link" {!! bitext('nav_map') !!}>{{ bival('nav_map') }}</a>
        <a href="#about"    class="mobile-menu__link" {!! bitext('nav_about') !!}>{{ bival('nav_about') }}</a>
        <a href="#services" class="mobile-menu__link" {!! bitext('nav_services') !!}>{{ bival('nav_services') }}</a>
        <a href="#process"  class="mobile-menu__link" {!! bitext('nav_process') !!}>{{ bival('nav_process') }}</a>
        <a href="#contact"  class="mobile-menu__link" {!! bitext('nav_contact') !!}>{{ bival('nav_contact') }}</a>
        @if (\App\Support\CostEstimate::enabled())
            <a href="{{ site_url('/estimate') }}" class="mobile-menu__link"
               data-href-en="{{ site_url('/estimate', 'en') }}" data-href-ku="{{ site_url('/estimate', 'ku') }}" {!! bitext('est_nav') !!}>{{ bival('est_nav') }}</a>
        @endif
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
                <span class="hero__line"><span class="hero__line-in"><span dir="auto" data-en="{{ $l1MainEn }}" data-ku="{{ $l1MainKu }}">{{ bi($l1MainEn, $l1MainKu) }}</span><span class="hero__by" dir="auto" data-en="{{ $l1ByEn }}" data-ku="{{ $l1ByKu }}">{{ bi($l1ByEn, $l1ByKu) }}</span></span></span>
                <span class="hero__line"><span class="hero__line-in hero__line-in--accent" dir="auto" data-en="{{ setting('hero_title_line2_en') }}" data-ku="{{ setting('hero_title_line2_ku') }}">{{ bi(setting('hero_title_line2_en'), setting('hero_title_line2_ku')) }}</span></span>
            </h1>

            {{-- Practice figures — rendered as the 3D glass card ring in
                 hero3d.js. This hidden list is the single source of truth
                 (and what screen readers announce); the canvas is decorative. --}}
            @php
                $areaNum = (int) $stats['area_short'];
                $areaSuf = trim(preg_replace('/[\d.,]/', '', $stats['area_short']));
            @endphp
            <div id="heroStatsData" class="sr-only">
                <span data-value="{{ $stats['projects'] }}" dir="auto" data-en="Projects" data-ku="پرۆژە">{{ $stats['projects'] }} {{ bi('Projects', 'پرۆژە') }}</span>
                <span data-value="{{ (int) setting('about_stat2_num') }}" dir="auto" data-en="Clients" data-ku="کڕیار">{{ (int) setting('about_stat2_num') }} {{ bi('Clients', 'کڕیار') }}</span>
                <span data-value="{{ $stats['cities'] }}" dir="auto" data-en="Cities" data-ku="شار">{{ $stats['cities'] }} {{ bi('Cities', 'شار') }}</span>
                <span data-value="{{ $stats['countries'] }}" dir="auto" data-en="Countries" data-ku="وڵات">{{ $stats['countries'] }} {{ bi('Countries', 'وڵات') }}</span>
                <span data-value="{{ $areaNum }}" data-suffix="{{ $areaSuf }}" dir="auto" data-en="m² Designed" data-ku="م² دیزاینکراو">{{ $stats['area_short'] }} {{ bi('m² Designed', 'م² دیزاینکراو') }}</span>
            </div>
        </div>

        {{-- Caption + CTA + drag hint live at the sheet's bottom centre on
             desktop (clear of the ring); on phones they stay in the flow
             right under the headline. --}}
        <div class="hero__foot">
            <p class="hero__sub" dir="auto" data-en="{{ setting('hero_sub_en') }}" data-ku="{{ setting('hero_sub_ku') }}">{{ bi(setting('hero_sub_en'), setting('hero_sub_ku')) }}</p>
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
        <button class="hero__lang" id="heroLang" type="button" aria-label="Switch language / گۆڕینی زمان">{{ site_lang() === 'ku' ? 'EN' : 'KU' }}</button>
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
                        <span class="statbar__label" dir="auto" data-en="{{ $st['en'] }}" data-ku="{{ $st['ku'] }}">{{ bi($st['en'], $st['ku']) }}</span>
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

            {{-- .img-ph rather than the shimmer host class: this frame's own
                 ::after is already the bottom gradient that keeps the number
                 legible over the photo. --}}
            <div class="map-card__img-wrap">
                <span class="img-ph is-loading" id="mapCardPh" aria-hidden="true"></span>
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
                        <dt dir="auto" data-en="Type" data-ku="جۆر">{{ bi('Type', 'جۆر') }}</dt>
                        <dd id="mcSpecType"></dd>
                    </div>
                    <div class="map-card__spec">
                        <dt dir="auto" data-en="Year" data-ku="ساڵ">{{ bi('Year', 'ساڵ') }}</dt>
                        <dd id="mcSpecYear"></dd>
                    </div>
                    <div class="map-card__spec">
                        <dt dir="auto" data-en="Plot Area" data-ku="ڕووبەری زەوی">{{ bi('Plot Area', 'ڕووبەری زەوی') }}</dt>
                        <dd id="mcSpecArea"></dd>
                    </div>
                    <div class="map-card__spec">
                        <dt dir="auto" data-en="Location" data-ku="شوێن">{{ bi('Location', 'شوێن') }}</dt>
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
                            <span class="client-logo__sub" dir="auto" data-en="{{ $c->sub_en }}" data-ku="{{ $c->sub_ku }}">{{ bi($c->sub_en, $c->sub_ku) }}</span>
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
                            <span class="client-logo__sub" dir="auto" data-en="{{ $c->sub_en }}" data-ku="{{ $c->sub_ku }}">{{ bi($c->sub_en, $c->sub_ku) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <!-- ========== SELECTED WORK ========== -->
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
        <section class="quotes" id="quotes" @if ($quotesEn->isNotEmpty()) data-has-en @endif @if ($quotesKu->isNotEmpty()) data-has-ku @endif>
            <p class="section-label section-label--center" {!! bitext('quotes_label') !!}>{{ bival('quotes_label') }}</p>
            <h2 class="quotes__title" {!! bitext('quotes_title') !!}>{{ bival('quotes_title') }}</h2>
            <div class="quotes__stage" id="quotesStage" aria-live="polite">
                @foreach ($quotes as $q)
                    <figure class="quote" @if (filled($q->quote_en)) data-has-en @endif @if (filled($q->quote_ku)) data-has-ku @endif>
                        @include('partials.client-voice', ['cvQuote' => $q])
                        <blockquote class="quote__text" dir="auto" data-en="{{ $q->quote('en') }}" data-ku="{{ $q->quote('ku') }}">{{ $q->quote(site_lang()) ?: $q->quote_en ?: $q->quote_ku }}</blockquote>
                        <figcaption class="quote__by">
                            <span class="quote__name" data-en="{{ $q->personName('en') }}" data-ku="{{ $q->personName('ku') }}">{{ $q->personName(site_lang()) }}</span>
                            @php $roleEn = $q->attribution('en'); $roleKu = $q->attribution('ku'); @endphp
                            @if (filled($roleEn) || filled($roleKu))
                                <span class="quote__role" data-en="{{ $roleEn }}" data-ku="{{ $roleKu }}">{{ site_lang() === 'ku' ? $roleKu : $roleEn }}</span>
                            @endif
                            @if ($q->project?->hasPage())
                                <a class="quote__project" href="{{ $q->project->pageUrl(site_lang()) }}"
                                   data-href-en="{{ $q->project->pageUrl('en') }}" data-href-ku="{{ $q->project->pageUrl('ku') }}">
                                    <span data-en="{{ $q->project->name }}" data-ku="{{ $q->project->name_ku ?: $q->project->name }}">{{ bi($q->project->name, $q->project->name_ku) }}</span>
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
                    <input class="pg__search" id="pgSearch" type="search" placeholder="{{ bi('Search projects…', 'گەڕان بۆ پرۆژەکان…') }}" data-en-ph="Search projects…" data-ku-ph="گەڕان بۆ پرۆژەکان…" autocomplete="off" aria-label="Search projects">
                    <span class="pg__search-icon" aria-hidden="true">↗&#xFE0E;</span>
                </div>
                {{-- Filter row is generated from the database-managed categories
                     (Manage → Project Categories), so add / remove / reorder there
                     is reflected here. "All" stays put. --}}
                <div class="pg__filter" role="group" aria-label="Filter by typology">
                    <button class="pgf-btn active" data-filter="all" {!! bitext('filter_all') !!}>{{ bival('filter_all') }}</button>
                    @foreach($categories as $cat)
                    <button class="pgf-btn" data-filter="{{ $cat->key }}" dir="auto" data-en="{{ $cat->name }}" data-ku="{{ $cat->name_ku ?: $cat->name }}">{{ bi($cat->name, $cat->name_ku) }}</button>
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
                            <h3 class="pgc__name" dir="auto" data-en="{{ $project->name }}" data-ku="{{ $project->name_ku ?: $project->name }}">{{ bi($project->name, $project->name_ku) }}</h3>
                            <p class="pgc__meta">{{ $project->metaLabel() }}</p>
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
                <a class="pgc__link" href="{{ $project->pageUrl(site_lang()) }}" draggable="false" aria-label="{{ bi($project->name, $project->name_ku) }}"
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
                  data-ku="ڕایبکێشە بۆ زیاتر — {{ $galleryCount }} پرۆژە">{{ bi('slide for more — '.$galleryCount.' projects', 'ڕایبکێشە بۆ زیاتر — '.$galleryCount.' پرۆژە') }}</span>
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
                    <p class="section-label" dir="auto" data-en="{{ setting('about_label_en') }}" data-ku="{{ setting('about_label_ku') }}">{{ bi(setting('about_label_en'), setting('about_label_ku')) }}</p>
                    <h2 class="about__heading" dir="auto" data-en="{{ setting('about_heading_en') }}" data-ku="{{ setting('about_heading_ku') }}">
                        {!! bi(setting('about_heading_en'), setting('about_heading_ku')) !!}
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
                        {{-- .img-ph again — the portrait frame's ::after is the
                             caption gradient. --}}
                        <span class="img-ph is-loading" aria-hidden="true"></span>
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
            // stroke-based line icons in the site's drafting language; 'to' is
            // where a card leads on /projects — every project, a category or a
            // status — and a card with nothing to show there leads to Contact
            $services = [
                ['en' => 'Architectural Design', 'ku' => 'دیزاینی تەڵارسازی',
                 'den' => 'Complete building design — from first concept to approved drawings.',
                 'dku' => 'دیزاینی تەواوی بینا — لە یەکەم بیرۆکەوە تا نەخشەی پەسەندکراو.',
                 'to' => [],
                 'icon' => '<circle cx="18" cy="7" r="3" stroke-width="1.8"/><line x1="16.4" y1="9.8" x2="9" y2="30" stroke-width="2"/><line x1="19.6" y1="9.8" x2="27" y2="30" stroke-width="2"/><path d="M11.5 24.5a13 13 0 0 0 13 0" stroke-width="1.8"/>'],
                ['en' => 'Exterior Design', 'ku' => 'دیزاینی دەرەکی',
                 'den' => 'Façades and outdoor environments that give a building its public face.',
                 'dku' => 'ڕووکار و ژینگەی دەرەوە کە ڕوخساری گشتیی بینا دروستدەکەن.',
                 'to' => ['type' => 'exterior-design'],
                 'icon' => '<rect x="4" y="8" width="15" height="24" stroke-width="1.8"/><line x1="8" y1="14" x2="15" y2="14" stroke-width="1.6"/><line x1="8" y1="20" x2="15" y2="20" stroke-width="1.6"/><line x1="8" y1="26" x2="15" y2="26" stroke-width="1.6"/><circle cx="27" cy="22" r="4" stroke-width="1.8"/><line x1="27" y1="26" x2="27" y2="32" stroke-width="1.8"/><line x1="22" y1="32" x2="32" y2="32" stroke-width="1.8"/>'],
                ['en' => 'Interior Design', 'ku' => 'دیزاینی ناوەکی',
                 'den' => 'Spatial planning, materials and light — interiors made to be lived in.',
                 'dku' => 'پلاندانانی بۆشایی، کەرەستە و ڕووناکی — ناوەوەیەک بۆ ژیان.',
                 'to' => ['type' => 'interior-design'],
                 'icon' => '<path d="M8 19v-7a3 3 0 0 1 3-3h14a3 3 0 0 1 3 3v7" stroke-width="1.8"/><path d="M5 19a2.5 2.5 0 0 1 5 0v2h16v-2a2.5 2.5 0 0 1 5 0v6a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2z" stroke-width="1.8"/><line x1="9" y1="29" x2="9" y2="32" stroke-width="1.8"/><line x1="27" y1="29" x2="27" y2="32" stroke-width="1.8"/>'],
                ['en' => 'Construction', 'ku' => 'بیناسازی',
                 'den' => "On-site execution and supervision with Kurdistan's finest builders.",
                 'dku' => 'جێبەجێکردن و سەرپەرشتی مەیدانی لەگەڵ باشترین بیناسازانی کوردستان.',
                 'to' => ['status' => 'under-construction'],
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

            // how many projects each card would open on /projects
            $withPage = $projects->filter(fn ($p) => $p->hasPage());
            $byType = $withPage->flatMap(fn ($p) => $p->categoryKeys())->countBy();
            $byStatus = $withPage->countBy(fn ($p) => $p->statusKey());
            $text = fn (string $lang, string $key) => $lang === 'ku' ? \App\Support\SiteText::ku($key) : \App\Support\SiteText::en($key);
            $serviceLink = function (array $s) use ($withPage, $byType, $byStatus, $text) {
                $to = $s['to'] ?? null;
                $n = match (true) {
                    $to === null => 0,
                    isset($to['type']) => $byType[$to['type']] ?? 0,
                    isset($to['status']) => $byStatus[$to['status']] ?? 0,
                    default => $withPage->count(),
                };
                if ($n === 0) {
                    return ['href' => '#contact', 'en' => $text('en', 'services_ask'), 'ku' => $text('ku', 'services_ask'), 'arrow' => '→'];
                }
                $path = '/projects'.($to ? '?'.http_build_query($to) : '');
                $say = fn (string $lang) => $n === 1
                    ? $text($lang, 'services_see_one')
                    : str_replace('{n}', (string) $n, $text($lang, 'services_see'));

                return ['href' => site_url($path), 'href_en' => site_url($path, 'en'), 'href_ku' => site_url($path, 'ku'), 'en' => $say('en'), 'ku' => $say('ku'), 'arrow' => '↗'];
            };
        @endphp

        <div class="services__inner">
            <div class="services__head">
                <p class="section-label section-label--center" {!! bitext('services_label') !!}>{{ bival('services_label') }}</p>
                <h2 class="services__title" {!! bitext('services_title') !!}>{{ bival('services_title') }}</h2>
            </div>
            <div class="services__grid">
                @foreach ($services as $i => $s)
                    @php $go = $serviceLink($s); @endphp
                    {{-- each card opens its work on /projects, or the contact form --}}
                    <a class="service{{ $i >= 4 ? ' service--third' : '' }}" href="{{ $go['href'] }}"
                       @isset($go['href_en']) data-href-en="{{ $go['href_en'] }}" data-href-ku="{{ $go['href_ku'] }}" @endisset>
                        <div class="service__top">
                            <svg class="service__icon" viewBox="0 0 36 36" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $s['icon'] !!}</svg>
                            <span class="service__num">0{{ $i + 1 }}</span>
                        </div>
                        <h3 class="service__name" dir="auto" data-en="{{ $s['en'] }}" data-ku="{{ $s['ku'] }}">{{ bi($s['en'], $s['ku']) }}</h3>
                        <p class="service__desc" dir="auto" data-en="{{ $s['den'] }}" data-ku="{{ $s['dku'] }}">{{ bi($s['den'], $s['dku']) }}</p>
                        <span class="service__go">
                            <span data-en="{{ $go['en'] }}" data-ku="{{ $go['ku'] }}">{{ bi($go['en'], $go['ku']) }}</span>
                            <span class="service__go-arrow" aria-hidden="true">{{ $go['arrow'] }}&#xFE0E;</span>
                        </span>
                    </a>
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
                <p class="section-label" dir="auto" data-en="{{ setting('process_label_en') }}" data-ku="{{ setting('process_label_ku') }}">{{ bi(setting('process_label_en'), setting('process_label_ku')) }}</p>
                <h2 class="process__title" dir="auto" data-en="{{ setting('process_title_en') }}" data-ku="{{ setting('process_title_ku') }}">{{ bi(setting('process_title_en'), setting('process_title_ku')) }}</h2>

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
                        <span class="process__ghost" aria-hidden="true" dir="auto" data-en="01" data-ku="٠١">{{ bi('01', '٠١') }}</span>
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
                        <span class="process__ghost" aria-hidden="true" dir="auto" data-en="02" data-ku="٠٢">{{ bi('02', '٠٢') }}</span>
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
                        <span class="process__ghost" aria-hidden="true" dir="auto" data-en="03" data-ku="٠٣">{{ bi('03', '٠٣') }}</span>
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
                        <span class="process__ghost" aria-hidden="true" dir="auto" data-en="04" data-ku="٠٤">{{ bi('04', '٠٤') }}</span>
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
                <p class="section-label section-label--light" dir="auto" data-en="{{ setting('heritage_label_en') }}" data-ku="{{ setting('heritage_label_ku') }}">{{ bi(setting('heritage_label_en'), setting('heritage_label_ku')) }}</p>
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

    {{-- ========== COST ESTIMATE ==========
         One line and a button to the estimator (/estimate), just before the
         contact form: the question most people come with, answered first. --}}
    @if (\App\Support\CostEstimate::enabled())
        <section class="est-band" id="estimate">
            <div class="est-band__inner">
                <div class="est-band__text">
                    <p class="section-label" {!! bitext('est_label') !!}>{{ bival('est_label') }}</p>
                    <h2 class="est-band__title" {!! bitext('est_band_title') !!}>{{ bival('est_band_title') }}</h2>
                    <p class="est-band__sub" {!! bitext('est_band_text') !!}>{{ bival('est_band_text') }}</p>
                </div>
                <a class="est-band__btn" href="{{ site_url('/estimate') }}"
                   data-href-en="{{ site_url('/estimate', 'en') }}" data-href-ku="{{ site_url('/estimate', 'ku') }}">
                    <span {!! bitext('est_band_btn') !!}>{{ bival('est_band_btn') }}</span>
                    <span class="est-band__arrow" aria-hidden="true">→</span>
                </a>
            </div>
        </section>
    @endif

    <!-- ========== CONTACT ========== -->
    <section class="contact" id="contact">
        <div class="contact__bg" aria-hidden="true">
            <span class="contact__aurora"></span>
            <span class="contact__grid"></span>
        </div>

        <div class="contact__inner">

            <div class="contact__info">
                <p class="section-label" dir="auto" data-en="{{ setting('contact_label_en') }}" data-ku="{{ setting('contact_label_ku') }}">{{ bi(setting('contact_label_en'), setting('contact_label_ku')) }}</p>
                <h2 class="contact__title" dir="auto" data-en="{{ setting('contact_title_en') }}" data-ku="{{ setting('contact_title_ku') }}">{!! bi(setting('contact_title_en'), setting('contact_title_ku')) !!}</h2>

                <ul class="contact__methods">
                    {{-- WhatsApp first — it is how most clients here would rather
                         start. Hidden until a number is set in Site Content. --}}
                    @if ($waUrl = whatsapp_url(bival('wa_text')))
                        <li>
                            <a class="cmethod cmethod--link cmethod--wa" href="{{ $waUrl }}" target="_blank" rel="noopener" data-wa="contact"
                               data-href-en="{{ whatsapp_url(\App\Support\SiteText::en('wa_text')) }}"
                               data-href-ku="{{ whatsapp_url(\App\Support\SiteText::ku('wa_text')) }}">
                                <span class="cmethod__icon">@include('partials.icon-whatsapp')</span>
                                <span class="cmethod__text">
                                    <span class="cmethod__label" {!! bitext('wa_label') !!}>{{ bival('wa_label') }}</span>
                                    <span class="cmethod__value" dir="ltr">{{ setting('contact_whatsapp') }}</span>
                                </span>
                                <span class="cmethod__hint"><span {!! bitext('wa_cta') !!}>{{ bival('wa_cta') }}</span> ↗&#xFE0E;</span>
                            </a>
                        </li>
                    @endif
                    {{-- one email, two lines — the address itself lives on the map card below --}}
                    <li class="cmethod cmethod--copy" data-copy="{{ setting('contact_email_new', 'Info@arammizuri.com') }}" tabindex="0" role="button" aria-label="Copy {{ setting('contact_email_new', 'Info@arammizuri.com') }}">
                        <span class="cmethod__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                        </span>
                        <div class="cmethod__text">
                            <span class="cmethod__label" {!! bitext('contact_email_lbl') !!}>{{ bival('contact_email_lbl') }}</span>
                            <span class="cmethod__value">{{ setting('contact_email_new', 'Info@arammizuri.com') }}</span>
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

            {{-- The enquiry form. Name and one way to reply (e-mail or phone) are
                 all it asks for; the project details fold away so a quick
                 question stays quick. "About" is set when the visitor comes
                 from a project — its page (?project=slug) or the overlay —
                 so the studio knows what they were looking at. --}}
            @php
                $choices = fn (array $options) => collect($options)->map(fn ($o, $key) =>
                    '<option value="'.e($key).'" data-en="'.e($o['en']).'" data-ku="'.e($o['ku']).'">'.e(bi($o['en'], $o['ku'])).'</option>'
                )->implode('');
                $choose = '<option value="" '.bitext('form_choose').'>'.e(bival('form_choose')).'</option>';
            @endphp
            <form class="contact__form" id="contactForm" novalidate>
                <div class="form-about" id="formAbout" hidden>
                    <span class="form-about__label" {!! bitext('form_about') !!}>{{ bival('form_about') }}</span>
                    <span class="form-about__name" id="formAboutName" dir="auto"></span>
                    <button type="button" class="form-about__clear" id="formAboutClear" aria-label="Remove">✕</button>
                </div>
                <input type="hidden" name="about" id="cf-about">
                {{-- Their saved projects go with the enquiry (saved.js) — shown, so
                     nothing is sent they cannot see, and ✕ to leave them off. --}}
                <div class="form-about form-saved" id="formSaved" hidden>
                    <span class="form-about__label">@include('partials.icon-heart')<span {!! bitext('form_saved') !!}>{{ bival('form_saved') }}</span></span>
                    <span class="form-about__name" id="formSavedNames" dir="auto"></span>
                    <button type="button" class="form-about__clear" id="formSavedClear" aria-label="Remove">✕</button>
                </div>

                <div class="form-grid">
                    <div class="form-field form-field--full">
                        <input type="text" id="cf-name" name="name" placeholder=" " required autocomplete="name">
                        <label for="cf-name" {!! bitext('form_name') !!}>{{ bival('form_name') }}</label>
                    </div>
                    <div class="form-field">
                        <input type="tel" id="cf-phone" name="phone" placeholder=" " autocomplete="tel" inputmode="tel" dir="ltr">
                        <label for="cf-phone" {!! bitext('form_phone') !!}>{{ bival('form_phone') }}</label>
                    </div>
                    <div class="form-field">
                        <input type="email" id="cf-email" name="email" placeholder=" " autocomplete="email" dir="ltr">
                        <label for="cf-email" {!! bitext('form_email') !!}>{{ bival('form_email') }}</label>
                    </div>
                    <p class="form-note form-field--full" id="cfReach" {!! bitext('form_reach') !!}>{{ bival('form_reach') }}</p>
                    <div class="form-field form-field--select form-field--full">
                        <select id="cf-type" name="type">{!! $choose !!}{!! $choices(\App\Support\Enquiry::types()) !!}</select>
                        <label for="cf-type" {!! bitext('form_project') !!}>{{ bival('form_project') }}</label>
                    </div>
                </div>

                <details class="form-more" id="formMore">
                    <summary class="form-more__toggle"><span {!! bitext('form_more') !!}>{{ bival('form_more') }}</span></summary>
                    <div class="form-grid">
                        <div class="form-field">
                            <input type="text" id="cf-city" name="city" placeholder=" " autocomplete="address-level2">
                            <label for="cf-city" {!! bitext('form_city') !!}>{{ bival('form_city') }}</label>
                        </div>
                        <div class="form-field">
                            <input type="number" id="cf-plot" name="plot_area" placeholder=" " min="1" step="1" inputmode="numeric" dir="ltr">
                            <label for="cf-plot" {!! bitext('form_plot') !!}>{{ bival('form_plot') }}</label>
                        </div>
                        <div class="form-field form-field--select">
                            <select id="cf-budget" name="budget">{!! $choose !!}{!! $choices(\App\Support\Enquiry::budgets()) !!}</select>
                            <label for="cf-budget" {!! bitext('form_budget') !!}>{{ bival('form_budget') }}</label>
                        </div>
                        <div class="form-field form-field--select">
                            <select id="cf-timeline" name="timeline">{!! $choose !!}{!! $choices(\App\Support\Enquiry::timelines()) !!}</select>
                            <label for="cf-timeline" {!! bitext('form_timeline') !!}>{{ bival('form_timeline') }}</label>
                        </div>
                    </div>
                </details>

                <div class="form-field form-field--grow">
                    <textarea id="cf-message" name="message" placeholder=" " rows="4"></textarea>
                    <label for="cf-message" {!! bitext('form_message') !!}>{{ bival('form_message') }}</label>
                </div>
                {{-- Spam trap: off-screen and out of the tab order, so only bots fill it. --}}
                <div class="form-hp" aria-hidden="true">
                    <label for="cf-website">Website</label>
                    <input type="text" id="cf-website" name="website" tabindex="-1" autocomplete="off">
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
                {{-- always Kurdish, in both versions of the site, so it is always RTL --}}
                <div class="footer__kurdish" dir="rtl">{{ setting('footer_kurdish') }}</div>
            </div>

            <div class="footer__col footer__col--sun">
                <div class="footer__sun-wrap" id="footerSun"></div>
            </div>

            <div class="footer__col footer__col--links">
                <nav class="footer__social">
                    <a href="{{ site_url('/careers') }}" class="footer__link"
                       data-href-en="{{ site_url('/careers', 'en') }}" data-href-ku="{{ site_url('/careers', 'ku') }}"
                       {!! bitext('careers_link') !!}>{{ bival('careers_link') }}</a>
                    @if (\App\Support\CostEstimate::enabled())
                        <a href="{{ site_url('/estimate') }}" class="footer__link"
                           data-href-en="{{ site_url('/estimate', 'en') }}" data-href-ku="{{ site_url('/estimate', 'ku') }}"
                           {!! bitext('est_nav') !!}>{{ bival('est_nav') }}</a>
                    @endif
                    @foreach (linked_socials() as $s)
                        @php $u = trim($s['url']); $ext = $u !== '' && $u !== '#'; @endphp
                        <a href="{{ $u !== '' ? $u : '#' }}" class="footer__link"@if($ext) target="_blank" rel="noopener"@endif>{{ $s['label'] }}</a>
                    @endforeach
                </nav>
                <p class="footer__copy">{!! nl2br(e(footer_copy())) !!}</p>
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
                    <h2 class="od-name" id="overlayName"></h2>

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

                    {{-- What the project's own page has beyond these photos: links
                         straight to it, shown only for the parts this project has
                         (set in populateOverlay from its "extras"). --}}
                    <div class="od-extras" id="overlayExtras" hidden>
                        <a class="od-extra" data-extra="film" data-anchor="film" href="#" hidden>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13l11-6.5z"/></svg>
                            <span {!! bitext('pp_film') !!}>{{ bival('pp_film') }}</span>
                        </a>
                        <a class="od-extra" data-extra="compare" data-anchor="render-vs-built" href="#" hidden>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="1.5"/><path d="M12 3v18"/></svg>
                            <span {!! bitext('pp_compare') !!}>{{ bival('pp_compare') }}</span>
                        </a>
                        <a class="od-extra" data-extra="progress" data-anchor="progress" href="#" hidden>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h16M6 20V10l6-5 6 5v10"/><path d="M10 20v-5h4v5"/></svg>
                            <span {!! bitext('pp_progress') !!}>{{ bival('pp_progress') }}</span>
                        </a>
                    </div>

                    {{-- The two ways to act on what was just seen. Both carry the
                         open project: the form gets it as "About", the WhatsApp
                         message names it (set in populateOverlay). --}}
                    <div class="od-cta">
                        <a class="od-cta__btn od-cta__btn--primary" id="overlayEnquire" href="{{ site_url('/') }}#contact">
                            <span {!! bitext('pp_cta_button') !!}>{{ bival('pp_cta_button') }}</span>
                            <span class="od-cta__arrow" aria-hidden="true">→</span>
                        </a>
                        @if (whatsapp_url())
                            <a class="od-cta__btn od-cta__btn--wa" id="overlayWa" href="{{ whatsapp_url() }}" target="_blank" rel="noopener" data-wa="overlay">
                                @include('partials.icon-whatsapp')
                                <span {!! bitext('wa_label') !!}>{{ bival('wa_label') }}</span>
                            </a>
                        @endif
                    </div>

                    {{-- Keep this one (saved.js), or pass it on (engage.js). Pointed
                         at the open project in populateOverlay; hidden for one
                         without a page of its own to link to. --}}
                    <div class="od-share" id="overlayShareRow" hidden>
                        <button class="od-share__btn od-share__btn--save" id="overlaySave" type="button" data-save="" data-save-at="overlay" aria-pressed="false">
                            @include('partials.icon-heart')
                            <span data-save-label>{{ bival('save_add') }}</span>
                        </button>
                        <a class="od-share__btn od-share__btn--wa" id="overlayShareWa" href="#" target="_blank" rel="noopener" data-share="whatsapp" data-share-at="overlay">
                            @include('partials.icon-whatsapp')
                            <span {!! bitext('share_wa') !!}>{{ bival('share_wa') }}</span>
                        </a>
                        @include('partials.share-link', ['shareClass' => 'od-share__btn', 'shareAt' => 'overlay', 'shareProject' => '',
                            'shareUrl' => site_url('/'), 'shareTitle' => bival('seo_title'), 'shareId' => 'overlayShare'])
                    </div>

                </div>
            </div>

        </div>
      </div><!-- /.od-modal -->

    </div>

    {{-- ========== FULL-SIZE IMAGE PROGRESS ==========
         Shown while "View Full Resolution" or the download is fetching the
         untouched original — a multi-megabyte file that is then decoded and
         watermarked. Before this existed the full-res link opened a blank
         tab and the download button appeared to do nothing at all, which is
         the exact wait most likely to be read as a broken site. Percentages
         come from Content-Length; see prepareFullImage in script.js. --}}
    <div class="dlp" id="dlProgress" role="dialog" aria-modal="true" aria-live="polite" aria-label="Preparing full-size image">
        <div class="dlp__panel">
            <div class="dlp__sun" id="dlpSun" aria-hidden="true"></div>
            <p class="dlp__title" id="dlpTitle"></p>
            <p class="dlp__note" id="dlpNote"></p>
            <div class="dlp__bar" id="dlpBar"><div class="dlp__fill" id="dlpFill"></div></div>
            <div class="dlp__stats">
                <span class="dlp__bytes" id="dlpBytes"></span>
                <span class="dlp__pct" id="dlpPct"></span>
            </div>
            <div class="dlp__actions">
                <button class="dlp__btn dlp__btn--gold" id="dlpRetry" type="button" hidden {!! bitext('dl_retry') !!}>{{ bival('dl_retry') }}</button>
                <button class="dlp__btn" id="dlpCancel" type="button" {!! bitext('dl_cancel') !!}>{{ bival('dl_cancel') }}</button>
            </div>
        </div>
    </div>

    {{-- The #kurdOutline SVG filter that used to live here (dilate/erode to
         dissolve the province seams into one united border) is gone: its work
         area scaled with the geometry, not the screen, so it froze pinch-zoom on
         iPad and iPhone. The same outline is now composited on a viewport-sized
         canvas — see KurdistanOutline in script.js. --}}

    @include('partials.whatsapp-fab', ['text' => ['en' => \App\Support\SiteText::en('wa_text'), 'ku' => \App\Support\SiteText::ku('wa_text')], 'hideOver' => '#map, #contact'])

    {{-- Self-hosted — see the note beside the Leaflet stylesheets above. --}}
    <script src="{{ asset('vendor/leaflet.js') }}?v={{ filemtime(public_path('vendor/leaflet.js')) }}"></script>
    <script src="{{ asset('vendor/leaflet-gesture-handling.min.js') }}?v={{ filemtime(public_path('vendor/leaflet-gesture-handling.min.js')) }}"></script>
    {{-- Plain JSON, Kurdish left as Kurdish: Js::from() wrapped it in a string
         and escaped every letter as \uXXXX, which made this block 60% larger.
         The HEX flags keep it inert inside <script> (no "</script>", no quotes). --}}
    <script>window.__SITE__ = {!! json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!};</script>
    <script src="{{ asset('script.js') }}?v={{ filemtime(public_path('script.js')) }}"></script>
    <script src="{{ asset('engage.js') }}?v={{ filemtime(public_path('engage.js')) }}" defer></script>
    @if ($quotesHaveVoices)
        <script src="{{ asset('pencil.js') }}?v={{ filemtime(public_path('pencil.js')) }}" defer></script>
        <script src="{{ asset('voices.js') }}?v={{ filemtime(public_path('voices.js')) }}" defer></script>
    @endif
    @include('partials.saved-script')
    <script src="{{ asset('vendor/three.min.js') }}" defer></script>
    <script src="{{ asset('hero3d.js') }}?v={{ filemtime(public_path('hero3d.js')) }}" defer></script>
</body>
</html>
