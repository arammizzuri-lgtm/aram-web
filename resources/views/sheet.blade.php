{{-- A sheet of the site's index with a page of its own: /map, /services,
     /process and /contact (PortfolioController::sheet). The homepage's own
     bar, footer, script and project overlay, so its section works here just
     as it does there; above it a title block, as on a drawing (its number in
     the index, the studio, the place), and below it what the page adds and
     the way on to the next sheet.
     $sheet: map, services, process or contact. --}}
@php
    use App\Support\Services;
    $menu = \App\Support\SiteMenu::items();
    $at = collect($menu)->search(fn ($s) => $s['key'] === $sheet);
    $me = $menu[$at];
    $prev = $menu[$at - 1] ?? null;
    $next = $menu[$at + 1] ?? $menu[0];
    $lastCode = end($menu)['code'];
    [$labelKey, $titleKey, $introKey] = [
        'map' => ['map_label', 'map_title', 'map_intro'],
        'services' => ['services_label', 'services_title', 'services_intro'],
        'process' => ['process_label', 'process_title', 'process_intro'],
        'contact' => ['contact_label', 'contact_title', 'contact_intro'],
    ][$sheet];
    $brand = trim(explode('|', bival('seo_title'))[0]);
    // the title allows <br> on screen; the tab and the link preview read it as one line
    $plainTitle = trim(preg_replace('/\s+/u', ' ', strip_tags(preg_replace('/<br\s*\/?>/i', ' ', bival($titleKey)))));
    $pageTitle = $plainTitle.' — '.$brand;
    $description = \Illuminate\Support\Str::limit(bival($introKey), 160);
    $ogImage = asset('og-image.jpg').'?v='.filemtime(public_path('og-image.jpg'));
    $v = fn (string $f) => asset($f).'?v='.filemtime(public_path($f));
@endphp
<!DOCTYPE html>
<html lang="{{ site_lang() }}" dir="{{ site_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $description }}">
    @include('partials.seo-alternates', ['path' => '/'.$sheet])
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Aram Mizuri Architecture">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ site_url('/'.$sheet) }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta name="twitter:card" content="summary_large_image">
    @if ($sheet === 'contact')
        @include('partials.studio-schema', ['image' => $ogImage, 'description' => $description])
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @php $fontsHref = 'https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Vazirmatn:wght@200;300;400;500;600;700&display=swap'; @endphp
    <link href="{{ $fontsHref }}" rel="stylesheet" media="print" onload="this.media='all';this.onload=null">
    <noscript><link href="{{ $fontsHref }}" rel="stylesheet"></noscript>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ $v('style.css') }}">
    <link rel="stylesheet" href="{{ $v('site-index.css') }}">
    @if (site_rtl())<link rel="stylesheet" href="{{ $v('rtl-type.css') }}">@endif
    <link rel="stylesheet" href="{{ $v('engage.css') }}">
    <link rel="stylesheet" href="{{ $v('sheet.css') }}">
    @include('partials.kurdish-font')
</head>
{{-- is-loaded from the start: there is no loader here to wait for --}}
<body class="is-loaded sheet sheet--{{ $sheet }}">
    <div class="cursor" id="cursor"></div>
    <div class="cursor-follower" id="cursorFollower"></div>

