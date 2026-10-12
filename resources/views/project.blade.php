@php
    /** @var \App\Models\Project $project */
    $lang = site_lang();
    $isKu = $lang === 'ku';
    $other = $isKu ? 'en' : 'ku';
    $path = '/projects/'.$project->slug;

    $name = bi($project->name, $project->name_ku);
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $title = $name.' — '.$brand;
    $desc = bi($project->desc, $project->desc_ku);
    // The narrative is a quote in the architect's own words, so it is shown
    // only in the language it was written in — never the other one standing in.
    $narrative = $isKu ? $project->narrative_ku : $project->narrative;
    $categories = $project->categoryLabelsIn($lang);
    $summary = $desc ?: collect([$project->typology, $project->location, $project->year])->filter()->implode(' · ');
    $metaDescription = \Illuminate\Support\Str::limit(preg_replace('/\s+/u', ' ', (string) $summary), 160);

    // index-aligned, cover first: display size, card size, full original
    $images = $project->orderedVariantUrls('lg');
    $thumbs = $project->orderedVariantUrls('thumb');
    $srcset = fn (int $i) => ($thumbs[$i] ?? null) && $thumbs[$i] !== $images[$i]
        ? $thumbs[$i].' 1200w, '.$images[$i].' 2560w' : null;

    $ogImage = $project->socialImageUrl();
    $ogIsCard = str_contains((string) $ogImage, '/projects/og/');

    $tiles = carto_tiles('dark_all');
    $hasMap = $project->lat && $project->lng && $tiles;

    $facts = array_filter([
        bival('overlay_typology') => $project->typology,
        bival('overlay_year') => $project->year,
        bival('overlay_plot') => $project->area,
        bival('overlay_location') => $project->location,
        bival('pp_category') => implode(' · ', $categories),
    ], 'filled');

    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => $brand, 'item' => site_url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => $name, 'item' => site_url($path)],
                ],
            ],
            array_filter([
                '@type' => 'CreativeWork',
                '@id' => site_url($path).'#project',
                'name' => $name,
                'description' => $summary ?: null,
                'url' => site_url($path),
                'inLanguage' => $lang,
                'image' => $images,
                'genre' => implode(', ', $categories) ?: null,
                'dateCreated' => preg_match('/^\d{4}$/', $project->endYear()) ? $project->endYear() : null,
                'creator' => ['@type' => 'ProfessionalService', '@id' => url('/').'#studio', 'name' => 'Aram Mizuri Architecture', 'url' => site_url('/')],
                'locationCreated' => array_filter([
                    '@type' => 'Place',
                    'name' => $project->location ?: null,
                    'geo' => $project->lat && $project->lng
                        ? ['@type' => 'GeoCoordinates', 'latitude' => $project->lat, 'longitude' => $project->lng] : null,
                ]),
            ]),
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $isKu ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    @include('partials.seo-alternates', ['path' => $path])

    {{-- The preview a shared link to this project shows: its own cover, cut
         to 1200x630 around the cover's focal point (Project::socialImageUrl). --}}
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="Aram Mizuri Architecture">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ site_url($path) }}">
    @if ($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
        <meta property="og:image:alt" content="{{ $name }}">
        @if ($ogIsCard)
            <meta property="og:image:type" content="image/jpeg">
            <meta property="og:image:width" content="1200">
            <meta property="og:image:height" content="630">
        @endif
        <meta name="twitter:image" content="{{ $ogImage }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- Non-blocking, as on the homepage: a slow fonts host must not hold the page. --}}
    @php $fontsHref = 'https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500&family=Vazirmatn:wght@200;300;400;500;600&display=swap'; @endphp
    <link href="{{ $fontsHref }}" rel="stylesheet" media="print" onload="this.media='all';this.onload=null">
    <noscript><link href="{{ $fontsHref }}" rel="stylesheet"></noscript>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @if ($images)
        <link rel="preload" as="image" href="{{ $images[0] }}" @if ($srcset(0)) imagesrcset="{{ $srcset(0) }}" imagesizes="100vw" @endif>
    @endif
    <link rel="stylesheet" href="{{ asset('project.css') }}?v={{ filemtime(public_path('project.css')) }}">
    @if ($hasMap)
        <link rel="stylesheet" href="{{ asset('vendor/leaflet.css') }}?v={{ filemtime(public_path('vendor/leaflet.css')) }}">
    @endif
</head>
<body class="pp">

    <header class="pp-top">
        <a class="pp-brand" href="{{ site_url('/') }}">
            <span class="pp-brand__name">{{ bival('brand_name') }}</span>
            <span class="pp-brand__sub">{{ bival('brand_sub') }}</span>
        </a>
        <nav class="pp-top__nav">
            <a class="pp-top__link" href="{{ site_url('/') }}#projects">
                <span class="pp-arrow pp-arrow--back" aria-hidden="true">←</span>
                <span>{{ bival('pp_all_projects') }}</span>
            </a>
            <a class="pp-lang" href="{{ $project->pageUrl($other) }}" hreflang="{{ $other }}" lang="{{ $other }}" dir="auto">{{ $isKu ? 'English' : 'کوردی' }}</a>
        </nav>
    </header>

    <main>
        <section class="pp-hero">
            @if ($images)
                <img class="pp-hero__img" src="{{ $images[0] }}" @if ($srcset(0)) srcset="{{ $srcset(0) }}" sizes="100vw" @endif
                     alt="{{ $name }}" fetchpriority="high" decoding="async"
                     style="object-position: {{ $project->coverPosition() }}">
            @endif
            <div class="pp-hero__shade" aria-hidden="true"></div>
            <div class="pp-hero__text">
                <p class="pp-hero__kicker">
                    <span class="pp-num">{{ $project->num }}</span>
                    @if ($categories)<span class="pp-hero__cats"><bdi>{{ implode(' · ', $categories) }}</bdi></span>@endif
                </p>
                <h1 class="pp-hero__title"><bdi>{{ $name }}</bdi></h1>
                @php $where = collect([$project->location, $project->year])->filter(); @endphp
                @if ($where->isNotEmpty())<p class="pp-hero__meta">{!! $where->map(fn ($part) => '<bdi>'.e($part).'</bdi>')->implode(' · ') !!}</p>@endif
            </div>
            @if (count($images) > 1)
                <button class="pp-hero__photos" type="button" data-open="0">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="m21 16-5-5-9 8"/></svg>
                    <span>{{ count($images) }}</span>
                </button>
            @endif
        </section>

        <section class="pp-body">
            <div class="pp-story">
                <p class="pp-status pp-status--{{ $project->statusClass() }}">
                    <span class="pp-status__dot" aria-hidden="true"></span>{{ $project->statusLabel($lang) }}
                </p>
                @if (filled($desc))
                    <p class="pp-desc" dir="auto">{{ $desc }}</p>
                @endif
                @if (filled($narrative))
                    <blockquote class="pp-quote" dir="auto">{{ $narrative }}</blockquote>
                @endif
                @if (! empty($project->materials))
                    <h2 class="pp-label">{{ bival('pp_materials') }}</h2>
                    <ul class="pp-chips">
                        @foreach ($project->materials as $material)
                            <li><bdi>{{ $material }}</bdi></li>
                        @endforeach
                    </ul>
                @endif
                <button class="pp-share" type="button" id="ppShare"
                        data-title="{{ $title }}" data-url="{{ site_url($path) }}" data-copied="{{ bival('pp_copied') }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="18" cy="5" r="2.4"/><circle cx="6" cy="12" r="2.4"/><circle cx="18" cy="19" r="2.4"/><path d="m8.2 10.8 7.6-4.4M8.2 13.2l7.6 4.4"/></svg>
                    <span class="pp-share__label">{{ bival('pp_share') }}</span>
                </button>
            </div>

            @if ($facts)
                <dl class="pp-facts">
                    @foreach ($facts as $label => $value)
                        <div class="pp-fact">
                            <dt>{{ $label }}</dt>
                            <dd><bdi>{{ $value }}</bdi></dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </section>

        @if (count($images) > 1)
            @php $rest = count($images) - 1; @endphp
            <section class="pp-gallery" aria-label="{{ $name }}">
                @foreach (array_slice($images, 1, null, true) as $i => $src)
                    @php
                        // rhythm of one wide, then a pair; a photo that would
                        // be left alone at the end of a pair takes the width
                        $k = $loop->index;
                        $wide = $k % 3 === 0 || ($k === $rest - 1 && $k % 3 === 1);
                    @endphp
                    <figure class="pp-shot{{ $wide ? ' pp-shot--wide' : '' }}">
                        <button class="pp-shot__open" type="button" data-open="{{ $i }}" aria-label="{{ $name }} — {{ $i + 1 }} / {{ count($images) }}">
                            <img src="{{ $thumbs[$i] ?? $src }}" @if ($srcset($i)) srcset="{{ $srcset($i) }}" sizes="{{ $wide ? '(min-width: 1400px) 1320px, 100vw' : '(min-width: 1400px) 660px, (min-width: 760px) 50vw, 100vw' }}" @endif
                                 alt="{{ $name }} — {{ $i + 1 }}" loading="lazy" decoding="async">
                        </button>
                    </figure>
                @endforeach
            </section>
        @endif

        @if ($hasMap)
            <section class="pp-map">
                <h2 class="pp-label">{{ bival('pp_on_map') }}</h2>
                <div class="pp-map__frame">
                    <div class="pp-map__canvas" id="ppMap" data-lat="{{ $project->lat }}" data-lng="{{ $project->lng }}" data-tiles="{{ $tiles }}"></div>
                    <a class="pp-map__open" href="https://www.google.com/maps/search/?api=1&amp;query={{ $project->lat }},{{ $project->lng }}" target="_blank" rel="noopener">
                        <span>{{ bival('contact_open_maps') }}</span> ↗&#xFE0E;
                    </a>
                    @if ($project->location)
                        <p class="pp-map__place"><span class="pp-map__dot" aria-hidden="true"></span><bdi>{{ $project->location }}</bdi></p>
                    @endif
                </div>
            </section>
        @endif

        <section class="pp-cta">
            <p class="pp-label">{{ bival('contact_label') }}</p>
            <h2 class="pp-cta__title">{{ bival('pp_cta_title') }}</h2>
            <p class="pp-cta__text">{{ bival('pp_cta_text') }}</p>
            <a class="pp-btn" href="{{ site_url('/') }}#contact">
                <span>{{ bival('pp_cta_button') }}</span>
                <span class="pp-arrow" aria-hidden="true">→</span>
            </a>
        </section>

        @php
            // With only two projects the previous one is also the next — say it once.
            $pager = array_filter(['prev' => $prev && ! $prev->is($next) ? $prev : null, 'next' => $next]);
        @endphp
        @if ($pager)
            <nav class="pp-pager" aria-label="{{ bival('nav_projects') }}">
                @foreach ($pager as $dir => $p)
                    <a class="pp-pager__item pp-pager__item--{{ $dir }}" href="{{ $p->pageUrl($lang) }}">
                        <img src="{{ $p->coverThumbUrl() }}" alt="" loading="lazy" decoding="async" style="object-position: {{ $p->coverPosition() }}">
                        <span class="pp-pager__shade" aria-hidden="true"></span>
                        <span class="pp-pager__dir">
                            @if ($dir === 'prev')<span class="pp-arrow pp-arrow--back" aria-hidden="true">←</span>@endif
                            {{ bival($dir === 'prev' ? 'pp_prev' : 'pp_next') }}
                            @if ($dir === 'next')<span class="pp-arrow" aria-hidden="true">→</span>@endif
                        </span>
                        <span class="pp-pager__name"><bdi>{{ bi($p->name, $p->name_ku) }}</bdi></span>
                    </a>
                @endforeach
            </nav>
        @endif

        @if ($more->isNotEmpty())
            <section class="pp-more">
                <h2 class="pp-label">{{ bival('pp_more') }}</h2>
                <div class="pp-more__grid">
                    @foreach ($more as $p)
                        <a class="pp-card" href="{{ $p->pageUrl($lang) }}">
                            <span class="pp-card__img">
                                <img src="{{ $p->coverThumbUrl() }}" alt="" loading="lazy" decoding="async" style="object-position: {{ $p->coverPosition() }}">
                            </span>
                            <span class="pp-card__num">{{ $p->num }}</span>
                            <span class="pp-card__name"><bdi>{{ bi($p->name, $p->name_ku) }}</bdi></span>
                            <span class="pp-card__meta">{!! collect([...$p->categoryLabelsIn($lang), $p->cityLabel(), $p->endYear()])->filter()->map(fn ($part) => '<bdi>'.e($part).'</bdi>')->implode(' · ') !!}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </main>

    <footer class="pp-foot">
        <div class="pp-foot__brand">
            <a href="{{ site_url('/') }}">{{ bival('footer_logo') }}</a>
            <span>{{ bival('footer_tagline') }}</span>
        </div>
        @php
            $email = setting('contact_email_new', 'Info@arammizzuri.com');
            $phone = setting('contact_phone', '+964 (0) 782 445 4414');
        @endphp
        <div class="pp-foot__links">
            <a href="mailto:{{ $email }}">{{ $email }}</a>
            <a href="tel:{{ preg_replace('/[^\d+]/', '', str_replace('(0)', '', $phone)) }}" dir="ltr">{{ $phone }}</a>
            @foreach (socials() as $s)
                @if (preg_match('#^https?://#i', trim($s['url'])))
                    <a href="{{ trim($s['url']) }}" target="_blank" rel="noopener">{{ $s['label'] }}</a>
                @endif
            @endforeach
        </div>
    </footer>

    {{-- Full-screen photo viewer for the gallery (project.js). --}}
    <dialog class="pp-lightbox" id="ppLightbox" data-images='@json($images)' aria-label="{{ $name }}">
        <img class="pp-lightbox__img" alt="{{ $name }}">
        <span class="pp-lightbox__count" aria-live="polite"></span>
        <button class="pp-lightbox__btn pp-lightbox__close" type="button" aria-label="Close">✕</button>
        <button class="pp-lightbox__btn pp-lightbox__prev" type="button" aria-label="Previous image">←</button>
        <button class="pp-lightbox__btn pp-lightbox__next" type="button" aria-label="Next image">→</button>
    </dialog>

    @if ($hasMap)
        <script src="{{ asset('vendor/leaflet.js') }}?v={{ filemtime(public_path('vendor/leaflet.js')) }}"></script>
    @endif
    <script src="{{ asset('project.js') }}?v={{ filemtime(public_path('project.js')) }}" defer></script>
</body>
</html>
