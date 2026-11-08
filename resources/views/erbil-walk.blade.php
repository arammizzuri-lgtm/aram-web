@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\WalkStop> $stops */
    $lang = site_lang();
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $title = bival('walk_title').' — '.bival('walk_label').' — '.$brand;
    $description = \Illuminate\Support\Str::limit(bival('walk_intro'), 160);
    $tiles = carto_tiles('dark_all');
    $digits = fn (string $s) => $lang === 'en' ? $s : strtr($s, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
    // "350 m" / "1.2 km", in the page's digits
    $dist = function (int $m) use ($lang, $digits) {
        $text = $m < 1000 ? (round($m / 10) * 10).($lang === 'en' ? ' m' : ($lang === 'ar' ? ' م' : ' م')) : number_format($m / 1000, 1).($lang === 'en' ? ' km' : ($lang === 'ku' ? ' کم' : ' كم'));
        return $digits($text);
    };
    $pins = $stops->filter(fn ($s) => $s->lat !== null)->map(fn ($s, $i) => ['n' => $i + 1, 'lat' => $s->lat, 'lng' => $s->lng, 'name' => $s->tr('name')])->values();
    $schema = ['@context' => 'https://schema.org', '@type' => 'TouristTrip', 'name' => bival('walk_title'), 'description' => $description,
        'url' => site_url('/erbil-walk'), 'inLanguage' => $lang, 'provider' => \App\Support\Architect::reference(),
        'touristType' => 'Architecture',
        'itinerary' => ['@type' => 'ItemList', 'itemListElement' => $stops->map(fn ($s, $i) => ['@type' => 'ListItem', 'position' => $i + 1,
            'item' => array_filter(['@type' => 'TouristAttraction', 'name' => $s->tr('name'), 'description' => $s->tr('note'),
                'image' => $s->imageUrl() ? url($s->imageUrl()) : null,
                'geo' => $s->lat !== null ? ['@type' => 'GeoCoordinates', 'latitude' => $s->lat, 'longitude' => $s->lng] : null])])->all()]];
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ site_rtl() ? 'rtl' : 'ltr' }}">
<head>
    @include('partials.page-head', ['path' => '/erbil-walk', 'css' => 'walk.css'])
    @if ($tiles && $pins->isNotEmpty())
        <link rel="stylesheet" href="{{ asset('vendor/leaflet.css') }}?v={{ filemtime(public_path('vendor/leaflet.css')) }}">
    @endif
</head>
<body class="pp pp--walk">
    @include('partials.page-top', ['otherUrl' => null])

    <main>
        <header class="wk-hero">
            <p class="pp-label">{{ bival('walk_label') }}</p>
            <h1 class="wk-hero__title">{{ bival('walk_title') }}</h1>
            <p class="wk-hero__intro">{{ bival('walk_intro') }}</p>
            @if ($stops->isNotEmpty())
                <p class="wk-hero__total">{{ str_replace(['{n}', '{d}'], [$digits((string) $stops->count()), $dist($total)], bival('walk_total')) }}</p>
            @endif
        </header>

        @if ($tiles && $pins->isNotEmpty())
            {{-- the route on the map: numbered pins, joined in order (walk.js) --}}
            <div class="wk-map" id="wkMap" data-tiles="{{ $tiles }}" data-pins='@json($pins)' aria-hidden="true"></div>
        @endif

        <ol class="wk-stops">
            @foreach ($stops as $i => $s)
                <li class="wk-stop" id="stop-{{ $i + 1 }}">
                    <figure class="wk-stop__img">
                        @if ($s->imageUrl())
                            <img src="{{ $s->imageUrl() }}" alt="{{ $s->tr('name') }}" loading="lazy" decoding="async">
                            @if ($s->image_credit)<figcaption>{{ $s->image_credit }}</figcaption>@endif
                        @else
                            <span class="wk-stop__soon">{{ bival('walk_photo_soon') }}</span>
                        @endif
                    </figure>
                    <div class="wk-stop__text">
                        <p class="wk-stop__num">{{ bival('walk_stop') }} {{ $digits(str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)) }}
                            @if ($i > 0 && $legs[$i] > 0)<span class="wk-stop__leg">· {{ str_replace('{d}', $dist($legs[$i]), bival('walk_from_prev')) }}</span>@endif
                        </p>
                        <h2 class="wk-stop__name" dir="auto">{{ $s->tr('name') }}</h2>
                        @if (filled($s->tr('built')))
                            <p class="wk-stop__built"><span>{{ bival('walk_built') }}</span> {{ $s->tr('built') }}</p>
                        @endif
                        @if (filled($s->tr('note')))
                            <p class="wk-stop__note" dir="auto">{{ $s->tr('note') }}</p>
                        @endif
                        @if ($s->lat !== null)
                            <a class="wk-stop__go" href="https://www.google.com/maps/dir/?api=1&destination={{ $s->lat }},{{ $s->lng }}&travelmode=walking" target="_blank" rel="noopener">{{ bival('walk_directions') }} <span class="pp-arrow" aria-hidden="true">↗</span></a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>

        <section class="wk-end">
            <p class="wk-end__by">{{ bival('walk_by') }}</p>
            <h2 class="wk-end__title">{{ bival('walk_cta') }}</h2>
            <div class="wk-end__actions">
                <a class="pp-btn" href="{{ site_url('/') }}#contact">{{ bival('walk_cta_btn') }} <span class="pp-arrow" aria-hidden="true">→</span></a>
                <a class="pp-btn pp-btn--line" href="{{ site_url('/projects') }}">{{ bival('nf_all') }}</a>
            </div>
        </section>
    </main>

    @include('partials.page-foot')
    @if ($tiles && $pins->isNotEmpty())
        <script src="{{ asset('vendor/leaflet.js') }}?v={{ filemtime(public_path('vendor/leaflet.js')) }}" defer></script>
        <script src="{{ asset('walk.js') }}?v={{ filemtime(public_path('walk.js')) }}" defer></script>
    @endif
</body>
</html>
