<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aram Mizuri Architecture | Erbil, Kurdistan</title>
    <meta name="description" content="Aram Mizuri Architecture — a leading architectural practice based in Erbil, Kurdistan Region. Residential, cultural, commercial and urban design.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('style.css') }}">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
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
            <div class="nav__logo-wordmark">
                <span class="logo-name">{{ setting('brand_name') }}</span>
                <span class="logo-sub">{{ setting('brand_sub') }}</span>
            </div>
        </a>

        <div class="nav__links" id="navLinks">
            <a href="#projects" class="nav__link" data-en="Projects" data-ku="پرۆژەکان">Projects</a>
            <a href="#map"      class="nav__link" data-en="Map"      data-ku="نەخشە">Map</a>
            <a href="#about"    class="nav__link" data-en="About"    data-ku="دەربارەی">About</a>
            <a href="#process"  class="nav__link" data-en="Process"  data-ku="ڕێگا">Process</a>
            <a href="#contact"  class="nav__link" data-en="Contact"  data-ku="پەیوەندی">Contact</a>
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
        <a href="#process"  class="mobile-menu__link" data-en="Process"  data-ku="ڕێگا">Process</a>
        <a href="#contact"  class="mobile-menu__link" data-en="Contact"  data-ku="پەیوەندی">Contact</a>
    </div>

    <!-- ========== HERO ========== -->
    <section class="hero" id="home">
        <div class="hero__bg-pattern"></div>
        <div class="hero__sun-wrap" id="heroSun"></div>

        <div class="hero__content">
            <p class="hero__eyebrow">{{ setting('hero_eyebrow') }}</p>
            <h1 class="hero__title">
                <span class="hero__title-line" data-en="{{ setting('hero_title_line1_en') }}" data-ku="{{ setting('hero_title_line1_ku') }}">{{ setting('hero_title_line1_en') }}</span>
                <span class="hero__title-line hero__title-accent" data-en="{{ setting('hero_title_line2_en') }}" data-ku="{{ setting('hero_title_line2_ku') }}">{{ setting('hero_title_line2_en') }}</span>
            </h1>
            <p class="hero__sub" data-en="{{ setting('hero_sub_en') }}" data-ku="{{ setting('hero_sub_ku') }}">
                {{ setting('hero_sub_en') }}
            </p>
        </div>

        <div class="hero__scroll">
            <span class="hero__scroll-label">Scroll</span>
            <div class="hero__scroll-line"></div>
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
            <p class="section-label" data-en="Where We Build" data-ku="کوێ دەبینین">Where We Build</p>
            <h2 class="proj-map__title">Projects Across<br>Kurdistan &amp; Beyond</h2>
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
                    <span>View Full Project</span>
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
            <div class="map-stat"><span class="map-stat__val">{{ $projects->count() }}</span><span class="map-stat__lbl">Projects</span></div>
            <div class="map-stat__div"></div>
            <div class="map-stat"><span class="map-stat__val">{{ $projects->map(fn ($p) => $p->cityLabel())->filter()->unique()->count() }}</span><span class="map-stat__lbl">Cities</span></div>
            <div class="map-stat__div"></div>
            <div class="map-stat"><span class="map-stat__val">347K</span><span class="map-stat__lbl">m² Designed</span></div>
            <div class="map-stat__div"></div>
            <div class="map-stat"><span class="map-stat__val">17</span><span class="map-stat__lbl">Years Active</span></div>
        </div>

    </section>

    <!-- ========== CLIENTS ========== -->
    <section class="clients" id="clients">
        <div class="clients__head">
            <p class="section-label section-label--center" data-en="Trusted By" data-ku="پشتیوانیكراو">Trusted By</p>
            <h2 class="clients__title">Clients &amp; Partners</h2>
        </div>
        <div class="clients__track-wrap">
            <div class="clients__track">

                <!-- ── Set 1 ── -->
                <div class="client-logo">
                    <svg class="client-logo__mark" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="18" cy="18" r="5.5" fill="#fff"/>
                        <circle cx="18" cy="18" r="10" stroke="#fff" stroke-width="1.2"/>
                        <line x1="18" y1="2"    x2="18" y2="6.5"  stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
                        <line x1="18" y1="29.5" x2="18" y2="34"   stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
                        <line x1="2"  y1="18"   x2="6.5" y2="18"  stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
                        <line x1="29.5" y1="18" x2="34" y2="18"   stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
                        <line x1="5.5"  y1="5.5"  x2="8.8"  y2="8.8"  stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
                        <line x1="27.2" y1="27.2" x2="30.5" y2="30.5" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
                        <line x1="30.5" y1="5.5"  x2="27.2" y2="8.8"  stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
                        <line x1="5.5"  y1="30.5" x2="8.8"  y2="27.2" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span class="client-logo__name">KRG</span>
                    <span class="client-logo__sub">Kurdistan Regional Gov't</span>
                </div>

                <div class="client-logo">
                    <svg class="client-logo__mark" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 27 Q18 6 30 27" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/>
                        <path d="M10 31 Q18 15 26 31" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/>
                        <circle cx="18" cy="33.5" r="2.5" fill="#fff"/>
                    </svg>
                    <span class="client-logo__name">Korek</span>
                    <span class="client-logo__sub">Telecom</span>
                </div>

                <div class="client-logo">
                    <svg class="client-logo__mark" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <polyline points="4,30 4,6 20,30 20,6" stroke="#fff" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>
                        <line x1="26" y1="6"  x2="32" y2="6"  stroke="#fff" stroke-width="2.8" stroke-linecap="round"/>
                        <line x1="29" y1="6"  x2="29" y2="22" stroke="#fff" stroke-width="2.8" stroke-linecap="round"/>
                    </svg>
                    <span class="client-logo__name">NRT</span>
                    <span class="client-logo__sub">Media Group</span>
                </div>

                <div class="client-logo">
                    <svg class="client-logo__mark" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <polyline points="4,28 18,8 32,28" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <polyline points="4,22 18,2 32,22" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity="0.4"/>
                    </svg>
                    <span class="client-logo__name">Falcon</span>
                    <span class="client-logo__sub">Real Estate</span>
                </div>

                <div class="client-logo">
                    <svg class="client-logo__mark" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <polygon points="18,3 33,18 18,33 3,18" stroke="#fff" stroke-width="2"/>
                        <polygon points="18,11 25,18 18,25 11,18" fill="#fff"/>
                    </svg>
                    <span class="client-logo__name">Maeen</span>
                    <span class="client-logo__sub">Holdings Group</span>
                </div>

                <div class="client-logo">
                    <svg class="client-logo__mark" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M18 3 C22 7 28 13 28 21 C28 27.6 23.5 33 18 33 C12.5 33 8 27.6 8 21 C8 14 12 10 12 10 C13 15 15 18 18 18 C18 18 14 9 18 3Z" fill="#fff"/>
                    </svg>
                    <span class="client-logo__name">Dana Gas</span>
                    <span class="client-logo__sub">Energy</span>
                </div>

                <div class="client-logo">
                    <svg class="client-logo__mark" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <polygon points="18,2 31,9.5 31,26.5 18,34 5,26.5 5,9.5" stroke="#fff" stroke-width="2"/>
                        <polygon points="18,9 25.5,13.5 25.5,22.5 18,27 10.5,22.5 10.5,13.5" fill="#fff" opacity="0.4"/>
                    </svg>
                    <span class="client-logo__name">Safeen</span>
                    <span class="client-logo__sub">Holdings</span>
                </div>

                <div class="client-logo">
                    <svg class="client-logo__mark" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <line x1="3"  y1="33" x2="33" y2="33" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
                        <polyline points="3,19 18,6 33,19" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <line x1="9"  y1="19" x2="9"  y2="33" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
                        <line x1="18" y1="19" x2="18" y2="33" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
                        <line x1="27" y1="19" x2="27" y2="33" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <span class="client-logo__name">Kurdistan Bank</span>
                    <span class="client-logo__sub">Financial Services</span>
                </div>

                <div class="client-logo">
                    <svg class="client-logo__mark" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <polyline points="2,30 2,6 12,22 18,14 24,22 34,6 34,30" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span class="client-logo__name">Mazi Group</span>
                    <span class="client-logo__sub">Retail &amp; Real Estate</span>
                </div>

                <div class="client-logo">
                    <svg class="client-logo__mark" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <line x1="18"  y1="3"   x2="18"  y2="33"  stroke="#fff" stroke-width="2" stroke-linecap="round"/>
                        <line x1="3"   y1="18"  x2="33"  y2="18"  stroke="#fff" stroke-width="2" stroke-linecap="round"/>
                        <line x1="7.2" y1="7.2" x2="28.8" y2="28.8" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
                        <line x1="28.8" y1="7.2" x2="7.2" y2="28.8" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <span class="client-logo__name">Darin Hotels</span>
                    <span class="client-logo__sub">Hospitality</span>
                </div>

                <!-- ── Set 2 (duplicate for seamless loop) ── -->
                <div class="client-logo">
                    <svg class="client-logo__mark" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="18" cy="18" r="5.5" fill="#fff"/>
                        <circle cx="18" cy="18" r="10" stroke="#fff" stroke-width="1.2"/>
                        <line x1="18" y1="2"    x2="18" y2="6.5"  stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
                        <line x1="18" y1="29.5" x2="18" y2="34"   stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
                        <line x1="2"  y1="18"   x2="6.5" y2="18"  stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
                        <line x1="29.5" y1="18" x2="34" y2="18"   stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
                        <line x1="5.5"  y1="5.5"  x2="8.8"  y2="8.8"  stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
                        <line x1="27.2" y1="27.2" x2="30.5" y2="30.5" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
                        <line x1="30.5" y1="5.5"  x2="27.2" y2="8.8"  stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
                        <line x1="5.5"  y1="30.5" x2="8.8"  y2="27.2" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span class="client-logo__name">KRG</span>
                    <span class="client-logo__sub">Kurdistan Regional Gov't</span>
                </div>

                <div class="client-logo">
                    <svg class="client-logo__mark" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 27 Q18 6 30 27" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/>
                        <path d="M10 31 Q18 15 26 31" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/>
                        <circle cx="18" cy="33.5" r="2.5" fill="#fff"/>
                    </svg>
                    <span class="client-logo__name">Korek</span>
                    <span class="client-logo__sub">Telecom</span>
                </div>

                <div class="client-logo">
                    <svg class="client-logo__mark" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <polyline points="4,30 4,6 20,30 20,6" stroke="#fff" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>
                        <line x1="26" y1="6"  x2="32" y2="6"  stroke="#fff" stroke-width="2.8" stroke-linecap="round"/>
                        <line x1="29" y1="6"  x2="29" y2="22" stroke="#fff" stroke-width="2.8" stroke-linecap="round"/>
                    </svg>
                    <span class="client-logo__name">NRT</span>
                    <span class="client-logo__sub">Media Group</span>
                </div>

                <div class="client-logo">
                    <svg class="client-logo__mark" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <polyline points="4,28 18,8 32,28" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <polyline points="4,22 18,2 32,22" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity="0.4"/>
                    </svg>
                    <span class="client-logo__name">Falcon</span>
                    <span class="client-logo__sub">Real Estate</span>
                </div>

                <div class="client-logo">
                    <svg class="client-logo__mark" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <polygon points="18,3 33,18 18,33 3,18" stroke="#fff" stroke-width="2"/>
                        <polygon points="18,11 25,18 18,25 11,18" fill="#fff"/>
                    </svg>
                    <span class="client-logo__name">Maeen</span>
                    <span class="client-logo__sub">Holdings Group</span>
                </div>

                <div class="client-logo">
                    <svg class="client-logo__mark" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M18 3 C22 7 28 13 28 21 C28 27.6 23.5 33 18 33 C12.5 33 8 27.6 8 21 C8 14 12 10 12 10 C13 15 15 18 18 18 C18 18 14 9 18 3Z" fill="#fff"/>
                    </svg>
                    <span class="client-logo__name">Dana Gas</span>
                    <span class="client-logo__sub">Energy</span>
                </div>

                <div class="client-logo">
                    <svg class="client-logo__mark" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <polygon points="18,2 31,9.5 31,26.5 18,34 5,26.5 5,9.5" stroke="#fff" stroke-width="2"/>
                        <polygon points="18,9 25.5,13.5 25.5,22.5 18,27 10.5,22.5 10.5,13.5" fill="#fff" opacity="0.4"/>
                    </svg>
                    <span class="client-logo__name">Safeen</span>
                    <span class="client-logo__sub">Holdings</span>
                </div>

                <div class="client-logo">
                    <svg class="client-logo__mark" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <line x1="3"  y1="33" x2="33" y2="33" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
                        <polyline points="3,19 18,6 33,19" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <line x1="9"  y1="19" x2="9"  y2="33" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
                        <line x1="18" y1="19" x2="18" y2="33" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
                        <line x1="27" y1="19" x2="27" y2="33" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <span class="client-logo__name">Kurdistan Bank</span>
                    <span class="client-logo__sub">Financial Services</span>
                </div>

                <div class="client-logo">
                    <svg class="client-logo__mark" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <polyline points="2,30 2,6 12,22 18,14 24,22 34,6 34,30" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span class="client-logo__name">Mazi Group</span>
                    <span class="client-logo__sub">Retail &amp; Real Estate</span>
                </div>

                <div class="client-logo">
                    <svg class="client-logo__mark" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <line x1="18"  y1="3"   x2="18"  y2="33"  stroke="#fff" stroke-width="2" stroke-linecap="round"/>
                        <line x1="3"   y1="18"  x2="33"  y2="18"  stroke="#fff" stroke-width="2" stroke-linecap="round"/>
                        <line x1="7.2" y1="7.2" x2="28.8" y2="28.8" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
                        <line x1="28.8" y1="7.2" x2="7.2" y2="28.8" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <span class="client-logo__name">Darin Hotels</span>
                    <span class="client-logo__sub">Hospitality</span>
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
                    <input class="pg__search" id="pgSearch" type="search" placeholder="Search projects…" autocomplete="off" aria-label="Search projects">
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
                            <h3 class="pgc__name">{{ $project->name }}</h3>
                            <p class="pgc__meta">{{ $project->metaLabel() }}</p>
                        </div>
                        <span class="pgc__cta">View Project <span class="pgc__cta-arrow">↗</span></span>
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

        <div class="pg__empty" id="pgEmpty" hidden>
            <p class="pg__empty-text">No projects match your criteria.</p>
        </div>

    </section>

    <!-- ========== ABOUT ========== -->
    <section class="about" id="about">
        <div class="about__pattern"></div>
        <div class="about__inner">
            <div class="about__text">
                <p class="section-label" data-en="{{ setting('about_label_en') }}" data-ku="{{ setting('about_label_ku') }}">{{ setting('about_label_en') }}</p>
                <h2 class="about__heading" data-en="{{ setting('about_heading_en') }}" data-ku="{{ setting('about_heading_ku') }}">
                    {!! setting('about_heading_en') !!}
                </h2>
                <p class="about__bio">
                    {{ setting('about_bio_1') }}
                </p>
                <p class="about__bio">
                    {{ setting('about_bio_2') }}
                </p>
                <div class="about__stats">
                    <div class="stat">
                        <div class="stat__num" data-target="{{ setting('about_stat1_num') }}">0</div>
                        <div class="stat__label">{{ setting('about_stat1_label') }}</div>
                    </div>
                    <div class="stat">
                        <div class="stat__num" data-target="{{ setting('about_stat2_num') }}">0</div>
                        <div class="stat__label">{{ setting('about_stat2_label') }}</div>
                    </div>
                    <div class="stat">
                        <div class="stat__num" data-target="{{ setting('about_stat3_num') }}">0</div>
                        <div class="stat__label">{{ setting('about_stat3_label') }}</div>
                    </div>
                    <div class="stat">
                        <div class="stat__num" data-target="{{ setting('about_stat4_num') }}">0</div>
                        <div class="stat__label">{{ setting('about_stat4_label') }}</div>
                    </div>
                </div>

                <blockquote class="about__quote">
                    <p>{{ setting('about_quote') }}</p>
                    <cite>{{ setting('about_quote_cite') }}</cite>
                </blockquote>
            </div>

            <div class="about__side">
                <div class="about__portrait">
                    <div class="portrait__sun" id="portraitSun"></div>
                    <img class="portrait__photo" src="{{ \App\Models\Project::resolveImage(setting('about_portrait_img')) }}" alt="Aram Mizuri, Principal Architect" style="position:absolute;inset:0;z-index:1;">
                    <div class="portrait__caption">{{ setting('about_portrait_caption') }}</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== PROCESS ========== -->
    <section class="process" id="process">
        <div class="process__inner">
            <div class="process__head">
                <p class="section-label" data-en="{{ setting('process_label_en') }}" data-ku="{{ setting('process_label_ku') }}">{{ setting('process_label_en') }}</p>
                <h2 class="process__title" data-en="{{ setting('process_title_en') }}" data-ku="{{ setting('process_title_ku') }}">{{ setting('process_title_en') }}</h2>
            </div>
            <div class="process__steps">
                <div class="process__step reveal">
                    <div class="step__num">01</div>
                    <div class="step__icon">◈</div>
                    <h3 class="step__title" data-en="{{ setting('process_step1_title_en') }}" data-ku="{{ setting('process_step1_title_ku') }}">{{ setting('process_step1_title_en') }}</h3>
                    <p class="step__desc">{{ setting('process_step1_desc') }}</p>
                </div>
                <div class="process__step reveal reveal-delay-1">
                    <div class="step__num">02</div>
                    <div class="step__icon">◈</div>
                    <h3 class="step__title" data-en="{{ setting('process_step2_title_en') }}" data-ku="{{ setting('process_step2_title_ku') }}">{{ setting('process_step2_title_en') }}</h3>
                    <p class="step__desc">{{ setting('process_step2_desc') }}</p>
                </div>
                <div class="process__step reveal reveal-delay-2">
                    <div class="step__num">03</div>
                    <div class="step__icon">◈</div>
                    <h3 class="step__title" data-en="{{ setting('process_step3_title_en') }}" data-ku="{{ setting('process_step3_title_ku') }}">{{ setting('process_step3_title_en') }}</h3>
                    <p class="step__desc">{{ setting('process_step3_desc') }}</p>
                </div>
                <div class="process__step reveal reveal-delay-3">
                    <div class="step__num">04</div>
                    <div class="step__icon">◈</div>
                    <h3 class="step__title" data-en="{{ setting('process_step4_title_en') }}" data-ku="{{ setting('process_step4_title_ku') }}">{{ setting('process_step4_title_en') }}</h3>
                    <p class="step__desc">{{ setting('process_step4_desc') }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== HERITAGE ========== -->
    <section class="heritage">
        <div class="heritage__inner">
            <div class="heritage__text reveal">
                <p class="section-label section-label--light" data-en="{{ setting('heritage_label_en') }}" data-ku="{{ setting('heritage_label_ku') }}">{{ setting('heritage_label_en') }}</p>
                <h2 class="heritage__title">
                    {!! nl2br(e(setting('heritage_title'))) !!}
                </h2>
                <p class="heritage__desc">
                    {{ setting('heritage_desc') }}
                </p>
                <a href="#projects" class="heritage__cta">
                    <span>{{ setting('heritage_cta') }}</span>
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
        <div class="contact__inner">
            <div class="contact__info">
                <p class="section-label" data-en="{{ setting('contact_label_en') }}" data-ku="{{ setting('contact_label_ku') }}">{{ setting('contact_label_en') }}</p>
                <h2 class="contact__title" data-en="{{ setting('contact_title_en') }}" data-ku="{{ setting('contact_title_ku') }}">{!! setting('contact_title_en') !!}</h2>

                <dl class="contact__details">
                    <div class="contact__row">
                        <dt>{{ setting('contact_studio_label') }}</dt>
                        <dd>{!! setting('contact_studio_value') !!}</dd>
                    </div>
                    <div class="contact__row">
                        <dt>{{ setting('contact_newwork_label') }}</dt>
                        <dd>{{ setting('contact_email_new') }}</dd>
                    </div>
                    <div class="contact__row">
                        <dt>{{ setting('contact_general_label') }}</dt>
                        <dd>{{ setting('contact_email_general') }}</dd>
                    </div>
                    <div class="contact__row">
                        <dt>{{ setting('contact_phone_label') }}</dt>
                        <dd>{{ setting('contact_phone') }}</dd>
                    </div>
                </dl>
            </div>

            <form class="contact__form" id="contactForm" novalidate>
                <div class="form-group">
                    <input type="text"  name="name"    placeholder="Your Name"       required autocomplete="off">
                    <div class="form-line"></div>
                </div>
                <div class="form-group">
                    <input type="email" name="email"   placeholder="Email Address"   required autocomplete="off">
                    <div class="form-line"></div>
                </div>
                <div class="form-group">
                    <input type="text"  name="project" placeholder="Project Type"              autocomplete="off">
                    <div class="form-line"></div>
                </div>
                <div class="form-group">
                    <textarea name="message" placeholder="Tell us about your project" rows="4"></textarea>
                    <div class="form-line"></div>
                </div>
                <button type="submit" class="form-submit">
                    <span class="form-submit__label">Send Message</span>
                    <span class="form-submit__arrow">→</span>
                </button>
                <p class="form-success" id="formSuccess" hidden>
                    ✓ Message received — we'll be in touch soon.
                </p>
            </form>
        </div>
    </section>

    <!-- ========== FOOTER ========== -->
    <footer class="footer">
        <div class="footer__inner">
            <div class="footer__col footer__col--brand">
                <div class="footer__logo">{{ setting('footer_logo') }}</div>
                <div class="footer__tagline">{{ setting('footer_tagline') }}</div>
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
      <div class="od-modal">

        <!-- Fixed top bar -->
        <div class="proj-overlay__topbar">
            <button class="proj-overlay__back" id="projOverlayClose" aria-label="Back to projects">Back to Projects</button>
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
                            <dt>Location</dt>
                            <dd id="overlayLocation"></dd>
                        </div>
                        <div class="od-spec">
                            <dt>Year</dt>
                            <dd id="overlayYear"></dd>
                        </div>
                        <div class="od-spec">
                            <dt>Typology</dt>
                            <dd id="overlayTypology"></dd>
                        </div>
                        <div class="od-spec">
                            <dt>Plot Area</dt>
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
    <script>window.__SITE__ = {!! \Illuminate\Support\Js::from($payload) !!};</script>
    <script src="{{ asset('script.js') }}"></script>
</body>
</html>
