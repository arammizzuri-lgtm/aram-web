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
    <link rel="stylesheet" href="{{ asset('engage.css') }}?v={{ filemtime(public_path('engage.css')) }}">
    @php $quotesHaveVoices = $quotes->contains(fn ($q) => $q->showsVoiceBlock()); @endphp
    @if ($quotesHaveVoices)
        <link rel="stylesheet" href="{{ asset('voices.css') }}?v={{ filemtime(public_path('voices.css')) }}">
    @endif
    @include('partials.kurdish-font')
    @if ($hasMap)
        <link rel="stylesheet" href="{{ asset('vendor/leaflet.css') }}?v={{ filemtime(public_path('vendor/leaflet.css')) }}">
    @endif
</head>
<body class="pp">

    @include('partials.page-top', ['otherUrl' => $project->pageUrl($other)])

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
                    @if ($updates->isNotEmpty())
                        <a class="pp-status__updated" href="#progress">· {{ bival('pp_updated') }} {{ site_date($updates->first()->date, $lang, true) }}</a>
                    @endif
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
                {{-- Keep it (the heart — saved.js), or pass it on: on WhatsApp,
                     which asks whom to send it to, or anywhere else (engage.js). --}}
                @php
                    $shareText = str_replace(['{project}', '{url}'], [$name, site_url($path)], bival('share_wa_text'));
                @endphp
                <div class="pp-actions">
                    <button class="pp-act pp-act--save" type="button" data-save="{{ $project->slug }}" data-save-at="project" aria-pressed="false">
                        @include('partials.icon-heart')
                        <span data-save-label>{{ bival('save_add') }}</span>
                    </button>
                    <a class="pp-act pp-act--wa" href="https://wa.me/?text={{ rawurlencode($shareText) }}" target="_blank" rel="noopener"
                       data-share="whatsapp" data-share-at="project" data-share-project="{{ $project->slug }}">
                        @include('partials.icon-whatsapp')
                        <span>{{ bival('share_wa') }}</span>
                    </a>
                    @include('partials.share-link', ['shareClass' => 'pp-act', 'shareAt' => 'project', 'shareProject' => $project->slug,
                        'shareUrl' => site_url($path), 'shareTitle' => $title])
                </div>
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

        {{-- Render over photograph, with a slider between them. The range input
             lies over the whole frame, invisible: it is what the pointer drags
             and what the keyboard steps, so the slider is accessible with no
             code of its own; project.js only moves the dividing line. Held
             left-to-right in both languages, so "render" is always the left. --}}
        @if ($compare)
            <section class="pp-compare" id="render-vs-built">
                <h2 class="pp-label">{{ bival('pp_compare') }}</h2>
                <div class="pp-compare__frame" dir="ltr" style="--pos: 50%">
                    <img class="pp-compare__img" src="{{ $compare['after'] }}" alt="{{ $name }} — {{ bival('pp_built') }}" loading="lazy" decoding="async">
                    <div class="pp-compare__before" aria-hidden="true">
                        <img class="pp-compare__img" src="{{ $compare['before'] }}" alt="" loading="lazy" decoding="async">
                    </div>
                    <span class="pp-compare__tag pp-compare__tag--before">{{ bival('pp_render') }}</span>
                    <span class="pp-compare__tag pp-compare__tag--after">{{ bival('pp_built') }}</span>
                    <span class="pp-compare__handle" aria-hidden="true"></span>
                    <input class="pp-compare__range" type="range" min="0" max="100" value="50" step="0.5"
                           aria-label="{{ bival('pp_render') }} / {{ bival('pp_built') }}">
                </div>
                <p class="pp-compare__hint">{{ bival('pp_compare_hint') }}</p>
            </section>
        @endif

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

        {{-- Film and 360° tours. Each shows a poster and a button; the player
             (and everything it loads from YouTube, Vimeo…) arrives only when
             the visitor presses it — project.js swaps the poster for it. --}}
        @if ($media)
            <section class="pp-media" id="film">
                <h2 class="pp-label">{{ bival('pp_film') }}</h2>
                <div class="pp-media__grid{{ count($media) === 1 ? ' pp-media__grid--one' : '' }}">
                    @foreach ($media as $m)
                        @php $caption = $lang === 'ku' && filled($m['caption_ku']) ? $m['caption_ku'] : $m['caption_en']; @endphp
                        <figure class="pp-embed pp-embed--{{ $m['kind'] }}">
                            <div class="pp-embed__frame" data-embed="{{ $m['embed'] }}" data-title="{{ $caption ?: $name }}">
                                <img class="pp-embed__poster" src="{{ $m['thumb'] ?? ($thumbs[0] ?? $images[0] ?? '') }}" alt="" loading="lazy" decoding="async">
                                <button class="pp-embed__play" type="button">
                                    @if ($m['kind'] === 'tour')
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="9" ry="3.6"/><path d="M12 3v18"/></svg>
                                    @else
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13l11-6.5z"/></svg>
                                    @endif
                                    <span>{{ bival($m['kind'] === 'tour' ? 'pp_tour' : 'pp_play') }}</span>
                                </button>
                            </div>
                            @if (filled($caption))
                                <figcaption dir="auto">{{ $caption }}</figcaption>
                            @endif
                        </figure>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Construction progress, newest first: a dated line and the site photos
             of each visit. The photos open in the same viewer as the gallery,
             as a set of their own (data-images). --}}
        @if ($updates->isNotEmpty())
            <section class="pp-progress" id="progress">
                <h2 class="pp-label">{{ bival('pp_progress') }}</h2>
                <ol class="pp-timeline">
                    @foreach ($updates as $u)
                        @php $big = $u->imageUrls('lg'); $small = $u->imageUrls('thumb'); @endphp
                        <li class="pp-step">
                            <time class="pp-step__date" datetime="{{ $u->date->toDateString() }}">{{ site_date($u->date, $lang) }}</time>
                            @if (filled($u->title($lang)))
                                <h3 class="pp-step__title" dir="auto">{{ $u->title($lang) }}</h3>
                            @endif
                            @if (filled($u->note($lang)))
                                <p class="pp-step__note" dir="auto">{{ $u->note($lang) }}</p>
                            @endif
                            @if ($big)
                                <div class="pp-step__photos" data-images='@json($big)'>
                                    @foreach ($small as $i => $src)
                                        <button class="pp-step__photo" type="button" data-open="{{ $i }}" aria-label="{{ site_date($u->date, $lang) }} — {{ $i + 1 }} / {{ count($small) }}">
                                            <img src="{{ $src }}" alt="" loading="lazy" decoding="async">
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        @if ($quotes->isNotEmpty())
            <section class="pp-quotes">
                <h2 class="pp-label">{{ bival('quotes_label') }}</h2>
                @foreach ($quotes as $q)
                    <figure class="pp-quote-card{{ $q->hasMedia() ? ' has-cv' : '' }}">
                        @include('partials.client-voice', ['cvQuote' => $q])
                        <blockquote dir="auto">{{ $q->quote($lang) }}</blockquote>
                        <figcaption>
                            <span class="pp-quote-card__name"><bdi>{{ $q->personName($lang) }}</bdi></span>
                            @if (filled($q->attribution($lang)))
                                <span class="pp-quote-card__role"><bdi>{{ $q->attribution($lang) }}</bdi></span>
                            @endif
                        </figcaption>
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
            @php
                // the chat opens already naming this project
                $waText = fn (string $l) => str_replace('{project}', $l === 'ku' ? ($project->name_ku ?: $project->name) : $project->name,
                    $l === 'ku' ? \App\Support\SiteText::ku('wa_text_project') : \App\Support\SiteText::en('wa_text_project'));
            @endphp
            <div class="pp-cta__actions">
                {{-- the form on the homepage, told which project this was about --}}
                <a class="pp-btn" href="{{ site_url('/') }}?project={{ $project->slug }}#contact">
                    <span>{{ bival('pp_cta_button') }}</span>
                    <span class="pp-arrow" aria-hidden="true">→</span>
                </a>
                @if ($wa = whatsapp_url($waText($lang)))
                    <a class="pp-btn pp-btn--wa" href="{{ $wa }}" target="_blank" rel="noopener" data-wa="project" data-wa-project="{{ $project->slug }}">
                        @include('partials.icon-whatsapp')
                        <span>{{ bival('wa_cta') }}</span>
                    </a>
                @endif
            </div>
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

    @include('partials.page-foot')

    {{-- Full-screen photo viewer for the gallery (project.js). --}}
    <dialog class="pp-lightbox" id="ppLightbox" data-images='@json($images)' aria-label="{{ $name }}">
        <img class="pp-lightbox__img" alt="{{ $name }}">
        <span class="pp-lightbox__count" aria-live="polite"></span>
        <button class="pp-lightbox__btn pp-lightbox__close" type="button" aria-label="Close">✕</button>
        <button class="pp-lightbox__btn pp-lightbox__prev" type="button" aria-label="Previous image">←</button>
        <button class="pp-lightbox__btn pp-lightbox__next" type="button" aria-label="Next image">→</button>
    </dialog>

    @include('partials.whatsapp-fab', ['text' => ['en' => $waText('en'), 'ku' => $waText('ku')], 'hideOver' => '.pp-cta', 'waProject' => $project->slug])

    @if ($hasMap)
        <script src="{{ asset('vendor/leaflet.js') }}?v={{ filemtime(public_path('vendor/leaflet.js')) }}"></script>
    @endif
    <script src="{{ asset('project.js') }}?v={{ filemtime(public_path('project.js')) }}" defer></script>
    <script src="{{ asset('engage.js') }}?v={{ filemtime(public_path('engage.js')) }}" defer></script>
    @if ($quotesHaveVoices)
        <script src="{{ asset('voices.js') }}?v={{ filemtime(public_path('voices.js')) }}" defer></script>
    @endif
    @include('partials.saved-script')
</body>
</html>