@include('home.nav')
    @include('partials.site-index', ['sheets' => $menu])

    <main id="main">
        {{-- The title block: the sheet's number, its title and what it is for;
             along its foot, the block a drawing carries in its corner. --}}
        <header class="sheet-hero">
            <div class="sheet-hero__bg" aria-hidden="true">
                <span class="sheet-hero__aurora"></span>
                <span class="sheet-hero__grid"></span>
                <span class="sheet-hero__ghost" dir="ltr">{{ $me['code'] }}</span>
            </div>
            <div class="sheet-hero__inner">
                <p class="sheet-hero__kicker">
                    <span class="sheet-hero__code" dir="ltr">{{ $me['code'] }}</span>
                    <span class="sheet-hero__rule" aria-hidden="true"></span>
                    <span class="sheet-hero__label">{{ bival($labelKey) }}</span>
                </p>
                <h1 class="sheet-hero__title">{!! bival($titleKey) !!}</h1>
                <p class="sheet-hero__intro">{{ bival($introKey) }}</p>
                @if ($sheet === 'contact')
                    {{-- the time in Erbil now (script.js keeps it), for whoever writes from far away --}}
                    <p class="sheet-hero__clock"><span class="hero__clock-dot" aria-hidden="true"></span> {{ bival('ui_clock_city') }} <time id="heroClock" dir="ltr">--:--:--</time></p>
                @endif
            </div>
            <dl class="sheet-hero__block">
                <div class="sheet-hero__cell">
                    <dt>{{ bival('pj_sheet') }}</dt>
                    <dd dir="ltr">{{ $me['code'] }} <span class="sheet-hero__of">/ {{ $lastCode }}</span></dd>
                </div>
                <div class="sheet-hero__cell">
                    <dt>{{ bival('nav_studio') }}</dt>
                    <dd>{{ $brand }}</dd>
                </div>
                <div class="sheet-hero__cell">
                    <dt>{{ bival('landmarks_place') }}</dt>
                    <dd>{{ bival('nav_place') }}</dd>
                </div>
                <div class="sheet-hero__cell sheet-hero__cell--scale" aria-hidden="true">
                    {{-- a drawing's scale bar, in metres --}}
                    <svg class="sheet-hero__scale" viewBox="0 0 132 22" dir="ltr">
                        <rect x="1" y="3" width="20" height="5"/><rect x="41" y="3" width="20" height="5"/><rect x="81" y="3" width="50" height="5" class="is-open"/>
                        <rect x="21" y="3" width="20" height="5" class="is-open"/><rect x="61" y="3" width="20" height="5" class="is-open"/>
                        <text x="1" y="20">0</text><text x="38" y="20">5</text><text x="76" y="20">10</text><text x="122" y="20">20m</text>
                    </svg>
                </div>
            </dl>
        </header>

        @switch($sheet)
            @case('map')
@include('home.map')
                @php
                    // place by place, the most projects first; each knows its place in
                    // window.__SITE__.projects, which the map's pins are numbered by
                    $places = $projects->values()->map(fn ($p, $i) => [$p, $i])
                        ->filter(fn ($row) => $row[0]->cityLabel() !== '')
                        ->groupBy(fn ($row) => $row[0]->cityLabel())
                        ->sortBy(fn ($rows, $city) => [-$rows->count(), $city]);
                @endphp
                @if ($places->isNotEmpty())
                    <section class="sheet-places" aria-labelledby="sheetPlaces">
                        <div class="sheet-places__head">
                            <p class="section-label">{{ bival('map_label') }}</p>
                            <h2 class="sheet-places__title" id="sheetPlaces">{{ bival('map_places') }}</h2>
                            <p class="sheet-places__hint">{{ bival('map_places_hint') }}</p>
                        </div>
                        <ol class="sheet-places__list">
                            @foreach ($places as $city => $rows)
                                <li class="sheet-place">
                                    <span class="sheet-place__n" dir="ltr">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                    <h3 class="sheet-place__city">{{ $city }}</h3>
                                    <span class="sheet-place__count">{{ $rows->count() === 1 ? bival('pj_count_one') : str_replace('{n}', (string) $rows->count(), bival('pj_count')) }}</span>
                                    <ul class="sheet-place__projects">
                                        @foreach ($rows as [$p, $i])
                                            <li>
                                                @if ($p->lat !== null && $p->lng !== null)
                                                    <button type="button" class="sheet-chip" data-pin="{{ $i }}">
                                                        <span class="sheet-chip__num" dir="ltr">{{ $p->num }}</span><span dir="auto">{{ $p->tr('name') }}</span>
                                                        <svg class="sheet-chip__pin" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 15s5-4.6 5-8.5A5 5 0 0 0 3 6.5C3 10.4 8 15 8 15z"/><circle cx="8" cy="6.5" r="1.8"/></svg>
                                                    </button>
                                                @elseif ($p->hasPage())
                                                    <a class="sheet-chip" href="{{ $p->pageUrl(site_lang()) }}">
                                                        <span class="sheet-chip__num" dir="ltr">{{ $p->num }}</span><span dir="auto">{{ $p->tr('name') }}</span>
                                                    </a>
                                                @else
                                                    <span class="sheet-chip sheet-chip--plain"><span class="sheet-chip__num" dir="ltr">{{ $p->num }}</span><span dir="auto">{{ $p->tr('name') }}</span></span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endif
                @break

            @case('services')
                {{-- Each service a chapter: its number, what it is, and the work
                     that shows it — or, with none to show yet, its line drawing. --}}
                <section class="svc" aria-label="{{ bival('services_title') }}">
                    <ol class="svc__list">
                        @foreach (Services::all() as $i => $s)
                            @php
                                $go = Services::link($s, $projects);
                                $work = Services::projects($s, $projects)->take(3);
                            @endphp
                            <li class="svc-ch" id="service-{{ $i + 1 }}">
                                <span class="svc-ch__num" dir="ltr">0{{ $i + 1 }}</span>
                                <div class="svc-ch__text">
                                    <svg class="svc-ch__icon" viewBox="0 0 36 36" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $s['icon'] !!}</svg>
                                    <h2 class="svc-ch__name" dir="auto">{{ slot($s['en'], $s['ku']) }}</h2>
                                    <p class="svc-ch__desc" dir="auto">{{ slot($s['den'], $s['dku']) }}</p>
                                    <a class="svc-ch__go" href="{{ $go['href'] }}">
                                        <span>{{ slot($go['en'], $go['ku']) }}</span>
                                        <span class="svc-ch__arrow" aria-hidden="true">{{ $go['arrow'] }}&#xFE0E;</span>
                                    </a>
                                </div>
                                @if ($work->isNotEmpty())
                                    <div class="svc-ch__work svc-ch__work--{{ $work->count() }}">
                                        @foreach ($work as $p)
                                            <a class="svc-ch__shot" href="{{ $p->pageUrl(site_lang()) }}">
                                                <img src="{{ $p->coverThumbUrl() }}" alt="" loading="lazy" decoding="async" style="object-position: {{ $p->coverPosition() }}">
                                                <span class="svc-ch__shot-name" dir="auto">{{ $p->tr('name') }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="svc-ch__plate" aria-hidden="true">
                                        <svg viewBox="0 0 36 36" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">{!! $s['icon'] !!}</svg>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </section>
                @break

            @case('process')
@include('home.process')
                {{-- The client's private room (RoomController): what a client
                     follows there while the four phases run. --}}
                <section class="sheet-room" aria-labelledby="sheetRoom">
                    <div class="sheet-room__inner">
                        <div class="sheet-room__text">
                            <p class="section-label">{{ bival('process_room_label') }}</p>
                            <h2 class="sheet-room__title" id="sheetRoom">{{ bival('process_room_title') }}</h2>
                            <p class="sheet-room__lead">{{ bival('process_room_text') }}</p>
                        </div>
                        @php
                            $roomIcons = [
                                '<path d="M5 3h10l4 4v14H5z"/><path d="M15 3v4h4"/><path d="M8.5 14l2.2 2.2 4.3-4.6"/>',
                                '<path d="M4 8h3l1.6-2.2h6.8L17 8h3v11H4z"/><circle cx="12" cy="13.2" r="3.4"/>',
                                '<path d="M6 3h12v18l-2.4-1.6L13.2 21l-2.4-1.6L8.4 21 6 19.4z"/><path d="M9 8h6M9 11.5h6M9 15h3.5"/>',
                                '<rect x="3.5" y="5" width="17" height="15" rx="1.5"/><path d="M3.5 9.5h17M8 3v4M16 3v4"/><path d="M7.5 13.5h3M7.5 16.5h6"/>',
                            ];
                        @endphp
                        <ol class="sheet-room__list">
                            @foreach ($roomIcons as $n => $icon)
                                <li class="sheet-room__item">
                                    <span class="sheet-room__n" dir="ltr">0{{ $n + 1 }}</span>
                                    <svg class="sheet-room__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
                                    <span class="sheet-room__what">{{ bival('process_room_'.($n + 1)) }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </section>
@include('home.estimate')
                @break

            @case('contact')
@include('home.contact')
                {{-- Other ways in: a visit, the cost estimator, a sketch for the form above --}}
                <section class="sheet-ways" aria-labelledby="sheetWays">
                    <h2 class="sheet-ways__title" id="sheetWays">{{ bival('contact_more') }}</h2>
                    <div class="sheet-ways__grid">
                        <a class="sheet-way" href="{{ site_url('/visit') }}">
                            <svg class="sheet-way__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15" rx="1.5"/><path d="M3.5 9.5h17M8 3v4M16 3v4"/><path d="M9 14.5l2 2 4-4"/></svg>
                            <span class="sheet-way__name">{{ bival('visit_link') }}</span>
                            <span class="sheet-way__text">{{ bival('visit_text') }}</span>
                            <span class="sheet-way__arrow" aria-hidden="true">→</span>
                        </a>
                        @if (\App\Support\CostEstimate::enabled())
                            <a class="sheet-way" href="{{ site_url('/estimate') }}">
                                <svg class="sheet-way__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11l9-7 9 7"/><path d="M5.5 9.5V20h13V9.5"/><path d="M10 20v-5h4v5"/></svg>
                                <span class="sheet-way__name">{{ bival('est_band_title') }}</span>
                                <span class="sheet-way__text">{{ bival('est_band_text') }}</span>
                                <span class="sheet-way__arrow" aria-hidden="true">→</span>
                            </a>
                        @endif
                        <button type="button" class="sheet-way" data-sketch-open="attach">
                            <svg class="sheet-way__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20l4.2-1 10-10a2.1 2.1 0 0 0-3-3l-10 10z"/><path d="M13.5 7.5l3 3"/></svg>
                            <span class="sheet-way__name">{{ bival('sk_title') }}</span>
                            <span class="sheet-way__text">{{ bival('sk_hint') }}</span>
                            <span class="sheet-way__arrow" aria-hidden="true">→</span>
                        </button>
                    </div>
                </section>
                @break
        @endswitch

        {{-- On through the set: the sheet before and the one after (after the last, back to A-00) --}}
        <nav class="sheet-next" aria-label="{{ bival('nav_index_title') }}">
            @if ($prev)
                <a class="sheet-next__link sheet-next__link--prev" href="{{ $prev['href'] }}" rel="prev">
                    <span class="sheet-next__k"><span class="sheet-next__arrow" aria-hidden="true">←</span> {{ bival('sheet_prev') }}</span>
                    <span class="sheet-next__v"><span class="sheet-next__code" dir="ltr">{{ $prev['code'] }}</span> {{ $prev['label'] }}</span>
                </a>
            @endif
            <a class="sheet-next__link sheet-next__link--next" href="{{ $next['href'] }}" rel="next">
                <span class="sheet-next__k">{{ bival('sheet_next') }} <span class="sheet-next__arrow" aria-hidden="true">→</span></span>
                <span class="sheet-next__v"><span class="sheet-next__code" dir="ltr">{{ $next['code'] }}</span> {{ $next['label'] }}</span>
            </a>
        </nav>
    </main>

@include('home.footer')
@include('home.overlay')

    @include('partials.whatsapp-fab', ['text' => ['en' => \App\Support\SiteText::en('wa_text'), 'ku' => \App\Support\SiteText::second('wa_text')], 'hideOver' => '#map, #contact'])

    <script>window.__LEAFLET__ = {!! json_encode([
        'css' => $v('vendor/leaflet.css'), 'gestureCss' => $v('vendor/leaflet-gesture-handling.min.css'),
        'js' => $v('vendor/leaflet.js'), 'gesture' => $v('vendor/leaflet-gesture-handling.min.js'),
    ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!};</script>
    <script>window.__SITE__ = {!! json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!};</script>
    <script src="{{ $v('script.js') }}" defer></script>
    <script src="{{ $v('site-index.js') }}" defer></script>
    @if ($sheet === 'map')
        <script src="{{ $v('map-growth.js') }}" defer></script>
        <script>
            // a project in the list: up to the map, flown to its pin, its card opened
            document.addEventListener('click', function (e) {
                var chip = e.target.closest('[data-pin]');
                if (!chip) return;
                var idx = +chip.dataset.pin;
                document.getElementById('map').scrollIntoView({ behavior: 'smooth', block: 'start' });
                function fly() {
                    var m = window.siteProjectMap;
                    var pin = m && m.pins.find(function (p) { return p.idx === idx; });
                    if (!pin) return;
                    m.map.once('moveend', function () { pin.marker.fire('click'); });
                    m.map.flyTo(pin.marker.getLatLng(), Math.max(m.map.getZoom(), 12), { duration: 1.1 });
                }
                if (window.siteProjectMap) fly();
                else document.addEventListener('projectmapready', fly, { once: true });
            });
        </script>
    @endif
    @include('partials.sketch-assets')
    <script src="{{ $v('engage.js') }}" defer></script>
    @include('partials.saved-script')
</body>
</html>
